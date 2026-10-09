<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\TripAssignment;
use App\Services\Maintenance\PmsScheduleSynchronizer;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function __construct(
        private readonly PmsScheduleSynchronizer $pmsScheduleSynchronizer
    ) {}

    public function index(Request $request)
    {
        $query = Bus::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('bus_no', 'like', "%{$search}%")
                    ->orWhere('plate_no', 'like', "%{$search}%")
                    ->orWhere('bus_model', 'like', "%{$search}%")
                    ->orWhere('route_grouping', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if (
            $request->filled('status')
            && $request->status !== 'All Status'
        ) {
            $query->where('status', $request->status);
        }

        $buses = $query
            ->orderBy('bus_no')
            ->paginate(10)
            ->withQueryString();

        // Display the next current/upcoming assigned route, not the broad
        // route_grouping value maintained on the bus master record.
        $routeAssignments = TripAssignment::query()
            ->with('tripSchedule.shuttleRoute')
            ->whereIn('bus_id', $buses->getCollection()->pluck('id'))
            ->whereHas('tripSchedule', fn ($query) => $query
                ->whereDate('trip_date', '>=', today(config('app.business_timezone', 'Asia/Manila'))->toDateString())
                ->whereNotIn('status', ['Cancelled', 'Completed', 'Missed']))
            ->get()
            ->filter(fn ($assignment) => $assignment->tripSchedule?->shuttleRoute !== null)
            ->sortBy(fn ($assignment) => $assignment->tripSchedule->trip_date->format('Y-m-d').' '.$assignment->tripSchedule->departure_time)
            ->groupBy('bus_id');

        $buses->getCollection()->each(function (Bus $bus) use ($routeAssignments): void {
            $bus->display_route_name = $routeAssignments
                ->get($bus->id)
                ?->first()
                ?->tripSchedule
                ?->shuttleRoute
                ?->route_name;

            // Mirror the existing server-side Bus No. history guard in the UI.
            $bus->bus_no_locked = $this->hasHistoricalBusReferences($bus);
        });

        $totalBuses = Bus::count();

        $activeBuses = Bus::where('status', 'Active')
            ->count();

        $underMaintenance = Bus::where(
            'status',
            'Under Maintenance'
        )->count();

        // Active buses with no unresolved scheduled trip assignment are
        // available for a new assignment. Past completed/cancelled trips do
        // not remove a bus from this count.
        $availableBuses = Bus::query()
            ->where('status', 'Active')
            ->whereDoesntHave('tripAssignments', function ($query): void {
                $query->whereHas('tripSchedule', fn ($schedule) => $schedule
                    ->whereNotIn('status', ['Cancelled', 'Completed', 'Missed'])
                    ->whereDate('trip_date', '>=', today(config('app.business_timezone', 'Asia/Manila'))->toDateString()));
            })
            ->count();

        return view('Operation.Shuttle_Bus_Management.bus-master-list', compact(
            'buses',
            'totalBuses',
            'activeBuses',
            'underMaintenance',
            'availableBuses'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bus_no' => [
                'required',
                'string',
                'max:100',
                'unique:buses,bus_no',
            ],
            'plate_no' => [
                'nullable',
                'string',
                'max:100',
            ],
            'bus_model' => [
                'nullable',
                'string',
                'max:255',
            ],
            'year_model' => [
                'nullable',
                'string',
                'max:20',
            ],
            'capacity' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'route_grouping' => [
                'nullable',
                'string',
                'max:255',
            ],
            'status' => [
                'required',
                'in:Active,Inactive,Under Maintenance',
            ],
        ]);

        $bus = DB::transaction(function () use ($validated): Bus {
            $createdBus = Bus::create([
                'bus_no' => strtoupper(trim($validated['bus_no'])),
                'plate_no' => $validated['plate_no'] ?? null,
                'bus_model' => $validated['bus_model'] ?? null,
                'year_model' => $validated['year_model'] ?? null,
                'capacity' => $validated['capacity'] ?? null,
                'route_grouping' => $validated['route_grouping'] ?? null,
                'status' => $validated['status'],
            ]);

            $this->pmsScheduleSynchronizer->ensureDefaultsFor($createdBus);

            return $createdBus;
        });

        $this->broadcastSystemDataUpdated('Operation', 'Bus', 'created', $bus->id, 'A bus was added to the master list.');

        session()->flash(
            'success',
            'Bus added successfully.'
        );

        return new RedirectResponse('/bus-master-list');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:5120',
            ],
        ]);

        $file = $request->file('csv_file');

        if (! $file || ! $file->isValid()) {
            session()->flash(
                'error',
                'Please upload a valid CSV file.'
            );

            return new RedirectResponse('/bus-master-list');
        }

        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            session()->flash(
                'error',
                'Unable to read the CSV file.'
            );

            return new RedirectResponse('/bus-master-list');
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

            session()->flash(
                'error',
                'The CSV file is empty.'
            );

            return new RedirectResponse('/bus-master-list');
        }

        $header = array_map(function ($value) {
            $value = preg_replace(
                '/^\xEF\xBB\xBF/',
                '',
                (string) $value
            );

            return strtolower(trim($value));
        }, $header);

        if (! in_array('bus_no', $header, true)) {
            fclose($handle);

            session()->flash(
                'error',
                'CSV must have a bus_no column.'
            );

            return new RedirectResponse('/bus-master-list');
        }

        $added = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $hasValue = count(array_filter(
                $row,
                fn ($value) => trim((string) $value) !== ''
            )) > 0;

            if (! $hasValue) {
                continue;
            }

            $row = array_pad(
                $row,
                count($header),
                null
            );

            $data = array_combine(
                $header,
                array_slice($row, 0, count($header))
            );

            $busNo = strtoupper(
                trim($data['bus_no'] ?? '')
            );

            if ($busNo === '') {
                $skipped++;

                continue;
            }

            $status = trim($data['status'] ?? '');

            if (! in_array(
                $status,
                ['Active', 'Inactive', 'Under Maintenance'],
                true
            )) {
                $status = 'Active';
            }

            $busData = [
                'plate_no' => trim(
                    $data['plate_no'] ?? ''
                ) ?: null,

                'bus_model' => trim(
                    $data['bus_model'] ?? ''
                ) ?: null,

                'year_model' => trim(
                    $data['year_model'] ?? ''
                ) ?: null,

                'capacity' => is_numeric(
                    $data['capacity'] ?? null
                )
                    ? (int) $data['capacity']
                    : null,

                'route_grouping' => trim(
                    $data['route_grouping'] ?? ''
                ) ?: null,

                'status' => $status,
            ];

            $existingBus = Bus::where(
                'bus_no',
                $busNo
            )->first();

            if ($existingBus) {
                DB::transaction(function () use ($busData, $existingBus): void {
                    $existingBus->update($busData);
                    $this->pmsScheduleSynchronizer->ensureDefaultsFor($existingBus);
                });
                $this->broadcastSystemDataUpdated('Operation', 'Bus', 'updated', $existingBus->id, 'A bus was updated by CSV import.');
                $updated++;
            } else {
                $createdBus = DB::transaction(function () use ($busData, $busNo): Bus {
                    $newBus = Bus::create(array_merge(
                        ['bus_no' => $busNo],
                        $busData
                    ));
                    $this->pmsScheduleSynchronizer->ensureDefaultsFor($newBus);

                    return $newBus;
                });
                $this->broadcastSystemDataUpdated('Operation', 'Bus', 'created', $createdBus->id, 'A bus was added by CSV import.');

                $added++;
            }
        }

        fclose($handle);

        session()->flash(
            'success',
            "CSV imported. Added: {$added}, Updated: {$updated}, Skipped: {$skipped}."
        );

        return new RedirectResponse('/bus-master-list');
    }

    public function update(Request $request, Bus $bus): RedirectResponse
    {
        $validated = $request->validate([
            'bus_no' => [
                'required',
                'string',
                'max:100',
                'unique:buses,bus_no,'.$bus->id,
            ],
            'plate_no' => [
                'nullable',
                'string',
                'max:100',
            ],
            'bus_model' => [
                'nullable',
                'string',
                'max:255',
            ],
            'year_model' => [
                'nullable',
                'string',
                'max:20',
            ],
            'capacity' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'route_grouping' => [
                'nullable',
                'string',
                'max:255',
            ],
            'status' => [
                'required',
                'in:Active,Inactive,Under Maintenance',
            ],
        ]);

        $bus = DB::transaction(function () use ($bus, $validated): Bus {
            $lockedBus = Bus::query()
                ->whereKey($bus->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedBus->status === 'Under Maintenance') {
                throw ValidationException::withMessages([
                    'bus' => 'This bus is Under Maintenance. Its master-list information is locked until Maintenance releases it.',
                ]);
            }

            $oldBusNo = $lockedBus->bus_no;
            $newBusNo = strtoupper(trim($validated['bus_no']));

            if (
                strcasecmp($oldBusNo, $newBusNo) !== 0
                && $this->hasHistoricalBusReferences($lockedBus)
            ) {
                throw ValidationException::withMessages([
                    'bus_no' => 'Bus Number cannot be changed after trip, GPS, fuel, purchase, or maintenance history exists.',
                ]);
            }

            if (
                $validated['status'] === 'Active'
                && $lockedBus->status !== 'Active'
                && JobOrder::query()
                    ->where('bus_no', $oldBusNo)
                    ->where('status', '!=', 'Completed')
                    ->lockForUpdate()
                    ->first() !== null
            ) {
                throw ValidationException::withMessages([
                    'status' => 'This bus cannot be set to Active while it has an ongoing maintenance Job Order.',
                ]);
            }

            $lockedBus->update([
                'bus_no' => $newBusNo,
                'plate_no' => $validated['plate_no'] ?? null,
                'bus_model' => $validated['bus_model'] ?? null,
                'year_model' => $validated['year_model'] ?? null,
                'capacity' => $validated['capacity'] ?? null,
                'route_grouping' => $validated['route_grouping'] ?? null,
                'status' => $validated['status'],
            ]);

            $this->pmsScheduleSynchronizer->renameBus($oldBusNo, $lockedBus);

            return $lockedBus;
        });

        $this->broadcastSystemDataUpdated('Operation', 'Bus', 'updated', $bus->id, 'A bus master-list record was updated.');

        session()->flash(
            'success',
            'Bus information updated successfully.'
        );

        return new RedirectResponse('/bus-master-list');
    }

    public function destroy(Bus $bus): RedirectResponse
    {
        $busId = $bus->id;
        $busNo = $bus->bus_no;

        DB::transaction(function () use ($bus, $busNo): void {
            $lockedBus = Bus::query()
                ->whereKey($bus->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBus->status === 'Under Maintenance') {
                throw ValidationException::withMessages([
                    'bus' => 'This bus is Under Maintenance and cannot be deleted until Maintenance releases it.',
                ]);
            }

            if ($this->hasTripBusHistory($lockedBus, true)) {
                throw ValidationException::withMessages([
                    'bus' => 'This bus cannot be deleted because trip history is linked to it.',
                ]);
            }

            if ($this->hasMaintenanceBusHistory($lockedBus, true)) {
                throw ValidationException::withMessages([
                    'bus' => 'This bus cannot be deleted because maintenance history is linked to it.',
                ]);
            }

            $lockedBus->delete();
            $this->pmsScheduleSynchronizer->removeUnusedSchedulesFor($busNo);
        });

        $this->broadcastSystemDataUpdated('Operation', 'Bus', 'deleted', $busId, 'A bus was removed from the master list.');

        session()->flash(
            'success',
            'Bus deleted successfully.'
        );

        return new RedirectResponse('/bus-master-list');
    }

    private function hasHistoricalBusReferences(Bus $bus): bool
    {
        if (
            $this->hasTripBusHistory($bus)
            || $this->hasMaintenanceBusHistory($bus)
        ) {
            return true;
        }

        $busNo = $bus->bus_no;

        return DB::table('fuel_reports')->where('bus_no', $busNo)->exists()
            || DB::table('purchase_requests')->where('bus_no', $busNo)->exists()
            || DB::table('gps_trip_records')->where('bus_no', $busNo)->exists()
            || DB::table('batch_uploads')->where('bus_no', $busNo)->exists();
    }

    private function hasTripBusHistory(Bus $bus, bool $lock = false): bool
    {
        $queries = [
            DB::table('trip_assignments')->where(function ($query) use ($bus): void {
                $query
                    ->where('bus_id', $bus->id)
                    ->orWhere('original_bus_id', $bus->id);
            }),
            DB::table('daily_driver_reports')->where('bus_id', $bus->id),
            DB::table('incidents')->where('bus_id', $bus->id),
            DB::table('incident_replacements')->where(function ($query) use ($bus): void {
                $query
                    ->where('original_bus_id', $bus->id)
                    ->orWhere('replacement_bus_id', $bus->id);
            }),
        ];

        return collect($queries)->contains(function ($query) use ($lock): bool {
            if ($lock) {
                $query->lockForUpdate();
            }

            return $query->first() !== null;
        });
    }

    private function hasMaintenanceBusHistory(Bus $bus, bool $lock = false): bool
    {
        $query = JobOrder::query()->where('bus_no', $bus->bus_no);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() !== null;
    }
}
