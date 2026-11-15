# Operation Multi-Bus Compatibility Audit

## Scope and decision

The current implementation is safe for one assignment per schedule. It is not
safe to remove the unique `trip_assignments.trip_schedule_id` constraint by
itself. Several production paths assume that a schedule has exactly one
assignment and would silently select the wrong driver or bus after that change.

The backward-compatible direction is to keep `trip_schedules` as the planned
service and promote each existing `trip_assignments` row into the operational
run for one driver and one bus. Existing assignment IDs must be preserved
because Daily Driver Reports and Incidents already use them as their most
specific historical reference.

No relationship or schema change was made during this audit.

## Current reference audit

| Area | Current reference | Multi-bus risk | Required transition |
|---|---|---|---|
| Trip Schedule | `TripSchedule::assignment()` is `HasOne`; `trip_assignments.trip_schedule_id` is unique | A second bus cannot be represented | Add `assignments()` as `HasMany`; keep the legacy accessor temporarily while all consumers are migrated |
| Assignment UI | Creating one assignment changes the schedule to `assignment_status=Assigned` and `status=Ready` | The first of three buses would incorrectly make the service look completely assigned | Derive `Unassigned`, `Partially Assigned`, or `Fully Assigned` from assignment count versus `required_buses`; do not infer execution completion |
| Auto Scheduling | Recommendation IDs require distinct schedule IDs and confirmation creates one row per schedule | It cannot generate or confirm multiple slots for one schedule | Recommend explicit service slots and validate every driver/bus run independently |
| Dispatch lifecycle | There is no dedicated route/controller for dispatching or completing an individual assignment; execution status is stored on the schedule | One shared schedule status cannot describe three independently dispatched/completed buses | Add per-assignment run status and timestamps before enabling multiple assignments |
| Trip Records | Queries historical `TripSchedule` rows and loads one `assignment.bus` | Only one bus/driver would be exported or displayed | Show a service header with run rows, or export one row per assignment while retaining the service code |
| Daily Driver Reports | Stores both `trip_schedule_id` and `trip_assignment_id` with `nullOnDelete` FKs | The columns are compatible, but matching currently resolves through singular `assignment()` and can attach the wrong run | Resolve and persist the exact assignment by schedule, driver, bus, and ticket; never select an arbitrary assignment |
| DDR additional trips | Stores both IDs after matching | Uses the same singular matching assumption | Apply the exact-assignment matcher to every entry |
| Incidents | Accepts an assignment ID, but removes it before insert; the model backfills the first assignment found for the schedule | A multi-bus incident could be attached to the wrong vehicle run | Persist the validated `trip_assignment_id` directly and verify it belongs to the submitted schedule |
| Active bus breakdown | Unique key is currently `trip:{trip_schedule_id}` | It allows only one active breakdown for the entire grouped service | Key active breakdowns by assignment/run ID, with a legacy fallback only for unassigned historical incidents |
| Replacement bus | `IncidentReplacement` finds the assignment by schedule ID | It could replace the wrong bus | Update only `incident.trip_assignment_id`; preserve that run's original/effective bus chain |
| Analytics and prediction | Several controllers load singular `assignment` to obtain bus and driver | Results would omit other runs or select an arbitrary run | Aggregate runs deliberately or emit one prediction/metric row per assignment |
| Historical protection | Assignment mutation is blocked when a linked DDR or Incident exists | This is compatible and must remain | Check history against the exact assignment plus the parent schedule |

## Status semantics

The following states must remain independent:

- Service assignment status on `trip_schedules`: `Unassigned`,
  `Partially Assigned`, `Fully Assigned`.
- Operational run status on each assignment: `Ready`, `Dispatched`,
  `Completed`, `Cancelled`, or `Missed`.
- Optional service lifecycle status on the schedule: planning/cancellation/closure
  of the overall scheduled service, not the completion state of an individual
  bus run.

For example, a service requiring three buses can be `Fully Assigned` while its
three runs are respectively `Completed`, `Dispatched`, and `Ready`.

## Recommended staged implementation

### Stage 1: Compatibility layer

1. Add `TripSchedule::assignments()` without removing `assignment()`.
2. Add a stable assignment slot number and per-run status/timestamps to
   `trip_assignments`.
3. Backfill every existing assignment as slot 1 and copy the existing schedule
   execution status/timestamps to that run.
4. Add a unique `(trip_schedule_id, slot_number)` constraint.
5. Do not remove the current unique schedule constraint in this stage.

### Stage 2: Consumer migration

1. Make DDR and Incident creation persist an exact `trip_assignment_id`.
2. Make replacement dispatch update the incident's exact assignment.
3. Move Trip Records, exports, analytics, availability, and operational-history
   checks from the singular relation to explicit run-aware behavior.
4. Add individual dispatch/complete/cancel transitions on assignments.
5. Derive schedule assignment status from locked assignment counts.

### Stage 3: Enable multiple assignments

1. Run production preflight checks for orphaned or mismatched schedule and
   assignment IDs.
2. Drop only the unique constraint on `trip_assignments.trip_schedule_id`.
3. Keep the foreign key and the new unique slot constraint.
4. Update manual and automatic scheduling to create only the missing slots up
   to `required_buses`.
5. Revalidate conflicts inside locked transactions for every slot.

## Required migration preflight

Before the constraint change, verification must prove:

- Every non-null DDR assignment belongs to its stored schedule.
- Every non-null Incident assignment belongs to its stored schedule.
- No assignment slot duplicates exist.
- Existing assignment count never exceeds `required_buses` without an explicit
  remediation decision.
- Active breakdown keys can be converted without collisions.
- No historical assignment would be deleted or renumbered.

The migration must abort with a descriptive exception when any preflight check
fails. It must never repair historical records by guessing.

## Required regression coverage

- One, partial, and fully assigned services.
- Three assignments using distinct active drivers and buses.
- Overlap rejection per run, including overnight trips.
- Simultaneous manual claims for the same remaining slot.
- Manual assignment versus Auto Scheduling confirmation.
- Individual dispatch and completion without changing sibling run statuses.
- Exact DDR and Incident linkage to each assignment.
- Replacement bus updates only the affected run.
- Trip Records and CSV export include every run without double-counting the
  service.
- Rollback preserves all existing assignment, DDR, Incident, and replacement
  IDs.
