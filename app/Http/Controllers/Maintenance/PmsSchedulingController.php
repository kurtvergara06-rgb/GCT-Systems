<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\PmsSchedule;
use Illuminate\Http\Request;

class PmsSchedulingController extends Controller
{
    private const WARNING_RANGE_KM = 500;

    public function index(Request $request)
    {
        $schedules = PmsSchedule::query()
            ->orderBy('bus_no')
            ->get();

        $processedRecords = GpsTripRecord::query()
            ->whereNotNull('bus_no')
            ->whereNotNull('mileage_km')
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
            ->orderBy('bus_no')
            ->orderByDesc('beginning_at')
            ->orderByDesc('id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Latest processed GPS record per bus
        |--------------------------------------------------------------------------
        */
        $gpsByBus = $processedRecords
            ->groupBy('bus_no')
            ->map(function ($records) {
                $latestRecord = $records->first();
                $previousRecord = $records->skip(1)->first();

                $currentKm = (float) $latestRecord->mileage_km;

                $previousKm = $previousRecord
                    ? (float) $previousRecord->mileage_km
                    : null;

                $kmTraveled = $previousKm !== null
                    ? max(0, $currentKm - $previousKm)
                    : 0;

                return [
                    'bus_no' => $latestRecord->bus_no,
                    'current_km' => $currentKm,
                    'km_traveled' => $kmTraveled,
                    'gps_report_date' => $latestRecord->beginning_at
                        ?? $latestRecord->created_at,
                ];
            });

        /*
        |--------------------------------------------------------------------------
        | Bus dropdown source
        |--------------------------------------------------------------------------
        | Only buses that have Processed GPS mileage records appear here.
        |--------------------------------------------------------------------------
        */
        $processedBuses = $gpsByBus
            ->map(function ($gps) {
                return (object) [
                    'bus_no' => $gps['bus_no'],
                    'current_km' => $gps['current_km'],
                    'gps_report_date' => $gps['gps_report_date'],
                ];
            })
            ->sortBy('bus_no')
            ->values();

        $rows = $schedules->map(function (PmsSchedule $schedule) use ($gpsByBus) {
            $gps = $gpsByBus->get($schedule->bus_no);

            $currentKm = $gps['current_km'] ?? null;
            $kmTraveled = $gps['km_traveled'] ?? null;
            $gpsReportDate = $gps['gps_report_date'] ?? null;

            $status = 'Upcoming';
            $remainingKm = null;

            if ($currentKm !== null) {
                $remainingKm = (float) $schedule->next_pms_km - $currentKm;

                if ($currentKm >= (float) $schedule->next_pms_km) {
                    $status = 'Overdue';
                } elseif (
                    $currentKm >= (
                        (float) $schedule->next_pms_km
                        - self::WARNING_RANGE_KM
                    )
                ) {
                    $status = 'Due Soon';
                }
            }

            return (object) [
                'schedule' => $schedule,
                'bus_no' => $schedule->bus_no,
                'gps_report_date' => $gpsReportDate,
                'current_km' => $currentKm,
                'km_traveled' => $kmTraveled,
                'last_pms_km' => (float) $schedule->last_pms_km,
                'next_pms_km' => (float) $schedule->next_pms_km,
                'pms_interval_km' => (float) $schedule->pms_interval_km,
                'maintenance_type' => $schedule->maintenance_type,
                'recommended_date' => $schedule->recommended_date,
                'remaining_km' => $remainingKm,
                'status' => $status,
            ];
        });

        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));

            $rows = $rows->filter(function ($row) use ($search) {
                return str_contains(strtolower($row->bus_no), $search)
                    || str_contains(strtolower($row->status), $search)
                    || str_contains(strtolower($row->maintenance_type), $search);
            });
        }

        if (
            $request->filled('status')
            && $request->status !== 'All Status'
        ) {
            $rows = $rows->where('status', $request->status);
        }

        $gpsRecordsToday = GpsTripRecord::query()
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
            ->whereDate('created_at', today())
            ->count();

        $upcomingCount = $rows->where('status', 'Upcoming')->count();
        $dueSoonCount = $rows->where('status', 'Due Soon')->count();
        $overdueCount = $rows->where('status', 'Overdue')->count();

        return view('Maintenance.PMS-Scheduling', compact(
            'rows',
            'processedBuses',
            'gpsRecordsToday',
            'upcomingCount',
            'dueSoonCount',
            'overdueCount'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bus_no' => [
                'required',
                'string',
                'max:255',
                'unique:pms_schedules,bus_no',
            ],
            'last_pms_km' => ['required', 'numeric', 'min:0'],
            'pms_interval_km' => ['required', 'numeric', 'min:1'],
            'maintenance_type' => ['required', 'string', 'max:255'],
            'recommended_date' => ['nullable', 'date'],
        ]);

        $validated['next_pms_km'] =
            (float) $validated['last_pms_km']
            + (float) $validated['pms_interval_km'];

        PmsSchedule::create($validated);

        return redirect()
            ->route('PMS-Scheduling')
            ->with('success', 'PMS schedule created successfully.');
    }

    public function update(Request $request, PmsSchedule $pmsSchedule)
    {
        $validated = $request->validate([
            'bus_no' => [
                'required',
                'string',
                'max:255',
                'unique:pms_schedules,bus_no,' . $pmsSchedule->id,
            ],
            'last_pms_km' => ['required', 'numeric', 'min:0'],
            'pms_interval_km' => ['required', 'numeric', 'min:1'],
            'maintenance_type' => ['required', 'string', 'max:255'],
            'recommended_date' => ['nullable', 'date'],
        ]);

        $validated['next_pms_km'] =
            (float) $validated['last_pms_km']
            + (float) $validated['pms_interval_km'];

        $pmsSchedule->update($validated);

        return redirect()
            ->route('PMS-Scheduling')
            ->with('success', 'PMS schedule updated successfully.');
    }

    public function destroy(PmsSchedule $pmsSchedule)
    {
        $pmsSchedule->delete();

        return redirect()
            ->route('PMS-Scheduling')
            ->with('success', 'PMS schedule deleted successfully.');
    }

    public function createJobOrder(PmsSchedule $pmsSchedule)
    {
        $latestGps = GpsTripRecord::query()
            ->where('bus_no', $pmsSchedule->bus_no)
            ->whereNotNull('mileage_km')
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
            ->orderByDesc('beginning_at')
            ->orderByDesc('id')
            ->first();

        if (! $latestGps) {
            return redirect()
                ->route('PMS-Scheduling')
                ->with(
                    'error',
                    'No processed GPS mileage record was found for this bus.'
                );
        }

        $currentKm = (float) $latestGps->mileage_km;

        $statusText = $currentKm >= (float) $pmsSchedule->next_pms_km
            ? 'overdue'
            : 'due soon';

        $issue =
            $pmsSchedule->maintenance_type
            . ' is '
            . $statusText
            . ' based on processed GPS mileage. '
            . 'Current KM: '
            . number_format($currentKm, 2)
            . ' km. Next PMS KM: '
            . number_format((float) $pmsSchedule->next_pms_km, 2)
            . ' km.';

        return redirect()
            ->route('job-orders', [
                'create_pms' => 1,
                'pms_schedule_id' => $pmsSchedule->id,
                'bus_no' => $pmsSchedule->bus_no,
                'maintenance_type' => 'PMS',
                'problem_issue' => $issue,
            ]);
    }
}