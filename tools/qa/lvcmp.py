# lvcmp.py: screenshot static vs Laravel pages and diff them
import sys, os
from playwright.sync_api import sync_playwright
from PIL import Image, ImageChops
PAGES = sys.argv[1].split(',') if len(sys.argv) > 1 and sys.argv[1] != 'all' else ['index','about','programs','learning','learning-approach','campus-life','life-at-tots','facilities','parents','parents-corner','admissions','careers','contact']
WIDTHS = [int(w) for w in (sys.argv[2].split(',') if len(sys.argv) > 2 else ['1440','768','390'])]
OUT = '/tmp/lvcmp'; os.makedirs(OUT, exist_ok=True)
LAR = os.environ.get('LAR', 'http://127.0.0.1:8000')   # Laravel app; original HTML served on :8765

def shot(page, url, path):
    page.goto(url); page.wait_for_timeout(400)
    page.evaluate("document.querySelectorAll('img[loading=lazy]').forEach(i=>{i.loading='eager';i.decoding='sync'})")
    h = page.evaluate("document.documentElement.scrollHeight")
    for y in range(0, h, 700):
        page.evaluate(f"window.scrollTo(0,{y})"); page.wait_for_timeout(60)
    page.evaluate("window.scrollTo(0,0)")
    page.wait_for_function("[...document.images].every(i=>i.complete)", timeout=20000); page.wait_for_timeout(400)
    page.screenshot(path=path, full_page=True)

with sync_playwright() as p:
    b = p.chromium.launch()
    for w in WIDTHS:
        ctx = b.new_context(viewport={'width': w, 'height': 900}, reduced_motion='reduce')
        pg = ctx.new_page(); errs = []
        pg.on('console', lambda m: errs.append(m.text) if m.type == 'error' else None)
        pg.on('requestfailed', lambda r: errs.append('FAIL ' + r.url))
        for name in PAGES:
            a = f'{OUT}/{name}-{w}-static.png'; c = f'{OUT}/{name}-{w}-laravel.png'
            shot(pg, f'http://localhost:8765/{name}.html', a)
            errs.clear()
            shot(pg, f'{LAR}/' + ('' if name == 'index' else name), c)
            A, C = Image.open(a).convert('RGB'), Image.open(c).convert('RGB')
            same_size = A.size == C.size
            hh = min(A.height, C.height)
            d = ImageChops.difference(A.crop((0, 0, w, hh)), C.crop((0, 0, w, hh))).convert('L').point(lambda v: 255 if v > 24 else 0)
            frac = sum(d.histogram()[255:]) / (w * hh)
            bbox = d.getbbox()
            print(f'{name:18} {w:5} static={A.size[1]:6} laravel={C.size[1]:6} diff={frac*100:6.3f}% bbox={bbox} errs={errs[:3]}')
            if frac > 0:
                d.save(f'{OUT}/{name}-{w}-diff.png')
        ctx.close()
    b.close()
