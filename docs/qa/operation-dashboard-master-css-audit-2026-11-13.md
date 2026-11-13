# Operation Dashboard and Master Data CSS Source Audit
Date: 2026-11-13
Branch: feature/global-dark-mode
Scope: Operation Dashboard, Routes, Client Master List, Driver Master List, Bus Master List, and Operation shared dark-mode stylesheet.

## Findings
- Dashboard: approximately 4,150 lines, 627 ordinary rule blocks, with 114 selector names appearing more than once. Several stages of visual refinements intentionally override earlier declarations, especially grid layout, workforce KPI cards, and workspace links.
- Routes: approximately 3,879 lines, 529 rule blocks, with 86 repeated selector names. Dark-mode is centralized in operation-dark-mode.css; map geometry, leaflet UI and responsive breakpoints remain in routes-stops.css.
- Client Master: approximately 502 lines, 149 rule blocks, with 13 repeated selector names, many associated with responsive behavior and modal modes.
- Driver Master / Personnel: approximately 2,787 lines, 587 rule blocks, with 92 repeated selector names. Attendance toolbar, specialty picker, and view/edit modal rules have historical refinements.
- Bus Master: approximately 3,174 lines, 607 rule blocks, with 53 repeated selector names. Filter popover, bus modals and responsive table rules deserve a separate rendered review.
- Operation dark mode: approximately 1,347 lines. It intentionally mirrors component rules with theme-specific colors and should not be counted as unused simply because declarations overlap.

Repeated selectors do NOT automatically indicate redundant CSS. Media queries, specificity, interactive states, and load ordering can make repetitions necessary. No layout is certified unchanged without browser verification.

## Safe cleanup performed
1. Removed an obsolete early unscoped .operation-bottom-grid declaration from dashboard-operation.css. A later complete base definition, followed by dashboard-scoped rules and responsive overrides, remains the effective rule.
2. Reduced the early .client-master-action-row declaration to its unique white-space rule. Its identical display/align-items/justify-content/gap declarations already exist in a later same-specificity base rule.

## Intentionally retained
- Responsive breakpoint overrides and media queries in all scoped files.
- Existing module-specific rules, including different Add, Edit, and View modal variants.
- Operation theme selectors and semantic active/maintenance/error status colors.
- Fleet, route, personnel, and client JS hooks, validation, and layout geometry.

## Follow-up QA
- Compare desktop and mobile layouts in both Light and Dark modes for Dashboard, Routes, Driver Master, Bus Master, and Client Master.
- Check shared styles load order and use browser computed styles before removing additional historical override blocks.
- Do not blindly eliminate all repeated selectors, use global !important replacements, or change functional styling during a source-only audit.

## Verification
Source inspection and exact-match rule cleanup only; no automated tests, Vite build, or live browser verification were run.
