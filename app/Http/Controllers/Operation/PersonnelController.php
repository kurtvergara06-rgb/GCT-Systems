<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Driver;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Mechanic;
use App\Models\Operation\TripAssignment;
use App\Support\TableSort;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PersonnelController extends Controller
{
    use SystemDataUpdateBroadcaster;

    /**
     * Controlled mechanic skill categories.
     *
     * The aliases keep older single-value records compatible while the UI
     * presents cleaner, workshop-oriented specialization names.
     */
    private const MECHANIC_SPECIALIZATIONS = [
        'Aircon' => ['Air Conditioning (HVAC)', 'Air Conditioning', 'HVAC', 'A/C', 'AC'],
        'Bodyworks' => ['Body & Chassis Repair', 'Body Work', 'Body Repair', 'Chassis'],
        'Brakes' => ['Brake System', 'Brake'],
        'Diagnostics' => ['Diagnostics & Troubleshooting', 'Diagnostic', 'Troubleshooting'],
        'Electrical' => ['Electrical & Electronics', 'Electronics', 'Auto Electrical'],
        'Engine' => ['Engine & Powertrain', 'Powertrain'],
        'Fuel System' => ['Diesel Fuel System', 'Diesel Fuel', 'Fuel Injection'],
        'Lubrication' => ['Lubrication & Fluid Service', 'Oil', 'Oil Change', 'Fluids', 'Fluid Service'],
        'Preventive Maintenance' => ['PMS', 'Preventive'],
        'Suspension' => ['Steering & Suspension', 'Steering'],
        'Tires & Wheels' => ['Tire & Wheel Service', 'Tires', 'Tyres', 'Wheels', 'Tire', 'Wheel Service'],
        'Transmission' => ['Transmission & Drivetrain', 'Drivetrain'],
        'Welding & Fabrication' => ['Welding', 'Fabrication'],
    ];

    public function drivers(Request $request): View
    {
        $query = Driver::query();

        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('employment_status', 'Active')->count(),
            'inactive' => (clone $query)->where('employment_status', 'Inactive')->count(),
            'authorized' => (clone $query)
                ->where('employment_status', 'Active')
                ->whereHas('authorizedBuses')
                ->count(),
        ];

        $driversQuery = $query
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->search);
                $query->where(function ($nested) use ($search): void {
                    $nested->where('driver_id', 'like', "%{$search}%")
                        ->orWhere('driver_name', 'like', "%{$search}%")
                        ->orWhere('shift', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->where('employment_status', $request->query('tab') === 'history' ? 'Inactive' : 'Active')
            ->when($request->query('bus_authorization') === 'assigned', fn ($query) => $query->whereHas('authorizedBuses'))
            ->when($request->query('bus_authorization') === 'unassigned', fn ($query) => $query->whereDoesntHave('authorizedBuses'));
        TableSort::apply($driversQuery, $request, ['driver_name' => 'driver_name', 'driver_id' => 'driver_id', 'shift' => 'shift', 'employment_status' => 'employment_status'], 'driver_name');
        $drivers = $driversQuery
            ->with('authorizedBuses')
            ->withExists('attendances')
            ->paginate(12)
            ->withQueryString();

        $driverIds = $drivers->getCollection()->pluck('driver_id');
        $lockedDriverIds = $driverIds->isEmpty() ? collect() : TripAssignment::query()
            ->whereIn('driver_id', $driverIds)
            ->whereHas('tripSchedule', fn ($query) => $query->whereNotIn('status', ['Cancelled', 'Completed']))
            ->pluck('driver_id');
        $drivers->getCollection()->each(function (Driver $driver) use ($lockedDriverIds): void {
            $driver->deactivation_locked = $lockedDriverIds->contains($driver->driver_id);
        });

        $availableBuses = Bus::query()->orderBy('bus_no')->get(['id', 'bus_no', 'plate_no']);
        $activeBusOwners = Driver::query()
            ->where('employment_status', 'Active')
            ->with('authorizedBuses:id')
            ->get()
            ->flatMap(fn (Driver $driver) => $driver->authorizedBuses->map(
                fn ($bus) => ['bus_id' => $bus->id, 'owner_id' => $driver->id, 'owner_name' => $driver->driver_name]
            ))
            ->keyBy('bus_id');
        return view('Operation.Personnel Management.driver_master_list', compact('drivers', 'stats', 'availableBuses', 'activeBusOwners'));
    }

    public function mechanics(Request $request): View
    {
        $query = Mechanic::query();
        $recordedSpecializations = $this->recordedMechanicSpecializations();
        $specializationOptions = collect(array_keys(self::MECHANIC_SPECIALIZATIONS));

        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('employment_status', 'Active')->count(),
            'inactive' => (clone $query)->where('employment_status', 'Inactive')->count(),
            'specializations' => $recordedSpecializations->count(),
        ];

        $mechanicsQuery = $query
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->search);
                $specializationTerms = $this->specializationSearchTerms($search);

                $query->where(function ($nested) use ($search, $specializationTerms): void {
                    $nested->where('mechanic_id', 'like', "%{$search}%")
                        ->orWhere('mechanic_name', 'like', "%{$search}%")
                        ->orWhere('shift', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('specialization', 'like', "%{$search}%");

                    foreach ($specializationTerms as $term) {
                        if (mb_strtolower($term) !== mb_strtolower($search)) {
                            $nested->orWhere('specialization', 'like', "%{$term}%");
                        }
                    }
                });
            })
            ->where('employment_status', $request->query('tab') === 'history' ? 'Inactive' : 'Active')
            ->when($request->filled('shift'), fn ($query) => $query->where('shift', $request->shift))
            ->when($request->filled('specialization'), function ($query) use ($request): void {
                $terms = $this->specializationSearchTerms((string) $request->specialization);

                $query->where(function ($nested) use ($terms): void {
                    foreach ($terms as $term) {
                        $nested->orWhere('specialization', 'like', "%{$term}%");
                    }
                });
            });
        TableSort::apply($mechanicsQuery, $request, ['mechanic_name' => 'mechanic_name', 'mechanic_id' => 'mechanic_id', 'shift' => 'shift', 'employment_status' => 'employment_status'], 'mechanic_name');
        $mechanics = $mechanicsQuery
            ->paginate(12)
            ->withQueryString();

        $mechanics->setCollection(
            $mechanics->getCollection()->map(function (Mechanic $mechanic): Mechanic {
                $specializations = $this->canonicalizeSpecializationList($mechanic->specialization);

                $mechanic->setAttribute('specialization_labels', $specializations);
                $mechanic->setAttribute('specialization_canonical', $specializations->implode(', '));

                return $mechanic;
            })
        );

        $mechanicNames = $mechanics->getCollection()->pluck('mechanic_name');
        $lockedMechanicNames = $mechanicNames->isEmpty() ? collect() : JobOrder::query()
            ->whereIn('assigned_mechanic', $mechanicNames)
            ->where('status', 'On Going')
            ->pluck('assigned_mechanic');
        $mechanics->getCollection()->each(function (Mechanic $mechanic) use ($lockedMechanicNames): void {
            $mechanic->deactivation_locked = $lockedMechanicNames->contains($mechanic->mechanic_name);
        });

        return view(
            'Operation.Personnel Management.mechanic_master_list',
            compact('mechanics', 'stats', 'specializationOptions')
        );
    }

    /**
     * Serialize ownership changes by locking bus rows before checking who owns
     * them. A bus may be authorized for only one active driver.
     */
    private function assertExclusiveActiveBusOwnership(array $busIds, ?int $exceptDriverId = null): void
    {
        if ($busIds === []) {
            return;
        }

        Bus::query()->whereIn('id', $busIds)->orderBy('id')->lockForUpdate()->get();

        $conflicting = Driver::query()
            ->where('employment_status', 'Active')
            ->when($exceptDriverId !== null, fn ($query) => $query->whereKeyNot($exceptDriverId))
            ->whereHas('authorizedBuses', fn ($query) => $query->whereIn('buses.id', $busIds))
            ->first();

        if ($conflicting) {
            throw ValidationException::withMessages([
                'bus_ids' => "A selected bus is already assigned to active driver {$conflicting->driver_name}. Deactivate or release the existing ownership first.",
            ]);
        }
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        $validated = $this->validateDriver($request);
        $busIds = $validated['bus_ids'] ?? [];
        unset($validated['bus_ids']);
        $driver = DB::transaction(function () use ($validated, $busIds): Driver {
            if ($validated['employment_status'] === 'Active') {
                $this->assertExclusiveActiveBusOwnership($busIds);
            }
            $driver = Driver::create($validated);
            $driver->authorizedBuses()->sync($busIds);
            return $driver;
        });

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Driver',
            'created',
            $driver->id,
            "{$driver->driver_name} was added to the Driver Master List."
        );

        return back()->with('success', 'Driver master record created successfully.');
    }

    public function updateDriver(Request $request, Driver $driver): RedirectResponse
    {
        $validated = $this->validateDriver($request, $driver);
        $busIds = $validated['bus_ids'] ?? null;
        unset($validated['bus_ids']);

        $driver = DB::transaction(function () use ($driver, $validated, $busIds): Driver {
            $lockedDriver = Driver::query()
                ->whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Driver ID and name are historical join keys in attendance and
            // trip records. They cannot change after linked records exist.
            $hasHistory = $lockedDriver->attendances()->exists()
                || TripAssignment::query()->where('driver_id', $lockedDriver->driver_id)->exists();
            if ($hasHistory && (
                $validated['driver_id'] !== $lockedDriver->driver_id
                || $validated['driver_name'] !== $lockedDriver->driver_name
            )) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Driver ID and name cannot be changed after attendance or trip history exists.',
                ]);
            }

            if (
                $validated['employment_status'] === 'Inactive'
                && $lockedDriver->employment_status !== 'Inactive'
            ) {
                $this->assertDriverCanBeDeactivated($lockedDriver);
            }

            if ($busIds !== null) {
                $oldIds = $lockedDriver->authorizedBuses()->pluck('buses.id')->all();
                $removedIds = array_diff($oldIds, $busIds);
                if ($removedIds && TripAssignment::query()
                    ->where('driver_id', $lockedDriver->driver_id)
                    ->whereIn('bus_id', $removedIds)
                    ->whereHas('tripSchedule', fn ($query) => $query
                        ->whereDate('trip_date', '>=', today(config('app.business_timezone', 'Asia/Manila'))->toDateString())
                        ->whereNotIn('status', ['Cancelled', 'Completed', 'Missed']))
                    ->exists()) {
                    throw ValidationException::withMessages(['bus_ids' => 'Cannot remove a bus linked to an ongoing or future trip assignment. Reassign that trip first.']);
                }
            }

            if ($validated['employment_status'] === 'Active') {
                $this->assertExclusiveActiveBusOwnership(
                    $busIds ?? $lockedDriver->authorizedBuses()->pluck('buses.id')->all(),
                    $lockedDriver->id
                );
            }

            $lockedDriver->update($validated);
            if ($validated['employment_status'] === 'Inactive') {
                $lockedDriver->authorizedBuses()->detach();
            } elseif ($busIds !== null) {
                $lockedDriver->authorizedBuses()->sync($busIds);
            }

            return $lockedDriver;
        });

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Driver',
            'updated',
            $driver->id,
            "{$driver->driver_name}'s master-list profile was updated."
        );

        return back()->with('success', 'Driver master record updated successfully.');
    }

    public function deactivateDriver(Driver $driver): RedirectResponse
    {
        $driver = DB::transaction(function () use ($driver): Driver {
            $lockedDriver = Driver::query()
                ->whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDriverCanBeDeactivated($lockedDriver);
            $lockedDriver->update(['employment_status' => 'Inactive']);
            $lockedDriver->authorizedBuses()->detach();

            return $lockedDriver;
        });

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Driver',
            'updated',
            $driver->id,
            "{$driver->driver_name} was deactivated."
        );

        return back()->with('success', 'Driver has been deactivated. Existing attendance records were preserved.');
    }

    public function storeMechanic(Request $request): RedirectResponse
    {
        $mechanic = Mechanic::create($this->validateMechanic($request));

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Mechanic',
            'created',
            $mechanic->id,
            "{$mechanic->mechanic_name} was added to the Mechanic Master List."
        );

        return back()->with('success', 'Mechanic master record created successfully.');
    }

    public function updateMechanic(Request $request, Mechanic $mechanic): RedirectResponse
    {
        $validated = $this->validateMechanic($request, $mechanic);

        $mechanic = DB::transaction(function () use ($mechanic, $validated): Mechanic {
            $lockedMechanic = Mechanic::query()
                ->whereKey($mechanic->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $validated['mechanic_id'] !== $lockedMechanic->mechanic_id
                && $lockedMechanic->attendances()->exists()
            ) {
                throw ValidationException::withMessages([
                    'mechanic_id' => 'Mechanic ID cannot be changed after attendance history exists.',
                ]);
            }

            if (
                $validated['employment_status'] === 'Inactive'
                && $lockedMechanic->employment_status !== 'Inactive'
            ) {
                $this->assertMechanicCanBeDeactivated($lockedMechanic);
            }

            $lockedMechanic->update($validated);

            return $lockedMechanic;
        });

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Mechanic',
            'updated',
            $mechanic->id,
            "{$mechanic->mechanic_name}'s master-list profile was updated."
        );

        return back()->with('success', 'Mechanic master record updated successfully.');
    }

    public function deactivateMechanic(Mechanic $mechanic): RedirectResponse
    {
        $mechanic = DB::transaction(function () use ($mechanic): Mechanic {
            $lockedMechanic = Mechanic::query()
                ->whereKey($mechanic->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertMechanicCanBeDeactivated($lockedMechanic);
            $lockedMechanic->update(['employment_status' => 'Inactive']);

            return $lockedMechanic;
        });

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Mechanic',
            'updated',
            $mechanic->id,
            "{$mechanic->mechanic_name} was deactivated."
        );

        return back()->with('success', 'Mechanic has been deactivated. Existing attendance records were preserved.');
    }

    public function activateMechanic(Mechanic $mechanic): RedirectResponse
    {
        $mechanic->update(['employment_status' => 'Active']);

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Mechanic',
            'updated',
            $mechanic->id,
            "{$mechanic->mechanic_name} was reactivated."
        );

        return back()->with('success', 'Mechanic has been reactivated and is available for future attendance records.');
    }

    private function validateDriver(Request $request, ?Driver $driver = null): array
    {
        $request->merge([
            'driver_id' => trim((string) $request->input('driver_id')),
            'driver_name' => trim((string) $request->input('driver_name')),
            // Preserve the existing shift on edit; use legacy Morning default only
            // for newly created drivers until the scheduling/attendance phase is migrated.
            'shift' => $driver?->shift ?? 'Morning',
        ]);

        if ($request->boolean('bus_ids_present')) $request->merge(['bus_ids' => $request->input('bus_ids', [])]);

        $validated = $request->validate([
            'driver_id' => ['required', 'string', 'max:100', Rule::unique('drivers', 'driver_id')->ignore($driver?->id)],
            'driver_name' => ['required', 'string', 'max:255', Rule::unique('drivers', 'driver_name')->ignore($driver?->id)],
            'shift' => ['required', Rule::in(['Morning', 'Afternoon'])],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'employment_status' => ['required', Rule::in(['Active', 'Inactive'])],
            'bus_ids' => ['sometimes', 'array'],
            'bus_ids.*' => ['integer', 'distinct', Rule::exists('buses', 'id')],
        ]);

        $duplicateName = Driver::query()
            ->whereRaw('LOWER(TRIM(driver_name)) = ?', [mb_strtolower($validated['driver_name'])])
            ->when($driver, fn ($query) => $query->whereKeyNot($driver->id))
            ->exists();

        if ($duplicateName) {
            throw ValidationException::withMessages([
                'driver_name' => 'A driver with this name already exists.',
            ]);
        }

        return $validated;
    }

    private function validateMechanic(Request $request, ?Mechanic $mechanic = null): array
    {
        $request->merge([
            'mechanic_id' => trim((string) $request->input('mechanic_id')),
            'mechanic_name' => trim((string) $request->input('mechanic_name')),
        ]);

        $validated = $request->validate([
            'mechanic_id' => ['required', 'string', 'max:100', Rule::unique('mechanics', 'mechanic_id')->ignore($mechanic?->id)],
            'mechanic_name' => ['required', 'string', 'max:255', Rule::unique('mechanics', 'mechanic_name')->ignore($mechanic?->id)],
            'shift' => ['required', Rule::in(['Morning', 'Afternoon'])],
            'specialization' => ['nullable', 'string', 'max:1000'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'employment_status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $duplicateName = Mechanic::query()
            ->whereRaw('LOWER(TRIM(mechanic_name)) = ?', [mb_strtolower($validated['mechanic_name'])])
            ->when($mechanic, fn ($query) => $query->whereKeyNot($mechanic->id))
            ->exists();

        if ($duplicateName) {
            throw ValidationException::withMessages([
                'mechanic_name' => 'A mechanic with this name already exists.',
            ]);
        }

        $validated['specialization'] = $this->normalizeSpecializations($validated['specialization'] ?? null);

        return $validated;
    }

    private function assertDriverCanBeDeactivated(Driver $driver): void
    {
        // Past attendance does not block deactivation; unresolved trips do.
        $hasActiveOrUpcomingTrip = TripAssignment::query()
            ->where('driver_id', $driver->driver_id)
            ->whereHas('tripSchedule', fn ($query) => $query
                ->whereNotIn('status', ['Cancelled', 'Completed']))
            ->lockForUpdate()
            ->first() !== null;

        if ($hasActiveOrUpcomingTrip) {
            throw ValidationException::withMessages([
                'employment_status' => 'This driver cannot be deactivated while assigned to an active or upcoming trip.',
            ]);
        }
    }

    private function assertMechanicCanBeDeactivated(Mechanic $mechanic): void
    {
        $hasOngoingJobOrder = JobOrder::query()
            ->where('assigned_mechanic', $mechanic->mechanic_name)
            ->where('status', 'On Going')
            ->lockForUpdate()
            ->first() !== null;

        if ($hasOngoingJobOrder) {
            throw ValidationException::withMessages([
                'employment_status' => 'This mechanic cannot be deactivated while assigned to an ongoing Job Order.',
            ]);
        }
    }

    private function recordedMechanicSpecializations(): Collection
    {
        return Mechanic::query()
            ->whereNotNull('specialization')
            ->pluck('specialization')
            ->flatMap(fn (string $specialization) => $this->canonicalizeSpecializationList($specialization))
            ->unique(fn (string $specialization) => mb_strtolower($specialization))
            ->sort()
            ->values();
    }

    private function canonicalizeSpecializationList(?string $specialization): Collection
    {
        if ($specialization === null || trim($specialization) === '') {
            return collect();
        }

        return collect(preg_split('/[,;]+/', $specialization) ?: [])
            ->map(fn (string $item) => $this->canonicalSpecialization($item))
            ->filter()
            ->unique(fn (string $item) => mb_strtolower($item))
            ->values();
    }

    private function canonicalSpecialization(string $specialization): ?string
    {
        $specialization = trim($specialization);

        if ($specialization === '') {
            return null;
        }

        foreach (self::MECHANIC_SPECIALIZATIONS as $canonical => $aliases) {
            $acceptedValues = array_merge([$canonical], $aliases);

            foreach ($acceptedValues as $acceptedValue) {
                if (mb_strtolower($acceptedValue) === mb_strtolower($specialization)) {
                    return $canonical;
                }
            }
        }

        return $specialization;
    }

    private function specializationSearchTerms(string $specialization): array
    {
        $canonical = $this->canonicalSpecialization($specialization);

        if ($canonical === null) {
            return [];
        }

        $aliases = self::MECHANIC_SPECIALIZATIONS[$canonical] ?? [];

        return array_values(array_unique(array_merge([$canonical], $aliases)));
    }

    private function normalizeSpecializations(?string $specialization): ?string
    {
        $normalized = $this->canonicalizeSpecializationList($specialization)->implode(', ');

        return $normalized !== '' ? $normalized : null;
    }
}
