# Post-Audit Round 2: Security Hardening & Service Correctness

## Summary

Follow-up fixes from the second round of read-only agent reviews. Addresses IDOR, information disclosure, service correctness, and model safety bugs discovered after PR #22/#23.

## Changes

### Auth & IDOR
- **MapVersionController**: Added `requireAdmin()` to `index()` and `show()` — all version endpoints are now admin-gated (restore/destroy/compare/export already had it)
- **MapVersionController::show**: Nullsafe creator access (`$version->creator?->name`) to prevent null errors
- **HealthController::live**: Removed `getmypid()` from response — public endpoint was leaking process ID
- **HealthController::ready**: Genericized error message in JSON response (raw exception still logged server-side)
- **HealthController::checkDatabase**: Genericized error message — public `/health` endpoint no longer leaks DB connection errors
- **HealthController::checkConfiguration**: Genericized message — no longer reveals whether API token is configured
- **RenderController::sse**: Clamped `max` parameter to `[5, 600]` seconds — prevents clients from holding connections open indefinitely

### Service Correctness
- **MapVersionService::getVersion**: Scoped by `map_id` to prevent cross-map version access
- **MapVersionService::restoreVersion**: Whitelisted snapshot fields via `array_intersect_key` for both nodes and links — prevents mass-assignment of unexpected fields through `forceCreate`
- **MapService::createNodes**: Guard `meta` with `is_array()` check — prevents non-array values from breaking JSON cast
- **MapService::updateMapProperties**: Guard `title` and `name` with `is_string()` + `trim()` check — rejects null, non-string, and whitespace-only values; added `name` to early-return condition so name-only updates aren't skipped
- **MapService::mergeMapOptions**: Replaced `array_merge` with recursive merge — nested option arrays (`default_node_style`/`default_link_style`) are now merged instead of replaced wholesale
- **MapService::createLinks**: Log dropped links with `Log::warning` when node references can't be resolved — previously silently discarded

### Model Safety
- **Node::convertStatusToString**: Guard against null `$status` (returns `'unknown'`) and cast to string before `strtolower` — prevents TypeError on null/non-string status
- **Node::fetchDevice**: Restricted Eloquent query to `['device_id', 'hostname', 'status']` columns; use `->toArray()` instead of `(array)` cast (which exposes protected properties, not attributes)
- **Node::preloadDevices**: Use `->toArray()` for Eloquent model conversion

### Tests
- Added `PostAudit2SecurityTest` with 21 regression tests covering all fixes
- Updated `MapRequestRulesTest` to match new trimmed name guard pattern

## Test Results
- 289 tests, 913 assertions, 1 skipped — all passing
- Container smoke: `/live` (no PID), `/ready` (generic error), `/health` (no API token leak), version index (admin-gated)
