<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\Driver;
use App\Models\Operation\Mechanic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PersonnelController extends Controller
{
    public function drivers(Request $request): View
    {
        $query = Driver::query();

        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('employment_status', 'Active')->count(),
            'inactive' => (clone $query)->where('employment_status', 'Inactive')->count(),
            'expiring' => (clone $query)
                ->whereNotNull('license_expiration')
                ->whereBetween('license_expiration', [today(), today()->addDays(60)])
                ->count(),
        ];

        $drivers = $query
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->search);
                $query->where(function ($nested) use ($search): void {
                    $nested->where('driver_id', 'like', "%{$search}%")
                        ->orWhere('driver_name', 'like', "%{$search}%")
                        ->orWhere('shift', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('license_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('employment_status', $request->status))
            ->orderBy('driver_name')
            ->paginate(12)
            ->withQueryString();

        return view('Operation.Personnel Management.driver_master_list', compact('drivers', 'stats'));
    }

    public function mechanics(Request $request): View
    {
        $query = Mechanic::query();

        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('employment_status', 'Active')->count(),
            'inactive' => (clone $query)->where('employment_status', 'Inactive')->count(),
            'specializations' => (clone $query)->whereNotNull('specialization')->distinct('specialization')->count('specialization'),
        ];

        $mechanics = $query
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->search);
                $query->where(function ($nested) use ($search): void {
                    $nested->where('mechanic_id', 'like', "%{$search}%")
                        ->orWhere('mechanic_name', 'like', "%{$search}%")
                        ->orWhere('shift', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('specialization', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('employment_status', $request->status))
            ->orderBy('mechanic_name')
            ->paginate(12)
            ->withQueryString();

        return view('Operation.Personnel Management.mechanic_master_list', compact('mechanics', 'stats'));
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        Driver::create($this->validateDriver($request));
        return back()->with('success', 'Driver master record created successfully.');
    }

    public function updateDriver(Request $request, Driver $driver): RedirectResponse
    {
        $driver->update($this->validateDriver($request, $driver));
        return back()->with('success', 'Driver master record updated successfully.');
    }

    public function deactivateDriver(Driver $driver): RedirectResponse
    {
        $driver->update(['employment_status' => 'Inactive']);
        return back()->with('success', 'Driver has been deactivated. Existing attendance records were preserved.');
    }

    public function storeMechanic(Request $request): RedirectResponse
    {
        Mechanic::create($this->validateMechanic($request));
        return back()->with('success', 'Mechanic master record created successfully.');
    }

    public function updateMechanic(Request $request, Mechanic $mechanic): RedirectResponse
    {
        $mechanic->update($this->validateMechanic($request, $mechanic));
        return back()->with('success', 'Mechanic master record updated successfully.');
    }

    public function deactivateMechanic(Mechanic $mechanic): RedirectResponse
    {
        $mechanic->update(['employment_status' => 'Inactive']);
        return back()->with('success', 'Mechanic has been deactivated. Existing attendance records were preserved.');
    }

    private function validateDriver(Request $request, ?Driver $driver = null): array
    {
        return $request->validate([
            'driver_id' => ['required', 'string', 'max:100', Rule::unique('drivers', 'driver_id')->ignore($driver?->id)],
            'driver_name' => ['required', 'string', 'max:255'],
            'shift' => ['required', Rule::in(['Morning', 'Afternoon', 'Night'])],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_expiration' => ['nullable', 'date'],
            'employment_status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function validateMechanic(Request $request, ?Mechanic $mechanic = null): array
    {
        return $request->validate([
            'mechanic_id' => ['required', 'string', 'max:100', Rule::unique('mechanics', 'mechanic_id')->ignore($mechanic?->id)],
            'mechanic_name' => ['required', 'string', 'max:255'],
            'shift' => ['required', Rule::in(['Morning', 'Afternoon', 'Night'])],
            'specialization' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'employment_status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }
}
