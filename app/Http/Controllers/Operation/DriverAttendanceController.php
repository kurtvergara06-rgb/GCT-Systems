<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class DriverAttendanceController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function index(Request $request)
    {
        $query = DriverAttendance::query()->with([
            'tripAssignments.bus',
            'tripAssignments.tripSchedule.shuttleRoute',
        ]);

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('driver_id', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhere('shift', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('tripAssignments.bus', fn ($busQuery) =>
                        $busQuery->where('bus_no', 'like', "%{$search}%")
                    )
                    ->orWhereHas('tripAssignments.tripSchedule', fn ($tripQuery) =>
                        $tripQuery->where('trip_code', 'like', "%{$search}%")
                    );
            });
        }

        if ($request->filled('status') && $request->status !== 'All Status') {
            $query->where('status', $request->status);
        }

        $driverAttendances = $query
            ->latest('attendance_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $summaryDate = $request->filled('attendance_date')
            ? Carbon::parse($request->attendance_date)->toDateString()
            : today()->toDateString();

        $summaryQuery = DriverAttendance::query()
            ->whereDate('attendance_date', $summaryDate);

        $present = (clone $summaryQuery)->where('status', 'Present')->count();
        $absent = (clone $summaryQuery)->where('status', 'Absent')->count();
        $late = (clone $summaryQuery)->where('status', 'Late')->count();
        $onDuty = (clone $summaryQuery)->where('status', 'On Duty')->count();

        $nextDriverId = 'From Driver Master List';

        return view('Operation.Attendance.driver-attendance', compact(
            'driverAttendances',
            'present',
            'absent',
            'late',
            'onDuty',
            'nextDriverId'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'driver_name' => 'required|string|max:255',
            'shift' => 'required|string|max:255',
            'attendance_date' => 'required|date',
            'time_in' => 'nullable',
            'time_out' => 'nullable',
            'status' => 'required|string|in:Present,Late,Absent,On Leave,On Duty',
        ]);

        $driver = Driver::query()
            ->where('driver_name', $validated['driver_name'])
            ->first();

        if (! $driver) {
            return back()->withInput()->with('error', 'Select an existing driver from the Driver Master List.');
        }

        $validated['driver_id'] = $driver->driver_id;
        $validated['driver_name'] = $driver->driver_name;
        $validated['shift'] = $driver->shift ?: $validated['shift'];

        $attendance = DriverAttendance::create($validated);

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'created',
            $attendance->id,
            'A driver attendance record was created.'
        );

        return redirect()->route('driver-attendance')
            ->with('success', 'Driver attendance record created successfully.');
    }

    public function update(Request $request, DriverAttendance $driverAttendance): RedirectResponse
    {
        $validated = $request->validate([
            'driver_name' => 'required|string|max:255',
            'shift' => 'required|string|max:255',
            'attendance_date' => 'required|date',
            'time_in' => 'nullable',
            'time_out' => 'nullable',
            'status' => 'required|string|in:Present,Late,Absent,On Leave,On Duty',
        ]);

        $driver = Driver::query()
            ->where('driver_name', $validated['driver_name'])
            ->first();

        if (! $driver) {
            return back()->withInput()->with('error', 'Select an existing driver from the Driver Master List.');
        }

        $validated['driver_id'] = $driver->driver_id;
        $validated['driver_name'] = $driver->driver_name;
        $validated['shift'] = $driver->shift ?: $validated['shift'];

        $driverAttendance->update($validated);

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'updated',
            $driverAttendance->id,
            'A driver attendance record was updated.'
        );

        return redirect()->route('driver-attendance')
            ->with('success', 'Driver attendance record updated successfully.');
    }

    public function destroy(DriverAttendance $driverAttendance): RedirectResponse
    {
        if ($driverAttendance->tripAssignments()->exists()) {
            return redirect()->route('driver-attendance')
                ->with('error', 'This attendance record cannot be deleted because it has a trip assignment.');
        }

        $attendanceId = $driverAttendance->id;
        $driverAttendance->delete();

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'deleted',
            $attendanceId,
            'A driver attendance record was deleted.'
        );

        return redirect()->route('driver-attendance')
            ->with('success', 'Driver attendance record deleted successfully.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => 'required|file|mimes:csv,txt',
        ]);

        $handle = fopen($request->file('import_file')->getRealPath(), 'r');

        if (! $handle) {
            return redirect()->route('driver-attendance')
                ->with('error', 'Unable to read the uploaded CSV file.');
        }

        try {
            $header = fgetcsv($handle);

            if (! $header) {
                return redirect()->route('driver-attendance')->with('error', 'CSV file is empty.');
            }

            $header = array_map(function ($value) {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value));
                return strtolower($value);
            }, $header);

            $requiredColumns = [
                'driver_name', 'shift', 'attendance_date', 'time_in', 'time_out', 'status',
            ];

            foreach ($requiredColumns as $column) {
                if (! in_array($column, $header, true)) {
                    return redirect()->route('driver-attendance')
                        ->with('error', "Invalid CSV format. Missing required column: {$column}");
                }
            }

            $imported = 0;
            $skipped = 0;

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($header)) {
                    $skipped++;
                    continue;
                }

                $data = array_combine($header, $row);
                $name = trim($data['driver_name'] ?? '');

                if ($name === '') {
                    $skipped++;
                    continue;
                }

                $driver = Driver::query()->where('driver_name', $name)->first();

                if (! $driver) {
                    $skipped++;
                    continue;
                }

                try {
                    $status = trim($data['status'] ?? 'Present');
                    if (! in_array($status, ['Present', 'Late', 'Absent', 'On Leave', 'On Duty'], true)) {
                        $status = 'Present';
                    }

                    DriverAttendance::updateOrCreate(
                        [
                            'driver_id' => $driver->driver_id,
                            'attendance_date' => $this->parseCsvDate($data['attendance_date'] ?? null),
                        ],
                        [
                            'driver_name' => $driver->driver_name,
                            'shift' => $driver->shift ?: trim($data['shift'] ?? 'Morning'),
                            'time_in' => $this->parseCsvTime($data['time_in'] ?? null),
                            'time_out' => $this->parseCsvTime($data['time_out'] ?? null),
                            'status' => $status,
                        ]
                    );
                    $imported++;
                } catch (Throwable) {
                    $skipped++;
                }
            }

            if ($imported === 0) {
                return redirect()->route('driver-attendance')
                    ->with('error', 'No driver attendance records were imported. Check that driver names exist in the Driver Master List.');
            }

            $this->broadcastSystemDataUpdated(
                'Operation',
                'Attendance',
                'updated',
                null,
                "{$imported} driver attendance record(s) imported successfully."
            );

            $message = "{$imported} driver attendance record(s) imported successfully.";
            if ($skipped > 0) {
                $message .= " {$skipped} row(s) were skipped.";
            }

            return redirect()->route('driver-attendance')->with('success', $message);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    private function parseCsvDate(?string $date): string
    {
        $date = trim($date ?? '');
        if ($date === '') {
            return today()->toDateString();
        }

        foreach (['Y-m-d', 'm/d/Y', 'm/d/y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $date)->toDateString();
            } catch (Throwable) {
            }
        }

        throw new \InvalidArgumentException('Invalid attendance date.');
    }

    private function parseCsvTime(?string $time): ?string
    {
        $time = trim($time ?? '');
        if ($time === '') {
            return null;
        }

        foreach (['H:i:s', 'H:i', 'h:i A', 'h:i:s A'] as $format) {
            try {
                return Carbon::createFromFormat($format, $time)->format('H:i:s');
            } catch (Throwable) {
            }
        }

        throw new \InvalidArgumentException('Invalid time format.');
    }
}
