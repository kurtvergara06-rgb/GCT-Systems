<?php

namespace App\Services\Maintenance;

use App\Models\Maintenance\JobOrder;
use App\Models\Maintenance\JobOrderAudit;
use App\Models\Maintenance\JobOrderMechanic;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use App\Models\Warehouse\InventoryItem;
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
        $busyLegacyNames = JobOrder::query()
            ->where('status', 'On Going')
            ->where('review_status', 'Approved')
            ->when($jobOrder, fn ($query) => $query->whereKeyNot($jobOrder->id))
            ->whereDoesntHave('mechanicAssignments')
            ->pluck('assigned_mechanic')
            ->flatMap(fn ($names) => preg_split('/\s*,\s*/', (string) $names) ?: [])
            ->filter();

        return Mechanic::query()
            ->where('employment_status', 'Active')
            ->whereNotIn('id', $busyIds)
            ->whereNotIn('mechanic_name', $busyLegacyNames)
            ->whereHas('attendances', fn ($query) => $query
                ->whereDate('attendance_date', $today)
                ->whereIn('status', ['Present', 'Late', 'On Duty']))
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
        // Evaluate duplicates against total requested demand, not each row alone.
        $items = InventoryItem::query()->get()->keyBy(
            fn (InventoryItem $item) => mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $item->parts_name))
        );
        $normalizedParts = collect($parts)->map(static function (array $part): array {
            $name = trim((string) ($part['name'] ?? ''));
            return [
                'name' => $name,
                'key' => mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', $name)),
                'quantity' => max(1, (int) ($part['quantity'] ?? 1)),
                'unit' => trim((string) ($part['unit'] ?? '')),
            ];
        });
        $totals = $normalizedParts->groupBy('key')
            ->map(fn ($group) => $group->sum('quantity'));

        $breakdown = $normalizedParts->map(function (array $part) use ($items, $totals): array {
            $item = $items->get($part['key']);
            $onHand = $item ? max(0, (int) $item->on_hand) : 0;
            $groupQuantity = (int) $totals->get($part['key'], $part['quantity']);
            return [
                'name' => $part['name'],
                'quantity' => $part['quantity'],
                'unit' => $part['unit'],
                'inventory_item_id' => $item?->id,
                'category' => $item?->category,
                'available' => $onHand,
                'shortage' => max(0, $groupQuantity - $onHand),
                'in_stock' => $onHand > 0,
                'fulfillable' => $onHand >= $groupQuantity,
            ];
        })->values();

        $availableGroups = $totals->sum(function ($total, $key) use ($items): int {
            $onHand = max(0, (int) ($items->get($key)?->on_hand ?? 0));
            return min((int) $total, $onHand);
        });
        $totalNeeded = $totals->sum();
        $status = $totalNeeded === 0 || $availableGroups >= $totalNeeded
            ? 'sufficient'
            : ($availableGroups > 0 ? 'partial' : 'none');

        return ['status' => $status, 'parts' => $breakdown->all()];
    }
}
