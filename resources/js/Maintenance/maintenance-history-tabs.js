/*
 * Maintenance Active / History tabs are server-rendered.
 *
 * Purchase Requests and Job Orders now use normal anchor links with
 * data-allow-partial-navigation so the shared navigation runtime owns the
 * lifecycle. Keeping this module intentionally side-effect free prevents
 * stale click handlers and DOM-injected tabs after <main> replacement.
 */
