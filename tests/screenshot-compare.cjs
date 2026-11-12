// Pixel comparison of the index, editor and embed views against the PNG
// baselines in tests/screenshots/. Runs against the disposable LibreNMS stack
// and removes only the fixture rows/user it created.
//
// Usage: node tests/screenshot-compare.cjs [URL] [--update]
//   URL       base of the running LibreNMS (default http://localhost:8000)
//   --update  write the baselines instead of comparing (UPDATE_SCREENSHOTS=1
//             is the CI/environment spelling of the same switch)
//
// Capture rules: outside the map canvases, a capture may differ in at most
// 0.5% of pixels and only by a small channel delta. Inside the canvases,
// demo/live traffic drifts slightly; that budget is 1%. A separate content
// check fails a blank map canvas, because a blank render can be < 1% pixels.
const { randomBytes } = require('node:crypto');
const { execFileSync } = require('node:child_process');
const { existsSync, mkdirSync, readFileSync, unlinkSync, writeFileSync } = require('node:fs');
const { tmpdir } = require('node:os');
const { join, resolve } = require('node:path');
const { deflateSync, inflateSync } = require('node:zlib');

const args = process.argv.slice(2);
const base = (args.find(arg => !arg.startsWith('--')) || 'http://localhost:8000').replace(/\/+$/, '');
const update = args.includes('--update') || process.env.UPDATE_SCREENSHOTS === '1';
const baselineDir = join(__dirname, 'screenshots');
const diffDir = join(resolve(__dirname, '..'), 'output', 'screenshot-diff');
const VIEWPORTS = [[480, 800], [768, 1024], [1920, 1080]];
// Fraction of bytes that may differ per channel before a pixel counts.
const CHANNEL_TOLERANCE = 4;
// Fraction of the whole capture that may differ without failing.
const MAX_DIFF_RATIO = 0.005;
// Canvas pixels still get a budget: animated traffic/text drifts a little, but
// a broken map render must fail. Observed drift is ~0.4%; a blank embed canvas
// measures 4-7%, and a blank editor canvas is caught by the content check.
const MAX_CANVAS_DIFF_RATIO = 0.01;

// A random map name would be rendered on the index card and in the editor
// header, so every capture would differ from its baseline. Randomize only the
// disposable username.
const tag = `wmng-visual-${randomBytes(6).toString('hex')}`;
const fixtureName = 'wmng-visual-fixture';
const php = code => execFileSync('docker', ['exec', '-u', 'librenms', 'librenms-dev', 'php', '-r', `
    require '/opt/librenms/vendor/autoload.php';
    $app = require '/opt/librenms/bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    ${code}
`], { encoding: 'utf8' });

// ---------------------------------------------------------------- PNG codec
const PNG_SIGNATURE = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);

const crcTable = (() => {
    const table = new Int32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        table[n] = c;
    }
    return table;
})();

const crc32 = buffer => {
    let crc = -1;
    for (const byte of buffer) crc = crcTable[(crc ^ byte) & 0xff] ^ (crc >>> 8);
    return (crc ^ -1) >>> 0;
};

const pngChunk = (type, data) => {
    const length = Buffer.alloc(4);
    length.writeUInt32BE(data.length);
    const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(body));
    return Buffer.concat([length, body, crc]);
};

const paeth = (left, up, upLeft) => {
    const estimate = left + up - upLeft;
    const fromLeft = Math.abs(estimate - left);
    const fromUp = Math.abs(estimate - up);
    const fromUpLeft = Math.abs(estimate - upLeft);
    if (fromLeft <= fromUp && fromLeft <= fromUpLeft) return left;
    return fromUp <= fromUpLeft ? up : upLeft;
};

