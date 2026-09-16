# MODEL #1 ETA — GPS REPORT STRUCTURE AUDIT

**Date:** 2026-09-16
**Auditor:** Automated audit via opencode
**Scope:** Does the current GCT Systems pipeline correctly distinguish one shuttle trip from an entire day's vehicle activity period? Can a 14-hour GPS report be treated as one trip in Model #1 ETA training?
**Status:** COMPLETE — READ-ONLY, no files modified

---

## 1. EXECUTIVE SUMMARY

The current GCT Systems pipeline **extracts GPS reports correctly** and **stores them faithfully**, but has **no concept of trip boundaries**. Each PDF table row or CSV row becomes one `GpsTripRecord` — whether that row represents a 45-minute shuttle trip or a 14-hour vehicle activity period is indeterminate from the data itself.

Model #1 ETA assumes `g.duration_minutes` (a single GPS field) equals actual shuttle trip duration. There is **no upper-bound validation** on `duration_minutes` anywhere in the pipeline — not at upload, not at storage, not at training. The current training data (362 rows, max 92 minutes) contains only valid shuttle trips, because GPS records without a linked `trip_assignment_id` are silently dropped by the LEFT JOINs in the training SQL. But the **data model allows** a 14-hour activity record to enter training if Operations links it to a trip assignment.

**Verdict: NOT ALIGNED** for the specific scenario of a 14-hour vehicle activity report being treated as one shuttle trip.

---

## 2. BATCH FILE PROCESSING AUDIT

### A. Fields Extracted/Saved

`BatchFileProcessingController::saveRecord()` (line 1199–1232) creates a `GpsTripRecord` with:

| Field | Source | Notes |
|---|---|---|
| `record_no` | "Record No." column | Nullable |
| `bus_no` | "Bus No." / "Vehicle" | Nullable (required only for PDF path) |
| `grouping` | "Grouping" / "Route" | GPS route label |
| `trip_type` | "Type" / "Trip Type" | Nullable |
| `beginning_at` | "Beginning" / "Start" | DateTime |
| `initial_location` | "Initial Location" / "Origin" | |
| `ending_at` | "End" / "Ending" | DateTime |
| `final_location` | "Final Location" / "Destination" | |
| `duration_minutes` | "Duration" | **No upper bound check** |
| `total_minutes` | "Total Time" | Falls back to duration_minutes (line 1395) |
| `in_motion_minutes` | "In Motion" | |
| `idling_minutes` | "Idling" | |
| `mileage_km` | "Mileage" / "Distance" | |
| `engine_hours` | "Engine Hours" | |
| `location` | "Location" / "Site" | |
| `coordinates` | "Coordinates" / "GPS" | |
| `description` | "Remarks" / "Notes" | |
| `severity` | Computed from idle minutes | Normal/Low/Medium/High (line 1662–1674) |
| `source_format` | Hardcoded | "GPS Report" / "CSV" / etc. |
| `raw_data` | Original parsed row | JSON blob |

**Key fields preserved:** Beginning, End, Duration, Total Time, In Motion, Idling, Mileage, Engine Hours, Bus No., Grouping, Location, Coordinates, Description, Severity, Record No., Trip Type.

**Key fields NOT present in GPS data (missing at source):**
- `trip_ticket` / trip ID (not in GPS reports)
- `shift` (not in GPS reports; comes from trip_schedules)
- `route_id` (not in GPS reports; comes from shuttle_routes)
- `distance_km` (not in GPS reports; comes from shuttle_routes)
- `estimated_time_minutes` (not in GPS reports; comes from shuttle_routes)

### B. How PDF Determines Records

**`processPdfFile()`** (`app/Http/Controllers/Admin/BatchFileProcessingController.php:1005–1197`):
1. Sends PDF to Python NLP endpoint `POST /nlp/extract-pdf`
2. Response contains `records[]` (each = one GpsTripRecord payload)
3. Iterates records, calls `saveRecord()` for each

**`extract_pdf_data()`** (`python_engine/main.py:223–557`):
1. `extract_pdf_rows()` (`python_engine/NLP/pdf_extractor.py:308–419`) — finds tables page-by-page via PyMuPDF. For each table, classifies it as **key-value** or **standard data table**.

2. `infer_records_from_table_rows()` (`python_engine/NLP/entity_extractor.py:228–379`):
   - **Key-value tables**: each table block → **ONE record** (lines 254–277)
   - **Standard data tables**: finds header row, then each subsequent data row → **ONE record** (lines 320–363)
   - Header-like rows are skipped (lines 325–328)

