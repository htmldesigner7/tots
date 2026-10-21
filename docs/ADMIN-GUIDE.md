# Ananya Tots CMS – Administrator guide

Sign in at **https://your-domain/admin** (or the address your developer set). After 5 wrong attempts, sign-in pauses for a minute.

## Roles

| Role | Can do |
|---|---|
| **Super Administrator** | Everything, including users, roles and the permission matrix |
| **Administrator** | Pages (including publish, delete and SEO), media, menus, settings, redirects, enquiries, audit log. Cannot manage users. |
| **Content Editor** | Edit page content and upload media; view enquiries. Saves drafts that an administrator publishes. |

A Super Administrator can fine-tune these permissions in **Roles & permissions**.

## Everyday workflow: editing a page

1. Open **Pages** and click **Edit** next to the page.
2. Change the **Page title** if needed. It appears in the breadcrumb and is the default SEO title.
3. Under **Sections**, click a section to open it. Every section is one of the approved designs from the website, so the layout always stays on-brand.
   * **Text boxes:** use **B** (bold), *I* (italic), 🔗 (link) and ↵ (line break). Pasted text arrives as plain text, so formatting copied from Word cannot break the design.
   * **Lists:** one line per entry.
   * **Buttons:** a label and a link. Pick an internal page from the suggestions, or type `#section`, `/page#section` or `https://…`.
   * **Repeating items** (cards, questions, steps): use ↑ ↓ to reorder, ⧉ to duplicate, ✕ to remove and **+ Add** to add. A new item starts as a copy of the last one; change its text and image.
4. **Images:** click **Choose image** to pick from the media library, or upload a new one in the same window.
   * Always fill in the **Alt text**: a short description of the photo for blind visitors and search engines.
   * The help text shows the shape the design expects (for example *1.20:1*). Click **Crop / edit** to crop the image to that shape.
   * **Use design image** goes back to the original design photo.
5. **Arrange sections:**
   * Reorder sections with the ⠿ handle (drag) or the ↑ ↓ buttons.
   * Untick **Visible** to hide a section without deleting it.
   * ⧉ duplicates a section; ✕ deletes it.
   * **+ Add section** adds a new section from the library of approved designs, grouped by the page they come from.
6. Click **Preview** to see your unsaved changes in a new tab. Search engines never index previews.
7. Save:
   * **Save draft / Save as draft:** stores your work.
     * On a page that is already live, the website keeps showing the published version. A yellow banner says there are *unpublished changes*.
     * You can then **Publish these changes** or **Discard changes**.
   * **Publish / Update & publish** (Administrators): makes the changes live.
     * To schedule a page, set a future **Publish date & time** first. The page goes live automatically at that moment.

**Content Editors** only see *Save draft*. An administrator reviews the draft (it is listed under *Pages → With unpublished changes*) and publishes it.

### Creating a new page
**Pages → + New page.**
1. Enter the title. The URL is created automatically; you can change it.
2. The page starts with a hero section. Add more sections from the library.
3. Save as a draft, preview, then publish.
4. Add the page to a menu (see *Menus*).

### Changing a page address (URL)
Change the **URL** field and publish. With *Redirect the old address* ticked (the default), visitors and Google are sent from the old address to the new one automatically (301 redirect). Menu links that point to the page update automatically.

### Duplicating, unpublishing and deleting
* **Duplicate:** creates a draft copy.
* **Unpublish:** hides the page from the website; the content is kept.
* **Delete:** moves the page to the **Trash**, from which it can be restored or deleted permanently. Menu links to a deleted page are switched off.
* The home page cannot be deleted or unpublished.

## SEO

Each page's right-hand column has **SEO & sharing**:

* **SEO title** (aim for 30–60 characters) and **Meta description** (70–160). Live counters and a Google-style preview help you.
* **Canonical URL:** leave empty (automatic) unless the same content lives at another address.
* **Search engines:** choose *Noindex* for pages that should not appear in Google. Noindex pages are also left out of the sitemap.
* **Social sharing:** a title, description and image for Facebook/WhatsApp/LinkedIn previews. The recommended image size is 1200×630.
* The **SEO checklist** checks:
  * title and description length
  * missing alt text
  * headings
  * how much text the page has
  * duplicate titles and descriptions

  It is guidance only and cannot guarantee rankings.

