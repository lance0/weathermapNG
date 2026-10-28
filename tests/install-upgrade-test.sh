#!/usr/bin/env bash
# Qualify schema upgrades only in a verified scratch clone of the disposable stack.
set -Eeuo pipefail
umask 077
app_container="${WMNG_APP_CONTAINER:-librenms-dev}"
db_container="${WMNG_DB_CONTAINER:-librenms-db}"
root="${LIBRENMS_PATH:-/opt/librenms}"
plugin="$root/html/plugins/WeathermapNG"
scratch="wmng_upgrade_$(date +%s)_${RANDOM}"
work="$(mktemp -d /tmp/wmng-upgrade.XXXXXXXX)"
created=false
cleanup() {
    if "$created"; then
        docker exec "$db_container" mariadb -uroot -e "REVOKE ALL ON \`$scratch\`.* FROM 'librenms'@'%'; DROP DATABASE \`$scratch\`;" || true
    fi
    rm -rf "$work"
}
trap cleanup EXIT
# Environment overrides must reach the real child kernel, never a cached config.
docker exec -u librenms -w "$root" "$app_container" php artisan config:clear
runtime() {
    docker exec -i -u librenms -e DB_DATABASE="$1" -e WMNG_PHASE="$2" -e LIBRENMS_PATH="$root" "$app_container" php <<'PHP'
<?php
require getenv('LIBRENMS_PATH').'/vendor/autoload.php';
$app = require getenv('LIBRENMS_PATH').'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, get_class($e).": ".$e->getMessage()."\n"); exit(1); });
$db = Illuminate\Support\Facades\DB::class;
$schema = Illuminate\Support\Facades\Schema::class;
$actual = $db::selectOne('SELECT DATABASE() AS name')->name;
if ($actual !== getenv('DB_DATABASE')) { throw new RuntimeException('Wrong database'); }
$phase = getenv('WMNG_PHASE');
if ($phase === 'identity') { echo $actual; exit; }
if ($phase === 'legacy') {
    if (!str_starts_with($actual, 'wmng_upgrade_')) { throw new RuntimeException('Not scratch'); }
    $schema::dropIfExists('wmng_map_versions');
    $schema::dropIfExists('wmng_map_templates');
    foreach (['title', 'parent_map_id'] as $column) {
        if ($schema::hasColumn('wmng_maps', $column)) { $schema::table('wmng_maps', fn($t) => $t->dropColumn($column)); }
    }
    $map = $db::table('wmng_maps')->insertGetId(['name'=>'upgrade-qualifier-'.uniqid(), 'width'=>913, 'height'=>719, 'options'=>'{"keep":true}']);
    $a = $db::table('wmng_nodes')->insertGetId(['map_id'=>$map, 'label'=>'upgrade-a', 'x'=>11, 'y'=>22]);
    $b = $db::table('wmng_nodes')->insertGetId(['map_id'=>$map, 'label'=>'upgrade-b', 'x'=>33, 'y'=>44]);
    $db::table('wmng_links')->insert(['map_id'=>$map, 'src_node_id'=>$a, 'dst_node_id'=>$b, 'bandwidth_bps'=>123456]);
    $keep = $db::table('plugins')->where('plugin_name','WeathermapNG')->where('version',2)->where('plugin_active',1)->orderByDesc('plugin_id')->first();
    if (!$keep) { throw new RuntimeException('Active v2 fixture absent'); }
    $duplicate = (array)$keep;
    unset($duplicate['plugin_id']);
    $duplicate['version'] = 1;
    $db::table('plugins')->insert($duplicate);
    if ($db::table('plugins')->where('plugin_name','WeathermapNG')->count() < 2) { throw new RuntimeException('Duplicate control absent'); }
}
if ($phase === 'break') {
    if (!str_starts_with($actual, 'wmng_upgrade_')) { throw new RuntimeException('Not scratch'); }
    $schema::table('plugins', fn($t) => $t->dropColumn('plugin_active'));
    exit;
}
if ($phase === 'assert') {
    foreach (['wmng_maps','wmng_nodes','wmng_links','wmng_map_versions','wmng_map_templates'] as $table) {
        if (!$schema::hasTable($table)) { throw new RuntimeException('Missing table'); }
    }
    foreach (['title','parent_map_id'] as $column) {
        if (!$schema::hasColumn('wmng_maps',$column)) { throw new RuntimeException('Missing upgrade column'); }
    }
    if (!$db::table('wmng_map_templates')->where('is_built_in',1)->exists()) { throw new RuntimeException('Missing builtin templates'); }
    if ($db::table('plugins')->where('plugin_name','WeathermapNG')->count() !== 1) { throw new RuntimeException('Duplicates remain'); }
    exit;
}
$tables = ['wmng_maps','wmng_nodes','wmng_links'];
if ($phase === 'full') { $tables = array_merge($tables,['wmng_map_versions','wmng_map_templates']); }
$result = [];
foreach ($tables as $table) {
    $rows = $db::table($table)->orderBy('id')->get()->map(fn($row)=>(array)$row)->all();
    if ($phase !== 'full' && $table === 'wmng_maps') {
        foreach ($rows as &$row) { unset($row['title'],$row['parent_map_id']); }
        unset($row);
    }
    $result[$table] = $rows;
}
$query = $db::table('plugins')->where('plugin_name','WeathermapNG');
if ($phase !== 'full') { $query->where('version',2)->where('plugin_active',1); }
$result['plugins'] = $query->orderBy('plugin_id')->get()->all();
echo json_encode($result, JSON_THROW_ON_ERROR);
PHP
}
runtime librenms full > "$work/original.json"
docker exec "$db_container" mariadb -uroot -e "CREATE DATABASE \`$scratch\`;"
created=true
docker exec "$db_container" mariadb -uroot -e "GRANT ALL ON \`$scratch\`.* TO 'librenms'@'%';"
docker exec "$db_container" mariadb-dump -uroot --single-transaction librenms > "$work/clone.sql"
docker exec -i "$db_container" mariadb -uroot "$scratch" < "$work/clone.sql"
[[ "$(runtime "$scratch" identity)" == "$scratch" ]]
runtime "$scratch" legacy > "$work/legacy.json"
setup() { docker exec -u librenms -e DB_DATABASE="$scratch" -e LIBRENMS_PATH="$root" "$app_container" php "$plugin/database/setup.php"; }
setup
runtime "$scratch" projection > "$work/upgraded.json"
cmp "$work/legacy.json" "$work/upgraded.json"
runtime "$scratch" assert
runtime "$scratch" full > "$work/first.json"
setup
runtime "$scratch" full > "$work/repeated.json"
cmp "$work/first.json" "$work/repeated.json"
runtime "$scratch" break
if setup > "$work/failure.log" 2>&1; then
    echo 'FAIL: missing required plugin column succeeded' >&2
    exit 1
fi
if grep -q 'Database setup completed successfully' "$work/failure.log"; then
    echo 'FAIL: schema failure printed success' >&2
    exit 1
fi
runtime librenms full > "$work/original-after.json"
cmp "$work/original.json" "$work/original-after.json"
echo 'PASS: legacy schema upgrade, preserved rows/settings, repeat, and required-column failure; original fixture unchanged'
