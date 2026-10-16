# Operations Module Sample Data Catalog (V2 Audit)

All test records created during this browser audit run have been strictly tagged with prefix `QA-OPS-V2-20261015` to ensure audit traceability and non-destructive isolation.

| Entity Type | System ID | Code / Number / Plate | Name / Description | Status / Attributes |
| :--- | :---: | :--- | :--- | :--- |
| **Bus** | `54` | Body: `QA-OPS-V2-20261015-BUS01`<br>Plate: `QA-2026-V2` | Isuzu QA Heavy (50-seater) | Active • Fleet Purpose: Regular |
| **Driver** | `21` | ID: `QA-OPS-V2-20261015-DRV01`<br>License: `N01-26-999999` | QA Test Driver Oct15 | Active • Morning Shift • Contact: 0917-999-0001 |
| **Mechanic** | `11` | ID: `QA-OPS-V2-20261015-MEC01`<br>Code: `MEC-TEST-011` | QA Test Mechanic Oct15 | Active • Morning Shift • Specs: Engine, Brakes |
| **Route** | `33` | Code: `R-04` | QA-OPS-V2-20261015-RT01 Batangas Express | Distance: 30.9 km • 1 Stop: San Jose Public Market |
| **Attendance (Driver)** | `3419` | DRV ID: `21` | Date: `2026-10-15` | Status: Present • Time In: 05:45 AM |
| **Attendance (Driver)** | `3420` | DRV ID: `21` | Date: `2026-10-16` | Status: Present • Time In: 05:50 AM |
| **Attendance (Mechanic)**| `624` | MEC ID: `11` | Date: `2026-10-15` | Status: Present • Time In: 06:00 AM |
| **Trip Schedule** | `3085` | `T-3085` | Batangas Express (Departure: 22:00 PST) | Route R-04 • 2 Buses Required • Scheduled |
| **Trip Schedule** | `3086` | `T-3086` | Batangas Express (Departure: 22:15 PST) | Route R-04 • 15-min spacing validated • Scheduled |
| **Trip Schedule** | `3087` | `T-3087` | Batangas Express (2026-10-16 09:00 PST) | Route R-04 • Assigned DRV01 + BUS01 • Ready |
| **Trip Schedule** | `3088` | `T-3088` | Batangas Express (2026-10-16 09:30 PST) | Route R-04 • Overlap blocked for DRV01/BUS01 |
| **DDR** | `2` | `DDR-2026-0002` | Linked to `DEMO-IN-01` | Passengers: 25 • Km: 20.5 • Cash: ₱0.00 • Submitted |
| **Incident** | `168` | `INC-00168` | Bus Breakdown on `DEMO-OUT-04` (`GCT-209`) | Location: Km 42 SLEX • Status: **Resolved** |
| **Maintenance Referral**| — | Linked to `INC-00168` | Referral for `GCT-209` engine/radiator repair | Status: Pending Review by Maintenance |
| **Incident Replacement**| — | Dispatched for `INC-00168` | `GCT-209` replaced by `GCT-210` (Reserve bus) | Dispatched by Operations Head • Driver Retained |

