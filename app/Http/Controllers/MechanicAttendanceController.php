<?php

namespace App\Http\Controllers;

use App\Models\MechanicAttendance;
use Illuminate\Http\Request;

class MechanicAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = MechanicAttendance::query();

        if ($request->filled('search')) {
            $search = $request->search;

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

        MechanicAttendance::create($validated);

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

        return redirect()
            ->route('mechanic-attendance')
            ->with('success', 'Mechanic attendance record updated successfully.');
    }

    public function destroy(MechanicAttendance $mechanicAttendance)
    {
        $mechanicAttendance->delete();

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
                ->with('error', 'Unable to read the uploaded file.');
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

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
            if (! in_array($column, $header)) {
                fclose($handle);

                return redirect()
                    ->route('mechanic-attendance')
                    ->with('error', "Missing CSV column: {$column}");
            }
        }

        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row)) === 0) {
                continue;
            }

            $data = array_combine($header, $row);

            if (! $data || empty($data['mechanic_name'])) {
                continue;
            }

            $status = trim($data['status'] ?? 'Present');

            if (! in_array($status, ['Present', 'Late', 'Absent', 'On Leave', 'On Duty'])) {
                $status = 'Present';
            }

            MechanicAttendance::create([
                'mechanic_id' => $this->generateMechanicId(),
                'mechanic_name' => trim($data['mechanic_name']),
                'shift' => trim($data['shift'] ?? 'Morning'),
                'assigned_job' => trim($data['assigned_job'] ?? ''),
                'attendance_date' => trim($data['attendance_date'] ?? now()->format('Y-m-d')),
                'time_in' => ! empty($data['time_in']) ? trim($data['time_in']) : null,
                'time_out' => ! empty($data['time_out']) ? trim($data['time_out']) : null,
                'status' => $status,
            ]);

            $imported++;
        }

        fclose($handle);

        return redirect()
            ->route('mechanic-attendance')
            ->with('success', "{$imported} mechanic attendance records imported successfully.");
    }

    private function generateMechanicId(): string
    {
        $year = now()->format('Y');

        $lastMechanicAttendance = MechanicAttendance::where('mechanic_id', 'like', "M-{$year}-%")
            ->orderByDesc('id')
            ->first();

        if (! $lastMechanicAttendance) {
            return "M-{$year}-0001";
        }

        preg_match('/M-' . $year . '-(\d+)/', $lastMechanicAttendance->mechanic_id, $matches);

        $lastNumber = isset($matches[1]) ? (int) $matches[1] : 0;
        $nextNumber = $lastNumber + 1;

        $newMechanicId = 'M-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (MechanicAttendance::where('mechanic_id', $newMechanicId)->exists()) {
            $nextNumber++;
            $newMechanicId = 'M-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newMechanicId;
    }
}