3. **Fallback** (no tables found): extracts ONE record from the entire PDF text (`main.py:389–481`)

### C. Does the PDF parser treat each row as a trip?

**YES.** `entity_extractor.py:320–363` — each data row after the header becomes exactly one `GpsTripRecord`. There is no distinction between a row representing a single trip vs. a row representing an entire vehicle activity period.

### D. Does it combine multiple sections of the same GPS report?

**NO.** Each key-value table block on each page is an independent record. Each standard data row is an independent record. If a GPS report has 3 pages × 2 blocks/page, that produces 6 separate records with no linkage between them.

### E. Existing Trip Boundary/Grouping Concepts

| Concept | Exists? | Evidence |
|---|---|---|
| `grouping` (route label) | YES | Saved from "Grouping"/"Route" column |
| `trip_type` | YES | Saved from "Type"/"Trip Type" column |
| `record_no` | YES | Saved from "Record No." column |
| Trip boundary detection | **NO** | Not in any file |
| Trip ticket | **NO** | No unique trip-ID concept |
| Route ID (FK) | **NO** | Only text `grouping`, not linked to `shuttle_routes.id` |
| GPS session | **NO** | Not modeled |
| Daily vehicle report | **NO** | Not modeled |
| Activity period splitting | **NO** | Not modeled |
| In-motion threshold | **NO** | Stored but never used for filtering |

---

## 3. GPS DATABASE AUDIT

### Full schema (after all 4 migrations):

```
gps_trip_records
├── id (bigint, PK)
├── batch_upload_id (FK → batch_uploads)
├── trip_assignment_id (FK → trip_assignments, nullable) ← added 2026-09-04
├── record_no (string, nullable)
├── bus_no (varchar(50), nullable)
├── grouping (string, nullable)
├── trip_type (string, nullable)
├── beginning_at (datetime, nullable)
├── initial_location (text, nullable)
├── ending_at (datetime, nullable)
├── final_location (text, nullable)
├── duration_minutes (int, nullable) ← NO UPPER BOUND
├── total_minutes (unsigned int, nullable)
├── in_motion_minutes (unsigned int, nullable)
├── idling_minutes (unsigned int, nullable)
├── mileage_km (decimal 10,2, nullable)
├── engine_hours (decimal 10,2, nullable)
├── severity (string, default "Normal")
├── location (string, nullable)
├── coordinates (string, nullable)
├── description (text, nullable)
├── source_format (string, nullable)
├── raw_data (json, nullable)
└── timestamps
```

### Can represent:

| Scenario | Possible? | Limitation |
|---|---|---|
| One shuttle trip (45 min) | YES | — |
| Entire day's vehicle activity (14 h) | YES | **Indistinguishable from a trip** |
| Multiple trips in one report | YES (multiple rows) | **No linkage between related rows** |

The schema stores GPS data faithfully but has **no metadata to distinguish a trip from an activity period**. The `trip_type` field exists but is whatever the client's GPS report contains — not system-enforced.

---

## 4. MODEL #1 ETA INPUT AUDIT

### A. Does Model #1 assume one GPS record = one trip?

**YES.** `python_engine/eta/training_data.py:66–90` (`fetch_trip_outcomes()`):

```python
sql = """
    SELECT
        g.id AS gps_record_id,
        g.bus_no,
        g.`grouping` AS route,
        g.beginning_at,
        g.duration_minutes,
        ts.shift,
        ts.departure_time,
        sr.distance_km,
        sr.estimated_time_minutes AS route_estimated_time_minutes
    FROM gps_trip_records g
    LEFT JOIN trip_assignments ta ON ta.id = g.trip_assignment_id
    LEFT JOIN trip_schedules ts ON ts.id = ta.trip_schedule_id
    LEFT JOIN shuttle_routes sr ON sr.id = ts.shuttle_route_id
    WHERE g.duration_minutes IS NOT NULL
      AND g.duration_minutes > 0
"""
```

Each `gps_trip_records` row = one training sample. No trip-boundary logic.

### B. Target field

`python_engine/eta/training_data.py:78`: `g.duration_minutes` → `ETA_TARGET = "actual_trip_duration"` (line 37, 158).

**Critical**: `build_dataset()` line 158:
```python
out[ETA_TARGET] = out["duration_minutes"].clip(lower=1.0)
```

Clipped to **lower=1.0 only**. No upper bound.

### C. Features from GPS fields

