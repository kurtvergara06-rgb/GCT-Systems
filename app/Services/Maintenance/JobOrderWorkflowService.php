<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\JobOrderAudit;
use App\Models\Maintenance\JobOrderMechanic;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Models\Warehouse\InventoryItem;
use App\Services\PartParser;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class JobOrderWorkflowService
{
    public function availableMechanics(?JobOrder $jobOrder = null, ?string $workText = null): Collection
    {
        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $busyIds = JobOrderMechanic::query()
            ->whereHas('jobOrder', function ($query) use ($jobOrder): void {
                $query->where('status', 'On Going')
                    ->where('review_status', 'Approved')
                    ->when($jobOrder, fn ($nested) => $nested->whereKeyNot($jobOrder->id));
            })
            ->whereNotNull('mechanic_id')
            ->pluck('mechanic_id');
        $legacyNames = JobOrder::query()
            ->where('status', 'On Going')
            ->where('review_status', 'Approved')
            ->when($jobOrder, fn ($query) => $query->whereKeyNot($jobOrder->id))
            ->whereDoesntHave('mechanicAssignments')
            ->pluck('assigned_mechanic')
            ->flatMap(fn ($names) => preg_split('/\s*,\s*/', (string) $names) ?: [])
            ->filter();
        // Legacy name-only assignments cannot identify namesakes reliably.
        // Exclude a name only if it belongs to one distinct master mechanic.
        $busyLegacyNames = $legacyNames->filter(fn ($name) =>
            Mechanic::query()->where('mechanic_name', $name)->count() === 1
        );

        // Use the same attendance-by-mechanic-ID basis as the Mechanic
        // Availability board, so both screens agree on today's duty status.
        $onDutyMechanicCodes = MechanicAttendance::query()
            ->whereDate('attendance_date', $today)
            ->whereIn('status', ['Present', 'Late', 'On Duty'])
            ->pluck('mechanic_id')
            ->filter(fn ($id) => trim((string) $id) !== '')
            ->unique();

        return Mechanic::query()
            ->where('employment_status', 'Active')
            ->whereIn('mechanic_id', $onDutyMechanicCodes)
            ->whereNotIn('id', $busyIds)
            ->whereNotIn('mechanic_name', $busyLegacyNames)
            ->orderBy('mechanic_name')
            ->get()
            ->map(function (Mechanic $mechanic) use ($workText): Mechanic {
                $skills = collect(preg_split('/[,;]+/u', (string) $mechanic->specialization) ?: [])
                    ->map(fn ($skill) => trim((string) $skill))->filter()->values();
                $haystack = mb_strtolower((string) $workText);
                $matches = $skills->filter(fn ($skill) => str_contains($haystack, mb_strtolower($skill)))->values();
                $mechanic->setAttribute('specialization_labels', $skills);
                $mechanic->setAttribute('skill_matches', $matches);
                $mechanic->setAttribute('skill_match_score', $matches->count());

                return $mechanic;
            })
            ->sortByDesc('skill_match_score')
            ->values();
    }

    public function validatedMechanics(array $mechanicIds, ?JobOrder $jobOrder = null): Collection
    {
        $ids = collect($mechanicIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $available = $this->availableMechanics($jobOrder)->whereIn('id', $ids)->values();
        if ($available->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'mechanic_ids' => 'One or more selected mechanics are unavailable, inactive, or have no attendance record today.',
            ]);
        }

        return $available;
    }

    public function syncMechanics(JobOrder $jobOrder, Collection $mechanics, ?int $assignedBy): void
    {
        $jobOrder->mechanicAssignments()->delete();
        foreach ($mechanics as $mechanic) {
            $jobOrder->mechanicAssignments()->create([
                'mechanic_id' => $mechanic->id,
                'mechanic_name' => $mechanic->mechanic_name,
                'specialization_snapshot' => $mechanic->specialization,
                'assigned_by' => $assignedBy,
            ]);
        }

        $jobOrder->update([
            'assigned_mechanic' => $mechanics->pluck('mechanic_name')->implode(', ') ?: null,
        ]);
    }

    public function setAttendanceStatus(JobOrder $jobOrder, string $status): void
    {
        $today = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $assignments = $jobOrder->mechanicAssignments;
        foreach ($assignments as $assignment) {
            $query = MechanicAttendance::query()
                ->whereDate('attendance_date', $today)
                ->when($assignment->mechanic?->mechanic_id,
                    fn ($nested, $mechanicId) => $nested->where('mechanic_id', $mechanicId),
                    fn ($nested) => $nested->where('mechanic_name', $assignment->mechanic_name));
            $query->latest('id')->first()?->update(['status' => $status]);
        }

        if ($assignments->isEmpty()) {
            foreach (preg_split('/\s*,\s*/', (string) $jobOrder->assigned_mechanic) ?: [] as $mechanicName) {
                if ($mechanicName === '') {
                    continue;
                }
                MechanicAttendance::query()
                    ->where('mechanic_name', $mechanicName)
                    ->whereDate('attendance_date', $today)
                    ->latest('id')->first()?->update(['status' => $status]);
            }
        }
    }

    public function audit(JobOrder $jobOrder, string $action, ?string $from, ?string $to, ?string $notes = null, array $metadata = []): JobOrderAudit
    {
        return $jobOrder->audits()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function inventoryAvailability(array $parts): array
    {
        // Match the same one-to-one name AND unit used at physical issuance.
        // Multiple matching stock records are ambiguous, not combined.
        $normal = static fn ($v): string => mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', trim((string) $v)));
        $unitKey = static fn ($unit): string => (new PartParser())->normalizeUnit((string) $unit);
        $items = InventoryItem::query()->get();
        $requested = collect($parts)->map(static function (array $part) use ($normal, $unitKey): array {
            $name = trim((string) ($part['name'] ?? ''));
            $unit = trim((string) ($part['unit'] ?? ''));
            return ['name'=>$name,'unit'=>$unit,'key'=>$normal($name).'|'.$unitKey($unit),
                'quantity'=>max(1,(int)($part['quantity'] ?? 1))];
        });
        $totals = $requested->groupBy('key')->map(fn ($rows) => $rows->sum('quantity'));
        $breakdown = $requested->map(function (array $part) use ($items, $normal, $unitKey, $totals): array {
            $matching = $items->filter(fn (InventoryItem $item) =>
                $normal($item->parts_name ?? $item->item_name ?? '') === $normal($part['name'])
                && $unitKey($item->unit ?? $item->unit_of_measurement ?? '') === $unitKey($part['unit'])
            )->values();
            $item = $matching->count() === 1 ? $matching->first() : null;
            $stock = $item ? max(0,(int)($item->quantity_available ?? $item->on_hand ?? 0)) : 0;
            $required = (int)$totals->get($part['key'], $part['quantity']);
            return ['name'=>$part['name'],'quantity'=>$part['quantity'],'unit'=>$part['unit'],
                'inventory_item_id'=>$item?->id,'category'=>$item?->category,
                'available'=>$stock,'shortage'=>max(0,$required-$stock),
                'in_stock'=>$stock>0,'fulfillable'=>$stock >= $required,
                'ambiguous'=>$matching->count()>1];
        })->values();
        $total = $totals->sum();
        $available = $breakdown->unique(fn ($part) => $normal($part['name']).'|'.$unitKey($part['unit']))
            ->sum(fn ($part) => min((int)$totals->get($normal($part['name']).'|'.$normal($part['unit']),0),$part['available']));
        return ['status'=>$total===0 || $available >= $total ? 'sufficient' : ($available>0?'partial':'none'),
            'parts'=>$breakdown->all()];
    }
}
