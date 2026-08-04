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
        $drivers = Driver::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->search);
                $query->where(function ($nested) use ($search): void {
                    $nested->where('driver_id', 'like', "%{$search}%")
                        ->orWhere('driver_name', 'like', "%{$search}%")
                        ->orWhere('shift', 'like', "%{$search}%")
                        ->orWhere('license_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('driver_name')
            ->paginate(12)
            ->withQueryString();

        return view('Operation.Personnel.driver-master-list', compact('drivers'));
    }

    public function mechanics(Request $request): View
    {
        $mechanics = Mechanic::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->search);
                $query->where(function ($nested) use ($search): void {
                    $nested->where('mechanic_id', 'like', "%{$search}%")
                        ->orWhere('mechanic_name', 'like', "%{$search}%")
                        ->orWhere('shift', 'like', "%{$search}%")
                        ->orWhere('specialization', 'like', "%{$search}%");
                });
            })
            ->orderBy('mechanic_name')
            ->paginate(12)
            ->withQueryString();

        return view('Operation.Personnel.mechanic-master-list', compact('mechanics'));
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'driver_id' => ['required', 'string', 'max:100', 'unique:drivers,driver_id'],
            'driver_name' => ['required', 'string', 'max:255'],
            'shift' => ['required', Rule::in(['Morning', 'Afternoon', 'Night'])],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_expiration' => ['nullable', 'date'],
            'employment_status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        Driver::create($validated);

        return back()->with('success', 'Driver master record created successfully.');
    }

    public function storeMechanic(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mechanic_id' => ['required', 'string', 'max:100', 'unique:mechanics,mechanic_id'],
            'mechanic_name' => ['required', 'string', 'max:255'],
            'shift' => ['required', Rule::in(['Morning', 'Afternoon', 'Night'])],
            'specialization' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'employment_status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        Mechanic::create($validated);

        return back()->with('success', 'Mechanic master record created successfully.');
    }
}
