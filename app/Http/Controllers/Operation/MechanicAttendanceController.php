<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\MechanicAttendance;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
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
            ->latest()
            ->paginate(8)
            ->withQueryString();

        $present = MechanicAttendance::where('status', 'Present')->count();
        $absent = MechanicAttendance::where('status', 'Absent')->count();
        $late = MechanicAttendance::where('status', 'Late')->count();
        $onDuty = MechanicAttendance::where('status', 'On Duty')->count();

        $nextMechanicId = $this->generateMechanicId();

        return view('Operation.mechanic-attendance', compact(
            'mechanicAttendances',
            'present',
            'absent',
            'late',
            'onDuty',
            'nextMechanicId'
        ));
    }

    public function store(Request $request)
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

        $validated['mechanic_id'] = $this->generateMechanicId();

        $attendance = MechanicAttendance::create($validated);

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'created',
            $attendance->id,
            'A mechanic attendance record was created.'
        );

        return redirect()
            ->route('mechanic-attendance')
            ->with('success', 'Mechanic attendance record created successfully.');
    }

    public function update(Request $request, MechanicAttendance $mechanicAttendance)
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

        $mechanicAttendance->update($validated);

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'updated',
            $mechanicAttendance->id,
            'A mechanic attendance record was updated.'
        );

        return redirect()
            ->route('mechanic-attendance')
            ->with('success', 'Mechanic attendance record updated successfully.');
    }

    public function destroy(MechanicAttendance $mechanicAttendance)
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

        return redirect()
            ->route('mechanic-attendance')
            ->with('success', 'Mechanic attendance record deleted successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('import_file');
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return redirect()
                ->route('mechanic-attendance')
                ->with('error', 'Unable to read the uploaded CSV file.');
        }

        try {
            $header = fgetcsv($handle);

            if (! $header) {
                return redirect()
                    ->route('mechanic-attendance')
                    ->with('error', 'CSV file is empty.');
            }

            $header = array_map(function ($value) {
                return strtolower(trim($value));
            }, $header);

            $requiredColumns = [
                'mechanic_name',
                'shift',
                'assigned_job',
                'attendance_date',
                'time_in',
                'time_out',
                'status',
            ];

            foreach ($requiredColumns as $column) {
                if (! in_array($column, $header, true)) {
                    return redirect()
                        ->route('mechanic-attendance')
                        ->with(
                            'error',
                            "Invalid CSV format. Missing required column: {$column}"
                        );
                }
            }

            $imported = 0;
            $skipped = 0;
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                if (count($row) !== count($header)) {
                    $skipped++;
                    continue;
                }

                $data = array_combine($header, $row);

                if (! $data || empty(trim($data['mechanic_name'] ?? ''))) {
                    $skipped++;
                    continue;
                }

                try {
                    $status = trim($data['status'] ?? 'Present');

                    if (! in_array($status, ['Present', 'Late', 'Absent', 'On Leave', 'On Duty'], true)) {
                        $status = 'Present';
                    }

                    $attendanceDate = $this->parseCsvDate(
                        $data['attendance_date'] ?? null
                    );

                    $timeIn = $this->parseCsvTime($data['time_in'] ?? null);
                    $timeOut = $this->parseCsvTime($data['time_out'] ?? null);

                    MechanicAttendance::create([
                        'mechanic_id' => $this->generateMechanicId(),
                        'mechanic_name' => trim($data['mechanic_name']),
                        'shift' => trim($data['shift'] ?? 'Morning'),
                        'assigned_job' => trim($data['assigned_job'] ?? ''),
                        'attendance_date' => $attendanceDate,
                        'time_in' => $timeIn,
                        'time_out' => $timeOut,
                        'status' => $status,
                    ]);

                    $imported++;
                } catch (Throwable $error) {
                    $skipped++;

                    return redirect()
                        ->route('mechanic-attendance')
                        ->with(
                            'error',
                            "Invalid CSV format on row {$rowNumber}. "
                            . "Use date YYYY-MM-DD and time HH:MM:SS or 08:00 AM."
                        );
                }
            }

            if ($imported === 0) {
                return redirect()
                    ->route('mechanic-attendance')
                    ->with(
                        'error',
                        'No attendance records were imported. Please check your CSV format.'
                    );
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

            return redirect()
                ->route('mechanic-attendance')
                ->with('success', $message);
        } catch (Throwable $error) {
            return redirect()
                ->route('mechanic-attendance')
                ->with(
                    'error',
                    'Unable to import the CSV file. Please check the file format and try again.'
                );
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
            return now()->format('Y-m-d');
        }

        $formats = [
            'Y-m-d',
            'm/d/Y',
            'm/d/y',
            'd/m/Y',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $date)->format('Y-m-d');
            } catch (Throwable $error) {
                continue;
            }
        }

        throw new \Exception('Invalid attendance date.');
    }

    private function parseCsvTime(?string $time): ?string
    {
        $time = trim($time ?? '');

        if ($time === '') {
            return null;
        }

        $formats = [
            'H:i:s',
            'H:i',
            'h:i A',
            'h:i:s A',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $time)->format('H:i:s');
            } catch (Throwable $error) {
                continue;
            }
        }

        throw new \Exception('Invalid time format.');
    }

    private function generateMechanicId(): string
    {
        $year = now()->format('Y');

        $lastMechanicAttendance = MechanicAttendance::where(
            'mechanic_id',
            'like',
            "M-{$year}-%"
        )
            ->orderByDesc('id')
            ->first();

        if (! $lastMechanicAttendance) {
            return "M-{$year}-0001";
        }

        preg_match(
            '/M-' . $year . '-(\d+)/',
            $lastMechanicAttendance->mechanic_id,
            $matches
        );

        $lastNumber = isset($matches[1]) ? (int) $matches[1] : 0;
        $nextNumber = $lastNumber + 1;

        $newMechanicId = 'M-' . $year . '-' . str_pad(
            $nextNumber,
            4,
            '0',
            STR_PAD_LEFT
        );

        while (MechanicAttendance::where('mechanic_id', $newMechanicId)->exists()) {
            $nextNumber++;

            $newMechanicId = 'M-' . $year . '-' . str_pad(
                $nextNumber,
                4,
                '0',
                STR_PAD_LEFT
            );
        }

        return $newMechanicId;
    }
}