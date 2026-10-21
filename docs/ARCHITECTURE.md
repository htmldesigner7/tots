# Architecture

## How the approved HTML became Laravel

1. **Audit.** The site has 13 pages:
   * the home page, built from "stage" sections with absolute positioning
   * 12 inner pages, built from shared flow components (`hero2`, `fl`, `hx`, `copy`, `split`, `cards`, `feats2`, `faq`, …)

   It uses one shared header and footer, `style.css`, `css/inner.css`, `css/flow.css` and `script.js`. The `css/pages/*.css` files were not referenced by any page and were not carried over.
2. **Conversion.** `tools/html-to-sections/convert.py` turns every top-level block inside `<main>` into a **section type**:
   * a Blade partial: `resources/views/sections/<type>.blade.php`
   * a field schema: `resources/cms/sections/<type>.json`
   * the page's content: `database/seeders/data/pages.json`, plus `resources/cms/defaults/<type>.json` for new sections

   Markup, classes, `data-anim` attributes, decorative images and SVGs stay literal, so the output is identical to the original HTML. What becomes editable:
   * text
   * content images (with alt text)
   * links and buttons
   * lists
   * repeating cards
3. **Hand-built pieces:**
   * the programme tabs (`sections/programme-tabs.blade.php`, linked tab/panel IDs)
   * the FAQ accordion list (`sections/partials/faq-list.blade.php`)
   * the enquiry and careers forms (`site/forms/*.blade.php`)
   * the header, footer, breadcrumb and 404 page
4. **Verification.** `tools/qa/lvcmp.py` screenshots each original page and its Laravel version at several widths and diffs the pixels. All 13 pages match exactly (0.000 % difference) at 1440, 768 and 390 px, including in production mode on MariaDB.

There are 81 section types: 10 home-page designs and 71 inner-page designs. The page editor offers all of them in **+ Add section**, grouped by the page they come from. This is the requested "page builder with design-matched sections": hero, text + image, cards and features, galleries, steps, FAQ, CTA, contact details and forms. No new visual designs were invented.

## Code map

| Area | Location |
|---|---|
| Public routes | `routes/web.php` (bottom): `/{slug}`, `sitemap.xml`, `robots.txt`, `forms/*`, fallback (redirects/404) |
| Admin routes | `routes/web.php` (top), prefix from `CMS_ADMIN_PATH` |
| Section system | `app/Cms/SectionRegistry.php` (types), `SectionView.php` (`$s` helper used in section Blade files), `SectionData.php` (validation and sanitising), `PageRenderer.php` (rendering and per-section cache), `MediaMap.php` (preloads images, no N+1 queries) |
| Business logic | `app/Services/PageService.php` (drafts, publishing, duplication, trash, slug redirects), `MediaService.php` (upload/optimise/crop/replace/rename/delete), `MediaUsageSync.php`, `SvgSanitizer.php` |
| Support | `app/Support/Settings.php`, `Menus.php`, `Seo.php` (meta, JSON-LD, checklist), `Redirects.php`, `HtmlSanitizer.php`, `SiteUrl.php`, `PublicCache.php`, `AuditLogger.php` |
| Controllers | `app/Http/Controllers/Site/*` (public), `app/Http/Controllers/Admin/*` |
| Validation | `app/Http/Requests/*` (forms, pages), plus inline rules in the smaller controllers |
| Authorisation | Gates per permission (`AppServiceProvider`), and `app/Policies/{Page,Media,User}Policy.php` |
| Middleware | `SecurityHeaders` (CSP with nonce, nosniff, frame, referrer, permissions, HSTS), `AdminArea` (noindex, no-store, deactivated-user logout) |
| Views | `resources/views/site/*` (layout, header, footer, forms), `sections/*`, `admin/*`, `errors/*` |
| Admin front-end | `resources/js/admin.js`, `resources/css/admin.css` (built by Vite into `public/build`) |
| Commands | `cms:create-admin`, `cms:import-images` |
| Tests | `tests/Feature/*` (70 tests) |

## Adding a new section design later
1. Write the HTML using the existing CSS classes.
2. Create `resources/views/sections/my-section.blade.php`, using `$s->html('key')`, `$s->img('key')`, `$s->linkAttrs('key')`, `@foreach($s->items('cards') as $it)` …
3. Create `resources/cms/sections/my-section.json` with `key`, `label`, `origin`, `css` (`home` or `inner`) and `fields`.
   * Field types: `inline`, `richtext`, `text`, `url`, `link`, `list`, `image`, `boolean`, `repeater`.
4. Optionally add default content in `resources/cms/defaults/my-section.json`.

The editor, validation, rendering and SEO checklist pick it up automatically.

## Security summary
* Laravel session authentication with bcrypt password hashing. Passwords need at least 12 characters with mixed case and a number.
* Login is rate-limited (5 per minute per e-mail+IP and 20 per minute per IP). Admin and form endpoints are throttled.
* CSRF protection on every form.
* Role-based access control: Gates and Policies on every admin action. The UI only shows what the role allows, and the server enforces it.
* Every section field is allow-list sanitised.
  * Inline and rich HTML keep only safe tags, and links are limited to http(s), mailto, tel, `/` and `#`.
  * Blade escapes all other output.
  * JSON-LD is encoded with `JSON_HEX_TAG`.
* All database access goes through Eloquent and the query builder, so SQL is parameterised.
* Uploads:
  * The extension, the real MIME type (finfo) and the decoded image dimensions are checked, with pixel-count limits against decompression bombs.
  * SVGs are sanitised: no scripts, event handlers, foreignObject or external references.
  * Files are stored under random-suffixed names, never executed, and originals and CVs are kept on a private disk.
* Security headers on every response: Content-Security-Policy with a per-request nonce, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, and HSTS on HTTPS.
* Admin, login and preview pages send `X-Robots-Tag: noindex` and `Cache-Control: no-store`. robots.txt disallows the admin area.
* Audit log of sign-ins, failed sign-ins and all content, media, menu, settings, redirect and user changes.
* Secrets stay only in `.env`. Debug output is off in production. CSV exports neutralise spreadsheet formulas.
* The forms use a honeypot field plus rate limits against spam.

## Performance
* The public pages use the original static CSS/JS; no Vite bundle or framework JavaScript is added to the public site.
* Rendered section HTML is cached. A version counter in `PublicCache` invalidates the cache on any content change.
* Menus, settings and redirects are cached. Page lookups are a single indexed query.
* Library images are served with WebP `srcset`/`sizes`. Every image has explicit width/height, so there is no layout shift. Below-the-fold images keep `loading="lazy"`, and the hero image keeps `fetchpriority="high"`.
* Static assets get `?v=` cache-busting based on file modification time.
