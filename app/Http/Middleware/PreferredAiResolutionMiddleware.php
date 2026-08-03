<?php

namespace App\Http\Middleware;

use App\Models\Maintenance\Bus;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PreferredAiResolutionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            !$request->isMethod('POST')
            || !$request->is('operation/auto-scheduling/resolve')
            || (
                !$request->filled('preferred_driver_attendance_id')
                && !$request->filled('preferred_bus_id')
            )
        ) {
            return $next($request);
        }

        return $this->resolveWithPreferences($request);
    }

    private function resolveWithPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trip_schedule_id' => [
                'required',
                'integer',
                'exists:trip_schedules,id',
            ],
            'proposed_departure_time' => [
                'required',
                'date_format:H:i:s',
            ],
            'preferred_driver_attendance_id' => [
                'nullable',
                'integer',
                'exists:driver_attendances,id',
            ],
            'preferred_bus_id' => [
                'nullable',
                'integer',
                'exists:buses,id',
            ],
        ]);

        $result = DB::transaction(function () use ($validated): array {
            $trip = TripSchedule::query()
                ->lockForUpdate()
                ->findOrFail((int) $validated['trip_schedule_id']);

            if (
                $trip->status !== 'Scheduled'
                || $trip->assignment_status !== 'Unassigned'
            ) {
                throw ValidationException::withMessages([
                    'trip_schedule_id' =>
                        'This trip is no longer available for resolution. Generate the schedule again.',
                ]);
            }

            $date = $trip->trip_date->format('Y-m-d');
            $originalStart = strtotime(
                $date.' '.$this->databaseTime($trip->departure_time)
            );
            $originalEnd = strtotime(
                $date.' '.$this->databaseTime($trip->estimated_arrival_time)
            );

            if ($originalStart === false || $originalEnd === false) {
                throw ValidationException::withMessages([
                    'trip_schedule_id' => 'The original trip time is invalid.',
                ]);
            }

            if ($originalEnd <= $originalStart) {
                $originalEnd += 86400;
            }

            $proposedStart = strtotime(
                $date.' '.$validated['proposed_departure_time']
            );

            if ($proposedStart === false) {
                throw ValidationException::withMessages([
                    'proposed_departure_time' =>
                        'The proposed departure time is invalid.',
                ]);
            }

            $proposedEnd = $proposedStart + ($originalEnd - $originalStart);
            $proposedDeparture = date('H:i:s', $proposedStart);
            $proposedArrival = date('H:i:s', $proposedEnd);

            $existingAssignments = TripAssignment::query()
                ->with('tripSchedule')
                ->whereHas('tripSchedule', function ($query) use ($date) {
                    $query
                        ->whereDate('trip_date', $date)
                        ->whereNotIn('status', ['Cancelled', 'Completed']);
                })
                ->lockForUpdate()
                ->get();

            $drivers = DriverAttendance::query()
                ->whereDate('attendance_date', $date)
                ->whereIn('status', ['Present', 'Late'])
                ->where('shift', $trip->shift)
                ->orderByRaw("CASE WHEN status = 'Present' THEN 1 ELSE 2 END")
                ->orderBy('driver_name')
                ->lockForUpdate()
                ->get();

            $buses = Bus::query()
                ->where('status', 'Active')
                ->orderBy('bus_no')
                ->lockForUpdate()
                ->get();

            $preferredDriverId = isset($validated['preferred_driver_attendance_id'])
                ? (int) $validated['preferred_driver_attendance_id']
                : null;
            $preferredBusId = isset($validated['preferred_bus_id'])
                ? (int) $validated['preferred_bus_id']
                : null;

            $driver = $this->selectDriver(
                drivers: $drivers,
                preferredId: $preferredDriverId,
                assignments: $existingAssignments,
                date: $date,
                departure: $proposedDeparture,
                arrival: $proposedArrival
            );

            $bus = $this->selectBus(
                buses: $buses,
                preferredId: $preferredBusId,
                assignments: $existingAssignments,
                date: $date,
                departure: $proposedDeparture,
                arrival: $proposedArrival
            );

            if (!$driver || !$bus) {
                $messages = [];

                if (!$driver) {
                    $messages[] = $preferredDriverId
                        ? 'The selected driver is no longer eligible or available at the proposed time.'
                        : 'No eligible driver is available at the proposed time.';
                }

                if (!$bus) {
                    $messages[] = $preferredBusId
                        ? 'The selected bus is no longer active or available at the proposed time.'
                        : 'No active conflict-free bus is available at the proposed time.';
                }

                throw ValidationException::withMessages([
                    'resolution' => implode(' ', $messages),
                ]);
            }

            $assignment = TripAssignment::create([
                'trip_schedule_id' => $trip->id,
                'driver_attendance_id' => $driver->id,
                'driver_id' => $driver->driver_id,
                'driver_name' => $driver->driver_name,
                'bus_id' => $bus->id,
                'assigned_by' => auth()->id(),
            ]);

            $trip->update([
                'departure_time' => $proposedDeparture,
                'estimated_arrival_time' => $proposedArrival,
                'assignment_status' => 'Assigned',
                'status' => 'Ready',
            ]);

            return [
                'assignment_id' => $assignment->id,
                'trip_code' => $trip->trip_code,
                'driver_name' => $driver->driver_name,
                'driver_status' => $driver->status,
                'bus_no' => $bus->bus_no,
                'departure_time' => $proposedDeparture,
                'departure_display' => date('g:i A', $proposedStart),
                'arrival_time' => $proposedArrival,
                'arrival_display' => date('g:i A', $proposedEnd),
                'preferred_driver_used' =>
                    !$preferredDriverId || $driver->id === $preferredDriverId,
                'preferred_bus_used' =>
                    !$preferredBusId || $bus->id === $preferredBusId,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => "Trip {$result['trip_code']} was resolved successfully.",
            'resolution' => $result,
            'redirect_url' => route(
                'driver-bus-assignment',
                [],
                false
            ),
        ]);
    }

    private function selectDriver(
        Collection $drivers,
        ?int $preferredId,
        Collection $assignments,
        string $date,
        string $departure,
        string $arrival
    ): ?DriverAttendance {
        if ($preferredId) {
            $preferred = $drivers->first(
                fn (DriverAttendance $driver): bool => $driver->id === $preferredId
            );

            if (!$preferred) {
                return null;
            }

            return $this->driverHasConflict(
                $preferred,
                $assignments,
                $date,
                $departure,
                $arrival
            ) ? null : $preferred;
        }

        return $drivers->first(
            fn (DriverAttendance $driver): bool => !$this->driverHasConflict(
                $driver,
                $assignments,
                $date,
                $departure,
                $arrival
            )
        );
    }

    private function selectBus(
        Collection $buses,
        ?int $preferredId,
        Collection $assignments,
        string $date,
        string $departure,
        string $arrival
    ): ?Bus {
        if ($preferredId) {
            $preferred = $buses->first(
                fn (Bus $bus): bool => $bus->id === $preferredId
            );

            if (!$preferred) {
                return null;
            }

            return $this->busHasConflict(
                $preferred,
                $assignments,
                $date,
                $departure,
                $arrival
            ) ? null : $preferred;
        }

        return $buses->first(
            fn (Bus $bus): bool => !$this->busHasConflict(
                $bus,
                $assignments,
                $date,
                $departure,
                $arrival
            )
        );
    }

    private function driverHasConflict(
        DriverAttendance $driver,
        Collection $assignments,
        string $date,
        string $departure,
        string $arrival
    ): bool {
        return $assignments->contains(function (TripAssignment $assignment) use (
            $driver,
            $date,
            $departure,
            $arrival
        ): bool {
            if ($assignment->driver_id !== $driver->driver_id) {
                return false;
            }

            return $this->assignmentOverlaps(
                $assignment,
                $date,
                $departure,
                $arrival
            );
        });
    }

    private function busHasConflict(
        Bus $bus,
        Collection $assignments,
        string $date,
        string $departure,
        string $arrival
    ): bool {
        return $assignments->contains(function (TripAssignment $assignment) use (
            $bus,
            $date,
            $departure,
            $arrival
        ): bool {
            if ((int) $assignment->bus_id !== (int) $bus->id) {
                return false;
            }

            return $this->assignmentOverlaps(
                $assignment,
                $date,
                $departure,
                $arrival
            );
        });
    }

    private function assignmentOverlaps(
        TripAssignment $assignment,
        string $date,
        string $departure,
        string $arrival
    ): bool {
        $assignedTrip = $assignment->tripSchedule;

        if (!$assignedTrip) {
            return false;
        }

        return $this->rangesOverlap(
            $this->range(
                $date,
                $departure,
                $arrival
            ),
            $this->range(
                $assignedTrip->trip_date->format('Y-m-d'),
                $this->databaseTime($assignedTrip->departure_time),
                $this->databaseTime($assignedTrip->estimated_arrival_time)
            )
        );
    }

    private function range(string $date, string $start, string $end): array
    {
        $startTimestamp = strtotime($date.' '.$start);
        $endTimestamp = strtotime($date.' '.$end);

        if ($startTimestamp === false || $endTimestamp === false) {
            return [0, 0];
        }

        if ($endTimestamp <= $startTimestamp) {
            $endTimestamp += 86400;
        }

        return [$startTimestamp, $endTimestamp];
    }

    private function rangesOverlap(array $first, array $second): bool
    {
        return $first[0] < $second[1] && $first[1] > $second[0];
    }

    private function databaseTime(mixed $time): string
    {
        return $time
            ? date('H:i:s', strtotime((string) $time))
            : '';
    }
}
