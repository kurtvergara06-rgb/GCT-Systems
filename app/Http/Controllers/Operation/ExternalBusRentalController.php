<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\TripSchedule;
use App\Models\Operation\ExternalBusRental;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExternalBusRentalController extends Controller
{
    /**
     * Save an external rental arrangement without treating it as a bus assignment.
     * Verification of an arrangement is not proof that a bus has been dispatched.
     */
    public function assign(Request $request, \App\Models\Operation\ExternalBusRental $rental): RedirectResponse
    {
        DB::transaction(function () use ($rental): void {
            $trip = TripSchedule::query()->whereKey($rental->trip_schedule_id)
                ->lockForUpdate()->firstOrFail();
            $rental = \App\Models\Operation\ExternalBusRental::query()
                ->whereKey($rental->id)->lockForUpdate()->firstOrFail();
            if ($trip->hasDeparted() || $trip->hasOperationalHistory()
                || ! in_array($trip->status, ['Scheduled', 'Ready'], true)
                || $rental->status !== 'Confirmed'
                || ! $rental->verified_at || ! $rental->verified_by
                || blank($rental->plate_number) || blank($rental->driver_name)) {
                throw ValidationException::withMessages([
                    'rental' => 'A verified rental for an upcoming trip is required.',
                ]);
            }
            if ($rental->slot_number > max(1, (int) $trip->required_buses)
                || $trip->assignments()->where('slot_number', $rental->slot_number)->exists()) {
                throw ValidationException::withMessages([
                    'slot_number' => 'This slot is no longer open.',
                ]);
            }
            $rental->status = 'Assigned';
            $rental->save();
            DB::table('auto_schedule_slot_decisions')
                ->where('trip_schedule_id', $trip->id)
                ->where('slot_number', $rental->slot_number)
                ->where('status', 'Pending')
                ->update(['status' => 'Resolved', 'resolved_at' => now(), 'updated_at' => now()]);
            DB::table('external_bus_rental_audits')->insert([
                'external_bus_rental_id' => $rental->id,
                'changed_by' => auth()->id(),
                'previous_values' => json_encode(['status' => 'Confirmed']),
                'new_values' => json_encode(['status' => 'Assigned']),
                'created_at' => now(),
            ]);
            $trip->syncAssignmentStatus();
        }, 3);

        return back()->with('success', 'Verified external rental assigned to its slot.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'trip_schedule_id' => ['required', 'integer', 'exists:trip_schedules,id'],
            'slot_number' => ['required', 'integer', 'min:1'],
            'company_name' => ['required', 'string', 'max:160'],
            'plate_number' => ['nullable', 'string', 'max:40'],
            'driver_name' => ['nullable', 'string', 'max:160'],
            'contact_details' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'rental_status' => ['required', 'in:Pending,Confirmed'],
        ]);

        DB::transaction(function () use ($data): void {
            $trip = TripSchedule::query()->whereKey($data['trip_schedule_id'])
                ->lockForUpdate()->firstOrFail();

            if ($trip->hasDeparted() || $trip->hasOperationalHistory()
                || ! in_array($trip->status, ['Scheduled', 'Ready'], true)) {
                throw ValidationException::withMessages([
                    'trip_schedule_id' => 'This trip is no longer eligible for rental arrangements.',
                ]);
            }

            $required = max(1, (int) $trip->required_buses);
            $slot = (int) $data['slot_number'];
            if ($slot > $required || $trip->assignments()->where('slot_number', $slot)->exists()) {
                throw ValidationException::withMessages([
                    'slot_number' => 'This slot is already assigned or does not exist.',
                ]);
            }

            if (ExternalBusRental::query()
                ->where('trip_schedule_id', $trip->id)
                ->where('slot_number', $slot)
                ->where('status', 'Assigned')->exists()) {
                throw ValidationException::withMessages([
                    'slot_number' => 'This rental slot is already assigned and cannot be edited as pending.',
                ]);
            }

            $confirmed = $data['rental_status'] === 'Confirmed';
            if ($confirmed && (blank($data['plate_number'] ?? null) || blank($data['driver_name'] ?? null))) {
                throw ValidationException::withMessages([
                    'plate_number' => 'Confirmed rentals require both the external vehicle plate and driver name.',
                ]);
            }

            // Lock candidate trip rows in a consistent order so two staff cannot
            // confirm the same rented vehicle concurrently for overlapping trips.
            $nearbyTrips = TripSchedule::query()
                ->whereBetween('trip_date', [
                    $trip->trip_date->copy()->subDay()->toDateString(),
                    $trip->trip_date->copy()->addDay()->toDateString(),
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($confirmed) {
                $plate = strtoupper(trim((string) $data['plate_number']));
                $sameTripConflict = ExternalBusRental::query()
                    ->where('trip_schedule_id', $trip->id)
                    ->where('slot_number', '!=', $slot)
                    ->whereIn('status', ['Confirmed', 'Assigned'])
                    ->where(function ($query) use ($plate, $data) {
                        $query->where('plate_number', $plate)
                            ->orWhere('driver_name', trim((string) $data['driver_name']));
                    })
                    ->exists();
                if ($sameTripConflict) {
                    throw ValidationException::withMessages([
                        'plate_number' => 'This external vehicle or driver is already assigned to another rental slot on this trip.',
                    ]);
                }
                $driverName = trim((string) $data['driver_name']);
                foreach ($nearbyTrips as $otherTrip) {
                    if ($otherTrip->id === $trip->id) {
                        continue;
                    }
                    $overlaps = $trip->departureDateTime()->lt($otherTrip->estimatedArrivalDateTime())
                        && $otherTrip->departureDateTime()->lt($trip->estimatedArrivalDateTime());
                    if (! $overlaps) {
                        continue;
                    }
                    $matchingRental = ExternalBusRental::query()
                        ->where('trip_schedule_id', $otherTrip->id)
                        ->whereIn('status', ['Confirmed', 'Assigned'])
                        ->where(function ($query) use ($plate, $driverName) {
                            $query->where('plate_number', $plate)
                                ->orWhere('driver_name', $driverName);
                        })
                        ->exists();
                    if ($matchingRental) {
                        throw ValidationException::withMessages([
                            'plate_number' => 'This external vehicle or driver already has an overlapping confirmed rental.',
                        ]);
                    }
                }
                $data['plate_number'] = $plate;
                $data['driver_name'] = $driverName;
            }

            $rental = ExternalBusRental::query()->firstOrNew([
                'trip_schedule_id' => $trip->id,
                'slot_number' => $slot,
            ]);
            $previousValues = $rental->exists ? $rental->only([
                'company_name', 'plate_number', 'driver_name', 'contact_details',
                'notes', 'status', 'verified_by', 'verified_at',
            ]) : null;
            $rental->fill([
                'company_name' => trim($data['company_name']),
                'plate_number' => $data['plate_number'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'contact_details' => $data['contact_details'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $data['rental_status'],
                'recorded_by' => auth()->id(),
                'verified_by' => $confirmed ? auth()->id() : null,
                'verified_at' => $confirmed ? now() : null,
            ]);
            $rental->save();
            DB::table('external_bus_rental_audits')->insert([
                'external_bus_rental_id' => $rental->id,
                'changed_by' => auth()->id(),
                'previous_values' => $previousValues ? json_encode($previousValues) : null,
                'new_values' => json_encode($rental->only([
                    'company_name', 'plate_number', 'driver_name', 'contact_details',
                    'notes', 'status', 'verified_by', 'verified_at',
                ])),
                'created_at' => now(),
            ]);

            // A rental arrangement is NOT a TripAssignment and never fulfills a slot
            // until a separate verified assignment integration is implemented.
        }, 3);

        return redirect()->route('driver-bus-assignment',
            ['trip_date' => TripSchedule::findOrFail($data['trip_schedule_id'])->trip_date->toDateString()])
            ->with('success', 'External rental arrangement saved. The slot remains pending until assigned.');
    }
}