| Feature | GPS/DB Source | Code |
|---|---|---|
| `route_encoded` | `g.grouping` via `shuttle_routes` | `training_data.py:146–148` |
| `distance_km` | `sr.distance_km` | `training_data.py:155` |
| `route_estimated_time_minutes` | `sr.estimated_time_minutes` | `training_data.py:156` |
| `departure_hour` | `g.beginning_at.hour` | `training_data.py:135` |
| `day_of_week` | `g.beginning_at.dayofweek` | `training_data.py:136` |
| `is_weekend` | Derived from day_of_week | `training_data.py:137` |
| `shift_encoded` | `ts.shift` | `training_data.py:140–142` |
| `bus_no_encoded` | `g.bus_no` | `training_data.py:150–152` |

### D. Can an activity period enter as trip duration?

**YES**, if:
1. The GPS record has `duration_minutes > 0` (passes WHERE clause at line 87–88)
2. The GPS record has a valid `trip_assignment_id` (so the LEFT JOINs provide route/distance/shift metadata)
3. `beginning_at` is valid (for departure_hour/day_of_week)

There is **no upper-bound filter** on `duration_minutes` in the SQL (line 87–88) or in `build_dataset()` (line 158).

### E. Prediction clipping

`python_engine/eta/predict.py:225–228`:
```python
target_range = _state.get("target_range") or {}
lo = float(target_range.get("min", 1.0)) if target_range else 1.0
hi = float(target_range.get("max", 240.0)) if target_range else 240.0
predicted = max(min(predicted, hi), lo)
```

If a 14-hour (840 min) record enters training, `target_range.max` becomes 840, and the model would predict up to 840 minutes for new trips.

---

## 5. DATA FLOW: GPS REPORT → MODEL #1 ETA

```
CLIENT GPS PDF/CSV
  ↓ (upload)
BatchFileProcessingController::upload() [line 144]
  ↓ (for PDF: NLP service)
processPdfFile() [line 1005] → POST /nlp/extract-pdf
  ↓ (python NLP)
extract_pdf_rows() → infer_records_from_table_rows()
  EACH key-value block / EACH data row → ONE record
  ↓ (return JSON with records[])
BatchFileProcessingController → saveRecord() [line 1199]
  → GpsTripRecord::create() [line 1204]
  → trip_assignment_id = NULL (NOT set during upload)
  ↓ (In Review → Processed)
Operations user reviews → marks as "Processed"
  ↓ (later, manual)
Operations links GPS records to trip_assignments
  trip_assignment_id populated
  ↓ (ETA training)
eta/training_data.py:fetch_trip_outcomes()
  SQL joins gps_trip_records → trip_assignments → trip_schedules → shuttle_routes
  Only records WITH trip_assignment_id appear in training
  ↓ (target)
duration_minutes from gps_trip_records → actual_trip_duration → ETA model
```

**Critical observation:** GPS records without `trip_assignment_id` are silently dropped from training by the LEFT JOINs + `dropna()` at `training_data.py:174`. This is why the current 362-row training set has no outliers. But if Operations links a 14-hour GPS record to a trip assignment, it enters training without any validation.

---

## 6. CURRENT TRAINING DATA ANALYSIS

### Model #1 ETA (`training_data/eta_trip_duration_training.csv`)

| Metric | Value |
|---|---|
| Rows | 362 |
| Distinct routes | 5 |
| Distinct buses | 11 |
| Min duration | 14 min |
| Max duration | 92 min |
| Mean duration | 43.8 min |
| Std deviation | 14.14 min |
| Records ≥ 300 min (5h) | 0 |
| Records ≥ 480 min (8h) | 0 |
| Records ≥ 600 min (10h) | 0 |
| Max duration in hours | 1.53 h |
| Date range | 2026-05-20 → 2026-08-31 |

**No multi-hour outliers exist in the current data.** This is because unmatched GPS records (no `trip_assignment_id`) are silently dropped.

---

## 7. ALIGNMENT VERDICT TABLE

| # | From | To | Status | Issue |
|---|---|---|---|---|
| 1 | Client GPS Report | Batch File Processing | **ALIGNED** | All GPS fields are parsed and saved |
| 2 | Batch File Processing | GpsTripRecord | **ALIGNED** | All extracted fields are stored in the schema |
| 3 | GpsTripRecord | Analytics (trip_assignments) | **PARTIALLY ALIGNED** | Records must have `trip_assignment_id` to be visible to models |
| 4 | GpsTripRecord | Model #1 ETA | **NOT ALIGNED** | No validation that `duration_minutes` represents a trip, not an activity period |
| 5 | One GPS report | One ETA training trip | **NOT ALIGNED** | A 14-hour activity report = one 840-min "trip" if linked to a trip assignment |

