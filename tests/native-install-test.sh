#!/usr/bin/env bash
# Disposable GitHub Ubuntu VM qualification of native plugin upgrades.
set -Eeuo pipefail
umask 077

root=/opt/librenms
plugin=$root/html/plugins/WeathermapNG
core_sha=17387db6dcb86a5482c4a309b5378ded1c6df3b7
baseline_sha=38830b0db55bc0f40f3c8fa981ccc24d6723bb8f
candidate_sha=${1:?candidate SHA required}
bundle=${2:?readable Git bundle required}
fixture=${3:?fixture path required}
evidence=${4:?evidence directory required}
private=$(mktemp -d)
cleanup() {
    local result=$?
    umask 077
    printf 'exit=%s stage=%s\n' "$result" "${stage:-preflight}" > "$evidence/exit-status.txt"
    echo "native upgrade test exit=$result stage=${stage:-preflight}"
    rm -rf "$private"
    exit "$result"
}
trap cleanup EXIT
mkdir -p "$evidence"
chown root:root "$private"
stage=preflight
test "$(id -u)" -eq 0
test "$(ps -p 1 -o comm=)" = systemd
test ! -e /.dockerenv
test ! -e "$root"
test -r "$fixture"
# Runtime commands must not inherit the runner's potentially private workspace.
cd /

as_librenms() { sudo -u librenms -H env -u XDG_CONFIG_HOME -u XDG_CACHE_HOME -u COMPOSER_HOME "$@"; }
logged() {
    local label=$1 result=0
    shift
    (umask 077; : > "$evidence/$label.log")
    "$@" > "$evidence/$label.log" 2>&1 || result=$?
    printf '%s exit=%s\n' "$label" "$result" >> "$evidence/commands-status.txt"
    return "$result"
}
fixture_run() {
    as_librenms env WMNG_TEST_TOKEN="$(cat "$private/token")" php "$private/fixture.php" "$1" "${@:2}"
}
report() {
    as_librenms php "$plugin/verify-deployment.php" --json > "$evidence/$stage-report.json"
    # PHP variables are intentionally literal.
    # shellcheck disable=SC2016
    php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); if (!$r["ok"] || $r["deployment"]!=="native" || !$r["checks"]["persistent_registry"]["ok"] || !$r["checks"]["composer_declaration"]["ok"]) exit(1);' "$evidence/$stage-report.json"
    as_librenms composer config --global repositories --json > "$private/repository.json"
    # shellcheck disable=SC2016
    php -r '$repos=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $matches=array_values(array_filter($repos,fn($r)=>($r["name"]??null)==="weathermapng")); $r=$matches[0]??[]; exit(count($matches)===1 && ($r["type"]??null)==="path" && ($r["url"]??null)===$argv[2] && ($r["options"]["symlink"]??null)===true ? 0 : 1);' "$private/repository.json" "$plugin"
    # shellcheck disable=SC2016
    as_librenms php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); exit(isset($r["require"]["librenms/weathermapng"]) ? 0 : 1);' "$root/composer.plugins.json"
}
check_stage() {
    umask 077
    test "$(as_librenms git -C "$root" rev-parse HEAD)" = "$core_sha"
    test "$(as_librenms git -C "$plugin" rev-parse HEAD)" = "$1"
    test "$(as_librenms id -un)" = librenms
    test "$(as_librenms printenv HOME)" = "$root"
    systemctl is-active --quiet "php$phpver-fpm" nginx
    ps -eo user=,args= | awk '$1=="librenms" && $0 ~ /php-fpm: pool librenms/ {found=1} END {exit !found}'
    sudo -u www-data test -r "$plugin/resources/js/editor-state.js"
    sudo -u www-data test -r "$plugin/resources/css/weathermapng.css"
    fixture_run snapshot > "$evidence/$stage-snapshot.json"
    cmp "$evidence/baseline-snapshot.json" "$evidence/$stage-snapshot.json"
    fixture_run check < "$private/state.json" > "$evidence/$stage-fixture.log"
    fixture_run recovery-check < "$private/state.json" >> "$evidence/$stage-fixture.log"
    report
    STAGE="$stage" EVIDENCE="$evidence" PRIVATE="$private" python3 - <<'PY'
import json, os, urllib.request, urllib.error
from pathlib import Path
state=json.loads((Path(os.environ['PRIVATE'])/'state.json').read_text())
token=(Path(os.environ['PRIVATE'])/'token').read_text().strip()
base='http://127.0.0.1'
map_id=state['map']
checks=[]
for path,headers,marker,expected in [
    (f'/plugin/WeathermapNG/editor/{map_id}',{'Cookie':state['cookie']},f'__WMNGState.mapId = {map_id};',200),
    (f'/plugin/WeathermapNG/api/maps/{map_id}/json',{'Authorization':'Bearer '+token},None,200),
    (f'/plugin/WeathermapNG/embed/{map_id}',{'Authorization':'Bearer '+token},f"mapId: '{map_id}'",200),
    (f'/plugin/WeathermapNG/api/maps/{map_id}/json',{'Authorization':'Bearer invalid'},None,401),
    ('/plugins/WeathermapNG/resources/js/editor-state.js',{},'WeathermapNG editor',200),
    ('/plugins/WeathermapNG/resources/css/weathermapng.css',{},'WeathermapNG Styles',200),
]:
    request=urllib.request.Request(base+path,headers=headers)
    try:
        with urllib.request.urlopen(request,timeout=20) as response:
            status,body,mime=response.status,response.read(),response.headers.get('Content-Type','')
    except urllib.error.HTTPError as error:
        status,body,mime=error.code,error.read(),error.headers.get('Content-Type','')
    assert status==expected,(path,status,expected)
    if marker: assert marker.encode() in body,path
    if path.endswith('.js'): assert 'javascript' in mime,path
    if path.endswith('.css'): assert 'text/css' in mime,path
    if path.endswith('/json') and status==200:
        data=json.loads(body)
        assert data['id']==map_id and len(data['nodes'])==2 and len(data['links'])==1,path
    checks.append({'path':path,'status':status})
out=Path(os.environ['EVIDENCE'])/(os.environ['STAGE']+'-http.json')
out.write_text(json.dumps(checks,indent=2)+'\n')
PY
    echo "PASS $stage core=$core_sha plugin=$1" | tee "$evidence/$stage-result.txt"
    umask 022
}

