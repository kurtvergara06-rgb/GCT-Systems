<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\MechanicAttendance;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $model = $this->modelFor($type);
        $nameColumn = $this->nameColumn($type);
        $idColumn = $this->personIdColumn($type);

        $records = $model::query()
            ->when($shift !== 'all', fn ($query) => $query->where('shift', $shift))
            ->latest('id')
            ->get();

        $roster = $records
            ->unique(fn ($record) => $this->personKey($record->{$nameColumn}, $record->shift))
            ->values();

        $dateRecords = $model::query()
            ->whereDate('attendance_date', $date)
            ->when($shift !== 'all', fn ($query) => $query->where('shift', $shift))
            ->get()
            ->keyBy(fn ($record) => $this->personKey($record->{$nameColumn}, $record->shift));

        $rows = $roster->map(function ($source) use (
            $dateRecords,
            $date,
            $type,
            $nameColumn,
            $idColumn
        ): array {
            $key = $this->personKey($source->{$nameColumn}, $source->shift);
            $record = $dateRecords->get($key);
            $status = $record?->status;

            if ($status === 'On Duty') {
                $status = 'Present';
            }

            return [
                'person_id' => (string) $source->{$idColumn},
                'name' => (string) $source->{$nameColumn},
                'shift' => (string) $source->shift,
                'attendance_date' => $date,
                'time_in' => $this->timeValue($record?->time_in),
                'time_out' => $this->timeValue($record?->time_out),
                'status' => $status ?: 'Present',
                'availability' => $this->availabilityLabel($type, $record ?: $source),
                'assigned_job' => $type === 'mechanic'
                    ? (string) ($record?->assigned_job ?? $source->assigned_job ?? '')
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
        $model = $this->modelFor($type);
        $nameColumn = $this->nameColumn($type);
        $idColumn = $this->personIdColumn($type);
        $saved = 0;

        DB::transaction(function () use (
            $validated,
            $date,
            $type,
            $model,
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
                    if (!$timeIn) {
                        throw ValidationException::withMessages([
                            "rows.{$index}.time_in" => "Time-in is required for {$row['name']}.",
                        ]);
                    }

                    $status = $this->attendanceStatus($row['shift'], $timeIn);
                }

                $attributes = [
                    $nameColumn => trim($row['name']),
                    'shift' => $row['shift'],
                    'attendance_date' => $date,
                ];

                $values = [
                    $idColumn => $row['person_id'],
                    'time_in' => $timeIn ? $timeIn . ':00' : null,
                    'time_out' => $timeOut ? $timeOut . ':00' : null,
                    'status' => $status,
                ];

                if ($type === 'mechanic') {
                    $values['assigned_job'] = trim((string) ($row['assigned_job'] ?? ''));
                }

                $model::query()->updateOrCreate($attributes, $values);
                $saved++;
            }
        });

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

    private function availabilityLabel(string $type, object $record): string
    {
        if ($type === 'mechanic') {
            return trim((string) ($record->assigned_job ?? '')) !== ''
                ? 'On Job'
                : 'Available';
        }

        if (method_exists($record, 'tripAssignments')) {
            return $record->tripAssignments()->exists() ? 'On Duty' : 'Available';
        }

        return $record->status === 'On Duty' ? 'On Duty' : 'Available';
    }

    private function timeValue(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        return Carbon::parse((string) $value)->format('H:i');
    }

    private function personKey(string $name, string $shift): string
    {
        return mb_strtolower(trim($name)) . '|' . $shift;
    }

    private function modelFor(string $type): string
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
