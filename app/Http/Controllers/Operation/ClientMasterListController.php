<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\OperationClient;
use App\Models\Operation\TripSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientMasterListController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $tab = $request->query('tab') === 'history' || $request->query('status') === 'Inactive'
            ? 'history'
            : 'active';
        $status = $tab === 'history' ? 'Inactive' : 'Active';
        $setup = in_array($request->query('setup'), ['complete', 'needs-setup'], true)
            ? $request->query('setup')
            : '';
        $sort = in_array($request->query('sort'), ['name', 'created_at'], true)
            ? $request->query('sort')
            : 'name';
        $direction = $request->query('sort_direction') === 'desc' ? 'desc' : 'asc';

        $clients = OperationClient::query()
            ->withCount(['buses', 'tripTimeConfigurations'])
            ->where('status', $status)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\\\').'%'))
            ->when($setup === 'complete', fn ($query) => $query->has('tripTimeConfigurations', '>=', 4))
            ->when($setup === 'needs-setup', fn ($query) => $query->has('tripTimeConfigurations', '<', 4))
            ->orderBy($sort, $direction)
            ->orderBy('id');

        return view('Operation.Clients.index', [
            'clients' => $clients->paginate(15)->withQueryString(),
            'totalClients' => OperationClient::count(),
            'activeClients' => OperationClient::where('status', 'Active')->count(),
            'configuredClients' => OperationClient::has('tripTimeConfigurations', '>=', 4)->count(),
            'canEditOperation' => auth()->user()?->hasSystemPermission('operation', 'edit') ?? false,
            'selectedTab' => $tab,
            'selectedSetup' => $setup,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            // Existing Client Trip Times creation remains backwards compatible.
            'company_address' => ['nullable', 'string', 'max:500'],
            'contact_person' => [$request->input('return_to') === 'client-master-list' ? 'required' : 'nullable', 'string', 'max:150'],
            'contact_position' => ['nullable', 'string', 'max:150'],
            'contact_number' => [$request->input('return_to') === 'client-master-list' ? 'required' : 'nullable', 'string', 'max:30', 'regex:/^[0-9+()\\s.-]{7,30}$/'],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'service_start_date' => ['nullable', 'date'],
            'logo' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb')],
            'return_to' => ['sometimes', 'in:client-master-list'],
        ]);

        $name = trim($validated['name']);

        if (OperationClient::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A client with this name already exists.',
            ]);
        }

        $logoPath = $request->hasFile('logo')
            ? $request->file('logo')->store('operation/client-logos', 'public')
            : null;

        try {
            OperationClient::create([
                'name' => $name,
                'logo_path' => $logoPath,
                'company_address' => $validated['company_address'] ?? null,
                'contact_person' => $validated['contact_person'] ?? null,
                'contact_position' => $validated['contact_position'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'service_start_date' => $validated['service_start_date'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            if ($logoPath) Storage::disk('public')->delete($logoPath);
            throw $exception;
        }

        return redirect()->route(($validated['return_to'] ?? '') === 'client-master-list' ? 'operation.clients.index' : 'client-trip-times')
            ->with('success', 'Client created successfully.');
    }
    public function update(Request $request, OperationClient $operationClient): RedirectResponse
    {
        // Client IDs are route-bound permanent keys, never accepted from the form.
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'contact_position' => ['nullable', 'string', 'max:150'],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\\s.-]{7,30}$/'],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'logo' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb')],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        $newLogoPath = $request->hasFile('logo')
            ? $request->file('logo')->store('operation/client-logos', 'public')
            : null;

        try {
            $previousLogoPath = DB::transaction(function () use ($operationClient, $validated, $newLogoPath): ?string {
                $client = OperationClient::query()->lockForUpdate()->findOrFail($operationClient->id);
                $newName = trim($validated['name']);
                $renaming = $newName !== $client->name;
    
                if ($renaming) {
                    if (OperationClient::query()
                        ->whereKeyNot($client->id)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($newName)])
                        ->exists()) {
                        throw ValidationException::withMessages(['name' => 'A client with this name already exists.']);
                    }
    
                    // Trip schedules currently store a client-name snapshot. A
                    // rename during active scheduling would break lookups that
                    // guard deactivation and unfinished trip operations.
                    $hasUnfinishedTrips = TripSchedule::query()
                        ->whereRaw('LOWER(client_name) = ?', [mb_strtolower($client->name)])
                        ->where(function ($query): void {
                            $query->where(function ($upcoming): void {
                                $upcoming->notDeparted()
                                    ->whereNotIn('status', ['Cancelled', 'Completed']);
                            })->orWhereIn('status', ['Dispatched', 'Ongoing', 'In Progress', 'Pending DDR']);
                        })->exists();
                    if ($hasUnfinishedTrips) {
                        throw ValidationException::withMessages([
                            'name' => 'Complete or cancel this client’s unfinished trips before changing the company name. Contact details may still be updated.',
                        ]);
                    }
                }
    
                $previousLogoPath = $client->logo_path;
                $client->update([
                    'name' => $newName,
                    'logo_path' => $newLogoPath ?? (($validated['remove_logo'] ?? false) ? null : $previousLogoPath),
                    'company_address' => $validated['company_address'] ?? null,
                    'contact_person' => $validated['contact_person'] ?? null,
                    'contact_position' => $validated['contact_position'] ?? null,
                    'contact_number' => $validated['contact_number'] ?? null,
                    'contact_email' => $validated['contact_email'] ?? null,
                ]);
                return $previousLogoPath;
            }, 5);
        } catch (\Throwable $exception) {
            if ($newLogoPath) Storage::disk('public')->delete($newLogoPath);
            throw $exception;
        }
        if ($previousLogoPath && ($newLogoPath || ($validated['remove_logo'] ?? false))) {
            Storage::disk('public')->delete($previousLogoPath);
        }

        return redirect()->route('operation.clients.index')
            ->with('success', 'Client details updated successfully.');
    }

    public function updateStatus(Request $request, OperationClient $operationClient): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:Active,Inactive'],
            'return_to' => ['sometimes', 'in:client-master-list'],
        ]);

        DB::transaction(function () use ($operationClient, $validated): void {
            $client = OperationClient::query()->lockForUpdate()->findOrFail($operationClient->id);

            if ($validated['status'] === 'Inactive' && $client->status !== 'Inactive') {
                $name = mb_strtolower($client->name);
                $hasUpcomingTrips = TripSchedule::query()
                    ->whereRaw('LOWER(client_name) = ?', [$name])
                    ->where(function ($query): void {
                        $query->where(function ($upcoming): void {
                            $upcoming->notDeparted()
                                ->whereNotIn('status', ['Cancelled', 'Completed']);
                        })->orWhereIn('status', ['Dispatched', 'Ongoing', 'In Progress']);
                    })->exists();

                if ($hasUpcomingTrips) {
                    throw ValidationException::withMessages([
                        'status' => 'This client has upcoming trips. Resolve those schedules before deactivating the client.',
                    ]);
                }
            }

            $client->update(['status' => $validated['status']]);
        }, 5);

        return redirect()->route(($validated['return_to'] ?? '') === 'client-master-list' ? 'operation.clients.index' : 'client-trip-times')
            ->with('success', 'Client status updated successfully.');
    }

}
