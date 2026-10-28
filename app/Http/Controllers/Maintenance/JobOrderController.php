<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\ItemRequest;
use App\Models\Maintenance\PmsSchedule;
use App\Models\Maintenance\PurchaseRequest;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Services\Maintenance\JobOrderDurationService;
use App\Services\Maintenance\JobOrderWorkflowService;
use App\Services\Maintenance\LinkedJobOrderResetService;
use App\Services\Maintenance\PurchaseRequestPartsService;
use App\Services\PartParser;
use App\Traits\SystemDataUpdateBroadcaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JobOrderController extends Controller
{
    use SystemDataUpdateBroadcaster;

    private PartParser $partParser;

    private LinkedJobOrderResetService $linkedJobOrderResetService;

    private JobOrderWorkflowService $workflow;

    private JobOrderDurationService $durations;

    private PurchaseRequestPartsService $purchaseRequestParts;

    public function __construct(
        PartParser $partParser,
        LinkedJobOrderResetService $linkedJobOrderResetService,
        JobOrderWorkflowService $workflow,
        JobOrderDurationService $durations,
        PurchaseRequestPartsService $purchaseRequestParts
    ) {
        $this->partParser = $partParser;
        $this->linkedJobOrderResetService = $linkedJobOrderResetService;
        $this->workflow = $workflow;
        $this->durations = $durations;
        $this->purchaseRequestParts = $purchaseRequestParts;
    }

    /* =========================================================
       INDEX
    ========================================================= */

    public function index(Request $request)
    {
        $query = JobOrder::query();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('job_order_no', 'like', "%{$search}%")
                    ->orWhere('bus_no', 'like', "%{$search}%")
                    ->orWhere('problem_issue', 'like', "%{$search}%")
                    ->orWhere('maintenance_type', 'like', "%{$search}%")
                    ->orWhere('assigned_mechanic', 'like', "%{$search}%")
                    ->orWhere('part_needed', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('part_status', 'like', "%{$search}%");
            });
        }

        if (
            $request->filled('part_status')
            && $request->part_status !== 'All Part Statuses'
        ) {
            if (in_array($request->part_status, ['No Parts Required', 'No Parts Needed'], true)) {
                $query->where(function ($q) {
                    $q->whereNull('part_needed')
                        ->orWhereRaw("TRIM(COALESCE(part_needed, '')) = ''")
                        ->orWhereIn('part_status', ['No Parts Required', 'No Parts Needed']);
                });
            } elseif ($request->part_status === 'Not Requested') {
                $query->where('part_status', 'Not Requested')
                    ->whereNotNull('part_needed')
                    ->whereRaw("TRIM(part_needed) != ''");
            } else {
                $query->where(
                    'part_status',
                    $request->part_status
                );
            }
        }

        if (
            $request->filled('maintenance_type')
            && $request->maintenance_type !== 'All Types'
        ) {
            $query->where(
                'maintenance_type',
                $request->maintenance_type
            );
        }

        /*
         * Load the complete filtered Job Order dataset into one paginator page.
         * The table itself handles vertical scrolling after eight visible rows,
         * so Previous/Next controls are no longer needed for normal JO volumes.
         */
        $jobOrders = $query
            ->latest()
            ->paginate(1000)
            ->withQueryString();

        // Older IRs were created without advancing the Job Order part_status.
        // Use the linked IR for the display instead of falsely showing
        // "Not Requested". This does not mutate historical database records.
        $existingIrStatuses = ItemRequest::query()
            ->whereIn('job_order_id', $jobOrders->getCollection()->pluck('id'))
            ->orderByDesc('id')
            ->get(['job_order_id', 'status'])
            ->unique('job_order_id')
            ->pluck('status', 'job_order_id');
        // Preserve the IR mapping as explicit view data. Mutating an Eloquent
        // model in a paginator does not reliably describe the persisted workflow.

        $onHold = JobOrder::where(
            'status',
            'On Hold'
        )->count();

        $onGoing = JobOrder::where(
            'status',
            'On Going'
        )->count();

        $completed = JobOrder::where('status', 'Completed')
            ->where('review_status', 'Approved')
            ->count();

        $needParts = JobOrder::query()
            ->whereNotNull('part_needed')
            ->whereRaw("TRIM(part_needed) <> ''")
            ->where('status', '!=', 'Completed')
            ->whereNotIn('part_status', ['Issued'])
            ->count();

        $nextJobOrderNo =
            $this->generateJobOrderNo();

        /* =====================================================
           AVAILABLE MECHANICS
        ====================================================== */

        $assignedActiveMechanics =
            JobOrder::query()
                ->where(
                    'status',
                    '!=',
                    'Completed'
                )
                ->whereNotNull(
                    'assigned_mechanic'
                )
                ->where(
                    'assigned_mechanic',
                    '!=',
                    ''
                )
                ->pluck(
                    'assigned_mechanic'
                )
                ->filter()
                ->unique()
                ->values();

        $availableMechanics =
            MechanicAttendance::query()
                ->whereDate('attendance_date', now(config('app.business_timezone', 'Asia/Manila'))->toDateString())
                ->whereIn(
                    'status',
                    ['Present', 'Late', 'On Duty']
                )
                ->whereHas('mechanic', fn ($query) => $query->where('employment_status', 'Active'))
                ->whereNotIn(
                    'mechanic_name',
                    $assignedActiveMechanics
                )
                ->orderBy(
                    'mechanic_name'
                )
                ->get();

        // Edit JO selections store mechanics.id, not mechanic_attendances.id.
        // Include existing assignments even if today's attendance has changed;
        // the update workflow still validates actual availability.
        $allMechanics = Mechanic::query()
            ->orderBy('mechanic_name')
            ->get();

        /* =====================================================
           BUSES
        ====================================================== */

        $buses = Bus::query()
            ->with('authorizedDrivers:id,driver_name')
            ->where(
                'status',
                'Active'
            )
            ->orderBy(
                'bus_no'
            )
            ->get();

        $activeBusNumbers =
            JobOrder::query()
                ->where(
                    'status',
                    '!=',
                    'Completed'
                )
                ->pluck(
                    'bus_no'
                )
                ->filter()
                ->unique()
                ->values();

        $availableBuses =
            $buses
                ->reject(
                    fn ($bus) =>
                        $activeBusNumbers->contains($bus->bus_no)
                        || $bus->hasOngoingDispatch()
                )
                ->values();

        /* =====================================================
           PMS CREATE
        ====================================================== */

        $pmsCreate = null;

        if (
            $request->boolean('create_pms')
            && $request->filled(
                'pms_schedule_id'
            )
        ) {
            $pmsCreate =
                PmsSchedule::find(
                    $request->integer(
                        'pms_schedule_id'
                    )
                );
        }

        return view(
            'Maintenance.job-order',
            compact(
                'jobOrders',
                'existingIrStatuses',
                'onHold',
                'onGoing',
                'completed',
                'needParts',
                'nextJobOrderNo',
                'availableMechanics',
                'allMechanics',
                'buses',
                'availableBuses',
                'pmsCreate'
            )
        );
    }

    public function inventoryOptions()
    {
        return response()->json(\App\Models\Warehouse\InventoryItem::query()
            ->orderBy('parts_name')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => (string) ($item->parts_name ?: $item->item_name),
                'code' => (string) $item->item_code,
                'unit' => trim((string) ($item->unit ?: $item->unit_of_measurement)),
                'in_stock' => (int) ($item->quantity_available ?? $item->on_hand ?? 0) > 0,
            ]));
    }

    public function specializationOptions()
    {
        return response()->json(DB::table('mechanic_specialization_options')
            ->orderBy('name')->pluck('name'));
    }

    public function availableMechanics(Request $request)
    {
        $jobs = $request->input('maintenance_jobs', [$request->input('maintenance_job', '')]);
        $jobs = is_array($jobs) ? collect($jobs)->filter(
            fn ($job) => is_string($job) && trim($job) !== ''
        )->values()->all() : [];
        $selectedSkills = collect($jobs)->filter(fn ($job) => is_string($job))->flatMap(
            fn ($job) => $this->requiredSpecializations(trim($job), (array) $request->input('specializations', []))
        )->unique()->values()->all();
        $workText = implode(' ', $selectedSkills);
        $mechanics = $this->workflow->availableMechanics(null, $workText)
            ->filter(fn (Mechanic $mechanic) => $jobs === []
                || collect($jobs)->contains(fn ($job) => is_string($job)
                    && $this->requiredSpecializations(trim($job), (array) $request->input('specializations', [])) !== []
                    && $this->matchesSpecialization($mechanic, $this->requiredSpecializations(trim($job), (array) $request->input('specializations', [])))))
            ->values()
            ->map(fn (Mechanic $mechanic) => [
                'id' => $mechanic->id,
                'mechanic_id' => $mechanic->mechanic_id,
                'mechanic_name' => $mechanic->mechanic_name,
                'specializations' => $mechanic->specialization_labels,
                'skill_matches' => $mechanic->skill_matches,
                'skill_match_score' => $mechanic->skill_match_score,
                'qualified_jobs' => collect($jobs)->filter(fn ($job) => $this->matchesSpecialization(
                    $mechanic, $this->requiredSpecializations((string) $job, (array) $request->input('specializations', []))
                ))->values()->all(),
                'availability' => 'Available',
            ]);

        return response()->json($mechanics);
    }

    public function durationSuggestion(Request $request)
    {
        $validated = $request->validate(['maintenance_type' => 'required|string|max:255']);
        $maintenanceJob = trim((string) $request->input('maintenance_job', ''));
        $customJob = trim((string) $request->input('custom_maintenance_job', ''));
        $specificWork = $maintenanceJob === 'Other' ? $customJob : $maintenanceJob;
        $suggestion = $this->durations->suggestion($validated['maintenance_type'], $specificWork);

        return response()->json([
            'available' => $suggestion !== null,
            'suggestion' => $suggestion,
            'message' => $suggestion
                ? 'Historical median for matching completed, approved maintenance work.'
                : 'Fewer than 3 matching completed Job Orders with valid actual durations. Enter a proposed duration manually.',
        ]);
    }

    /**
     * Detailed stock breakdown for authorized Maintenance Head JO review only.
     * Do not expose numerical on-hand quantities through the staff endpoint.
     */
    public function headReviewInventoryAvailability(Request $request)
    {
        $this->assertMaintenanceHead();
        $validated = $request->validate([
            'parts' => 'nullable|array',
            'parts.*.name' => 'required|string|max:255',
            'parts.*.quantity' => 'required|integer|min:1',
            'parts.*.unit' => 'nullable|string|max:50',
            'parts.*.inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
        ]);
        $result = $this->workflow->inventoryAvailability(
            $this->partParser->normalizePartsInput($validated['parts'] ?? [])
        );

        return response()->json([
            'parts' => array_map(static function (array $part): array {
                $required = (int) $part['quantity'];
                $available = (int) $part['available'];
                return [
                    'required' => $required,
                    'available' => $available,
                    'shortage' => max(0, $required - $available),
                    'status' => $available >= $required ? 'Available'
                        : ($available > 0 ? 'Partially Available' : 'Unavailable'),
                ];
            }, $result['parts']),
        ]);
    }

    public function inventoryAvailability(Request $request)
    {
        $validated = $request->validate([
            'parts' => 'nullable|array',
            'parts.*.name' => 'required|string|max:255',
            'parts.*.quantity' => 'required|integer|min:1',
            'parts.*.unit' => 'nullable|string|max:50',
            'parts.*.inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
        ]);

        $result = $this->workflow->inventoryAvailability(
            $this->partParser->normalizePartsInput($validated['parts'] ?? [])
        );
        // This Maintenance-facing endpoint must never expose on-hand stock,
        // inventory IDs or numeric shortages to browser clients.
        return response()->json([
            'status' => $result['status'],
            'parts' => array_map(static fn (array $part): array => [
                'name' => $part['name'],
                'fulfillable' => $part['fulfillable'],
                'in_stock' => $part['in_stock'],
            ], $result['parts']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bus_no' => 'required|string|exists:buses,bus_no',
            'problem_issue' => 'required|string',
            'maintenance_type' => 'required|string|max:255',
            'maintenance_job' => 'nullable|string|max:255',
            'maintenance_jobs' => 'nullable|array|max:10',
            'maintenance_jobs.*' => 'string|max:255|distinct',
            'custom_maintenance_job' => 'nullable|string|max:255',
            'specializations' => 'nullable|array',
            'specializations.*' => 'string|max:255',
            'mechanic_ids' => 'nullable|array',
            'mechanic_ids.*' => 'integer|distinct|exists:mechanics,id',
            'assigned_mechanic' => 'nullable|string|max:255',
            'estimated_duration_value' => 'nullable|numeric|gt:0',
            'estimated_duration_unit' => 'nullable|in:Minutes,Hours,Days',
            'parts' => 'nullable|array',
            'parts.*.name' => 'nullable|string|max:255',
            'parts.*.quantity' => 'nullable|integer|min:1',
            'parts.*.unit' => 'nullable|string|max:50',
            'parts.*.inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'parts_issue_strategy' => 'nullable|in:complete_before_issuance,issue_available_only',
            'pms_schedule_id' => 'nullable|integer|exists:pms_schedules,id',
        ]);

        $selectedJobs = array_values(array_unique(array_filter(
            $validated['maintenance_jobs'] ?? [$validated['maintenance_job'] ?? null],
            fn ($job) => is_string($job) && trim($job) !== ''
        )));
        if (count($selectedJobs) > 1 && in_array('Other', $selectedJobs, true)) {
            throw ValidationException::withMessages([
                'maintenance_jobs' => 'Custom work must be selected on its own, not with preset jobs.',
            ]);
        }
        $validated['maintenance_job'] = $selectedJobs[0] ?? '';
        $jobSkills = collect($selectedJobs)->flatMap(
            fn ($job) => $this->requiredSpecializations($job, $validated['specializations'] ?? [])
        )->unique()->values()->all();
        if ($selectedJobs !== [] && $jobSkills === []) {
            throw ValidationException::withMessages([
                'maintenance_jobs' => 'Select valid Maintenance Jobs with defined specializations.',
            ]);
        }

        if (($validated['maintenance_job'] ?? null) === 'Other') {
            $customName = trim((string) ($validated['custom_maintenance_job'] ?? ''));
            if ($customName === '' || mb_strlen($customName) < 3) {
                throw ValidationException::withMessages([
                    'custom_maintenance_job' => 'Please describe the custom maintenance job.',
                ]);
            }
            $catalog = DB::table('mechanic_specialization_options')->pluck('name')
                ->map(fn ($name) => mb_strtolower(trim((string) $name)))->all();
            $skills = array_unique($validated['specializations'] ?? []);
            if ($skills === [] || collect($skills)->contains(
                fn ($skill) => ! in_array(mb_strtolower(trim($skill)), $catalog, true)
            )) {
                throw ValidationException::withMessages([
                    'specializations' => 'Select valid specializations from Mechanic Master List.',
                ]);
            }
        }

        // Keep the staff's proposed duration for Head review; do not overwrite it
        // with a median across unrelated repairs sharing a broad maintenance type.
        if (isset($validated['estimated_duration_value']) !== isset($validated['estimated_duration_unit'])) {
            throw ValidationException::withMessages([
                'estimated_duration_value' => 'Provide both the estimated duration and its unit.',
            ]);
        }

        if (JobOrder::query()->where('bus_no', $validated['bus_no'])->open()->exists()) {
            throw ValidationException::withMessages([
                'bus_no' => 'This bus already has an active Job Order.',
            ]);
        }

        $mechanicIds = $validated['mechanic_ids'] ?? [];
        if ($mechanicIds === [] && ! empty($validated['assigned_mechanic'])) {
            $legacyMechanic = Mechanic::query()->where('mechanic_name', $validated['assigned_mechanic'])->first();
            $mechanicIds = $legacyMechanic ? [$legacyMechanic->id] : [];
        }
        if ($mechanicIds === []) {
            throw ValidationException::withMessages([
                'mechanic_ids' => 'Please assign at least one mechanic before submitting the Job Order.',
            ]);
        }
        $mechanics = $this->workflow->validatedMechanics($mechanicIds);
        $requiredSkills = $jobSkills;
        if (($validated['maintenance_job'] ?? '') !== '' && $requiredSkills === []) {
            throw ValidationException::withMessages([
                'maintenance_job' => 'Select a supported Maintenance Job with defined specialization requirements.',
            ]);
        }
        if ($requiredSkills !== []) {
            foreach ($mechanics as $mechanic) {
                if (! collect($selectedJobs)->contains(fn ($job) => $this->matchesSpecialization(
                    $mechanic, $this->requiredSpecializations($job, $validated['specializations'] ?? [])
                ))) {
                    throw ValidationException::withMessages([
                        'mechanic_ids' => "Selected mechanic {$mechanic->mechanic_name} is not qualified for any selected maintenance job.",
                    ]);
                }
            }
            $uncoveredJobs = collect($selectedJobs)->reject(fn ($job) => $mechanics->contains(
                fn (Mechanic $mechanic) => $this->matchesSpecialization(
                    $mechanic, $this->requiredSpecializations($job, $validated['specializations'] ?? [])
                )
            ))->values();
            if ($uncoveredJobs->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'mechanic_ids' => 'Assign qualified mechanics for every selected maintenance job. Uncovered: '.$uncoveredJobs->implode(', '),
                ]);
            }
        }
        $pmsSchedule = null;

        if (! empty($validated['pms_schedule_id'])) {
            $pmsSchedule = PmsSchedule::findOrFail($validated['pms_schedule_id']);

            if ($validated['bus_no'] !== $pmsSchedule->bus_no) {
                return redirect()->back()->withInput()->with('error', 'The selected bus does not match the PMS schedule.');
            }

            if ($validated['maintenance_type'] !== 'PMS') {
                return redirect()->back()->withInput()->with('error', 'A PMS schedule can only create a PMS Job Order.');
            }

            $hasActivePmsJobOrder = JobOrder::query()
                ->where('pms_schedule_id', $pmsSchedule->id)
                ->open()
                ->exists();

            if ($hasActivePmsJobOrder) {
                return redirect()->route('PMS-Scheduling')->with('error', 'This PMS schedule already has an active Job Order.');
            }
        }

        // Never trust a client-provided Warehouse item name or UOM.
        // Historical/legacy callers without an item ID continue through the existing parser.
        $partInputs = $request->input('parts', []);
        foreach ($partInputs as $index => &$partInput) {
            if (! is_array($partInput) || empty($partInput['inventory_item_id'])) {
                continue;
            }
            $item = \App\Models\Warehouse\InventoryItem::find($partInput['inventory_item_id']);
            if (! $item || ! trim((string) ($item->unit ?: $item->unit_of_measurement))) {
                throw ValidationException::withMessages([
                    "parts.{$index}.inventory_item_id" => 'Select a Warehouse item with a configured unit.',
                ]);
            }
            $partInput['name'] = (string) ($item->parts_name ?: $item->item_name);
            $partInput['unit'] = (string) ($item->unit ?: $item->unit_of_measurement);
        }
        unset($partInput);
        $parts = $this->partParser->normalizePartsInput($partInputs);

        $partNeeded = count($parts) > 0
            ? $this->partParser->formatParts($parts)
            : null;

        $partStatus = $partNeeded ? 'Not Requested' : 'No Parts Required';

        $inventory = $this->workflow->inventoryAvailability($parts);
        $jobOrder = DB::transaction(function () use (
            $mechanics,
            $inventory,
            $partNeeded,
            $partStatus,
            $pmsSchedule,
            $validated,
            $selectedJobs
        ): JobOrder {
            $bus = Bus::query()
                ->where('bus_no', $validated['bus_no'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($bus->status !== 'Active') {
                throw ValidationException::withMessages([
                    'bus_no' => 'Only active buses without an open Job Order can be selected.',
                ]);
            }

            // An Active master status does not mean the vehicle has returned
            // from an already dispatched trip. Guard writes as well as the UI.
            if ($bus->hasOngoingDispatch()) {
                throw ValidationException::withMessages([
                    'bus_no' => 'This bus is currently dispatched. It cannot be assigned to a new Job Order until its trip is completed.',
                ]);
            }

            if (JobOrder::query()->where('bus_no', $bus->bus_no)->open()->exists()) {
                throw ValidationException::withMessages([
                    'bus_no' => 'This bus already has an active Job Order.',
                ]);
            }

            $createdJobOrder = JobOrder::create([
                'job_order_no' => $this->generateJobOrderNo(),
                'bus_no' => $validated['bus_no'],
                'pms_schedule_id' => $pmsSchedule?->id,
                'problem_issue' => $validated['problem_issue'],
                'work_to_perform' => in_array('Other', $selectedJobs, true)
                    ? trim($validated['custom_maintenance_job'])
                    : implode(', ', $selectedJobs),
                'maintenance_type' => $validated['maintenance_type'],
                'assigned_mechanic' => $mechanics->pluck('mechanic_name')->implode(', ') ?: null,
                'part_needed' => $partNeeded,
                'estimated_duration_value' => $validated['estimated_duration_value'] ?? null,
                'estimated_duration_unit' => $validated['estimated_duration_unit'] ?? null,
                'start_date' => null,
                'completion_date' => null,
                'status' => 'On Hold',
                'part_status' => $partStatus,
                'review_status' => 'For Review',
                'created_by' => auth()->id(),
                'parts_issue_strategy' => $validated['parts_issue_strategy'] ?? 'complete_before_issuance',
                'inventory_availability' => $inventory['status'],
                'inventory_snapshot' => $inventory,
            ]);

            // Claim stock atomically at submission; physical inventory is not deducted here.
            $reservation = app(\App\Services\Maintenance\PartsReservationService::class)->reserveForApproval($createdJobOrder);
            $createdJobOrder->update([
                'inventory_availability' => $reservation['status'],
                'inventory_snapshot' => array_merge($reservation, ['reservation_at_submission' => true]),
            ]);
            $this->workflow->syncMechanics($createdJobOrder, $mechanics, auth()->id());
            $this->workflow->audit($createdJobOrder, 'created', null, 'For Review', 'Submitted for Maintenance Head review.', [
                'maintenance_job' => $validated['maintenance_job'] ?? null,
                'maintenance_jobs' => $selectedJobs,
                'custom_maintenance_job' => $validated['custom_maintenance_job'] ?? null,
                'required_specializations' => $validated['specializations'] ?? [],
            ]);

            return $createdJobOrder;
        });

        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'created', $jobOrder->id, 'A job order was created.');

        $successMsg = 'Job order submitted for Maintenance Head review. It is view-only until reviewed.';

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'job_order' => $jobOrder,
            ]);
        }

        return redirect()->to(route('job-orders', [], false))->with(
            'success',
            $successMsg
        );
    }

    public function update(Request $request, JobOrder $jobOrder)
    {
        if ($jobOrder->review_status === 'Approved') {
            abort(423, 'Approved Job Orders are view-only. Use the dedicated completion workflow.');
        }

        if ($jobOrder->status === 'Completed') {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Completed job orders can only be viewed.'], 422);
            }
            return redirect()->back()->with('error', 'Completed job orders can only be viewed.');
        }

        if ($jobOrder->review_status === 'For Review') {
            abort(423, 'Job Orders pending Maintenance Head review are view-only.');
        }

        if ($jobOrder->review_status === 'Rejected') {
            abort(423, 'Rejected Job Orders are audit records and cannot be edited.');
        }

        if (in_array($jobOrder->part_status, [
            'Approved', 'For Purchase', 'Ordered', 'For Pick-up',
            'For Delivery', 'Delivered', 'Picked Up', 'Issued',
        ], true)) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'This Job Order can no longer be edited because its Purchase Request is already approved or being processed.'], 422);
            }
            return redirect()->back()->with('error', 'This Job Order can no longer be edited because its Purchase Request is already approved or being processed.');
        }

        $validated = $request->validate([
            'job_order_no' => 'required|string|max:255|unique:job_orders,job_order_no,' . $jobOrder->id,
            'bus_no' => [
                'required',
                'string',
                Rule::in([$jobOrder->bus_no]),
            ],
            'problem_issue' => 'required|string',
            'work_to_perform' => 'sometimes|required|string|max:2000',
            'maintenance_type' => 'required|string|max:255',
            'maintenance_job' => 'nullable|string|max:255',
            'maintenance_jobs' => 'nullable|array|max:10',
            'maintenance_jobs.*' => 'string|max:255|distinct',
            'custom_maintenance_job' => 'nullable|string|max:255',
            'specializations' => 'nullable|array',
            'specializations.*' => 'string|max:255',
            'mechanic_ids' => 'nullable|array',
            'mechanic_ids.*' => 'integer|distinct|exists:mechanics,id',
            'assigned_mechanic' => 'nullable|string|max:255',
            'estimated_duration_value' => 'sometimes|required|numeric|gt:0|required_with:estimated_duration_unit',
            'estimated_duration_unit' => 'sometimes|required|in:Minutes,Hours,Days|required_with:estimated_duration_value',
            'status' => 'nullable|string|in:On Hold,On Going',
            'parts' => 'nullable|array',
            'parts.*.name' => 'nullable|string|max:255',
            'parts.*.quantity' => 'nullable|integer|min:1',
            'parts.*.unit' => 'nullable|string|max:50',
            'parts.*.inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            'parts_issue_strategy' => 'nullable|in:complete_before_issuance,issue_available_only',
        ]);

        $selectedJobs = array_values(array_unique(array_filter(
            $validated['maintenance_jobs'] ?? [$validated['maintenance_job'] ?? null],
            fn ($job) => is_string($job) && trim($job) !== ''
        )));
        if (count($selectedJobs) > 1 && in_array('Other', $selectedJobs, true)) {
            throw ValidationException::withMessages([
                'maintenance_jobs' => 'Custom work must be selected on its own, not with preset jobs.',
            ]);
        }
        $jobSkills = collect($selectedJobs)->flatMap(
            fn ($job) => $this->requiredSpecializations($job, $validated['specializations'] ?? [])
        )->unique()->values()->all();
        if ($selectedJobs !== [] && $jobSkills === []) {
            throw ValidationException::withMessages([
                'maintenance_jobs' => 'Select valid Maintenance Jobs with defined specializations.',
            ]);
        }
        if (in_array('Other', $selectedJobs, true)) {
            $customName = trim((string) ($validated['custom_maintenance_job'] ?? ''));
            if (mb_strlen($customName) < 3) {
                throw ValidationException::withMessages([
                    'custom_maintenance_job' => 'Please describe the custom maintenance job.',
                ]);
            }
            $catalog = DB::table('mechanic_specialization_options')->pluck('name')
                ->map(fn ($name) => mb_strtolower(trim((string) $name)))->all();
            $skills = array_unique($validated['specializations'] ?? []);
            if ($skills === [] || collect($skills)->contains(
                fn ($skill) => ! in_array(mb_strtolower(trim($skill)), $catalog, true)
            )) {
                throw ValidationException::withMessages([
                    'specializations' => 'Select valid specializations from Mechanic Master List.',
                ]);
            }
        }

        $mechanicIds = $validated['mechanic_ids'] ?? [];
        if ($mechanicIds === [] && ! empty($validated['assigned_mechanic'])) {
            $legacyMechanic = Mechanic::query()->where('mechanic_name', $validated['assigned_mechanic'])->first();
            $mechanicIds = $legacyMechanic ? [$legacyMechanic->id] : [];
        }
        if ($mechanicIds === [] && $selectedJobs !== []) {
            throw ValidationException::withMessages([
                'mechanic_ids' => 'Please assign at least one mechanic before updating the Job Order.',
            ]);
        }
        $mechanics = $this->workflow->validatedMechanics($mechanicIds, $jobOrder);
        if ($selectedJobs !== []) {
            foreach ($mechanics as $mechanic) {
                if (! collect($selectedJobs)->contains(fn ($job) => $this->matchesSpecialization(
                    $mechanic,
                    $this->requiredSpecializations($job, $validated['specializations'] ?? [])
                ))) {
                    throw ValidationException::withMessages([
                        'mechanic_ids' => "Selected mechanic {$mechanic->mechanic_name} is not qualified for any selected maintenance job.",
                    ]);
                }
            }
            $uncoveredJobs = collect($selectedJobs)->reject(fn ($job) => $mechanics->contains(
                fn (Mechanic $mechanic) => $this->matchesSpecialization(
                    $mechanic,
                    $this->requiredSpecializations($job, $validated['specializations'] ?? [])
                )
            ))->values();
            if ($uncoveredJobs->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'mechanic_ids' => 'Assign qualified mechanics for every selected maintenance job. Uncovered: '.$uncoveredJobs->implode(', '),
                ]);
            }
        }
        // Resolve selected Warehouse items server-side, exactly as the Create JO form does.
        $partInputs = $request->input('parts', []);
        foreach ($partInputs as $index => &$partInput) {
            if (! is_array($partInput) || empty($partInput['inventory_item_id'])) {
                continue;
            }
            $item = \App\Models\Warehouse\InventoryItem::find($partInput['inventory_item_id']);
            if (! $item || ! trim((string) ($item->unit ?: $item->unit_of_measurement))) {
                throw ValidationException::withMessages([
                    "parts.{$index}.inventory_item_id" => 'Select a Warehouse item with a configured unit.',
                ]);
            }
            $partInput['name'] = (string) ($item->parts_name ?: $item->item_name);
            $partInput['unit'] = (string) ($item->unit ?: $item->unit_of_measurement);
        }
        unset($partInput);
        $parts = $this->partParser->normalizePartsInput($partInputs);

        $partNeeded = count($parts) > 0
            ? $this->partParser->formatParts($parts)
            : null;

        $partStatus = $jobOrder->part_status;

        if (! $partNeeded) {
            $partStatus = 'No Parts Required';
        } elseif (! $partStatus || in_array($partStatus, ['Unknown', 'No Parts Needed', 'No Parts Required'], true)) {
            $partStatus = 'Not Requested';
        }

        if ($jobOrder->part_status === 'Rejected' && $partNeeded) {
            $partStatus = 'Rejected';
        }

        $wasRevision = $jobOrder->review_status === 'Revision Required';
        $inventory = $this->workflow->inventoryAvailability($parts);
        $updates = [
            'job_order_no' => $validated['job_order_no'],
            'bus_no' => $validated['bus_no'],
            'problem_issue' => $validated['problem_issue'],
            'maintenance_type' => $validated['maintenance_type'],
            'assigned_mechanic' => $mechanics->pluck('mechanic_name')->implode(', ') ?: null,
            'part_needed' => $partNeeded,
            'part_status' => $partStatus,
            'parts_issue_strategy' => $validated['parts_issue_strategy'] ?? $jobOrder->parts_issue_strategy,
            'inventory_availability' => $inventory['status'],
            'inventory_snapshot' => $inventory,
        ];

        if ($selectedJobs !== []) {
            $updates['work_to_perform'] = in_array('Other', $selectedJobs, true)
                ? trim((string) $validated['custom_maintenance_job'])
                : implode(', ', $selectedJobs);
        }

        if ($wasRevision) {
            $updates['review_status'] = 'For Review';
            $updates['review_notes'] = null;
            $updates['revision_number'] = $jobOrder->revision_number + 1;
        } else {
            $partsReady = trim((string) $partNeeded) === '' || $partStatus === 'Issued';
            $updates['status'] = $mechanics->isNotEmpty() && $partsReady ? 'On Going' : 'On Hold';
            if ($mechanics->isNotEmpty() && $partsReady && ! $jobOrder->actual_started_at) {
                $updates['actual_started_at'] = now();
                $updates['start_date'] = now();
            }
        }

        if ($selectedJobs === [] && array_key_exists('work_to_perform', $validated)) {
            $updates['work_to_perform'] = $validated['work_to_perform'] !== null
                ? trim($validated['work_to_perform'])
                : null;
        }

        if (array_key_exists('estimated_duration_value', $validated)) {
            $updates['estimated_duration_value'] = $validated['estimated_duration_value'];
        }

        if (array_key_exists('estimated_duration_unit', $validated)) {
            $updates['estimated_duration_unit'] = $validated['estimated_duration_unit'];
        }

        DB::transaction(function () use ($jobOrder, $mechanics, $updates, $wasRevision, $selectedJobs, $validated): void {
            $locked = JobOrder::query()->lockForUpdate()->findOrFail($jobOrder->id);
            if ($locked->review_status === 'For Review' || $locked->review_status === 'Rejected' || $locked->status === 'Completed') {
                abort(423, 'This Job Order changed and is no longer editable.');
            }
            $mechanicIds = $mechanics->pluck('id')->all();
            if ($mechanicIds !== []) {
                Mechanic::query()->whereIn('id', $mechanicIds)->lockForUpdate()->get();
            }
            $lockedMechanics = $this->workflow->validatedMechanics($mechanicIds, $locked);
            $oldAssignments = $locked->mechanicAssignments()->with('mechanic')->get();
            if (! $wasRevision) {
                foreach ($oldAssignments as $assignment) {
                    MechanicAttendance::query()
                        ->where('mechanic_id', $assignment->mechanic?->mechanic_id)
                        ->whereDate('attendance_date', now(config('app.business_timezone', 'Asia/Manila'))->toDateString())
                        ->latest('id')->first()?->update(['status' => 'Present']);
                }
            }
            // Capture the previous allocation before updating the snapshot.
            // Revision resubmission overwrites inventory_snapshot in $updates.
            $hadSubmissionReservation = isset($locked->inventory_snapshot['reservation_at_submission'])
                || \App\Models\Maintenance\JobOrderPartReservation::query()
                    ->where('job_order_id', $locked->id)->exists();
            $locked->update($updates);
            if ($wasRevision && $hadSubmissionReservation) {
                // Reconcile changed part quantities while holding the JO transaction lock.
                $reservationService = app(\App\Services\Maintenance\PartsReservationService::class);
                $reservationService->release($locked);
                $locked->update(['inventory_snapshot' => null]);
                $newReservation = $reservationService->reserveForApproval($locked);
                $locked->update([
                    'inventory_availability' => $newReservation['status'],
                    'inventory_snapshot' => array_merge($newReservation, ['reservation_at_submission' => true]),
                ]);
            }
            $this->workflow->syncMechanics($locked, $lockedMechanics, auth()->id());
            if ($wasRevision) {
                $this->workflow->audit(
                    $locked,
                    'resubmitted',
                    'Revision Required',
                    'For Review',
                    'Revised Job Order resubmitted.',
                    [
                        'maintenance_jobs' => $selectedJobs,
                        'custom_maintenance_job' => $validated['custom_maintenance_job'] ?? null,
                        'required_specializations' => $validated['specializations'] ?? [],
                    ]
                );
            } else {
                if ($locked->actual_started_at) {
                    $this->workflow->setAttendanceStatus($locked->fresh('mechanicAssignments.mechanic'), 'On Duty');
                }
                $this->workflow->audit($locked, 'updated', 'Approved', 'Approved');
            }
        });

        $jobOrder->refresh();

        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'updated', $jobOrder->id, 'A job order was updated.');

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $wasRevision ? 'Revised Job Order resubmitted for review.' : 'Job order updated successfully.',
                'job_order' => $jobOrder,
            ]);
        }

        return redirect()->to(route('job-orders', [], false))->with('success', $wasRevision
            ? 'Revised Job Order resubmitted for Maintenance Head review.'
            : 'Job order updated successfully.');
    }

    public function createPurchaseRequest(Request $request, JobOrder $jobOrder)
    {
        // Legacy shortcut must not bypass the new Pending PR review workflow.
        // Derive the same JO-aware shortage, then require Maintenance to review
        // and submit the actual PR through the canonical store action.
        if ($jobOrder->review_status !== 'Approved' || $jobOrder->status === 'Completed') {
            abort(423, 'Only approved and open Job Orders can request purchasing.');
        }
        if (trim((string) $jobOrder->assigned_mechanic) === '' || trim((string) $jobOrder->part_needed) === '') {
            throw ValidationException::withMessages(['job_order_no' => 'The Job Order must have assigned mechanics and requested parts.']);
        }
        if ($this->maintenancePurchaseRequestForJobOrder($jobOrder->job_order_no)->exists()) {
            return redirect()->route('purchase-requests', ['record_view' => 'active', 'search' => $jobOrder->job_order_no])
                ->with('error', 'A Purchase Request already exists for this Job Order.');
        }
        $parts = $this->purchaseRequestParts->missingParts((string) $jobOrder->part_needed, $jobOrder);
        if ($parts === []) {
            throw ValidationException::withMessages(['job_order_no' => 'There is no outstanding shortage for this Job Order.']);
        }
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'submitted' => false,
                'job_order_no' => $jobOrder->job_order_no,
                'suggested_parts' => $parts,
                'message' => 'Review the suggested shortage before submitting a Purchase Request.',
            ]);
        }
        return redirect()->route('purchase-requests', ['record_view' => 'pending', 'search' => $jobOrder->job_order_no])
            ->with('success', 'Review the suggested shortage and submit the Purchase Request.');
    }

    public function approvalSignature(Request $request, JobOrder $jobOrder)
    {
        // The route requires Maintenance view permission. Never expose the
        // private storage path or allow an arbitrary requested file path.
        if ($jobOrder->review_status !== 'Approved') {
            abort(404);
        }
        $audit = $jobOrder->audits()
            ->where('action', 'approved')
            ->latest('id')
            ->first();
        $path = $audit?->metadata['handwritten_signature_path'] ?? null;
        $expectedPrefix = 'maintenance/job-order-signatures/'.$jobOrder->id.'/';
        if (! is_string($path) || ! str_starts_with($path, $expectedPrefix)
            || ! preg_match('/^[a-z0-9-]+\\.png$/i', substr($path, strlen($expectedPrefix)))) {
            abort(404);
        }
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if (! $disk->exists($path)) {
            abort(404);
        }
        return response($disk->get($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function approve(Request $request, JobOrder $jobOrder, \App\Services\Maintenance\PartsReservationService $reservations)
    {
        $this->assertMaintenanceHead();
        $validated = $request->validate([
            'electronic_signature' => 'required|string|max:255',
            'signature_image' => 'required|string|max:550000',
            'duration_decision' => 'required|in:accept,adjust',
            'parts_issue_strategy' => ['nullable', Rule::in(['complete_before_issuance', 'issue_available_only'])],
            'verified_duration_value' => 'required_if:duration_decision,adjust|nullable|numeric|gt:0',
            'verified_duration_unit' => 'required_if:duration_decision,adjust|nullable|in:Minutes,Hours,Days',
            'duration_adjustment_reason' => 'required_if:duration_decision,adjust|nullable|string|max:2000',
            'review_notes' => 'nullable|string|max:2000',
        ]);
        $user = $request->user();
        if (mb_strtolower(trim($validated['electronic_signature'])) !== mb_strtolower(trim((string) $user->name))) {
            throw ValidationException::withMessages([
                'electronic_signature' => 'Type your authenticated full name exactly to sign this approval.',
            ]);
        }

        // A drawn signature is required in addition to the authenticated typed name.
        // Keep the PNG in private storage; only its path and hash enter the audit trail.
        $signatureData = $validated['signature_image'];
        if (! str_starts_with($signatureData, 'data:image/png;base64,')) {
            throw ValidationException::withMessages(['signature_image' => 'Draw your signature in the signature box.']);
        }
        $signatureBytes = base64_decode(substr($signatureData, strlen('data:image/png;base64,')), true);
        if ($signatureBytes === false || strlen($signatureBytes) > 400000
            || strlen($signatureBytes) < 100
            || bin2hex(substr($signatureBytes, 0, 8)) !== '89504e470d0a1a0a'
            || ! @getimagesizefromstring($signatureBytes)) {
            throw ValidationException::withMessages(['signature_image' => 'The drawn signature is invalid. Clear it and sign again.']);
        }

        $signaturePath = null;
        try {
            $jobOrder = DB::transaction(function () use ($jobOrder, $user, $validated, $signatureBytes, $reservations, &$signaturePath): JobOrder {
                $locked = JobOrder::query()->with('mechanicAssignments')->lockForUpdate()->findOrFail($jobOrder->id);
            if ($locked->review_status !== 'For Review') {
                throw ValidationException::withMessages(['review_status' => 'Only Job Orders currently For Review can be approved.']);
            }

            $bus = Bus::query()->where('bus_no', $locked->bus_no)->lockForUpdate()->firstOrFail();
            if (! in_array($bus->status, ['Active', 'Under Maintenance'], true)) {
                throw ValidationException::withMessages(['bus_no' => 'The bus is no longer available for this Job Order.']);
            }

            $mechanicIds = $locked->mechanicAssignments->pluck('mechanic_id')->filter()->all();
            if ($mechanicIds !== []) {
                Mechanic::query()->whereIn('id', $mechanicIds)->lockForUpdate()->get();
            }
            $mechanics = $this->workflow->validatedMechanics($mechanicIds, $locked);
            if ($mechanics->count() !== $locked->mechanicAssignments->count()) {
                throw ValidationException::withMessages(['mechanic_ids' => 'A selected mechanic is no longer available. Return the Job Order for revision.']);
            }

            $originalDurationValue = $locked->estimated_duration_value;
            $originalDurationUnit = $locked->estimated_duration_unit;
            $adjustDuration = $validated['duration_decision'] === 'adjust';
            // With insufficient history, only the Head may establish a duration.
            if (! $originalDurationValue && ! $adjustDuration) {
                throw ValidationException::withMessages([
                    'duration_decision' => 'No historical median is available. Select Adjust and provide a verified duration and reason.',
                ]);
            }
            if (! $adjustDuration && (! $originalDurationValue || ! $originalDurationUnit)) {
                throw ValidationException::withMessages([
                    'duration_decision' => 'A proposed estimated duration is required before accepting it.',
                ]);
            }
            $finalDurationValue = $adjustDuration
                ? $validated['verified_duration_value'] : $originalDurationValue;
            $finalDurationUnit = $adjustDuration
                ? $validated['verified_duration_unit'] : $originalDurationUnit;

            $signaturePath = 'maintenance/job-order-signatures/'. $locked->id .'/'. \Illuminate\Support\Str::uuid() .'.png';
            if (! \Illuminate\Support\Facades\Storage::disk('local')->put($signaturePath, $signatureBytes)) {
                throw ValidationException::withMessages(['signature_image' => 'Unable to save the signed approval. Please retry.']);
            }
            $stockReservation = $reservations->reserveForApproval($locked);
            $stockStatus = $stockReservation['status'];
            // The UI may have hidden the choice using an earlier snapshot.
            // If stock becomes partial while the modal is open, default to the
            // safer hold policy rather than silently authorizing partial issue.
            $releasePolicy = $stockStatus === 'partial'
                ? ($validated['parts_issue_strategy'] ?? 'complete_before_issuance')
                : 'complete_before_issuance';
            $approvedAt = now();
            $signatureHash = hash_hmac('sha256', implode('|', [
                $locked->id, $locked->job_order_no, $user->id, $user->name, $approvedAt->toISOString(),
            ]), (string) config('app.key'));
            $hasMechanics = $mechanics->isNotEmpty();
            $canStart = $hasMechanics && (trim((string) $locked->part_needed) === '' || $locked->part_status === 'Issued');
            $locked->update([
                'review_status' => 'Approved',
                'estimated_duration_value' => $finalDurationValue,
                'estimated_duration_unit' => $finalDurationUnit,
                'review_notes' => $validated['review_notes'] ?? null,
                'approved_by' => $user->id,
                'approved_at' => $approvedAt,
                'approval_signature_hash' => $signatureHash,
                'parts_issue_strategy' => $releasePolicy,
                'inventory_availability' => $stockStatus,
                'inventory_snapshot' => $stockReservation,
                'status' => $canStart ? 'On Going' : 'On Hold',
                'start_date' => $canStart ? $approvedAt : null,
                'actual_started_at' => $canStart ? $approvedAt : null,
            ]);

            $bus->placeUnderMaintenance();
            if ($canStart) {
                $this->workflow->setAttendanceStatus($locked->fresh('mechanicAssignments.mechanic'), 'On Duty');
            }
            $this->workflow->audit($locked, 'approved', 'For Review', 'Approved', $validated['review_notes'] ?? null, [
                'approved_by' => $user->id,
                'approved_at' => $approvedAt->toISOString(),
                'signature_hash' => $signatureHash,
                'parts_issue_strategy' => $locked->parts_issue_strategy,
                'handwritten_signature_path' => $signaturePath,
                'handwritten_signature_sha256' => hash('sha256', $signatureBytes),
                'duration_verification' => [
                    'decision' => $validated['duration_decision'],
                    'staff_proposed_value' => $originalDurationValue,
                    'staff_proposed_unit' => $originalDurationUnit,
                    'approved_value' => $finalDurationValue,
                    'approved_unit' => $finalDurationUnit,
                    'adjustment_reason' => $adjustDuration ? trim($validated['duration_adjustment_reason']) : null,
                    'verified_by' => $user->id,
                    'verified_at' => $approvedAt->toISOString(),
                ],
            ]);

                return $locked->fresh();
            });
        } catch (\Throwable $exception) {
            // The filesystem is not transactional. Remove a signature written
            // before a later reservation/database failure rolls back approval.
            if (is_string($signaturePath) && $signaturePath !== '') {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($signaturePath);
            }

            throw $exception;
        }

        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'approved', $jobOrder->id, 'A Job Order was approved and electronically signed.');
        $bus = Bus::query()->where('bus_no', $jobOrder->bus_no)->first();
        if ($bus) {
            $this->broadcastSystemDataUpdated('Operation', 'Bus', 'updated', $bus->id, 'An approved Job Order placed the bus Under Maintenance.');
        }

        return redirect()->back()->with('success', 'Job Order approved. The authenticated electronic signature and approval time were recorded.');
    }

    public function returnForRevision(Request $request, JobOrder $jobOrder)
    {
        $this->assertMaintenanceHead();
        $validated = $request->validate(['review_notes' => 'required|string|max:2000']);
        DB::transaction(function () use ($jobOrder, $validated): void {
            $locked = JobOrder::query()->lockForUpdate()->findOrFail($jobOrder->id);
            if ($locked->review_status !== 'For Review') {
                throw ValidationException::withMessages(['review_status' => 'Only Job Orders currently For Review can be returned.']);
            }
            $locked->update(['review_status' => 'Revision Required', 'review_notes' => $validated['review_notes']]);
            $this->workflow->audit($locked, 'returned_for_revision', 'For Review', 'Revision Required', $validated['review_notes']);
        });

        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'revision_required', $jobOrder->id, 'A Job Order was returned for revision.');

        return redirect()->back()->with('success', 'Job Order returned for revision.');
    }

    public function reject(Request $request, JobOrder $jobOrder)
    {
        $this->assertMaintenanceHead();
        $validated = $request->validate(['review_notes' => 'required|string|max:2000']);
        DB::transaction(function () use ($jobOrder, $validated): void {
            $locked = JobOrder::query()->lockForUpdate()->findOrFail($jobOrder->id);
            if ($locked->review_status !== 'For Review') {
                throw ValidationException::withMessages(['review_status' => 'Only Job Orders currently For Review can be rejected.']);
            }
            // Rejected review records are terminal but are not genuine completed
            // repairs. Keeping the operational status terminal prevents them from
            // blocking bus scheduling; review_status distinguishes them in history.
            app(\App\Services\Maintenance\PartsReservationService::class)->release($locked);
            $locked->update([
                'status' => 'Completed',
                'review_status' => 'Rejected',
                'review_notes' => $validated['review_notes'],
            ]);
            $this->workflow->audit($locked, 'rejected', 'For Review', 'Rejected', $validated['review_notes']);
        });

        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'rejected', $jobOrder->id, 'A Job Order was rejected.');

        return redirect()->back()->with('success', 'Job Order rejected and retained in the audit history.');
    }

    public function finish(Request $request, JobOrder $jobOrder)
    {
        if ($jobOrder->review_status !== 'Approved') {
            abort(423, 'Completion is locked until the Job Order is approved.');
        }

        if ($jobOrder->status === 'Completed') {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Job order is already completed.'], 422);
            }
            return redirect()->back()->with('error', 'Job order is already completed.');
        }

        if ($jobOrder->status === 'On Hold') {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'This job order cannot be completed because it is currently on hold.'], 422);
            }
            return redirect()->back()->with('error', 'This job order cannot be completed because it is currently on hold.');
        }

        if ($jobOrder->status !== 'On Going') {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Only active job orders can be completed.'], 422);
            }
            return redirect()->back()->with('error', 'Only active job orders can be completed.');
        }

        if (! $this->canFinishWithPartStatus($jobOrder)) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'This job order cannot be completed yet. Required parts must be issued first.'], 422);
            }
            return redirect()->back()->with('error', 'This job order cannot be completed yet. Required parts must be issued first.');
        }

        $pmsSchedule = null;
        $bus = null;
        $pmsScheduleUpdate = null;
        $busUpdateData = null;

        if ($jobOrder->maintenance_type === 'PMS') {
            if (! $jobOrder->pms_schedule_id) {
                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'This PMS Job Order is not linked to a PMS schedule.'], 422);
                }
                return redirect()->back()->with('error', 'This PMS Job Order is not linked to a PMS schedule.');
            }

            $pmsSchedule = PmsSchedule::find($jobOrder->pms_schedule_id);

            if (! $pmsSchedule) {
                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'The linked PMS schedule was not found.'], 422);
                }
                return redirect()->back()->with('error', 'The linked PMS schedule was not found.');
            }

            $bus = Bus::whereRaw('UPPER(TRIM(bus_no)) = ?', [strtoupper(trim($jobOrder->bus_no))])->first();

            if (! $bus) {
                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'The matching bus was not found in Bus Master List.'], 422);
                }
                return redirect()->back()->with('error', 'The matching bus was not found in Bus Master List.');
            }

            $latestGps = GpsTripRecord::query()
                ->whereRaw('UPPER(TRIM(bus_no)) = ?', [strtoupper(trim($jobOrder->bus_no))])
                ->whereNotNull('mileage_km')
                ->whereHas('batchUpload', fn ($query) => $query->where('status', 'Processed'))
                ->orderByDesc('beginning_at')
                ->orderByDesc('id')
                ->first();

            $completedPmsKm = $latestGps
                ? (float) $latestGps->mileage_km
                : ($bus->latest_gps_km !== null
                    ? (float) $bus->latest_gps_km
                    : (float) $pmsSchedule->last_pms_km);

            $intervalKm = (float) $pmsSchedule->pms_interval_km;

            if ($intervalKm <= 0) {
                $intervalKm = 5000;
            }

            $nextPmsKm = $completedPmsKm + $intervalKm;

            $pmsScheduleUpdate = [
                'last_pms_km' => $completedPmsKm,
                'pms_interval_km' => $intervalKm,
                'next_pms_km' => $nextPmsKm,
            ];

            $busUpdateData = [
                'last_pms_km' => $completedPmsKm,
                'pms_interval_km' => $intervalKm,
                'next_pms_km' => $nextPmsKm,
            ];

            if ($latestGps) {
                $busUpdateData['latest_gps_km'] = $completedPmsKm;
                $busUpdateData['latest_gps_at'] = $latestGps->beginning_at ?? $latestGps->created_at;
            }

        }

        $busChanged = false;

        $jobOrder = DB::transaction(function () use (
            $bus,
            $busUpdateData,
            &$busChanged,
            $jobOrder,
            $pmsSchedule,
            $pmsScheduleUpdate
        ): JobOrder {
            $lockedJobOrder = JobOrder::query()->lockForUpdate()->findOrFail($jobOrder->id);

            if ($lockedJobOrder->review_status !== 'Approved' || $lockedJobOrder->status !== 'On Going' || ! $this->canFinishWithPartStatus($lockedJobOrder)) {
                throw ValidationException::withMessages([
                    'status' => 'The Job Order changed and can no longer be completed. Refresh and try again.',
                ]);
            }

            if ($pmsSchedule && $pmsScheduleUpdate && $bus && $busUpdateData) {
                PmsSchedule::query()->lockForUpdate()->findOrFail($pmsSchedule->id)->update($pmsScheduleUpdate);
                Bus::query()->lockForUpdate()->findOrFail($bus->id)->update($busUpdateData);
                $busChanged = true;
            }

            $lockedJobOrder->update([
                'completion_date' => now(),
                'actual_completed_at' => now(),
                'status' => 'Completed',
            ]);
            // Unused reservations must not hold stock after completion.
            \App\Models\Maintenance\JobOrderPartReservation::query()
                ->where('job_order_id', $lockedJobOrder->id)->delete();

            $this->workflow->setAttendanceStatus($lockedJobOrder->fresh('mechanicAssignments.mechanic'), 'Present');
            $this->workflow->audit($lockedJobOrder, 'completed', 'Approved', 'Completed');

            $lockedBus = Bus::query()
                ->whereRaw('UPPER(TRIM(bus_no)) = ?', [strtoupper(trim($lockedJobOrder->bus_no))])
                ->lockForUpdate()
                ->first();

            $hasAnotherActiveJobOrder = JobOrder::query()
                ->where('bus_no', $lockedJobOrder->bus_no)
                ->where('id', '!=', $lockedJobOrder->id)
                ->where('status', '!=', 'Completed')
                ->exists();

            if ($lockedBus && ! $hasAnotherActiveJobOrder) {
                $busChanged = $lockedBus->releaseFromMaintenance() || $busChanged;
            }

            return $lockedJobOrder->fresh();
        });

        if ($busChanged) {
            $updatedBus = Bus::query()->where('bus_no', $jobOrder->bus_no)->first();

            if ($updatedBus) {
                $this->broadcastSystemDataUpdated('Operation', 'Bus', 'updated', $updatedBus->id, 'A completed Job Order updated the bus availability or PMS mileage.');
            }
        }

        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'status_updated', $jobOrder->id, 'A job order was marked as completed.');

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Job order marked as completed.',
                'job_order' => $jobOrder,
            ]);
        }

        return redirect()->to(route('job-orders', [], false))->with('success', 'Job order marked as completed.');
    }

    public function destroy(Request $request, JobOrder $jobOrder)
    {
        if ($jobOrder->status === 'Completed') {
            $message = 'Completed Job Orders are history records and cannot be deleted.';

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', $message);
        }

        if (in_array($jobOrder->review_status, ['For Review', 'Rejected'], true)) {
            abort(423, 'Pending and rejected Job Orders are protected audit records and cannot be deleted.');
        }

        $linkedPurchaseRequests = $this
            ->maintenancePurchaseRequestForJobOrder($jobOrder->job_order_no)
            ->orderBy('id')
            ->get();

        if ($jobOrder->review_status === 'Approved' && $linkedPurchaseRequests->isEmpty()) {
            abort(423, 'Approved Job Orders cannot be deleted.');
        }

        if ($linkedPurchaseRequests->isNotEmpty()) {
            $allRejected = $linkedPurchaseRequests->every(
                fn (PurchaseRequest $purchaseRequest): bool => $purchaseRequest->status === 'Rejected'
            );

            if (! $allRejected) {
                $message = 'This Job Order cannot be deleted because it already has an active linked Purchase Request.';

                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $message], 422);
                }

                return redirect()->back()->with('error', $message);
            }

            $jobOrderId = $jobOrder->id;

            try {
                $cleanupResult = $this->linkedJobOrderResetService->deleteRejectedWorkflow(
                    $jobOrder->job_order_no
                );
            } catch (\RuntimeException $exception) {
                $message = 'This rejected Job Order could not be deleted safely: '
                    .$exception->getMessage();

                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $message], 422);
                }

                return redirect()->back()->with('error', $message);
            }

            if ($cleanupResult['bus_released_id'] ?? null) {
                $this->broadcastSystemDataUpdated(
                    'Operation',
                    'Bus',
                    'updated',
                    $cleanupResult['bus_released_id'],
                    'A bus was released by Maintenance after its Job Order was safely deleted.'
                );
            }

            $this->broadcastSystemDataUpdated(
                'Maintenance',
                'JobOrder',
                'deleted',
                $jobOrderId,
                'A rejected job order and its linked purchase-request data were deleted.'
            );

            $message = 'Rejected Job Order and its linked Purchase Request data were deleted successfully.';

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return redirect()->to(route('job-orders', [], false))->with('success', $message);
        }

        $jobOrderId = $jobOrder->id;
        $busNo = $jobOrder->bus_no;

        $releasedBus = DB::transaction(function () use ($busNo, $jobOrder): ?Bus {
            $locked = JobOrder::query()->with('mechanicAssignments.mechanic')->lockForUpdate()->findOrFail($jobOrder->id);
            $this->workflow->setAttendanceStatus($locked, 'Present');
            $locked->delete();

            return $this->releaseBusWithoutActiveJobOrders($busNo);
        });

        if ($releasedBus) {
            $this->broadcastSystemDataUpdated('Operation', 'Bus', 'updated', $releasedBus->id, 'A bus was released by Maintenance after its Job Order was deleted.');
        }

        $this->broadcastSystemDataUpdated('Maintenance', 'JobOrder', 'deleted', $jobOrderId, 'A job order was deleted.');

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Job order deleted successfully.',
            ]);
        }

        return redirect()->to(route('job-orders', [], false))->with('success', 'Job order deleted successfully.');
    }

    private function maintenancePurchaseRequestForJobOrder(string $jobOrderNo)
    {
        return PurchaseRequest::query()
            ->where('job_order_no', $jobOrderNo)
            ->where('pr_no', 'not like', '%-P')
            ->where(function ($query) {
                $query->whereNull('source_type')
                    ->orWhere('source_type', 'Maintenance Request');
            });
    }

    private function assertMaintenanceHead(): void
    {
        $user = auth()->user();
        $department = mb_strtolower(trim((string) $user?->department));
        $role = mb_strtolower(trim((string) $user?->role));
        $isHead = ($department === 'maintenance' && in_array($role, ['head', 'maintenance head', 'admin', 'maintenance admin'], true))
            || ($department === 'admin' && in_array($role, ['head', 'admin', 'system admin'], true));

        abort_unless($user && $isHead && $user->hasSystemPermission('maintenance', 'approve'), 403,
            'Only an authorized Maintenance Head can review Job Orders.');
    }

    private function canFinishWithPartStatus(JobOrder $jobOrder): bool
    {
        $hasNeededParts = trim((string) $jobOrder->part_needed) !== '';

        if (! $hasNeededParts) {
            return true;
        }

        return $jobOrder->part_status === 'Issued';
    }

    private function releaseBusWithoutActiveJobOrders(string $busNo): ?Bus
    {
        $bus = Bus::query()
            ->where('bus_no', $busNo)
            ->lockForUpdate()
            ->first();

        if (! $bus || $bus->status !== 'Under Maintenance') {
            return null;
        }

        $hasActiveJobOrder = JobOrder::query()
            ->where('bus_no', $busNo)
            ->open()
            ->exists();

        if ($hasActiveJobOrder) {
            return null;
        }

        $bus->releaseFromMaintenance();

        return $bus->fresh();
    }

    private function generateJobOrderNo(): string
    {
        $year = now()->format('Y');
        $lastJobOrder = JobOrder::where('job_order_no', 'like', "JO-{$year}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $lastJobOrder) {
            return "JO-{$year}-0001";
        }

        preg_match('/JO-' . $year . '-(\d+)/', $lastJobOrder->job_order_no, $matches);
        $nextNumber = (isset($matches[1]) ? (int) $matches[1] : 0) + 1;
        $newJobOrderNo = 'JO-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (JobOrder::where('job_order_no', $newJobOrderNo)->exists()) {
            $nextNumber++;
            $newJobOrderNo = 'JO-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newJobOrderNo;
    }

    private function generatePrNo(): string
    {
        $year = now()->format('Y');
        $lastPr = PurchaseRequest::where('pr_no', 'like', "PR-{$year}-%")
            ->where('pr_no', 'not like', '%-P')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $lastPr) {
            return "PR-{$year}-0001";
        }

        preg_match('/PR-' . $year . '-(\d+)/', $lastPr->pr_no, $matches);
        $nextNumber = isset($matches[1]) ? (int) $matches[1] + 1 : 1;
        $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (PurchaseRequest::where('pr_no', $newPrNo)->exists()) {
            $nextNumber++;
            $newPrNo = 'PR-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $newPrNo;
    }
    /**
     * Qualifying skills for the existing fixed Maintenance Job presets.
     * Custom jobs instead use validated names from the shared specialization catalog.
     */
    private function requiredSpecializations(string $job, array $customSkills = []): array
    {
        $jobs = [
            'Change Oil' => ['Change Oil', 'Lubrication', 'Preventive Maintenance'],
            'Oil Filter Replacement' => ['Oil Filter Replacement', 'Lubrication', 'Preventive Maintenance'],
            'Brake Inspection' => ['Brakes'],
            'Brake Pad Replacement' => ['Brakes'],
            'Air Filter Replacement' => ['Engine', 'Preventive Maintenance'],
            'Aircon Servicing' => ['Aircon'],
            'Battery Replacement' => ['Electrical'],
            'Tire Replacement' => ['Tires & Wheels'],
            'Engine Tune-Up' => ['Engine', 'Diagnostics'],
            'Full PMS' => ['Preventive Maintenance'],
            'Brake Adjustment' => ['Brakes'],
            'Brake Air System Inspection' => ['Brakes'],
            'Brake Lining Replacement' => ['Brakes'],
            'Engine Oil Leak Inspection' => ['Engine', 'Diagnostics'],
            'Fuel Filter Replacement' => ['Fuel System', 'Preventive Maintenance'],
            'Cooling System Inspection' => ['Engine', 'Diagnostics'],
            'Radiator and Coolant Servicing' => ['Engine', 'Preventive Maintenance'],
            'Transmission and Clutch Inspection' => ['Transmission'],
            'Suspension Inspection and Repair' => ['Suspension'],
            'Steering System Inspection' => ['Suspension'],
            'Wheel Bearing Inspection' => ['Tires & Wheels'],
            'Tire Pressure and Condition Check' => ['Tires & Wheels'],
            'Headlight and Signal Light Repair' => ['Electrical'],
            'Wiring and Electrical Troubleshooting' => ['Electrical', 'Diagnostics'],
            'Aircon Refrigerant Recharge' => ['Aircon'],
            'Body Panel and Bumper Repair' => ['Bodyworks'],
            'Door and Window Mechanism Repair' => ['Bodyworks'],
            'Chassis Bolt Retightening' => ['Preventive Maintenance'],
            'Chassis Greasing' => ['Lubrication', 'Preventive Maintenance'],
            'Exhaust System Inspection and Repair' => ['Engine'],
        ];

        return $job === 'Other'
            ? array_values(array_filter($customSkills, 'is_string'))
            : ($jobs[$job] ?? []);
    }

    private function matchesSpecialization(Mechanic $mechanic, array $requiredSkills): bool
    {
        $actual = collect(preg_split('/[,;]+/u', (string) $mechanic->specialization) ?: [])
            ->map(fn ($skill) => mb_strtolower(trim((string) $skill)))
            ->filter();

        return $actual->intersect(
            collect($requiredSkills)->map(fn ($skill) => mb_strtolower(trim((string) $skill)))
        )->isNotEmpty();
    }

}
