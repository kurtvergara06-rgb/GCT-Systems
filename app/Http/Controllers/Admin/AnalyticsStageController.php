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

        $fleetView = app(FleetTripAnalyticsController::class)->index($request, $predictionService);
        $fleet = $fleetView->getData();
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

        $diagnostic = $stage === 'diagnostic'
            ? $this->buildDiagnosticData($request, $fleet, $fuel, $inventoryItems, $inventory)
            : null;

        $viewName = $stage === 'diagnostic'
            ? 'Admin.Analytics.diagnostic.layout'
            : 'Admin.Analytics.stage';

        return view($viewName, [
            'stage' => $stage,
            'stageLabel' => self::STAGES[$stage],
            'domain' => $domain,
            'fleet' => $fleet,
            'fuel' => $fuel,
            'inventory' => $inventory,
            'diagnostic' => $diagnostic,
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
}
