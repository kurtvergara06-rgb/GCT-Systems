<?php

namespace App\Console\Commands;

use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use Illuminate\Console\Command;

class AuditBusAuthorization extends Command
{
    protected $signature = 'operation:audit-bus-authorization {busNo=AAB-1201}';

    protected $description = 'Read-only audit of bus authorization and scheduled/active trip assignments';

    public function handle(): int
    {
        $number = (string) $this->argument('busNo');
        $bus = Bus::query()->with('authorizedDrivers')->where('bus_no', $number)->first();

        if (! $bus) {
            $this->error("Bus {$number} was not found on this database connection.");
            return self::FAILURE;
        }

        $this->info("Bus {$bus->bus_no} (database ID {$bus->id})");
        $this->table(['DB ID', 'Driver ID', 'Name'], $bus->authorizedDrivers
            ->map(fn (Driver $driver) => [$driver->id, $driver->driver_id, $driver->driver_name])
            ->all());

        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $assignments = $bus->tripAssignments()
            ->with(['tripSchedule' => fn ($query) => $query->withTrashed(), 'driverMaster'])
            ->get();

        $relevant = $assignments->filter(function ($assignment) use ($today): bool {
            $trip = $assignment->tripSchedule;
            return $trip && ($trip->trip_date?->format('Y-m-d') >= $today
                || in_array($trip->status, ['Dispatched'], true));
        })->sortBy(fn ($assignment) => $assignment->tripSchedule->trip_date?->format('Y-m-d') ?? '');

        $this->line("Active/upcoming trips (business date: {$today}):");
        $this->table(
            ['Assignment', 'Trip', 'Date', 'Departure', 'Status', 'Driver DB ID', 'Driver ID', 'Name'],
            $relevant->map(function ($assignment) {
                $trip = $assignment->tripSchedule;
                return [
                    $assignment->id,
                    $trip->trip_code ?: $trip->id,
                    $trip->trip_date?->format('Y-m-d'),
                    $trip->departure_time,
                    $trip->status,
                    $assignment->driver_master_id,
                    $assignment->driver_id,
                    $assignment->driverMaster?->driver_name ?? $assignment->driver_name,
                ];
            })->values()->all()
        );

        $this->line('Historical trip assignments retained: '.$assignments->count().'; no records were modified.');
        $this->warn('Audit only. Khelvi (DB ID 19) is the proposed keeper; no authorization has been detached.');
        return self::SUCCESS;
    }
}
