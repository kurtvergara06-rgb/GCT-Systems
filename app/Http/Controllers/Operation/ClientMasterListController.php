<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\OperationClient;
use App\Models\Operation\TripSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientMasterListController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
        ]);

        $name = trim($validated['name']);

        if (OperationClient::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A client with this name already exists.',
            ]);
        }

        OperationClient::create(['name' => $name]);

        return redirect()->route('client-trip-times')
            ->with('success', 'Client created successfully. Configure the new client’s departure times below.');
    }
    public function updateStatus(Request $request, OperationClient $operationClient): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:Active,Inactive'],
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

        return redirect()->route('client-trip-times')
            ->with('success', 'Client status updated successfully.');
    }

}
