// Run only against a disposable, empty LibreNMS database.
const assert = require('node:assert/strict');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');

const base = process.argv[2] || 'http://localhost:8000';
const tag = randomBytes(6).toString('hex');
const name = `wmng-onboarding-${tag}`;
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
    let browser;
    try {
        browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH });
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
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

        await page.getByRole('button', { name: 'Browse map templates' }).click();
        await page.locator('#templatesPane.active').waitFor();
        await page.locator('#templatesGrid [data-template-id]').first().waitFor();
        assert.ok(new URL(page.url()).pathname.endsWith('/WeathermapNG'));
        await page.keyboard.press('Escape');
        await page.locator('#createMapModal').waitFor({ state: 'hidden' });
        assert.equal(count(), 0);

        await page.getByRole('button', { name: 'Create your first map' }).click();
        await page.locator('#customPane.active').waitFor();
        await page.getByRole('button', { name: 'Cancel' }).first().click();
        await page.locator('#createMapModal').waitFor({ state: 'hidden' });
        assert.equal(count(), 0);

        page.once('dialog', dialog => dialog.dismiss());
        await page.getByRole('button', { name: 'Create a sample map from the Star Topology template' }).click();
        await page.locator('#createSampleMapBtn:not([disabled])').waitFor();
        assert.equal(count(), 0);

        page.once('dialog', dialog => dialog.accept(name));
        const created = page.waitForResponse(response => response.url().includes('/templates/') && response.url().endsWith('/create-map') && response.request().method() === 'POST');
        await page.getByRole('button', { name: 'Create a sample map from the Star Topology template' }).click();
        const response = await created;
        assert.equal(response.status(), 201);
        const body = await response.json();
        const mapId = body.map.id;
        await page.waitForURL(url => url.pathname.endsWith(`/editor/${mapId}`));
        await page.waitForFunction(() => window.WMNG?.EditorState?.mapDataLoaded);
        const graph = await (await page.request.get(`${base}/plugin/WeathermapNG/api/maps/${mapId}/json`)).json();
        assert.equal(graph.name, name);
        assert.equal(graph.nodes.length, 4);
        assert.equal(graph.links.length, 3);
        assert.equal(count(), 1);
        await page.goto(`${base}/plugin/WeathermapNG`);
        assert.equal(await page.getByText('No maps yet').count(), 0);
        assert.deepEqual(errors, []);
        console.log('PASS: empty onboarding, modal tabs, cancel safety, named sample, editor graph, and welcome removal');
    } finally {
        await browser?.close();
        php(`
            $map = LibreNMS\\Plugins\\WeathermapNG\\Models\\Map::where('name', '${name}')->first();
            if ($map) {
                $map->links()->delete();
                LibreNMS\\Plugins\\WeathermapNG\\Models\\MapVersion::where('map_id', $map->id)->delete();
                $map->nodes()->delete();
                $map->delete();
            }
            App\\Models\\User::findOrFail(${fixture.id})->delete();
        `);
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