// Decodes non-interlaced 8-bit RGB/RGBA PNGs (what Chromium writes) to RGBA.
function decodePng(path) {
    const file = readFileSync(path);
    if (!file.subarray(0, 8).equals(PNG_SIGNATURE)) throw new Error(`${path} is not a PNG`);
    let offset = 8;
    let header = null;
    const idat = [];
    while (offset + 8 <= file.length) {
        const length = file.readUInt32BE(offset);
        const type = file.toString('ascii', offset + 4, offset + 8);
        const data = file.subarray(offset + 8, offset + 8 + length);
        offset += 12 + length;
        if (type === 'IHDR') {
            header = {
                width: data.readUInt32BE(0),
                height: data.readUInt32BE(4),
                bitDepth: data[8],
                colorType: data[9],
                interlace: data[12],
            };
        } else if (type === 'IDAT') {
            idat.push(data);
        } else if (type === 'IEND') {
            break;
        }
    }
    if (!header) throw new Error(`${path} has no IHDR chunk`);
    const channels = { 2: 3, 6: 4 }[header.colorType];
    if (header.bitDepth !== 8 || header.interlace !== 0 || !channels) {
        throw new Error(`${path}: unsupported PNG (bit depth ${header.bitDepth}, colour type ${header.colorType}, interlace ${header.interlace})`);
    }
    const { width, height } = header;
    const stride = width * channels;
    const raw = inflateSync(Buffer.concat(idat));
    if (raw.length < (stride + 1) * height) throw new Error(`${path} is truncated`);
    const pixels = Buffer.alloc(width * height * 4);
    let previous = Buffer.alloc(stride);
    let position = 0;
    for (let y = 0; y < height; y++) {
        const filter = raw[position++];
        const row = raw.subarray(position, position + stride);
        position += stride;
        const line = Buffer.alloc(stride);
        for (let x = 0; x < stride; x++) {
            const left = x >= channels ? line[x - channels] : 0;
            const up = previous[x];
            const upLeft = x >= channels ? previous[x - channels] : 0;
            let value;
            switch (filter) {
                case 0: value = row[x]; break;
                case 1: value = row[x] + left; break;
                case 2: value = row[x] + up; break;
                case 3: value = row[x] + ((left + up) >> 1); break;
                case 4: value = row[x] + paeth(left, up, upLeft); break;
                default: throw new Error(`${path}: unsupported PNG filter ${filter}`);
            }
            line[x] = value & 0xff;
        }
        for (let x = 0; x < width; x++) {
            const source = x * channels;
            const target = (y * width + x) * 4;
            pixels[target] = line[source];
            pixels[target + 1] = line[source + 1];
            pixels[target + 2] = line[source + 2];
            pixels[target + 3] = channels === 4 ? line[source + 3] : 255;
        }
        previous = line;
    }
    return { width, height, pixels };
}

function encodePng({ width, height, pixels }) {
    const stride = width * 4;
    const raw = Buffer.alloc((stride + 1) * height);
    for (let y = 0; y < height; y++) {
        raw[y * (stride + 1)] = 0;
        pixels.copy(raw, y * (stride + 1) + 1, y * stride, (y + 1) * stride);
    }
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(width, 0);
    ihdr.writeUInt32BE(height, 4);
    ihdr[8] = 8;   // bit depth
    ihdr[9] = 6;   // RGBA
    return Buffer.concat([
        PNG_SIGNATURE,
        pngChunk('IHDR', ihdr),
        pngChunk('IDAT', deflateSync(raw)),
        pngChunk('IEND', Buffer.alloc(0)),
    ]);
}

// Marks map pixels inside the canvas rectangles and clears UI regions drawn on
// top of them (nav bar, controls, legend, minimap), so chrome regressions are
// still compared even on the full-bleed embed page.
function canvasMask(rects, holes, width, height) {
    const mask = new Uint8Array(width * height);
    const fillRect = (rect, value) => {
        const top = Math.max(0, Math.floor(rect.y));
        const bottom = Math.min(height, Math.ceil(rect.y + rect.h));
        const left = Math.max(0, Math.floor(rect.x));
        const right = Math.min(width, Math.ceil(rect.x + rect.w));
        for (let y = top; y < bottom; y++) mask.fill(value, y * width + left, y * width + right);
    };
    rects.forEach(rect => fillRect(rect, 1));
    holes.forEach(rect => fillRect(rect, 0));
    return mask;
}

