<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\PmsSchedule;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PmsSchedulingController extends Controller
{
    private const WARNING_RANGE_KM = 500;
    private const PER_PAGE = 20;

    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Sync Bus Master List to PMS Scheduling
        |--------------------------------------------------------------------------
        | Every bus in Bus Master List automatically gets one PMS schedule.
        | The Bus Master List remains the source of Last PMS / Next PMS values.
        |--------------------------------------------------------------------------
        */
        $this->syncSchedulesFromBuses();

        $schedules = PmsSchedule::query()
            ->orderBy('bus_no')
            ->get();

        $gpsByBus = $this->getLatestProcessedGpsByBus();

        $processedBuses = $gpsByBus
            ->map(function (array $gps) {
                return (object) [
                    'bus_no' => $gps['bus_no'],
                    'current_km' => $gps['current_km'],
                    'gps_report_date' => $gps['gps_report_date'],
                ];
            })
            ->sortBy('bus_no')
            ->values();

        $allRows = $schedules->map(function (PmsSchedule $schedule) use ($gpsByBus) {
            $gps = $gpsByBus->get(strtoupper(trim($schedule->bus_no)));

            $currentKm = $gps['current_km'] ?? null;
            $kmTraveled = $gps['km_traveled'] ?? 0;
            $gpsReportDate = $gps['gps_report_date'] ?? null;

            $status = 'Upcoming';
            $remainingKm = null;

            if ($currentKm !== null) {
                $remainingKm = (float) $schedule->next_pms_km - $currentKm;

                if ($currentKm >= (float) $schedule->next_pms_km) {
                    $status = 'Overdue';
                } elseif (
                    $currentKm >= ((float) $schedule->next_pms_km - self::WARNING_RANGE_KM)
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

        /*
        |--------------------------------------------------------------------------
        | Dashboard counts use all PMS records, before table search/filter.
        |--------------------------------------------------------------------------
        */
        $upcomingCount = $allRows->where('status', 'Upcoming')->count();
        $dueSoonCount = $allRows->where('status', 'Due Soon')->count();
        $overdueCount = $allRows->where('status', 'Overdue')->count();

        $filteredRows = $allRows;

        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));

            $filteredRows = $filteredRows->filter(function ($row) use ($search) {
                return str_contains(strtolower($row->bus_no), $search)
                    || str_contains(strtolower($row->status), $search)
                    || str_contains(strtolower($row->maintenance_type), $search);
            });
        }

        if ($request->filled('status') && $request->status !== 'All Status') {
            $filteredRows = $filteredRows->where('status', $request->status);
        }

        /*
        |--------------------------------------------------------------------------
        | Table pagination: 20 PMS records per page
        |--------------------------------------------------------------------------
        */
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $totalRows = $filteredRows->count();

        $rows = new LengthAwarePaginator(
            $filteredRows
                ->values()
                ->forPage($currentPage, self::PER_PAGE)
                ->values(),
            $totalRows,
            self::PER_PAGE,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $gpsRecordsToday = GpsTripRecord::query()
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
            ->whereDate('created_at', today())
            ->count();

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
                'exists:buses,bus_no',
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

        Bus::where('bus_no', $validated['bus_no'])->update([
            'last_pms_km' => $validated['last_pms_km'],
            'pms_interval_km' => $validated['pms_interval_km'],
            'next_pms_km' => $validated['next_pms_km'],
        ]);

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
                'exists:buses,bus_no',
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

        Bus::where('bus_no', $validated['bus_no'])->update([
            'last_pms_km' => $validated['last_pms_km'],
            'pms_interval_km' => $validated['pms_interval_km'],
            'next_pms_km' => $validated['next_pms_km'],
        ]);

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
            ->whereRaw(
                'UPPER(TRIM(bus_no)) = ?',
                [strtoupper(trim($pmsSchedule->bus_no))]
            )
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
                ->with('error', 'No processed GPS mileage record was found for this bus.');
        }

        $currentKm = (float) $latestGps->mileage_km;

        if ($currentKm < (float) $pmsSchedule->next_pms_km - self::WARNING_RANGE_KM) {
            return redirect()
                ->route('PMS-Scheduling')
                ->with('error', 'This PMS schedule is still Upcoming and cannot create a Job Order yet.');
        }

        $statusText = $currentKm >= (float) $pmsSchedule->next_pms_km
            ? 'overdue'
            : 'due soon';

        $issue = $pmsSchedule->maintenance_type
            . ' is ' . $statusText
            . ' based on processed GPS mileage. '
            . 'Current KM: ' . number_format($currentKm, 2)
            . ' km. Next PMS KM: '
            . number_format((float) $pmsSchedule->next_pms_km, 2)
            . ' km.';

        return redirect()->route('job-orders', [
            'create_pms' => 1,
            'pms_schedule_id' => $pmsSchedule->id,
            'bus_no' => $pmsSchedule->bus_no,
            'maintenance_type' => 'PMS',
            'problem_issue' => $issue,
        ]);
    }

    private function syncSchedulesFromBuses(): void
    {
        Bus::query()
            ->orderBy('bus_no')
            ->get()
            ->each(function (Bus $bus) {
                $lastPmsKm = (float) ($bus->last_pms_km ?? 0);
                $intervalKm = (float) ($bus->pms_interval_km ?? 5000);

                if ($intervalKm <= 0) {
                    $intervalKm = 5000;
                }

                PmsSchedule::updateOrCreate(
                    ['bus_no' => $bus->bus_no],
                    [
                        'last_pms_km' => $lastPmsKm,
                        'pms_interval_km' => $intervalKm,
                        'next_pms_km' => $lastPmsKm + $intervalKm,
                        'maintenance_type' => 'Preventive Maintenance',
                        'recommended_date' => null,
                    ]
                );
            });
    }

    private function getLatestProcessedGpsByBus()
    {
        return GpsTripRecord::query()
            ->whereNotNull('bus_no')
            ->whereNotNull('mileage_km')
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
            ->orderBy('bus_no')
            ->orderByDesc('beginning_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(function (GpsTripRecord $record) {
                return strtoupper(trim($record->bus_no));
            })
            ->map(function ($records) {
                $latestRecord = $records->first();
                $previousRecord = $records->skip(1)->first();

                $currentKm = (float) $latestRecord->mileage_km;
                $previousKm = $previousRecord
                    ? (float) $previousRecord->mileage_km
                    : null;

                return [
                    'bus_no' => $latestRecord->bus_no,
                    'current_km' => $currentKm,
                    'km_traveled' => $previousKm !== null
                        ? max(0, $currentKm - $previousKm)
                        : 0,
                    'gps_report_date' => $latestRecord->beginning_at
                        ?? $latestRecord->created_at,
                ];
            });
    }
}