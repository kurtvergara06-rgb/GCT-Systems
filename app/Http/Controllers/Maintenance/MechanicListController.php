<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Http\Request;

class MechanicListController extends Controller
{
    public function index(Request $request)
    {
        $query = MechanicAttendance::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('mechanic_name', 'like', "%{$search}%")
                    ->orWhere('mechanic_id', 'like', "%{$search}%")
                    ->orWhere('assigned_job', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_filter')) {
            if ($request->date_filter === 'Today') {
                $query->whereDate('attendance_date', today());
            }

            if ($request->date_filter === 'This Week') {
                $query->whereBetween('attendance_date', [
                    now()->startOfWeek(),
                    now()->endOfWeek(),
                ]);
            }
        }

        if (
            $request->filled('availability') &&
            $request->availability !== 'All Types'
        ) {
            if ($request->availability === 'Available') {
                $query->whereIn('status', ['Present', 'Late']);
            }

            if ($request->availability === 'Not Available') {
                $query->whereIn('status', [
                    'On Duty',
                    'Absent',
                    'On Leave',
                ]);
            }
        }

        $mechanics = $query
            ->latest('attendance_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $totalMechanics = MechanicAttendance::count();

        $availableMechanics = MechanicAttendance::query()
            ->whereIn('status', ['Present', 'Late'])
            ->count();

        $notAvailableMechanics = MechanicAttendance::query()
            ->whereIn('status', [
                'On Duty',
                'Absent',
                'On Leave',
            ])
            ->count();

        $onDutyMechanics = MechanicAttendance::query()
            ->where('status', 'On Duty')
            ->count();

        return view('Maintenance.mechanic-list', compact(
            'mechanics',
            'totalMechanics',
            'availableMechanics',
            'notAvailableMechanics',
            'onDutyMechanics'
        ));
    }
}