function comparePng(referencePath, capture, mask) {
    const reference = decodePng(referencePath);
    if (reference.width !== capture.width || reference.height !== capture.height) {
        return {
            error: `baseline is ${reference.width}x${reference.height} but the capture is ${capture.width}x${capture.height}`,
        };
    }
    const total = reference.width * reference.height;
    const diff = Buffer.from(reference.pixels);
    let differing = 0;
    let outside = 0;
    let inside = 0;
    let canvasPixels = 0;
    for (let index = 0; index < total; index++) {
        const offset = index * 4;
        const delta = Math.max(
            Math.abs(reference.pixels[offset] - capture.pixels[offset]),
            Math.abs(reference.pixels[offset + 1] - capture.pixels[offset + 1]),
            Math.abs(reference.pixels[offset + 2] - capture.pixels[offset + 2]),
            Math.abs(reference.pixels[offset + 3] - capture.pixels[offset + 3]),
        );
        if (mask[index]) canvasPixels++;
        if (delta <= CHANNEL_TOLERANCE) continue;
        differing++;
        if (mask[index]) inside++; else outside++;
        diff[offset] = 220;
        diff[offset + 1] = 20;
        diff[offset + 2] = 20;
        diff[offset + 3] = 255;
    }
    return {
        width: reference.width,
        height: reference.height,
        diff,
        differing,
        outside,
        inside,
        ratio: total ? differing / total : 0,
        insideRatio: canvasPixels ? inside / canvasPixels : 0,
    };
}

// ------------------------------------------------------------------ fixture
const fixtureSql = `
    Illuminate\\Support\\Facades\\DB::transaction(function () {
        // The map name is rendered (index card + editor header), so it must be
        // stable across runs; clear a previous fixture if one survived.
        foreach (LibreNMS\\Plugins\\WeathermapNG\\Models\\Map::where('name', '${fixtureName}')->get() as $old) {
            $old->links()->delete();
            LibreNMS\\Plugins\\WeathermapNG\\Models\\MapVersion::where('map_id', $old->id)->delete();
            $old->nodes()->delete();
            $old->delete();
        }
        $password = bin2hex(random_bytes(16));
        $user = new App\\Models\\User(['username'=>'${tag}', 'auth_type'=>'mysql', 'enabled'=>true]);
        $user->setPassword($password); $user->save(); $user->assignRole('admin');
        $map = LibreNMS\\Plugins\\WeathermapNG\\Models\\Map::create([
            'name'=>'${fixtureName}', 'title'=>'Visual regression fixture',
            'options'=>['width'=>960, 'height'=>640, 'background'=>'#ffffff'],
        ]);
        $points = [
            ['Core Router Alpha', 140, 120], ['Core Router Bravo', 620, 120],
            ['Distribution Switch North', 140, 340], ['Distribution Switch South', 620, 340],
            ['Edge Server One', 380, 560],
        ];
        $nodes = [];
        foreach ($points as [$label, $x, $y]) {
            $nodes[] = LibreNMS\\Plugins\\WeathermapNG\\Models\\Node::create([
                'map_id'=>$map->id, 'label'=>$label, 'x'=>$x, 'y'=>$y,
            ]);
        }
        $nodes[0]->update(['meta'=>['color'=>'#aa3300', 'shape'=>'square', 'size'=>18]]);
        $links = [];
        foreach ([[0,1,1000000000],[0,2,1000000000],[1,3,1000000000],[2,4,100000000],[3,4,100000000]] as [$from,$to,$bandwidth]) {
            $links[] = LibreNMS\\Plugins\\WeathermapNG\\Models\\Link::create([
                'map_id'=>$map->id, 'src_node_id'=>$nodes[$from]->id, 'dst_node_id'=>$nodes[$to]->id,
                'bandwidth_bps'=>$bandwidth, 'style'=>['width'=>3],
            ]);
        }
        $webhooks = new class extends LibreNMS\\Plugins\\WeathermapNG\\Services\\WebhookDispatcher {
            public function dispatch(string $event, array $map): void {}
        };
        $service = new LibreNMS\\Plugins\\WeathermapNG\\Services\\MapVersionService($webhooks);
        $service->createVersion($map, 'Visual regression baseline', null, $user->user_id);
        // The index card prints "Updated <relative time>"; pin it to a fixed
        // instant so captures stay byte-identical as the fixture ages.
        $map->timestamps = false;
        $map->forceFill(['created_at'=>'2020-01-01 00:00:00', 'updated_at'=>'2020-01-01 00:00:00'])->save();
        return ['map'=>$map->id, 'user'=>$user->user_id, 'username'=>$user->username, 'password'=>$password];
    })
`;

