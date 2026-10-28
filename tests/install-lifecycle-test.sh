#!/usr/bin/env bash
# Real lifecycle qualification against an already initialized disposable dev stack.
set -Eeuo pipefail
umask 077
project="${WMNG_COMPOSE_PROJECT:-wmng-auth}"
app="${WMNG_APP_CONTAINER:-librenms-dev}"
base="${WMNG_HTTP_BASE:-http://localhost:8000}"
root=/opt/librenms
plugin="$root/html/plugins/WeathermapNG"
work="$(mktemp -d /tmp/wmng-lifecycle.XXXXXXXX)"
logs="${WMNG_EVIDENCE_DIR:-/tmp/wmng-install-evidence/lifecycle-$(date +%s)}"
mkdir -p "$logs"
compose=(docker compose -p "$project" -f docker-compose.dev.yml)
owned_git=false
pristine_container=
fixture=false
env_saved=false
composer_saved=false
recreated=false
failed_startup=false
token="lifecycle-$(od -An -N12 -tx1 /dev/urandom | tr -d ' \n')"
fixture_php() {
    docker exec -i -u librenms -e WMNG_TEST_TOKEN="$token" "$app" env -u WEATHERMAPNG_EMBED_TOKEN php "$plugin/tests/integration_lifecycle_fixture.php" "$1"
}
restore_native() {
    if "$owned_git"; then docker exec "$app" rm -rf "$root/.git"; owned_git=false; fi
    if "$composer_saved"; then
        docker cp "$work/composer.json" "$app:$root/composer.json"
        docker cp "$work/composer.lock" "$app:$root/composer.lock"
        docker exec "$app" chown librenms:librenms "$root/composer.json" "$root/composer.lock"
        composer_saved=false
    fi
}
wait_web() {
    for _ in {1..120}; do
        if [ "$(curl -sS -o "$work/live.json" -w '%{http_code}' "$base/plugin/WeathermapNG/live" 2>/dev/null)" = 200 ] &&
            php -r '$s=json_decode(file_get_contents($argv[1]),true); exit(($s["alive"]??null)===true ? 0 : 1);' "$work/live.json"; then return; fi
        sleep 2
    done
    docker logs "$app" > "$logs/readiness-failure.log" 2>&1
    return 1
}
cleanup() {
    local result=$?
    trap - EXIT
    set +e
    if [ -n "$pristine_container" ]; then docker rm "$pristine_container" >/dev/null; fi
    # A failed startup must be replaced before restoring the fixture/configuration.
    if "$failed_startup"; then "${compose[@]}" up -d --no-deps --force-recreate librenms >/dev/null; wait_web; fi
    restore_native
    if "$fixture"; then fixture_php cleanup < "$work/state.json"; fi
    if "$env_saved"; then
        docker cp "$work/persistent.env" "$app:/data/.env"
        docker exec "$app" chown librenms:librenms /data/.env
        if "$recreated"; then "${compose[@]}" up -d --no-deps --force-recreate librenms >/dev/null; wait_web; fi
    fi
    if [ -f "$work/before.json" ]; then
        fixture_php snapshot > "$work/after-cleanup.json"
        cp "$work/after-cleanup.json" "$logs/cleanup.json"
        cmp "$work/before.json" "$work/after-cleanup.json" || result=1
    fi
    rm -rf "$work"
    exit "$result"
}
trap cleanup EXIT
# This harness requires the official image's initialized persistent config, before fixture writes.
docker exec "$app" test -f /data/.env
docker cp "$app:/data/.env" "$work/persistent.env"
env_saved=true
fixture_php snapshot > "$work/before.json"
cp "$work/before.json" "$logs/before.json"
# Persistent .env is appended by official init after its generated database config.
# Keep all existing operator keys except this test's explicitly replaced settings.
awk '!/^WEATHERMAPNG_EMBED_TOKEN=|^WEATHERMAPNG_DEMO_MODE=|^INSTALL=/' "$work/persistent.env" > "$work/test.env"
printf '\nWEATHERMAPNG_EMBED_TOKEN=%s\nWEATHERMAPNG_DEMO_MODE=true\nINSTALL=false\n' "$token" >> "$work/test.env"
docker cp "$work/test.env" "$app:/data/.env"
docker exec "$app" chown librenms:librenms /data/.env

