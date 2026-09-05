<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance\Bus;
use App\Models\Warehouse\InventoryItem;
use App\Services\FleetTripPredictionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AnalyticsStageController extends Controller
{
    private const STAGES = [
        'descriptive' => 'Descriptive',
        'diagnostic' => 'Diagnostic',
        'predictive' => 'Predictive',
        'prescriptive' => 'Prescriptive',
    ];

    private const DOMAINS = [
        'all',
        'fleet-trip',
        'fuel',
        'bus-health',
        'inventory',
    ];

    public function show(
        Request $request,
        string $stage,
        FleetTripPredictionService $predictionService
    ): View {
        abort_unless(array_key_exists($stage, self::STAGES), 404);

        $domain = strtolower(trim((string) $request->input('domain', 'all')));
        if (! in_array($domain, self::DOMAINS, true)) {
            $domain = 'all';
        }

        $fleet = app(FleetTripAnalyticsController::class)->data($request, $predictionService);
        $fuel = app(FuelAnalyticsController::class)->data($request);

        $inventoryItems = InventoryItem::query()
            ->orderBy('category')
            ->orderBy('parts_name')
            ->get();
        $inventoryTotal = $inventoryItems->count();
        $inventoryLow = $inventoryItems
            ->filter(fn (InventoryItem $item) => $item->on_hand > 0 && $item->on_hand <= (int) $item->reorder_level)
            ->count();
        $inventoryCritical = $inventoryItems->where('on_hand', '<=', 0)->count();

        $inventory = (object) [
            'total' => $inventoryTotal,
            'healthy' => max(0, $inventoryTotal - $inventoryLow - $inventoryCritical),
            'low' => $inventoryLow,
            'critical' => $inventoryCritical,
        ];

        $busOptions = collect($fleet['busOptions'] ?? [])
            ->pluck('bus_no')
            ->merge(collect($fuel['buses'] ?? [])->pluck('bus_no'))
            ->filter()
            ->map(fn ($busNo) => strtoupper(trim((string) $busNo)))
            ->unique()
            ->sort()
            ->values();

        $diagnostic = in_array($stage, ['diagnostic', 'predictive'], true)
            ? $this->buildDiagnosticData($request, $fleet, $fuel, $inventoryItems, $inventory)
            : null;

        $predictive = $stage === 'predictive'
            ? $this->buildPredictiveData($request, $fleet, $fuel, $diagnostic)
            : null;

        $viewName = match ($stage) {
            'diagnostic' => 'Admin.Analytics.diagnostic.layout',
            'predictive' => 'Admin.Analytics.predictive.layout',
            default => 'Admin.Analytics.stage',
        };

        return view($viewName, [
            'stage' => $stage,
            'stageLabel' => self::STAGES[$stage],
            'domain' => $domain,
            'fleet' => $fleet,
            'fuel' => $fuel,
            'inventory' => $inventory,
            'diagnostic' => $diagnostic,
            'predictive' => $predictive,
            'stats' => $predictive?->fleet->stats ?? null,
            'issues' => $predictive?->fleet->issues ?? null,
            'predictions' => $predictive?->fleet->predictions ?? null,
            'routes' => $predictive?->fleet->routes ?? null,
            'busOptions' => $busOptions,
            'period' => $fuel['period'] ?? ($fleet['period'] ?? 'this-month'),
            'selectedBus' => strtolower((string) ($fuel['selectedBus'] ?? 'all')),
        ]);
    }

    private function buildDiagnosticData(
        Request $request,
        array $fleet,
        array $fuel,
        Collection $inventoryItems,
        object $inventory
    ): object {
        $selectedBus = strtoupper(trim((string) $request->input('bus', 'all')));
        $fleetDiagnostics = $fleet['diagnostics'] ?? (object) [];
        $fuelReviewUnits = collect($fuel['reviewUnits'] ?? []);
        $highIdlingUnits = collect($fuel['highIdlingUnits'] ?? []);

        $buses = Bus::query()->orderBy('bus_no')->get();
        if ($selectedBus !== '' && $selectedBus !== 'ALL') {
            $buses = $buses->filter(fn (Bus $bus) => strtoupper(trim((string) $bus->bus_no)) === $selectedBus)->values();
        }

        $jobOrders = collect();
        if (Schema::hasTable('job_orders')) {
            $jobOrders = DB::table('job_orders')
                ->when($selectedBus !== '' && $selectedBus !== 'ALL', fn ($query) => $query->whereRaw('UPPER(TRIM(bus_no)) = ?', [$selectedBus]))
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->get();
        }

        $openJobOrders = $jobOrders->filter(function ($row): bool {
            $status = strtolower(trim((string) ($row->status ?? '')));
            return ! in_array($status, ['completed', 'complete', 'closed', 'done'], true);
        })->values();

        $overdueJobOrders = $openJobOrders->filter(function ($row): bool {
            if (empty($row->start_date) || empty($row->estimated_duration_value) || empty($row->estimated_duration_unit)) {
                return false;
            }

            $due = Carbon::parse($row->start_date);
            $value = (float) $row->estimated_duration_value;
            $due = match ((string) $row->estimated_duration_unit) {
                'Minutes' => $due->addMinutes($value),
                'Hours' => $due->addMinutes($value * 60),
                'Days' => $due->addMinutes($value * 1440),
                default => null,
            };

            return $due !== null && now()->greaterThan($due);
        })->values();

        $maintenanceTypes = $openJobOrders
            ->groupBy(fn ($row) => trim((string) ($row->maintenance_type ?? '')) ?: 'Unspecified')
            ->map(fn (Collection $rows, string $label) => (object) [
                'label' => $label,
                'count' => $rows->count(),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values();

        $busAttention = $buses
            ->map(function (Bus $bus) use ($openJobOrders, $overdueJobOrders): object {
                $busNo = strtoupper(trim((string) $bus->bus_no));
                $orders = $openJobOrders->filter(fn ($row) => strtoupper(trim((string) ($row->bus_no ?? ''))) === $busNo);
                $overdue = $overdueJobOrders->filter(fn ($row) => strtoupper(trim((string) ($row->bus_no ?? ''))) === $busNo);
                $statusAttention = $bus->status !== 'Active';

                return (object) [
                    'bus_no' => $bus->bus_no,
                    'plate_no' => $bus->plate_no,
                    'bus_model' => $bus->bus_model,
                    'year_model' => $bus->year_model,
                    'status' => $bus->status,
                    'open_orders' => $orders->count(),
                    'overdue_orders' => $overdue->count(),
                    'needs_attention' => $statusAttention || $orders->isNotEmpty(),
                    'attention_score' => ($statusAttention ? 2 : 0) + $orders->count() + ($overdue->count() * 2),
                ];
            })
            ->filter(fn ($row) => $row->needs_attention)
            ->sortByDesc('attention_score')
            ->values();

        $inventoryRows = $inventoryItems->map(function (InventoryItem $item): object {
            $onHand = (int) $item->on_hand;
            $reorder = max(0, (int) $item->reorder_level);
            $state = $onHand <= 0 ? 'Out of Stock' : (($reorder > 0 && $onHand <= $reorder) ? 'Low Stock' : 'Well Stocked');
            $severity = $state === 'Out of Stock' ? 3 : ($state === 'Low Stock' ? 2 : 0);

            return (object) [
                'item_code' => $item->item_code,
                'name' => $item->parts_name ?? $item->item_name ?? $item->item_code ?? 'Inventory Item',
                'category' => trim((string) $item->category) ?: 'Uncategorized',
                'on_hand' => $onHand,
                'reorder_level' => $reorder,
                'state' => $state,
                'severity' => $severity,
                'gap' => max(0, $reorder - $onHand),
            ];
        });

        $inventoryCategories = $inventoryRows
            ->groupBy('category')
            ->map(function (Collection $rows, string $category): object {
                $attention = $rows->whereIn('state', ['Low Stock', 'Out of Stock']);
                return (object) [
                    'category' => $category,
                    'total' => $rows->count(),
                    'attention' => $attention->count(),
                    'critical' => $rows->where('state', 'Out of Stock')->count(),
                    'low' => $rows->where('state', 'Low Stock')->count(),
                ];
            })
            ->sortByDesc('attention')
            ->values();

        $inventoryAttentionRows = $inventoryRows
            ->filter(fn ($row) => $row->severity > 0)
            ->sortByDesc(fn ($row) => ($row->severity * 100000) + $row->gap)
            ->values();

        $fleetSignals = (int) ($fleetDiagnostics->review_count ?? 0);
        $fuelSignals = $fuelReviewUnits->count();
        $busSignals = $busAttention->count();
        $inventorySignals = $inventoryAttentionRows->count();

        $areasWithIssues = collect([$fleetSignals, $fuelSignals, $busSignals, $inventorySignals])
            ->filter(fn ($value) => $value > 0)
            ->count();

        $topFactors = collect([
            (object) ['domain' => 'Fleet & Trip', 'title' => 'Trip records requiring review', 'value' => $fleetSignals, 'tone' => 'blue'],
            (object) ['domain' => 'Fuel', 'title' => 'Buses with fuel review signals', 'value' => $fuelSignals, 'tone' => 'green'],
            (object) ['domain' => 'Bus Health', 'title' => 'Buses with status or maintenance attention', 'value' => $busSignals, 'tone' => 'purple'],
            (object) ['domain' => 'Inventory', 'title' => 'Items at or below reorder attention', 'value' => $inventorySignals, 'tone' => 'orange'],
        ])->sortByDesc('value')->values();

        return (object) [
            'all' => (object) [
                'signals' => $fleetSignals + $fuelSignals + $busSignals + $inventorySignals,
                'high_impact' => (int) ($fleetDiagnostics->delay_count ?? 0)
                    + $highIdlingUnits->count()
                    + $overdueJobOrders->count()
                    + $inventory->critical,
                'contributing_factors' => collect([
                    (int) ($fleetDiagnostics->high_idle_count ?? 0),
                    (int) ($fleetDiagnostics->slow_movement_count ?? 0),
                    $fuelReviewUnits->count(),
                    $maintenanceTypes->count(),
                    $inventoryCategories->where('attention', '>', 0)->count(),
                ])->sum(),
                'areas_with_issues' => $areasWithIssues,
                'top_factors' => $topFactors,
            ],
            'fleet' => (object) [
                'diagnostics' => $fleetDiagnostics,
                'routes' => collect($fleet['routes'] ?? []),
                'bus_activity' => collect($fleet['busActivity'] ?? []),
                'trip_count' => (int) ($fleet['tripCount'] ?? 0),
                'total_idle_minutes' => (float) ($fleet['totalIdleMinutes'] ?? 0),
                'average_trip_duration' => (float) ($fleet['averageTripDuration'] ?? 0),
                'average_speed' => (float) ($fleet['averageSpeed'] ?? 0),
            ],
            'fuel' => (object) [
                'total_fuel' => (float) ($fuel['totalFuel'] ?? 0),
                'total_distance' => (float) ($fuel['totalDistance'] ?? 0),
                'fleet_average' => (float) ($fuel['fleetAverage'] ?? 0),
                'bus_summaries' => collect($fuel['busSummaries'] ?? []),
                'review_units' => $fuelReviewUnits,
                'high_idling_units' => $highIdlingUnits,
                'trend' => collect($fuel['trend'] ?? []),
                'idling_median' => (float) ($fuel['idlingMedian'] ?? 0),
            ],
            'bus_health' => (object) [
                'buses' => $buses,
                'total' => $buses->count(),
                'active' => $buses->where('status', 'Active')->count(),
                'maintenance' => $buses->where('status', 'Under Maintenance')->count(),
                'inactive' => $buses->where('status', 'Inactive')->count(),
                'open_orders' => $openJobOrders,
                'overdue_orders' => $overdueJobOrders,
                'maintenance_types' => $maintenanceTypes,
                'attention_buses' => $busAttention,
            ],
            'inventory' => (object) [
                'rows' => $inventoryRows,
                'categories' => $inventoryCategories,
                'attention_rows' => $inventoryAttentionRows,
                'total' => $inventory->total,
                'healthy' => $inventory->healthy,
                'low' => $inventory->low,
                'critical' => $inventory->critical,
            ],
        ];
    }

    private function buildPredictiveData(
        Request $request,
        array $fleet,
        array $fuel,
        object $diagnostic
    ): object {
        $fleetDiag = $fleet['diagnostics'] ?? (object) [];
        $fleetPred = $fleet['prediction'] ?? (object) ['available' => false, 'predictions' => collect()];

        $tripCount = (int) ($fleet['tripCount'] ?? 0);
        $fleetAvailability = (float) ($fleet['fleetAvailability'] ?? 0);
        $fleetRoutes = collect($fleet['routes'] ?? [])->values();

        $fuelDiag = $diagnostic->fuel;
        $healthDiag = $diagnostic->bus_health;
        $inventoryDiag = $diagnostic->inventory;

        $delayRisk = (int) ($fleetDiag->delay_count ?? 0) + (int) ($fleetDiag->slow_movement_count ?? 0);
        $highIdleRisk = (int) ($fleetDiag->high_idle_count ?? 0) + $fuelDiag->high_idling_units->count();
        $tripsAtRisk = (int) ($fleetDiag->review_count ?? 0);

        $predictedRecords = collect($fleetPred->predictions ?? []);
        $averageDelayRiskPct = $predictedRecords->isNotEmpty()
            ? (float) $predictedRecords->avg('delay_risk_percent')
            : 0.0;
        $completionForecast = $tripCount > 0
            ? max(0.0, min(100.0, 100.0 - (($tripsAtRisk / $tripCount) * 100)))
            : ($tripsAtRisk === 0 ? 100.0 : 50.0);

        $fuelSummaries = $fuelDiag->bus_summaries;
        $fuelReviewUnits = $fuelDiag->review_units;
        $fuelHighIdlingUnits = $fuelDiag->high_idling_units;
        $fuelAverage = (float) ($fuelDiag->fleet_average ?? 0);
        $belowFleetAverage = $fuelSummaries
            ->filter(fn ($row) => (float) ($row->km_per_liter ?? 0) > 0 && (float) $row->km_per_liter < $fuelAverage)
            ->count();
        $fuelForecastObject = $fuel['forecast'] ?? (object) [];
        $fuelChange = (float) ($fuelForecastObject->change_percent ?? 0);
        $projectedLiters = (float) ($fuelForecastObject->projected_liters ?? $fuel['totalFuel'] ?? 0);

        $buses = $healthDiag->buses;
        $openOrders = $healthDiag->open_orders;
        $overdueOrders = $healthDiag->overdue_orders;
        $busAttention = $healthDiag->attention_buses;

        $inventoryRows = $inventoryDiag->rows;
        $inventoryCategories = $inventoryDiag->categories;
        $inventoryAttentionRows = $inventoryDiag->attention_rows;

        $riskLevel = function (int $count, int $highThreshold, int $mediumThreshold): string {
            if ($count <= 0) {
                return 'low';
            }

            return $count >= $highThreshold
                ? 'high'
                : ($count >= $mediumThreshold ? 'medium' : 'low');
        };

        $issueCandidates = collect([
            (object) [
                'title' => 'Trip delay risk',
                'description' => 'Trips predicted to deviate from route baselines.',
                'icon' => 'fa-clock',
                'count' => $delayRisk,
                'unit' => 'trips',
            ],
            (object) [
                'title' => 'High idle risk',
                'description' => 'Buses predicted to exceed expected idle thresholds.',
                'icon' => 'fa-clock-rotate-left',
                'count' => $highIdleRisk,
                'unit' => 'buses',
            ],
            (object) [
                'title' => 'Fuel efficiency risk',
                'description' => 'Buses flagged below the fleet fuel baseline.',
                'icon' => 'fa-gas-pump',
                'count' => $fuelReviewUnits->count(),
                'unit' => 'buses',
            ],
            (object) [
                'title' => 'Maintenance attention',
                'description' => 'Buses with open or overdue maintenance work.',
                'icon' => 'fa-screwdriver-wrench',
                'count' => $busAttention->count(),
                'unit' => 'buses',
            ],
            (object) [
                'title' => 'Stockout risk',
                'description' => 'Items at or below reorder level.',
                'icon' => 'fa-boxes-stacked',
                'count' => $inventoryAttentionRows->count(),
                'unit' => 'items',
            ],
        ]);

        $fleetIssues = $issueCandidates
            ->filter(fn ($issue) => $issue->count > 0)
            ->sortByDesc('count')
            ->take(4)
            ->values()
            ->map(function ($issue, $index) {
                $level = $issue->count > 0
                    ? ($issue->count > 5 ? 'high' : 'medium')
                    : 'low';

                return [
                    'rank' => $index + 1,
                    'title' => $issue->title,
                    'description' => $issue->description,
                    'level' => ucfirst($level),
                    'count' => $issue->count . ' ' . $issue->unit,
                    'icon' => $issue->icon,
                    'class' => $level === 'high' ? 'danger' : ($level === 'medium' ? 'warning' : 'success'),
                ];
            });

        $predictionRows = $predictedRecords
            ->sortByDesc('delay_risk_percent')
            ->take(8)
            ->values()
            ->map(function ($prediction): array {
                return [
                    $prediction->trip_code ?? 'Scheduled Trip',
                    (string) ($prediction->bus_no ?? '—'),
                    $prediction->route ?? 'Unspecified Route',
                    $prediction->departure_at ? $prediction->departure_at->format('M j, Y h:i A') : '—',
                    (int) round((float) ($prediction->delay_risk_percent ?? 0)),
                    'Delay Risk',
                    $prediction->risk_level ?? 'Low',
                    'Scheduled',
                ];
            });

        $maxRouteTrips = max(1, (int) $fleetRoutes->max('trips'));
        $maxRouteDuration = max(1, (float) $fleetRoutes->max('average_duration'));
        $routeRows = $fleetRoutes->map(function ($route) use ($maxRouteTrips, $maxRouteDuration): array {
            $riskPercent = (int) round(
                (($route->trips / $maxRouteTrips) * 40)
                + (($route->average_duration / $maxRouteDuration) * 60)
            );
            $riskPercent = max(5, min(95, $riskPercent));
            $level = $riskPercent >= 60 ? 'High' : ($riskPercent >= 30 ? 'Medium' : 'Low');

            return [
                $route->label,
                (int) $route->trips,
                (int) round((float) $route->average_duration),
                $riskPercent,
                $level,
            ];
        });

        $statusLevel = function (string $status): string {
            return match (strtolower(trim($status))) {
                'priority review' => 'high',
                'review' => 'medium',
                default => 'low',
            };
        };

        $fuelRows = $fuelSummaries
            ->sortByDesc(function ($row): int {
                return match (strtolower(trim((string) ($row->status ?? '')))) {
                    'priority review' => 3,
                    'review' => 2,
                    'efficient' => 1,
                    default => 0,
                };
            })
            ->take(8)
            ->values()
            ->map(function ($row) use ($statusLevel): array {
                $level = $statusLevel((string) $row->status);
                $reason = match (strtolower(trim((string) $row->status))) {
                    'priority review' => 'Flagged for review',
                    'review' => 'Below fleet baseline',
                    'efficient' => 'Above fleet efficiency',
                    default => 'Within expected range',
                };

                return [
                    $row->bus_no,
                    number_format((float) ($row->distance_km ?? 0), 1),
                    number_format((float) ($row->fuel_liters ?? 0), 1),
                    number_format((float) ($row->km_per_liter ?? 0), 2),
                    isset($row->idling_minutes) ? number_format((float) $row->idling_minutes, 0) : '—',
                    $row->status,
                    ucfirst($level),
                    $reason,
                ];
            });

        $fuelDistribution = (object) [
            'low' => $fuelSummaries->filter(fn ($row) => $statusLevel((string) $row->status) === 'low')->count(),
            'medium' => $fuelSummaries->filter(fn ($row) => $statusLevel((string) $row->status) === 'medium')->count(),
            'high' => $fuelSummaries->filter(fn ($row) => $statusLevel((string) $row->status) === 'high')->count(),
            'total' => $fuelSummaries->count(),
        ];

        $fuelTrend = collect($fuel['trend'] ?? [])->values();
        $fuelTrendLabels = $fuelTrend->map(function ($bucket): string {
            return is_array($bucket) ? (string) ($bucket['label'] ?? '') : (string) ($bucket->label ?? '');
        })->values();
        $fuelTrendActual = $fuelTrend
            ->map(function ($bucket): float {
                return is_array($bucket) ? (float) ($bucket['fuel_liters'] ?? 0) : (float) ($bucket->fuel_liters ?? 0);
            })
            ->values();
        $fuelTrendForecast = $fuelTrend
            ->map(function ($bucket) use ($fuelChange): float {
                $value = is_array($bucket) ? (float) ($bucket['fuel_liters'] ?? 0) : (float) ($bucket->fuel_liters ?? 0);
                $adjustment = max(-0.25, min(0.25, $fuelChange / 100));
                return round($value * (1 + $adjustment), 1);
            })
            ->values();

        $fuelTrendEfficiency = $fuelTrend
            ->map(function ($bucket): float {
                $value = is_array($bucket) ? (float) ($bucket['efficiency'] ?? 0) : (float) ($bucket->efficiency ?? 0);
                return round($value, 2);
            })
            ->values();
        $fuelBaselineEfficiency = $fuelTrendEfficiency
            ->filter(fn (float $value) => $value > 0)
            ->avg();

        $fuelTrendEfficiencyForecast = $fuelTrendEfficiency
            ->map(function (float $value) use ($fuelBaselineEfficiency): float {
                if ((float) $value > 0) {
                    return $value;
                }

                return $fuelBaselineEfficiency > 0 ? round($fuelBaselineEfficiency, 2) : 0.0;
            })
            ->values();

        $busRiskRow = [];
        foreach ($buses as $bus) {
            $busNo = strtoupper(trim((string) $bus->bus_no));
            $attention = collect($busAttention)->first(
                fn ($row) => strtoupper(trim((string) ($row->bus_no ?? ''))) === $busNo
            );
            $open = $attention?->open_orders ?? 0;
            $overdue = $attention?->overdue_orders ?? 0;
            $score = $attention?->attention_score ?? 0;
            $level = $overdue > 0 ? 'High' : ($open > 0 ? 'Medium' : 'Low');

            $busRiskRow[] = [
                $bus->bus_no,
                $bus->plate_no ?? '—',
                trim((string) ($bus->bus_model ?? '')) !== '' ? $bus->bus_model : '—',
                $bus->status,
                $open,
                $overdue,
                $level,
                $score,
            ];
        }
        $busRiskRows = collect($busRiskRow)
            ->sortByDesc(fn (array $row) => (int) $row[7])
            ->take(8)
            ->values();

        $inventoryLevel = function (string $state): string {
            return $state === 'Out of Stock' ? 'high' : ($state === 'Low Stock' ? 'medium' : 'low');
        };

        $inventoryRowsList = $inventoryRows
            ->sortByDesc(fn ($row) => ($row->severity * 100000) + max(0, ($row->gap ?? 0)))
            ->take(8)
            ->values()
            ->map(function ($row) use ($inventoryLevel): array {
                return [
                    $row->item_code ?? '—',
                    $row->name ?? 'Inventory Item',
                    $row->category ?? 'Uncategorized',
                    (int) ($row->on_hand ?? 0),
                    (int) ($row->reorder_level ?? 0),
                    $row->state ?? 'Well Stocked',
                    max(0, (int) ($row->gap ?? 0)),
                    ucfirst($inventoryLevel($row->state ?? 'Well Stocked')),
                ];
            });

        $domainSummaries = collect([
            (object) [
                'domain' => 'Fleet & Trip',
                'basis' => number_format($tripCount) . ' trips',
                'signal' => number_format($tripsAtRisk) . ' at risk',
                'level' => $riskLevel($delayRisk + $highIdleRisk, 4, 1),
                'status' => $fleetPred->available === true ? 'ML forecast' : 'Derived from trend',
                'icon' => 'fa-route',
            ],
            (object) [
                'domain' => 'Fuel',
                'basis' => number_format($fuelSummaries->count()) . ' units',
                'signal' => number_format($fuelReviewUnits->count()) . ' flagged',
                'level' => $riskLevel($fuelReviewUnits->count(), 3, 1),
                'status' => ($fuelForecastObject->available ?? false) === true ? '7-day baseline' : 'Derived from trend',
                'icon' => 'fa-gas-pump',
            ],
            (object) [
                'domain' => 'Bus Health',
                'basis' => number_format($buses->count()) . ' buses',
                'signal' => number_format($overdueOrders->count()) . ' overdue',
                'level' => $riskLevel($busAttention->count(), 3, 1),
                'status' => 'Job order records',
                'icon' => 'fa-screwdriver-wrench',
            ],
            (object) [
                'domain' => 'Inventory',
                'basis' => number_format($inventoryDiag->total) . ' items',
                'signal' => number_format($inventoryAttentionRows->count()) . ' attention',
                'level' => $riskLevel($inventoryDiag->critical, 1, 0),
                'status' => 'Reorder exposure',
                'icon' => 'fa-boxes-stacked',
            ],
        ]);

        $fleetLevel = $riskLevel($delayRisk + $highIdleRisk, 4, 1);
        $fuelDomainLevel = $riskLevel($fuelReviewUnits->count(), 3, 1);
        $busDomainLevel = $riskLevel($busAttention->count(), 3, 1);
        $inventoryDomainLevel = $riskLevel($inventoryDiag->critical, 1, 0);

        $domainRiskBuckets = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
        ];
        foreach (['fleet' => $fleetLevel, 'fuel' => $fuelDomainLevel, 'bus' => $busDomainLevel, 'inventory' => $inventoryDomainLevel] as $domainKey => $level) {
            $baseCount = match ($domainKey) {
                'fleet' => $tripsAtRisk + $delayRisk,
                'fuel' => $fuelReviewUnits->count() + $fuelHighIdlingUnits->count(),
                'bus' => $busAttention->count(),
                'inventory' => $inventoryAttentionRows->count(),
            };
            $domainRiskBuckets[$level] += $baseCount;
        }
        $riskTotal = max(1, array_sum($domainRiskBuckets));

        $highestDomain = $domainSummaries->sortByDesc(fn ($row) => (int) $this->levelWeight($row->level))->first();

        return (object) [
            'all' => (object) [
                'kpis' => [
                    ['label' => 'Trips at Risk', 'value' => number_format($tripsAtRisk), 'icon' => 'fa-triangle-exclamation', 'tone' => 'danger', 'caption' => $tripCount > 0 ? sprintf('%.0f%% of processed trips', $tripCount > 0 ? ($tripsAtRisk / $tripCount) * 100 : 0) : 'No processed trips'],
                    ['label' => 'Predicted Delays', 'value' => number_format($delayRisk), 'icon' => 'fa-clock', 'tone' => 'warning', 'caption' => number_format($averageDelayRiskPct, 0) . '% avg. delay risk'],
                    ['label' => 'Utilization Forecast', 'value' => number_format($fleetAvailability, 1) . '%', 'icon' => 'fa-chart-line', 'tone' => 'success', 'caption' => 'Availability from fleet status'],
                    ['label' => 'Fuel Review Units', 'value' => number_format($fuelReviewUnits->count()), 'icon' => 'fa-gas-pump', 'tone' => 'warning', 'caption' => number_format($fuelHighIdlingUnits->count()) . ' high-idle units'],
                    ['label' => 'Completion Forecast', 'value' => number_format($completionForecast, 1) . '%', 'icon' => 'fa-circle-check', 'tone' => 'purple', 'caption' => $tripCount > 0 ? 'Based on trip history' : 'No history yet'],
                ],
                'overview' => (object) [
                    'labels' => ['Fleet & Trip', 'Fuel', 'Bus Health', 'Inventory'],
                    'records' => [$tripCount, $fuelSummaries->count(), $buses->count(), $inventoryDiag->total],
                    'at_risk' => [$tripsAtRisk, $fuelReviewUnits->count(), $busAttention->count(), $inventoryAttentionRows->count()],
                ],
                'risk' => (object) [
                    'low' => $domainRiskBuckets['low'],
                    'medium' => $domainRiskBuckets['medium'],
                    'high' => $domainRiskBuckets['high'],
                    'total' => $riskTotal,
                ],
                'issues' => $fleetIssues->values(),
                'table_rows' => $domainSummaries->values(),
                'fuel_labels' => $fuelTrendLabels,
                'fuel_actual' => $fuelTrendActual,
                'fuel_forecast' => $fuelTrendForecast,
                'insights' => collect([
                    (object) [
                        'icon' => 'fa-chart-line',
                        'tone' => 'blue',
                        'title' => 'Top forecast area',
                        'text' => $highestDomain
                            ? sprintf('%s leads forecast signal volume with %s.', $highestDomain->domain, $highestDomain->signal)
                            : 'No forecast signals are present in the selected records.',
                    ],
                    (object) [
                        'icon' => 'fa-robot',
                        'tone' => 'green',
                        'title' => 'Prediction engine',
                        'text' => $fleetPred->available === true
                            ? 'Fleet & Trip ML forecasting is live for upcoming scheduled trips.'
                            : 'Python prediction service is offline; forecasts fall back to recorded-trend calculations.',
                    ],
                    (object) [
                        'icon' => 'fa-gas-pump',
                        'tone' => 'orange',
                        'title' => 'Fuel efficiency',
                        'text' => $fuelAverage > 0
                            ? sprintf('Fleet efficiency averages %.2f km/L across %d recorded units.', $fuelAverage, $fuelSummaries->count())
                            : 'No fuel records are available for the selected period.',
                    ],
                    (object) [
                        'icon' => 'fa-boxes-stacked',
                        'tone' => 'yellow',
                        'title' => 'Stock exposure',
                        'text' => $inventoryAttentionRows->isNotEmpty()
                            ? sprintf('%d stock items need restocking attention.', $inventoryAttentionRows->count())
                            : 'All inventory items are above reorder level.',
                    ],
                ]),
            ],
            'fleet' => (object) [
                'stats' => [
                    'tripsAtRisk' => $tripsAtRisk,
                    'predictedDelays' => $delayRisk,
                    'utilization' => round($fleetAvailability, 1),
                    'highIdleRisk' => $highIdleRisk,
                    'completionForecast' => round($completionForecast, 1),
                ],
                'issues' => $fleetIssues,
                'predictions' => $predictionRows,
                'routes' => $routeRows,
            ],
            'fuel' => (object) [
                'kpis' => [
                    ['label' => 'Consumption Forecast', 'value' => number_format($projectedLiters, 0) . ' L', 'icon' => 'fa-gas-pump', 'tone' => 'blue', 'caption' => sprintf('%+.1f%% projected change', $fuelChange)],
                    ['label' => 'Efficiency Forecast', 'value' => $fuelAverage > 0 ? number_format($fuelAverage, 2) . ' km/L' : '—', 'icon' => 'fa-chart-line', 'tone' => 'green', 'caption' => 'Fleet baseline'],
                    ['label' => 'Below Fleet Average', 'value' => number_format($belowFleetAverage) . ' buses', 'icon' => 'fa-arrow-down', 'tone' => 'purple', 'caption' => sprintf('of %d recorded units', $fuelSummaries->count())],
                    ['label' => 'Review Units', 'value' => number_format($fuelReviewUnits->count()) . ' buses', 'icon' => 'fa-triangle-exclamation', 'tone' => 'warning', 'caption' => 'Fuel review signal'],
                    ['label' => 'High Idle Units', 'value' => number_format($fuelHighIdlingUnits->count()) . ' buses', 'icon' => 'fa-hourglass-half', 'tone' => 'warning', 'caption' => 'Idling intensity signal'],
                ],
                'rows' => $fuelRows,
                'factors' => collect([
                    (object) ['title' => 'High Consumption Trend', 'description' => sprintf('%d buses flagged for fuel review.', $fuelReviewUnits->count()), 'level' => $fuelReviewUnits->count() > 0 ? 'High' : 'Low'],
                    (object) ['title' => 'Frequent Idling', 'description' => sprintf('%d units exceed idle intensity thresholds.', $fuelHighIdlingUnits->count()), 'level' => $fuelHighIdlingUnits->count() > 0 ? 'Medium' : 'Low'],
                    (object) ['title' => 'Below Fleet Efficiency', 'description' => sprintf('%d buses below the %.2f km/L fleet baseline.', $belowFleetAverage, $fuelAverage), 'level' => $belowFleetAverage > 0 ? 'Medium' : 'Low'],
                ]),
                'distribution' => $fuelDistribution,
                'trend_labels' => $fuelTrendLabels,
                'trend_actual' => $fuelTrendActual,
                'trend_forecast' => $fuelTrendForecast,
                'efficiency_labels' => $fuelTrendLabels,
                'efficiency_actual' => $fuelTrendEfficiency,
                'efficiency_forecast' => $fuelTrendEfficiencyForecast,
            ],
            'bus_health' => (object) [
                'kpis' => [
                    ['label' => 'Total Buses', 'value' => number_format($healthDiag->total), 'icon' => 'fa-bus', 'tone' => 'blue', 'caption' => 'Registered fleet'],
                    ['label' => 'Active Buses', 'value' => number_format($healthDiag->active), 'icon' => 'fa-circle-check', 'tone' => 'success', 'caption' => sprintf('%.0f%% of fleet', $healthDiag->total > 0 ? ($healthDiag->active / $healthDiag->total) * 100 : 0)],
                    ['label' => 'Under Maintenance', 'value' => number_format($healthDiag->maintenance), 'icon' => 'fa-screwdriver-wrench', 'tone' => 'warning', 'caption' => 'Currently in shop'],
                    ['label' => 'Open Job Orders', 'value' => number_format($healthDiag->open_orders->count()), 'icon' => 'fa-clipboard-list', 'tone' => 'purple', 'caption' => 'Not yet completed'],
                    ['label' => 'Overdue Job Orders', 'value' => number_format($healthDiag->overdue_orders->count()), 'icon' => 'fa-clock', 'tone' => 'danger', 'caption' => 'Past due date'],
                ],
                'distribution' => (object) [
                    'active' => $healthDiag->active,
                    'maintenance' => $healthDiag->maintenance,
                    'inactive' => $healthDiag->inactive,
                    'total' => max(1, $healthDiag->total),
                ],
                'rows' => $busRiskRows,
                'issues' => collect([
                    (object) ['icon' => 'fa-clock', 'tone' => 'danger', 'title' => 'Overdue maintenance', 'description' => sprintf('%d job orders past their estimated completion.', $overdueOrders->count())],
                    (object) ['icon' => 'fa-screwdriver-wrench', 'tone' => 'warning', 'title' => 'Open job orders', 'description' => sprintf('%d active orders still in progress.', $openOrders->count())],
                    (object) ['icon' => 'fa-bus', 'tone' => 'purple', 'title' => 'Non-active fleet', 'description' => sprintf('%d buses are not currently in service.', $healthDiag->total - $healthDiag->active)],
                ]),
            ],
            'inventory' => (object) [
                'kpis' => [
                    ['label' => 'Total Items', 'value' => number_format($inventoryDiag->total), 'icon' => 'fa-boxes-stacked', 'tone' => 'blue', 'caption' => 'Registered inventory'],
                    ['label' => 'Well Stocked', 'value' => number_format($inventoryDiag->healthy), 'icon' => 'fa-circle-check', 'tone' => 'success', 'caption' => 'Above reorder level'],
                    ['label' => 'Low Stock', 'value' => number_format($inventoryDiag->low), 'icon' => 'fa-triangle-exclamation', 'tone' => 'warning', 'caption' => 'At or near reorder level'],
                    ['label' => 'Out of Stock', 'value' => number_format($inventoryDiag->critical), 'icon' => 'fa-ban', 'tone' => 'danger', 'caption' => 'No on-hand quantity'],
                    ['label' => 'Stockout Risk', 'value' => number_format($inventoryDiag->total > 0 ? ($inventoryAttentionRows->count() / $inventoryDiag->total) * 100 : 0, 0) . '%', 'icon' => 'fa-chart-line', 'tone' => 'purple', 'caption' => 'Share requiring restock'],
                ],
                'rows' => $inventoryRowsList,
                'categories' => $inventoryCategories->take(6)->values(),
                'attention_count' => $inventoryAttentionRows->count(),
                'total' => $inventoryDiag->total,
                'healthy' => $inventoryDiag->healthy,
                'low' => $inventoryDiag->low,
                'critical' => $inventoryDiag->critical,
                'issues' => collect([
                    (object) ['icon' => 'fa-ban', 'tone' => 'danger', 'title' => 'Out of stock', 'description' => sprintf('%d items have zero on-hand quantity.', $inventoryDiag->critical)],
                    (object) ['icon' => 'fa-triangle-exclamation', 'tone' => 'warning', 'title' => 'Low stock', 'description' => sprintf('%d items are at or below reorder level.', $inventoryDiag->low)],
                    (object) ['icon' => 'fa-boxes-stacked', 'tone' => 'blue', 'title' => 'Stock coverage', 'description' => sprintf('%d items remain above reorder level.', $inventoryDiag->healthy)],
                ]),
            ],
        ];
    }

    private function levelWeight(string $level): int
    {
        return match ($level) {
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }
}
