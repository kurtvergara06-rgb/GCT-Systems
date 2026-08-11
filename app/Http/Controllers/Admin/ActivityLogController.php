<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
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

        $logs = $query
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $activitiesToday = ActivityLog::whereDate('created_at', today())->count();
        $userActions = ActivityLog::whereDate('created_at', today())
            ->whereNotIn('event_type', ['Login', 'Logout', 'Security'])
            ->count();
        $systemEvents = ActivityLog::whereDate('created_at', today())
            ->whereIn('event_type', ['Created', 'Updated', 'Completed', 'Received', 'Issued', 'Assigned'])
            ->count();
        $securityEvents = ActivityLog::whereDate('created_at', today())
            ->whereIn('event_type', ['Login', 'Logout', 'Security', 'Deleted'])
            ->count();

        $modules = ActivityLog::query()
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        $events = ActivityLog::query()
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');

        return view('Admin.System_Monitoring.activity-logs', compact(
            'logs',
            'activitiesToday',
            'userActions',
            'systemEvents',
            'securityEvents',
            'modules',
            'events'
        ));
    }
}
