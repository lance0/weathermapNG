// Run against the disposable LibreNMS stack; clean up only the owned fixture IDs.
const assert = require('node:assert/strict');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const base = process.argv[2] || 'http://localhost:8000';
const tag = `wmng-version-http-${randomBytes(6).toString('hex')}`;
const php = code => execFileSync('docker', ['exec', '-u', 'librenms', 'librenms-dev', 'php', '-r', `
    require '/opt/librenms/vendor/autoload.php';
    $app = require '/opt/librenms/bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    ${code}
`], { encoding: 'utf8' });
const sorted = values => values.sort((a,b) => Number(a.id)-Number(b.id));
function graphProjection(graph) {
    return {
        id: graph.id, name: graph.name, title: graph.title, width: graph.width, height: graph.height,
        background: graph.background, options: graph.options,
        nodes: sorted(graph.nodes.map(node => ({ id:node.id, label:node.label, x:node.x, y:node.y, device_id:node.device_id, meta:node.meta }))),
        links: sorted(graph.links.map(link => ({ id:link.id, src_node_id:link.src, dst_node_id:link.dst,
            port_id_a:link.port_id_a, port_id_b:link.port_id_b, bandwidth_bps:link.bandwidth_bps, style:link.style }))),
    };
}
function snapshotProjection(snapshot) {
    return {
        id:snapshot.map.id, name:snapshot.map.name, title:snapshot.map.title, width:snapshot.map.width,
        height:snapshot.map.height, background:snapshot.map.background, options:snapshot.map.options,
        nodes:sorted(Object.values(snapshot.nodes)), links:sorted(Object.values(snapshot.links)),
    };
}
(async () => {
    let fixture;
    let browser;
    try {
        fixture = JSON.parse(php(`
            if (config('weathermapng.webhooks.urls') || trim((string)config('weathermapng.webhook_urls',''))) {
                throw new RuntimeException('HTTP probe requires no external webhook destinations.');
            }
            echo json_encode(Illuminate\\Support\\Facades\\DB::transaction(function () {
                $users = [];
                foreach (['admin','reader'] as $role) {
                    $password = bin2hex(random_bytes(16));
                    $user = new App\\Models\\User(['username'=>'${tag}-'.$role, 'auth_type'=>'mysql', 'enabled'=>true]);
                    $user->setPassword($password); $user->save();
                    if ($role === 'admin') $user->assignRole('admin');
                    $users[$role] = ['id'=>$user->user_id,'username'=>$user->username,'displayName'=>$user->realname ?: $user->username,'password'=>$password];
                }
                $map = LibreNMS\\Plugins\\WeathermapNG\\Models\\Map::create([
                    'name'=>'${tag}', 'title'=>'Historical HTTP title', 'description'=>'Historical description',
                    'options'=>['width'=>1173,'height'=>641,'background'=>'#123456','tags'=>['historical'],'default_node_style'=>['shape'=>'rect']],
                ]);
                $map->forceFill(['width'=>1173,'height'=>641])->save();
                $a = LibreNMS\\Plugins\\WeathermapNG\\Models\\Node::create(['map_id'=>$map->id,'label'=>'Historical A','x'=>100,'y'=>200,'meta'=>['icon'=>'router']]);
                $b = LibreNMS\\Plugins\\WeathermapNG\\Models\\Node::create(['map_id'=>$map->id,'label'=>'Historical B','x'=>300,'y'=>400,'meta'=>['color'=>'#123456']]);
                $link = LibreNMS\\Plugins\\WeathermapNG\\Models\\Link::create(['map_id'=>$map->id,'src_node_id'=>$a->id,'dst_node_id'=>$b->id,'bandwidth_bps'=>123456,'style'=>['width'=>3]]);
                $webhooks = new class extends LibreNMS\\Plugins\\WeathermapNG\\Services\\WebhookDispatcher {
                    public function dispatch(string $event, array $map): void {}
                };
                $service = new LibreNMS\\Plugins\\WeathermapNG\\Services\\MapVersionService($webhooks);
                $target = $service->createVersion($map,'HTTP target','Historical graph',$users['admin']['id']);
                $map->update(['title'=>'Current HTTP title','description'=>'Current description','options'=>['width'=>999,'height'=>777,'background'=>'#abcdef','tags'=>['current'],'custom'=>['keep'=>true]]]);
                $map->forceFill(['width'=>999,'height'=>777])->save();
                $a->update(['label'=>'Current A','x'=>55,'meta'=>['icon'=>'switch']]);
                $extra = LibreNMS\\Plugins\\WeathermapNG\\Models\\Node::create(['map_id'=>$map->id,'label'=>'Current extra','x'=>500,'y'=>600]);
                LibreNMS\\Plugins\\WeathermapNG\\Models\\Link::create(['map_id'=>$map->id,'src_node_id'=>$b->id,'dst_node_id'=>$extra->id,'bandwidth_bps'=>654321]);
                $link->update(['style'=>['width'=>7]]);
                $current = $service->createVersion($map,'HTTP current','Before restoration',$users['admin']['id']);
                $invalid = LibreNMS\\Plugins\\WeathermapNG\\Models\\MapVersion::create(['map_id'=>$map->id,'name'=>'HTTP malformed','config_snapshot'=>[],'created_by'=>$users['admin']['id']]);
                for ($i=1; $i<=22; $i++) {
                    LibreNMS\\Plugins\\WeathermapNG\\Models\\MapVersion::create(['map_id'=>$map->id,'name'=>'HTTP archive '.$i,
                        'config_snapshot'=>$current->config_snapshot,'created_by'=>$users['admin']['id']]);
                }
                $other = LibreNMS\\Plugins\\WeathermapNG\\Models\\Map::create(['name'=>'${tag}-other']);
                $otherVersion = $service->createVersion($other,'Other map version',null,$users['admin']['id']);
                return ['users'=>$users,'map'=>$map->id,'target'=>$target->id,'targetSnapshot'=>$target->config_snapshot,
                    'currentSnapshot'=>$current->config_snapshot,'invalid'=>$invalid->id,'other'=>$other->id,'otherVersion'=>$otherVersion->id];
            }));
        `));
        const state = () => php(`
            $result = ['map'=>(array)Illuminate\\Support\\Facades\\DB::table('wmng_maps')->where('id',${fixture.map})->first()];
            foreach (['wmng_nodes','wmng_links','wmng_map_versions'] as $table) {
                $result[$table] = Illuminate\\Support\\Facades\\DB::table($table)->where('map_id',${fixture.map})->orderBy('id')->get()->all();
            }
            echo json_encode($result, JSON_THROW_ON_ERROR);
        `);
        const before = state();
        browser = await chromium.launch({ headless:true, executablePath:process.env.CHROMIUM_PATH });
        const login = async credentials => {
            const context = await browser.newContext();
            const page = await context.newPage();
            await page.goto(`${base}/plugin/WeathermapNG`);
            await page.locator('[name=username]').fill(credentials.username);
            await page.locator('[name=password]').fill(credentials.password);
            await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')),page.locator('button[type=submit]').click()]);
            await page.goto(`${base}/plugin/WeathermapNG`);
            const csrf = await page.locator('meta[name=csrf-token]').getAttribute('content');
            assert.ok(csrf, 'logged-in page has session CSRF token');
            return { context, csrf };
        };
        const reader = await login(fixture.users.reader);
        const admin = await login(fixture.users.admin);
        const exportUrl = `${base}/plugin/WeathermapNG/api/maps/${fixture.map}/versions/export`;
        const deniedExport = await reader.context.request.get(exportUrl);
        assert.equal(deniedExport.status(),403,'non-admin history export denied');
        assert.equal(state(),before,'denied export leaves raw map/graph/history identical');
        const exportedResponse = await admin.context.request.get(exportUrl);
        assert.equal(exportedResponse.status(),200);
        const exported = (await exportedResponse.json()).export_data;
        const exportRowsBefore = JSON.parse(before).wmng_map_versions;
        assert.equal(exportRowsBefore.length,25);
        assert.equal(exported.metadata.total_versions,25);
        assert.equal(exported.versions.length,25,'export includes history beyond list limits');
        const ordered = [...exportRowsBefore].sort((a,b) => b.created_at.localeCompare(a.created_at) || b.id-a.id);
        assert.deepEqual(exported.versions.map(row => row.id),ordered.map(row => row.id));
        for (const row of exported.versions) {
            assert.notEqual(row.id,fixture.otherVersion,'other map history stays excluded');
            assert.deepEqual(row.snapshot,JSON.parse(exportRowsBefore.find(saved => saved.id===row.id).config_snapshot));
            assert.equal(row.created_by,fixture.users.admin.displayName);
        }
        assert.equal(state(),before,'successful export leaves raw map/graph/history identical');
        console.log('PASS: export all 25 snapshots in order, admin-only, map-scoped and read-only');
        const post = (session,id) => session.context.request.post(`${base}/plugin/WeathermapNG/api/versions/${id}/restore`, {
            data:{}, headers:{ 'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':session.csrf },
        });
        const denied = await post(reader,fixture.target);
        assert.equal(denied.status(),403,'non-admin restore denied through actual controller');
        assert.equal(state(),before,'denied restore leaves raw map/graph/history identical');
        console.log('PASS: non-admin HTTP restore403 with unchanged raw state');

        const malformed = await post(admin,fixture.invalid);
        assert.equal(malformed.status(),422,'malformed target returns safe422');
        const malformedBody = await malformed.json();
        assert.deepEqual(malformedBody,{ success:false,message:'This version cannot be safely restored. The map was not changed.' });
        assert.equal(/SQLSTATE|PDOException|QueryException|stack trace|password|dsn=/i.test(JSON.stringify(malformedBody)),false);
        const afterMalformed = state();
        assert.equal(afterMalformed,before,'malformed restore preserves every raw map/graph/history field');
        console.log('PASS: malformed HTTP restore422 redacted and byte-identical state');

        const restoredResponse = await post(admin,fixture.target);
        assert.equal(restoredResponse.status(),200,'valid restore succeeds');
        const restored = await restoredResponse.json();
        assert.equal(restored.success,true);
        assert.equal(restored.map.title,fixture.targetSnapshot.map.title,'response map has fresh restored title');
        assert.deepEqual(restored.map.options,fixture.targetSnapshot.map.options,'response map has fresh restored complete options');
        const graphResponse = await admin.context.request.get(`${base}/plugin/WeathermapNG/api/maps/${fixture.map}/json`);
        assert.equal(graphResponse.status(),200);
        const graph = await graphResponse.json();
        assert.deepEqual(graphProjection(graph),snapshotProjection(fixture.targetSnapshot),'real HTTP JSON matches historical graph/configuration');
        const afterValid = JSON.parse(state());
        const beforeRows = JSON.parse(before).wmng_map_versions;
        assert.equal(afterValid.wmng_map_versions.length,beforeRows.length+2);
        for (const row of beforeRows) assert.deepEqual(afterValid.wmng_map_versions.find(candidate=>candidate.id===row.id),row,'prior history row remains byte-equivalent');
        const added = afterValid.wmng_map_versions.filter(row=>!beforeRows.some(previous=>previous.id===row.id));
        const checkpoint = added.find(row=>row.name===`Before restore #${fixture.target}`);
        const resultVersion = added.find(row=>row.name===`Restored version #${fixture.target}`);
        assert.ok(checkpoint && resultVersion);
        assert.deepEqual(JSON.parse(checkpoint.config_snapshot),fixture.currentSnapshot,'checkpoint captures exact preceding state');
        assert.deepEqual(JSON.parse(resultVersion.config_snapshot),fixture.targetSnapshot,'new history captures exact restored state');
        for (const row of added) assert.equal(Number(row.created_by),fixture.users.admin.id,'restoring admin is attributed');
        const historyResponse = await admin.context.request.get(`${base}/plugin/WeathermapNG/api/maps/${fixture.map}/versions`);
        assert.equal(historyResponse.status(),200);
        const history = await historyResponse.json();
        assert.equal(history.display_limit, 20);
        assert.equal(history.max_versions, history.display_limit, 'retain the existing display-limit field for API consumers');
        assert.equal(history.latest_version.id,resultVersion.id);
        for (const row of history.versions.filter(row=>added.some(addedRow=>addedRow.id===row.id))) {
            assert.equal(Number(row.creator.user_id),fixture.users.admin.id);
            assert.equal(row.creator.username,fixture.users.admin.username);
        }
        console.log('PASS: valid HTTP restore200 fresh response/graph, two history rows, immutable target, creator attribution');

        const recoveredResponse = await post(admin,checkpoint.id);
        assert.equal(recoveredResponse.status(),200,'checkpoint itself restores successfully');
        const recoveredBody = await recoveredResponse.json();
        assert.equal(recoveredBody.map.title,fixture.currentSnapshot.map.title);
        assert.deepEqual(recoveredBody.map.options,fixture.currentSnapshot.map.options);
        const recoveredGraphResponse = await admin.context.request.get(`${base}/plugin/WeathermapNG/api/maps/${fixture.map}/json`);
        assert.equal(recoveredGraphResponse.status(),200);
        const recoveredGraph = await recoveredGraphResponse.json();
        assert.deepEqual(graphProjection(recoveredGraph),snapshotProjection(fixture.currentSnapshot),'checkpoint recovers exact prior graph/configuration through HTTP');
        const afterCheckpoint = JSON.parse(state());
        assert.equal(afterCheckpoint.wmng_map_versions.length,beforeRows.length+4);
        for (const row of afterValid.wmng_map_versions) assert.deepEqual(afterCheckpoint.wmng_map_versions.find(candidate=>candidate.id===row.id),row);
        console.log('PASS: checkpoint HTTP recovery restores preceding graph/configuration and preserves history');
    } finally {
        try { await browser?.close(); } finally {
            if (fixture) php(`
                Illuminate\\Support\\Facades\\DB::transaction(function () {
                    Illuminate\\Support\\Facades\\DB::table('wmng_links')->where('map_id',${fixture.map})->delete();
                    Illuminate\\Support\\Facades\\DB::table('wmng_nodes')->where('map_id',${fixture.map})->delete();
                    Illuminate\\Support\\Facades\\DB::table('wmng_map_versions')->whereIn('map_id',[${fixture.map},${fixture.other}])->delete();
                    Illuminate\\Support\\Facades\\DB::table('wmng_maps')->whereIn('id',[${fixture.map},${fixture.other}])->delete();
                    foreach ([${fixture.users.admin.id},${fixture.users.reader.id}] as $id) App\\Models\\User::findOrFail($id)->delete();
                });
            `);
        }
    }
})().catch(error=>{ console.error(error); process.exitCode=1; });