// --------------------------------------------------------------- page flow
async function logIn(page) {
    await page.goto(`${base}/plugin/WeathermapNG/editor/${fixture.map}`, { waitUntil: 'domcontentloaded' });
    if (!new URL(page.url()).pathname.endsWith('/login')) return;
    await page.locator('[name=username]').fill(fixture.username);
    await page.locator('[name=password]').fill(fixture.password);
    await Promise.all([
        page.waitForURL(url => !url.pathname.endsWith('/login')),
        page.locator('button[type=submit]').click(),
    ]);
}

const pageUrl = name => {
    const plugin = `${base}/plugin/WeathermapNG`;
    if (name === 'index') return plugin;
    if (name === 'editor') return `${plugin}/editor/${fixture.map}`;
    return `${plugin}/embed/${fixture.map}?metrics=0&graphs=0&sse=0`;
};

async function settle(page, name) {
    if (name === 'index') await page.waitForSelector('.wmng-index');
    if (name === 'editor') {
        await page.waitForFunction(() => window.WMNG?.EditorState?.mapDataLoaded);
        await page.waitForSelector('#map-canvas');
    }
    if (name === 'embed') {
        await page.waitForFunction(() => typeof mapData !== 'undefined' && mapData && !mapData.error);
        await page.waitForSelector('#map-canvas');
    }
    // Live/polling traffic can keep the network busy; treat idle as best effort.
    await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
    await page.evaluate(() => document.fonts.ready.then(() => true));
    // One paint after layout/fonts settle.
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
}

const elementRects = (page, selectors) => page.evaluate(selectors => {
    const ratio = window.devicePixelRatio || 1;
    return selectors.flatMap(selector => [...document.querySelectorAll(selector)].map(element => {
        const bounds = element.getBoundingClientRect();
        if (bounds.width <= 0 || bounds.height <= 0) return null;
        return {
            x: Math.max(0, bounds.left) * ratio,
            y: Math.max(0, bounds.top) * ratio,
            w: Math.max(0, Math.min(window.innerWidth, bounds.right) - Math.max(0, bounds.left)) * ratio,
            h: Math.max(0, Math.min(window.innerHeight, bounds.bottom) - Math.max(0, bounds.top)) * ratio,
        };
    }).filter(Boolean));
}, selectors);

const canvasRects = page => page.evaluate(() => {
    const ratio = window.devicePixelRatio || 1;
    const width = window.innerWidth;
    const height = window.innerHeight;
    return [...document.querySelectorAll('canvas')].map(canvas => {
        const bounds = canvas.getBoundingClientRect();
        const left = Math.max(0, bounds.left);
        const top = Math.max(0, bounds.top);
        return {
            x: left * ratio,
            y: top * ratio,
            w: Math.max(0, Math.min(width, bounds.right) - left) * ratio,
            h: Math.max(0, Math.min(height, bounds.bottom) - top) * ratio,
        };
    }).filter(rect => rect.w > 0 && rect.h > 0);
});

