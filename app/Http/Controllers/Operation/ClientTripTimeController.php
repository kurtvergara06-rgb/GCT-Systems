<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\ClientTripTimeConfiguration;
use App\Models\Operation\ClientTripTimeSlot;
use App\Models\Operation\OperationClient;
use App\Support\ClientTripTimes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientTripTimeController extends Controller
{
    public function index(): View
    {
        $clients = OperationClient::query()
            ->with([
                'tripTimeConfigurations' => fn ($query) => $query->orderBy('category'),
                'tripTimeConfigurations.slots',
                'buses' => fn ($query) => $query->orderBy('bus_no'),
            ])
            ->orderBy('name')
            ->get();

        return view('Operation.Scheduling_And_Dispatch.client-trip-times', [
            'clients' => $clients,
            'categoryLabels' => ClientTripTimes::CATEGORY_LABELS,
            'canEditOperation' => auth()->user()?->hasSystemPermission('operation', 'edit') ?? false,
        ]);
    }

    public function panel(OperationClient $operationClient, string $category): View
    {
        abort_unless(ClientTripTimes::isValidCategory($category), 404);

        $configuration = $operationClient
            ->tripTimeConfigurations()
            ->where('category', $category)
            ->with('slots')
            ->first();

        return view('Operation.Scheduling_And_Dispatch.partials.client-trip-time-panel', [
            'client' => $operationClient,
            'category' => $category,
            'categoryLabel' => ClientTripTimes::categoryLabel($category),
            'configuration' => $configuration,
            'canEditOperation' => (auth()->user()?->hasSystemPermission('operation', 'edit') ?? false) && $operationClient->isActive(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'operation_client_id' => [
                'required',
                'integer',
                Rule::exists('operation_clients', 'id'),
            ],
            'category' => [
                'required',
                Rule::in(ClientTripTimes::CATEGORIES),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
            'slots' => ['sometimes', 'array', 'max:96'],
            'slots.*' => ['required', 'date_format:H:i'],
        ]);

        $this->assertClientActive(OperationClient::query()->findOrFail($validated['operation_client_id']));

        $this->assertRangeIsCoherent(
            $validated['category'],
            $validated['start_time'],
            $validated['end_time']
        );

        $startMinutes = ClientTripTimes::minutesFromTime($validated['start_time']);
        $endMinutes = ClientTripTimes::minutesFromTime($validated['end_time']);

        $slotMinutes = array_map(ClientTripTimes::minutesFromTime(...), $validated['slots'] ?? []);
        $slotMinutes = array_values(array_unique($slotMinutes));
        sort($slotMinutes);

        if (count($slotMinutes) !== count($validated['slots'] ?? [])) {
            throw ValidationException::withMessages([
                'slots' => 'Duplicate time slots were submitted. Each configured slot may only appear once.',
            ]);
        }

        foreach ($slotMinutes as $minutes) {
            $this->assertSlotInsideRange($minutes, $startMinutes, $endMinutes, $validated['category']);
        }

        DB::transaction(function () use ($validated, $slotMinutes, $startMinutes, $endMinutes): void {
            // Lock the client even if this category configuration does not
            // exist yet; locking only missing rows cannot serialize creation.
            $client = OperationClient::query()->lockForUpdate()
                ->findOrFail($validated['operation_client_id']);
            $this->assertClientActive($client);

            if (ClientTripTimeConfiguration::query()
                ->where('operation_client_id', $client->id)
                ->where('category', $validated['category'])
                ->exists()) {
                throw ValidationException::withMessages([
                    'category' => sprintf(
                        '%s is already configured for this client. Edit the existing configuration instead.',
                        ClientTripTimes::categoryLabel($validated['category'])
                    ),
                ]);
            }

            $configuration = ClientTripTimeConfiguration::create([
                'operation_client_id' => $validated['operation_client_id'],
                'category' => $validated['category'],
                'start_time' => ClientTripTimes::timeFromMinutes($startMinutes),
                'end_time' => ClientTripTimes::timeFromMinutes($endMinutes),
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($slotMinutes as $minutes) {
                ClientTripTimeSlot::create([
                    'client_trip_time_configuration_id' => $configuration->id,
                    'slot_time' => ClientTripTimes::timeFromMinutes($minutes),
                    'is_enabled' => true,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'panel_url' => route(
                'client-trip-times.panel',
                [
                    $validated['operation_client_id'],
                    $validated['category'],
                ],
                false
            ),
        ]);
    }

    public function update(
        Request $request,
        ClientTripTimeConfiguration $clientTripTimeConfiguration
    ): JsonResponse {
        $validated = $request->validate([
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = (string) $clientTripTimeConfiguration->category;
        $this->assertRangeIsCoherent(
            $category,
            $validated['start_time'],
            $validated['end_time']
        );

        $startMinutes = ClientTripTimes::minutesFromTime($validated['start_time']);
        $endMinutes = ClientTripTimes::minutesFromTime($validated['end_time']);

        DB::transaction(function () use ($clientTripTimeConfiguration, $validated, $startMinutes, $endMinutes): void {
            $configuration = ClientTripTimeConfiguration::query()
                ->lockForUpdate()->findOrFail($clientTripTimeConfiguration->id);
            $this->assertClientActive($configuration->client);

            $outsideRange = $configuration->slots()->get()
                ->filter(fn (ClientTripTimeSlot $slot): bool => $slot->minutes() < $startMinutes
                    || $slot->minutes() > $endMinutes)
                ->values();
            if ($outsideRange->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'end_time' => sprintf(
                        'The new window (%s – %s) excludes existing slots: %s. Remove those slots first or keep them inside the window.',
                        ClientTripTimes::displayTime($startMinutes),
                        ClientTripTimes::displayTime($endMinutes),
                        $outsideRange->map(fn (ClientTripTimeSlot $slot): string => ClientTripTimes::displayTime($slot->minutes()))
                            ->implode(', ')
                    ),
                ]);
            }

            $configuration->update([
                'start_time' => ClientTripTimes::timeFromMinutes($startMinutes),
                'end_time' => ClientTripTimes::timeFromMinutes($endMinutes),
                'is_active' => $validated['is_active'] ?? $configuration->is_active,
                'updated_by' => auth()->id(),
            ]);
        }, 5);

        return response()->json([
            'success' => true,
            'panel_url' => route(
                'client-trip-times.panel',
                [
                    $clientTripTimeConfiguration->operation_client_id,
                    $clientTripTimeConfiguration->category,
                ],
                false
            ),
        ]);
    }

    public function addSlot(
        Request $request,
        ClientTripTimeConfiguration $clientTripTimeConfiguration
    ): JsonResponse {
        $validated = $request->validate([
            'slot_time' => ['required', 'date_format:H:i'],
        ]);

        $minutes = ClientTripTimes::minutesFromTime($validated['slot_time']);

        DB::transaction(function () use ($clientTripTimeConfiguration, $minutes): void {
            // Serialize changes to every slot under the same configuration.
            $configuration = ClientTripTimeConfiguration::query()
                ->lockForUpdate()->findOrFail($clientTripTimeConfiguration->id);
            $this->assertClientActive($configuration->client);

            $this->assertSlotInsideRange(
                $minutes,
                $configuration->startMinutes(),
                $configuration->endMinutes(),
                (string) $configuration->category
            );

            $duplicate = $configuration->slots()->get()
                ->contains(fn (ClientTripTimeSlot $slot): bool => $slot->minutes() === $minutes);
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'slot_time' => sprintf(
                        '%s is already an allowed slot for this category.',
                        ClientTripTimes::displayTime($minutes)
                    ),
                ]);
            }

            ClientTripTimeSlot::create([
                'client_trip_time_configuration_id' => $configuration->id,
                'slot_time' => ClientTripTimes::timeFromMinutes($minutes),
                'is_enabled' => true,
            ]);
            $configuration->update(['updated_by' => auth()->id()]);
        }, 5);

        return response()->json([
            'success' => true,
            'panel_url' => route('client-trip-times.panel', [
                $clientTripTimeConfiguration->operation_client_id,
                $clientTripTimeConfiguration->category,
            ], false),
        ]);
    }

    public function updateSlot(
        Request $request,
        ClientTripTimeSlot $clientTripTimeSlot
    ): JsonResponse {
        $validated = $request->validate([
            'slot_time' => ['required', 'date_format:H:i'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);
        $minutes = ClientTripTimes::minutesFromTime($validated['slot_time']);
        $configurationId = $clientTripTimeSlot->client_trip_time_configuration_id;

        $configuration = DB::transaction(function () use ($clientTripTimeSlot, $configurationId, $minutes, $validated): ClientTripTimeConfiguration {
            $configuration = ClientTripTimeConfiguration::query()
                ->lockForUpdate()->findOrFail($configurationId);
            $slot = ClientTripTimeSlot::query()->where('client_trip_time_configuration_id', $configuration->id)
                ->findOrFail($clientTripTimeSlot->id);
            $this->assertClientActive($configuration->client);
            $this->assertSlotInsideRange(
                $minutes,
                $configuration->startMinutes(),
                $configuration->endMinutes(),
                (string) $configuration->category
            );

            $duplicate = $configuration->slots()->get()
                ->contains(fn (ClientTripTimeSlot $other): bool =>
                    $other->id !== $slot->id && $other->minutes() === $minutes
                );
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'slot_time' => sprintf(
                        '%s is already an allowed slot for this category.',
                        ClientTripTimes::displayTime($minutes)
                    ),
                ]);
            }

            $slot->update([
                'slot_time' => ClientTripTimes::timeFromMinutes($minutes),
                'is_enabled' => $validated['is_enabled'] ?? $slot->is_enabled,
            ]);
            $configuration->update(['updated_by' => auth()->id()]);
            return $configuration;
        }, 5);

        return response()->json([
            'success' => true,
            'panel_url' => route('client-trip-times.panel', [
                $configuration->operation_client_id,
                $configuration->category,
            ], false),
        ]);
    }

    public function destroySlot(ClientTripTimeSlot $clientTripTimeSlot): JsonResponse
    {
        $configuration = DB::transaction(function () use ($clientTripTimeSlot): ?ClientTripTimeConfiguration {
            $configuration = ClientTripTimeConfiguration::query()
                ->lockForUpdate()
                ->find($clientTripTimeSlot->client_trip_time_configuration_id);
            if (! $configuration) {
                return null;
            }

            $this->assertClientActive($configuration->client);
            ClientTripTimeSlot::query()
                ->where('client_trip_time_configuration_id', $configuration->id)
                ->whereKey($clientTripTimeSlot->id)
                ->delete();
            $configuration->update(['updated_by' => auth()->id()]);
            return $configuration;
        }, 5);

        return response()->json([
            'success' => true,
            'panel_url' => $configuration
                ? route(
                    'client-trip-times.panel',
                    [
                        $configuration->operation_client_id,
                        $configuration->category,
                    ],
                    false
                )
                : null,
        ]);
    }

    private function assertClientActive(OperationClient $client): void
    {
        if (! $client->isActive()) {
            throw ValidationException::withMessages([
                'client' => 'Inactive clients are read-only. Reactivate the client before changing trip times.',
            ]);
        }
    }

    private function assertRangeIsCoherent(
        string $category,
        string $startTime,
        string $endTime
    ): void {
        $startMinutes = ClientTripTimes::minutesFromTime($startTime);
        $endMinutes = ClientTripTimes::minutesFromTime($endTime);

        if ($startMinutes > $endMinutes) {
            throw ValidationException::withMessages([
                'end_time' => 'Trip time ranges cannot cross midnight. The start time must not be after the end time.',
            ]);
        }

        $isAm = ClientTripTimes::isAmCategory($category);
        if ($isAm && $startMinutes >= ClientTripTimes::NOON_MINUTES) {
            throw ValidationException::withMessages([
                'start_time' => 'AM categories must start before 12:00 PM.',
            ]);
        }
        if ($isAm && $endMinutes >= ClientTripTimes::NOON_MINUTES) {
            throw ValidationException::withMessages([
                'end_time' => 'AM categories must end before 12:00 PM. Latest allowed time is 11:59 AM.',
            ]);
        }
        if (! $isAm && $startMinutes < ClientTripTimes::NOON_MINUTES) {
            throw ValidationException::withMessages([
                'start_time' => 'PM categories must start at 12:00 PM or later (through 11:59 PM).',
            ]);
        }
    }

    private function assertSlotInsideRange(
        int $slotMinutes,
        int $startMinutes,
        int $endMinutes,
        string $category
    ): void {
        if ($slotMinutes < $startMinutes || $slotMinutes > $endMinutes) {
            throw ValidationException::withMessages([
                'slot_time' => sprintf(
                    '%s is outside the configured %s window (%s – %s).',
                    ClientTripTimes::displayTime($slotMinutes),
                    ClientTripTimes::categoryLabel($category),
                    ClientTripTimes::displayTime($startMinutes),
                    ClientTripTimes::displayTime($endMinutes)
                ),
            ]);
        }

        $isAm = ClientTripTimes::isAmCategory($category);

        if ($isAm && $slotMinutes >= ClientTripTimes::NOON_MINUTES) {
            throw ValidationException::withMessages([
                'slot_time' => sprintf(
                    '%s belongs to an AM category but is 12:00 NN or later.',
                    ClientTripTimes::displayTime($slotMinutes)
                ),
            ]);
        }

        if (! $isAm && $slotMinutes < ClientTripTimes::NOON_MINUTES) {
            throw ValidationException::withMessages([
                'slot_time' => sprintf(
                    '%s belongs to a PM category but is before 12:00 NN.',
                    ClientTripTimes::displayTime($slotMinutes)
                ),
            ]);
        }
    }
}
