# Visual parity harness

Proves the platform *looks* like the live site. Heading/DOM checks cannot do this —
`/schedule/` once passed a "2/2 headings match" check while 94% of its pixels
differed.

```bash
cd tools/vdiff
npm i -D playwright pixelmatch pngjs && npx playwright install chromium
node diff.mjs urls.txt            # both viewports
node diff.mjs urls.txt desktop    # one viewport
```

Screenshots the same path on `127.0.0.1:8000` and `www.navyweek.org` at 1280 and
375 wide, pixel-diffs them, and prints a worst-first ranking. Anything over **1%**
is a failing gate; read the emitted `shots/*.diff.png` to see where it differs.

Third-party chat widgets are hidden and transitions frozen before capture, so the
diff reflects the port rather than animation or vendor chrome.
