<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TopbarNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationCenterController extends Controller
{
    public function index(Request $request)
    {
        return view('Admin.System_Monitoring.notifications', $this->data($request));
    }

    public function data(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $readAt = DB::table('topbar_read_states')
            ->where('user_id', $userId)
            ->value('notifications_read_at');
        $individuallyReadIds = $this->individuallyReadIds($userId);

        $query = TopbarNotification::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('message', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('entity', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('record_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('module') && $request->input('module') !== 'all') {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('type') && $request->input('type') !== 'all') {
            $this->applyTypeFilter($query, (string) $request->input('type'));
        }

        if ($request->input('state') === 'unread') {
            $this->applyUnreadFilter($query, $readAt, $individuallyReadIds);
        } elseif ($request->input('state') === 'read') {
            $this->applyReadFilter($query, $readAt, $individuallyReadIds);
        }

        $notifications = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $notifications->getCollection()->transform(function (TopbarNotification $notification) use ($readAt, $individuallyReadIds) {
            $type = $this->typeFor($notification);

            return [
                'id' => $notification->id,
                'title' => $this->titleFor($notification),
                'message' => $notification->message,
                'module' => $notification->module ?: 'System',
                'type' => $type,
                'reference' => $notification->record_id ?: '—',
                'entity' => $notification->entity,
                'action' => $notification->action,
                'date' => $notification->created_at->format('M d, Y'),
                'time' => $notification->created_at->format('g:i A'),
                'unread' => $this->isUnread($notification, $readAt, $individuallyReadIds),
                'icon' => $this->iconFor($notification),
            ];
        });

        $all = TopbarNotification::query();
        $totalNotifications = (clone $all)->count();
        $unreadQuery = clone $all;
        $this->applyUnreadFilter($unreadQuery, $readAt, $individuallyReadIds);
        $unreadNotifications = $unreadQuery->count();
        $criticalAlerts = (clone $all)
            ->where(function ($builder): void {
                $builder
                    ->where('message', 'like', '%critical%')
                    ->orWhere('message', 'like', '%failed%')
                    ->orWhere('action', 'like', '%reject%')
                    ->orWhere('action', 'like', '%security%');
            })
            ->count();
        $systemUpdates = max(0, $totalNotifications - $criticalAlerts);

        $modules = TopbarNotification::query()
            ->whereNotNull('module')
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        return compact(
            'notifications',
            'unreadNotifications',
            'criticalAlerts',
            'systemUpdates',
            'totalNotifications',
            'modules'
        );
    }

    public function markRead(Request $request, TopbarNotification $notification): JsonResponse
    {
        if (! Schema::hasTable('topbar_notification_reads')) {
            return response()->json([
                'success' => false,
                'message' => 'Notification read tracking is not available until the latest migration is applied.',
            ], 503);
        }

        DB::table('topbar_notification_reads')->updateOrInsert(
            [
                'user_id' => $request->user()->id,
                'notification_id' => $notification->id,
            ],
            [
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    private function individuallyReadIds(int $userId): Collection
    {
        if (! Schema::hasTable('topbar_notification_reads')) {
            return collect();
        }

        return DB::table('topbar_notification_reads')
            ->where('user_id', $userId)
            ->pluck('notification_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function applyUnreadFilter($query, mixed $readAt, Collection $individuallyReadIds): void
    {
        if ($readAt) {
            $query->where('created_at', '>', $readAt);
        }

        if ($individuallyReadIds->isNotEmpty()) {
            $query->whereNotIn('id', $individuallyReadIds->all());
        }
    }

    private function applyReadFilter($query, mixed $readAt, Collection $individuallyReadIds): void
    {
        if (! $readAt && $individuallyReadIds->isEmpty()) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($builder) use ($readAt, $individuallyReadIds): void {
            if ($readAt) {
                $builder->where('created_at', '<=', $readAt);
            }

            if ($individuallyReadIds->isNotEmpty()) {
                if ($readAt) {
                    $builder->orWhereIn('id', $individuallyReadIds->all());
                } else {
                    $builder->whereIn('id', $individuallyReadIds->all());
                }
            }
        });
    }

    private function isUnread(
        TopbarNotification $notification,
        mixed $readAt,
        Collection $individuallyReadIds
    ): bool {
        $coveredByMarkAll = $readAt && $notification->created_at->lte($readAt);
        $markedIndividually = $individuallyReadIds->contains((int) $notification->id);

        return ! $coveredByMarkAll && ! $markedIndividually;
    }

    private function typeFor(TopbarNotification $notification): string
    {
        $text = strtolower(trim($notification->action . ' ' . $notification->message));

        if (Str::contains($text, ['critical', 'failed', 'security', 'rejected'])) {
            return 'Critical';
        }

        if (Str::contains($text, ['warning', 'low stock', 'on hold', 'overdue', 'due soon'])) {
            return 'Warning';
        }

        if (Str::contains($text, ['completed', 'approved', 'received', 'success', 'created'])) {
            return 'Success';
        }

        return 'Update';
    }

    private function applyTypeFilter($query, string $type): void
    {
        $type = strtolower($type);

        if ($type === 'critical') {
            $query->where(function ($builder): void {
                $builder->where('message', 'like', '%critical%')
                    ->orWhere('message', 'like', '%failed%')
                    ->orWhere('action', 'like', '%reject%')
                    ->orWhere('action', 'like', '%security%');
            });
        } elseif ($type === 'warning') {
            $query->where(function ($builder): void {
                $builder->where('message', 'like', '%warning%')
                    ->orWhere('message', 'like', '%low stock%')
                    ->orWhere('message', 'like', '%on hold%')
                    ->orWhere('message', 'like', '%overdue%')
                    ->orWhere('message', 'like', '%due soon%');
            });
        } elseif ($type === 'success') {
            $query->where(function ($builder): void {
                $builder->where('action', 'like', '%completed%')
                    ->orWhere('action', 'like', '%approved%')
                    ->orWhere('action', 'like', '%received%')
                    ->orWhere('action', 'like', '%created%')
                    ->orWhere('message', 'like', '%success%');
            });
        } elseif ($type === 'update') {
            $query->where(function ($builder): void {
                $builder->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%critical%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%failed%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%security%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%rejected%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%warning%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%low stock%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%on hold%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%overdue%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%due soon%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%completed%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%approved%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%received%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%success%'])
                    ->whereRaw('LOWER(CONCAT(COALESCE(action, \'\'), \' \', COALESCE(message, \'\'))) NOT LIKE ?', ['%created%']);
            });
        }
    }

    private function titleFor(TopbarNotification $notification): string
    {
        $entity = Str::of((string) ($notification->entity ?: 'System'))->headline();
        $action = Str::of((string) ($notification->action ?: 'Updated'))->headline();

        return trim($entity . ' ' . $action);
    }

    private function iconFor(TopbarNotification $notification): string
    {
        return match ($notification->entity) {
            'Inventory' => 'fa-box-open',
            'JobOrder' => 'fa-screwdriver-wrench',
            'PurchaseOrder' => 'fa-cart-shopping',
            'PurchaseRequest', 'MaintenanceRequest' => 'fa-file-circle-check',
            'BatchUpload' => 'fa-file-circle-check',
            'Bus' => 'fa-bus',
            'TripSchedule' => 'fa-route',
            'Attendance' => 'fa-user-check',
            default => 'fa-bell',
        };
    }
}
