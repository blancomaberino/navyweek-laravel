// Diff the OPEN Events dropdown — the at-rest page diff can never see it.
import { chromium } from 'playwright';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';
import fs from 'node:fs';

const b = await chromium.launch();
const shot = async (base, out) => {
  const ctx = await b.newContext({ viewport:{width:1280,height:900} });
  const p = await ctx.newPage();
  await p.goto(base+'/schedule/',{waitUntil:'networkidle',timeout:60000}).catch(()=>{});
  await p.addStyleTag({content:'*,*::before,*::after{transition:none!important;animation:none!important}'});
  // Force the dropdown open regardless of hover support in headless.
  await p.addStyleTag({content:'.nw-dropdown .nw-dropdown-menu{visibility:visible!important;opacity:1!important}'});
  await p.waitForTimeout(600);
  const el = await p.$('.nw-dropdown-menu');
  const box = await el.boundingBox();
  await p.screenshot({ path: out, clip: { x: Math.max(0,box.x-8), y: Math.max(0,box.y-8), width: box.width+16, height: box.height+16 } });
  await ctx.close();
  return box;
};
const L = await shot('http://127.0.0.1:8000','crops/menu.local.png');
const R = await shot('https://www.navyweek.org','crops/menu.remote.png');
await b.close();
console.log('local  panel', JSON.stringify({w:Math.round(L.width),h:Math.round(L.height)}));
console.log('remote panel', JSON.stringify({w:Math.round(R.width),h:Math.round(R.height)}));
const a=PNG.sync.read(fs.readFileSync('crops/menu.local.png'));
const c=PNG.sync.read(fs.readFileSync('crops/menu.remote.png'));
if(a.width===c.width && a.height===c.height){
  const d=new PNG({width:a.width,height:a.height});
  const n=pixelmatch(a.data,c.data,d.data,a.width,a.height,{threshold:0.12});
  fs.writeFileSync('crops/menu.diff.png', PNG.sync.write(d));
  console.log('differing px', n, ((n/(a.width*a.height))*100).toFixed(2)+'%');
} else console.log('SIZE MISMATCH — panels are different dimensions');