# Apply persistent settings through official startup before the ordinary-session control.
recreated=true
"${compose[@]}" up -d --no-deps --force-recreate librenms
wait_web
fixture_php snapshot > "$work/warmed.json"
cmp "$work/before.json" "$work/warmed.json"
fixture_php seed > "$work/state.json"
fixture=true
fixture_php snapshot > "$work/populated.json"
cp "$work/populated.json" "$logs/populated.json"
check_session() {
    fixture_php check < "$work/state.json"
    local map cookie code
    map="$(php -r '$s=json_decode(file_get_contents($argv[1]),true); echo $s["map"];' "$work/state.json")"
    cookie="$(php -r '$s=json_decode(file_get_contents($argv[1]),true); echo $s["cookie"];' "$work/state.json")"
    code="$(curl -sS -o "$work/editor.html" -D "$work/editor.headers" -w '%{http_code}' -H "Cookie: $cookie" "$base/plugin/WeathermapNG/editor/$map")"
    printf 'FPM session editor status=%s ' "$code"
    awk 'tolower($0) ~ /^location:/ { gsub("\r", ""); print }' "$work/editor.headers"
    [ "$code" = 200 ]
    grep -q "__WMNGState.mapId = $map;" "$work/editor.html"
    grep -q 'Retained lifecycle map' "$work/editor.html"
}
check_session
echo 'PASS: pre-lifecycle ordinary session resolves populated map in kernel and FPM'

# Qualify the actual post-pull dependency restoration, using this exact image's pristine files.
image="$(docker inspect "$app" --format '{{.Image}}')"
docker image inspect "$image" --format 'ImageID={{.Id}} RepoDigests={{json .RepoDigests}}' | tee "$logs/image.txt"
pristine_container="$(docker create "$image")"
docker cp "$pristine_container:$root/composer.json" "$work/pristine.json"
docker cp "$pristine_container:$root/composer.lock" "$work/pristine.lock"
docker cp "$pristine_container:$root/lnms" "$work/lnms-original"
docker rm "$pristine_container" >/dev/null
pristine_container=
docker cp "$app:$root/composer.json" "$work/composer.json"
docker cp "$app:$root/composer.lock" "$work/composer.lock"
composer_saved=true
if docker exec "$app" test -e "$root/.git"; then
    echo 'Refusing to replace an existing LibreNMS Git checkout' >&2
    exit 1
fi
docker cp "$work/pristine.json" "$app:$root/composer.json"
docker cp "$work/pristine.lock" "$app:$root/composer.lock"
docker exec "$app" chown librenms:librenms "$root/composer.json" "$root/composer.lock"
owned_git=true
docker exec -u librenms -w "$root" "$app" sh -c 'git init -q; git -c user.name=Fixture -c user.email=fixture@localhost add composer.json composer.lock; git -c user.name=Fixture -c user.email=fixture@localhost commit -qm pristine'
docker cp "$work/composer.json" "$app:$root/composer.json"
docker cp "$work/composer.lock" "$app:$root/composer.lock"
docker exec "$app" chown librenms:librenms "$root/composer.json" "$root/composer.lock"
docker exec -u librenms -w "$root" "$app" git checkout -- composer.json composer.lock
docker exec "$app" su -l librenms -s /bin/bash -c 'cd /opt/librenms && ./daily.sh post-pull same-version same-version' > "$logs/native-post-pull.log" 2>&1
# daily.sh can mask individual task failures; require its dependency step plus our shared runtime postcondition.
if grep -q 'FAIL' "$logs/native-post-pull.log"; then cat "$logs/native-post-pull.log"; exit 1; fi
docker exec -u librenms "$app" php "$plugin/verify-deployment.php" --json > "$logs/native-report.json"
fixture_php snapshot > "$work/native.json"
cp "$work/native.json" "$logs/native.json"
cmp "$work/populated.json" "$work/native.json"
restore_native
echo 'PASS: actual daily.sh post-pull restores dependencies after pristine Composer reset (container qualification)'

