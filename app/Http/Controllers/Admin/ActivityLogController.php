<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        return view(
            'Admin.System_Monitoring.activity-logs',
            $this->data($request)
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $logs = $this->filteredLogs($request);

        return response()->streamDownload(function () use ($logs): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['User', 'Role', 'Activity', 'Module', 'Reference', 'Event Type', 'Date & Time'], escape: '');

            foreach ($logs->lazy(500) as $log) {
                $row = [
                    $log->user_name, $log->user_role, $log->activity, $log->module,
                    $log->reference, $log->event_type, $log->created_at?->toIso8601String(),
                ];

                fputcsv($output, array_map(
                    fn ($value) => is_string($value) && preg_match('/^[\s\x00-\x1f]*[=+@-]/', $value) ? "'".$value : $value,
                    $row
                ), escape: '');
            }
            fclose($output);
        }, 'activity-logs.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function filteredLogs(Request $request): Builder
    {
        $query = ActivityLog::query();
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('user_name', 'like', "%{$search}%")
                    ->orWhere('user_role', 'like', "%{$search}%")
                    ->orWhere('activity', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('event_type', 'like', "%{$search}%");
            });
        }

        if ($request->filled('module') && $request->input('module') !== 'all') {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('event') && $request->input('event') !== 'all') {
            $query->where('event_type', $request->input('event'));
        }

        $dateRange = (string) $request->input('date', 'all');

        if ($dateRange === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($dateRange === 'week') {
            $query->where('created_at', '>=', now()->subDays(7)->startOfDay());
        } elseif ($dateRange === 'month') {
            $query->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month);
        }

        // Allow-list sortable columns to prevent untrusted query parameters from
        // being used as SQL identifiers. Preserve stable pagination for ties.
        $allowedSorts = ['created_at', 'user_name', 'activity', 'module', 'event_type'];
        $sort = in_array((string) $request->input('sort'), $allowedSorts, true)
            ? (string) $request->input('sort')
            : 'created_at';
        $direction = $request->input('sort_direction', 'desc') === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc');
    }

    private function data(Request $request): array
    {
        $logs = $this->filteredLogs($request)
            ->with('user:id,avatar_path')
            ->paginate(20)
            ->withQueryString();

        $today = ActivityLog::query()->whereDate('created_at', today());

        $securityEventTypes = [
            'Login',
            'Logout',
            'Security',
            'Deleted',
        ];

        $businessEventTypes = [
            'Created',
            'Updated',
            'Approval',
            'Rejected',
            'Assigned',
            'Confirmed',
            'Completed',
            'Received',
            'Issued',
            'Imported',
        ];

        return [
            'logs' => $logs,
            'activitiesToday' => (clone $today)->count(),
            'userActions' => (clone $today)
                ->whereNotIn('event_type', ['Login', 'Logout'])
                ->count(),
            'systemEvents' => (clone $today)
                ->whereIn('event_type', $businessEventTypes)
                ->count(),
            'securityEvents' => (clone $today)
                ->whereIn('event_type', $securityEventTypes)
                ->count(),
            'modules' => ActivityLog::query()
                ->select('module')
                ->distinct()
                ->orderBy('module')
                ->pluck('module'),
            'events' => ActivityLog::query()
                ->select('event_type')
                ->distinct()
                ->orderBy('event_type')
                ->pluck('event_type'),
        ];
    }
}
