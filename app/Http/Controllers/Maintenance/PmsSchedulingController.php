<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\PmsSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PmsSchedulingController extends Controller
{
    private const WARNING_RANGE_KM = 500;
    private const PER_PAGE = 20;
    private const DEFAULT_AVERAGE_DAILY_KM = 250;

    public function index(Request $request)
    {
        $this->syncSchedulesFromBuses();

        $schedules = PmsSchedule::query()
            ->orderBy('bus_no')
            ->orderBy('maintenance_type')
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

        $allTasks = $schedules->map(function (PmsSchedule $schedule) use ($gpsByBus) {
            $gps = $gpsByBus->get(strtoupper(trim($schedule->bus_no)));

            $currentKm = $gps['current_km'] ?? null;
            $kmTraveled = $gps['km_traveled'] ?? 0;
            $gpsReportDate = $gps['gps_report_date'] ?? null;

            $status = $this->getPmsStatus(
                $currentKm,
                (float) $schedule->next_pms_km
            );

            $remainingKm = $currentKm !== null
                ? (float) $schedule->next_pms_km - $currentKm
                : null;

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
                'recommended_date' => $this->getRecommendedDate(
                    $currentKm,
                    (float) $schedule->next_pms_km,
                    $gpsReportDate
                ),
                'remaining_km' => $remainingKm,
                'status' => $status,
            ];
        });

        $upcomingCount = $allTasks->where('status', 'Upcoming')->count();
        $dueSoonCount = $allTasks->where('status', 'Due Soon')->count();
        $overdueCount = $allTasks->where('status', 'Overdue')->count();

        $groups = $allTasks
            ->groupBy('bus_no')
            ->map(function ($tasks, string $busNo) {
                $firstTask = $tasks->first();

                return (object) [
                    'bus_no' => $busNo,
                    'gps_report_date' => $firstTask->gps_report_date,
                    'current_km' => $firstTask->current_km,
                    'km_traveled' => $firstTask->km_traveled,
                    'tasks' => $tasks->values(),
                    'due_pms_count' => $tasks
                        ->filter(fn ($task) => in_array(
                            $task->status,
                            ['Due Soon', 'Overdue'],
                            true
                        ))
                        ->count(),
                    'overall_status' => $this->getOverallStatus($tasks),
                ];
            })
            ->values();

        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));

            $groups = $groups->filter(function ($group) use ($search) {
                return str_contains(strtolower($group->bus_no), $search)
                    || str_contains(strtolower($group->overall_status), $search)
                    || $group->tasks->contains(function ($task) use ($search) {
                        return str_contains(strtolower($task->maintenance_type), $search)
                            || str_contains(strtolower($task->status), $search);
                    });
            })->values();
        }

        if (
            $request->filled('status')
            && $request->status !== 'All Status'
        ) {
            $groups = $groups->filter(function ($group) use ($request) {
                return $group->overall_status === $request->status
                    || $group->tasks->contains(
                        fn ($task) => $task->status === $request->status
                    );
            })->values();
        }

        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $rows = new LengthAwarePaginator(
            $groups->forPage($currentPage, self::PER_PAGE)->values(),
            $groups->count(),
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
            ],
            'last_pms_km' => ['required', 'numeric', 'min:0'],
            'pms_interval_km' => ['required', 'numeric', 'min:1'],
            'maintenance_type' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pms_schedules', 'maintenance_type')
                    ->where(fn ($query) => $query->where(
                        'bus_no',
                        $request->bus_no
                    )),
            ],
        ]);

        $validated['next_pms_km'] =
            (float) $validated['last_pms_km']
            + (float) $validated['pms_interval_km'];

        $latestGps = $this->getLatestProcessedGpsForBus(
            $validated['bus_no']
        );

        $validated['recommended_date'] = $this->getRecommendedDate(
            $latestGps ? (float) $latestGps->mileage_km : null,
            (float) $validated['next_pms_km'],
            $latestGps
                ? ($latestGps->beginning_at ?? $latestGps->created_at)
                : null
        );

        PmsSchedule::create($validated);

        return redirect()
            ->route('PMS-Scheduling')
            ->with('success', 'PMS task created successfully.');
    }

    public function update(Request $request, PmsSchedule $pmsSchedule)
    {
        $validated = $request->validate([
            'bus_no' => [
                'required',
                'string',
                'max:255',
                'exists:buses,bus_no',
            ],
            'last_pms_km' => ['required', 'numeric', 'min:0'],
            'pms_interval_km' => ['required', 'numeric', 'min:1'],
            'maintenance_type' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pms_schedules', 'maintenance_type')
                    ->where(fn ($query) => $query->where(
                        'bus_no',
                        $request->bus_no
                    ))
                    ->ignore($pmsSchedule->id),
            ],
        ]);

        $validated['next_pms_km'] =
            (float) $validated['last_pms_km']
            + (float) $validated['pms_interval_km'];

        $latestGps = $this->getLatestProcessedGpsForBus(
            $validated['bus_no']
        );

        $validated['recommended_date'] = $this->getRecommendedDate(
            $latestGps ? (float) $latestGps->mileage_km : null,
            (float) $validated['next_pms_km'],
            $latestGps
                ? ($latestGps->beginning_at ?? $latestGps->created_at)
                : null
        );

        $pmsSchedule->update($validated);

        return redirect()
            ->route('PMS-Scheduling')
            ->with('success', 'PMS task updated successfully.');
    }

    public function destroy(PmsSchedule $pmsSchedule)
    {
        $hasActiveJobOrder = $pmsSchedule->jobOrders()
            ->where('status', '!=', 'Completed')
            ->exists();

        if ($hasActiveJobOrder) {
            return redirect()
                ->route('PMS-Scheduling')
                ->with(
                    'error',
                    'This PMS task cannot be deleted while it has an active Job Order.'
                );
        }

        $pmsSchedule->delete();

        return redirect()
            ->route('PMS-Scheduling')
            ->with('success', 'PMS task deleted successfully.');
    }

    public function createJobOrder(PmsSchedule $pmsSchedule)
    {
        $latestGps = $this->getLatestProcessedGpsForBus(
            $pmsSchedule->bus_no
        );

        if (! $latestGps) {
            return redirect()
                ->route('PMS-Scheduling')
                ->with(
                    'error',
                    'No processed GPS mileage record was found for this bus.'
                );
        }

        $currentKm = (float) $latestGps->mileage_km;

        if (
            $currentKm
            < (float) $pmsSchedule->next_pms_km
                - self::WARNING_RANGE_KM
        ) {
            return redirect()
                ->route('PMS-Scheduling')
                ->with(
                    'error',
                    'This PMS task is still Upcoming and cannot create a Job Order yet.'
                );
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
        $defaultTasks = [
            ['maintenance_type' => 'Change Oil', 'interval' => 5000],
            ['maintenance_type' => 'Oil Filter', 'interval' => 5000],
            ['maintenance_type' => 'Brake Check', 'interval' => 10000],
            ['maintenance_type' => 'Air Filter', 'interval' => 10000],
        ];

        Bus::query()
            ->orderBy('bus_no')
            ->get()
            ->each(function (Bus $bus) use ($defaultTasks) {
                $lastPmsKm = (float) ($bus->last_pms_km ?? 0);

                foreach ($defaultTasks as $task) {
                    PmsSchedule::firstOrCreate(
                        [
                            'bus_no' => $bus->bus_no,
                            'maintenance_type' => $task['maintenance_type'],
                        ],
                        [
                            'last_pms_km' => $lastPmsKm,
                            'pms_interval_km' => $task['interval'],
                            'next_pms_km' => $lastPmsKm + $task['interval'],
                            'recommended_date' => null,
                        ]
                    );
                }
            });
    }

    private function getPmsStatus(
        ?float $currentKm,
        float $nextPmsKm
    ): string {
        if ($currentKm === null) {
            return 'Upcoming';
        }

        if ($currentKm >= $nextPmsKm) {
            return 'Overdue';
        }

        if ($currentKm >= ($nextPmsKm - self::WARNING_RANGE_KM)) {
            return 'Due Soon';
        }

        return 'Upcoming';
    }

    private function getOverallStatus($tasks): string
    {
        if ($tasks->contains(fn ($task) => $task->status === 'Overdue')) {
            return 'Overdue';
        }

        if ($tasks->contains(fn ($task) => $task->status === 'Due Soon')) {
            return 'Due Soon';
        }

        return 'Upcoming';
    }

    private function getRecommendedDate(
        ?float $currentKm,
        float $nextPmsKm,
        $gpsReportDate
    ): ?string {
        if ($currentKm === null || ! $gpsReportDate) {
            return null;
        }

        $remainingKm = $nextPmsKm - $currentKm;

        if ($remainingKm <= 0) {
            return Carbon::parse($gpsReportDate)->toDateString();
        }

        $daysUntilPms = (int) ceil(
            $remainingKm / self::DEFAULT_AVERAGE_DAILY_KM
        );

        return Carbon::parse($gpsReportDate)
            ->addDays(max(1, $daysUntilPms))
            ->toDateString();
    }

    private function getLatestProcessedGpsForBus(
        string $busNo
    ): ?GpsTripRecord {
        return GpsTripRecord::query()
            ->whereRaw(
                'UPPER(TRIM(bus_no)) = ?',
                [strtoupper(trim($busNo))]
            )
            ->whereNotNull('mileage_km')
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
            ->orderByDesc('beginning_at')
            ->orderByDesc('id')
            ->first();
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