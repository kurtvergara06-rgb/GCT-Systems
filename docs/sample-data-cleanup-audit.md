# Sample data cleanup audit

This audit is based on the migrations, Eloquent relationships, controllers,
services, and both database seeders. Dates are deliberately not cleanup
criteria. Rows without an explicit sample identifier remain protected.

## Classification markers

The deterministic realistic seeder owns these identifiers:

- `TRIP-GCT-*`, `DDR-GCT-*`, `INC-GCT-*`, `JO-GCT-*`, `PR-GCT-*`,
  `PO-GCT-*`, `GCT-PART-*`, `GCT-DRV-*`, and `GCT-RT-*`.
- The exact sample buses `GCT-201` through `GCT-208`.
- `stock_movements.source` equal to `demo` or `simulated`.
- `inventory_items.source` equal to `demo` or `simulated` (including the
  catalog that the source-tagging migration identified as demo-owned).

Legacy equivalents are `TRIP-DEMO-*`, `DEMO-DDR-*`, `DEMO-INC-*`,
`DEMO-JO-*`, `DEMO-PR-*`, `DEMO-PO-*`, `DEMO-PART-*`, `DEMO-BUS-*`,
`DEMO-DRV-*`, and `DEMO-RT-*`.

`DemoDataSeeder` also creates plausible-looking development records (for
example buses `GCT-101` through `GCT-114`) without a durable row-level demo
marker. Those ambiguous records are not safe to identify retroactively and
are intentionally not auto-deleted. Automatic demo seeding is now disabled;
`SEED_DEMO_DATA=true` is required explicitly. `RealisticSampleDataSeeder`
remains manual-only.

## Relationship map and deletion implications

- `purchase_orders.purchase_request_id` references `purchase_requests.id`
  with null-on-delete; `scheduled_purchases.last_po_id` references purchase
  orders with null-on-delete. A scheduled purchase protects its sample PO.
- `purchase_requests.job_order_no` is a soft string link to
  `job_orders.job_order_no`; `bus_no` is also a soft link. A genuine PO
  protects its sample PR, and a genuine PR protects its sample JO.
- `job_orders.pms_schedule_id`, `maintenance_referral_id`, and `incident_id`
  are nullable foreign keys. A genuine JO protects a sample incident.
- `maintenance_referrals.incident_id` cascades on incident deletion;
  incident responses and replacements also belong to incidents. Replacement
  bus links restrict bus deletion.
- `trip_assignments.trip_schedule_id` cascades; attendance and bus links
  restrict deletion. DDR and incident schedule/assignment links null on
  deletion. A sample schedule referenced by a genuine DDR or incident is
  protected.
- `trip_schedules.shuttle_route_id` is an indexed soft relationship.
  `route_stops.shuttle_route_id` cascades. A route remains if any schedule
  remains.
- Buses are additionally referenced by trip assignments, DDRs, incidents,
  replacements, JO/PR soft bus numbers, PMS schedules, fuel reports, driver
  attendance assignments, batch uploads, and GPS trip records.
- Drivers use a soft `driver_id` relationship from attendance, assignments,
  DDRs, and incidents. Driver masters remain while any such record remains.
- `stock_movements.inventory_item_id` and issuance item inventory/movement
  links null on delete. Movements linked to an issuance are protected as
  audit history. Sample items remain if referenced by any movement, issuance,
  PR source item, scheduled purchase item name, or remaining PO JSON payload.
- Batch uploads own GPS trip records and processed records by cascade.
  Data activities, users, role permissions, activity logs, notifications,
  analytics exports/models, fuel reports, mechanic attendance, and scheduled
  purchases have no reliable cleanup marker and are report/protect-only.

The transactional execution order is: unissued sample stock movements,
unreferenced sample POs, PRs, JOs, DDRs, incidents (whose dependent response,
replacement, and referral rows follow their constraints), assignments,
schedules, sample-only attendance, then unreferenced inventory/route/driver/
bus masters.

## Orphan report

`data:cleanup-samples --orphans` reports but never deletes:

- PR numbers whose `job_order_no` has no JO.
- PO numbers whose foreign-key or JSON PR reference has no PR.
- JO numbers whose `bus_no` has no bus.
- stock movements whose ID/code has no inventory item.
- assignments missing their schedule, attendance, or bus.
- DDRs and incidents with a non-null schedule/assignment reference whose
  parent is missing.

Some database constraints make several conditions impossible in a fully
migrated database, but the checks remain useful for imported legacy data.