stage=provision
for file in apt.log apt-install.log core-clone.log nginx-test.log services.log bundle-verify.log baseline-snapshot.json runtime-versions.txt revisions.txt commands-status.txt; do
    : > "$evidence/$file"
done
umask 022
export DEBIAN_FRONTEND=noninteractive
sudo systemctl start mysql
apt-get update > "$evidence/apt.log" 2>&1
apt-get install -y --no-install-recommends acl ca-certificates composer curl fping git graphviz imagemagick mtr-tiny nginx nmap php-cli php-curl php-fpm php-gd php-gmp php-intl php-mbstring php-mysql php-snmp php-xml php-zip python3-command-runner python3-dotenv python3-pip python3-psutil python3-pymysql python3-redis python3-setuptools python3-systemd rrdtool snmp snmpd traceroute unzip whois > "$evidence/apt-install.log" 2>&1
test "$(MYSQL_PWD=root mysql -uroot -Nse 'SELECT @@lower_case_table_names')" = 0
useradd -U librenms -d "$root" -M -r -s /bin/bash
git clone --filter=blob:none --branch 26.10.2 https://github.com/librenms/librenms.git "$root" > "$evidence/core-clone.log" 2>&1
test "$(git -C "$root" rev-parse HEAD)" = "$core_sha"
db_password=$(openssl rand -hex 24)
token=$(openssl rand -hex 24)
umask 077
printf '%s' "$token" > "$private/token"
chown root:librenms "$private"
chmod 710 "$private"
chown root:librenms "$private/token"
chmod 640 "$private/token"
MYSQL_PWD=root mysql -uroot <<SQL
CREATE DATABASE librenms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'librenms'@'localhost' IDENTIFIED BY '$db_password';
GRANT ALL PRIVILEGES ON librenms.* TO 'librenms'@'localhost';
FLUSH PRIVILEGES;
SQL
DB_PASSWORD="$db_password" WMNG_TOKEN="$token" python3 - <<'PY'
from pathlib import Path
import os
root=Path('/opt/librenms')
values={'APP_ENV':'production','APP_DEBUG':'false','APP_URL':'http://localhost','DB_HOST':'localhost','DB_DATABASE':'librenms','DB_USERNAME':'librenms','DB_PASSWORD':os.environ['DB_PASSWORD'],'SESSION_DRIVER':'file','CACHE_STORE':'file','CACHE_DRIVER':'file','QUEUE_CONNECTION':'sync','INSTALL':'false','WEATHERMAPNG_EMBED_TOKEN':os.environ['WMNG_TOKEN'],'WEATHERMAPNG_DEMO_MODE':'true'}
source=(root/'.env.example').read_text()
lines=[line for line in source.splitlines() if line.split('=',1)[0] not in values]
(root/'.env').write_text('\n'.join(lines)+'\n'+'\n'.join(f'{k}={v}' for k,v in values.items())+'\n')
(root/'config.php').write_text("<?php\n$config['user'] = 'librenms';\n$config['auth_mechanism'] = 'mysql';\n$config['update'] = true;\n$config['update_channel'] = 'release';\n$config['update_on_days'] = [];\n")
(root/'.env').chmod(0o600)
PY
unset db_password token
umask 022
chown -R librenms:librenms "$root"
chmod 771 "$root"
mkdir -p "$root"/{rrd,logs,bootstrap/cache,storage}
chown -R librenms:librenms "$root"/{rrd,logs,bootstrap/cache,storage}
setfacl -d -m g::rwx "$root"/{rrd,logs,bootstrap/cache,storage}
setfacl -R -m g::rwx "$root"/{rrd,logs,bootstrap/cache,storage}
logged core-composer as_librenms bash -euc 'cd /opt/librenms; composer install --no-dev --no-interaction --no-progress'
logged core-key as_librenms bash -euc 'cd /opt/librenms; php artisan key:generate --force'
logged core-migrate as_librenms bash -euc 'cd /opt/librenms; ./lnms migrate --force --no-interaction --seed'
phpver=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
for mode in cli fpm; do printf 'date.timezone=UTC\n' > "/etc/php/$phpver/$mode/conf.d/99-native-timezone.ini"; done
sed -e 's/^\[www\]/[librenms]/' -e 's/^user = .*/user = librenms/' -e 's/^group = .*/group = librenms/' -e 's|^listen = .*|listen = /run/php-fpm-librenms.sock|' "/etc/php/$phpver/fpm/pool.d/www.conf" > "/etc/php/$phpver/fpm/pool.d/librenms.conf"
rm "/etc/php/$phpver/fpm/pool.d/www.conf"
cat > /etc/nginx/conf.d/librenms.conf <<'NGINX'
server {
    listen 80;
    server_name _;
    root /opt/librenms/html;
    index index.php;
    charset utf-8;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ [^/]\.php(/|$) {
        fastcgi_pass unix:/run/php-fpm-librenms.sock;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include fastcgi.conf;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
rm -f /etc/nginx/sites-enabled/default
nginx -t > "$evidence/nginx-test.log" 2>&1
systemctl enable --now "php$phpver-fpm" nginx > "$evidence/services.log" 2>&1
systemctl restart "php$phpver-fpm" nginx
mkdir -p "$root/html/plugins/WeathermapNG"
chown -R librenms:librenms "$root/html/plugins"
as_librenms git -C "$plugin" init -q
as_librenms git -C "$plugin" bundle verify "$bundle" > "$evidence/bundle-verify.log" 2>&1
as_librenms git -C "$plugin" fetch -q "$bundle" refs/tags/v1.15.1 refs/heads/candidate
as_librenms git -C "$plugin" checkout -q --detach "$baseline_sha"
test "$(as_librenms git -C "$plugin" rev-parse HEAD)" = "$baseline_sha"
cp "$fixture" "$private/fixture.php"
chown root:librenms "$private/fixture.php"
chmod 640 "$private/fixture.php"
logged baseline-install as_librenms bash -euc 'cd /opt/librenms/html/plugins/WeathermapNG; ./quick-install.sh'
systemctl restart "php$phpver-fpm"
stage=baseline
umask 077
fixture_run seed > "$private/state.json"
chown root:librenms "$private/state.json"
chmod 640 "$private/state.json"
fixture_run snapshot > "$evidence/baseline-snapshot.json"
umask 022
check_stage "$baseline_sha"

stage=deploy
as_librenms git -C "$plugin" checkout -q --detach "$candidate_sha"
logged deploy as_librenms bash -euc 'cd /opt/librenms/html/plugins/WeathermapNG; ./deploy.sh'
systemctl restart "php$phpver-fpm"
check_stage "$candidate_sha"

stage=deploy-repeat
logged deploy-repeat as_librenms bash -euc 'cd /opt/librenms/html/plugins/WeathermapNG; ./deploy.sh'
systemctl restart "php$phpver-fpm"
check_stage "$candidate_sha"

for stage in post-pull post-pull-repeat; do
    # Restore the actual upstream tracked manifest and lock before each updater run.
    as_librenms git -C "$root" checkout -- composer.json composer.lock
    logged "$stage" as_librenms bash -euc 'cd /opt/librenms; ./daily.sh post-pull same-version same-version'
    if grep -Eq '(^|[^[:alpha:]])FAIL([^[:alpha:]]|$)' "$evidence/$stage.log"; then
        echo "Inner updater reported FAIL at $stage" >&2
        exit 1
    fi
    systemctl restart "php$phpver-fpm"
    check_stage "$candidate_sha"
done
umask 077
printf 'PHP=%s Composer=%s MySQL=%s\n' "$(php -r 'echo PHP_VERSION;')" "$(composer --version --no-ansi)" "$(mysql --version)" > "$evidence/runtime-versions.txt"
printf 'core=%s\nbaseline=%s\ncandidate=%s\n' "$core_sha" "$baseline_sha" "$candidate_sha" > "$evidence/revisions.txt"
