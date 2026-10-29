// Real Compare UI against the disposable LibreNMS stack; only owned fixture IDs are removed.
const assert = require('node:assert/strict');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { tmpdir } = require('node:os');
const { join } = require('node:path');
const { chromium } = require('playwright-core');
const base = process.argv[2] || 'http://localhost:8000';
const tag = `wmng-version-diff-${randomBytes(6).toString('hex')}`;
const php = code => execFileSync('docker', ['exec', '-u', 'librenms', 'librenms-dev', 'php', '-r', `
    require '/opt/librenms/vendor/autoload.php';
    $app = require '/opt/librenms/bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    ${code}
`], { encoding: 'utf8' });

(async () => {
    let fixture, browser;
    try {
        fixture = JSON.parse(php(`
            echo json_encode(Illuminate\\Support\\Facades\\DB::transaction(function () {
                $password = bin2hex(random_bytes(16));
                $user = new App\\Models\\User(['username'=>'${tag}', 'auth_type'=>'mysql', 'enabled'=>true]);
                $user->setPassword($password); $user->save(); $user->assignRole('admin');
                $map = LibreNMS\\Plugins\\WeathermapNG\\Models\\Map::create([
                    'name'=>'${tag}', 'title'=>'Before title',
                    'options'=>['width'=>800,'height'=>600,'background'=>'#ffffff','tags'=>['old'],'removeMe'=>null],
                ]);
                $nodes = [];
                for ($i = 1; $i <= 106; $i++) {
                    $nodes[] = LibreNMS\\Plugins\\WeathermapNG\\Models\\Node::create(['map_id'=>$map->id,'label'=>'Node '.$i,'x'=>$i*10,'y'=>$i*20]);
                }
                $links = [];
                for ($i = 1; $i <= 161; $i++) {
                    $links[] = LibreNMS\\Plugins\\WeathermapNG\\Models\\Link::create(['map_id'=>$map->id,'src_node_id'=>$nodes[0]->id,'dst_node_id'=>$nodes[1]->id]);
                }
                $webhooks = new class extends LibreNMS\\Plugins\\WeathermapNG\\Services\\WebhookDispatcher {
                    public function dispatch(string $event, array $map): void {}
                };
                $service = new LibreNMS\\Plugins\\WeathermapNG\\Services\\MapVersionService($webhooks);
                $large = $service->createVersion($map, 'Large snapshot', null, $user->user_id);
                $largeSnapshot = $large->config_snapshot;
                $before = $largeSnapshot;
                $before['nodes'] = array_slice(array_values($before['nodes']), 0, 3);
                $before['links'] = array_slice(array_values($before['links']), 0, 2);
                $after = $largeSnapshot;
                $after['nodes'] = array_intersect_key($after['nodes'], array_flip([$nodes[0]->id,$nodes[1]->id,$nodes[3]->id]));
                $after['links'] = array_intersect_key($after['links'], array_flip([$links[0]->id,$links[2]->id]));
                $after['nodes'][$nodes[0]->id]['x'] = 90;
                $after['nodes'][$nodes[0]->id]['y'] = 50;
                $after['nodes'][$nodes[0]->id]['label'] = '<svg onload="window.__versionDiffXss=true">';
                $after['nodes'][$nodes[0]->id]['meta'] = ['tooltip'=>'<img src=x onerror="window.__versionDiffXss=true">','sub_map_id'=>null];
                $after['links'][$links[0]->id]['bandwidth_bps'] = 500;
                $after['links'][$links[0]->id]['style'] = ['width'=>7,'via_points'=>[['x'=>30,'y'=>40]]];
                $after['map']['title'] = 'After title';
                $after['map']['options']['tags'] = ['new'];
                unset($after['map']['options']['removeMe']);
                $legacy = $before;
                unset($legacy['map']['options'], $legacy['map']['description'], $legacy['map']['parent_map_id'], $legacy['map']['raw_width'], $legacy['map']['raw_height']);
                $nullOptions = $before;
                $nullOptions['map']['options'] = null;
                $empty = $before;
                $empty['nodes'] = []; $empty['links'] = [];
                $versions = [];
                foreach (['before'=>['Before snapshot',$before], 'after'=>['After <img src=x onerror="window.__versionDiffXss=true">',$after],
                    'legacy'=>['Legacy snapshot',$legacy], 'legacy2'=>['Another legacy snapshot',$legacy], 'null'=>['Recorded null options',$nullOptions],
                    'empty'=>['Empty snapshot',$empty], 'empty2'=>['Another empty snapshot',$empty]] as $key => [$name,$snapshot]) {
                    $version = LibreNMS\\Plugins\\WeathermapNG\\Models\\MapVersion::create(['map_id'=>$map->id,'name'=>$name,'config_snapshot'=>$snapshot,'created_by'=>$user->user_id]);
                    $versions[$key] = ['id'=>$version->id,'name'=>$name];
                }
                $largeSnapshot['nodes'][$nodes[105]->id]['x'] = null;
                $largeSnapshot['links'][$links[160]->id]['src_node_id'] = $nodes[105]->id + 1000000;
                $large->update(['config_snapshot'=>$largeSnapshot]);
                $versions['large'] = ['id'=>$large->id,'name'=>$large->name];
                return ['map'=>$map->id,'user'=>$user->user_id,'username'=>$user->username,'password'=>$password,'versions'=>$versions,
                    'nodes'=>array_map(fn($node)=>$node->id,array_slice($nodes,0,4)), 'links'=>array_map(fn($link)=>$link->id,array_slice($links,0,3))];
            }), JSON_THROW_ON_ERROR);
        `));
        const state = () => php(`
            $state = ['map'=>(array)Illuminate\\Support\\Facades\\DB::table('wmng_maps')->where('id',${fixture.map})->first()];
            foreach (['wmng_nodes','wmng_links','wmng_map_versions'] as $table) {
                $state[$table] = Illuminate\\Support\\Facades\\DB::table($table)->where('map_id',${fixture.map})->orderBy('id')->get()->all();
            }
            echo json_encode($state, JSON_THROW_ON_ERROR);
        `);
        const original = state();
        browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH });
        const context = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(`${base}/plugin/WeathermapNG/editor/${fixture.map}`);
        await page.locator('[name=username]').fill(fixture.username);
        await page.locator('[name=password]').fill(fixture.password);
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('button[type=submit]').click()]);
        await page.goto(`${base}/plugin/WeathermapNG/editor/${fixture.map}`);
        await page.waitForFunction(() => WMNG.EditorState.mapDataLoaded);
        await page.locator('#versionHistoryBtn').click();
        await page.locator(`#versionList .list-group-item[data-version-id="${fixture.versions.before.id}"]`).waitFor();

        const path = (left, right) => `/api/versions/${fixture.versions[left].id}/compare/${fixture.versions[right].id}`;
        const start = async (left, right) => {
            const item = page.locator(`#versionList .list-group-item[data-version-id="${fixture.versions[left].id}"]`);
            await item.locator('[data-action=compare]').click();
            await item.locator('select').selectOption(String(fixture.versions[right].id));
            await item.locator('[data-action=compare-go]').click();
        };
        const ready = async (left, right) => {
            await page.waitForFunction(([leftName, rightName]) => {
                const captions = [...document.querySelectorAll('#versionDiffVisual figcaption')].map(node => node.textContent);
                return document.getElementById('versionDiffStatus').textContent === ''
                    && captions[0] === `Before: ${leftName}` && captions[1] === `After: ${rightName}`;
            }, [fixture.versions[left].name, fixture.versions[right].name]);
        };
        const compare = async (left, right) => {
            const response = page.waitForResponse(response => response.url().endsWith(path(left, right)));
            await start(left, right);
            const result = await response;
            assert.equal(result.status(), 200);
            await ready(left, right);
            return result.json();
        };
        const rows = () => page.locator('#versionDiffVisual tbody tr').evaluateAll(rows => rows.map(row => [...row.children].map(cell => cell.textContent)));
        const position = (pane, id) => page.locator(`#versionDiffVisual .version-diff-pane:nth-child(${pane}) .version-diff-node[data-id="${id}"] circle`).evaluate(circle => [circle.getAttribute('cx'), circle.getAttribute('cy')]);

        const result = await compare('before', 'after');
        assert.deepEqual(result.diff, { nodes_added: [fixture.nodes[3]], nodes_removed: [fixture.nodes[2]], nodes_modified: [fixture.nodes[0]], links_added: [fixture.links[2]], links_removed: [fixture.links[1]], links_modified: [fixture.links[0]] });
        assert.deepEqual(await position(1, fixture.nodes[1]), await position(2, fixture.nodes[1]), 'unchanged positions share one frame');
        assert.notDeepEqual(await position(1, fixture.nodes[0]), await position(2, fixture.nodes[0]), 'moved node remains visibly moved');
        assert.equal(await page.locator(`#versionDiffVisual .version-diff-node.version-diff-added[data-id="${fixture.nodes[3]}"]`).count(), 1);
        assert.equal(await page.locator(`#versionDiffVisual .version-diff-link.version-diff-removed[data-id="${fixture.links[1]}"]`).count(), 1);
        let details = await rows();
        assert.ok(details.some(row => row[0] === `Node #${fixture.nodes[0]} (modified)` && row[1] === 'x' && row[2] === '10' && row[3] === '90'));
        assert.ok(details.some(row => row[0] === `Link #${fixture.links[0]} (modified)` && row[1] === 'bandwidth_bps' && row[3] === '500'));
        assert.ok(details.some(row => row[1] === 'style' && row[3].includes('via_points')));
        assert.ok(details.some(row => row[0] === 'Map option (modified)' && row[1] === 'tags' && row[3].includes('new')));
        assert.ok(details.some(row => row[1] === 'removeMe' && row[2] === 'null' && row[3] === 'Not set'));
        assert.ok((await page.locator('#versionDiffVisual').textContent()).includes('<img src=x onerror='));
        assert.equal(await page.locator('#versionDiffVisual img, #versionDiffVisual script, #versionDiffVisual foreignObject').count(), 0);
        assert.equal(await page.evaluate(() => window.__versionDiffXss), undefined);
        const wideScreenshot = join(tmpdir(), `${tag}-wide.png`);
        await page.screenshot({ path: wideScreenshot, fullPage: true });
        console.log(`PASS: actual comparison IDs, shared geometry, field/config details and literal unsafe text; screenshot ${wideScreenshot}`);

        await compare('after', 'before');
        assert.equal(await page.locator(`#versionDiffVisual .version-diff-pane:nth-child(2) .version-diff-added[data-id="${fixture.nodes[2]}"]`).count(), 1);
        await compare('legacy', 'legacy2');
        assert.equal(await page.locator('#versionDiffVisual .version-diff-availability').count(), 2);
        for (const note of await page.locator('#versionDiffVisual .version-diff-availability').allTextContents()) {
            for (const label of ['options', 'description', 'parent map', 'stored width', 'stored height']) assert.ok(note.includes(label));
        }
        assert.equal(await page.locator('#versionDiffVisual tbody tr').count(), 0, 'identical missing fields are not fake changes');
        assert.ok((await page.locator('#versionDiffVisual .version-diff-limit').textContent()).startsWith('Showing 0 of 0 field differences.'));
        await compare('legacy', 'after');
        details = await rows();
        assert.ok(details.some(row => row[1] === 'tags' && row[2] === 'Unavailable — not recorded in this version' && row[0] === 'Map option (unavailable)'));
        await compare('null', 'before');
        assert.equal(await page.locator('#versionDiffVisual .version-diff-availability').count(), 0, 'recorded null remains available');
        assert.ok((await rows()).some(row => row[1] === 'options' && row[2] === 'null'));
        await compare('empty', 'empty2');
        assert.equal(await page.locator('#versionDiffVisual .version-diff-node').count(), 0);
        assert.equal(await page.locator('#versionDiffVisual .version-diff-empty').textContent(), 'No recorded field differences.');
        await compare('empty', 'large');
        assert.equal(await page.locator('#versionDiffVisual .version-diff-pane:nth-child(2) .version-diff-node').count(), 100);
        assert.equal(await page.locator('#versionDiffVisual .version-diff-pane:nth-child(2) .version-diff-link').count(), 150);
        assert.equal(await page.locator('#versionDiffVisual tbody tr').count(), 200);
        assert.ok((await page.locator('#versionDiffVisual .version-diff-pane:nth-child(2) .version-diff-counts').textContent()).includes('100 of 106 shown; 1 invalid, 5 outside preview'));
        assert.ok((await page.locator('#versionDiffVisual .version-diff-pane:nth-child(2) .version-diff-counts').textContent()).includes('150 of 161 shown; 1 invalid records/endpoints, 10 outside preview'));
        console.log('PASS: reversed direction, unavailable legacy options, recorded null, empty graphs and exact display-limit counts');

        const failedRoute = `**${path('before', 'after')}`;
        await page.route(failedRoute, route => route.fulfill({ status: 503, contentType: 'application/json', body: '{"error":"fixture failure"}' }));
        await start('before', 'after');
        await page.waitForFunction(() => document.getElementById('versionDiffStatus').textContent.startsWith('Failed to load comparison.'));
        assert.equal(await page.locator('#versionDiffVisual svg').count(), 0, 'failed request clears stale diagrams');
        assert.equal(await page.locator('#diffSummary li').count(), 0);
        await page.unroute(failedRoute);

        let release, started;
        const barrier = new Promise(resolve => { release = resolve; });
        const entered = new Promise(resolve => { started = resolve; });
        await page.route(failedRoute, async route => { started(); await barrier; await route.continue(); });
        const oldResponse = page.waitForResponse(response => response.url().endsWith(path('before', 'after')));
        try {
            await start('before', 'after');
            await entered;
            await compare('after', 'before');
        } finally {
            release();
        }
        await (await oldResponse).finished();
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        await ready('after', 'before');
        await page.unroute(failedRoute);
        console.log('PASS: failures clear stale content and older responses cannot replace the latest comparison');

        await page.setViewportSize({ width: 375, height: 900 });
        await compare('before', 'after');
        assert.equal(await page.locator('#versionDiffVisual .version-diff-pane').evaluateAll(panes => {
            const [a, b] = panes.map(pane => pane.getBoundingClientRect());
            return b.top >= a.bottom && a.left >= 0 && b.left >= 0 && a.right <= innerWidth && b.right <= innerWidth;
        }), true, 'previews stack and fit narrow screens');
        assert.equal(await page.locator('#versionDiffVisual .version-diff-table-wrap').evaluate(node => {
            const bounds = node.getBoundingClientRect();
            return bounds.left >= 0 && bounds.right <= innerWidth;
        }), true, 'wide table scrolls inside the modal');
        const narrowScreenshot = join(tmpdir(), `${tag}-narrow.png`);
        await page.screenshot({ path: narrowScreenshot, fullPage: true });
        assert.equal(state(), original, 'all comparisons preserve exact live graph and saved history rows');
        assert.deepEqual(errors, []);
        console.log(`PASS: responsive comparison and unchanged raw graph/history; screenshot ${narrowScreenshot}`);
    } finally {
        try { await browser?.close(); } finally {
            if (fixture) php(`
                Illuminate\\Support\\Facades\\DB::transaction(function () {
                    foreach (['wmng_links','wmng_nodes','wmng_map_versions'] as $table) Illuminate\\Support\\Facades\\DB::table($table)->where('map_id',${fixture.map})->delete();
                    Illuminate\\Support\\Facades\\DB::table('wmng_maps')->where('id',${fixture.map})->delete();
                    App\\Models\\User::findOrFail(${fixture.user})->delete();
                });
            `);
        }
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
