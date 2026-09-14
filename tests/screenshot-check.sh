#!/bin/bash
# Screenshot check: renders the index, editor, and embed views at
# representative viewport sizes and writes PNGs for manual review or CI
# artifact upload (ROADMAP.md "Validation coverage").
#
# Usage: tests/screenshot-check.sh [URL] [OUTDIR]
#   URL    base of the running LibreNMS plugin (default http://localhost:18080)
#   OUTDIR where PNG files are written (default output/screenshots)
#
# Requires a Chromium-family binary on PATH (chromium, google-chrome,
# chromium-browser, or headless_shell). Run in the plugin host so cookies/auth
# resolve; unauthenticated renders will show the login page, which is itself
# a useful regression signal for chrome/regression checks on the embed view.

set -euo pipefail

URL="${1:-http://localhost:18080}"
OUTDIR="${2:-output/screenshots}"
BASE="${URL%/}/plugin/WeathermapNG"

# Undo delegating to docker when local chromium unavailable; keep a list of
# possible binaries and pick the first that runs.
BROWSER=""
for candidate in chromium chromium-browser google-chrome google-chrome-stable headless_shell; do
    if command -v "$candidate" >/dev/null 2>&1; then
        BROWSER="$(command -v "$candidate")"
        break
    fi
done

if [[ -z "$BROWSER" ]]; then
    # Fall back to docker if no local chromium.
    if command -v docker >/dev/null 2>&1; then
        echo "No local chromium; using docker php:8.3-cli + chrome unavailable — refusing." >&2
        exit 2
    fi
    echo "No chromium-family browser found on PATH." >&2
    exit 2
fi

VIEWPORTS=(480x800 768x1024 1920x1080)
PAGES=(index editor embed)

mkdir -p "$OUTDIR"
FAIL=0

for page in "${PAGES[@]}"; do
    for vp in "${VIEWPORTS[@]}"; do
        w="${vp%x*}"
        h="${vp#*x}"
        outfile="$OUTDIR/${page}-${vp}.png"
        target="$BASE/$page/1"
        # 'index' maps to the plugin root page.
        [[ "$page" == "index" ]] && target="$BASE"
        # 'editor' is /editor/{id}; without a map id fall back to /editor.
        [[ "$page" == "editor" && ! "$URL" =~ "librenms" ]] && target="$BASE/editor/1"
        echo "→ $page @ ${w}x${h} → $outfile"
        if ! "$BROWSER" --headless --disable-gpu --no-sandbox \
             --hide-scrollbars --window-size="$w,$h" \
             --screenshot="$outfile" \
             --virtual-time-budget=4000 \
             "$target" >/dev/null 2>&1; then
            echo "  ✗ failed to render $page @ $vp" >&2
            FAIL=1
            continue
        fi
        if [[ ! -s "$outfile" ]]; then
            echo "  ✗ empty screenshot for $page @ $vp" >&2
            FAIL=1
        fi
    done
done

if [[ "$FAIL" -ne 0 ]]; then
    echo "Some screenshots failed; see messages above." >&2
    exit 1
fi

# Every file rendered and is non-empty; sizes are reported to eyeball for
# blank/white captures.
echo "Summary:"
for f in "$OUTDIR"/*.png; do
    size=$(wc -c < "$f")
    printf "  %-40s %8d bytes\n" "$(basename "$f")" "$size"
done

exit 0
