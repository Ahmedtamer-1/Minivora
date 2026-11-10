#!/usr/bin/env node
/* Render a built Minivora deck (build.py output) to other formats with Playwright's Chromium.

   node export.mjs deck.html --pdf out/deck.pdf          one slide per page, finished state, vector-free but pixel exact
   node export.mjs deck.html --png out/slides/           slide-01.png … at 2x
   node export.mjs deck.html --video out/deck.webm       the live animations, auto-advancing (--hold 3.5 seconds per slide)
   node export.mjs deck.html --layers out/layers/        background + one transparent PNG per animated element, and
                                                         manifest.json, for build_pptx.py (animated PowerPoint)
   Options: --theme light|dark (default light), --scale 2

   Playwright is resolved from the global install the environment provides; set PLAYWRIGHT_CHROMIUM
   to a Chromium binary if your setup needs it. */
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const require = createRequire(import.meta.url);
let pw;
try { pw = require('playwright'); } catch { pw = require(path.join(process.env.NODE_PATH || '/usr/lib/node_modules', 'playwright')); }
const { chromium } = pw;

const args = process.argv.slice(2);
const input = args.find((a) => !a.startsWith('--') && /\.html?$/i.test(a));
const opt = (k, d) => { const i = args.indexOf(`--${k}`); return i >= 0 ? args[i + 1] : d; };
if (!input) { console.error('usage: node export.mjs deck.html --pdf out.pdf | --png dir | --video out.webm | --layers dir'); process.exit(1); }
const theme = opt('theme', 'light');
const scale = parseFloat(opt('scale', '2'));
const url = pathToFileURL(path.resolve(input)).href;
const W = 1600, H = 900;

const launch = () => chromium.launch(process.env.PLAYWRIGHT_CHROMIUM ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM } : {});

