<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\DailyDriverReportTripEntry;
use App\Models\Operation\Driver;
use App\Models\Operation\TripSchedule;
use App\Services\Operation\DailyDriverReportScheduleMatchService;
use App\Services\Operation\OperationNumberSequenceService;
use App\Support\TableSort;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DailyDriverReportController extends Controller
{
    public function __construct(
        private readonly DailyDriverReportScheduleMatchService $scheduleMatchService,
        private readonly OperationNumberSequenceService $numberSequenceService
    ) {}

    public function index(Request $request): View
    {
        $query = DailyDriverReport::query()
            ->with(['driver', 'bus', 'additionalTrips'])
            ->orderByDesc('report_date')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('ddr_no', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhere('driver_id', 'like', "%{$search}%")
                    ->orWhere('trip_ticket', 'like', "%{$search}%")
                    ->orWhere('from_location', 'like', "%{$search}%")
                    ->orWhere('to_location', 'like', "%{$search}%")
                    ->orWhereHas('additionalTrips', function ($trips) use ($search) {
                        $trips->where('trip_ticket', 'like', "%{$search}%")
                            ->orWhere('from_location', 'like', "%{$search}%")
                            ->orWhere('to_location', 'like', "%{$search}%");
                    })
                    ->orWhereHas('bus', function ($busQuery) use ($search) {
                        $busQuery->where('bus_no', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('report_date')) {
            $query->whereDate(
                'report_date',
                $request->input('report_date')
            );
        }

        if (
            $request->filled('driver')
            && $request->input('driver') !== 'all'
        ) {
            $query->where('driver_id', $request->input('driver'));
        }

        if (
            $request->filled('bus')
            && $request->input('bus') !== 'all'
        ) {
            $query->where('bus_id', $request->integer('bus'));
        }

        TableSort::apply($query, $request, ['report_date' => 'report_date', 'ddr_no' => 'ddr_no', 'driver_name' => 'driver_name', 'passengers' => 'passengers'], 'report_date', 'desc');
        $reports = $query
            ->paginate(8)
            ->withQueryString();

        $totalReports = DailyDriverReport::query()->count();

        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();

        $reportsToday = DailyDriverReport::query()
            ->whereDate('report_date', $today)
            ->count();

        $passengersToday = DailyDriverReport::query()
            ->whereDate('report_date', $today)
            ->sum('passengers')
            + DailyDriverReportTripEntry::query()
                ->whereHas('report', fn ($q) => $q->whereDate('report_date', $today))
                ->sum('passengers');

        $tripsToday = DailyDriverReport::query()->whereDate('report_date', $today)->count()
            + DailyDriverReportTripEntry::query()
                ->whereHas('report', fn ($q) => $q->whereDate('report_date', $today))->count();

        $passengerCount = $tripsToday > 0
            ? $passengersToday / $tripsToday
            : 0;

        $drivers = Driver::query()
            ->orderBy('driver_name')
            ->get(['driver_id', 'driver_name', 'shift', 'employment_status']);

        $buses = Bus::query()
            ->orderBy('bus_no')
            ->get(['id', 'bus_no', 'plate_no', 'status']);

        [$activeBuses, $tripTicketSuggestions] = $this->encodeLookups();

        return view(
            'Operation.Daily_Driver_Reports.index',
            compact(
                'reports',
                'totalReports',
                'reportsToday',
                'passengersToday',
                'passengerCount',
                'drivers',
                'buses',
                'activeBuses',
                'tripTicketSuggestions'
            )
        );
    }

    public function create(): View
    {
        $drivers = Driver::query()
            ->orderBy('driver_name')
            ->get(['driver_id', 'driver_name', 'shift', 'employment_status']);

        [$activeBuses, $tripTicketSuggestions] = $this->encodeLookups();

        return view(
            'Operation.Daily_Driver_Reports.create',
            compact(
                'drivers',
                'activeBuses',
                'tripTicketSuggestions'
            )
        );
    }

    private function encodeLookups(): array
    {
        // DDR is a historical record. Do not hide buses that became inactive
        // or entered maintenance after the date printed on the DDR.
        $activeBuses = Bus::query()
            ->orderBy('bus_no')
            ->get(['id', 'bus_no', 'plate_no', 'capacity', 'status']);

        $recentTripTickets = DailyDriverReport::query()
            ->selectRaw('trip_ticket, MAX(report_date) as latest_date')
            ->groupBy('trip_ticket')
            ->orderByDesc('latest_date')
            ->limit(100)
            ->get()
            ->pluck('trip_ticket');

        $scheduledTickets = TripSchedule::query()
            ->where('status', '!=', 'Cancelled')
            ->orderByDesc('trip_date')
            ->limit(1000)
            ->get(['trip_code'])
            ->pluck('trip_code');

        $tripTicketSuggestions = $recentTripTickets
            ->merge($scheduledTickets)
            ->filter()
            ->unique()
            ->values();

        return [$activeBuses, $tripTicketSuggestions];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'report_date' => [
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value > now(config('app.business_timezone', 'Asia/Manila'))->toDateString()) {
                        $fail('The report date cannot be in the future.');
                    }
                },
            ],
            'driver_id' => [
                'required',
                'string',
                'max:255',
                Rule::exists('drivers', 'driver_id'),
            ],
            'bus_id' => [
                'required',
                'integer',
                Rule::exists('buses', 'id'),
            ],
            'trip_ticket' => [
                'required',
                'string',
                'max:50',
            ],
            'from_location' => [
                'required',
                'string',
                'max:150',
            ],
            'to_location' => [
                'required',
                'string',
                'max:150',
            ],
            'departure_time' => [
                'required',
                'date_format:H:i',
            ],
            'arrival_time' => [
                'required',
                'date_format:H:i',
            ],
            'passengers' => [
                'required',
                'integer',
                'min:0',
            ],
            'km' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'additional_trips' => ['sometimes', 'array', 'max:30'],
            'additional_trips.*.trip_ticket' => ['required', 'string', 'max:50'],
            'additional_trips.*.from_location' => ['required', 'string', 'max:150'],
            'additional_trips.*.to_location' => ['required', 'string', 'max:150'],
            'additional_trips.*.departure_time' => ['required', 'date_format:H:i'],
            'additional_trips.*.arrival_time' => ['required', 'date_format:H:i'],
            'additional_trips.*.passengers' => ['required', 'integer', 'min:0'],
            'additional_trips.*.km' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $allTickets = collect([$validated['trip_ticket']])
            ->merge(collect($validated['additional_trips'] ?? [])->pluck('trip_ticket'))
            ->map(fn ($ticket) => trim((string) $ticket));
        $normalizedTickets = $allTickets->map(fn ($ticket) => mb_strtolower($ticket));
        if ($normalizedTickets->duplicates()->isNotEmpty()) {
            return back()->withInput()->with('error', 'A trip ticket cannot be repeated within the same DDR.');
        }

        $existingTicket = DailyDriverReport::query()
            ->whereDate('report_date', $validated['report_date'])
            ->whereIn('trip_ticket', $allTickets)
            ->exists()
            || DailyDriverReportTripEntry::query()
                ->whereIn('trip_ticket', $allTickets)
                ->whereHas('report', fn ($query) => $query->whereDate('report_date', $validated['report_date']))
                ->exists();

        if ($existingTicket) {
            return back()->withInput()->with(
                'error',
                'A daily driver report for this trip ticket already exists on the selected date.'
            );
        }

        $driver = Driver::query()
            ->where('driver_id', $validated['driver_id'])
            ->first();

        $departure = Carbon::createFromFormat(
            'H:i',
            $validated['departure_time']
        );

        $arrival = Carbon::createFromFormat(
            'H:i',
            $validated['arrival_time']
        );

        $ddrNo = '';

        $year = substr($validated['report_date'], 0, 4);
        $latestNumber = DailyDriverReport::query()
            ->where('ddr_no', 'like', "DDR-{$year}-%")
            ->selectRaw('MAX(CAST(RIGHT(ddr_no, 4) AS UNSIGNED)) AS maximum')
            ->value('maximum');
        $nextNumber = $this->numberSequenceService->next(
            "ddr:{$year}",
            (int) $latestNumber
        );
        $ddrNo = 'DDR-'
            .$year
            .'-'
            .str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        try {
            DB::transaction(function () use (
                $validated,
                $driver,
                $departure,
                $arrival,
                $allTickets,
                $normalizedTickets,
                $ddrNo
            ): void {
                $report = DailyDriverReport::create([
                    'ddr_no' => $ddrNo,
                    'report_date' => $validated['report_date'],
                    'driver_id' => $validated['driver_id'],
                    'driver_name' => $driver?->driver_name
                        ?: $validated['driver_id'],
                    'bus_id' => $validated['bus_id'],
                    'trip_ticket' => $validated['trip_ticket'],
                    'from_location' => $validated['from_location'],
                    'to_location' => $validated['to_location'],
                    'departure_time' => $departure->format('H:i:s'),
                    'arrival_time' => $arrival->format('H:i:s'),
                    'passengers' => $validated['passengers'],
                    'km' => $validated['km'] ?? null,
                    'encoded_by' => auth()->id(),
                ]);

                foreach ($allTickets as $index => $ticket) {
                    $inserted = DB::table('daily_driver_report_ticket_claims')->insertOrIgnore([
                        'daily_driver_report_id' => $report->id,
                        'report_date' => $validated['report_date'],
                        'normalized_ticket' => $normalizedTickets->get($index),
                        'trip_ticket' => $ticket,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($inserted !== 1) {
                        throw ValidationException::withMessages([
                            'trip_ticket' => 'A daily driver report for this trip ticket already exists on the selected date.',
                        ]);
                    }
                }

                foreach (($validated['additional_trips'] ?? []) as $index => $trip) {
                    $report->additionalTrips()->create([
                        'sequence' => $index + 2,
                        'trip_ticket' => trim($trip['trip_ticket']),
                        'from_location' => $trip['from_location'],
                        'to_location' => $trip['to_location'],
                        'departure_time' => $trip['departure_time'].':00',
                        'arrival_time' => $trip['arrival_time'].':00',
                        'passengers' => $trip['passengers'],
                        'km' => $trip['km'] ?? null,
                    ]);
                }
            }, 5);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['trip_ticket'])) {
                return back()->withInput()->with(
                    'error',
                    $exception->errors()['trip_ticket'][0]
                );
            }

            throw $exception;
        }

        return redirect()
            ->route('daily-driver-reports')
            ->with(
                'success',
                "Daily driver report {$ddrNo} has been encoded successfully."
            );
    }

    private function assertDdrEditable(DailyDriverReport $report): void
    {
        if ($report->trip_schedule_id !== null || $report->trip_assignment_id !== null
            || $report->incidents()->exists()
            || $report->additionalTrips()->where(function ($query) {
                $query->whereNotNull('trip_schedule_id')->orWhereNotNull('trip_assignment_id');
            })->exists()) {
            throw ValidationException::withMessages([
                'report' => 'This DDR is linked to operational history and cannot be modified.',
            ]);
        }
    }

    public function edit(DailyDriverReport $dailyDriverReport): View
    {
        $this->assertDdrEditable($dailyDriverReport);

        return view('Operation.Daily_Driver_Reports.edit', [
            'report' => $dailyDriverReport->load('additionalTrips'),
        ]);
    }

    public function update(Request $request, DailyDriverReport $dailyDriverReport): RedirectResponse
    {
        $data = $request->validate([
            'from_location' => ['required', 'string', 'max:150'],
            'to_location' => ['required', 'string', 'max:150'],
            'departure_time' => ['required', 'date_format:H:i'],
            'arrival_time' => ['required', 'date_format:H:i'],
            'passengers' => ['required', 'integer', 'min:0'],
            'km' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'additional_trips' => ['sometimes', 'array'],
            'additional_trips.*.id' => ['required', 'integer'],
            'additional_trips.*.from_location' => ['required', 'string', 'max:150'],
            'additional_trips.*.to_location' => ['required', 'string', 'max:150'],
            'additional_trips.*.departure_time' => ['required', 'date_format:H:i'],
            'additional_trips.*.arrival_time' => ['required', 'date_format:H:i'],
            'additional_trips.*.passengers' => ['required', 'integer', 'min:0'],
            'additional_trips.*.km' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        DB::transaction(function () use ($dailyDriverReport, $data): void {
            $report = DailyDriverReport::query()->whereKey($dailyDriverReport->id)
                ->lockForUpdate()->firstOrFail();
            $this->assertDdrEditable($report);
            $existingEntries = $report->additionalTrips()->lockForUpdate()->get()->keyBy('id');
            $submittedEntries = collect($data['additional_trips'] ?? []);
            $submittedIds = $submittedEntries->pluck('id')->map(fn ($id) => (int) $id);
            if ($submittedIds->duplicates()->isNotEmpty()
                || $submittedIds->sort()->values()->all() !== $existingEntries->keys()->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'additional_trips' => 'The trip entries have changed. Reload the report before saving.',
                ]);
            }
            $report->update([
                'from_location' => $data['from_location'],
                'to_location' => $data['to_location'],
                'departure_time' => $data['departure_time'].':00',
                'arrival_time' => $data['arrival_time'].':00',
                'passengers' => $data['passengers'],
                'km' => $data['km'] ?? null,
            ]);
            foreach ($submittedEntries as $entryData) {
                $entry = $existingEntries->get((int) $entryData['id']);
                $entry->update([
                    'from_location' => $entryData['from_location'],
                    'to_location' => $entryData['to_location'],
                    'departure_time' => $entryData['departure_time'].':00',
                    'arrival_time' => $entryData['arrival_time'].':00',
                    'passengers' => $entryData['passengers'],
                    'km' => $entryData['km'] ?? null,
                ]);
            }
        });

        return redirect()->route('daily-driver-reports')
            ->with('success', 'DDR details updated.');
    }

    public function destroy(DailyDriverReport $dailyDriverReport): RedirectResponse
    {
        DB::transaction(function () use ($dailyDriverReport): void {
            $report = DailyDriverReport::query()
                ->whereKey($dailyDriverReport->id)->lockForUpdate()->firstOrFail();

            // Encoded records linked to operational trips are historical evidence.
            if ($report->trip_schedule_id !== null || $report->trip_assignment_id !== null
                || $report->incidents()->exists()
                || $report->additionalTrips()->whereNotNull('trip_schedule_id')->exists()) {
                throw ValidationException::withMessages([
                    'report' => 'This report is linked to operational history and cannot be deleted.',
                ]);
            }

            // Only erroneous standalone records may be removed.
            $report->additionalTrips()->delete();
            DB::table('daily_driver_report_ticket_claims')
                ->where('daily_driver_report_id', $report->id)->delete();
            $report->delete();
        });

        return redirect()->route('daily-driver-reports')
            ->with('success', 'The unlinked daily driver report was deleted.');
    }

    public function scheduleLookup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_date' => ['required', 'date'],
            'driver_id' => ['required', 'string'],
            'bus_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'schedules' => [],
                'message' => 'Schedule match unavailable.',
            ], 422);
        }

        $schedules = TripSchedule::query()
            ->with('shuttleRoute')
            ->whereDate('trip_date', $validator->validated()['report_date'])
            ->whereHas('assignments', function ($assignmentQuery) use ($validator) {
                $assignmentQuery
                    ->where('driver_id', $validator->validated()['driver_id'])
                    ->where('bus_id', $validator->validated()['bus_id']);
            })
            ->orderBy('departure_time')
            ->get([
                'id',
                'shuttle_route_id',
                'trip_code',
                'shift',
                'departure_time',
                'estimated_arrival_time',
                'status',
            ]);

        $rows = $schedules->map(fn (TripSchedule $schedule) => [
            'trip_code' => $schedule->trip_code,
            'route_label' => $schedule->shuttleRoute
                ? ($schedule->shuttleRoute->route_code
                    .' - '.$schedule->shuttleRoute->route_name)
                : null,
            'origin' => $schedule->shuttleRoute?->origin,
            'destination' => $schedule->shuttleRoute?->destination,
            'shift' => $schedule->shift,
            'departure_time' => substr($schedule->departure_time, 0, 5),
            'estimated_arrival_time' => substr($schedule->estimated_arrival_time, 0, 5),
            'status' => $schedule->status,
        ]);

        return response()->json([
            'schedules' => $rows,
            'message' => $rows->isEmpty()
                ? 'No scheduled trip matches the selected driver and bus on this date.'
                : ($rows->count() === 1
                    ? 'Scheduled trip found for this driver and bus.'
                    : count($rows).' scheduled trips found. Select an exact trip ticket so the report is not linked to the wrong trip.'),
        ]);
    }
}