// A blank canvas can be only a few hundred differing pixels, so also assert
// that the map layer actually contains content and the fixture's styled node
// (#aa3300 square) was painted. This catches "map renders nothing" regressions
// that a pixel ratio alone would miss.
const assertMapCanvasContent = async (page, name) => {
    const stats = await page.evaluate(() => {
        const canvas = document.getElementById('map-canvas') || document.querySelector('canvas');
        if (!canvas) return null;
        const ctx = canvas.getContext('2d');
        const { width, height } = canvas;
        const data = ctx.getImageData(0, 0, width, height).data;
        const colors = new Set();
        let nonBg = 0;
        let styled = 0;
        const bg = [data[0], data[1], data[2], data[3]];
        for (let i = 0; i < data.length; i += 4 * 3) {
            const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
            if (a > 0) colors.add(`${r},${g},${b}`);
            if (a > 0 && (Math.abs(r - bg[0]) > 8 || Math.abs(g - bg[1]) > 8 || Math.abs(b - bg[2]) > 8)) nonBg++;
            if (a > 0 && Math.abs(r - 0xaa) <= 10 && Math.abs(g - 0x33) <= 10 && b <= 10) styled++;
        }
        return { width, height, colors: colors.size, nonBg, styled };
    });
    if (!stats || stats.width < 10 || stats.height < 10) {
        throw new Error(`${name}: map canvas is missing or has no drawing surface`);
    }
    if (stats.colors < 3 || stats.nonBg < 20) {
        throw new Error(`${name}: map canvas looks blank (${JSON.stringify(stats)})`);
    }
    if (stats.styled < 30) {
        throw new Error(`${name}: per-node style meta.color was not painted (${JSON.stringify(stats)})`);
    }
};

// -------------------------------------------------------------------- main
let fixture;
let browser;

