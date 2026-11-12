// Documentation link and anchor check. Runs with the other pure Node suites in
// CI (tests/*-test.cjs) so renamed headings and moved files fail before a
// release note points at a dead link.
//
// Scope: root markdown + docs/*.md. External URLs are not fetched; only
// repository-relative files and in-page anchors are resolved.
const assert = require('node:assert/strict');
const { existsSync, readFileSync, readdirSync, statSync } = require('node:fs');
const { dirname, join, normalize, relative, resolve } = require('node:path');

const ROOT = resolve(__dirname, '..');
const files = [
    ...readdirSync(ROOT)
        .filter(name => name.endsWith('.md'))
        .map(name => join(ROOT, name)),
    ...readdirSync(join(ROOT, 'docs'))
        .filter(name => name.endsWith('.md'))
        .map(name => join(ROOT, 'docs', name)),
].sort();

// GitHub-style heading slug: lowercase, drop punctuation, spaces to hyphens.
const slug = text => text
    .trim()
    .toLowerCase()
    .replace(/<[^>]+>/g, '')
    .replace(/[`*~]/g, '')
    .replace(/[^\p{L}\p{N}\s-]/gu, '')
    .replace(/ /g, '-');

const anchorsFor = source => {
    const anchors = new Set();
    const counts = new Map();
    const add = value => {
        const base = slug(value);
        if (!base) return;
        const seen = counts.get(base) || 0;
        counts.set(base, seen + 1);
        anchors.add(seen === 0 ? base : `${base}-${seen}`);
    };
    let fence = false;
    for (const line of source.split('\n')) {
        if (/^\s*```/.test(line)) { fence = !fence; continue; }
        if (fence) continue;
        const heading = line.match(/^(#{1,6})\s+(.+?)\s*#*\s*$/);
        if (heading) add(heading[2]);
        for (const explicit of line.matchAll(/<a\s+(?:name|id)="([^"]+)"/g)) anchors.add(explicit[1]);
    }
    return anchors;
};

const stripCode = source => source.replace(/```[\s\S]*?```/g, '');

const failures = [];
let checked = 0;

for (const file of files) {
    const source = readFileSync(file, 'utf8');
    const text = stripCode(source);
    const rel = relative(ROOT, file);

    const unfenced = stripCode(source);
    const h1s = unfenced.match(/^#\s+\S/gm) || [];
    if (h1s.length !== 1) failures.push(`${rel}: expected one top-level H1, found ${h1s.length}`);
    const fences = source.split('\n').filter(line => /^\s*```/.test(line)).length;
    if (fences % 2 !== 0) failures.push(`${rel}: unbalanced code fences (${fences})`);
    if (/^\t/m.test(unfenced)) failures.push(`${rel}: contains tab indentation`);

    const anchors = anchorsFor(source);
    for (const match of text.matchAll(/!?\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g)) {
        const target = match[1];
        if (/^(https?:|mailto:|data:)/i.test(target)) continue;
        checked++;

        const [pathPart, anchorPart] = target.split('#');
        if (pathPart === '') {
            if (anchorPart && !anchors.has(anchorPart)) {
                failures.push(`${rel}: missing anchor #${anchorPart}`);
            }
            continue;
        }

        const resolved = normalize(join(dirname(file), decodeURIComponent(pathPart)));
        if (!existsSync(resolved)) {
            failures.push(`${rel}: missing file ${pathPart}`);
            continue;
        }
        if (anchorPart && statSync(resolved).isFile() && resolved.endsWith('.md')) {
            const targetAnchors = anchorsFor(readFileSync(resolved, 'utf8'));
            if (!targetAnchors.has(anchorPart)) {
                failures.push(`${rel}: ${pathPart} has no anchor #${anchorPart}`);
            }
        }
    }
}

for (const failure of failures) console.error(`FAIL ${failure}`);
assert.equal(failures.length, 0, `${failures.length} documentation link problem(s)`);
console.log(`PASS: ${checked} relative links across ${files.length} documentation files resolve`);
