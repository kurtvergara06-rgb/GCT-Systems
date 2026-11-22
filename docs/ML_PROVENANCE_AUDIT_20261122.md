# ML provenance audit — November 22, 2026

## Finding

None of the five model groups passes an independent source-to-artifact provenance audit with the evidence currently retained. A source string in a state file or HTTP response is an assertion, not verification. Availability does not establish production suitability.

| Group | Local artifact evidence | Live read-only status before correction | Audit conclusion |
| --- | --- | --- | --- |
| ETA | State explicitly says synthetic; 362 reported rows | synthetic; production model false | Known synthetic artifact; not genuine operational training evidence |
| Fuel | State claims genuine; 156 reported rows | genuine; model loaded | Claim unverified; original source-row snapshot and traceable artifact lineage not found |
| Delay | State explicitly says sample; 500 reported rows | Contradictory: data_source genuine, source sample, production model false | Sample artifact; genuine claim must not override sample evidence |
| Inventory | State explicitly says sample; 47,872 reported rows | synthetic/source sample; production model false | Known sample artifact; not genuine operational training evidence |
| Auto Scheduling | State claims genuine; bus 367, driver 6 | Hardcoded genuine; bus component loaded, driver fallback | Claim unverified; two different training populations must not be summed |

Counts above are metadata claims, not current independently verified database counts. The scheduling driver pipeline aggregates one training row per driver, not one row per attendance event. The prior 373 total mixed GPS samples and driver-level rows.

## Scope and preservation

The audit inspected retained state files, training loaders, model reports, Git history and the five public Python status endpoints using GET only. No training, migrations, data cleanup or record deletion was performed. No production database rows were read directly or exported. Physical authenticity of original uploads and per-row provenance therefore remain unverified.

`python tools/audit_ml_provenance.py` inventories SHA-256 hashes of retained model/state/report/feature files without deserializing models or changing artifacts. Local results are saved outside version control under storage/logs. Hashes prove preservation, not authenticity; production artifact hashes were not independently downloaded or compared.

The original scheduling bus and driver CSV snapshots are absent from the local training directory. No source-record-ID list, dataset hash or provenance manifest is present in the five canonical state files. This prevents reproducing a source-to-model match from the retained package alone.

## Pipeline risks and distinctions

- Scheduling reads GPS rows with a nonempty bus number and attendance rows without a verified batch-origin exclusion. DemoDataSeeder populates those same tables with randomized GPS/attendance values. This establishes contamination risk, not proof that every historical training row was seeded.
- Fuel joins fuel reports to GPS rows, but does not require an independently verified genuine batch origin. The demo seeder also populates these tables; a joined database row alone is insufficient proof of authenticity.
- ETA's current extractor explicitly joins genuine, verified batch uploads. This is stronger filtering, but does not retroactively certify the retained synthetic artifact or a missing training snapshot.
- Delay's genuine extractor has explicit demo-schedule exclusions. Its retained sample artifact and conflicting runtime source labels still require conservative handling.
- Inventory's genuine path selects application-origin stock movements. That filter is useful but is not a historical audit of the retained sample artifact.
- The legacy scheduling neural JSONL generator explicitly produces synthetic samples. Its separate file is not proof that the active Random Forest used those samples; production policy blocks the legacy synthetic scorer.

## Monitor correction

All five groups remain `Provenance Not Verified` until a separate evidence-backed audit is approved. Reachability and endpoint-reported model loading remain visible separately. No endpoint-provided `genuine`, `is_production_model` or `provenance_verified` flag can bypass the pending audit in the Laravel monitor. Contradictory sample/synthetic source fields take precedence over a genuine claim.

Reported counts are retained as unverified metadata. Verified counts contribute zero, display `Verification Pending`, and scheduling counts remain separate. Unverified candidate benchmarks are not displayed as validated comparisons. Old dashboard snapshots are invalidated with a new cache key. The scheduling status API stops hardcoding genuine provenance; technical readiness flags remain separate.

This change corrects monitoring and status reporting. It does not retrain, delete, overwrite or disable existing prediction artifacts, and it is not approval of their production suitability. Operational recommendation and authorization workflows are unchanged.

## Evidence required before audit approval

1. Original operational source files and approved import history, identifying demo/test origins explicitly.
2. Immutable training snapshots with table/source record IDs, source-file hashes and exclusion decisions.
3. Reconciliation of every training row to that verified source snapshot, including joins and transformations.
4. Artifact hashes tied to the training run, code revision, environment, split strategy and timestamp.
5. Reproducible training/evaluation and reviewer sign-off. Availability, sample volume and a self-reported source string are not substitutes.

## Validation

- Final full Laravel suite: PASS, 1,179 tests / 9,629 assertions, isolated local QA database only.
- Focused Laravel model checks: PASS, 40 tests / 723 assertions.
- Python status-provenance and low-memory unit checks: PASS, 4 tests.
- Existing Python production ML policy and scheduling policy scripts: PASS.
- Frontend build, changed-PHP syntax, Python compilation, Blade clear/cache/clear and git whitespace checks: PASS.
- Before/after SHA-256 artifact inventory: unchanged after verification.
- Production verification is recorded separately after deployment; these test results do not certify data provenance.
