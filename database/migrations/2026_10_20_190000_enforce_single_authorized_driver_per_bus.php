<?php

use App\Models\Maintenance\Bus;
use App\Models\Operation\TripAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        // Targeted production cleanup approved for AAB-1201. Never delete
        // a driver, bus, assignment or historical record.
        DB::transaction(function (): void {
            $bus = Bus::query()
                ->where('bus_no', 'AAB-1201')
                ->lockForUpdate()
                ->first();

            if (! $bus) {
                return;
            }

            $ownerIds = DB::table('driver_authorized_buses')
                ->where('bus_id', $bus->id)
                ->lockForUpdate()
                ->pluck('driver_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($ownerIds === [19]) {
                return; // Already corrected.
            }

            if ($ownerIds !== [19, 20]) {
                throw new \RuntimeException(
                    'AAB-1201 owner records differ from the audited IDs; nothing was changed.'
                );
            }

            $drivers = $bus->authorizedDrivers()->get(['drivers.id', 'drivers.driver_id', 'drivers.driver_name'])
                ->keyBy('id');

            if ((string) $drivers->get(19)?->driver_id !== '111'
                || (string) $drivers->get(20)?->driver_id !== '222') {
                throw new \RuntimeException(
                    'AAB-1201 driver identities no longer match the production audit; nothing was changed.'
                );
            }

            $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
            $conflicts = TripAssignment::query()
                ->where('bus_id', $bus->id)
                ->where(function ($query): void {
                    $query->where('driver_master_id', 20)
                        ->orWhere('driver_id', '222');
                })
                ->whereHas('tripSchedule', function ($query) use ($today): void {
                    $query->where(function ($trips) use ($today): void {
                        $trips->where('status', 'Dispatched')
                            ->orWhere(function ($future) use ($today): void {
                                $future->whereDate('trip_date', '>=', $today)
                                    ->whereNotIn('status', ['Cancelled', 'Completed', 'Missed']);
                            });
                    });
                })
                ->with('tripSchedule')
                ->get();

            if ($conflicts->isNotEmpty()) {
                Log::warning('AAB-1201 cleanup blocked by active/upcoming Brixx trips', [
                    'trip_assignment_ids' => $conflicts->pluck('id')->all(),
                    'trip_schedule_ids' => $conflicts->pluck('trip_schedule_id')->all(),
                ]);
                throw new \RuntimeException(
                    'AAB-1201 has active/upcoming trips assigned to Brixx. No records were changed.'
                );
            }

            $bus->authorizedDrivers()->detach(20);
            Log::warning('AAB-1201 duplicate authorization corrected', [
                'retained_driver_db_id' => 19,
                'detached_driver_db_id' => 20,
                'historical_trips_changed' => false,
            ]);
        });

        $conflictingBusIds = DB::table('driver_authorized_buses')
            ->select('bus_id')
            ->groupBy('bus_id')
            ->havingRaw('COUNT(DISTINCT driver_id) > 1')
            ->orderBy('bus_id')
            ->pluck('bus_id');

        if ($conflictingBusIds->isNotEmpty()) {
            // Read-only diagnostics for production deploy logs. No ownership
            // is changed: an operator must resolve conflicting authorizations.
            Bus::query()
                ->with('authorizedDrivers:id,driver_id,driver_name')
                ->whereIn('id', $conflictingBusIds)
                ->orderBy('bus_no')
                ->get(['id', 'bus_no'])
                ->each(function (Bus $bus): void {
                    Log::warning('Conflicting bus authorization', [
                        'bus_no' => $bus->bus_no,
                        'authorized_drivers' => $bus->authorizedDrivers
                            ->map(fn ($driver) => [
                                'id' => $driver->id,
                                'driver_id' => $driver->driver_id,
                                'driver_name' => $driver->driver_name,
                            ])
                            ->values()
                            ->all(),
                    ]);
                });
            $busLabels = DB::table('buses')
                ->whereIn('id', $conflictingBusIds)
                ->orderBy('bus_no')
                ->pluck('bus_no')
                ->all();

            throw new \RuntimeException(
                'Cannot enforce one authorized driver per bus. Resolve the duplicate driver authorizations for: '
                .implode(', ', $busLabels)
                .'. No authorization records were changed.'
            );
        }

        Schema::table('driver_authorized_buses', function (Blueprint $table): void {
            $table->unique('bus_id', 'driver_authorized_buses_bus_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('driver_authorized_buses', function (Blueprint $table): void {
            $table->dropUnique('driver_authorized_buses_bus_id_unique');
        });
    }
};
