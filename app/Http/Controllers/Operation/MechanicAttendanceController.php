<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class MechanicAttendanceController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function index(Request $request)
    {
        $query = MechanicAttendance::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('mechanic_id', 'like', "%{$search}%")
                    ->orWhere('mechanic_name', 'like', "%{$search}%")
                    ->orWhere('shift', 'like', "%{$search}%")
                    ->orWhere('assigned_job', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'All Status') {
            $query->where('status', $request->status);
        }

        $mechanicAttendances = $query
            ->latest('attendance_date')
            ->latest('id')
            ->paginate(8)
            ->withQueryString();

        $summaryDate = $request->filled('attendance_date')
            ? Carbon::parse($request->attendance_date)->toDateString()
            : today()->toDateString();

        $summaryQuery = MechanicAttendance::query()
            ->whereDate('attendance_date', $summaryDate);

        $present = (clone $summaryQuery)->where('status', 'Present')->count();
        $absent = (clone $summaryQuery)->where('status', 'Absent')->count();
        $late = (clone $summaryQuery)->where('status', 'Late')->count();
        $onDuty = (clone $summaryQuery)->where('status', 'On Duty')->count();

        $nextMechanicId = 'From Mechanic Master List';

        return view('Operation.Attendance.mechanic-attendance', compact(
            'mechanicAttendances',
            'present',
            'absent',
            'late',
            'onDuty',
            'nextMechanicId'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mechanic_name' => 'required|string|max:255',
            'shift' => 'required|string|max:255',
            'assigned_job' => 'nullable|string|max:255',
            'attendance_date' => 'required|date',
            'time_in' => 'nullable',
            'time_out' => 'nullable',
            'status' => 'required|string|in:Present,Late,Absent,On Leave,On Duty',
        ]);

        $mechanic = Mechanic::query()
            ->where('mechanic_name', $validated['mechanic_name'])
            ->first();

        if (! $mechanic) {
            return back()->withInput()->with('error', 'Select an existing mechanic from the Mechanic Master List.');
        }

        $validated['mechanic_id'] = $mechanic->mechanic_id;
        $validated['mechanic_name'] = $mechanic->mechanic_name;
        $validated['shift'] = $mechanic->shift ?: $validated['shift'];

        $attendance = MechanicAttendance::create($validated);

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'created',
            $attendance->id,
            'A mechanic attendance record was created.'
        );

        return redirect()->route('mechanic-attendance')
            ->with('success', 'Mechanic attendance record created successfully.');
    }

    public function update(Request $request, MechanicAttendance $mechanicAttendance): RedirectResponse
    {
        $validated = $request->validate([
            'mechanic_name' => 'required|string|max:255',
            'shift' => 'required|string|max:255',
            'assigned_job' => 'nullable|string|max:255',
            'attendance_date' => 'required|date',
            'time_in' => 'nullable',
            'time_out' => 'nullable',
            'status' => 'required|string|in:Present,Late,Absent,On Leave,On Duty',
        ]);

        $mechanic = Mechanic::query()
            ->where('mechanic_name', $validated['mechanic_name'])
            ->first();

        if (! $mechanic) {
            return back()->withInput()->with('error', 'Select an existing mechanic from the Mechanic Master List.');
        }

        $validated['mechanic_id'] = $mechanic->mechanic_id;
        $validated['mechanic_name'] = $mechanic->mechanic_name;
        $validated['shift'] = $mechanic->shift ?: $validated['shift'];

        $mechanicAttendance->update($validated);

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'updated',
            $mechanicAttendance->id,
            'A mechanic attendance record was updated.'
        );

        return redirect()->route('mechanic-attendance')
            ->with('success', 'Mechanic attendance record updated successfully.');
    }

    public function destroy(MechanicAttendance $mechanicAttendance): RedirectResponse
    {
        $attendanceId = $mechanicAttendance->id;
        $mechanicAttendance->delete();

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'deleted',
            $attendanceId,
            'A mechanic attendance record was deleted.'
        );

        return redirect()->route('mechanic-attendance')
            ->with('success', 'Mechanic attendance record deleted successfully.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => 'required|file|mimes:csv,txt',
        ]);

        $handle = fopen($request->file('import_file')->getRealPath(), 'r');

        if (! $handle) {
            return redirect()->route('mechanic-attendance')
                ->with('error', 'Unable to read the uploaded CSV file.');
        }

        try {
            $header = fgetcsv($handle);

            if (! $header) {
                return redirect()->route('mechanic-attendance')->with('error', 'CSV file is empty.');
            }

            $header = array_map(function ($value) {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value));
                return strtolower($value);
            }, $header);

            $requiredColumns = [
                'mechanic_name', 'shift', 'assigned_job', 'attendance_date', 'time_in', 'time_out', 'status',
            ];

            foreach ($requiredColumns as $column) {
                if (! in_array($column, $header, true)) {
                    return redirect()->route('mechanic-attendance')
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
                $name = trim($data['mechanic_name'] ?? '');

                if ($name === '') {
                    $skipped++;
                    continue;
                }

                $mechanic = Mechanic::query()->where('mechanic_name', $name)->first();

                if (! $mechanic) {
                    $skipped++;
                    continue;
                }

                try {
                    $status = trim($data['status'] ?? 'Present');
                    if (! in_array($status, ['Present', 'Late', 'Absent', 'On Leave', 'On Duty'], true)) {
                        $status = 'Present';
                    }

                    MechanicAttendance::updateOrCreate(
                        [
                            'mechanic_id' => $mechanic->mechanic_id,
                            'attendance_date' => $this->parseCsvDate($data['attendance_date'] ?? null),
                        ],
                        [
                            'mechanic_name' => $mechanic->mechanic_name,
                            'shift' => $mechanic->shift ?: trim($data['shift'] ?? 'Morning'),
                            'assigned_job' => trim($data['assigned_job'] ?? ''),
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
                return redirect()->route('mechanic-attendance')
                    ->with('error', 'No mechanic attendance records were imported. Check that mechanic names exist in the Mechanic Master List.');
            }

            $this->broadcastSystemDataUpdated(
                'Operation',
                'Attendance',
                'updated',
                null,
                "{$imported} mechanic attendance record(s) imported successfully."
            );

            $message = "{$imported} mechanic attendance record(s) imported successfully.";
            if ($skipped > 0) {
                $message .= " {$skipped} row(s) were skipped.";
            }

            return redirect()->route('mechanic-attendance')->with('success', $message);
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
