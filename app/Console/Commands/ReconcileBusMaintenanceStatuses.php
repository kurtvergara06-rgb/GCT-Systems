<?php

namespace App\Console\Commands;

use App\Models\Maintenance\Bus;
use App\Models\Maintenance\JobOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconcileBusMaintenanceStatuses extends Command
{
    protected $signature = 'maintenance:reconcile-bus-statuses
        {--bus=* : Limit reconciliation to one or more exact bus numbers}
        {--apply : Apply the reported status corrections}';

    protected $description = 'Audit or reconcile buses whose status conflicts with an unresolved Maintenance Job Order';

    public function handle(): int
    {
        if (! Schema::hasColumn('buses', 'pre_maintenance_status')) {
            $this->error('Run the pending migrations before reconciling bus maintenance statuses.');

            return self::FAILURE;
        }

        $requestedBusNumbers = collect($this->option('bus'))
            ->map(fn ($busNo) => strtoupper(trim((string) $busNo)))
            ->filter()
            ->unique()
            ->values();

        $query = Bus::query()
            ->where('status', '!=', 'Under Maintenance')
            ->whereIn('bus_no', JobOrder::query()
                ->select('bus_no')
                ->where('status', '!=', 'Completed'))
            ->orderBy('bus_no');

        if ($requestedBusNumbers->isNotEmpty()) {
            $query->whereIn(DB::raw('UPPER(TRIM(bus_no))'), $requestedBusNumbers);
        }

        $inconsistentBuses = $query->get();

        if ($inconsistentBuses->isEmpty()) {
            $this->info('No inconsistent bus maintenance statuses were found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Bus', 'Current status', 'Open Job Orders', 'Correction'],
            $inconsistentBuses->map(function (Bus $bus): array {
                return [
                    $bus->bus_no,
                    $bus->status,
                    JobOrder::query()
                        ->where('bus_no', $bus->bus_no)
                        ->where('status', '!=', 'Completed')
                        ->count(),
                    'Under Maintenance (restore '.$bus->status.' after release)',
                ];
            })->all()
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Re-run with --apply after reviewing the listed buses.');

            return self::SUCCESS;
        }

        $corrected = 0;

        foreach ($inconsistentBuses as $candidate) {
            $changed = DB::transaction(function () use ($candidate): bool {
                $bus = Bus::query()->lockForUpdate()->findOrFail($candidate->id);

                $hasUnresolvedJobOrder = JobOrder::query()
                    ->where('bus_no', $bus->bus_no)
                    ->where('status', '!=', 'Completed')
                    ->lockForUpdate()
                    ->exists();

                if (! $hasUnresolvedJobOrder || $bus->status === 'Under Maintenance') {
                    return false;
                }

                if (! in_array($bus->status, ['Active', 'Inactive'], true)) {
                    $this->warn("Skipped {$bus->bus_no}: unsupported current status {$bus->status}.");

                    return false;
                }

                return $bus->placeUnderMaintenance();
            });

            if ($changed) {
                $corrected++;
            }
        }

        $this->info("Reconciled {$corrected} bus maintenance status(es).");

        return self::SUCCESS;
    }
}