check_retained() {
    docker exec -u librenms "$app" php "$plugin/verify-deployment.php" --json > "$logs/recreated-report.json"
    fixture_php snapshot > "$work/recreated.json"
    cp "$work/recreated.json" "$logs/recreated.json"
    cmp "$work/populated.json" "$work/recreated.json"
    # File sessions are ephemeral in this image; record the old cookie rather than requiring survival.
    fixture_php observe < "$work/state.json"
    local map cookie code
    map="$(php -r '$s=json_decode(file_get_contents($argv[1]),true); echo $s["map"];' "$work/state.json")"
    cookie="$(php -r '$s=json_decode(file_get_contents($argv[1]),true); echo $s["cookie"];' "$work/state.json")"
    code="$(curl -sS -o /dev/null -D "$work/old.headers" -w '%{http_code}' -H "Cookie: $cookie" "$base/plugin/WeathermapNG/editor/$map")"
    printf 'Old FPM session status=%s ' "$code"
    awk 'tolower($0) ~ /^location:/ { gsub("\r", ""); print }' "$work/old.headers"
    fixture_php session < "$work/state.json" > "$work/fresh-state.json"
    mv "$work/fresh-state.json" "$work/state.json"
    check_session
    code="$(curl -sS -o "$work/token.json" -w '%{http_code}' -H "Authorization: Bearer $token" "$base/plugin/WeathermapNG/api/maps/$map/json")"
    [ "$code" = 200 ]
    php -r '$s=json_decode(file_get_contents($argv[1]),true); if (($s["id"]??0)!=(int)$argv[2] || count($s["nodes"]??[])!==2 || count($s["links"]??[])!==1) exit(1);' "$work/token.json" "$map"
    code="$(curl -sS -o "$work/embed.html" -w '%{http_code}' -H "Authorization: Bearer $token" "$base/plugin/WeathermapNG/embed/$map")"
    [ "$code" = 200 ]
    grep -q "mapId: '$map'" "$work/embed.html"
    echo 'PASS: recreated FPM serves retained map JSON/embed via persistent token and editor via same-user fresh session'
}

recreated=true
"${compose[@]}" up -d --no-deps --force-recreate librenms
wait_web
check_retained

# Replace only plugin:add; upstream migration commands still execute the pristine lnms at the same directory.
cat > "$work/lnms-failure" <<'WRAPPER'
#!/bin/sh
if [ "${1:-}" = plugin:add ]; then echo 'Injected plugin:add failure' >&2; exit 42; fi
exec /opt/librenms/lnms-original "$@"
WRAPPER
chmod 755 "$work/lnms-failure" "$work/lnms-original"
cat > "$work/failure.yml" <<YAML
services:
  librenms:
    restart: "no"
    volumes:
      - $work/lnms-failure:/opt/librenms/lnms:ro
      - $work/lnms-original:/opt/librenms/lnms-original:ro
YAML
failed_startup=true
"${compose[@]}" -f "$work/failure.yml" up -d --no-deps --force-recreate librenms
exited=false
for _ in {1..120}; do
    if [ "$(docker inspect "$app" --format '{{.State.Status}}')" = exited ]; then exited=true; break; fi
    # No web service may start after required registration failure.
    if curl -fsS "$base/plugin/WeathermapNG/live" > /dev/null 2>&1; then echo 'FAIL: web started during failed installation' >&2; exit 1; fi
    sleep 2
done
docker logs "$app" > "$logs/registration-failure.log" 2>&1
"$exited"
[ "$(docker inspect "$app" --format '{{.State.ExitCode}}')" -ne 0 ]
grep -q 'Injected plugin:add failure' "$logs/registration-failure.log"
! grep -Eq 'ready to handle connections|start worker process|nginx entered RUNNING state' "$logs/registration-failure.log"
echo 'PASS: plugin registration failure exits container before web service starts'
"${compose[@]}" up -d --no-deps --force-recreate librenms
wait_web
failed_startup=false
check_retained
echo "PASS: lifecycle qualification complete; raw evidence in $logs"
