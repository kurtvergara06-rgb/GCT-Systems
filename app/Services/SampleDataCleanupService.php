<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SampleDataCleanupService
{
    private const PREFIXES = [
        'trip_schedules' => ['column' => 'trip_code', 'values' => ['TRIP-GCT-', 'TRIP-DEMO-']],
        'daily_driver_reports' => ['column' => 'ddr_no', 'values' => ['DDR-GCT-', 'DEMO-DDR-']],
        'incidents' => ['column' => 'incident_no', 'values' => ['INC-GCT-', 'DEMO-INC-']],
        'job_orders' => ['column' => 'job_order_no', 'values' => ['JO-GCT-', 'DEMO-JO-']],
        'purchase_requests' => ['column' => 'pr_no', 'values' => ['PR-GCT-', 'DEMO-PR-']],
        'purchase_orders' => ['column' => 'po_no', 'values' => ['PO-GCT-', 'DEMO-PO-']],
        'inventory_items' => ['column' => 'item_code', 'values' => ['GCT-PART-', 'DEMO-PART-']],
        'drivers' => ['column' => 'driver_id', 'values' => ['GCT-DRV-', 'DEMO-DRV-']],
        'shuttle_routes' => ['column' => 'route_code', 'values' => ['GCT-RT-', 'DEMO-RT-']],
    ];

    /** @return array{counts: array<string, int>, candidates: array<string, array<int, string>>, protected: array<string, array<int, string>>, orphans: array<string, array<int, string>>} */
    public function audit(): array
    {
        $counts = [];
        foreach (self::PREFIXES as $table => $rule) {
            $counts[$table] = $this->sampleQuery($table)->count();
        }

        $counts['buses'] = $this->sampleBusQuery()->count();
        $counts['stock_movements'] = DB::table('stock_movements')
            ->whereIn('source', ['demo', 'simulated'])
            ->count();

        return [
            'counts' => $counts,
            'candidates' => [
                'buses' => $this->sampleBusQuery()->orderBy('bus_no')->pluck('bus_no')->all(),
                'drivers' => $this->sampleQuery('drivers')->orderBy('driver_id')->pluck('driver_id')->all(),
                'routes' => $this->sampleQuery('shuttle_routes')->orderBy('route_code')->pluck('route_code')->all(),
                'inventory_items' => $this->sampleQuery('inventory_items')->orderBy('item_code')->pluck('item_code')->all(),
            ],
            'protected' => $this->protectedMasters(),
            'orphans' => $this->orphans(),
        ];
    }

    /** @return array<string, int> */
    public function execute(): array
    {
        return DB::transaction(function (): array {
            $deleted = [];

            // A movement linked to an issuance is an auditable warehouse record.
            $deleted['stock_movements'] = DB::table('stock_movements')
                ->whereIn('source', ['demo', 'simulated'])
                ->whereNotExists(fn (Builder $query) => $query
                    ->selectRaw('1')
                    ->from('inventory_issuance_items')
                    ->whereColumn('inventory_issuance_items.stock_movement_id', 'stock_movements.id'))
                ->delete();

            $deleted['purchase_orders'] = $this->sampleQuery('purchase_orders')
                ->whereNotExists(fn (Builder $query) => $query
                    ->selectRaw('1')
                    ->from('scheduled_purchases')
                    ->whereColumn('scheduled_purchases.last_po_id', 'purchase_orders.id'))
                ->delete();

            $deleted['purchase_requests'] = $this->sampleQuery('purchase_requests')
                ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('purchase_orders')
                    ->whereColumn('purchase_orders.purchase_request_id', 'purchase_requests.id'))
                ->delete();

            $deleted['job_orders'] = $this->sampleQuery('job_orders')
                ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('purchase_requests')
                    ->whereColumn('purchase_requests.job_order_no', 'job_orders.job_order_no'))
                ->delete();

            $deleted['daily_driver_reports'] = $this->sampleDailyDriverReportQuery()->delete();

            $deleted['incidents'] = $this->sampleQuery('incidents')
                ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('job_orders')
                    ->whereColumn('job_orders.incident_id', 'incidents.id'))
                ->delete();

            $deletableScheduleIds = $this->deletableScheduleIds();
            $deleted['trip_assignments'] = $deletableScheduleIds->isEmpty()
                ? 0
                : DB::table('trip_assignments')->whereIn('trip_schedule_id', $deletableScheduleIds)->delete();
            $deleted['trip_schedules'] = $deletableScheduleIds->isEmpty()
                ? 0
                : DB::table('trip_schedules')->whereIn('id', $deletableScheduleIds)->delete();

            $deleted['driver_attendances'] = DB::table('driver_attendances')
                ->where(function (Builder $query): void {
                    $this->whereSample($query, 'driver_id', self::PREFIXES['drivers']['values']);
                })
                ->whereNotExists(fn (Builder $query) => $query
                    ->selectRaw('1')->from('trip_assignments')
                    ->whereColumn('trip_assignments.driver_attendance_id', 'driver_attendances.id'))
                ->delete();

            $deleted += $this->deleteUnreferencedMasters();

            return $deleted;
        });
    }

    /** @return array<string, array<int, string>> */
    public function orphans(): array
    {
        $missingJobOrders = DB::table('purchase_requests as pr')
            ->leftJoin('job_orders as jo', 'jo.job_order_no', '=', 'pr.job_order_no')
            ->whereNotNull('pr.job_order_no')->whereNull('jo.id')
            ->orderBy('pr.pr_no')->pluck('pr.pr_no')->map(fn ($value) => (string) $value)->all();

        $missingPurchaseRequests = [];
        DB::table('purchase_orders')->orderBy('po_no')->get(['po_no', 'purchase_request_id', 'items'])
            ->each(function (object $order) use (&$missingPurchaseRequests): void {
                if ($order->purchase_request_id !== null
                    && ! DB::table('purchase_requests')->where('id', $order->purchase_request_id)->exists()) {
                    $missingPurchaseRequests[] = (string) $order->po_no;

                    return;
                }

                $items = json_decode((string) $order->items, true);
                $prNumbers = collect(is_array($items) ? $items : [])->pluck('pr_no')->filter()->unique();
                $existingPrNumbers = DB::table('purchase_requests')->whereIn('pr_no', $prNumbers)->pluck('pr_no');
                if ($prNumbers->diff($existingPrNumbers)->isNotEmpty()) {
                    $missingPurchaseRequests[] = (string) $order->po_no;
                }
            });

        return [
            'purchase_requests_missing_job_order' => $missingJobOrders,
            'purchase_orders_missing_purchase_request' => array_values(array_unique($missingPurchaseRequests)),
            'job_orders_missing_bus' => DB::table('job_orders as jo')
                ->leftJoin('buses as b', 'b.bus_no', '=', 'jo.bus_no')
                ->whereNotNull('jo.bus_no')->whereNull('b.id')
                ->orderBy('jo.job_order_no')->pluck('jo.job_order_no')->map(fn ($value) => (string) $value)->all(),
            'purchase_requests_missing_bus' => DB::table('purchase_requests as pr')
                ->leftJoin('buses as b', 'b.bus_no', '=', 'pr.bus_no')
                ->whereNotNull('pr.bus_no')->whereNull('b.id')
                ->orderBy('pr.pr_no')->pluck('pr.pr_no')->map(fn ($value) => (string) $value)->all(),
            'pms_schedules_missing_bus' => DB::table('pms_schedules as pms')
                ->leftJoin('buses as b', 'b.bus_no', '=', 'pms.bus_no')
                ->whereNotNull('pms.bus_no')->whereNull('b.id')
                ->orderBy('pms.id')->pluck('pms.id')->map(fn ($value) => (string) $value)->all(),
            'fuel_reports_missing_bus' => DB::table('fuel_reports as fr')
                ->leftJoin('buses as b', 'b.bus_no', '=', 'fr.bus_no')
                ->whereNotNull('fr.bus_no')->whereNull('b.id')
                ->orderBy('fr.id')->pluck('fr.id')->map(fn ($value) => (string) $value)->all(),
            'batch_uploads_missing_bus' => DB::table('batch_uploads as bu')
                ->leftJoin('buses as b', 'b.bus_no', '=', 'bu.bus_no')
                ->whereNotNull('bu.bus_no')->whereNull('b.id')
                ->orderBy('bu.id')->pluck('bu.id')->map(fn ($value) => (string) $value)->all(),
            'gps_trip_records_missing_bus' => DB::table('gps_trip_records as gps')
                ->leftJoin('buses as b', 'b.bus_no', '=', 'gps.bus_no')
                ->whereNotNull('gps.bus_no')->whereNull('b.id')
                ->orderBy('gps.id')->pluck('gps.id')->map(fn ($value) => (string) $value)->all(),
            'stock_movements_missing_inventory_item' => DB::table('stock_movements as sm')
                ->leftJoin('inventory_items as ii', 'ii.id', '=', 'sm.inventory_item_id')
                ->where(function (Builder $query): void {
                    $query->whereNotNull('sm.inventory_item_id')->whereNull('ii.id')
                        ->orWhere(function (Builder $legacy): void {
                            $legacy->whereNull('sm.inventory_item_id')->whereNotNull('sm.item_code')
                                ->whereNotExists(fn (Builder $items) => $items->selectRaw('1')->from('inventory_items')
                                    ->whereColumn('inventory_items.item_code', 'sm.item_code'));
                        });
                })->orderBy('sm.id')->pluck('sm.id')->map(fn ($value) => (string) $value)->all(),
            'trip_schedules_missing_route' => DB::table('trip_schedules as ts')
                ->leftJoin('shuttle_routes as sr', 'sr.id', '=', 'ts.shuttle_route_id')
                ->whereNull('sr.id')->orderBy('ts.trip_code')->pluck('ts.trip_code')->map(fn ($value) => (string) $value)->all(),
            'trip_assignments_missing_parent' => DB::table('trip_assignments as ta')
                ->leftJoin('trip_schedules as ts', 'ts.id', '=', 'ta.trip_schedule_id')
                ->leftJoin('driver_attendances as da', 'da.id', '=', 'ta.driver_attendance_id')
                ->leftJoin('buses as b', 'b.id', '=', 'ta.bus_id')
                ->where(fn (Builder $query) => $query->whereNull('ts.id')->orWhereNull('da.id')->orWhereNull('b.id'))
                ->orderBy('ta.id')->pluck('ta.id')->map(fn ($value) => (string) $value)->all(),
            'driver_attendances_missing_driver' => DB::table('driver_attendances as da')
                ->leftJoin('drivers as d', 'd.driver_id', '=', 'da.driver_id')
                ->whereNotNull('da.driver_id')->whereNull('d.id')
                ->orderBy('da.id')->pluck('da.id')->map(fn ($value) => (string) $value)->all(),
            'mechanic_attendances_missing_mechanic' => DB::table('mechanic_attendances as ma')
                ->leftJoin('mechanics as m', 'm.mechanic_id', '=', 'ma.mechanic_id')
                ->whereNotNull('ma.mechanic_id')->whereNull('m.id')
                ->orderBy('ma.id')->pluck('ma.id')->map(fn ($value) => (string) $value)->all(),
            'daily_driver_reports_missing_parent' => DB::table('daily_driver_reports as ddr')
                ->leftJoin('trip_schedules as ts', 'ts.id', '=', 'ddr.trip_schedule_id')
                ->leftJoin('trip_assignments as ta', 'ta.id', '=', 'ddr.trip_assignment_id')
                ->leftJoin('buses as b', 'b.id', '=', 'ddr.bus_id')
                ->leftJoin('drivers as d', 'd.driver_id', '=', 'ddr.driver_id')
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $q) => $q->whereNotNull('ddr.trip_schedule_id')->whereNull('ts.id'))
                    ->orWhere(fn (Builder $q) => $q->whereNotNull('ddr.trip_assignment_id')->whereNull('ta.id'))
                    ->orWhere(fn (Builder $q) => $q->whereNotNull('ddr.bus_id')->whereNull('b.id'))
                    ->orWhere(fn (Builder $q) => $q->whereNotNull('ddr.driver_id')->whereNull('d.id')))
                ->orderBy('ddr.ddr_no')->pluck('ddr.ddr_no')->map(fn ($value) => (string) $value)->all(),
            'incidents_missing_source' => DB::table('incidents as i')
                ->leftJoin('trip_schedules as ts', 'ts.id', '=', 'i.trip_schedule_id')
                ->leftJoin('trip_assignments as ta', 'ta.id', '=', 'i.trip_assignment_id')
                ->leftJoin('buses as b', 'b.id', '=', 'i.bus_id')
                ->leftJoin('drivers as d', 'd.driver_id', '=', 'i.driver_id')
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $q) => $q->whereNotNull('i.trip_schedule_id')->whereNull('ts.id'))
                    ->orWhere(fn (Builder $q) => $q->whereNotNull('i.trip_assignment_id')->whereNull('ta.id'))
                    ->orWhere(fn (Builder $q) => $q->whereNotNull('i.bus_id')->whereNull('b.id'))
                    ->orWhere(fn (Builder $q) => $q->whereNotNull('i.driver_id')->whereNull('d.id')))
                ->orderBy('i.incident_no')->pluck('i.incident_no')->map(fn ($value) => (string) $value)->all(),
        ];
    }

    private function sampleQuery(string $table): Builder
    {
        $rule = self::PREFIXES[$table];

        return DB::table($table)->where(function (Builder $query) use ($rule): void {
            $this->whereSample($query, $rule['column'], $rule['values']);
            if ($rule['column'] === 'item_code') {
                $query->orWhereIn('source', ['demo', 'simulated']);
            }
        });
    }

    private function sampleDailyDriverReportQuery(): Builder
    {
        return DB::table('daily_driver_reports')->where(function (Builder $query): void {
            $this->whereSample($query, 'ddr_no', self::PREFIXES['daily_driver_reports']['values']);
            $query->orWhere(function (Builder $trip): void {
                $this->whereSample($trip, 'trip_ticket', self::PREFIXES['trip_schedules']['values']);
            });
        });
    }

    private function sampleBusQuery(): Builder
    {
        return DB::table('buses')->where(function (Builder $query): void {
            $query->whereIn('bus_no', [
                'GCT-201', 'GCT-202', 'GCT-203', 'GCT-204',
                'GCT-205', 'GCT-206', 'GCT-207', 'GCT-208',
            ])->orWhere('bus_no', 'like', 'DEMO-BUS-%');
        });
    }

    private function whereSample(Builder $query, string $column, array $prefixes): void
    {
        foreach ($prefixes as $index => $prefix) {
            $method = $index === 0 ? 'where' : 'orWhere';
            $query->{$method}($column, 'like', $prefix.'%');
        }
    }

    private function whereNotSample(Builder $query, string $column, array $prefixes): void
    {
        $query->where(function (Builder $notSample) use ($column, $prefixes): void {
            foreach ($prefixes as $prefix) {
                $notSample->where($column, 'not like', $prefix.'%');
            }
        });
    }

    private function deletableScheduleIds(): Collection
    {
        return $this->sampleQuery('trip_schedules')
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('daily_driver_reports')
                ->whereColumn('daily_driver_reports.trip_schedule_id', 'trip_schedules.id'))
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('incidents')
                ->whereColumn('incidents.trip_schedule_id', 'trip_schedules.id'))
            ->pluck('id');
    }

    /** @return array<string, array<int, string>> */
    private function protectedMasters(): array
    {
        return [
            'buses' => $this->sampleBusQuery()->get(['id', 'bus_no'])->filter(fn (object $bus) => $this->busHasGenuineReference($bus))->pluck('bus_no')->all(),
            'drivers' => $this->sampleQuery('drivers')->get(['id', 'driver_id'])->filter(fn (object $driver) => $this->driverHasReference((string) $driver->driver_id))->pluck('driver_id')->all(),
            'routes' => $this->sampleQuery('shuttle_routes')->get(['id', 'route_code'])->filter(fn (object $route) => DB::table('trip_schedules')->where('shuttle_route_id', $route->id)->where(function (Builder $q): void {
                $this->whereNotSample($q, 'trip_code', self::PREFIXES['trip_schedules']['values']);
            })->exists())->pluck('route_code')->all(),
            'inventory_items' => $this->sampleQuery('inventory_items')->get(['id', 'item_code', 'item_name'])->filter(fn (object $item) => $this->inventoryItemHasGenuineReference($item))->pluck('item_code')->all(),
        ];
    }

    private function busHasGenuineReference(object $bus): bool
    {
        $busNo = (string) $bus->bus_no;
        $busId = (int) $bus->id;

        return DB::table('job_orders')->where('bus_no', $busNo)->where(function (Builder $q): void {
            $this->whereNotSample($q, 'job_order_no', self::PREFIXES['job_orders']['values']);
        })->exists()
            || DB::table('purchase_requests')->where('bus_no', $busNo)->where(function (Builder $q): void {
                $this->whereNotSample($q, 'pr_no', self::PREFIXES['purchase_requests']['values']);
            })->exists()
            || DB::table('fuel_reports')->where('bus_no', $busNo)->exists()
            || DB::table('pms_schedules')->where('bus_no', $busNo)->exists()
            || DB::table('batch_uploads')->where('bus_no', $busNo)->exists()
            || DB::table('gps_trip_records')->where('bus_no', $busNo)->exists()
            || DB::table('driver_attendances')->where('bus_assignment', $busNo)->where(function (Builder $q): void {
                $this->whereNotSample($q, 'driver_id', self::PREFIXES['drivers']['values']);
            })->exists()
            || DB::table('trip_assignments as ta')->join('trip_schedules as ts', 'ts.id', '=', 'ta.trip_schedule_id')->where('ta.bus_id', $busId)->where(function (Builder $q): void {
                $this->whereNotSample($q, 'ts.trip_code', self::PREFIXES['trip_schedules']['values']);
            })->exists()
            || DB::table('daily_driver_reports')->where('bus_id', $busId)->where(function (Builder $q): void {
                $this->whereNotSample($q, 'ddr_no', self::PREFIXES['daily_driver_reports']['values']);
            })->exists()
            || DB::table('incidents')->where('bus_id', $busId)->where(function (Builder $q): void {
                $this->whereNotSample($q, 'incident_no', self::PREFIXES['incidents']['values']);
            })->exists()
            || DB::table('incident_replacements as ir')->join('incidents as i', 'i.id', '=', 'ir.incident_id')->where(fn (Builder $q) => $q->where('ir.original_bus_id', $busId)->orWhere('ir.replacement_bus_id', $busId))->where(function (Builder $q): void {
                $this->whereNotSample($q, 'i.incident_no', self::PREFIXES['incidents']['values']);
            })->exists();
    }

    private function driverHasReference(string $driverId): bool
    {
        return DB::table('driver_attendances as da')->leftJoin('trip_assignments as ta', 'ta.driver_attendance_id', '=', 'da.id')->leftJoin('trip_schedules as ts', 'ts.id', '=', 'ta.trip_schedule_id')->where('da.driver_id', $driverId)->where(fn (Builder $q) => $q->whereNull('ta.id')->orWhere(function (Builder $genuine): void {
            $this->whereNotSample($genuine, 'ts.trip_code', self::PREFIXES['trip_schedules']['values']);
        }))->exists()
            || DB::table('daily_driver_reports')->where('driver_id', $driverId)->where(function (Builder $q): void {
                $this->whereNotSample($q, 'ddr_no', self::PREFIXES['daily_driver_reports']['values']);
            })->exists()
            || DB::table('incidents')->where('driver_id', $driverId)->where(function (Builder $q): void {
                $this->whereNotSample($q, 'incident_no', self::PREFIXES['incidents']['values']);
            })->exists();
    }

    private function inventoryItemHasGenuineReference(object $item): bool
    {
        return DB::table('stock_movements')->where('inventory_item_id', $item->id)->whereNotIn('source', ['demo', 'simulated'])->exists()
            || DB::table('inventory_issuance_items')->where('inventory_item_id', $item->id)->exists()
            || DB::table('purchase_requests')->where('source_inventory_item_id', $item->id)->where(function (Builder $q): void {
                $this->whereNotSample($q, 'pr_no', self::PREFIXES['purchase_requests']['values']);
            })->exists()
            || DB::table('scheduled_purchases')->where('item', $item->item_name)->exists()
            || DB::table('purchase_orders')->where(function (Builder $query) use ($item): void {
                $query->where('items', 'like', '%'.$item->item_code.'%')
                    ->orWhere('items', 'like', '%'.$item->item_name.'%');
            })->where(function (Builder $query): void {
                $this->whereNotSample($query, 'po_no', self::PREFIXES['purchase_orders']['values']);
            })->exists();
    }

    /** @return array<string, int> */
    private function deleteUnreferencedMasters(): array
    {
        $counts = [];
        $deletableItemIds = $this->sampleQuery('inventory_items')
            ->get(['id', 'item_code', 'item_name'])
            ->reject(fn (object $item) => $this->inventoryItemHasAnyReference($item))
            ->pluck('id');
        $counts['inventory_items'] = $deletableItemIds->isEmpty()
            ? 0
            : DB::table('inventory_items')->whereIn('id', $deletableItemIds)->delete();
        $counts['shuttle_routes'] = $this->sampleQuery('shuttle_routes')
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('trip_schedules')->whereColumn('trip_schedules.shuttle_route_id', 'shuttle_routes.id'))
            ->delete();
        $counts['drivers'] = $this->sampleQuery('drivers')
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('driver_attendances')->whereColumn('driver_attendances.driver_id', 'drivers.driver_id'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('daily_driver_reports')->whereColumn('daily_driver_reports.driver_id', 'drivers.driver_id'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('incidents')->whereColumn('incidents.driver_id', 'drivers.driver_id'))
            ->delete();
        $counts['buses'] = $this->sampleBusQuery()
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('trip_assignments')->whereColumn('trip_assignments.bus_id', 'buses.id'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('daily_driver_reports')->whereColumn('daily_driver_reports.bus_id', 'buses.id'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('incidents')->whereColumn('incidents.bus_id', 'buses.id'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('incident_replacements')->whereColumn('incident_replacements.original_bus_id', 'buses.id')->orWhereColumn('incident_replacements.replacement_bus_id', 'buses.id'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('job_orders')->whereColumn('job_orders.bus_no', 'buses.bus_no'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('purchase_requests')->whereColumn('purchase_requests.bus_no', 'buses.bus_no'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('fuel_reports')->whereColumn('fuel_reports.bus_no', 'buses.bus_no'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('pms_schedules')->whereColumn('pms_schedules.bus_no', 'buses.bus_no'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('batch_uploads')->whereColumn('batch_uploads.bus_no', 'buses.bus_no'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('gps_trip_records')->whereColumn('gps_trip_records.bus_no', 'buses.bus_no'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('driver_attendances')->whereColumn('driver_attendances.bus_assignment', 'buses.bus_no'))
            ->delete();

        return $counts;
    }

    private function inventoryItemHasAnyReference(object $item): bool
    {
        return DB::table('stock_movements')->where('inventory_item_id', $item->id)->exists()
            || DB::table('inventory_issuance_items')->where('inventory_item_id', $item->id)->exists()
            || DB::table('purchase_requests')->where('source_inventory_item_id', $item->id)->exists()
            || DB::table('scheduled_purchases')->where('item', $item->item_name)->exists()
            || DB::table('purchase_orders')->where(function (Builder $query) use ($item): void {
                $query->where('items', 'like', '%'.$item->item_code.'%')
                    ->orWhere('items', 'like', '%'.$item->item_name.'%');
            })->exists();
    }
}
