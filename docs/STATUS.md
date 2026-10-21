# Project status

Status as delivered. "Tested" means covered by the automated test suite (`tests/Feature`: 70 tests, 468 assertions, passing on SQLite **and** MariaDB 10.11), by the browser end-to-end script `tools/qa/admin_e2e.py` (22 checks in Chromium), and/or by the pixel comparison `tools/qa/lvcmp.py`.

## Completed and tested

### Frontend
| Feature | How it was verified |
|---|---|
| All 13 pages converted to dynamic Blade, design identical | Pixel diff vs original HTML: 13 pages × 1440/768/390 px = 39 comparisons at 0.000 %; repeated in production mode on MariaDB (1440/390) |
| Original URLs preserved as clean URLs (`/about`); old `*.html` URLs 301-redirect | Feature test |
| Header, dropdowns and footer menus from the database, with active states as in the original | Feature test plus pixel diff |
| Enquiry form (contact and admissions) and job-application form (with CV/photo upload) | Feature tests (validation, storage, honeypot, rate limit, private CV download, e-mail notification with a fake mailer) |
| Forms submit with AJAX, with a fallback to normal POST; server-side errors shown at the fields | Feature tests for the server side |
| Custom 404 page in the site design (noindex), plus minimal 500/503/419/429/403 pages | Feature test (404) |
| SEO in `<head>`: title, description, robots, canonical, Open Graph, Twitter cards | Feature tests |
| JSON-LD: Preschool/Organisation (from Settings, no ratings or reviews), WebSite, WebPage, BreadcrumbList; FAQPage only when switched on | Feature tests |
| `sitemap.xml` (only live, indexable pages) and `robots.txt` | Feature tests |
| Security headers and CSP with nonce | Feature tests |

### Admin panel
| Feature | How it was verified |
|---|---|
| Login, logout, rate limiting, inactive accounts, password reset by e-mail, profile and password change | Feature tests |
| Roles (Super Administrator, Administrator, Content Editor), editable permission matrix, policies | Feature tests |
| Dashboard: page counts, drafts, pending changes, media count and size, SEO scores, recent pages, images and activity, quick actions | Feature test plus screenshot review |
| Pages: create, edit, duplicate, preview (unsaved and saved), draft, publish, unpublish, schedule, trash, restore, permanent delete | Feature tests plus E2E |
| Drafts of published pages: the live page stays unchanged until published; drafts can be discarded | Feature tests plus E2E |
| Section builder: add from the design library, edit fields, reorder (drag and arrows), hide/show, duplicate, delete, repeaters, anchors | Feature tests plus E2E |
| Field validation and sanitising (XSS, `javascript:` links, path traversal in image references) | Feature tests |
| URL change → automatic 301 redirect, menu links follow automatically | Feature tests plus E2E |
| Per-page SEO fields, live length counters, Google-style preview, SEO checklist | Feature tests plus E2E |
| Media upload: multiple files, preview before upload, validation of type, real content, size and dimensions, SVG sanitising | Feature tests plus E2E |
| Automatic optimisation: main file with EXIF stripped, WebP variants 320–1920 (never upscaled), thumbnail, size report, private original | Feature tests |
| Image editor: crop with presets, custom size, rotate 90°, live preview, save, save as copy, restore original | Feature tests plus E2E (output verified visually) |
| Replace file keeping all placements; rename with SEO file names; alt, title, caption; folders; search and filters | Feature tests plus E2E |
| Reference checks: an image in use cannot be deleted; deleting removes all files | Feature tests plus E2E |
| `srcset`/`sizes` for library images; layout size kept | Feature tests plus E2E |
| Menus: header with one level of dropdowns, three footer columns, page or custom links, new tab, visibility, highlight style, drag and drop | Feature tests plus E2E |
| Settings and global content: logo, footer logo, favicon, header button, footer texts, copyright, announcement bar, contact details, form options, social links, SEO defaults, robots rules | Feature tests plus E2E |
| Redirect manager (301/302/307/308, hit counter, loop and admin protection) | Feature tests |
| Enquiries inbox, attachment download, CSV export (formula-injection safe), delete | Feature tests |
| User management (no self-delete, the last Super Administrator is protected, only Super Administrators can assign that role) | Feature tests |
| Audit log with filters | Feature tests |
| Admin responsive layout (sidebar collapses on mobile) | Screenshot review at 390 px |
| `cms:create-admin` and `cms:import-images` commands | Feature test (import) and manual run (create-admin) |

## Not built / needs your decision
* **Blog.** The approved website has no blog design; the footer "Blog" link is a placeholder (`#blog`). As the brief requires, no public blog design was added. Once you approve a design, a blog module can be added: posts, categories, tags, author, Article schema, pagination and related posts.
* **Placeholder content from the approved HTML that still needs real details:**
  * the principal's name (About page)
  * the careers e-mail
  * centre names and job positions (Settings → Contact)
  * the privacy policy link
  * social media links
  * footer anchors `#photo-gallery`, `#newsletter`, `#social`, `#blog`
  * the postal address (for structured data)
* **Multiple languages:** not implemented. The site is English-only; Settings has a content-locale option only.

## Known limitations
* **Dependencies / lock file:**
  * During development, Packagist was unreachable from the build environment. The PHP dependencies were installed from the packages' official GitHub release tags, at the same versions Composer would resolve: Laravel 12.69.3, Intervention Image 3.11.10, PHPUnit 11.5.57 and their dependencies.
  * `composer.json` is standard. Run `composer install` on a machine with internet access to create the `composer.lock`, then commit it.
  * `php artisan test` needs the optional `nunomaduro/collision` package, which is not included. Use `composer test` or `vendor/bin/phpunit`.
* **AVIF:**
  * AVIF uploads are accepted.
  * Responsive variants are generated as WebP, which every current browser supports.
  * AVIF files are only re-encoded if the server's GD library has AVIF support; otherwise they are published unchanged.
* **Image editor:**
  * Rotation is in 90° steps.
  * After rotating, the image sits at the left of the editing area (cosmetic only; the crop result is correct).
* **Text editor:** lightweight, by design, to protect the approved typography: bold, italic, link, line break, bullet list, paragraph.
* **Image processing timing:** runs during the upload (no queue worker needed), about 1–3 s per large photo.
* **Untested against live services:**
  * E-mail sending was tested with Laravel's fake mailer. Configure SMTP and send a test enquiry on the live server.
  * Browser testing was done in Chromium. Please also check the admin panel in Safari and Firefox before launch.