async function openDeck(browser, { record, statics = true } = {}) {
    const ctx = await browser.newContext({
        viewport: { width: W, height: H },
        deviceScaleFactor: record ? 1 : scale,
        colorScheme: theme === 'dark' ? 'dark' : 'light',
        reducedMotion: 'no-preference',
        ...(record ? { recordVideo: { dir: record, size: { width: W, height: H } } } : {}),
    });
    await ctx.addInitScript((t) => {
        try { localStorage.setItem('mv-theme', t); } catch (e) {}
    }, theme);
    const page = await ctx.newPage();
    await page.goto(url + '?mode=deck', { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    // Finished state for stills: no motion, everything shown (see html.mv-static in minivora.css).
    if (statics) await page.evaluate(() => document.documentElement.classList.add('mv-static'));
    await page.waitForFunction(() => typeof window.mvGo === 'function');
    // Hide the hover nav and the progress bar from captures.
    await page.addStyleTag({ content: '.mv-nav,.mv-progress{display:none!important}' });
    return { ctx, page, count: await page.evaluate(() => window.mvCount) };
}

async function show(page, i) {
    await page.evaluate((n) => window.mvGo(n), i);
    await page.waitForTimeout(120);
}

async function png(dir, type = 'png') {
    fs.mkdirSync(dir, { recursive: true });
    const browser = await launch();
    const { page, count } = await openDeck(browser);
    const files = [];
    for (let i = 0; i < count; i++) {
        await show(page, i);
        const f = path.join(dir, `slide-${String(i + 1).padStart(2, '0')}.${type === 'jpeg' ? 'jpg' : 'png'}`);
        await page.screenshot(type === 'jpeg' ? { path: f, type, quality: 90 } : { path: f });
        files.push(f);
    }
    await browser.close();
    return files;
}

async function pdf(out) {
    const tmp = fs.mkdtempSync(path.join(path.dirname(path.resolve(out)), '.mv-pdf-'));
    const files = await png(tmp, 'jpeg'); // full-frame gradients: JPEG keeps the PDF small
    const browser = await launch();
    const page = await browser.newPage();
    const imgs = files.map((f) => `<img src="data:image/jpeg;base64,${fs.readFileSync(f).toString('base64')}">`).join('');
    await page.setContent(`<!doctype html><style>@page{size:${W}px ${H}px;margin:0}html,body{margin:0}img{display:block;width:${W}px;height:${H}px;page-break-after:always}</style>${imgs}`);
    await page.pdf({ path: out, width: `${W}px`, height: `${H}px`, printBackground: true });
    await browser.close();
    fs.rmSync(tmp, { recursive: true, force: true });
    console.log(`wrote ${out} (${files.length} pages)`);
}

async function video(out) {
    const hold = parseFloat(opt('hold', '3.5')) * 1000;
    const dir = fs.mkdtempSync(path.join(path.dirname(path.resolve(out)), '.mv-vid-'));
    const browser = await launch();
    const { ctx, page, count } = await openDeck(browser, { record: dir, statics: false });
    // Replay the first slide's entrance from the start of the recording.
    await page.evaluate(() => { location.hash = '#1'; });
    await page.waitForTimeout(hold);
    for (let i = 1; i < count; i++) {
        await page.keyboard.press('ArrowRight');
        await page.waitForTimeout(hold);
    }
    const v = page.video();
    await ctx.close();
    await browser.close();
    fs.renameSync(await v.path(), out);
    fs.rmSync(dir, { recursive: true, force: true });
    console.log(`wrote ${out} (${count} slides, ${hold / 1000}s each)`);
}

async function layers(dir) {
    fs.mkdirSync(dir, { recursive: true });
    const browser = await launch();
    const { page, count } = await openDeck(browser);
    const manifest = { width: W, height: H, slides: [] };
    for (let i = 0; i < count; i++) {
        await show(page, i);
        // Top-level animated pieces of this slide: [data-anim] not inside another one, plus word-reveal titles.
        const items = await page.evaluate((n) => {
            const slide = document.querySelectorAll('.mv-slide')[n];
            const all = [...slide.querySelectorAll('[data-anim], .mv-words')];
            const top = all.filter((el) => !all.some((o) => o !== el && o.contains(el)));
            const stage = document.querySelector('.mv-stage').getBoundingClientRect();
            return top.map((el, k) => {
                el.dataset.mvLayer = String(k);
                const r = el.getBoundingClientRect();
                const cs = getComputedStyle(el);
                return {
                    k,
                    anim: el.classList.contains('mv-words') ? 'words' : el.dataset.anim || 'rise',
                    x: r.left - stage.left, y: r.top - stage.top, w: r.width, h: r.height,
                    radius: parseFloat(cs.borderTopLeftRadius) || 0,
                    text: (el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 600),
                };
            });
        }, i);
        const notes = await page.evaluate((n) => document.querySelectorAll('.mv-slide')[n].querySelector('.mv-notes')?.textContent || '', i);
        const title = await page.evaluate((n) => document.querySelectorAll('.mv-slide')[n].getAttribute('aria-label') || '', i);
        const sid = String(i + 1).padStart(2, '0');

        // 1) Background: everything except the animated pieces.
        await page.addStyleTag({ content: `.mv-slide.is-active [data-mv-layer]{visibility:hidden!important}` }).then((h) => h.evaluate((s) => s.setAttribute('id', 'mv-hide-layers')));
        const bg = path.join(dir, `s${sid}-bg.jpg`);
        await page.screenshot({ path: bg, type: 'jpeg', quality: 90 });
        await page.evaluate(() => document.getElementById('mv-hide-layers')?.remove());

        // 2) Each piece alone on a transparent page, padded so soft shadows are kept.
        const out = [];
        for (const it of items) {
            await page.evaluate((k) => {
                const st = document.createElement('style');
                st.id = 'mv-solo';
                st.textContent = `html,body{background:transparent!important}.mv-backdrop,.mv-chrome{visibility:hidden!important}
                    .mv-slide.is-active *{visibility:hidden}
                    .mv-slide.is-active [data-mv-layer="${k}"], .mv-slide.is-active [data-mv-layer="${k}"] *{visibility:visible}
                    .mv-hero::before,.mv-hero::after{visibility:hidden!important}`;
                document.head.appendChild(st);
            }, it.k);
            const pad = 40;
            const clip = {
                x: Math.max(0, Math.floor(it.x - pad)), y: Math.max(0, Math.floor(it.y - pad)),
            };
            clip.width = Math.min(W - clip.x, Math.ceil(it.w + pad * 2));
            clip.height = Math.min(H - clip.y, Math.ceil(it.h + pad * 2));
            const f = path.join(dir, `s${sid}-l${String(it.k).padStart(2, '0')}.png`);
            await page.screenshot({ path: f, clip, omitBackground: true });
            await page.evaluate(() => document.getElementById('mv-solo')?.remove());
            out.push({ png: path.basename(f), x: clip.x, y: clip.y, w: clip.width, h: clip.height, anim: it.anim, text: it.text });
        }
        await page.evaluate(() => document.querySelectorAll('[data-mv-layer]').forEach((e) => e.removeAttribute('data-mv-layer')));
        manifest.slides.push({ title, bg: path.basename(bg), layers: out, notes });
        process.stdout.write(`slide ${i + 1}/${count}: ${out.length} layers\n`);
    }
    fs.writeFileSync(path.join(dir, 'manifest.json'), JSON.stringify(manifest, null, 2));
    await browser.close();
    console.log(`wrote ${path.join(dir, 'manifest.json')}`);
}

(async () => {
    if (opt('png')) { const f = await png(opt('png')); console.log(`wrote ${f.length} PNGs to ${opt('png')}`); }
    if (opt('pdf')) await pdf(opt('pdf'));
    if (opt('video')) await video(opt('video'));
    if (opt('layers')) await layers(opt('layers'));
})().catch((e) => { console.error(e); process.exit(1); });
