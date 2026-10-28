// Run after quick-install.sh against the disposable docker-compose.dev.yml stack.
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const base = process.argv[2] || 'http://localhost:8000';
const php = code => execFileSync('docker', ['exec', '-u', 'librenms', 'librenms-dev', 'php', '-r', `
    require '/opt/librenms/vendor/autoload.php';
    $app = require '/opt/librenms/bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    ${code}
`], { encoding: 'utf8' });

(async () => {
    const fixture = JSON.parse(php(`
        $tag = bin2hex(random_bytes(6));
        $password = bin2hex(random_bytes(16));
        $user = new App\\Models\\User(['username' => 'wmng-browser-' . $tag, 'auth_type' => 'mysql', 'enabled' => true]);
        $user->setPassword($password);
        $user->save();
        $user->assignRole('admin');
        $map = LibreNMS\\Plugins\\WeathermapNG\\Models\\Map::create(['name' => 'wmng-browser-' . $tag]);
        $child = app(LibreNMS\\Plugins\\WeathermapNG\\Services\\MapService::class)->createMap(['name' => 'wmng-browser-' . $tag . '-child', 'width' => 1013, 'height' => 619]);
        $child->update(['parent_map_id' => $map->id]);
        Illuminate\\Support\\Facades\\DB::table('wmng_maps')->where('id', $child->id)->update(['width' => 1173, 'height' => 641]);
        $legacy = LibreNMS\\Plugins\\WeathermapNG\\Models\\Map::forceCreate(['name' => 'wmng-browser-' . $tag . '-legacy', 'width' => 1173, 'height' => 641, 'options' => ['background' => '#ffffff']]);
        $a = LibreNMS\\Plugins\\WeathermapNG\\Models\\Node::create(['map_id' => $legacy->id, 'label' => 'Legacy A', 'x' => 100, 'y' => 150]);
        $b = LibreNMS\\Plugins\\WeathermapNG\\Models\\Node::create(['map_id' => $legacy->id, 'label' => 'Legacy B', 'x' => 200, 'y' => 150]);
        Illuminate\\Support\\Facades\\DB::table('wmng_links')->insert(['map_id' => $legacy->id, 'src_node_id' => $a->id, 'dst_node_id' => $b->id, 'bandwidth_bps' => 123456]);
        LibreNMS\\Plugins\\WeathermapNG\\Models\\Node::create(['map_id' => $map->id, 'label' => 'Browser fixture', 'x' => 100, 'y' => 100, 'meta' => ['sub_map_id' => $child->id]]);
        LibreNMS\\Plugins\\WeathermapNG\\Models\\Node::create(['map_id' => $child->id, 'label' => 'Child fixture', 'x' => 100, 'y' => 100]);
        echo json_encode(['map' => $map->id, 'child' => $child->id, 'legacy' => $legacy->id, 'user' => $user->user_id, 'username' => $user->username, 'password' => $password, 'token' => config('weathermapng.security.embed_token')]);
    `));
    let browser;
    try {
        browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH });
        const context = await browser.newContext();
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(`${base}/plugin/WeathermapNG/embed/${fixture.map}?interval=1&max=5`);
        assert.ok(new URL(page.url()).pathname.endsWith('/login'));
        await page.locator('[name=username]').fill(fixture.username);
        await page.locator('[name=password]').fill(fixture.password);
        await Promise.all([
            page.waitForURL(url => !url.pathname.endsWith('/login')),
            page.locator('button[type=submit]').click(),
        ]);
        assert.ok(new URL(page.url()).pathname.endsWith(`/embed/${fixture.map}`));
        assert.equal((await page.goto(`${base}/plugin/WeathermapNG`)).status(), 200);
        const editorData = page.waitForResponse(r => r.url().endsWith(`/api/maps/${fixture.child}/json`));
        await page.goto(`${base}/plugin/WeathermapNG/editor/${fixture.child}`);
        assert.equal((await editorData).status(), 200);
        assert.equal((await (await editorData).json()).id, fixture.child);
        await page.waitForFunction(() => window.WMNG?.EditorState?.mapDataLoaded);
        assert.equal(await page.evaluate(() => WMNG.EditorState.nodes.length), 1);
        const saved = page.waitForResponse(r => r.url().endsWith(`/api/maps/${fixture.child}/save`));
        await page.locator('[onclick="saveMap()"]').click();
        assert.equal((await saved).status(), 200);
        assert.equal((await (await context.request.get(`${base}/plugin/WeathermapNG/api/maps/${fixture.child}/json`)).json()).nodes.length, 1);
        console.log('PASS: browser login, index, editor JSON load, and save preserve map content');

        for (const [id, width, height] of [[fixture.legacy, 1173, 641], [fixture.child, 1013, 619]]) {
            const api = `${base}/plugin/WeathermapNG/api/maps/${id}`;
            const before = await (await context.request.get(`${api}/json`)).json();
            assert.deepEqual([before.width, before.height], [width, height]);
            await page.goto(`${base}/plugin/WeathermapNG/editor/${id}`);
            await page.waitForFunction(() => window.WMNG?.EditorState?.mapDataLoaded);
            assert.equal(await page.locator('#map-width').inputValue(), String(width));
            assert.equal(await page.locator('#map-height').inputValue(), String(height));
            assert.deepEqual(await page.evaluate(() => [WMNG.EditorState.mapWidth, WMNG.EditorState.mapHeight]), [width, height]);
            const csrf = await page.evaluate(() => getCsrfToken());
            const versionResponse = await context.request.post(`${api}/versions`, {
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                data: { name: 'Dimensions before save', description: 'Browser regression' },
            });
            assert.equal(versionResponse.status(), 201);
            const version = (await versionResponse.json()).version;
            assert.deepEqual([version.config_snapshot.map.width, version.config_snapshot.map.height], [width, height]);
            const savedDimensions = page.waitForResponse(r => r.url().endsWith(`/api/maps/${id}/save`));
            await page.locator('[onclick="saveMap()"]').click();
            assert.equal((await savedDimensions).status(), 200);
            const after = await (await context.request.get(`${api}/json`)).json();
            assert.deepEqual([after.width, after.height], [width, height]);
            const graph = data => ({
                nodes: data.nodes.map(n => [n.label, n.x, n.y]).sort((a, b) => a[0].localeCompare(b[0])),
                links: data.links.map(l => [
                    data.nodes.find(n => n.id === l.src).label,
                    data.nodes.find(n => n.id === l.dst).label,
                    l.bandwidth_bps,
                ]),
            });
            assert.deepEqual(graph(after), graph(before));
            const historical = await (await context.request.get(`${base}/plugin/WeathermapNG/api/versions/${version.id}`)).json();
            assert.deepEqual(historical.snapshot, version.config_snapshot);
            await page.reload();
            await page.waitForFunction(() => window.WMNG?.EditorState?.mapDataLoaded);
            assert.equal(await page.locator('#map-width').inputValue(), String(width));
            assert.equal(await page.locator('#map-height').inputValue(), String(height));
        }
        console.log('PASS: legacy dimensions and modern options survive editor save/reload and version capture');

        const live = page.waitForResponse(r => r.url().includes(`/api/maps/${fixture.map}/live`));
        await page.goto(`${base}/plugin/WeathermapNG/embed/${fixture.map}?sse=0&interval=1`);
        assert.equal((await live).status(), 200);
        assert.equal(await page.evaluate(() => WMNG.EmbedConfig.embedToken), null);
        console.log('PASS: session viewer polls without an embed token');

        const tokenContext = await browser.newContext();
        const tokenPage = await tokenContext.newPage();
        tokenPage.on('pageerror', error => errors.push(error.message));
        const url = `${base}/plugin/WeathermapNG/embed/${fixture.map}?token=${encodeURIComponent(fixture.token)}&interval=1&max=5`;
        const appStream = tokenPage.waitForResponse(r => r.url().includes(`/api/maps/${fixture.map}/sse`));
        assert.equal((await tokenPage.goto(url)).status(), 200);
        assert.equal((await appStream).status(), 200);
        assert.equal(new URL((await appStream).url()).searchParams.get('token'), fixture.token);
        await tokenPage.waitForFunction(() => currentTransport === 'sse' && mapData.nodes.length > 0);
        const stream = await tokenPage.evaluate(() => new Promise((resolve, reject) => {
            const events = new EventSource(embedUrl(`${baseUrl}/plugin/WeathermapNG/api/maps/${mapId}/sse?interval=1&max=5`));
            const timer = setTimeout(() => { events.close(); reject(new Error('SSE timed out')); }, 10000);
            events.onmessage = event => { clearTimeout(timer); events.close(); resolve(JSON.parse(event.data)); };
            events.onerror = () => { clearTimeout(timer); events.close(); reject(new Error('SSE rejected')); };
        }));
        assert.ok(Object.keys(stream.nodes).length > 0);
        const jsonResponse = tokenPage.waitForResponse(r => r.url().includes(`/api/maps/${fixture.map}/json`));
        await tokenPage.evaluate(() => fetchMapData());
        assert.equal((await jsonResponse).status(), 200);
        await tokenPage.evaluate(child => navigateToSubMap(child), fixture.child);
        await tokenPage.waitForURL(url => url.pathname.endsWith(`/embed/${fixture.child}`));
        assert.equal(new URL(tokenPage.url()).searchParams.get('token'), fixture.token);
        await tokenPage.locator('[data-crumb-id]').first().click();
        await tokenPage.waitForURL(url => url.pathname.endsWith(`/embed/${fixture.map}`));
        console.log('PASS: anonymous token viewer renders, refreshes JSON, streams live data, and navigates nested maps');

        await tokenPage.goto(url + '&sse=0&kiosk=1&cycle=5');
        await tokenPage.waitForURL(next => next.pathname.endsWith(`/embed/${fixture.child}`), { timeout: 15000 });
        assert.equal(new URL(tokenPage.url()).searchParams.get('token'), fixture.token);
        const tokenLive = tokenPage.waitForResponse(r => r.url().includes(`/api/maps/${fixture.child}/live`));
        await tokenPage.locator('#kiosk-exit').click();
        await tokenPage.waitForURL(next => !next.searchParams.has('kiosk'));
        assert.equal(new URL(tokenPage.url()).searchParams.get('token'), fixture.token);
        assert.equal((await tokenLive).status(), 200);
        assert.equal((await tokenContext.request.get(`${base}/plugin/WeathermapNG/api/maps/${fixture.map}/json?token=invalid`)).status(), 401);
        assert.deepEqual(errors, []);
        console.log('PASS: token polling, kiosk cycling/exit, invalid-token denial, and no browser script errors');
    } finally {
        await browser?.close();
        php(`
            foreach ([${fixture.legacy}, ${fixture.child}, ${fixture.map}] as $id) {
                $map = LibreNMS\\Plugins\\WeathermapNG\\Models\\Map::findOrFail($id);
                $map->links()->delete();
                LibreNMS\\Plugins\\WeathermapNG\\Models\\MapVersion::where('map_id', $id)->delete();
                $map->nodes()->delete();
                $map->delete();
            }
            App\\Models\\User::findOrFail(${fixture.user})->delete();
        `);
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
