<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\PmsSchedule;
use App\Services\Maintenance\PmsStatusService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PmsSchedulingController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly PmsStatusService $pmsStatusService
    ) {
    }

    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Synchronize PMS records with the official Bus Master List
        |--------------------------------------------------------------------------
        |
        | This will:
        | 1. Remove PMS records belonging to deleted buses.
        | 2. Create missing default PMS tasks for existing buses.
        |
        */

        $this->syncSchedulesFromBuses();

        $schedules = PmsSchedule::query()
            ->orderBy('bus_no')
            ->orderBy('maintenance_type')
            ->get();

        $gpsByBus = $this->pmsStatusService->latestProcessedGpsByBus();

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

        $allTasks = $schedules->map(
            function (PmsSchedule $schedule) use ($gpsByBus) {
                $normalizedBusNo = strtoupper(
                    trim((string) $schedule->bus_no)
                );

                $gps = $gpsByBus->get($normalizedBusNo);

                $assessment = $this->pmsStatusService->assess(
                    $schedule,
                    $gps
                );

                return (object) [
                    'schedule' => $schedule,
                    'bus_no' => $schedule->bus_no,
                    'gps_report_date' => $assessment['gps_report_date'],
                    'current_km' => $assessment['current_km'],
                    'km_traveled' => $assessment['km_traveled'],
                    'last_pms_km' => (float) $schedule->last_pms_km,
                    'next_pms_km' => $assessment['next_pms_km'],
                    'pms_interval_km' => (float) $schedule->pms_interval_km,
                    'maintenance_type' => $schedule->maintenance_type,
                    'recommended_date' => $assessment['recommended_date'],
                    'remaining_km' => $assessment['remaining_km'],
                    'status' => $assessment['status'],
                ];
            }
        );

        $upcomingCount = $allTasks
            ->where('status', 'Upcoming')
            ->count();

        $dueSoonCount = $allTasks
            ->where('status', 'Due Soon')
            ->count();

        $overdueCount = $allTasks
            ->where('status', 'Overdue')
            ->count();

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
                        ->filter(
                            fn ($task) => in_array(
                                $task->status,
                                ['Due Soon', 'Overdue'],
                                true
                            )
                        )
                        ->count(),
                    'overall_status' => $this->getOverallStatus($tasks),
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = strtolower(trim((string) $request->search));

            $groups = $groups
                ->filter(function ($group) use ($search) {
                    return str_contains(
                        strtolower((string) $group->bus_no),
                        $search
                    )
                        || str_contains(
                            strtolower((string) $group->overall_status),
                            $search
                        )
                        || $group->tasks->contains(
                            function ($task) use ($search) {
                                return str_contains(
                                    strtolower(
                                        (string) $task->maintenance_type
                                    ),
                                    $search
                                )
                                    || str_contains(
                                        strtolower((string) $task->status),
                                        $search
                                    );
                            }
                        );
                })
                ->values();
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('status')
            && $request->status !== 'All Status'
        ) {
            $groups = $groups
                ->filter(function ($group) use ($request) {
                    return $group->overall_status === $request->status
                        || $group->tasks->contains(
                            fn ($task) => $task->status
                                === $request->status
                        );
                })
                ->values();
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $currentPage = LengthAwarePaginator::resolveCurrentPage();

            $rows = new LengthAwarePaginator(
                $groups
                    ->forPage($currentPage, self::PER_PAGE)
                    ->values(),
                $groups->count(),
                self::PER_PAGE,
                $currentPage,
                [
                    'path' => route('PMS-Scheduling', [], false),
                    'query' => $request->except('page'),
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | GPS Records Today
        |--------------------------------------------------------------------------
        */

        $gpsRecordsToday = GpsTripRecord::query()
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
            ->whereDate('created_at', today())
            ->count();

        return view('Maintenance.pms-scheduling', compact(
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

            'last_pms_km' => [
                'required',
                'numeric',
                'min:0',
            ],

            'pms_interval_km' => [
                'required',
                'numeric',
                'min:1',
            ],

            'maintenance_type' => [
                'required',
                'string',
                'max:255',

                Rule::unique(
                    'pms_schedules',
                    'maintenance_type'
                )->where(
                    fn ($query) => $query->where(
                        'bus_no',
                        $request->bus_no
                    )
                ),
            ],
        ]);

        $validated['next_pms_km'] =
            (float) $validated['last_pms_km']
            + (float) $validated['pms_interval_km'];

        $latestGps = $this->pmsStatusService->latestProcessedGpsForBus(
            $validated['bus_no']
        );

        $validated['recommended_date'] =
            $this->pmsStatusService->recommendedDate(
                $latestGps
                    ? (float) $latestGps->mileage_km
                    : null,
                (float) $validated['next_pms_km'],
                $latestGps
                    ? (
                        $latestGps->beginning_at
                        ?? $latestGps->created_at
                    )
                    : null
            )?->toDateString();

        PmsSchedule::create($validated);

        return redirect()
            ->to(route('PMS-Scheduling', [], false))
            ->with(
                'success',
                'PMS task created successfully.'
            );
    }

    public function update(
        Request $request,
        PmsSchedule $pmsSchedule
    ) {
        $validated = $request->validate([
            'bus_no' => [
                'required',
                'string',
                'max:255',
                'exists:buses,bus_no',
            ],

            'last_pms_km' => [
                'required',
                'numeric',
                'min:0',
            ],

            'pms_interval_km' => [
                'required',
                'numeric',
                'min:1',
            ],

            'maintenance_type' => [
                'required',
                'string',
                'max:255',

                Rule::unique(
                    'pms_schedules',
                    'maintenance_type'
                )
                    ->where(
                        fn ($query) => $query->where(
                            'bus_no',
                            $request->bus_no
                        )
                    )
                    ->ignore($pmsSchedule->id),
            ],
        ]);

        $validated['next_pms_km'] =
            (float) $validated['last_pms_km']
            + (float) $validated['pms_interval_km'];

        $latestGps = $this->getLatestProcessedGpsForBus(
            $validated['bus_no']
        );

        $validated['recommended_date'] =
            $this->getRecommendedDate(
                $latestGps
                    ? (float) $latestGps->mileage_km
                    : null,
                (float) $validated['next_pms_km'],
                $latestGps
                    ? (
                        $latestGps->beginning_at
                        ?? $latestGps->created_at
                    )
                    : null
            );

        $pmsSchedule->update($validated);

        return redirect()
            ->to(route('PMS-Scheduling', [], false))
            ->with(
                'success',
                'PMS task updated successfully.'
            );
    }

    public function destroy(PmsSchedule $pmsSchedule)
    {
        $hasActiveJobOrder = $pmsSchedule
            ->jobOrders()
            ->where('status', '!=', 'Completed')
            ->exists();

        if ($hasActiveJobOrder) {
            return redirect()
                ->to(route('PMS-Scheduling', [], false))
                ->with(
                    'error',
                    'This PMS task cannot be deleted while it has an active Job Order.'
                );
        }

        $pmsSchedule->delete();

        return redirect()
            ->to(route('PMS-Scheduling', [], false))
            ->with(
                'success',
                'PMS task deleted successfully.'
            );
    }

    public function createJobOrder(PmsSchedule $pmsSchedule)
    {
        $latestGps = $this->pmsStatusService->latestProcessedGpsForBus(
            $pmsSchedule->bus_no
        );

        if (! $latestGps) {
            return redirect()
                ->to(route('PMS-Scheduling', [], false))
                ->with(
                    'error',
                    'No processed GPS mileage record was found for this bus.'
                );
        }

        $currentKm = (float) $latestGps->mileage_km;

        $status = $this->pmsStatusService->determineStatus(
            $currentKm,
            (float) $pmsSchedule->next_pms_km,
            $pmsSchedule->recommended_date
        );

        if ($status === 'Upcoming') {
            return redirect()
                ->to(route('PMS-Scheduling', [], false))
                ->with(
                    'error',
                    'This PMS task is still Upcoming and cannot create a Job Order yet.'
                );
        }

        $issue = $pmsSchedule->maintenance_type
            . ' is '
            . strtolower($status)
            . ' based on the PMS schedule threshold. '
            . 'Current KM: '
            . number_format($currentKm, 2)
            . ' km. Next PMS KM: '
            . number_format(
                (float) $pmsSchedule->next_pms_km,
                2
            )
            . ' km.'
            . (
                $pmsSchedule->recommended_date
                    ? ' Recommended Date: '
                        . $pmsSchedule->recommended_date->format('M d, Y')
                        . '.'
                    : ''
            );

        return redirect()->to(
            route(
                'job-orders',
                [
                    'create_pms' => 1,
                    'pms_schedule_id' => $pmsSchedule->id,
                    'bus_no' => $pmsSchedule->bus_no,
                    'maintenance_type' => 'PMS',
                    'problem_issue' => $issue,
                ],
                false
            )
        );
            }

    /*
    |--------------------------------------------------------------------------
    | Synchronize PMS Schedules with Bus Master List
    |--------------------------------------------------------------------------
    |
    | The Bus Master List is the official source of buses.
    |
    | This method:
    | 1. Deletes PMS schedules for buses no longer in the master list.
    | 2. Deletes related Job Orders for removed PMS schedules.
    | 3. Creates missing default PMS tasks for valid buses.
    |
    */

    private function syncSchedulesFromBuses(): void
    {
        $defaultTasks = [
            [
                'maintenance_type' => 'Change Oil',
                'interval' => 5000,
            ],
            [
                'maintenance_type' => 'Oil Filter',
                'interval' => 5000,
            ],
            [
                'maintenance_type' => 'Brake Check',
                'interval' => 10000,
            ],
            [
                'maintenance_type' => 'Air Filter',
                'interval' => 10000,
            ],
        ];

        DB::transaction(function () use ($defaultTasks) {
            /*
            |--------------------------------------------------------------------------
            | Retrieve official buses
            |--------------------------------------------------------------------------
            */

            $buses = Bus::query()
                ->orderBy('bus_no')
                ->get();

            $officialBusNumbers = $buses
                ->pluck('bus_no')
                ->map(
                    fn ($busNo) => strtoupper(
                        trim((string) $busNo)
                    )
                )
                ->filter()
                ->unique()
                ->values();

            /*
            |--------------------------------------------------------------------------
            | Delete orphaned PMS schedules
            |--------------------------------------------------------------------------
            |
            | A PMS schedule is orphaned when its bus number is no longer
            | found in the official Bus Master List.
            |
            */

            $orphanedSchedules = PmsSchedule::query()
                ->with('jobOrders')
                ->get()
                ->filter(function (PmsSchedule $schedule) use (
                    $officialBusNumbers
                ) {
                    $scheduleBusNo = strtoupper(
                        trim((string) $schedule->bus_no)
                    );

                    return ! $officialBusNumbers->contains(
                        $scheduleBusNo
                    );
                });

            foreach ($orphanedSchedules as $schedule) {
                $hasJobOrders = $schedule
                    ->jobOrders()
                    ->exists();

                if ($hasJobOrders) {
                    continue;
                }

                $schedule->delete();
            }

            /*
            |--------------------------------------------------------------------------
            | Create missing default PMS tasks
            |--------------------------------------------------------------------------
            */

            foreach ($buses as $bus) {
                $lastPmsKm = (float) (
                    $bus->last_pms_km ?? 0
                );

                foreach ($defaultTasks as $task) {
                    PmsSchedule::firstOrCreate(
                        [
                            'bus_no' => $bus->bus_no,
                            'maintenance_type' =>
                                $task['maintenance_type'],
                        ],
                        [
                            'last_pms_km' => $lastPmsKm,
                            'pms_interval_km' =>
                                $task['interval'],
                            'next_pms_km' =>
                                $lastPmsKm
                                + $task['interval'],
                            'recommended_date' => null,
                        ]
                    );
                }
            }
        });
    }


}