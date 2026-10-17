<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Operation\OperationClient;
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
        $fleetTab = $request->query('fleet_purpose', 'Regular');
        $query = Bus::query();
        if ($fleetTab === 'Inactive') {
            $query->where('status', 'Inactive')
                ->whereNotIn('bus_no', $this->unresolvedJobOrderBusNumbers());
        } else {
            $query->where('fleet_purpose', $fleetTab === 'Reserve' ? 'Reserve' : 'Regular')
                ->where(function ($q): void {
                    $q->where('status', '!=', 'Inactive')
                        ->orWhereIn('bus_no', $this->unresolvedJobOrderBusNumbers());
                });
        }

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
            if ($request->status === 'Under Maintenance') {
                $query->where(function ($query): void {
                    $query->where('status', 'Under Maintenance')
                        ->orWhereIn('bus_no', $this->unresolvedJobOrderBusNumbers());
                });
            } else {
                $query->where('status', $request->status)
                    ->whereNotIn('bus_no', $this->unresolvedJobOrderBusNumbers());
            }
        }

        // Sort only by known Bus columns; never interpolate untrusted SQL identifiers.
        $sort = in_array($request->query('sort'), ['bus_no', 'plate_no', 'bus_model', 'status'], true)
            ? $request->query('sort')
            : 'bus_no';
        $direction = $request->query('sort_direction', $request->query('direction')) === 'desc' ? 'desc' : 'asc';

        $buses = $query
            ->with(['operationClients', 'authorizedDrivers'])
            ->orderBy($sort, $direction)
            ->when($sort !== 'bus_no', fn ($builder) => $builder->orderBy('bus_no'))
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

        $maintenanceLockedBusNumbers = JobOrder::query()
            ->where('status', '!=', 'Completed')
            ->whereIn('bus_no', $buses->getCollection()->pluck('bus_no'))
            ->pluck('bus_no')
            ->map(fn ($busNo) => strtoupper(trim((string) $busNo)))
            ->flip();

        $buses->getCollection()->each(function (Bus $bus) use ($maintenanceLockedBusNumbers, $routeAssignments): void {
            $bus->display_route_name = $routeAssignments
                ->get($bus->id)
                ?->first()
                ?->tripSchedule
                ?->shuttleRoute
                ?->route_name;

            // Mirror the existing server-side Bus No. history guard in the UI.
            $bus->bus_no_locked = $this->hasHistoricalBusReferences($bus);
            $bus->fleet_purpose_locked = $routeAssignments->has($bus->id);
            $bus->deactivation_locked = $routeAssignments->has($bus->id);
            $bus->maintenance_locked = $maintenanceLockedBusNumbers->has(strtoupper(trim($bus->bus_no)));
            $bus->display_status = $bus->maintenance_locked ? 'Under Maintenance' : $bus->status;
        });

        $totalBuses = Bus::count();

        $activeBuses = Bus::where('status', 'Active')
            ->whereNotIn('bus_no', $this->unresolvedJobOrderBusNumbers())
            ->count();

        $underMaintenance = Bus::query()
            ->where(function ($query): void {
                $query->where('status', 'Under Maintenance')
                    ->orWhereIn('bus_no', $this->unresolvedJobOrderBusNumbers());
            })
            ->count();

        // Active buses with no unresolved scheduled trip assignment are
        // available for a new assignment. Past completed/cancelled trips do
        // not remove a bus from this count.
        $availableBuses = Bus::query()
            ->where('status', 'Active')
            ->where('fleet_purpose', 'Regular')
            ->whereNotIn('bus_no', $this->unresolvedJobOrderBusNumbers())
            ->whereDoesntHave('tripAssignments', function ($query): void {
                $query->whereHas('tripSchedule', fn ($schedule) => $schedule
                    ->whereNotIn('status', ['Cancelled', 'Completed', 'Missed'])
                    ->whereDate('trip_date', '>=', today(config('app.business_timezone', 'Asia/Manila'))->toDateString()));
            })
            ->count();

        $companyOptions = OperationClient::query()->orderBy('name')->pluck('name');

        return view('Operation.Shuttle_Bus_Management.bus-master-list', compact(
            'buses',
            'totalBuses',
            'activeBuses',
            'underMaintenance',
            'availableBuses',
            'companyOptions'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $allocation = $this->validateClientAllocation($request);
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
                'in:Active,Inactive',
            ],
            'fleet_purpose' => ['sometimes', 'in:Regular,Reserve'],
        ]);

        $bus = DB::transaction(function () use ($validated, $allocation): Bus {
            $createdBus = Bus::create([
                'bus_no' => strtoupper(trim($validated['bus_no'])),
                'plate_no' => $validated['plate_no'] ?? null,
                'bus_model' => $validated['bus_model'] ?? null,
                'year_model' => $validated['year_model'] ?? null,
                'capacity' => $validated['capacity'] ?? null,
                'route_grouping' => $validated['route_grouping'] ?? null,
                'status' => $validated['status'],
                'fleet_purpose' => $validated['fleet_purpose'] ?? 'Regular',
            ]);

            if ($createdBus->fleet_purpose === 'Reserve' && $allocation !== null) {
                throw ValidationException::withMessages([
                    'client_name' => 'Reserve buses are standby units and cannot have permanent client allocations.',
                ]);
            }
            if ($allocation !== null) $this->syncClientAllocation($createdBus, $allocation);
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
        $allocation = $this->validateClientAllocation($request);
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
            'status' => [
                'required',
                'in:Active,Inactive',
            ],
            'fleet_purpose' => ['sometimes', 'in:Regular,Reserve'],
        ]);

        $bus = DB::transaction(function () use ($bus, $validated, $allocation): Bus {
            $lockedBus = Bus::query()
                ->whereKey($bus->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedBus->status === 'Under Maintenance') {
                throw ValidationException::withMessages([
                    $validated['status'] === 'Active' ? 'status' : 'bus_no' => 'This bus is Under Maintenance. Its master-list information is locked until Maintenance releases it.',
                ]);
            }

            // Operations may deactivate a bus only when doing so cannot invalidate
            // an ongoing or future confirmed vehicle assignment.
            if ($validated['status'] === 'Inactive' && $lockedBus->status === 'Active') {
                if ($this->hasUnresolvedCurrentOrFutureAssignment($lockedBus)) {
                    throw ValidationException::withMessages([
                        'status' => 'Cannot deactivate this bus while it has an ongoing or future confirmed trip assignment. Reassign the trip first.',
                    ]);
                }

                if (JobOrder::query()
                    ->where('bus_no', $lockedBus->bus_no)
                    ->where('status', '!=', 'Completed')
                    ->lockForUpdate()
                    ->first() !== null) {
                    throw ValidationException::withMessages([
                        'status' => 'This bus has an ongoing maintenance Job Order. Only Maintenance can release it; Operation cannot deactivate it.',
                    ]);
                }
            }

            $newFleetPurpose = $validated['fleet_purpose'] ?? $lockedBus->fleet_purpose;

            if (
                $newFleetPurpose !== $lockedBus->fleet_purpose
                && $this->hasUnresolvedCurrentOrFutureAssignment($lockedBus)
            ) {
                throw ValidationException::withMessages([
                    'fleet_purpose' => 'Cannot change this bus between Regular and Reserve while it has an ongoing or future confirmed trip assignment. Resolve or reassign the trip first.',
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

            if ($newFleetPurpose === 'Reserve' && $allocation !== null) {
                throw ValidationException::withMessages([
                    'client_name' => 'Reserve buses must remain unallocated to clients.',
                ]);
            }

            $lockedBus->update([
                'bus_no' => $newBusNo,
                'plate_no' => $validated['plate_no'] ?? null,
                'bus_model' => $validated['bus_model'] ?? null,
                'year_model' => $validated['year_model'] ?? null,
                'capacity' => $validated['capacity'] ?? null,
                'status' => $validated['status'],
                'fleet_purpose' => $newFleetPurpose,
            ]);

            if ($newFleetPurpose === 'Reserve') {
                // Reserve is not a permanent client or driver resource.
                // The fleet-purpose switch was already checked for unresolved
                // active or future trips above; preserve historical trip rows.
                $lockedBus->operationClients()->detach();
                $lockedBus->authorizedDrivers()->detach();
                $lockedBus->update(['client_allocation_type' => null]);
            } elseif ($allocation !== null) {
                $lockedBus->load('operationClients');
                // Ignore harmless differences in case and whitespace when
                // comparing the submitted company against the saved allocation.
                // This is comparison-only: stored names are not rewritten.
                $normalizeClientName = static fn (string $name): string => mb_strtolower(
                    trim((string) preg_replace('/\s+/u', ' ', $name))
                );
                $oldNames = $lockedBus->operationClients->pluck('name')
                    ->map(fn ($name) => $normalizeClientName((string) $name))
                    ->sort()->values()->all();
                $newNames = array_map($normalizeClientName, $allocation['names']);
                sort($newNames);
                // Keep the existing allocation type (including legacy Shared)
                // when the submitted company name has not actually changed.
                // Trip schedules currently have no client/company link, so we
                // cannot infer a genuine conflict from a trip assignment alone.
                if ($oldNames !== $newNames) {
                    // Historical and future scheduled trips are not company locks.
                    // Only prevent reassignment while this bus is actually on a
                    // dispatched trip that has not arrived yet.
                    if ($oldNames !== [] && $this->hasOngoingDispatchedTrip($lockedBus)) {
                        throw ValidationException::withMessages([
                            'client_name' => 'This bus is currently operating a dispatched trip. Complete the trip before changing its company.',
                        ]);
                    }

                    $this->syncClientAllocation($lockedBus, $allocation);
                }
            }

            $this->pmsScheduleSynchronizer->renameBus($oldBusNo, $lockedBus);

            return $lockedBus;
        });

        $this->broadcastSystemDataUpdated('Operation', 'Bus', 'updated', $bus->id, 'A bus master-list record was updated.');

        session()->flash(
            'success',
            'Bus information updated successfully.'
        );

        return redirect()->back(fallback: '/bus-master-list');
    }

    public function deactivate(Bus $bus): RedirectResponse
    {
        DB::transaction(function () use ($bus): void {
            $lockedBus = Bus::query()
                ->whereKey($bus->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBus->status === 'Under Maintenance') {
                throw ValidationException::withMessages([
                    'bus' => 'This bus is Under Maintenance. Its master-list status is locked until Maintenance releases it.',
                ]);
            }

            if ($this->hasUnresolvedCurrentOrFutureAssignment($lockedBus)) {
                throw ValidationException::withMessages([
                    'bus' => 'Cannot deactivate this bus while it has an ongoing or future confirmed trip assignment. Reassign the trip first.',
                ]);
            }

            if (JobOrder::query()
                ->where('bus_no', $lockedBus->bus_no)
                ->where('status', '!=', 'Completed')
                ->lockForUpdate()
                ->first() !== null) {
                throw ValidationException::withMessages([
                    'bus' => 'This bus has an ongoing maintenance Job Order. Only Maintenance can release the bus; Operation cannot deactivate it.',
                ]);
            }

            $lockedBus->update(['status' => 'Inactive']);
        });

        $this->broadcastSystemDataUpdated('Operation', 'Bus', 'updated', $bus->id, "Bus {$bus->bus_no} was deactivated.");

        session()->flash(
            'success',
            "Bus {$bus->bus_no} has been deactivated successfully."
        );

        return redirect()->back(fallback: '/bus-master-list');
    }

    public function activate(Bus $bus): RedirectResponse
    {
        DB::transaction(function () use ($bus): void {
            $lockedBus = Bus::query()
                ->whereKey($bus->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBus->status === 'Under Maintenance') {
                throw ValidationException::withMessages([
                    'bus' => 'This bus is Under Maintenance. Its master-list status is locked until Maintenance releases it.',
                ]);
            }

            if (
                JobOrder::query()
                    ->where('bus_no', $lockedBus->bus_no)
                    ->where('status', '!=', 'Completed')
                    ->lockForUpdate()
                    ->first() !== null
            ) {
                throw ValidationException::withMessages([
                    'bus' => 'This bus cannot be activated while it has an ongoing maintenance Job Order.',
                ]);
            }

            $lockedBus->update(['status' => 'Active']);
        });

        $this->broadcastSystemDataUpdated('Operation', 'Bus', 'updated', $bus->id, "Bus {$bus->bus_no} was activated.");

        session()->flash(
            'success',
            "Bus {$bus->bus_no} has been activated successfully."
        );

        return redirect()->back(fallback: '/bus-master-list');
    }

    /**
     * Missing fields keep existing allocations unchanged (legacy imports).
     * A supplied allocation must explicitly identify the permitted companies.
     */
    private function validateClientAllocation(Request $request): ?array
    {
        // Blank on edit preserves existing clients. Multiple comma-separated
        // names use the existing bus <-> operation_clients pivot relationship.
        $data = $request->validate([
            'client_name' => ['nullable', 'string', 'max:1000'],
        ]);

        $raw = trim((string) ($data['client_name'] ?? ''));
        if ($raw === '') return null;

        $names = [];
        foreach (explode(',', $raw) as $value) {
            $name = trim(preg_replace('/\\s+/u', ' ', $value));
            if ($name === '' || mb_strlen($name) > 100) {
                throw ValidationException::withMessages([
                    'client_name' => 'Enter valid client names, each up to 100 characters, separated by commas.',
                ]);
            }
            $key = mb_strtolower($name);
            $names[$key] ??= $name;
        }

        if (count($names) > 20) {
            throw ValidationException::withMessages([
                'client_name' => 'A bus may have up to 20 client names.',
            ]);
        }

        return [
            'type' => count($names) > 1 ? 'Shared' : 'Exclusive',
            'names' => array_values($names),
        ];
    }

    private function syncClientAllocation(Bus $bus, array $allocation): void
    {
        $ids = [];
        foreach ($allocation['names'] as $name) {
            $ids[] = OperationClient::firstOrCreate(['name' => $name])->id;
        }

        $bus->update(['client_allocation_type' => $allocation['type']]);
        $bus->operationClients()->sync($ids);
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

        return redirect()->back(fallback: '/bus-master-list');
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

    private function hasOngoingDispatchedTrip(Bus $bus): bool
    {
        // Completed/history and future scheduled or Ready trips must not
        // prevent company changes. A Dispatched trip remains protected until
        // actual arrival is recorded.
        return TripAssignment::query()
            ->where('bus_id', $bus->id)
            ->whereHas('tripSchedule', function ($query): void {
                $query->where('status', 'Dispatched')
                    ->whereNull('actual_arrival_time');
            })
            ->exists();
    }

    private function hasUnresolvedCurrentOrFutureAssignment(Bus $bus): bool
    {
        // The bus row is locked by update() before this check. Assignment and
        // replacement flows also lock the selected bus, so a concurrent write
        // cannot bypass the purpose/status guard.
        return TripAssignment::query()
            ->where('bus_id', $bus->id)
            ->whereHas('tripSchedule', function ($query): void {
                $query->whereNotIn('status', ['Cancelled', 'Completed', 'Missed'])
                    ->whereDate('trip_date', '>=', today(config('app.business_timezone', 'Asia/Manila'))->toDateString());
            })
            ->exists();
    }

    private function unresolvedJobOrderBusNumbers()
    {
        return JobOrder::query()
            ->select('bus_no')
            ->where('status', '!=', 'Completed');
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
