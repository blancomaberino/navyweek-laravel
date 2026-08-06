import { chromium } from 'playwright';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';
import fs from 'node:fs';
const b = await chromium.launch();
const shot = async (base, out) => {
  const ctx = await b.newContext({ viewport:{width:375,height:812} });
  const p = await ctx.newPage();
  const response = await p.goto(base+'/schedule/',{waitUntil:'networkidle',timeout:60000});
  if (response === null) throw new Error(`no response for ${base}/schedule/`);
  if (!response.ok()) throw new Error(`HTTP ${response.status()} for ${base}/schedule/`);
  await p.addStyleTag({content:'*,*::before,*::after{transition:none!important;animation:none!important}'});
  await p.evaluate(()=>{ const c=document.querySelector('#nw-mobile-toggle'); if(c){c.checked=true;c.dispatchEvent(new Event('change',{bubbles:true}));} });
  await p.waitForTimeout(400);
  // open the Events accordion
  await p.evaluate(()=>{ const d=[...document.querySelectorAll('details')].find(x=>/Events/i.test(x.textContent||'')); if(d) d.open=true; });
  await p.evaluate(()=>{ for(const e of document.body.querySelectorAll('div')){const c=getComputedStyle(e);
    if(c.position==='fixed' && Number(c.zIndex)>2000000000) e.style.display='none'; } });
  await p.waitForTimeout(500);
  const el = await p.$('nav[aria-label="Mobile navigation"]');
  const box = await el.boundingBox();
  if(!box || box.height<10) { await ctx.close(); return null; }
  await p.screenshot({ path: out, clip:{x:box.x,y:Math.max(0,box.y),width:box.width,height:Math.min(box.height,700)} });
  await ctx.close(); return box;
};
const L = await shot('http://127.0.0.1:8000','crops/mob.local.png');
const R = await shot('https://www.navyweek.org','crops/mob.remote.png');
await b.close();
console.log('local ', L && {w:Math.round(L.width),h:Math.round(L.height)});
console.log('remote', R && {w:Math.round(R.width),h:Math.round(R.height)});
// A panel that never opened is the very failure this script exists to catch —
// skipping the compare and exiting 0 would make "never ran" look like "passed".
if (!L || !R) {
  console.error('FAIL — mobile nav panel not captured', { local: !!L, remote: !!R });
  process.exit(1);
}
{
  const a=PNG.sync.read(fs.readFileSync('crops/mob.local.png'));
  const c=PNG.sync.read(fs.readFileSync('crops/mob.remote.png'));
  if(a.width===c.width&&a.height===c.height){
    const d=new PNG({width:a.width,height:a.height});
    const n=pixelmatch(a.data,c.data,d.data,a.width,a.height,{threshold:0.12});
    fs.writeFileSync('crops/mob.diff.png',PNG.sync.write(d));
    const pct=(n/(a.width*a.height))*100;
    console.log('differing px',n,pct.toFixed(2)+'%');
    if (pct > 1) { console.error('FAIL — open mobile menu exceeds the 1% gate'); process.exit(1); }
  } else {
    console.error('FAIL — panels are different dimensions', a.width+'x'+a.height, c.width+'x'+c.height);
    process.exit(1);
  }
}
