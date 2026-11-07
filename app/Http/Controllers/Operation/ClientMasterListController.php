<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\OperationClient;
use App\Models\Operation\TripSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'return_to' => ['sometimes', 'in:client-master-list'],
        ]);

        $name = trim($validated['name']);

        if (OperationClient::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A client with this name already exists.',
            ]);
        }

        OperationClient::create([
            'name' => $name,
            'company_address' => $validated['company_address'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'contact_position' => $validated['contact_position'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'service_start_date' => $validated['service_start_date'] ?? null,
        ]);

        return redirect()->route(($validated['return_to'] ?? '') === 'client-master-list' ? 'operation.clients.index' : 'client-trip-times')
            ->with('success', 'Client created successfully.');
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
