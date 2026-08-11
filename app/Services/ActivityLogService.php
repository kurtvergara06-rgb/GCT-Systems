<?php

namespace App\Services;

use App\Models\Admin\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ActivityLogService
{
    public function record(
        Authenticatable $user,
        Request $request,
        string $activity,
        string $eventType,
        ?string $module = null,
        ?string $reference = null,
        ?string $details = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $user->getAuthIdentifier(),
            'user_name' => (string) ($user->name ?? 'System User'),
            'user_role' => $this->formatRole($user),
            'department' => $this->normalizeDepartment((string) ($user->department ?? '')),
            'activity' => $activity,
            'module' => $module ?: $this->inferModule($request),
            'reference' => $reference ?: $this->inferReference($request),
            'event_type' => $eventType,
            'details' => $details,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function recordRequest(Authenticatable $user, Request $request): ?ActivityLog
    {
        $routeName = (string) optional($request->route())->getName();

        if ($this->shouldIgnore($routeName, $request)) {
            return null;
        }

        $eventType = $this->inferEventType($routeName, $request->method());
        $activity = $this->humanizeRouteAction($routeName, $eventType);
        $module = $this->inferModule($request);
        $reference = $this->inferReference($request);

        return $this->record(
            $user,
            $request,
            $activity,
            $eventType,
            $module,
            $reference,
            $this->buildDetails($request, $activity)
        );
    }

    private function shouldIgnore(string $routeName, Request $request): bool
    {
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        return Str::startsWith($routeName, [
            'topbar.',
        ]);
    }

    private function inferEventType(string $routeName, string $method): string
    {
        $route = strtolower($routeName);

        if (Str::contains($route, ['login'])) {
            return 'Login';
        }

        if (Str::contains($route, ['logout'])) {
            return 'Logout';
        }

        if (Str::contains($route, ['approve'])) {
            return 'Approval';
        }

        if (Str::contains($route, ['reject'])) {
            return 'Rejected';
        }

        if (Str::contains($route, ['delete', 'destroy', 'remove'])) {
            return 'Deleted';
        }

        if (Str::contains($route, ['create', 'store', 'import', 'upload'])) {
            return 'Created';
        }

        if (Str::contains($route, ['receive'])) {
            return 'Received';
        }

        if (Str::contains($route, ['issue'])) {
            return 'Issued';
        }

        if (Str::contains($route, ['complete', 'finish'])) {
            return 'Completed';
        }

        if (Str::contains($route, ['assign'])) {
            return 'Assigned';
        }

        if (Str::contains($route, ['reset-password'])) {
            return 'Security';
        }

        return strtoupper($method) === 'POST' ? 'Created' : 'Updated';
    }

    private function humanizeRouteAction(string $routeName, string $eventType): string
    {
        if ($routeName === '') {
            return $eventType . ' system record';
        }

        $segments = collect(explode('.', $routeName))
            ->reject(fn (string $segment) => in_array($segment, ['admin', 'warehouse', 'operation'], true))
            ->values();

        $resource = $segments->first() ?: 'system record';
        $action = $segments->last() ?: $eventType;

        $resourceLabel = Str::of($resource)
            ->replace(['-', '_'], ' ')
            ->singular()
            ->title();

        $actionLabels = [
            'store' => 'Created',
            'create' => 'Created',
            'update' => 'Updated',
            'update-status' => 'Updated status of',
            'toggle-status' => 'Updated status of',
            'destroy' => 'Deleted',
            'approve' => 'Approved',
            'reject' => 'Rejected',
            'resubmit' => 'Resubmitted',
            'for-purchase' => 'Marked for purchase',
            'delivered' => 'Marked delivered',
            'issue' => 'Issued',
            'send-to-purchase' => 'Sent to Purchase',
            'finish' => 'Completed',
            'complete' => 'Completed',
            'create-po' => 'Created purchase order from',
            'reset-password' => 'Reset password for',
            'import' => 'Imported',
            'logout' => 'Logged out of',
        ];

        $actionLabel = $actionLabels[$action]
            ?? Str::of($action)->replace(['-', '_'], ' ')->title()->toString();

        return trim($actionLabel . ' ' . $resourceLabel);
    }

    private function inferModule(Request $request): string
    {
        $routeName = strtolower((string) optional($request->route())->getName());
        $path = strtolower($request->path());
        $haystack = $routeName . ' ' . $path;

        if (Str::contains($haystack, ['admin/', 'admin.'])) {
            return 'Admin';
        }

        if (Str::contains($haystack, [
            'warehouse/',
            'warehouse.',
            'inventory',
            'part-requests',
            'stock-movements',
            'incoming-deliveries',
        ])) {
            return 'Warehouse';
        }

        if (Str::contains($haystack, [
            'purchase/',
            'purchase-orders',
            'maintenance-requests',
            'inventory-restock',
            'scheduled-purchase',
        ])) {
            return 'Purchase';
        }

        if (Str::contains($haystack, [
            'operation/',
            'driver-',
            'mechanic-attendance',
            'trip-',
            'bus-master',
            'auto-scheduling',
            'routes',
        ])) {
            return 'Operation';
        }

        if (Str::contains($haystack, [
            'maintenance',
            'job-orders',
            'pms-',
            'fuel-reports',
            'mechanic-list',
            'purchase-requests',
        ])) {
            return 'Maintenance';
        }

        return $this->normalizeDepartment((string) optional($request->user())->department) ?: 'System';
    }

    private function inferReference(Request $request): ?string
    {
        $routeParameters = optional($request->route())->parameters() ?? [];

        foreach ($routeParameters as $parameter) {
            if ($parameter instanceof Model) {
                $reference = $this->referenceFromModel($parameter);
                if ($reference) {
                    return $reference;
                }
            }

            if (is_scalar($parameter) && filled((string) $parameter)) {
                return (string) $parameter;
            }
        }

        foreach ([
            'reference',
            'po_number',
            'purchase_order_number',
            'pr_number',
            'purchase_request_number',
            'job_order_number',
            'jo_number',
            'bus_number',
            'plate_number',
            'code',
            'id',
        ] as $key) {
            if ($request->filled($key)) {
                return (string) $request->input($key);
            }
        }

        return null;
    }

    private function referenceFromModel(Model $model): ?string
    {
        foreach ([
            'reference',
            'po_number',
            'purchase_order_number',
            'pr_number',
            'purchase_request_number',
            'job_order_number',
            'jo_number',
            'bus_number',
            'plate_number',
            'code',
            'employee_id',
            'driver_id',
            'mechanic_id',
            'id',
        ] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function buildDetails(Request $request, string $activity): string
    {
        $routeName = (string) optional($request->route())->getName();

        return trim(sprintf(
            '%s via %s.',
            $activity,
            $routeName !== '' ? $routeName : $request->path()
        ));
    }

    private function formatRole(Authenticatable $user): ?string
    {
        $department = $this->normalizeDepartment((string) ($user->department ?? ''));
        $role = strtolower(trim((string) ($user->role ?? '')));

        if ($department === 'Admin') {
            return $role === 'head' ? 'System Admin' : 'Admin Staff';
        }

        if ($department === '') {
            return $role !== '' ? Str::title($role) : null;
        }

        return trim($department . ' ' . Str::title($role ?: 'Staff'));
    }

    private function normalizeDepartment(string $department): string
    {
        $department = trim($department);

        if ($department === '') {
            return '';
        }

        return match (strtolower($department)) {
            'operations' => 'Operation',
            'purchasing' => 'Purchase',
            'administration' => 'Admin',
            default => Str::title($department),
        };
    }
}
