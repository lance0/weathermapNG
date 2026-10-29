// Disposable LibreNMS stack; preserve all existing maps and delete only this fixture.
const assert = require('node:assert/strict');
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const base = process.argv[2] || 'http://localhost:8000';
const prefix = `wmng-preview-${randomBytes(6).toString('hex')}`;
const php = code => execFileSync('docker', ['exec', '-u', 'librenms', 'librenms-dev', 'php', '-r', `
    require '/opt/librenms/vendor/autoload.php';
    $app = require '/opt/librenms/bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    ${code}
`], { encoding: 'utf8' });
(async () => {
    const fixture = JSON.parse(php(`
        echo json_encode(Illuminate\\Support\\Facades\\DB::transaction(function () {
            $password = bin2hex(random_bytes(16));
            $user = new App\\Models\\User(['username'=>'${prefix}', 'auth_type'=>'mysql', 'enabled'=>true]);
            $user->setPassword($password); $user->save(); $user->assignRole('admin');
            $maps = [];
            foreach (['a-chain','b-star','c-empty'] as $shape) {
                $map = LibreNMS\\Plugins\\WeathermapNG\\Models\\Map::create(['name'=>'${prefix}-'.$shape, 'title'=>'Preview '.$shape]);
                $maps[] = ['id'=>$map->id, 'name'=>$map->name];
                if ($shape === 'c-empty') continue;
                $ids = [];
                for ($i=0; $i<110; $i++) {
                    $x = $shape === 'a-chain' ? $i*10 : ($i ? 300+200*cos($i*2*pi()/109) : 300);
                    $y = $shape === 'a-chain' ? 100 : ($i ? 300+200*sin($i*2*pi()/109) : 300);
                    $ids[] = Illuminate\\Support\\Facades\\DB::table('wmng_nodes')->insertGetId(['map_id'=>$map->id, 'label'=>'Preview node', 'x'=>$x, 'y'=>$y]);
                }
                for ($i=0; $i<160; $i++) {
                    Illuminate\\Support\\Facades\\DB::table('wmng_links')->insert(['map_id'=>$map->id, 'src_node_id'=>$ids[$shape === 'a-chain' ? $i%99 : 0], 'dst_node_id'=>$ids[1+$i%99]]);
                }
            }
            return ['user'=>$user->user_id, 'username'=>$user->username, 'password'=>$password, 'maps'=>$maps];
        }));
    `));
    let browser;
    try {
        browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH });
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(`${base}/plugin/WeathermapNG`);
        await page.locator('[name=username]').fill(fixture.username);
        await page.locator('[name=password]').fill(fixture.password);
        await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('button[type=submit]').click()]);
        await page.goto(`${base}/plugin/WeathermapNG`);
        const cards = fixture.maps.map(map => page.locator(`.map-card-col[data-name="${map.name}"]`));
        for (const card of cards.slice(0,2)) {
            assert.equal(await card.locator('svg circle').count(), 100, 'per-map MariaDB node cap');
            assert.equal(await card.locator('svg line').count(), 150, 'per-map MariaDB link cap');
            assert.equal(await card.getAttribute('data-nodes'), '110');
            assert.equal(await card.getAttribute('data-links'), '160');
            assert.equal(await card.locator('.topology-preview-note').innerText(), 'Overview');
        }
        const geometry = async card => card.locator('svg circle').evaluateAll(nodes => nodes.map(node => [node.getAttribute('cx'),node.getAttribute('cy')]));
        assert.notDeepEqual(await geometry(cards[0]), await geometry(cards[1]));
        assert.equal(await cards[2].locator('.topology-preview-note').innerText(), 'No topology');
        await page.getByRole('textbox', { name:'Search maps' }).fill(prefix);
        assert.equal(await page.locator('.map-card-col:visible').count(), 3);
        await page.getByRole('combobox', { name:'Sort maps' }).selectOption('name-desc');
        assert.equal(await page.locator('.map-card-col:visible').first().getAttribute('data-name'), fixture.maps[2].name);
        await page.getByRole('textbox', { name:'Search maps' }).fill(fixture.maps[1].name);
        assert.equal(await page.locator('.map-card-col:visible').count(), 1);

        await page.evaluate(() => {
            window.previewModalShown = false;
            $('#createMapModal').one('shown.bs.modal', () => { window.previewModalShown = true; });
        });
        await page.getByRole('button', { name:'Create new map', exact:true }).click();
        await page.waitForFunction(() => window.previewModalShown);
        const star = page.getByRole('button', { name:'Use template Star Topology', exact:true });
        const small = page.getByRole('button', { name:'Use template Small Network', exact:true });
        await star.locator('svg').waitFor();
        assert.equal(await star.locator('circle').count(), 4);
        assert.equal(await small.locator('circle').count(), 2);
        assert.equal(await star.locator('line').count(), 3);
        assert.equal(await small.locator('line').count(), 1);
        assert.equal(await star.evaluate(element => element.closest('.wmng-index') !== null), true, 'modal inherits themed root');
        const wasDark = await page.locator('.wmng-index').evaluate(root => root.classList.contains('dark-theme'));
        await page.locator('.wmng-index').evaluate(root => root.classList.add('dark-theme'));
        await page.waitForFunction(() => getComputedStyle(document.querySelector('.template-card')).backgroundColor === 'rgb(44, 49, 54)');
        await page.locator('.wmng-index').evaluate((root, dark) => root.classList.toggle('dark-theme', dark), wasDark);
        const cancelled = new Promise(resolve => page.once('dialog', async dialog => { await dialog.dismiss(); resolve(); }));
        await star.focus();
        await page.keyboard.press('Enter');
        await cancelled;
        await page.keyboard.press('Escape');
        await page.locator('#createMapModal').waitFor({ state:'hidden' });
        await page.getByRole('textbox', { name:'Search maps' }).fill(fixture.maps[0].name);
        await cards[0].getByRole('link', { name:`Edit map ${fixture.maps[0].name}`, exact:true }).focus();
        await Promise.all([page.waitForURL(new RegExp(`/editor/${fixture.maps[0].id}$`)), page.keyboard.press('Enter')]);
        await page.waitForFunction(() => window.WMNG?.EditorState?.mapDataLoaded);
        assert.deepEqual(errors, []);
        console.log('PASS: distinct bounded map/template geometry, empty state, theme, keyboard actions, search and sort');
    } finally {
        await browser?.close();
        php(`
            foreach (${JSON.stringify(fixture.maps.map(map => map.id))} as $id) {
                Illuminate\\Support\\Facades\\DB::table('wmng_links')->where('map_id',$id)->delete();
                Illuminate\\Support\\Facades\\DB::table('wmng_map_versions')->where('map_id',$id)->delete();
                Illuminate\\Support\\Facades\\DB::table('wmng_nodes')->where('map_id',$id)->delete();
                Illuminate\\Support\\Facades\\DB::table('wmng_maps')->where('id',$id)->delete();
            }
            App\\Models\\User::findOrFail(${fixture.user})->delete();
        `);
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
