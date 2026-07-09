Fixes correctness and security regressions found in a follow-up audit after #22.

## What #22 fixed
- Node label live-preview, link bandwidth units, and map dimension inputs now wire `markUnsaved()`.
- Existing-map saves include and persist `name`.
- SaveMapRequest sanitizes `name`/`title` and trims node labels; MapService persists `name` when present.
- Node/link numeric casts updated upstream.

## Findings from this audit + fixes in this PR

| Severity | Issue | Fix |
|---|---|---|
| High | `embed.blade.php` injected `json_encode()` raw into a `<script>` block; user-controlled map/node/link strings (e.g. a node label containing `<!--`) could break JS parsing or open XSS. | Replaced all `{!! json_encode(...) !!}` in the embed script block with `@json(...)`, which uses Laravel's HTML/hex escaping. |
| Medium | `SaveMapRequest` `name` rule required ASCII-only slugs, so existing legacy maps with dots/Unicode names could no longer be saved. | Relaxed to `nullable|string|max:255`; create-time restrictions remain on `CreateMapRequest`. Trailing whitespace is still trimmed in `prepareForValidation()`. |
| Medium | `SaveMapRequest` only allow-listed `via_style`/`via_points` inside `links.*.style`; existing rows that store `color`/`width` failed validation on load-then-save. | Allow-listed and validated `color`/`width`, with matching sanitization. Updated the error message. |
| Medium | `MapService::resolveNodeId()` fell back to the raw numeric client ID when the new node map missed it, allowing cross-map/orphan links. | Removed the numeric fallback; unresolved IDs become `null`. |
| Medium | Embed canvas always fell back to hard-coded `#ffffff`, ignoring configured map background color. | Uses `mapData.background` as fallback fill. |

## Tests
- `MapRequestRulesTest`: updated name-rule assertion; added assertions for link-style `color`/`width` rules and the resolved-node-id removal.
- `SanitizationTest`: updated the link-style sanitizer mirror to strip unknown keys and sanitize `color`/`width`; added coverage for out-of-range width removal and HTML-stripped color.
- `UIEmbedKioskTest`: updated to expect `@json(...)` directives.
- Full PHPUnit suite: **263 tests, 876 assertions, 0 failures** (1 skipped).
- Container smoke: login → editor load → map JSON fetch → embed render → save with legacy Unicode/dot name and `<!--` label node succeeds; dev fixture restored from the latest map version afterwards.

## Not addressed here
- 0-bandwidth semantics remain as-is pending product decision.
- Low-priority embed/kiosk URL regex/routing cleanups and map-tags duplicate listeners were scoped out to keep this PR clean.
