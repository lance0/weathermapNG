# WeathermapNG embed viewer

The embed view is the read-only map viewer for dashboards, iframes, and NOC
walls: one map with live traffic, drill-down, and PNG/SVG export.

## Embed URL

```text
https://librenms.example.com/plugin/WeathermapNG/embed/1
```

The viewer loads its initial data server-side, then pulls updates from
`api/maps/{map}/live` or `api/maps/{map}/sse`. The four token-capable routes
(`embed`, `json`, `live`, `sse`) accept either a logged-in LibreNMS session or
an embed token; see [Embed API token auth](#embed-api-token-auth).

A basic iframe:

```html
<iframe src="https://librenms.example.com/plugin/WeathermapNG/embed/1?kiosk=1"
        width="100%" height="600" frameborder="0"></iframe>
```

## Query parameters

| Parameter | Default | Meaning |
|-----------|---------|---------|
| `metric` | `percent` | Link label: `percent`, `in`, `out`, or `sum` (in + out). |
| `scale` | `weathermapng.scale` (`bits`) | Units for traffic labels: `bits` (Gb/s, Mb/s) or `bytes` (GB/s, MB/s). |
| `sse` | `weathermapng.enable_sse` (`1`) | Use Server-Sent Events. `0` forces polling; browsers without `EventSource` poll regardless. |
| `interval` | `weathermapng.client_refresh` (`60`) | Seconds between polls and between SSE frames. The server clamps SSE to 1–60. |
| `max` | `300` | SSE stream lifetime in seconds before reconnect. The server clamps to 5–600. |
| `graphs` | `1` | RRD graph popup on hover. `0` disables; always off in kiosk mode. |
| `metrics` | `weathermapng.show_node_metrics` (`1`) | CPU/MEM line on nodes. `0` hides it. |
| `nav` | `1` | Zoom buttons, wheel zoom, drag-pan, and double-click zoom. `0` disables all of them. |
| `minz` | `0.5` | Minimum zoom level. |
| `maxz` | `4` | Maximum zoom level. |
| `kiosk` | `0` | `1` strips chrome for wall displays; see [Kiosk mode](#kiosk-mode). |
| `cycle` | none | With `kiosk=1`, rotate to the next map every N seconds (minimum 5). |
| `target` | `_blank` | Where device/link click-through opens: `_blank` or `self`/`_self` for the same tab. |
| `debugDots` | off | `1` draws particle-position crosses with a transform readout; `2` opens the render-pipeline panel. |
| `token` | none | Embed token for viewers without a LibreNMS session. |

`sse`, `graphs`, `metrics`, and `nav` are off only when set to `0`. `kiosk`
uses standard boolean parsing, so `kiosk=0`, `false`, or `off` disables it; use
`kiosk=1` to enable. `minz` and `maxz` clamp wheel, button, and double-click
zoom; panning is unbounded.

Colors and legend bands come from `weathermapng.colors` and
`weathermapng.thresholds` (default `[50, 80, 95]`). Links without an explicit
`style.via_style` use the map's `options.default_link_style.via_style`, then
`weathermapng.link_style` (default `straight`).

## Live and polling behavior

- The first paint uses server-rendered traffic, status, metrics, and alerts, so
  the map is not blank while the first request completes.
- With `sse=1`, the viewer subscribes to `api/maps/{map}/sse`. Each frame is the
  same payload as the live endpoint. After consecutive errors it retries up to
  five times, two seconds apart, then falls back to polling.
- A stream closes after `max` seconds and the viewer reconnects.
- With polling, the viewer fetches `api/maps/{map}/live` every `interval`
  seconds.
- The Live button in the toolbar switches between SSE and polling at runtime.
  The label shows the active transport and interval.
- A hidden tab pauses rendering, SSE, and polling; all resume when the tab is
  visible again.
- The payload shape is documented in
  [API: live payload](API.md#live-payload).

## Exports

Both buttons download from the browser; nothing is rendered server-side.

| Export | Content | Filename |
|--------|---------|----------|
| PNG | The visible viewport: background, map, labels, and flow particles at the current zoom/pan. | `weathermap-<mapId>.png` |
| SVG | A static full-map snapshot with the same colors, shapes, thresholds, node labels, Σ traffic when live values exist, and link labels. No animation, CPU/MEM line, minimap, or current zoom. | `weathermap-<mapId>.svg` |

The SVG is a self-contained document: `viewBox` matches the map dimensions, the
background rect uses `map.background`, and there are no external assets,
scripts, or `foreignObject` content. Link `style.via_points`/`via_style` and
`style.color`/`style.width` are honored; without an explicit color the stroke
follows utilization thresholds. Link labels follow the selected `metric` and
the `show_percentages`/`show_bandwidth` config switches, and are omitted for
links without live values. Output is deterministic for the same map and live
data.

<a id="degradation--performance"></a>

## Degradation rules

The viewer reduces work instead of dropping frames on large or busy maps.

- **Particle budget.** Total flow particles across the map stay at or below
  **1500**. Density is full up to 150 links, half up to 300, and a quarter
  above 300; each link's allowance is `floor(1500 / linkCount)`. Above 1500
  links the allowance is zero and flow animation is skipped rather than
  exceeding the cap. The Flow, Particle Density, and Particle Speed controls
  apply on top of this budget.
- **Zoomed-out labels.** Below zoom `0.6`, nodes keep their name but drop the
  Σ traffic and CPU/MEM lines. Link labels are unaffected; zoom in to restore
  them.
- **`?metrics=0`.** Hides only the CPU/MEM line; node names and traffic totals
  stay visible.
- **Reduced motion.** With the OS reduced-motion preference, flow particles
  start disabled and dash-animation offsets stop advancing. Use the Flow button
  to enable them. The map stays fully readable.

## Kiosk mode

`?kiosk=1` hides navigation, controls, legend, minimap, and status chrome.
Mouse movement or any key reveals the chrome for 3.5 seconds; `Esc` toggles it.
An **Exit Kiosk** button in the bottom-right clears `kiosk` and `cycle` from the
URL.

`cycle=N` (minimum 5 seconds) rotates to the next map in alphabetical order by
name, preserving the current query parameters and the `target` setting. RRD
graph hover is disabled in kiosk mode.

```text
/plugin/WeathermapNG/embed/1?kiosk=1
/plugin/WeathermapNG/embed/1?kiosk=1&target=self
/plugin/WeathermapNG/embed/1?kiosk=1&cycle=30&target=self
```

## Nested maps

Maps form a hierarchy through `parent_map_id`. A node whose `meta.sub_map_id`
points at another map becomes a drill-down target: clicking it opens that map
and keeps the current query parameters. A node with both a sub-map and a
device opens the sub-map.

A breadcrumb appears above the canvas when the current map has a parent. Each
crumb in the root-to-current chain is clickable except the last.

## Embed API token auth

External systems that cannot hold a browser session can use a static token.
Set it in the LibreNMS `.env`:

```bash
WEATHERMAPNG_EMBED_TOKEN=<random-secret>
```

Then pass it in the URL:

```html
<iframe src="https://librenms.example.com/plugin/WeathermapNG/embed/1?token=<random-secret>"></iframe>
```

Or, preferably, in a header for scripted access:

```bash
curl -H "Authorization: Bearer <random-secret>" \
  https://librenms.example.com/plugin/WeathermapNG/api/maps/1/json
```

Token auth applies to `embed/{map}`, `api/maps/{map}/json`,
`api/maps/{map}/live`, and `api/maps/{map}/sse` only. Logged-in users never
need a token. Behaviour:

| Request | Result |
|---------|--------|
| No token | Full LibreNMS `web` + `auth` stack: login, account-enabled, and two-factor checks. |
| Token, but `WEATHERMAPNG_EMBED_TOKEN` unset | `403`. |
| Wrong token | `401` with `{"success": false, "message": "Invalid or missing embed token."}`. |
| Correct token | Request succeeds; `Cache-Control: private, no-store, no-transform` and `Referrer-Policy: no-referrer` are set. |

`Authorization: Bearer` wins over `?token=`. The viewer forwards its token to
the JSON, live, SSE, drill-down, cycle, and exit requests.

Caveat: a `?token=` URL can leak through proxy logs, browser history, and
referrers. Use the header where the consumer supports it, and rotate the token
by changing `.env`. The token grants read access to these map endpoints only;
LibreNMS device pages and RRD graphs still require a LibreNMS session.

## Grafana iframe panel

Use a **Text** panel with raw HTML containing the iframe:

```html
<iframe src="https://librenms.example.com/plugin/WeathermapNG/embed/1?kiosk=1&token=<random-secret>"
        width="100%" height="500" frameborder="0"></iframe>
```

`kiosk=1` removes the viewer chrome; `target=self` keeps click-through inside
the panel. Grafana Text panels render raw HTML, so no plugin is required. If
Grafana is served from a different origin, the LibreNMS host must allow frame
embedding (check its `X-Frame-Options` and CSP `frame-ancestors`).

## Troubleshooting

| Symptom | Check |
|---------|-------|
| Login page inside the iframe | The browser session is absent; add a token. |
| `403` on the embed URL | `WEATHERMAPNG_EMBED_TOKEN` is not set on the LibreNMS host. |
| `401` on the embed URL | Token mismatch; the environment may still hold an old value. |
| Traffic never updates | Reverse proxy buffering blocks SSE; try `?sse=0&interval=30`. |
| Graph popups never appear | `graphs=0`, kiosk mode, or an anonymous token request (RRD needs a session). |
| Secondary labels disappear | Zoom is below `0.6`; zoom in to restore the Σ traffic and CPU/MEM lines. |
| Flow animation missing | The map has over 1500 links (flow is skipped), Flow is off, or reduced motion is set. |
| Map appears small with margins | The viewer fits the map at scale ≤1 and centers it; match the iframe aspect ratio to the map. |

## Tips

- Use `metric=sum` for total link load and `scale=bytes` to match interface
  counters.
- Use `nav=0` for fixed dashboard widgets; use `minz`/`maxz` to bound zoom when
  navigation stays enabled.
- Use `kiosk=1&target=self` in an iframe so drill-down and device links stay in
  the panel.
- Keep embed tokens in headers for scripts and rotate them with `.env`.
- See [Performance](PERFORMANCE.md) for profiling a large map and
  [API](API.md#map-data) for the raw payloads.
