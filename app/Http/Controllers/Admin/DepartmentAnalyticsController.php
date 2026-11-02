<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DepartmentAnalyticsController extends Controller
{
    private const STAGES = ['descriptive', 'diagnostic', 'predictive', 'prescriptive'];
    private const DEPARTMENTS = ['operation', 'maintenance', 'warehouse', 'purchase'];

    public function show(Request $request, string $stage): View
    {
        abort_unless(in_array($stage, self::STAGES, true), 404);
        $user = $request->user();
        abort_unless($user && $user->hasSystemPermission('analytics', 'view'), 403);
        $capability = match ($stage) {
            'descriptive' => 'view',
            'prescriptive' => 'recommendations',
            default => 'analyze',
        };
        abort_unless($user->hasSystemPermission('analytics', $capability), 403);

        $department = strtolower(trim((string) $user->department));
        abort_unless(in_array($department, self::DEPARTMENTS, true), 403);

        // Allow-listed department sources only; do not load the Admin cross-module datasets.
        $sources = match ($department) {
            'operation' => ['GPS trip records' => 'gps_trip_records', 'Transport incidents' => 'incidents'],
            'maintenance' => ['Job orders' => 'job_orders', 'Fuel reports' => 'fuel_reports'],
            'warehouse' => ['Inventory items' => 'inventory_items', 'Stock movements' => 'stock_movements'],
            'purchase' => ['Purchase orders' => 'purchase_orders', 'Purchase requests' => 'purchase_requests'],
        };
        $metrics = [];
        foreach ($sources as $label => $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table($table);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            $total = (clone $query)->count();
            $statuses = Schema::hasColumn($table, 'status')
                ? (clone $query)->select('status')->selectRaw('COUNT(*) AS total')
                    ->groupBy('status')->orderByDesc('total')->limit(8)->get()
                : collect();
            $metrics[] = compact('label', 'total', 'statuses');
        }

        // Department-scoped descriptive trend from the same allow-listed sources.
        // Only records with actual created_at timestamps are included.
        $monthlyActivity = collect();
        if ($stage === 'descriptive') {
            $months = collect(range(5, 0))->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset));
            $monthlyActivity = $months->map(function ($month) use ($sources): array {
                $monthStart = $month->copy()->startOfMonth();
                $monthEnd = $month->copy()->endOfMonth();
                $counts = [];
                foreach ($sources as $label => $table) {
                    if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'created_at')) {
                        continue;
                    }
                    $query = DB::table($table)->whereBetween('created_at', [$monthStart, $monthEnd]);
                    if (Schema::hasColumn($table, 'deleted_at')) {
                        $query->whereNull('deleted_at');
                    }
                    $counts[$label] = $query->count();
                }

                return ['month' => $month->format('M Y'), 'counts' => $counts, 'total' => array_sum($counts)];
            });
        }

        return view('Admin.Analytics.department-stage', compact('department', 'stage', 'metrics', 'monthlyActivity'));
    }
}