Other SEO tools:
* **SEO overview:** every page's score at a glance.
* **Redirects:** add your own redirects (e.g. from an old brochure link) and see how often each one is used.
* **Settings → SEO defaults:**
  * the title suffix and default description
  * the default share image
  * the organisation type used in structured data
  * extra robots.txt rules
  * a switch to hide the whole site from search engines, for staging copies only
* The **sitemap** (`/sitemap.xml`) and **robots.txt** update automatically.
* **FAQ sections** have a switch *Output FAQPage structured data*. Only enable it for genuine questions and answers.

## Media library

**Media library → Upload images.**
* You can select several files at a time; they are previewed before uploading.
* Allowed types: JPG, PNG, WebP, AVIF, GIF and SVG.
* Every upload is checked, then automatically:
  * resized into responsive versions (320–1920 px WebP)
  * compressed
  * stripped of camera data
* The **original is always kept**, so edits can be undone.
* Images are never enlarged beyond their real size.

On an image's page you can:
* **Crop & rotate:**
  * choose a ratio (Home hero, Inner hero, Landscape card, Portrait card, Square, Social share or Free) or type an exact width and height
  * rotate in 90° steps
  * then **Save crop**, which updates everywhere the image is used, or **Save as new image**, which keeps the old one
  * **Restore original** removes the crop
* **Details:** set the alt text, title, caption and folder. You can also give the file a descriptive **file name**; renaming is safe because pages refer to the image itself, not its file name.
* **Replace file:** upload a new version. Every page using the image shows the new one.
* **Where this image is used:** an image that is still in use cannot be deleted.
* **Search and filter** the library by name or alt text, file type, folder, upload date or *unused only*. Organise images into **folders**.

## Menus

**Menus** has four menus:
* the header navigation, with dropdowns
* three footer columns, each with its own heading

For each menu you can:
* Drag ⠿ to reorder items. In the header, drag an item under another to place it in that item's dropdown (one level).
* Link each item to a **page**, so the link updates if the page's URL changes. With a page selected you can also add an `#anchor`.
* Or link to a **custom URL**.
* Choose *Same tab* or *New tab*.
* Untick **Visible** to hide an item. Links to unpublished pages are hidden automatically.
* Footer items can use the *Highlighted (arrow)* style used by "Book a Visit".

## Settings & global content

Changes here apply to every page:

| Tab | What you can change |
|---|---|
| **General** | Website name, website URL, time zone, browser theme colour |
| **Branding** | Header logo, footer logo, favicon |
| **Header & footer** | Header button text and link, footer tagline and handwritten note, copyright (`{year}` updates itself), sign-off |
| **Announcement bar** | An optional strip above the header, e.g. "Admissions open" |
| **Contact** | Public email and phone, address (used in Google's structured data), who receives enquiry and job-application e-mails, the centres, programmes and positions offered in the forms, privacy policy link |
| **Social** | Profile links |
| **SEO defaults** | See *SEO* above |

Server passwords and secret keys are deliberately **not** in the admin panel.

## Enquiries and job applications

Website form submissions are stored in **Enquiries**. Unread entries are shown in bold.
* Open an entry to reply by e-mail, call, or download the CV and photograph. Attachments are private and only signed-in staff can download them.
* **Export** creates a CSV file for Excel.
* If an e-mail address is set in *Settings → Contact*, a notification is also e-mailed to it.

## Users, profile and audit log

* **Users** (Super Administrators): add people, choose their role and deactivate accounts.
  * You cannot delete yourself or remove the last Super Administrator.
* **My profile:** change your name, e-mail and password. Your current password is required.
* **Audit log:** a record of sign-ins, failed sign-ins and every change to pages, media, menus, settings, redirects and users, with the time, the person and their IP address.

## Tips
* Keep alt text short and descriptive ("Children painting at an easel"), not "image1".
* Upload photos at least as wide as the place they are shown; very large originals are fine, they are resized automatically.
* Use **Preview** before publishing big changes.
* If something looks out of date on the website, use *Settings → Clear website cache*.
