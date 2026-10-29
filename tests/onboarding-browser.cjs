// Run only against a disposable, empty LibreNMS database.
const assert = require('node:assert/strict');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');

const base = process.argv[2] || 'http://localhost:8000';
const tag = randomBytes(6).toString('hex');
const name = `wmng-onboarding-${tag}`;
const customName = `${name}-custom`;
const php = code => execFileSync('docker', ['exec', '-u', 'librenms', 'librenms-dev', 'php', '-r', `
    require '/opt/librenms/vendor/autoload.php';
    $app = require '/opt/librenms/bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    ${code}
`], { encoding: 'utf8' });

(async () => {
    const count = () => Number(php(`echo Illuminate\\Support\\Facades\\DB::table('wmng_maps')->count();`));
    assert.equal(count(), 0, 'Onboarding browser test requires a disposable database with no maps');
    const fixture = JSON.parse(php(`
        $password = bin2hex(random_bytes(16));
        $user = new App\\Models\\User(['username' => '${name}', 'auth_type' => 'mysql', 'enabled' => true]);
        $user->setPassword($password);
        $user->save();
        $user->assignRole('admin');
        echo json_encode(['id' => $user->user_id, 'username' => $user->username, 'password' => $password]);
    `));
    const deleteFixtureMap = fixtureName => php(`
        foreach (LibreNMS\\Plugins\\WeathermapNG\\Models\\Map::where('name', '${fixtureName}')->get() as $map) {
            $map->links()->delete();
            LibreNMS\\Plugins\\WeathermapNG\\Models\\MapVersion::where('map_id', $map->id)->delete();
            $map->nodes()->delete();
            $map->delete();
        }
    `);
    let browser;
    try {
        browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH });
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const modalAction = async (event, action) => {
            await page.evaluate(event => {
                window.wmngModalEvent = null;
                $('#createMapModal').one(event, () => { window.wmngModalEvent = event; });
            }, event);
            await action();
            await page.waitForFunction(event => window.wmngModalEvent === event, event);
        };
        await page.goto(`${base}/plugin/WeathermapNG`);
        assert.ok(new URL(page.url()).pathname.endsWith('/login'));
        await page.locator('[name=username]').fill(fixture.username);
        await page.locator('[name=password]').fill(fixture.password);
        await Promise.all([
            page.waitForURL(url => !url.pathname.endsWith('/login')),
            page.locator('button[type=submit]').click(),
        ]);
        await page.goto(`${base}/plugin/WeathermapNG`);
        await page.getByText('No maps yet').waitFor();
        await page.getByRole('link', { name: 'Open diagnostics' }).count().then(n => assert.equal(n, 1));
        await page.getByRole('link', { name: 'Read installation and recovery documentation' }).count().then(n => assert.equal(n, 1));

        await page.route('**/plugin/WeathermapNG/templates', route => route.fulfill({ status: 503, body: '{}' }));
        await modalAction('shown.bs.modal', () => page.getByRole('button', { name: 'Browse map templates' }).click());
        await page.locator('#templatesPane.active').waitFor();
        await page.locator('#templatesError').waitFor({ state: 'visible' });
        assert.equal(count(), 0);
        await page.unroute('**/plugin/WeathermapNG/templates');
        await page.locator('#templatesError a').click();
        await page.locator('#templatesGrid [data-template-id]').first().waitFor();
        assert.equal(await page.evaluate(() => {
            const dialog = document.getElementById('createMapDialog').getBoundingClientRect();
            return [...document.querySelectorAll('#templatesGrid > div')].every(card => {
                const bounds = card.getBoundingClientRect();
                return bounds.left >= dialog.left && bounds.right <= dialog.right;
            });
        }), true, 'Template cards must fit inside the modal');
        assert.ok(new URL(page.url()).pathname.endsWith('/WeathermapNG'));
        await modalAction('hidden.bs.modal', () => page.locator('#createMapModal .modal-header .close').click());
        assert.equal(count(), 0);

        await modalAction('shown.bs.modal', () => page.getByRole('button', { name: 'Create your first map' }).click());
        await page.locator('#customPane.active').waitFor();
        await page.locator('#map-name').fill('');
        const customPost = response => new URL(response.url()).pathname.endsWith('/plugin/WeathermapNG/map') && response.request().method() === 'POST';
        const invalidResponse = page.waitForResponse(customPost);
        await page.locator('#createMapSubmitBtn').click();
        const invalid = await invalidResponse;
        assert.equal(invalid.status(), 422);
        assert.match(invalid.headers()['content-type'], /application\/json/);
        const validation = await invalid.json();
        assert.ok(validation.errors?.name?.includes('Map name is required'));
        await page.locator('.wmng-toast-error').filter({ hasText: 'Map name is required' }).waitFor({ state: 'visible' });
        await page.locator('#createMapSubmitBtn:not([disabled])').waitFor();
        assert.equal(await page.locator('#createMapModal').isVisible(), true);
        assert.equal(count(), 0);

        await page.locator('#map-name').fill(customName);
        await page.locator('#map-width').fill('960');
        await page.locator('#map-height').fill('640');
        const customResponse = page.waitForResponse(customPost);
        await page.locator('#createMapSubmitBtn').click();
        const custom = await customResponse;
        assert.equal(custom.status(), 200);
        assert.match(custom.headers()['content-type'], /application\/json/);
        assert.equal(custom.request().headers()['accept'], 'application/json');
        assert.equal(custom.request().headers()['x-requested-with'], 'XMLHttpRequest');
        const customData = await custom.json();
        assert.equal(customData.success, true);
        assert.equal(customData.map.name, customName);
        const customId = Number(customData.map.id);
        assert.ok(Number.isSafeInteger(customId) && customId > 0);
        await page.waitForURL(url => url.pathname.endsWith(`/editor/${customId}`));
        await page.waitForFunction(() => window.WMNG?.EditorState?.mapDataLoaded);
        assert.deepEqual(await page.evaluate(() => ({
            name: document.getElementById('map-name').value,
            width: WMNG.EditorState.mapWidth,
            height: WMNG.EditorState.mapHeight,
        })), { name: customName, width: 960, height: 640 });
        assert.equal(count(), 1);
        await page.goto(`${base}/plugin/WeathermapNG`);
        deleteFixtureMap(customName);
        assert.equal(count(), 0);
        await page.reload();
        await page.getByText('No maps yet').waitFor();

        await modalAction('shown.bs.modal', () => page.getByRole('button', { name: 'Create your first map' }).click());
        await page.locator('#customPane.active').waitFor();
        await modalAction('hidden.bs.modal', () => page.getByRole('button', { name: 'Cancel' }).first().click());
        assert.equal(count(), 0);

        page.once('dialog', dialog => dialog.dismiss());
        await page.getByRole('button', { name: 'Create a sample map from the Star Topology template' }).click();
        await page.locator('#createSampleMapBtn:not([disabled])').waitFor();
        assert.equal(count(), 0);

        page.once('dialog', dialog => dialog.accept(name));
        let releasePost;
        const postBarrier = new Promise(resolve => { releasePost = resolve; });
        await page.route('**/templates/*/create-map', async route => {
            await postBarrier;
            await route.continue();
        });
        const postRequest = page.waitForRequest(request => request.url().endsWith('/create-map') && request.method() === 'POST');
        const created = page.waitForResponse(response => response.url().includes('/templates/') && response.url().endsWith('/create-map') && response.request().method() === 'POST');
        try {
            const clicked = page.getByRole('button', { name: 'Create a sample map from the Star Topology template' }).click();
            await postRequest;
            assert.equal(await page.locator('#createSampleMapBtn').isDisabled(), true);
            assert.equal(count(), 0);
            releasePost();
            await clicked;
        } finally {
            releasePost();
        }
        const response = await created;
        assert.equal(response.status(), 201);
        await page.waitForURL(/\/editor\/\d+$/);
        const mapId = Number(new URL(page.url()).pathname.match(/\/editor\/(\d+)$/)[1]);
        await page.waitForFunction(() => window.WMNG?.EditorState?.mapDataLoaded);
        const graph = await (await page.request.get(`${base}/plugin/WeathermapNG/api/maps/${mapId}/json`)).json();
        assert.equal(graph.name, name);
        assert.equal(graph.nodes.length, 4);
        assert.equal(graph.links.length, 3);
        assert.equal(count(), 1);
        await page.goto(`${base}/plugin/WeathermapNG`);
        assert.equal(await page.getByText('No maps yet').count(), 0);
        assert.deepEqual(errors, []);
        console.log('PASS: empty onboarding, validation recovery, custom JSON creation, modal tabs, cancel safety, named sample, editor graph, and welcome removal');
    } finally {
        await browser?.close();
        deleteFixtureMap(customName);
        deleteFixtureMap(name);
        php(`
            App\\Models\\User::findOrFail(${fixture.id})->delete();
        `);
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
