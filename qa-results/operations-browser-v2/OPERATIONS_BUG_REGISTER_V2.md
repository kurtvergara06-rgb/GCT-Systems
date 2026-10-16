# Operations Module Bug Register & Verification Log (V2)

## 1. Verified & Closed Issues

### BUG-OPS-001: Vite Manifest Missing in Production Builds
- **Severity**: High
- **Module**: Assets / Frontend Build Pipeline
- **Root Cause**: Build pipeline lacked persistent manifest generation during container deployment.
- **Verification Method**: Checked `public/build/manifest.json` (1,149 lines). Inspected real-browser network requests and DOM `<script>` tags.
- **Result**: Fully resolved. Vite dev server and production manifest both support smooth rendering without missing bundle errors.
- **Status**: **CLOSED (VERIFIED)**

### BUG-OPS-002: Sidebar Route Names Missing in Route Service Provider
- **Severity**: Medium
- **Module**: Navigation / Master Data
- **Root Cause**: Blade navigation links pointed to unaliased personnel controller actions.
- **Verification Method**: Verified `routes/operation.php` named routes `operation.personnel.drivers` and `operation.personnel.mechanics`. Clicked sidebar links in real browser.
- **Result**: Both routes return HTTP 200 and load complete master lists without 404 or redirect loops.
- **Status**: **CLOSED (VERIFIED)**

### BUG-OPS-003: Missed Trips Not Properly Flagged
- **Severity**: Medium
- **Module**: Trip Operations / History
- **Root Cause**: Ambiguity in cutoff logic between past unassigned schedules and upcoming future trips.
- **Verification Method**: Evaluated scheduled console command `operation:mark-missed-trips` and departure boundary in `TripSchedule::hasDeparted()`. Created future trips at 22:00 and 22:15 on current test date.
- **Result**: Trips prior to current system time without dispatch are flagged Missed; future trips remain Scheduled with active Assign actions.
- **Status**: **CLOSED (VERIFIED)**

---

## 2. Newly Observed Edge Cases & Recommendations (Non-Blocking)

### OBS-OPS-001: Driver Shift vs Night Trip Validation Alignment
- **Severity**: Minor (UX Improvement)
- **Module**: Driver Assignment
- **Description**: In `TripSchedule::shiftForDeparture()`, any trip departing >= 18:00 (e.g., 22:00) is classified as `Night` shift. However, Driver Master List creation UI only exposes `Morning` and `Afternoon` options in its dropdown.
- **Impact**: Operators assigning drivers to evening/night departures must ensure drivers have flexible or night shift attributes in the DB.
- **Recommendation**: Add "Night" shift as a standard selectable option in the Driver Master List creation and edit modal.

### OBS-OPS-002: Form Field Accessibility Label Warnings
- **Severity**: Trivial / Informational
- **Module**: Accessibility (a11y)
- **Description**: Browser DevTools console noted informational issues: *"No label associated with a form field (count: 16)"* on complex table toolbar filters and hidden CSRF/method inputs.
- **Recommendation**: Add explicit `aria-label` or `for` associations to search inputs and filter comboboxes for enhanced WCAG 2.1 AA compliance.

