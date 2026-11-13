<?php

namespace App\Services;

use App\Models\Admin\User;
use App\Models\TopbarNotification;
use Illuminate\Support\Collection;
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

        $notificationModels = (clone $notificationQuery)
            ->latest()
            ->limit(5)
            ->get();
        $individuallyReadIds = $this->individuallyReadIds(
            (int) $user->id,
            $notificationModels->modelKeys()
        );
        $notifications = $notificationModels
            ->map(fn (TopbarNotification $notification) => $this->formatNotification($notification, $readAt, $individuallyReadIds)
            )
            ->values();

        $unreadQuery = clone $notificationQuery;

        if ($readAt) {
            $unreadQuery->where('created_at', '>', $readAt);
        }

        if (Schema::hasTable('topbar_notification_reads')) {
            $unreadQuery->whereNotExists(function ($query) use ($user) {
                $query->selectRaw('1')
                    ->from('topbar_notification_reads')
                    ->whereColumn('topbar_notification_reads.notification_id', 'topbar_notifications.id')
                    ->where('topbar_notification_reads.user_id', $user->id);
            });
        }

        return [
            'unread_count' => $unreadQuery->count(),
            'notifications' => $notifications,
            'pending_actions' => $this->pendingActions($user),
            'recent_activity' => $notifications
                ->map(fn (array $item) => array_merge($item, [
                    'unread' => false,
                ]))
                ->values(),
        ];
    }

    /**
     * Accessible to every authenticated department using the same module
     * visibility rules as the topbar preview. No cross-department leakage.
     */
    public function notificationHistory(User $user, int $page = 1, bool $unreadOnly = false): array
    {
        $page = max(1, min($page, 10000));
        $readAt = Schema::hasTable('topbar_read_states')
            ? DB::table('topbar_read_states')->where('user_id', $user->id)->value('notifications_read_at')
            : null;
        $query = $this->notificationQueryFor($user);

        if ($unreadOnly) {
            if ($readAt) {
                $query->where('created_at', '>', $readAt);
            }
            if (Schema::hasTable('topbar_notification_reads')) {
                $query->whereNotExists(function ($builder) use ($user) {
                    $builder->selectRaw('1')
                        ->from('topbar_notification_reads')
                        ->whereColumn('topbar_notification_reads.notification_id', 'topbar_notifications.id')
                        ->where('topbar_notification_reads.user_id', $user->id);
                });
            }
        }

        $records = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(20, ['*'], 'page', $page);
        $ids = $records->getCollection()->modelKeys();
        $individualReads = $this->individuallyReadIds((int) $user->id, $ids);

        return [
            'items' => $records->getCollection()
                ->map(fn (TopbarNotification $notification) => $this->formatNotification($notification, $readAt, $individualReads))
                ->values(),
            'page' => $records->currentPage(),
            'last_page' => $records->lastPage(),
            'total' => $records->total(),
        ];
    }

    private function professionalMessage(string $message): string
    {
        // Normalize older scheduler notifications without rewriting audit data.
        if (preg_match('/^(\\d+) trip status\\(es\\) advanced by scheduled times\\.$/i', trim($message), $matches)) {
            $count = (int) $matches[1];
            return $count === 1
                ? 'The status of 1 trip was automatically updated based on its schedule.'
                : "The statuses of {$count} trips were automatically updated based on their schedules.";
        }

        return $message;
    }

    private function individuallyReadIds(int $userId, array $notificationIds): Collection
    {
        if (! Schema::hasTable('topbar_notification_reads') || $notificationIds === []) {
            return collect();
        }

        return DB::table('topbar_notification_reads')
            ->where('user_id', $userId)
            ->whereIn('notification_id', $notificationIds)
            ->pluck('notification_id')
            ->map(fn ($id) => (int) $id)
            ->values();
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

        if (! $module) {
            // Unknown or unassigned departments must never receive every
            // department's notifications by falling through an unscoped query.
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($builder) use ($module) {
            $builder
                ->where('module', $module)
                ->orWhereNull('module')
                ->orWhere('module', 'System');
        });
    }

    private function formatNotification(
        TopbarNotification $notification,
        mixed $readAt,
        Collection $individuallyReadIds
    ): array {
        $coveredByMarkAll = $readAt && $notification->created_at->lte($readAt);
        $markedIndividually = $individuallyReadIds->contains((int) $notification->id);

        return [
            'id' => $notification->id,
            'module' => $notification->module ?: 'System',
            'action' => $notification->action,
            'message' => $this->professionalMessage((string) $notification->message),
            'url' => $this->notificationUrl($notification),
            'unread' => ! $coveredByMarkAll && ! $markedIndividually,
            'time' => $notification->created_at->diffForHumans(),
        ];
    }

    private function pendingActions(User $user): array
    {
        $department = $this->normalizedDepartment($user);

        $actionsByDepartment = [
            'maintenance' => [
                ['maintenance_referrals', 'status', ['Pending'], 'Pending breakdown referrals', 'maintenance-referrals', 'fa-arrow-right-arrow-left'],
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
            ],
            'purchasing' => [
                ['purchase_requests', 'status', ['For Purchase'], 'Requests ready for purchase', 'maintenance-requests', 'fa-cart-shopping'],
            ],
            'operation' => [
                ['trip_schedules', 'assignment_status', ['Unassigned'], 'Trips awaiting assignment', 'trip-schedule', 'fa-bus-simple'],
                ['buses', 'status', ['Under Maintenance'], 'Buses under maintenance', 'bus-master-list', 'fa-wrench'],
                ['incidents', 'status', ['Reported', 'Monitoring', 'Responding'], 'Active incidents', 'incidents', 'fa-triangle-exclamation'],
            ],
            'operations' => [
                ['trip_schedules', 'assignment_status', ['Unassigned'], 'Trips awaiting assignment', 'trip-schedule', 'fa-bus-simple'],
                ['buses', 'status', ['Under Maintenance'], 'Buses under maintenance', 'bus-master-list', 'fa-wrench'],
                ['incidents', 'status', ['Reported', 'Monitoring', 'Responding'], 'Active incidents', 'incidents', 'fa-triangle-exclamation'],
            ],
            'admin' => [
                ['users', 'status', ['Pending'], 'Pending user accounts', 'admin.users', 'fa-user-clock'],
            ],
            'administration' => [
                ['users', 'status', ['Pending'], 'Pending user accounts', 'admin.users', 'fa-user-clock'],
            ],
        ];

        // Review decisions are restricted to the Maintenance Head. They must
        // not be presented as work already approved and in progress.
        if ($department === 'maintenance'
            && strtolower(trim((string) $user->role)) === 'head'
            && $user->hasSystemPermission('maintenance', 'approve')) {
            array_unshift($actionsByDepartment['maintenance'],
                ['job_orders', 'review_status', ['For Review'], 'Job orders awaiting Head review', 'job-orders', 'fa-file-circle-question']
            );
        }

        return collect($actionsByDepartment[$department] ?? [])
            ->filter(function (array $definition): bool {
                [$table, $column, , , $route] = $definition;

                if (! Schema::hasTable($table) || ! Route::has($route)) {
                    return false;
                }

                if ($table === 'inventory_items') {
                    return Schema::hasColumn($table, 'quantity_available')
                        && Schema::hasColumn($table, 'reorder_level');
                }

                // Missing status columns must not turn a filtered pending
                // action into an incorrect count of every record.
                return $column !== null && Schema::hasColumn($table, $column);
            })
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

                // A draft JO may retain an operational status from legacy data.
                // It is not active Maintenance work until Head approval.
                if ($table === 'job_orders' && $column === 'status') {
                    if (! Schema::hasColumn('job_orders', 'review_status')) {
                        return null;
                    }
                    $query->where('review_status', 'Approved');
                }

                return [
                    'label' => $label,
                    'count' => $query->count(),
                    'url' => route($route, [], false),
                    'icon' => $icon,
                ];
            })
            ->filter(fn (?array $action) => $action !== null && $action['count'] > 0)
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
            'MaintenanceReferral' => 'maintenance-referrals',
            'PurchaseOrder' => 'purchase-orders',
            'PurchaseRequest' => $notification->module === 'Warehouse'
                ? 'part-requests'
                : 'purchase-requests',
            'MaintenanceRequest' => 'maintenance-requests',
            'BatchUpload' => 'batch-file-processing',
            'Bus' => 'bus-master-list',
            'TripSchedule' => 'trip-schedule',
            'Incident' => 'incidents',
            default => null,
        };

        return $route && Route::has($route)
            ? route($route, [], false)
            : null;
    }
}
