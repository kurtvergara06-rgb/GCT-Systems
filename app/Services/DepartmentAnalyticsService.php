<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Read-only aggregates; no Admin analytics datasets, user-selected tables or model mutations. */
class DepartmentAnalyticsService
{
    public function maintenanceJobs(): Builder
    {
        return $this->scopedQuery('job_orders', 'maintenance');
    }

    public function summary(string $department, string $from, string $to): array
    {
        $sources = match ($department) {
            'operation' => [
                ['GPS trip records', 'gps_trip_records', 'beginning_at', null, 'gps'],
                ['Trip schedules', 'trip_schedules', 'trip_date', 'status', 'operation'],
                ['Transport incidents', 'incidents', 'incident_reported_at', 'status', 'operation'],
            ],
            'maintenance' => [
                ['Job orders', 'job_orders', 'created_at', 'status', 'maintenance'],
                ['Fuel reports', 'fuel_reports', 'report_date', 'status', 'maintenance'],
            ],
            'warehouse' => [
                ['Inventory items', 'inventory_items', 'created_at', 'status', 'warehouse'],
                ['Stock movements', 'stock_movements', 'created_at', 'movement_type', 'warehouse'],
                ['Issuance history', 'inventory_issuances', 'issued_at', null, 'warehouse'],
            ],
            'purchase' => [
                ['Purchase orders', 'purchase_orders', 'po_date', 'status', 'purchase'],
                ['Purchase requests', 'purchase_requests', 'created_at', 'status', 'purchase'],
            ],
            default => abort(403),
        };
        $months = [];
        for ($month = CarbonImmutable::parse($from)->startOfMonth(); $month <= CarbonImmutable::parse($to); $month = $month->addMonth()) {
            $months[$month->format('Y-m')] = ['month' => $month->format('M Y'), 'counts' => [], 'total' => 0];
        }
        $metrics = [];
        $recentBySource = [];
        foreach ($sources as [$label, $table, $date, $status, $scope]) {
            $recentGroup = ['label' => $label, 'available' => false, 'records' => collect()];
            $metric = ['label' => $label, 'total' => null, 'statuses' => collect(), 'available' => false];
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $date)) {
                    $metrics[] = $metric;
                    continue;
                }
                $query = $this->scopedQuery($table, $scope);
                $query->whereBetween($table.'.'.$date, [$from.' 00:00:00', $to.' 23:59:59.999999']);
                $metric['total'] = (clone $query)->count();
                $metric['available'] = true;
                $recentGroup['available'] = true;
                if (Schema::hasColumn($table, 'id')) {
                    $recent = (clone $query)->select([
                        $table.'.id as record_id',
                        $table.'.'.$date.' as recorded_at',
                    ]);
                    if ($status && Schema::hasColumn($table, $status)) {
                        $recent->addSelect($table.'.'.$status.' as record_status');
                    } else {
                        $recent->selectRaw('NULL AS record_status');
                    }
                    $recentGroup['records'] = $recent->orderByDesc($table.'.'.$date)->limit(5)->get();
                }
                if ($status && Schema::hasColumn($table, $status)) {
                    $column = $table.'.'.$status;
                    $metric['statuses'] = (clone $query)->select($column.' as status')->selectRaw('COUNT(*) AS total')
                        ->groupBy($column)->orderByDesc('total')->limit(8)->get();
                }
                $rows = (clone $query)->selectRaw("DATE_FORMAT(`$table`.`$date`, '%Y-%m') AS month_key, COUNT(*) AS total")
                    ->groupBy('month_key')->get();
                foreach ($rows as $row) {
                    if (isset($months[$row->month_key])) {
                        $months[$row->month_key]['counts'][$label] = (int) $row->total;
                        $months[$row->month_key]['total'] += (int) $row->total;
                    }
                }
            } catch (\Illuminate\Database\QueryException $exception) {
                Log::warning('Department analytics source unavailable.', ['department' => $department, 'source' => $table]);
                $metric = ['label' => $label, 'total' => null, 'statuses' => collect(), 'available' => false];
            }
            $metrics[] = $metric;
            $recentBySource[] = $recentGroup;
        }
        return [
            'metrics' => $metrics,
            'recentBySource' => $recentBySource,
            'monthlyActivity' => collect(array_values($months)),
            'from' => $from,
            'to' => $to,
        ];
    }

    /** Service reachability is not department-scoped prediction readiness. */
    public function modelReadiness(string $department): array
    {
        $paths = match ($department) {
            'operation' => ['ETA model' => '/eta/status', 'Delay model' => '/delay/status'],
            'maintenance' => ['Fuel model' => '/fuel/status'],
            'warehouse' => ['Inventory model' => '/inventory/status'],
            default => [],
        };
        $base = rtrim((string) config('services.nlp.api_url'), '/');
        return collect($paths)->map(function (string $path, string $name) use ($base): array {
            $result = ['name' => $name, 'reachable' => false, 'model_ready' => false, 'genuine_training' => false];
            try {
                $response = \Illuminate\Support\Facades\Http::acceptJson()->connectTimeout(1)->timeout(2)->get($base.$path);
                $result['reachable'] = $response->successful();
                $body = $response->json();
                if ($response->successful() && is_array($body) && ($body['success'] ?? false) === true) {
                    $result['model_ready'] = ($body['model_ready'] ?? false) === true;
                    $result['genuine_training'] = ($body['data_source'] ?? '') === 'genuine'
                        && ($body['is_production_model'] ?? false) === true
                        && (int) ($body['training_record_count'] ?? $body['sample_count'] ?? 0) > 0;
                }
            } catch (\Illuminate\Http\Client\ConnectionException $exception) {
                // No synthetic fallback or operational prediction request.
            }
            return $result;
        })->values()->all();
    }

    private function scopedQuery(string $table, string $department): Builder
    {
        $query = DB::table($table);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull($table.'.deleted_at');
        }
        // A mixed-department extension must never widen this registry's visibility.
        if (Schema::hasColumn($table, 'department')) {
            $query->whereRaw('LOWER(TRIM(`'.$table.'`.`department`)) = ?', [$department === 'gps' ? 'operation' : $department]);
        }
        if ($table === 'gps_trip_records') {
            // BatchUpload::SUPPORTED_PROCESSORS establishes Operation ownership;
            // provenance and processing state prevent demo/unknown imports appearing as live data.
            $query->whereExists(fn (Builder $batch) => $batch->selectRaw('1')->from('batch_uploads')
                ->whereColumn('batch_uploads.id', 'gps_trip_records.batch_upload_id')
                ->where('batch_uploads.module', 'Operation')->where('batch_uploads.data_type', 'GPS Trip Records')
                ->where('batch_uploads.status', 'Processed')->where('batch_uploads.data_origin', 'genuine'));
        } elseif ($table === 'trip_schedules') {
            $this->ownedBy($query, $table, 'created_by', 'operation');
        } elseif ($table === 'incidents') {
            $this->ownedBy($query, $table, 'reported_by', 'operation');
        } elseif ($table === 'job_orders') {
            // A referral/PMS link is an explicit handoff into Maintenance. Direct
            // records without either link require a verified Maintenance/Admin creator.
            $query->where(function (Builder $owned) use ($table): void {
                $this->ownedBy($owned, $table, 'created_by', 'maintenance');
                $owned->orWhereNotNull('maintenance_referral_id')->orWhereNotNull('pms_schedule_id');
            });
        } elseif ($table === 'inventory_items') {
            $query->where('source', 'app');
        } elseif ($table === 'stock_movements') {
            $query->where('stock_movements.source', 'app')->whereExists(fn (Builder $item) => $item->selectRaw('1')
                ->from('inventory_items')->whereColumn('inventory_items.id', 'stock_movements.inventory_item_id')
                ->where('inventory_items.source', 'app'));
        } elseif ($table === 'inventory_issuances') {
            $this->ownedBy($query, $table, 'issued_by', 'warehouse');
        } elseif ($table === 'purchase_requests') {
            // Reuse the Purchase workflow handoff, excluding confidential drafts
            // still internal to Maintenance/Warehouse and terminal unhanded requests.
            $query->whereIn('status', ['For Purchase', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered', 'Picked Up', 'Issued']);
        } elseif ($table === 'purchase_orders') {
            // Same active/history populations as PurchaseOrderController::index.
            $query->whereIn('status', ['For Review', 'Revision Required', 'Rejected', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered', 'Picked Up']);
        }
        // FuelReportController authorizes the complete fuel-report register to
        // Maintenance. Only its aggregates are exposed; no driver/GPS rows leave here.
        return $query;
    }

    private function ownedBy(Builder $query, string $table, string $column, string $department): void
    {
        $query->whereExists(fn (Builder $owner) => $owner->selectRaw('1')->from('users as analytics_owner')
            ->whereColumn('analytics_owner.id', $table.'.'.$column)
            ->whereRaw('LOWER(TRIM(analytics_owner.department)) IN (?, ?)', [$department, 'admin']));
    }
}
