<?php

namespace App\Console\Commands;

use App\Jobs\EnrichGpsNlpChunk;
use App\Models\Admin\BatchUpload;
use Illuminate\Console\Command;

class QueueGpsNlpEnrichment extends Command
{
    protected $signature = 'gps:nlp-enrich {batch_id : Existing GPS batch ID}';
    protected $description = 'Queue optional, read-only NLP enrichment for an existing GPS upload';

    public function handle(): int
    {
        if (! config('gps_nlp.enabled') || ! config('gps_nlp.token')) {
            $this->error('GPS NLP is disabled or GCT_NLP_ENRICH_TOKEN is missing.');
            return self::FAILURE;
        }

        $batch = BatchUpload::find($this->argument('batch_id'));
        if (! $batch || $batch->module !== 'Operation' || $batch->data_type !== 'GPS Trip Records'
            || ! in_array($batch->status, ['In Review', 'Processed'], true)) {
            $this->error('Select an existing completed extraction in the GPS Trip Records profile.');
            return self::FAILURE;
        }

        EnrichGpsNlpChunk::enqueueIfEnabled($batch->id);
        $this->info("Queued advisory NLP enrichment for GPS batch {$batch->id}.");
        $this->line('Run one database queue worker on gps-nlp to process the queued chunks.');
        return self::SUCCESS;
    }
}
