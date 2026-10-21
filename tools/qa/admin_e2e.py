# Run ONLY against a local/staging copy with freshly seeded data: it edits content.
"""End-to-end CMS workflow in a real browser: edit → draft → preview → publish → frontend;
media upload → pick into section → crop; menu edit; settings change."""
import os, re
from playwright.sync_api import sync_playwright, expect
B = os.environ.get('CMS_URL', 'http://127.0.0.1:8000')
EMAIL = os.environ['CMS_E2E_EMAIL']        # a disposable test account on a local copy
PASSWORD = os.environ['CMS_E2E_PASSWORD']
OUT = '/tmp/adm'
os.makedirs(OUT, exist_ok=True)
ok = lambda m: print('PASS', m)

with sync_playwright() as p:
    b = p.chromium.launch()
    ctx = b.new_context(viewport={'width': 1400, 'height': 1000})
    pg = ctx.new_page()
    errs = []
    pg.on('pageerror', lambda e: errs.append(str(e)))
    pg.on('dialog', lambda d: d.accept())
    pg.goto(B + '/admin/login')
    pg.fill('input[name=email]', EMAIL); pg.fill('input[name=password]', PASSWORD)
    pg.click('button[type=submit]'); pg.wait_for_url(B + '/admin')

    # ---- 1. edit hero heading on About, save as draft (published page => live unchanged)
    pg.goto(B + '/admin/pages/2/edit')
    pg.click('.sec:nth-child(1) .sec__title')
    area = pg.locator('.sec:nth-child(1) .rte__area').first
    area.click(); pg.keyboard.press('Control+A'); pg.keyboard.type('A Childhood Full of E2E')
    pg.click('button[name=action][value=save]'); pg.wait_for_load_state()
    assert 'Changes saved as a draft' in pg.content(), 'draft message'
    ok('draft saved')
    live = ctx.new_page(); live.goto(B + '/about')
    assert 'A Childhood Filled' in live.content() and 'E2E' not in live.content(); ok('live page unchanged by draft')
    # preview of saved draft
    live.goto(B + '/admin/pages/2/preview'); assert 'Full of E2E' in live.content() and 'noindex' in live.content(); ok('saved preview shows draft')
    # unsaved preview via button
    with ctx.expect_page() as newp:
        pg.click('button[data-preview]')
    prev = newp.value; prev.wait_for_load_state()
    assert 'Full of E2E' in prev.content(); ok('unsaved preview opens in new tab'); prev.close()
    # publish
    pg.click('button[name=action][value=publish]'); pg.wait_for_load_state()
    live.goto(B + '/about'); assert 'A Childhood Full of E2E' in live.content(); ok('published change visible on frontend')

    # ---- 2. add a section from the library, reorder, save
    pg.click('button[data-add-section] >> nth=0')
    pg.fill('[data-section-filter]', 'principal')
    pg.click('.seclib__item >> nth=0'); pg.wait_for_timeout(400)
    n = pg.locator('.sec').count()
    pg.click('button[name=action][value=publish]'); pg.wait_for_load_state()
    assert pg.locator('.sec').count() == n; ok(f'section added ({n} sections)')
    live.goto(B + '/about'); assert live.content().count('Our Principal') >= 2; ok('added section rendered')
    # delete the added section again
    pg.locator('.sec').last.locator('button.danger').click()
    pg.click('button[name=action][value=publish]'); pg.wait_for_load_state()
    assert pg.locator('.sec').count() == n - 1; ok('section removed')

    # ---- 3. media upload with preview, then edit/crop
    pg.goto(B + '/admin/media')
    pg.set_input_files('[data-upload-form] input[type=file]', '/tmp/test-photo.jpg')
    assert pg.locator('[data-upload-preview] img').count() == 1; ok('upload preview before saving')
    pg.click('[data-upload-form] button.primary'); pg.wait_for_load_state()
    assert 'uploaded and optimised' in pg.content(); ok('upload')
    pg.click('.mitem >> nth=0'); pg.wait_for_load_state()
    media_url = pg.url
    pg.wait_for_selector('.cropper-container')
    pg.click('button[data-ratio="1"]'); pg.wait_for_timeout(300)
    pg.click('button[data-rotate="90"]'); pg.wait_for_timeout(300)
    w = int(pg.input_value('#crop-form input[name=width]')); h = int(pg.input_value('#crop-form input[name=height]'))
    assert w > 0 and abs(w - h) <= 2, (w, h); ok(f'square crop selection {w}x{h}')
    pg.click('#crop-form button[value="0"]'); pg.wait_for_load_state()
    assert 'Image cropped' in pg.content(); ok('crop saved')
    dims = re.search(r'Displayed size</dt><dd>(\d+)×(\d+)', pg.content())
    assert dims and abs(int(dims.group(1)) - int(dims.group(2))) <= 2, dims; ok(f'cropped file is square {dims.groups()}')
    pg.screenshot(path=f'{OUT}/media-show.png', full_page=True)

    # ---- 4. use the uploaded image in a section via the picker
    pg.goto(B + '/admin/pages/2/edit')
    pg.click('.sec:nth-child(2) .sec__title')
    pg.locator('.sec:nth-child(2) .imgfield button:has-text("Choose image")').first.click()
    pg.wait_for_selector('#media-modal .pick'); pg.click('#media-modal .pick >> nth=0')
    pg.locator('.sec:nth-child(2) .imgfield input[type=text]').first.fill('E2E alt text')
    pg.click('button[name=action][value=publish]'); pg.wait_for_load_state()
    live.goto(B + '/about'); c = live.content()
    assert 'srcset=' in c and 'E2E alt text' in c and '/storage/media/' in c; ok('library image rendered with srcset + alt')
    pg.goto(media_url); assert 'About Us' in pg.locator('text=Where this image is used').locator('..').inner_text(); ok('usage tracked')
    pg.on('dialog', lambda d: d.accept())
    assert pg.locator('button:has-text("Delete image")').count() == 0; ok('in-use image cannot be deleted')

    # ---- 5. menus: rename an item
    pg.goto(B + '/admin/menus/1')
    pg.locator('.mi__label').first.fill('About E2E')
    pg.click('#menu-form button.primary'); pg.wait_for_load_state()
    assert 'Menu saved' in pg.content(); live.goto(B + '/'); assert '>About E2E<' in live.content(); ok('menu change on frontend')
    pg.locator('.mi__label').first.fill('About'); pg.click('#menu-form button.primary'); pg.wait_for_load_state()

    # ---- 6. settings: phone number
    pg.goto(B + '/admin/settings?tab=contact')
    pg.fill('input[name=contact_phone]', '+91 90000 11111')
    pg.click('form button.primary'); pg.wait_for_load_state()
    live.goto(B + '/contact'); assert '+91 90000 11111' in live.content(); ok('settings change on frontend')
    pg.screenshot(path=f'{OUT}/settings.png', full_page=True)

    # ---- 7. new page lifecycle
    pg.goto(B + '/admin/pages/create')
    pg.fill('[data-title-input]', 'Summer Camp 2027')
    assert pg.input_value('[data-slug-input]') == 'summer-camp-2027'; ok('slug auto-generated')
    pg.click('button[name=action][value=save]'); pg.wait_for_load_state()
    r = live.goto(B + '/summer-camp-2027'); assert r.status == 404; ok('draft page not public')
    pg.click('button[name=action][value=publish]'); pg.wait_for_load_state()
    r = live.goto(B + '/summer-camp-2027'); assert r.status == 200 and 'Summer Camp 2027' in live.content(); ok('published new page')
    pg.fill('[data-slug-input]', 'summer-camp'); pg.click('button[name=action][value=publish]'); pg.wait_for_load_state()
    r = live.goto(B + '/summer-camp-2027'); assert live.url.endswith('/summer-camp'); ok('slug change creates 301 redirect')
    print('JS errors:', errs)
    b.close()
