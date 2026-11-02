<?php

namespace App\Console\Commands;

use App\Models\Admin\BatchUpload;
use App\Models\Admin\GpsTripRecord;
use App\Services\Gps\PdfGpsRuleParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RepairGpsPdfBatch extends Command
{
    protected $signature = 'gps:repair-pdf-batch
        {batch : Existing PDF batch ID}
        {--apply : Reconcile the In Review batch}
        {--sync-source-values : Replace stored numeric values with values derived from the matching PDF rows}';

    protected $description = 'Preview or reconcile an In Review GPS PDF batch without deleting existing records';

    public function handle(PdfGpsRuleParser $parser): int
    {
        $batch = BatchUpload::find($this->argument('batch'));
        if (! $batch || strtolower((string) $batch->file_type) !== 'pdf'
            || $batch->status !== 'In Review'
            || ! $batch->file_path
            || ! Storage::disk('public')->exists($batch->file_path)) {
            $this->error('An In Review PDF batch with its original file is required.');

            return self::FAILURE;
        }

        $source = collect($parser->extract($batch->file_path)['records'])->keyBy('record_no');
        $found = [];
        $matched = 0;
        $changed = 0;
        $mismatched = 0;
        $apply = (bool) $this->option('apply');
        $syncSourceValues = (bool) $this->option('sync-source-values');

        $batch->tripRecords()->orderBy('id')->chunkById(200, function ($records) use (
            $source, &$found, &$matched, &$changed, &$mismatched, $apply, $syncSourceValues
        ): void {
            $updates = [];
            foreach ($records as $record) {
                $item = $source->get($record->record_no);
                // An older parser truncated identifiers containing an underscore.
                // Recover only when the row suffix and both exact timestamps
                // identify one source row for the same vehicle prefix.
                if (! $item && $record->record_no && $record->bus_no) {
                    $suffix = strrchr($record->record_no, '-');
                    $candidates = $source->filter(fn ($candidate) => $suffix
                        && str_ends_with($candidate['record_no'], $suffix)
                        && str_starts_with($candidate['bus_no'], $record->bus_no.'_')
                        && $candidate['beginning'] === $record->beginning_at?->format('Y-m-d H:i:s')
                        && $candidate['ending'] === $record->ending_at?->format('Y-m-d H:i:s'));
                    if ($candidates->count() === 1) {
                        $item = $candidates->first();
                    }
                }
                if (! $item || ($record->bus_no !== $item['bus_no']
                    && ! str_starts_with($item['bus_no'], $record->bus_no.'_'))
                    || $record->beginning_at?->format('Y-m-d H:i:s') !== $item['beginning']
                    || $record->ending_at?->format('Y-m-d H:i:s') !== $item['ending']) {
                    $mismatched++;
                    continue;
                }
                $found[$record->record_no] = true;
                $found[$item['record_no']] = true;
                $matched++;
                $fields = [];
                if ($record->record_no !== $item['record_no']) {
                    $fields['record_no'] = $item['record_no'];
                    $fields['bus_no'] = $item['bus_no'];
                }
                foreach (['grouping', 'initial_location', 'final_location'] as $field) {
                    if (blank($record->{$field}) && filled($item[$field] ?? null)) {
                        $fields[$field] = $item[$field];
                    }
                }
                foreach (['mileage_km', 'engine_hours', 'duration_minutes', 'total_minutes', 'in_motion_minutes', 'idling_minutes'] as $field) {
                    $sourceValue = $item[$field] ?? null;
                    $tolerance = $field === 'engine_hours' ? 0.0000005 : 0.00005;
                    if ($sourceValue !== null && ($record->{$field} === null
                        || ($syncSourceValues && abs((float) $record->{$field} - $sourceValue) > $tolerance))) {
                        $fields[$field] = $item[$field];
                    }
                }
                $raw = $record->raw_data ?? [];
                if (($raw['source_values'] ?? null) != $item['raw_data']['source_values']
                    || ($raw['source_warnings'] ?? null) != $item['raw_data']['source_warnings']) {
                    $raw['source_values'] = $item['raw_data']['source_values'];
                    $raw['source_warnings'] = $item['raw_data']['source_warnings'];
                    $fields['raw_data'] = $raw;
                }
                if ($fields !== []) {
                    $changed++;
                    $updates[] = [$record, $fields];
                }
            }
            if ($apply && $updates !== []) {
                DB::transaction(function () use ($updates): void {
                    foreach ($updates as [$record, $fields]) {
                        $record->update($fields);
                    }
                });
            }
        });

        $missingItems = $source->reject(fn ($item) => isset($found[$item['record_no']]));
        $conflicts = 0;
        $inserted = 0;
        foreach ($missingItems as $item) {
            $overlap = GpsTripRecord::query()
                ->where('batch_upload_id', '!=', $batch->id)
                ->where('bus_no', $item['bus_no'])
                ->where('beginning_at', $item['beginning'])
                ->where('ending_at', $item['ending'])
                ->whereHas('batchUpload', fn ($query) => $query->whereIn('status', ['Processing', 'In Review', 'Processed']))
                ->exists();
            if ($overlap) {
                $conflicts++;
                continue;
            }
            if ($apply) {
                GpsTripRecord::create([
                    'batch_upload_id' => $batch->id,
                    'record_no' => $item['record_no'],
                    'bus_no' => $item['bus_no'],
                    'grouping' => $item['grouping'],
                    'trip_type' => $item['trip_type'],
                    'beginning_at' => $item['beginning'],
                    'initial_location' => $item['initial_location'],
                    'ending_at' => $item['ending'],
                    'final_location' => $item['final_location'],
                    'duration_minutes' => $item['duration_minutes'],
                    'total_minutes' => $item['total_minutes'],
                    'in_motion_minutes' => $item['in_motion_minutes'],
                    'idling_minutes' => $item['idling_minutes'],
                    'engine_hours' => $item['engine_hours'],
                    'mileage_km' => $item['mileage_km'],
                    'description' => $item['description'],
                    'source_format' => 'GPS Engine Hours Report',
                    'severity' => 'Normal',
                    'raw_data' => $item['raw_data'],
                ]);
                $inserted++;
            }
        }
        if ($apply) {
            $batch->update([
                'total_records' => $source->count(),
                'processed_records' => $batch->tripRecords()->count(),
                'failed_records' => $mismatched + $conflicts,
            ]);
        }
        $this->line(($apply ? 'Applied' : 'Dry run').": matched {$matched}; records needing updates {$changed}; unmatched existing {$mismatched}; source rows to add {$missingItems->count()}; conflicts {$conflicts}; inserted {$inserted}.");
        if ($mismatched || $conflicts) {
            $this->warn('Unmatched or conflicting rows require manual review.');
        }

        return self::SUCCESS;
    }
}
