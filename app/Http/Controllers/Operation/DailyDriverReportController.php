<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\Driver;
use App\Models\Operation\TripSchedule;
use App\Services\Operation\DailyDriverReportScheduleMatchService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DailyDriverReportController extends Controller
{
    public function __construct(
        private readonly DailyDriverReportScheduleMatchService $scheduleMatchService
    ) {
    }

    public function index(Request $request): View
    {
        $query = DailyDriverReport::query()
            ->with(['driver', 'bus'])
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

        $reports = $query
            ->paginate(8)
            ->withQueryString();

        $totalReports = DailyDriverReport::query()->count();

        $today = now()->toDateString();

        $reportsToday = DailyDriverReport::query()
            ->whereDate('report_date', $today)
            ->count();

        $passengersToday = DailyDriverReport::query()
            ->whereDate('report_date', $today)
            ->sum('passengers');

        $passengerCount = $reportsToday > 0
            ? $passengersToday / $reportsToday
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
        $activeBuses = Bus::query()
            ->where('status', 'Active')
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
                'date',
                'before_or_equal:today',
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
        ]);

        $duplicateExists = DailyDriverReport::query()
            ->where('report_date', $validated['report_date'])
            ->where('trip_ticket', $validated['trip_ticket'])
            ->exists();

        if ($duplicateExists) {
            return redirect()
                ->back()
                ->withInput()
                ->with(
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

        DB::transaction(function () use ($validated, $driver, $departure, $arrival, &$ddrNo) {
            $year = substr($validated['report_date'], 0, 4);

            $latest = DailyDriverReport::query()
                ->lockForUpdate()
                ->where('ddr_no', 'like', "DDR-{$year}-%")
                ->orderByDesc('id')
                ->first();

            $lastNumber = $latest && $latest->ddr_no
                ? (int) substr($latest->ddr_no, -4)
                : 0;

            $ddrNo = 'DDR-'
                . $year
                . '-'
                . str_pad(
                    (string) ($lastNumber + 1),
                    4,
                    '0',
                    STR_PAD_LEFT
                );

            DailyDriverReport::create([
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
                'encoded_by' => auth()->id(),
            ]);
        });

        return redirect()
            ->route('daily-driver-reports')
            ->with(
                'success',
                "Daily driver report {$ddrNo} has been encoded successfully."
            );
    }

    public function show(
        Request $request,
        DailyDriverReport $dailyDriverReport
    ): View {
        $report = $dailyDriverReport->load(['driver', 'bus', 'encoder']);

        $schedule = $this->scheduleMatchService->match($report);

        $comparison = $this->scheduleMatchService->comparison(
            $schedule,
            $report->departure_time,
            $report->arrival_time
        );

        return view(
            'Operation.Daily_Driver_Reports.show',
            compact('report', 'comparison')
        );
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
            ->whereHas('assignment', function ($assignmentQuery) use ($validator) {
                $assignmentQuery
                    ->where('driver_id', $validator->validated()['driver_id'])
                    ->where('bus_id', $validator->validated()['bus_id']);
            })
            ->orderBy('departure_time')
            ->get([
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
                    . ' - ' . $schedule->shuttleRoute->route_name)
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
                    : count($rows) . ' scheduled trips found for this driver and bus. The closest departure will be used for the comparison.'),
        ]);
    }
}