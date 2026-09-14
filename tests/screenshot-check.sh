#!/bin/bash
# Screenshot check: renders the index, editor, and embed views at
# representative viewport sizes and writes PNGs for manual review or CI
# artifact upload (ROADMAP.md "Validation coverage").
#
# Usage: tests/screenshot-check.sh [URL] [OUTDIR]
#   URL    base of the running LibreNMS plugin (default http://localhost:18080)
#   OUTDIR where PNG files are written (default output/screenshots)
#
# Requires a Chromium-family binary (chromium, google-chrome, headless_shell,
# or the macOS Chrome app). Unauthenticated renders show the LibreNMS login
# page, which is itself a useful regression signal for chrome/regression
# checks on the embed view.

set -euo pipefail

URL="${1:-http://localhost:18080}"
OUTDIR="${2:-output/screenshots}"
BASE="${URL%/}/plugin/WeathermapNG"

BROWSER=""
for candidate in chromium chromium-browser google-chrome google-chrome-stable headless_shell \
                 "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"; do
    if [[ -x "$candidate" ]]; then
        BROWSER="$candidate"
        break
    fi
    if command -v "$candidate" >/dev/null 2>&1; then
        BROWSER="$(command -v "$candidate")"
        break
    fi
done

if [[ -z "$BROWSER" ]]; then
    echo "No chromium-family browser found on PATH or /Applications." >&2
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
        [[ "$page" == "index" ]] && target="$BASE"
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

# Every file rendered and is non-empty; sizes reported to catch blank/white
# captures at a glance.
echo "Summary:"
for f in "$OUTDIR"/*.png; do
    size=$(wc -c < "$f")
    printf "  %-40s %8d bytes\n" "$(basename "$f")" "$size"
done
