<?php

namespace App\Services;

use App\Models\Admin\User;
use App\Models\TopbarNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class TopbarSummaryService
{
    public function summary(User $user): array
    {
        $readAt = Schema::hasTable('topbar_read_states')
            ? DB::table('topbar_read_states')
                ->where('user_id', $user->id)
                ->value('notifications_read_at')
            : null;

        $notificationQuery = $this->notificationQueryFor($user);

        $notifications = (clone $notificationQuery)
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (TopbarNotification $notification) =>
                $this->formatNotification($notification, $readAt)
            )
            ->values();

        $unreadQuery = clone $notificationQuery;

        if ($readAt) {
            $unreadQuery->where('created_at', '>', $readAt);
        }

        return [
            'unread_count' => $unreadQuery->count(),
            'notifications' => $notifications,
            'pending_actions' => $this->pendingActions($user),
            'recent_activity' => $notifications
                ->take(8)
                ->map(fn (array $item) => array_merge($item, [
                    'unread' => false,
                ]))
                ->values(),
        ];
    }

    private function notificationQueryFor(User $user)
    {
        $query = TopbarNotification::query();
        $department = $this->normalizedDepartment($user);

        if (in_array($department, ['admin', 'administration'], true)) {
            return $query;
        }

        $module = match ($department) {
            'maintenance' => 'Maintenance',
            'operation', 'operations' => 'Operation',
            'warehouse' => 'Warehouse',
            'purchase', 'purchasing' => 'Purchase',
            default => null,
        };

        if ($module) {
            $query->where(function ($builder) use ($module) {
                $builder
                    ->where('module', $module)
                    ->orWhereNull('module')
                    ->orWhere('module', 'System');
            });
        }

        return $query;
    }

    private function formatNotification(
        TopbarNotification $notification,
        mixed $readAt
    ): array {
        return [
            'id' => $notification->id,
            'module' => $notification->module ?: 'System',
            'action' => $notification->action,
            'message' => $notification->message,
            'url' => $this->notificationUrl($notification),
            'unread' => ! $readAt || $notification->created_at->gt($readAt),
            'time' => $notification->created_at->diffForHumans(),
        ];
    }

    private function pendingActions(User $user): array
    {
        $department = $this->normalizedDepartment($user);

        $actionsByDepartment = [
            'maintenance' => [
                ['job_orders', 'status', ['On Going'], 'Job orders in progress', 'job-orders', 'fa-screwdriver-wrench'],
                ['job_orders', 'status', ['On Hold'], 'Job orders on hold', 'job-orders', 'fa-pause'],
                ['purchase_requests', 'status', ['Rejected'], 'Purchase requests needing revision', 'purchase-requests', 'fa-rotate'],
                ['purchase_requests', 'status', ['Submitted'], 'Purchase requests pending review', 'purchase-requests', 'fa-file-circle-question'],
            ],
            'warehouse' => [
                ['purchase_requests', 'status', ['Approved'], 'Approved part requests', 'part-requests', 'fa-box-open'],
                ['inventory_items', null, [], 'Low-stock inventory items', 'inventory', 'fa-triangle-exclamation'],
            ],
            'purchase' => [
                ['purchase_requests', 'status', ['For Purchase'], 'Requests ready for purchase', 'maintenance-requests', 'fa-cart-shopping'],
                ['scheduled_purchases', 'status', ['Active'], 'Active scheduled purchases', 'scheduled-purchase', 'fa-calendar-check'],
            ],
            'purchasing' => [
                ['purchase_requests', 'status', ['For Purchase'], 'Requests ready for purchase', 'maintenance-requests', 'fa-cart-shopping'],
                ['scheduled_purchases', 'status', ['Active'], 'Active scheduled purchases', 'scheduled-purchase', 'fa-calendar-check'],
            ],
            'operation' => [
                ['trip_schedules', 'assignment_status', ['Unassigned'], 'Trips awaiting assignment', 'trip-schedule', 'fa-bus-simple'],
                ['buses', 'status', ['Under Maintenance'], 'Buses under maintenance', 'bus-master-list', 'fa-wrench'],
            ],
            'operations' => [
                ['trip_schedules', 'assignment_status', ['Unassigned'], 'Trips awaiting assignment', 'trip-schedule', 'fa-bus-simple'],
                ['buses', 'status', ['Under Maintenance'], 'Buses under maintenance', 'bus-master-list', 'fa-wrench'],
            ],
            'admin' => [
                ['users', 'status', ['Pending'], 'Pending user accounts', 'admin.users', 'fa-user-clock'],
            ],
            'administration' => [
                ['users', 'status', ['Pending'], 'Pending user accounts', 'admin.users', 'fa-user-clock'],
            ],
        ];

        return collect($actionsByDepartment[$department] ?? [])
            ->filter(fn (array $definition) =>
                Schema::hasTable($definition[0])
                && Route::has($definition[4])
            )
            ->map(function (array $definition): array {
                [$table, $column, $statuses, $label, $route, $icon] = $definition;

                $query = DB::table($table);

                if ($table === 'inventory_items') {
                    $query->whereColumn(
                        'quantity_available',
                        '<=',
                        'reorder_level'
                    );
                } elseif ($column && Schema::hasColumn($table, $column)) {
                    $query->whereIn($column, $statuses);
                }

                return [
                    'label' => $label,
                    'count' => $query->count(),
                    'url' => route($route, [], false),
                    'icon' => $icon,
                ];
            })
            ->filter(fn (array $action) => $action['count'] > 0)
            ->values()
            ->all();
    }

    private function normalizedDepartment(User $user): string
    {
        return strtolower(trim(str_replace(
            ['_', '-'],
            ' ',
            (string) $user->department
        )));
    }

    private function notificationUrl(TopbarNotification $notification): ?string
    {
        $route = match ($notification->entity) {
            'Attendance' => str_contains(
                strtolower($notification->message),
                'mechanic'
            ) ? 'mechanic-attendance' : 'driver-attendance',
            'Inventory' => 'inventory',
            'JobOrder' => 'job-orders',
            'PurchaseOrder' => 'purchase-orders',
            'PurchaseRequest' => $notification->module === 'Warehouse'
                ? 'part-requests'
                : 'purchase-requests',
            'MaintenanceRequest' => 'maintenance-requests',
            'BatchUpload' => 'batch-file-processing',
            'Bus' => 'bus-master-list',
            'TripSchedule' => 'trip-schedule',
            default => null,
        };

        return $route && Route::has($route)
            ? route($route, [], false)
            : null;
    }
}