---

## 8. DIRECT ANSWER TO THE 14-HOUR QUESTION

**Can a 14-hour GPS vehicle activity report be treated as one shuttle trip in Model #1 ETA?**

**NO — this is unsafe, and the current code does not prevent it.**

### Evidence:

1. **No upper-bound filter in SQL** (`training_data.py:87–88`):
   ```python
   WHERE g.duration_minutes IS NOT NULL
     AND g.duration_minutes > 0
   ```
   A 14-hour (840 min) record passes this filter.

2. **No upper-bound clip on target** (`training_data.py:158`):
   ```python
   out[ETA_TARGET] = out["duration_minutes"].clip(lower=1.0)
   ```
   Only clipped to lower=1.0. An 840-min record stays at 840.

3. **No duration validation at upload** (`BatchFileProcessingController.php:1459–1480`):
   ```php
   'duration_minutes' => ['nullable', 'numeric', 'min:0'],
   ```
   No `max:` rule.

4. **Prediction clips to training range** (`predict.py:225–228`):
   ```python
   hi = float(target_range.get("max", 240.0)) if target_range else 240.0
   predicted = max(min(predicted, hi), lo)
   ```
   If 840 is the training max, predictions would clip to 840 — distorting all outputs.

5. **Current safety net** (implicit, fragile): GPS records without `trip_assignment_id` are dropped by the LEFT JOINs + `dropna()`. But this relies on Operations not linking activity-period records to trip assignments. There is no explicit guard.

### What makes the client's GPS report a vehicle activity report:

A report with Total Time=14h, In Motion=3h, Idling=4h06m, Mileage=101km is a **vehicle daily summary** — the entire vehicle's activity from shift start to shift end. One shuttle trip on the current routes (12–18 km, 22–55 min) would have Total Time≈25–60 min, In Motion≈20–50 min, Mileage≈5–18 km.

---

## 9. MINIMUM RECOMMENDED CHANGE

### Option A (recommended): Add upper-bound filter in training SQL

**File:** `python_engine/eta/training_data.py:87–88`

**Current:**
```python
WHERE g.duration_minutes IS NOT NULL
  AND g.duration_minutes > 0
```

**Proposed:**
```python
WHERE g.duration_minutes IS NOT NULL
  AND g.duration_minutes > 0
  AND g.duration_minutes <= 720
```

**Rationale:** 720 min (12 hours) is well above any plausible shuttle trip but below a full shift. This is the single highest-impact change — it prevents multi-hour activity periods from silently entering ETA training while preserving the client's raw GPS data in `gps_trip_records`.

**Risk:** Low. The current max is 92 min. No real trip would be excluded.

### Option B (defense-in-depth): Add warning flag at upload

**File:** `app/Http/Controllers/Admin/BatchFileProcessingController.php`, inside `saveRecord()` (after line 1232)

**Proposed:** After creating the GPS record, if `duration_minutes > 720` and `total_minutes > 720`, add a flag to `raw_data`:
```php
if ($duration > 720 && $total > 720) {
    $rawData['_activity_period_warning'] = true;
    $record->raw_data = $rawData;
    $record->save();
}
```

**Rationale:** Flags suspicious records for Operations reviewers without rejecting or altering the GPS data.

### Option C (reviewer aid): Add in_motion_ratio computed attribute

**File:** `app/Models/Admin/GpsTripRecord.php`

**Proposed:**
```php
public function getInMotionRatioAttribute(): ?float
{
    if (!$this->total_minutes || $this->total_minutes == 0) {
        return null;
    }
    return round($this->in_motion_minutes / $this->total_minutes, 3);
}
```

**Rationale:** A ratio < 0.25 (3h in 14h) strongly suggests an activity period rather than a single trip. Helps Operations reviewers distinguish the two.

### What NOT to do:

