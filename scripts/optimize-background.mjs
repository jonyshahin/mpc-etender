#!/usr/bin/env node
import sharp from 'sharp';
import { mkdir, stat } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '..');

const SOURCE = resolve(projectRoot, 'storage/app/brand/app-background-original.jpg');
const OUT_DIR = resolve(projectRoot, 'public/images');

const WEBP_VARIANTS = [
    { width: 3840, quality: 48, file: 'app-background-3840.webp', effort: 6 },
    { width: 1920, quality: 75, file: 'app-background-1920.webp' },
    { width: 1280, quality: 78, file: 'app-background-1280.webp' },
];

const JPG_VARIANTS = [
    { width: 3840, quality: 62, file: 'app-background-3840.jpg' },
    { width: 1920, quality: 80, file: 'app-background-1920.jpg' },
    { width: 1280, quality: 82, file: 'app-background-1280.jpg' },
];

// LQIP: tiny blurred placeholder (1-2 KB) for very-slow-network fallback.
const LQIP = { width: 32, quality: 40, file: 'app-background-blur.jpg' };

// Size caps. The fabric's fine noise/texture resists WebP compression — the
// 3840 variant floors out around 700-780 KB at quality 48 (near the quality
// boundary where blocky artifacts appear). Cap raised to 800 KB with that in
// mind; this variant only loads on ≥1920px viewports (i.e., 4K monitors on
// fast connections) and is not the preloaded hero (1920w is, at ~360 KB).
const SIZE_LIMIT_BYTES = {
    '3840.webp': 800 * 1024,
    '1920.webp': 400 * 1024,
    '1280.webp': 400 * 1024,
    '3840.jpg': 900 * 1024,
    '1920.jpg': 500 * 1024,
    '1280.jpg': 400 * 1024,
    'blur.jpg': 4 * 1024,
};

async function build() {
    await mkdir(OUT_DIR, { recursive: true });

    try {
        await stat(SOURCE);
    } catch {
        console.error(`Source not found: ${SOURCE}`);
        console.error('Place the pristine original at storage/app/brand/app-background-original.jpg first.');
        process.exit(1);
    }

    const base = sharp(SOURCE);
    const meta = await base.metadata();
    console.log(`Source: ${meta.width}×${meta.height} ${meta.format}\n`);

    const results = [];

    for (const v of WEBP_VARIANTS) {
        const out = resolve(OUT_DIR, v.file);
        await sharp(SOURCE)
            .resize({ width: v.width, withoutEnlargement: true })
            .webp({ quality: v.quality, effort: v.effort ?? 5 })
            .toFile(out);
        const { size } = await stat(out);
        results.push({ file: v.file, width: v.width, size });
    }

    for (const v of JPG_VARIANTS) {
        const out = resolve(OUT_DIR, v.file);
        await sharp(SOURCE)
            .resize({ width: v.width, withoutEnlargement: true })
            .jpeg({ quality: v.quality, mozjpeg: true })
            .toFile(out);
        const { size } = await stat(out);
        results.push({ file: v.file, width: v.width, size });
    }

    {
        const out = resolve(OUT_DIR, LQIP.file);
        await sharp(SOURCE)
            .resize({ width: LQIP.width })
            .jpeg({ quality: LQIP.quality })
            .toFile(out);
        const { size } = await stat(out);
        results.push({ file: LQIP.file, width: LQIP.width, size });
    }

    const kb = n => `${(n / 1024).toFixed(1)} KB`;
    console.log('Generated:');
    let worstOffender = null;
    for (const r of results) {
        const key = r.file.replace('app-background-', '');
        const limit = SIZE_LIMIT_BYTES[key];
        const ok = limit == null || r.size <= limit;
        if (!ok) worstOffender = worstOffender ?? r.file;
        const badge = ok ? 'ok' : `OVER (cap ${kb(limit)})`;
        console.log(`  ${r.file.padEnd(30)} ${String(r.width).padStart(5)}w  ${kb(r.size).padStart(10)}  ${badge}`);
    }

    if (worstOffender) {
        console.error(`\nAt least one variant exceeded its size cap: ${worstOffender}`);
        console.error('Lower quality by 3-5 points in WEBP_VARIANTS/JPG_VARIANTS and rerun.');
        process.exit(2);
    }
}

build().catch(err => {
    console.error(err);
    process.exit(1);
});
