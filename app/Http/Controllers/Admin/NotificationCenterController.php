<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TopbarNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationCenterController extends Controller
{
    public function index(Request $request)
    {
        return view('Admin.System_Monitoring.notifications', $this->data($request));
    }

    public function data(Request $request): array
    {
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

        $readAt = DB::table('topbar_read_states')
            ->where('user_id', $request->user()->id)
            ->value('notifications_read_at');

        if ($request->input('state') === 'unread') {
            if ($readAt) {
                $query->where('created_at', '>', $readAt);
            }
        } elseif ($request->input('state') === 'read') {
            if ($readAt) {
                $query->where('created_at', '<=', $readAt);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $notifications = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $notifications->getCollection()->transform(function (TopbarNotification $notification) use ($readAt) {
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
                'unread' => ! $readAt || $notification->created_at->gt($readAt),
                'icon' => $this->iconFor($notification),
            ];
        });

        $all = TopbarNotification::query();
        $totalNotifications = (clone $all)->count();
        $unreadNotifications = (clone $all)
            ->when($readAt, fn ($builder) => $builder->where('created_at', '>', $readAt))
            ->count();
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
                    ->orWhere('message', 'like', '%overdue%');
            });
        } elseif ($type === 'success') {
            $query->where(function ($builder): void {
                $builder->where('action', 'like', '%completed%')
                    ->orWhere('action', 'like', '%approved%')
                    ->orWhere('action', 'like', '%received%')
                    ->orWhere('action', 'like', '%created%');
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
