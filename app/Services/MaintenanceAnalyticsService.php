<?php

namespace App\Services;

use Carbon\CarbonImmutable as Date;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Read-only evidence for the Maintenance views of the existing Analytics routes. */
class MaintenanceAnalyticsService
{
    public function report(Request $request): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'bus' => ['nullable', 'string', 'max:60'],
            'issue' => ['nullable', 'in:all,repair,parts,repeat,workload,fuel'],
            'category' => ['nullable', 'in:all,repair,mechanic,parts,fuel'],
            'period' => ['nullable', 'in:this-month,last-30-days,last-3-months,this-year'],
        ]);
        $to = Date::parse($filters['to'] ?? now()->toDateString())->endOfDay();
        $from = isset($filters['from']) ? Date::parse($filters['from'])->startOfDay() : match ($filters['period'] ?? 'last-30-days') {
            'this-month' => $to->startOfMonth(), 'last-3-months' => $to->startOfMonth()->subMonths(2),
            'this-year' => $to->startOfYear(), default => $to->startOfDay()->subDays(29),
        };
        abort_if($from > $to || $from->diffInDays($to) > 366, 422, 'Choose a reporting period of at most one year.');
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $previousFrom = $from->subDays($days); $previousTo = $from->subMicrosecond();
        // Query Builder deliberately avoids JobOrder's retrieved notification hook.
        $jobs = app(DepartmentAnalyticsService::class)->maintenanceJobs()->where('job_orders.created_at', '<=', $to)->get();
        $fuel = DB::table('fuel_reports')->where('report_date', '<=', $to->toDateString())->where('fuel_liters', '>=', 0)->get();
        $busOptions = $jobs->pluck('bus_no')->merge($fuel->pluck('bus_no'))->filter()->unique()->sort()->values();
        $bus = $filters['bus'] ?? 'all';
        abort_if($bus !== 'all' && ! $busOptions->contains($bus), 422, 'Choose a bus from authorized Maintenance records.');
        if ($bus !== 'all') { $jobs = $jobs->where('bus_no', $bus)->values(); $fuel = $fuel->where('bus_no', $bus)->values(); }
        $within = fn ($date, $start, $end) => $date && Date::parse($date)->betweenIncluded($start, $end);
        $created = $jobs->filter(fn ($job) => $within($job->created_at, $from, $to));
        $completed = $jobs->filter(fn ($job) => $job->status === 'Completed' && $within($job->actual_completed_at, $from, $to));
        $periodFuel = $fuel->filter(fn ($row) => $within($row->report_date, $from, $to));
        $priorJobs = $jobs->filter(fn ($job) => $within($job->created_at, $previousFrom, $previousTo));
        $priorCompleted = $jobs->filter(fn ($job) => $job->status === 'Completed' && $within($job->actual_completed_at, $previousFrom, $previousTo));
        $priorFuel = $fuel->filter(fn ($row) => $within($row->report_date, $previousFrom, $previousTo));
        $hours = fn ($job) => $this->duration($job);
        $durations = $completed->map($hours)->filter(fn ($value) => $value !== null);
        $priorDurations = $priorCompleted->map($hours)->filter(fn ($value) => $value !== null);
        $kpis = [
            $this->kpi('Total Job Orders', $created->count(), $priorJobs->count(), 'fa-clipboard-list', 'blue'),
            $this->kpi('Completed Repairs', $completed->count(), $priorCompleted->count(), 'fa-circle-check', 'green'),
            $this->kpi('Average Repair Time', $durations->isEmpty() ? null : $durations->average(), $priorDurations->isEmpty() ? null : $priorDurations->average(), 'fa-clock', 'yellow', 'hrs'),
            $this->kpi('Fuel Consumed', $periodFuel->sum('fuel_liters'), $priorFuel->sum('fuel_liters'), 'fa-gas-pump', 'purple', 'L'),
        ];
        $monthly = [];
        for ($month = $from->startOfMonth(); $month <= $to; $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $monthly[] = ['label' => $month->format('M Y'),
                'created' => $created->filter(fn ($row) => str_starts_with($row->created_at, $key))->count(),
                'completed' => $completed->filter(fn ($row) => str_starts_with($row->actual_completed_at, $key))->count(),
                'fuel' => round($periodFuel->filter(fn ($row) => str_starts_with($row->report_date, $key))->sum('fuel_liters'), 2)];
        }
        $statusColors = [
            'Completed' => '#22c55e',
            'On Going' => '#3b82f6',
            'On Hold' => '#facc15',
            'Approved' => '#a78bfa',
            'For Review' => '#fb7185',
        ];
        $statuses = $created->groupBy('status')->map(fn ($rows, $label) => [
            'label' => $label ?: 'Unspecified',
            'count' => $rows->count(),
            'color' => $statusColors[$label] ?? '#94a3b8',
        ])->sortBy(fn ($item) => array_search($item['label'], array_keys($statusColors), true) === false
            ? count($statusColors) : array_search($item['label'], array_keys($statusColors), true))->values();
        $fuelByBus = $periodFuel->groupBy('bus_no')->map(fn ($rows, $number) => [
            'bus' => $number, 'liters' => round($rows->sum('fuel_liters'), 2),
            'share' => $periodFuel->sum('fuel_liters') > 0 ? round($rows->sum('fuel_liters') / $periodFuel->sum('fuel_liters') * 100, 1) : 0,
        ])->sortByDesc('liters')->take(10)->values();
        $active = $created->where('status', 'On Going');
        $partsHeld = $created->whereIn('status', ['On Going', 'On Hold'])->filter(fn ($job) => trim((string) $job->part_needed) !== '' && $job->part_status !== 'Issued');
        $overdue = $active->filter(fn ($job) => $this->due($job) !== null && $this->due($job) < min($to, Date::now()));
        $findings = collect(); $recommendations = collect();
        $add = function ($job, string $type, string $category, string $title, string $evidence, string $priority, string $action) use ($findings, $recommendations): void {
            $row = ['bus' => $job->bus_no, 'type' => $type, 'category' => $category, 'title' => $title, 'evidence' => $evidence,
                'priority' => $priority, 'action' => $action, 'reference' => $job->job_order_no, 'recordId' => $job->id];
            $findings->push($row); $recommendations->push($row);
        };
        foreach ($overdue as $job) {
            $over = round($this->due($job)->diffInMinutes(min($to, Date::now())) / 60, 1);
            $add($job, 'repair', 'repair', 'Repair Delay', 'Active repair exceeds its recorded duration by '.$over.' hours.', 'High', 'Review this Job Order and confirm repair progress, blockers, and a revised completion estimate.');
        }
        foreach ($partsHeld as $job) {
            $add($job, 'parts', 'parts', 'Parts-Related Delay', 'Required parts have not been fully issued. Recorded part status: '.($job->part_status ?: 'Unknown').'.', 'Medium', 'Review the existing parts request and Warehouse issuance status. Request purchasing review only after stock is verified.');
        }
        $recentHistory = $jobs->filter(fn ($job) => $within($job->created_at, $to->subDays(60)->startOfDay(), $to));
        $repeats = $recentHistory->filter(fn ($job) => trim((string) $job->problem_issue) !== '')
            ->groupBy(fn ($job) => $job->bus_no.'|'.mb_strtolower(trim($job->problem_issue)))
            ->filter(fn ($group) => $group->count() >= 3);
        foreach ($repeats as $group) {
            $job = $group->sortByDesc('created_at')->first();
            if (! $created->contains('id', $job->id)) continue;
            $add($job, 'repeat', 'repair', 'Recurring Bus Issue', 'The same recorded issue appears '.$group->count().' times for this bus in the last 60 days.', 'High', 'Review the linked repair history and investigate the recurring issue before approving follow-up work.');
        }
        $assignments = DB::table('job_order_mechanics')->whereIn('job_order_id', $active->pluck('id'))->get();
        foreach ($active as $job) {
            if ($assignments->contains('job_order_id', $job->id) || trim((string) $job->assigned_mechanic) !== '') continue;
            $add($job, 'workload', 'mechanic', 'Mechanic Assignment Review', 'No mechanic assignment is recorded for this active Job Order.', 'Medium', 'Review mechanic attendance, qualifications, and workload in the existing assignment workflow.');
        }
        $validFuel = $periodFuel->filter(fn ($row) => $row->distance_km > 0 && $row->fuel_liters > 0);
        $fuelComparison = [];
        foreach ($validFuel->groupBy('bus_no') as $number => $rows) {
            // Leave-one-bus-out baseline, normalized for distance; never claim route equivalence.
            $baseline = $validFuel->where('bus_no', '!=', $number);
            $expected = $baseline->count() >= 3 && $baseline->sum('distance_km') > 0
                ? $rows->sum('distance_km') * $baseline->sum('fuel_liters') / $baseline->sum('distance_km') : null;
            $actual = $rows->sum('fuel_liters');
            $fuelComparison[] = ['bus' => $number, 'actual' => round($actual, 2), 'expected' => $expected === null ? null : round($expected, 2)];
            if ($expected === null || $expected <= 0 || $actual < $expected * 1.2) continue;
            $difference = round(($actual / $expected - 1) * 100, 1);
            $row = ['bus'=>$number,'type'=>'fuel','category'=>'fuel','title'=>'Unusual Fuel Consumption',
                'evidence'=>$difference.'% above a distance-adjusted baseline from '.$baseline->count().' other-bus fuel reports. Routes and loads may differ.',
                'priority'=>'Medium','action'=>'Review fuel entries, distance accuracy, route/load differences, and inspection history. This signal is not proof of a mechanical fault or misuse.',
                'reference'=>'Fuel Report #'.$rows->first()->id,'recordId'=>$rows->first()->id];
            $findings->push($row); $recommendations->push($row);
        }
        $factors = [['label'=>'Awaiting Parts','count'=>$partsHeld->count()],['label'=>'Extended Repair','count'=>$overdue->count()],
            ['label'=>'Repeated Issue','count'=>$findings->where('type', 'repeat')->count()],['label'=>'Missing Assignment','count'=>$findings->where('type', 'workload')->count()]];
        $historicalDurations = $jobs->filter(fn ($job) => $job->status === 'Completed' && $within($job->actual_completed_at, $to->subDays(90), $to))->map($hours)->filter(fn ($value) => $value !== null);
        $repairEstimate = $historicalDurations->count() >= 3 ? round($historicalDurations->average(), 1) : null;
        $fuelMonthly = [];
        for ($offset = 3; $offset >= 1; $offset--) {
            $month = $to->startOfMonth()->subMonths($offset);
            $rows = $fuel->filter(fn ($row) => str_starts_with($row->report_date, $month->format('Y-m')));
            $fuelMonthly[] = ['label'=>$month->format('M Y'),'value'=>$rows->isEmpty() ? null : round($rows->sum('fuel_liters'), 2)];
        }
        $fuelEstimate = collect($fuelMonthly)->whereNotNull('value')->count() === 3 ? round(collect($fuelMonthly)->average('value'), 2) : null;
        $futureFuel = collect(range(1,3))->map(fn ($offset) => ['label'=>$to->startOfMonth()->addMonths($offset)->format('M Y'),'value'=>$fuelEstimate]);
        $futureRepair = collect(range(1,4))->map(fn ($offset) => ['label'=>$to->startOfWeek()->addWeeks($offset)->format('M d'), 'value'=>$repairEstimate]);
        $riskBuses = $recentHistory->groupBy('bus_no')->filter(fn ($rows) => $rows->count() >= 3)->keys();
        $categoryCounts = collect(['repair','mechanic','parts','fuel'])->mapWithKeys(fn ($key) => [$key=>$recommendations->where('category',$key)->count()]);
        $findings = $findings->when(($filters['issue'] ?? 'all') !== 'all', fn ($rows) => $rows->where('type',$filters['issue']))->sortBy(fn ($row) => $row['priority'] === 'High' ? 0 : 1)->values();
        $recommendations = $recommendations->when(($filters['category'] ?? 'all') !== 'all', fn ($rows) => $rows->where('category',$filters['category']))->sortBy(fn ($row) => $row['priority'] === 'High' ? 0 : 1)->values();
        return compact('kpis','monthly','statuses','fuelByBus','findings','recommendations','factors','fuelComparison','repairEstimate','fuelEstimate','historicalDurations','fuelMonthly','futureFuel','futureRepair','riskBuses','categoryCounts','busOptions','bus') + [
            'from'=>$from->toDateString(),'to'=>$to->toDateString(),'issue'=>$filters['issue'] ?? 'all','category'=>$filters['category'] ?? 'all',
            'createdCount'=>$created->count(),'durationSampleCount'=>$durations->count(),'fuelRecordCount'=>$periodFuel->count(),
            'riskSampleCount'=>$recentHistory->count(), 'riskReady'=>$recentHistory->count() >= 3,
        ];
    }

    private function duration(object $job): ?float
    {
        if (! $job->actual_started_at || ! $job->actual_completed_at) return null;
        $minutes = Date::parse($job->actual_started_at)->diffInMinutes(Date::parse($job->actual_completed_at), false);
        return $minutes > 0 ? $minutes / 60 : null;
    }

    private function due(object $job): ?Date
    {
        if (! $job->actual_started_at || $job->estimated_duration_value <= 0 || $job->actual_completed_at || $job->completion_date
            || (trim((string) $job->part_needed) !== '' && $job->part_status !== 'Issued')) return null;
        $multiplier = match ($job->estimated_duration_unit) { 'Minutes'=>1, 'Hours'=>60, 'Days'=>1440, default=>null };
        return $multiplier ? Date::parse($job->actual_started_at)->addMinutes($job->estimated_duration_value * $multiplier) : null;
    }

    private function kpi(string $label, ?float $value, ?float $previous, string $icon, string $color, string $unit = ''): array
    {
        return ['label'=>$label,'value'=>$value === null ? 'Unavailable' : number_format($value, $unit === '' ? 0 : 1).($unit ? ' '.$unit : ''),
            'delta'=>$value !== null && $previous !== null && $previous > 0 ? round(($value / $previous - 1) * 100, 1) : null,
            'icon'=>$icon,'color'=>$color,'previous'=>$previous];
    }
}
