# Optional GPS NLP Enrichment (512 MB Render profile)

## Scope

The existing Laravel GPS CSV/JSON/Excel/PDF extraction, approval flow, database values and source validation are unchanged. When explicitly enabled, post-extraction processing sends up to 25 GPS records per request to the existing Python Engine's rule-based NLP modules. It stores advisory NER, severity and anomaly JSON in `gps_nlp_enrichments` (one row per GPS record). The review table shows an optional summary and flag. These flags **do not auto-approve, rewrite, reclassify or replace any GPS source data**.

The source label is `operational_rules` with `trained_model: false`. No spaCy, PyTorch, transformers, or Python PDF extraction is used by this endpoint.

## Deployment order

1. Pull the feature branch and run `php artisan migrate` on a backed-up database (ordinary additive migration only; **never** `migrate:fresh`).
2. Deploy matching Python Engine changes. Share the same long random value in `GCT_NLP_ENRICH_TOKEN` on Laravel and Python. Do not commit tokens.
3. Keep `GCT_ENABLE_SPACY_NLP=false` and `GCT_ENABLE_PYTHON_PDF=false` on the Python Engine.
4. Configure Laravel: `NLP_API_URL=https://<your-python-engine-host>`, `GCT_GPS_NLP_ENABLED=true`, `GCT_GPS_NLP_CHUNK_SIZE=25`, `GCT_GPS_NLP_TIMEOUT=8`.
5. Run exactly one Laravel queue worker for the new queue: `php artisan queue:work database --queue=gps-nlp --sleep=3 --tries=1 --timeout=45`. It is **separate** from the existing `gps-pdf` queue worker and does not add another Python web worker. Without this queue worker, imported GPS data is unaffected and NLP work remains queued.
6. Verify a small upload before enabling broadly: 25, then 100, 1,000 and 10,000 records. Observe Python RSS/peak memory, timeout counts, throughput, MySQL row counts and review display. Stop/reduce chunk size if peak memory approaches the 512 MB cap.

## Optional existing-batch backfill

After migration, configuration and the dedicated single worker are ready, use
`php artisan gps:nlp-enrich <batch_id>` to enqueue an existing GPS batch.
It does not alter imported GPS values or their review/approval status. To
avoid duplicate concurrent work, run one backfill for a batch at a time.
Reruns update existing advisory rows by GPS record ID.

## Failure isolation

- Disabled by default; nothing calls Python unless the feature flag and token are present.
- Python endpoint rejects missing/invalid shared token and requests containing more than 25 records.
- Laravel caps batch size to 1–25 and HTTP timeout to 2–15 seconds, with 3-second connection timeout.
- A job processes one chunk and queues the following chunk **only on success**. A failed request is logged and enrichment pauses; the GPS import and original batch state remain intact.
- A rerun from record ID 0 is idempotent using a unique GPS record ID and upsert.
- The review screen only reads the advisory table when enabled and the migration exists. No NLP data is used for automatic approval.
- Do not use synchronous queues for this feature. Only the dedicated `gps-nlp` database worker should process these jobs.

## 10,000-record readiness target

A 10,000-record upload is **not** one 10,000-record Python request:
with `GCT_GPS_NLP_CHUNK_SIZE=10` it requires about 1,000 sequential
requests; with 25 it requires about 400 requests. Each queue job reads
only one chunk through indexed record IDs. The feature is resilient to
temporary HTTP failure (two attempts per chunk), and refuses incomplete
or duplicate record responses rather than silently claiming success.

The original Laravel GPS import completes independently of the NLP
enrichment queue. Inspect `gps_nlp_enrichments` count against the
batch's saved GPS record count, not against raw rows that were skipped
during PDF validation. When a chunk fails, the job stops and logs its
cursor; use `php artisan gps:nlp-enrich <batch_id>` for an idempotent
rerun. Start only one backfill per batch at a time.

Run from `python_engine`:

```bash
python tests/benchmark_gps_nlp_10k.py
```

This benchmark runs 10,000 synthetic records through the same rule
endpoint in batches of 25 and reports elapsed time and peak resident
memory. It does **not** measure HTTP/network latency, database workload,
or actual Render peak RSS. Before production enablement, separately
upload 10,000 records to a staging system and confirm all advisory rows
have been written while monitoring Render peak RAM; keep enough headroom
below 512 MB for other Python Engine services.

## Testing

```bash
php artisan test --filter=GpsNlpEnrichmentTest
(cd python_engine && python -m unittest discover -s tests -p 'test_gps_nlp_enrichment.py')
npm run build
php artisan view:clear
php artisan view:cache
```

No model training or performance benchmark is implied by passing the above tests; verify peak memory on Render before production enablement.
