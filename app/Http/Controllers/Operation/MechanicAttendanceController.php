<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\JobOrder;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Support\TableSort;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class MechanicAttendanceController extends Controller
{
    use SystemDataUpdateBroadcaster;

    public function index(Request $request)
    {
        $user = $request->user();
        $module = $this->moduleFor($request);

        abort_unless(
            $user?->hasSystemPermission($module, 'view'),
            403,
            "Your role does not have permission to view {$module} records."
        );

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

        // Keep the attendance record list in sync with the selected KPI date.
        $request->validate(['attendance_date' => ['sometimes', 'required', 'date_format:Y-m-d']]);
        $summaryDate = $request->input('attendance_date')
            ?: now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $query->whereDate('attendance_date', $summaryDate);

        TableSort::apply($query, $request, ['mechanic_name' => 'mechanic_name', 'mechanic_id' => 'mechanic_id', 'shift' => 'shift', 'status' => 'status', 'time_in' => 'time_in'], 'mechanic_name');
        $mechanicAttendances = $query
            ->paginate(8)
            ->withQueryString();

        // Attendance history retains its original job reference. For today's
        // records, show the live On Going Job Order assigned in Maintenance.
        $activeJobsByMechanic = collect();
        if ($summaryDate === now(config('app.business_timezone', 'Asia/Manila'))->toDateString()) {
            $names = $mechanicAttendances->getCollection()
                ->pluck('mechanic_name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique()
                ->values();

            if ($names->isNotEmpty()) {
                $activeJobsByMechanic = JobOrder::query()->with('mechanicAssignments')
                    ->where('status', 'On Going')
                    ->where('review_status', 'Approved')
                    ->orderByDesc('id')
                    ->get()
                    ->flatMap(function (JobOrder $jobOrder) use ($names) {
                        $assignedNames = $jobOrder->mechanicAssignments->pluck('mechanic_name');
                        if ($assignedNames->isEmpty()) {
                            $assignedNames = collect(preg_split('/\s*,\s*/', (string) $jobOrder->assigned_mechanic) ?: []);
                        }

                        return $assignedNames->filter(fn ($name) => $names->contains($name))->map(fn ($name) => (object) [
                            'mechanic_name' => $name,
                            'job_order_no' => $jobOrder->job_order_no,
                        ]);
                    })->groupBy(fn ($job) => mb_strtolower(trim((string) $job->mechanic_name)));
            }
        }

        $mechanicAttendances->getCollection()->each(function (MechanicAttendance $attendance) use ($activeJobsByMechanic): void {
            $jobs = $activeJobsByMechanic->get(mb_strtolower(trim((string) $attendance->mechanic_name)));
            $attendance->display_assigned_job = $jobs && $jobs->isNotEmpty()
                ? $jobs->pluck('job_order_no')->filter()->unique()->implode(', ')
                : (trim((string) $attendance->assigned_job) ?: 'Unassigned');
        });

        // Preload active JO names for permission-aware Edit buttons.
        $mechanicNames = $mechanicAttendances->getCollection()->pluck('mechanic_name')->filter()->unique();
        $busyMechanicNames = $activeJobsByMechanic->keys();
        $mechanicAttendances->getCollection()->each(function (MechanicAttendance $attendance) use ($busyMechanicNames): void {
            $attendance->has_active_job = $busyMechanicNames->contains(mb_strtolower(trim((string) $attendance->mechanic_name)));
        });

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
            'nextMechanicId',
            'summaryDate'
        ));
    }

    private function normalizeAttendanceTimes(Request $request): void
    {
        $normalized = [];
        foreach (['time_in', 'time_out'] as $field) {
            $raw = $request->input($field);
            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }
            $value = trim($raw);
            foreach (['H:i', 'H:i:s', 'h:i A', 'g:i A', 'h:i:s A', 'g:i:s A'] as $format) {
                try {
                    $parsed = Carbon::createFromFormat('!'.$format, strtoupper($value));
                    if ($parsed && $parsed->format($format) === strtoupper($value)) {
                        $normalized[$field] = $parsed->format('H:i');
                        break;
                    }
                } catch (Throwable) {
                    // Keep the invalid input unchanged for standard validation.
                }
            }
        }
        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeModuleAction($request, 'edit');
        $this->normalizeAttendanceTimes($request);
        $validated = $request->validate([
            'mechanic_name' => 'required|string|max:255',
            'mechanic_id' => 'nullable|string|max:255',
            'shift' => 'required|string|in:Morning',
            'attendance_date' => 'required|date_format:Y-m-d',
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'status' => 'required|string|in:Present,Late,Absent,On Leave,On Duty',
        ]);

        $mechanicQuery = Mechanic::query();
        if (! empty($validated['mechanic_id'])) {
            $mechanicQuery->where('mechanic_id', $validated['mechanic_id']);
        } else {
            $mechanicQuery->where('mechanic_name', $validated['mechanic_name']);
            if ((clone $mechanicQuery)->count() > 1) {
                throw ValidationException::withMessages([
                    'mechanic_id' => 'Two mechanics have this name. Select the correct Mechanic ID.',
                ]);
            }
        }
        $mechanic = $mechanicQuery->first();

        if (! $mechanic) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Select an existing mechanic from the Mechanic Master List.',
                    'errors' => [
                        'mechanic_name' => ['Select an existing mechanic from the Mechanic Master List.'],
                    ],
                ], 422);
            }

            return back()->withInput()->with('error', 'Select an existing mechanic from the Mechanic Master List.');
        }

        if ($mechanic->employment_status !== 'Active') {
            throw ValidationException::withMessages([
                'mechanic_name' => 'Only active mechanics can receive new attendance records.',
            ]);
        }

        if (! in_array($mechanic->shift, ['Morning'], true)) {
            throw ValidationException::withMessages([
                'mechanic_name' => 'New mechanic attendance requires a Morning master-list shift.',
            ]);
        }

        $validated['mechanic_id'] = $mechanic->mechanic_id;
        $validated['mechanic_name'] = $mechanic->mechanic_name;
        $validated['shift'] = 'Morning';
        if (! in_array($validated['status'], ['Absent', 'On Leave'], true) && ! empty($validated['time_in'])) {
            $validated['status'] = $validated['time_in'] > '08:00' ? 'Late' : 'Present';
        }

        // Serialize attendance writes per master-list employee to protect the
        // model's duplicate-date check against simultaneous requests.
        $attendance = DB::transaction(function () use ($validated) {
            Mechanic::query()->where('mechanic_id', $validated['mechanic_id'])->lockForUpdate()->firstOrFail();

            return MechanicAttendance::create($validated);
        });

        $this->broadcastSystemDataUpdated(
            $this->moduleName($request),
            'Attendance',
            'created',
            $attendance->id,
            'A mechanic attendance record was created.'
        );

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Mechanic attendance record created successfully.',
                'attendance' => $attendance,
            ]);
        }

        return redirect()->route('mechanic-attendance')
            ->with('success', 'Mechanic attendance record created successfully.');
    }

    public function update(Request $request, MechanicAttendance $mechanicAttendance): JsonResponse|RedirectResponse
    {
        $this->authorizeModuleAction($request, 'edit');
        $this->normalizeAttendanceTimes($request);
        if (JobOrder::query()->assignedToMechanic($mechanicAttendance->mechanic_name, $mechanicAttendance->mechanic_id)
            ->where('status', 'On Going')->where('review_status', 'Approved')->exists()) {
            throw ValidationException::withMessages([
                'attendance' => 'Attendance is locked while this employee has an active assignment. Update assignments through their owning module.',
            ]);
        }

        $validated = $request->validate([
            'shift' => 'required|string|in:Morning',
            'attendance_date' => 'required|date_format:Y-m-d',
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'status' => 'required|string|in:Present,Late,Absent,On Leave,On Duty',
        ]);

        // Never allow an attendance edit to reassign the record to another person.
        $mechanic = Mechanic::query()
            ->where('mechanic_id', $mechanicAttendance->mechanic_id)
            ->first();

        if (! $mechanic) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Select an existing mechanic from the Mechanic Master List.',
                    'errors' => [
                        'mechanic_name' => ['Select an existing mechanic from the Mechanic Master List.'],
                    ],
                ], 422);
            }

            return back()->withInput()->with('error', 'Select an existing mechanic from the Mechanic Master List.');
        }

        $validated['mechanic_id'] = $mechanic->mechanic_id;
        $validated['mechanic_name'] = $mechanic->mechanic_name;
        $validated['shift'] = 'Morning';
        if (! in_array($validated['status'], ['Absent', 'On Leave'], true) && ! empty($validated['time_in'])) {
            $validated['status'] = $validated['time_in'] > '08:00' ? 'Late' : 'Present';
        }

        // Prevent an ongoing Maintenance Job Order from being made unavailable
        // or moved to a different attendance day.
        if (JobOrder::query()
            ->assignedToMechanic($mechanicAttendance->mechanic_name, $mechanicAttendance->mechanic_id)
            ->where('status', 'On Going')
            ->where('review_status', 'Approved')
            ->exists()
            && ($mechanicAttendance->attendance_date?->toDateString() !== $validated['attendance_date']
                || in_array($validated['status'], ['Absent', 'On Leave'], true))) {
            throw ValidationException::withMessages([
                'status' => 'A mechanic with an ongoing Job Order cannot be marked unavailable or moved to another date.',
            ]);
        }

        DB::transaction(function () use ($mechanicAttendance, $validated) {
            Mechanic::query()->where('mechanic_id', $mechanicAttendance->mechanic_id)->lockForUpdate()->firstOrFail();
            $mechanicAttendance->update($validated);
        });

        $this->broadcastSystemDataUpdated(
            $this->moduleName($request),
            'Attendance',
            'updated',
            $mechanicAttendance->id,
            'A mechanic attendance record was updated.'
        );

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Mechanic attendance record updated successfully.',
                'attendance' => $mechanicAttendance,
            ]);
        }

        return redirect()->route('mechanic-attendance')
            ->with('success', 'Mechanic attendance record updated successfully.');
    }

    public function destroy(Request $request, MechanicAttendance $mechanicAttendance): JsonResponse|RedirectResponse
    {
        $this->authorizeModuleAction($request, 'edit');
        $deleted = DB::transaction(function () use ($mechanicAttendance): bool {
            // Use the master mechanic lock consistently with attendance updates.
            Mechanic::query()->where('mechanic_id', $mechanicAttendance->mechanic_id)->lockForUpdate()->firstOrFail();
            $locked = MechanicAttendance::query()->lockForUpdate()->findOrFail($mechanicAttendance->id);
            if (JobOrder::query()
                ->assignedToMechanic($locked->mechanic_name, $locked->mechanic_id)
                ->where('status', 'On Going')
                ->where('review_status', 'Approved')
                ->exists()) {
                return false;
            }
            $locked->delete();

            return true;
        }, 5);

        if (! $deleted) {
            $message = 'This mechanic has an active Job Order. Attendance cannot be deleted.';
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('mechanic-attendance')->with('error', $message);
        }

        $this->broadcastSystemDataUpdated(
            $this->moduleName($request), 'Attendance', 'deleted', $mechanicAttendance->id,
            'A mechanic attendance record was deleted.'
        );
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Mechanic attendance record deleted successfully.']);
        }

        return redirect()->route('mechanic-attendance')->with('success', 'Mechanic attendance record deleted successfully.');
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizeModuleAction($request, 'edit');
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
                    $importTimeIn = $this->parseCsvTime($data['time_in'] ?? null);
                    if (! in_array($status, ['Absent', 'On Leave'], true) && $importTimeIn) {
                        $status = substr((string) $importTimeIn, 0, 5) > '08:00' ? 'Late' : 'Present';
                    }
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
                $this->moduleName($request),
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

    private function moduleFor(Request $request): string
    {
        $department = strtolower(trim((string) $request->user()?->department));

        return in_array($department, ['operation', 'operations'], true) ? 'operation' : 'maintenance';
    }

    private function moduleName(Request $request): string
    {
        return ucfirst($this->moduleFor($request));
    }

    private function authorizeModuleAction(Request $request, string $action): void
    {
        $module = $this->moduleFor($request);

        abort_unless(
            $request->user()?->hasSystemPermission($module, $action),
            403,
            'Your role does not have permission for this action.'
        );
    }

    private function parseCsvDate(?string $date): string
    {
        $date = trim($date ?? '');
        if ($date === '') {
            return now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
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
