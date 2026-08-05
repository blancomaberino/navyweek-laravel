// Visual parity harness: screenshot the SAME path on local + remote at two
// viewports, pixel-diff them, and report the difference ranked worst-first.
//
// Heading-outline comparison is blind to layout, type, spacing and icons — this
// is the metric that actually answers "does it look the same".
import { chromium } from 'playwright';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';
import fs from 'node:fs';
import path from 'node:path';

const LOCAL = 'http://127.0.0.1:8000';
const REMOTE = 'https://www.navyweek.org';
const OUT = path.resolve('shots');
const VIEWPORTS = [
  { name: 'desktop', width: 1280, height: 900 },
  { name: 'mobile', width: 375, height: 812 },
];

const paths = fs.readFileSync(process.argv[2] ?? 'urls.txt', 'utf8')
  .split('\n').map((s) => s.trim()).filter(Boolean);
const only = process.argv[3] ?? null; // optional viewport filter

fs.mkdirSync(OUT, { recursive: true });

/** Screenshot one URL, with the chat widget + anything animated hidden. */
async function shoot(page, url, file, height) {
  await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 }).catch(() => {});
  // Neutralize third-party chrome that isn't part of the port, and freeze motion.
  await page.addStyleTag({
    content: `*,*::before,*::after{transition:none!important;animation:none!important}
      iframe[title*="hat"],div[id*="crisp"],div[class*="crisp"],#hubspot-messages-iframe-container{display:none!important}`,
  }).catch(() => {});
  // The live site injects a third-party chat bubble with no id or class to target
  // — a fixed 50x50 div at the max z-index. Hide anything with that signature so it
  // stops showing up as a difference the port is supposed to fix.
  await page.evaluate(() => {
    for (const el of document.body.querySelectorAll('div')) {
      const cs = getComputedStyle(el);
      if (cs.position === 'fixed' && Number(cs.zIndex) > 2000000000) el.style.display = 'none';
    }
  }).catch(() => {});
  await page.waitForTimeout(400);
  // fullPage, NOT clip: Playwright clamps `clip` to the viewport, so a clipped
  // shot only ever compared the top ~900px and everything below the fold went
  // unmeasured. Pad both shots to a common height so the compare is apples-to-apples.
  await page.screenshot({ path: file, fullPage: true });
}

const browser = await chromium.launch();
const rows = [];

for (const vp of VIEWPORTS) {
  if (only && only !== vp.name) continue;
  const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();

  for (const p of paths) {
    const slug = (p.replace(/\//g, '_') || '_root') + '__' + vp.name;
    const aFile = path.join(OUT, `${slug}.local.png`);
    const bFile = path.join(OUT, `${slug}.remote.png`);
    // Compare a fixed-height window: full-page shots differ in length whenever
    // content differs at all, which drowns out *where* the difference is.
    const H = vp.name === 'desktop' ? 2400 : 3000;
    try {
      await shoot(page, LOCAL + p, aFile, H);
      await shoot(page, REMOTE + p, bFile, H);
      const a = PNG.sync.read(fs.readFileSync(aFile));
      const b = PNG.sync.read(fs.readFileSync(bFile));
      // Full-page shots differ in length whenever content does; compare over the
      // taller of the two so missing/extra content counts as a difference.
      const width = Math.min(a.width, b.width);
      const height = Math.max(a.height, b.height);
      const pad = (img) => {
        if (img.height === height && img.width === width) return img;
        const out = new PNG({ width, height });
        PNG.bitblt(img, out, 0, 0, Math.min(width, img.width), Math.min(height, img.height), 0, 0);
        return out;
      };
      const A = pad(a), B = pad(b);
      const diff = new PNG({ width, height });
      const n = pixelmatch(A.data, B.data, diff.data, width, height, { threshold: 0.12 });
      const pct = (n / (width * height)) * 100;
      if (pct > 0.5) fs.writeFileSync(path.join(OUT, `${slug}.diff.png`), PNG.sync.write(diff));
      rows.push({ path: p, vp: vp.name, pct });
    } catch (e) {
      rows.push({ path: p, vp: vp.name, pct: NaN, err: String(e).slice(0, 80) });
    }
  }
  await ctx.close();
}
await browser.close();

rows.sort((x, y) => (y.pct || 0) - (x.pct || 0));
console.log('worst-first (％ of pixels differing):\n');
for (const r of rows) {
  const flag = r.pct > 15 ? '🔴' : r.pct > 5 ? '🟠' : r.pct > 1 ? '🟡' : '🟢';
  console.log(`${flag} ${(r.pct ?? 0).toFixed(1).padStart(5)}%  ${r.vp.padEnd(7)} ${r.path}${r.err ? '  ERR ' + r.err : ''}`);
}
const bad = rows.filter((r) => r.pct > 1).length;
console.log(`\n${rows.length - bad}/${rows.length} within 1% · diff images in ${OUT}`);