- **Do NOT** redesign the database schema
- **Do NOT** change the ETA model architecture
- **Do NOT** reject GPS records at upload (preserve client data)
- **Do NOT** add fabricated fields (accident_flag, traffic_flag, etc.)
- **Do NOT** change the fuel/delay/inventory models (they don't use duration_minutes as target)

---

## 10. EXACT FILES + FUNCTIONS + LINE NUMBERS

| Component | File | Function/Line | Finding |
|---|---|---|---|
| PDF row extraction | `python_engine/NLP/pdf_extractor.py` | `extract_pdf_rows()` :308–419 | Tables per page; key-value vs standard |
| PDF record creation | `python_engine/NLP/entity_extractor.py` | `infer_records_from_table_rows()` :228–379 | Each data row → one record |
| PDF fallback | `python_engine/main.py` | `extract_pdf_data()` :389–481 | ONE record from entire text |
| GPS save | `app/Http/Controllers/Admin/BatchFileProcessingController.php` | `saveRecord()` :1199–1233 | All GPS fields; no trip_assignment_id; no duration check |
| GPS field mapping | `app/Http/Controllers/Admin/BatchFileProcessingController.php` | `mapUnifiedRecord()` :1235–1457 | Duration from "Duration" column |
| Duration parsing | `app/Http/Controllers/Admin/BatchFileProcessingController.php` | `durationToMinutes()` :1592–1647 | HH:MM:SS or "X hours" format |
| Validation rules | `app/Http/Controllers/Admin/BatchFileProcessingController.php` | `recordValidationRules()` :1459–1480 | `nullable, numeric, min:0` — no upper bound |
| GPS model | `app/Models/Admin/GpsTripRecord.php` | :9–49 | All GPS fields; trip_assignment_id nullable |
| Schema (base) | `database/migrations/2026_06_21_030826_create_gps_trip_records_table.php` | :11–44 | Core GPS fields |
| Schema (+trip link) | `database/migrations/2026_09_04_000000_add_actual_times_and_gps_linkage.php` | :20–23 | trip_assignment_id nullable FK |
| ETA SQL | `python_engine/eta/training_data.py` | `fetch_trip_outcomes()` :66–90 | `WHERE duration_minutes > 0` — no upper bound |
| ETA target clip | `python_engine/eta/training_data.py` | `build_dataset()` :158 | `clip(lower=1.0)` — no upper bound |
| ETA features | `python_engine/eta/training_data.py` | `build_dataset()` :119–175 | 8 features; route from grouping |
| ETA model | `python_engine/eta/model.py` | `train_eta_model()` :51–132 | 200-tree RF; 8 features |
| ETA predict clip | `python_engine/eta/predict.py` | `predict_trip_duration()` :225–228 | Clips to training target_range max |
| Current ETA data | `training_data/eta_trip_duration_training.csv` | — | 362 rows; 14–92 min; no outliers |

---

## APPENDIX: EVIDENCE FILE LIST

| File | Role | Read during audit? |
|---|---|---|
| `app/Http/Controllers/Admin/BatchFileProcessingController.php` | PDF/CSV extraction, staging, approval | YES |
| `app/Http/Controllers/Admin/GenericBatchFileProcessingController.php` | Non-GPS modules (maintenance/warehouse) | YES |
| `app/Models/Admin/GpsTripRecord.php` | GPS record model (34 fields) | YES |
| `database/migrations/2026_06_21_030826_create_gps_trip_records_table.php` | GPS schema base | YES |
| `database/migrations/2026_08_03_182016_alter_gps_trip_records_add_total_inmotion_columns.php` | GPS schema: total_minutes, in_motion_minutes | YES |
| `database/migrations/2026_08_06_230048_alter_gps_trip_records_add_idling_mileage_engine.php` | GPS schema: idling, mileage, engine_hours | YES |
| `database/migrations/2026_09_04_000000_add_actual_times_and_gps_linkage.php` | GPS schema: trip_assignment_id FK | YES |
| `python_engine/NLP/pdf_extractor.py` | PDF text/table extraction | YES |
| `python_engine/NLP/entity_extractor.py` | Row-to-record inference | YES |
| `python_engine/main.py` | `/nlp/extract-pdf` endpoint | YES |
| `python_engine/eta/training_data.py` | ETA dataset builder (DB query, features) | YES |
| `python_engine/eta/model.py` | ETA RF training | YES |
| `python_engine/eta/predict.py` | ETA prediction service | YES |
| `python_engine/eta/router.py` | ETA FastAPI router | YES |
| `python_engine/eta/config.py` | ETA paths and thresholds | YES |
| `python_engine/eta/prepare_training_data.py` | ETA CLI data prep | YES |
| `python_engine/eta/train_model.py` | ETA CLI training | YES |
| `training_data/eta_trip_duration_training.csv` | ETA training data (362 rows) | YES (stats computed) |
