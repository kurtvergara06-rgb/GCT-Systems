<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class BatchAttendanceController extends Controller
{
    use SystemDataUpdateBroadcaster;

    private const GRACE_MINUTES = 10;

    private const SHIFT_STARTS = [
        'Morning' => '06:00:00',
        'Afternoon' => '14:00:00',
    ];

    private const MECHANIC_SHIFT_START = '08:00:00';
    private const MECHANIC_SHIFT_END = '17:00:00';

    public function roster(Request $request, string $type): JsonResponse
    {
        $this->assertType($type);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'shift' => ['nullable', Rule::in(['all', 'Morning', 'Afternoon'])],
        ]);

        if ($type === 'mechanic' && ($validated['shift'] ?? 'all') === 'Afternoon') {
            throw ValidationException::withMessages(['shift' => 'Mechanic attendance is Morning only.']);
        }

        $date = Carbon::parse($validated['date'])->toDateString();
        $shift = $validated['shift'] ?? 'all';
        $personModel = $this->personModelFor($type);
        $attendanceModel = $this->attendanceModelFor($type);
        $nameColumn = $this->nameColumn($type);
        $idColumn = $this->personIdColumn($type);

        $people = $personModel::query()
            ->where('employment_status', 'Active')
            ->orderBy($nameColumn)
            ->get();

        $dateRecords = $attendanceModel::query()
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy($idColumn);

        // For drivers, show only personnel who still need attendance for
        // this date. A saved Present, Absent or On Leave record must not
        // reappear in the new-entry modal.
        if ($type === 'driver') {
            $people = $people->reject(
                fn ($person) => ($record = $dateRecords->get((string) $person->{$idColumn}))
                    && ! in_array($record->status, ['Unmarked', null, ''], true)
            )->values();
        }

        $rows = $people->map(function ($person) use (
            $dateRecords,
            $date,
            $type,
            $nameColumn,
            $idColumn
        ): array {
            $personId = (string) $person->{$idColumn};
            $record = $dateRecords->get($personId);
            $status = $record?->status;

            if ($status === 'On Duty') {
                $status = 'Present';
            }

            return [
                'person_id' => $personId,
                'recorded' => $record !== null && ! in_array($record->status, ['Unmarked', null, ''], true),
                'name' => (string) $person->{$nameColumn},
                'shift' => 'Morning', // Legacy persistence only; mechanic shift is no longer user-selected.
                'attendance_date' => $date,
                'time_in' => $this->timeValue($record?->time_in),
                'time_out' => $this->timeValue($record?->time_out),
                'status' => $status ?: ($type === 'driver' ? 'Unmarked' : 'Present'),
                'availability' => $this->availabilityLabel($type, $record, $personId),
                'assigned_job' => $type === 'mechanic'
                    ? (string) ($record?->assigned_job ?? '')
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'type' => $type,
            'date' => $date,
            'grace_minutes' => self::GRACE_MINUTES,
            'shift_starts' => self::SHIFT_STARTS,
            'rows' => $rows,
        ]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $this->assertType($type);

        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
            'rows' => ['required', 'array', 'min:1', 'max:300'],
            'rows.*.person_id' => ['required', 'string', 'max:100'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.shift' => ['required', Rule::in(array_keys(self::SHIFT_STARTS))],
            'rows.*.time_in' => ['nullable', 'date_format:H:i'],
            'rows.*.time_out' => ['nullable', 'date_format:H:i'],
            'rows.*.status' => ['required', Rule::in($type === 'mechanic' ? ['Present', 'Late', 'Absent', 'On Leave'] : ['Present', 'Absent', 'On Leave'])],
            'rows.*.assigned_job' => ['nullable', 'string', 'max:255'],
        ]);

        if ($type === 'mechanic') {
            foreach ($validated['rows'] as $index => $row) {
                if ($row['shift'] !== 'Morning') {
                    throw ValidationException::withMessages([
                        "rows.{$index}.shift" => 'Mechanic attendance only supports Morning shift.',
                    ]);
                }
            }
        }

        $date = Carbon::parse($validated['attendance_date'])->toDateString();
        $attendanceModel = $this->attendanceModelFor($type);
        $nameColumn = $this->nameColumn($type);
        $idColumn = $this->personIdColumn($type);
        $saved = 0;

        try {
            DB::transaction(function () use (
                $validated,
                $date,
                $type,
                $attendanceModel,
                $nameColumn,
                $idColumn,
                &$saved
            ): void {
                $personModel = $this->personModelFor($type);
                $seenIds = [];

                foreach ($validated['rows'] as $index => $row) {
                    $personId = (string) $row['person_id'];
                    if (isset($seenIds[$personId])) {
                        throw ValidationException::withMessages([
                            "rows.{$index}.person_id" => 'This person appears more than once in the batch.',
                        ]);
                    }
                    $seenIds[$personId] = true;

                    $person = $personModel::query()
                        ->where($idColumn, $personId)
                        ->lockForUpdate()
                        ->first();
                    if (! $person || $person->employment_status !== 'Active') {
                        throw ValidationException::withMessages([
                            "rows.{$index}.person_id" => 'Choose an active person from the master list.',
                        ]);
                    }
                    if ($type === 'mechanic' && $row['shift'] !== 'Morning') {
                        throw ValidationException::withMessages([
                            "rows.{$index}.shift" => 'Mechanic attendance only supports Morning shift.',
                        ]);
                    }

                    $existing = $attendanceModel::query()
                        ->where($idColumn, $personId)
                        ->whereDate('attendance_date', $date)
                        ->lockForUpdate()->first();

                    // A batch is create-only: never silently replace an existing
                    // daily record, including one linked to a trip or job order.
                    if ($existing && ! in_array($existing->status, ['Unmarked', null, ''], true)) {
                        throw ValidationException::withMessages([
                            "rows.{$index}.person_id" => 'Attendance is already recorded for this person on the selected date. Refresh the roster.',
                        ]);
                    }

                    // Match the individual attendance controllers: linked records
                    // cannot be rewritten through the batch endpoint.
                    if ($type === 'driver' && $existing?->tripAssignments()->exists()) {
                        throw ValidationException::withMessages([
                            "rows.{$index}.person_id" => 'Attendance is linked to a trip assignment and cannot be changed through batch attendance.',
                        ]);
                    }
                    $hasOngoingJo = $type === 'mechanic' && JobOrder::query()
                        ->assignedToMechanic($person->mechanic_name, $person->mechanic_id)
                        ->where('status', 'On Going')
                        ->where('review_status', 'Approved')
                        ->exists();
                    if ($hasOngoingJo && in_array($row['status'], ['Absent', 'On Leave'], true)) {
                        throw ValidationException::withMessages([
                            "rows.{$index}.status" => 'This mechanic has an approved ongoing Job Order. Resolve the assignment before marking Absent or On Leave.',
                        ]);
                    }

                    $status = $row['status'];
                    $timeIn = $row['time_in'] ?? null;
                    $timeOut = $row['time_out'] ?? null;

                    if (in_array($status, ['Absent', 'On Leave'], true)) {
                        $timeIn = null;
                        $timeOut = null;
                    } else {
                        if (! $timeIn) {
                            throw ValidationException::withMessages([
                                "rows.{$index}.time_in" => "Time-in is required for {$row['name']}.",
                            ]);
                        }

                        $status = $type === 'mechanic'
                            ? $this->attendanceStatus($timeIn)
                            : 'Present';
                    }

                    $values = [
                        $nameColumn => (string) $person->{$nameColumn},
                        'shift' => $type === 'driver' ? 'Morning' : $row['shift'],
                        'time_in' => $timeIn ? $timeIn . ':00' : null,
                        'time_out' => $type === 'driver' ? null : ($timeOut ? $timeOut . ':00' : null),
                        'status' => $status,
                    ];

                    if ($type === 'mechanic') {
                        $values['assigned_job'] = trim((string) ($row['assigned_job'] ?? ''));
                    }

                    if ($existing) {
                        // Finalize a previously created Unmarked placeholder in place.
                        // Never change an attendance row already linked to a trip.
                        $existing->update($values);
                    } else {
                        $attendanceModel::query()->create(array_merge([
                            $idColumn => $personId,
                            'attendance_date' => $date,
                        ], $values));
                    }

                    $saved++;
                }
            });
        } catch (ValidationException $error) {
            throw $error;
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'success' => false,
                'message' => 'Attendance could not be saved. Please run the latest database migrations and try again.',
            ], 422);
        }

        $label = $type === 'driver' ? 'driver' : 'mechanic';

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'updated',
            null,
            "{$saved} {$label} attendance record(s) saved through daily attendance."
        );

        return response()->json([
            'success' => true,
            'saved' => $saved,
            'message' => "{$saved} {$label} attendance record(s) saved successfully.",
        ]);
    }

    private function attendanceStatus(string $timeIn): string
    {
        $start = Carbon::createFromFormat('H:i:s', self::MECHANIC_SHIFT_START);
        $actual = Carbon::createFromFormat('H:i', $timeIn);

        return $actual->greaterThan($start)
            ? 'Late'
            : 'Present';
    }

    private function availabilityLabel(
        string $type,
        ?object $record,
        string $personId
    ): string {
        if ($record && in_array($record->status, ['Absent', 'On Leave'], true)) {
            return 'Unavailable';
        }

        if ($type === 'mechanic') {
            $mechanic = Mechanic::query()->where('mechanic_id', $personId)->first();
            $onApprovedJo = $mechanic && JobOrder::query()
                ->assignedToMechanic($mechanic->mechanic_name, $mechanic->mechanic_id)
                ->where('status', 'On Going')
                ->where('review_status', 'Approved')
                ->exists();

            return $onApprovedJo || trim((string) ($record?->assigned_job ?? '')) !== ''
                ? 'On Duty'
                : 'Available';
        }

        if (! $record) {
            return 'Available';
        }

        return $record->tripAssignments()
            ->whereHas('tripSchedule', function ($query): void {
                $query->whereNotIn('status', ['Cancelled', 'Completed']);
            })
            ->exists()
                ? 'On Duty'
                : 'Available';
    }

    private function timeValue(mixed $value): ?string
    {
        return $value ? Carbon::parse((string) $value)->format('H:i') : null;
    }

    private function personModelFor(string $type): string
    {
        return $type === 'driver' ? Driver::class : Mechanic::class;
    }

    private function attendanceModelFor(string $type): string
    {
        return $type === 'driver' ? DriverAttendance::class : MechanicAttendance::class;
    }

    private function nameColumn(string $type): string
    {
        return $type === 'driver' ? 'driver_name' : 'mechanic_name';
    }

    private function personIdColumn(string $type): string
    {
        return $type === 'driver' ? 'driver_id' : 'mechanic_id';
    }

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, ['driver', 'mechanic'], true), 404);
    }
}