async function main() {
    // Required lazily so the PNG codec and diff rules stay importable without
    // the browser package installed.
    const { chromium } = require('playwright-core');
    try {
        fixture = JSON.parse(php(`echo json_encode(${fixtureSql}, JSON_THROW_ON_ERROR);`));
        browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH });
        const context = await browser.newContext({
            viewport: { width: 1920, height: 1080 },
            deviceScaleFactor: 1,
            reducedMotion: 'reduce',
        });
        const page = await context.newPage();
        const pageErrors = [];
        page.on('pageerror', error => pageErrors.push(error.message));
        await logIn(page);
        mkdirSync(baselineDir, { recursive: true });

        const failures = [];
        let written = 0;
        for (const name of ['index', 'editor', 'embed']) {
            for (const [width, height] of VIEWPORTS) {
                const viewport = `${width}x${height}`;
                const baseline = join(baselineDir, `${name}-${viewport}.png`);
                pageErrors.length = 0;
                try {
                    await page.setViewportSize({ width, height });
                    const response = await page.goto(pageUrl(name), { waitUntil: 'domcontentloaded' });
                    if (response && response.status() >= 400) {
                        throw new Error(`HTTP ${response.status()} for ${response.url()}`);
                    }
                    await settle(page, name);
                    if (name === 'editor' || name === 'embed') {
                        await assertMapCanvasContent(page, name);
                    }
                    const holes = await elementRects(page, ['#nav-bar', '#controls', '#legend', '#minimap', '#zoom-controls']);
                    const mask = canvasMask(await canvasRects(page), holes, width, height);
                    const shot = join(tmpdir(), `${tag}-${name}-${viewport}.png`);
                    await page.screenshot({ path: shot, animations: 'disabled', caret: 'hide' });
                    let capture;
                    try {
                        capture = decodePng(shot);
                    } finally {
                        unlinkSync(shot);
                    }
                    if (pageErrors.length) {
                        failures.push(`${name}-${viewport}: page errors: ${pageErrors.join('; ')}`);
                        continue;
                    }
                    if (update) {
                        writeFileSync(baseline, encodePng(capture));
                        written++;
                        console.log(`UPDATED ${name}-${viewport} -> ${baseline}`);
                        continue;
                    }
                    if (!existsSync(baseline)) {
                        failures.push(`${name}-${viewport}: missing baseline ${baseline} (run \`just visual-baseline ${base}\`)`);
                        continue;
                    }
                    const result = comparePng(baseline, capture, mask);
                    if (result.error) {
                        failures.push(`${name}-${viewport}: ${result.error}`);
                        continue;
                    }
                    const percent = (result.ratio * 100).toFixed(2);
                    const canvasPercent = (result.insideRatio * 100).toFixed(2);
                    const confined = result.differing > 0 && result.outside === 0;
                    if (result.insideRatio > MAX_CANVAS_DIFF_RATIO) {
                        mkdirSync(diffDir, { recursive: true });
                        const diffPath = join(diffDir, `${name}-${viewport}.png`);
                        writeFileSync(diffPath, encodePng({ width: result.width, height: result.height, pixels: result.diff }));
                        failures.push(`${name}-${viewport}: ${canvasPercent}% of background canvas pixels differ (> ${(MAX_CANVAS_DIFF_RATIO * 100).toFixed(0)}%) — map render changed; diff written to ${diffPath}`);
                        continue;
                    }
                    if (result.ratio > MAX_DIFF_RATIO && !confined) {
                        mkdirSync(diffDir, { recursive: true });
                        const diffPath = join(diffDir, `${name}-${viewport}.png`);
                        writeFileSync(diffPath, encodePng({ width: result.width, height: result.height, pixels: result.diff }));
                        failures.push(`${name}-${viewport}: ${percent}% of pixels differ (${result.differing}, ${result.outside} outside canvas) — diff written to ${diffPath}`);
                        continue;
                    }
                    const note = result.differing === 0
                        ? 'identical'
                        : `${percent}% differ (${result.differing}, ${canvasPercent}% of canvas pixels), ${confined ? 'all inside the animated canvas region' : `${result.outside} outside canvas`}`;
                    console.log(`ok ${name}-${viewport}: ${note}`);
                } catch (error) {
                    failures.push(`${name}-${viewport}: ${error.message}`);
                }
            }
        }

        if (update) {
            for (const failure of failures) console.warn(`SKIPPED ${failure}`);
            console.log(`PASS: wrote ${written} baselines to ${baselineDir} — review the PNGs, then commit them`);
            if (failures.length) process.exitCode = 1;
            return;
        }
        if (failures.length) {
            for (const failure of failures) console.error(`FAIL ${failure}`);
            process.exitCode = 1;
            return;
        }
        console.log(`PASS: ${VIEWPORTS.length * 3} captures match ${baselineDir} (tolerance ${(MAX_DIFF_RATIO * 100).toFixed(1)}%, canvas-only drift ignored)`);
    } finally {
        try {
            await browser?.close();
        } finally {
            if (fixture) {
                php(`
                    Illuminate\\Support\\Facades\\DB::transaction(function () {
                        foreach (['wmng_links','wmng_nodes','wmng_map_versions'] as $table) {
                            Illuminate\\Support\\Facades\\DB::table($table)->where('map_id',${fixture.map})->delete();
                        }
                        Illuminate\\Support\\Facades\\DB::table('wmng_maps')->where('id',${fixture.map})->delete();
                        App\\Models\\User::findOrFail(${fixture.user})->delete();
                    });
                `);
            }
        }
    }
}

if (require.main === module) {
    main().catch(error => { console.error(error); process.exitCode = 1; });
}

// Exported so the PNG codec and diff rules can be exercised without a stack.
module.exports = { decodePng, encodePng, comparePng, canvasMask };
