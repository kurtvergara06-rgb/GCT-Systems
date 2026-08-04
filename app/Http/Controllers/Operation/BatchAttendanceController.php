<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
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

    private const SHIFT_STARTS = [
        'Morning' => '06:00:00',
        'Afternoon' => '14:00:00',
        'Night' => '22:00:00',
    ];

    private const GRACE_MINUTES = 10;

    public function roster(Request $request, string $type): JsonResponse
    {
        $this->assertType($type);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'shift' => ['nullable', Rule::in(['all', 'Morning', 'Afternoon', 'Night'])],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $shift = $validated['shift'] ?? 'all';
        $personModel = $this->personModelFor($type);
        $attendanceModel = $this->attendanceModelFor($type);
        $nameColumn = $this->nameColumn($type);
        $idColumn = $this->personIdColumn($type);

        $people = $personModel::query()
            ->where('employment_status', 'Active')
            ->when($shift !== 'all', fn ($query) => $query->where('shift', $shift))
            ->orderBy($nameColumn)
            ->get();

        $dateRecords = $attendanceModel::query()
            ->whereDate('attendance_date', $date)
            ->when($shift !== 'all', fn ($query) => $query->where('shift', $shift))
            ->get()
            ->keyBy($idColumn);

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
                'name' => (string) $person->{$nameColumn},
                'shift' => (string) $person->shift,
                'attendance_date' => $date,
                'time_in' => $this->timeValue($record?->time_in),
                'time_out' => $this->timeValue($record?->time_out),
                'status' => $status ?: 'Present',
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
            'rows.*.status' => ['required', Rule::in(['Present', 'Late', 'Absent', 'On Leave'])],
            'rows.*.assigned_job' => ['nullable', 'string', 'max:255'],
        ]);

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
                foreach ($validated['rows'] as $index => $row) {
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

                        $status = $this->attendanceStatus($row['shift'], $timeIn);
                    }

                    $values = [
                        $nameColumn => trim($row['name']),
                        'shift' => $row['shift'],
                        'time_in' => $timeIn ? $timeIn . ':00' : null,
                        'time_out' => $timeOut ? $timeOut . ':00' : null,
                        'status' => $status,
                    ];

                    if ($type === 'mechanic') {
                        $values['assigned_job'] = trim((string) ($row['assigned_job'] ?? ''));
                    }

                    $attendanceModel::query()->updateOrCreate(
                        [
                            $idColumn => $row['person_id'],
                            'attendance_date' => $date,
                        ],
                        $values
                    );

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

    private function attendanceStatus(string $shift, string $timeIn): string
    {
        $start = Carbon::createFromFormat('H:i:s', self::SHIFT_STARTS[$shift]);
        $actual = Carbon::createFromFormat('H:i', $timeIn);

        return $actual->greaterThan($start->copy()->addMinutes(self::GRACE_MINUTES))
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
            return trim((string) ($record?->assigned_job ?? '')) !== ''
                ? 'On Job'
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
