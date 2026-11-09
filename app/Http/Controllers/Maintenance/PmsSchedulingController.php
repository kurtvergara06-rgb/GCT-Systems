<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\PmsSchedule;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\BusPmsMonitoring;
use App\Services\Maintenance\PmsStatusService;
use App\Services\Maintenance\DdrPmsMileageService;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PmsSchedulingController extends Controller
{
    use SystemDataUpdateBroadcaster;

    private const PER_PAGE = 20;

    public function __construct(
        private readonly PmsStatusService $pmsStatusService
    ) {}

    public function index(Request $request)
    {
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
            ->groupBy(fn ($task) => strtoupper(trim((string) $task->bus_no)))
            ->map(function ($tasks, string $busNo) {
                $firstTask = $tasks->first();

                return (object) [
                    'bus_no' => $busNo,
                    'gps_report_date' => $firstTask->gps_report_date,
                    'current_km' => $firstTask->current_km,
                    'km_traveled' => $firstTask->km_traveled,
                    'tasks' => $tasks->values(),
                    // Display the nearest milestone only; these are still task-based
                    // schedules, not a verified bus-level PMS service baseline.
                    'next_pms_km' => $tasks->min('next_pms_km'),
                    'next_pms_date' => $tasks->pluck('recommended_date')->filter()->sort()->first(),
                    'has_processed_gps' => $firstTask->current_km !== null,
                    'due_pms_count' => $tasks
                        ->filter(
                            fn ($task) => in_array(
                                $task->status,
                                ['Due Soon', 'Overdue'],
                                true
                            )
                        )
                        ->count(),
                    'overall_status' => $this->pmsStatusService->overallStatus($tasks),
                ];
            })
            ->values();

        $monitoringByBus = BusPmsMonitoring::query()->with('bus')->get()
            ->keyBy(fn (BusPmsMonitoring $monitoring) => strtoupper(trim((string) $monitoring->bus?->bus_no)));
        $ddrMileageByBus = app(DdrPmsMileageService::class)->estimates($monitoringByBus->values());
        $currentOdometerByBus = $monitoringByBus->mapWithKeys(function (BusPmsMonitoring $monitoring) use ($ddrMileageByBus) {
            $estimate = $ddrMileageByBus->get($monitoring->bus_id);
            $mileage = $estimate['estimated_odometer_km'] ?? $monitoring->verified_current_odometer_km;
            return [$monitoring->bus_id => $mileage !== null ? (float) $mileage : null];
        });
        $configurableBuses = Bus::query()->orderBy('bus_no')->get(['id', 'bus_no']);
        $canConfigurePms = $request->user()?->hasSystemPermission('maintenance', 'edit') ?? false;
        // Configured buses must be visible even when no legacy PMS tasks exist.
        foreach ($monitoringByBus as $busKey => $monitoring) {
            if ($groups->contains(fn ($group) => strtoupper(trim((string) $group->bus_no)) === $busKey)) {
                continue;
            }
            $groups->push((object) [
                'bus_no' => $monitoring->bus?->bus_no,
                'gps_report_date' => null,
                'current_km' => null,
                'km_traveled' => null,
                'tasks' => collect(),
                'next_pms_km' => $monitoring->nextDueOdometerKm(),
                'next_pms_date' => $monitoring->nextDueDate(),
                'has_processed_gps' => false,
                'due_pms_count' => 0,
                'overall_status' => $monitoring->status(),
            ]);
        }
        // Bus Master List is authoritative: show every bus, including buses
        // without any PMS schedule or monitoring baseline.
        $masterBusKeys = $configurableBuses->mapWithKeys(
            fn (Bus $bus) => [strtoupper(trim((string) $bus->bus_no)) => $bus->bus_no]
        );
        $groups = $groups->filter(
            fn ($group) => $masterBusKeys->has(strtoupper(trim((string) $group->bus_no)))
        );
        foreach ($masterBusKeys as $busKey => $busNo) {
            if ($groups->contains(fn ($group) => strtoupper(trim((string) $group->bus_no)) === $busKey)) {
                continue;
            }
            $groups->push((object) [
                'bus_no' => $busNo,
                'gps_report_date' => null,
                'current_km' => null,
                'km_traveled' => null,
                'tasks' => collect(),
                'next_pms_km' => null,
                'next_pms_date' => null,
                'has_processed_gps' => false,
                'due_pms_count' => 0,
                'overall_status' => 'Not Configured',
            ]);
        }
        // Legacy schedule numbers may differ by casing/whitespace. A bus must
        // occupy one monitoring row even if old task records use other formats.
        $groups = $groups->groupBy(fn ($group) => strtoupper(trim((string) $group->bus_no)))
            ->map(function ($matches) {
                $primary = $matches->first();
                $primary->tasks = $matches->flatMap(fn ($group) => $group->tasks)->values();
                return $primary;
            })->values();
        $groups = $groups->map(function ($group) use ($monitoringByBus, $currentOdometerByBus) {
            $configuration = $monitoringByBus->get(strtoupper(trim((string) $group->bus_no)));
            $group->overall_status = $configuration?->status($configuration ? $currentOdometerByBus->get($configuration->bus_id) : null) ?? 'Not Configured';
            return $group;
        });
        $upcomingCount = $monitoringByBus->filter(fn ($monitoring) => $monitoring->status($currentOdometerByBus->get($monitoring->bus_id)) === 'Upcoming')->count();
        $dueSoonCount = $monitoringByBus->filter(fn ($monitoring) => $monitoring->status($currentOdometerByBus->get($monitoring->bus_id)) === 'Mileage Unverified')->count();
        $overdueCount = $monitoringByBus->filter(fn ($monitoring) => $monitoring->status($currentOdometerByBus->get($monitoring->bus_id)) === 'PMS Due')->count();

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
                    return $group->overall_status === $request->status;
                })
                ->values();
        }

        $sort = in_array($request->query('sort'), ['bus_no', 'current_km', 'overall_status'], true)
            ? $request->query('sort')
            : 'bus_no';
        $descending = $request->query('sort_direction') === 'desc';
        $groups = $groups->sortBy(
            fn ($group) => data_get($group, $sort),
            SORT_NATURAL | SORT_FLAG_CASE,
            $descending
        )->values();

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

        // Batch open PMS JOs to avoid one database query per rendered bus.
        $visibleBusNumbers = $rows->getCollection()->pluck('bus_no')->filter()->values();
        $openPmsJobsByBus = \App\Models\Maintenance\JobOrder::query()
            ->whereIn('bus_no', $visibleBusNumbers)
            ->where('maintenance_type', 'PMS')
            ->open()
            ->orderByDesc('id')
            ->get()
            ->keyBy(fn ($jobOrder) => strtoupper(trim((string) $jobOrder->bus_no)));

        return view('Maintenance.pms-scheduling', compact(
            'rows',
            'openPmsJobsByBus',
            'processedBuses',
            'gpsRecordsToday',
            'upcomingCount',
            'dueSoonCount',
            'overdueCount',
            'monitoringByBus',
            'ddrMileageByBus',
            'currentOdometerByBus',
            'configurableBuses',
            'canConfigurePms'
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

        $schedule = PmsSchedule::create($validated);

        $this->broadcastSystemDataUpdated('Maintenance', 'PmsSchedule', 'created', $schedule->id, 'A PMS task was created.');

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

        $pmsSchedule->update($validated);

        $this->broadcastSystemDataUpdated('Maintenance', 'PmsSchedule', 'updated', $pmsSchedule->id, 'A PMS task was updated.');

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

        $scheduleId = $pmsSchedule->id;
        $pmsSchedule->delete();

        $this->broadcastSystemDataUpdated('Maintenance', 'PmsSchedule', 'deleted', $scheduleId, 'A PMS task was deleted.');

        return redirect()
            ->to(route('PMS-Scheduling', [], false))
            ->with(
                'success',
                'PMS task deleted successfully.'
            );
    }

    /**
     * Bridge the newer bus-level monitoring baseline into the existing linked
     * PMS JO workflow. The request only prepares the JO form: it never changes
     * the bus status and does not approve or create a Job Order.
     */
    public function prepareMonitoringJobOrder(BusPmsMonitoring $monitoring)
    {
        $monitoring->loadMissing('bus');
        $bus = $monitoring->bus;
        abort_unless($bus, 404, 'The monitored bus no longer exists.');

        $estimate = app(DdrPmsMileageService::class)->estimates(collect([$monitoring]))->get($bus->id);
        $currentKm = $estimate['estimated_odometer_km'] ?? $monitoring->verified_current_odometer_km;
        abort_unless($monitoring->status($currentKm !== null ? (float) $currentKm : null) === 'PMS Due', 409,
            'The bus is not yet due for preventive maintenance.');

        // An approved PMS is a preparation only; an active trip must prevent
        // physical service start, not creation of a planned service request.
        abort_unless(in_array($bus->status, ['Active', 'Under Maintenance'], true), 409,
            'This bus is not eligible for a PMS Job Order in its current status.');

        $openPms = \App\Models\Maintenance\JobOrder::query()
            ->where('bus_no', $bus->bus_no)
            ->where('maintenance_type', 'PMS')->open()->first();
        if ($openPms) {
            return redirect()->route('job-orders', ['search' => $openPms->job_order_no])
                ->with('info', 'This bus already has an open PMS Job Order.');
        }

        // Use the existing PMS link required by the JO creation and completion
        // workflow. Do not change the baseline when preparing a draft.
        $schedule = PmsSchedule::query()->firstOrCreate(
            ['bus_no' => $bus->bus_no, 'maintenance_type' => 'PMS'],
            [
                'last_pms_km' => (float) $monitoring->last_completed_pms_odometer_km,
                'pms_interval_km' => (float) $monitoring->interval_km,
                'next_pms_km' => $monitoring->nextDueOdometerKm(),
                'recommended_date' => $monitoring->nextDueDate()->toDateString(),
            ]
        );

        return redirect()->route('job-orders', [
            'create_pms' => 1,
            'pms_schedule_id' => $schedule->id,
            'bus_no' => $bus->bus_no,
            'maintenance_type' => 'PMS',
            'problem_issue' => 'PMS due for '.$bus->bus_no.
                '. Next service: '.$monitoring->nextDueDate()->format('M d, Y').
                ' or '.number_format($monitoring->nextDueOdometerKm(), 2).' km.',
        ]);
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
            .' is '
            .strtolower($status)
            .' based on the PMS schedule threshold. '
            .'Current KM: '
            .number_format($currentKm, 2)
            .' km. Next PMS KM: '
            .number_format(
                (float) $pmsSchedule->next_pms_km,
                2
            )
            .' km.'
            .(
                $pmsSchedule->recommended_date
                    ? ' Recommended Date: '
                        .$pmsSchedule->recommended_date->format('M d, Y')
                        .'.'
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
}
