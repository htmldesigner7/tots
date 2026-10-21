<?php

/*
|--------------------------------------------------------------------------
| Ananya Tots CMS configuration
|--------------------------------------------------------------------------
| Content-related defaults only. Secrets (DB passwords, mail credentials,
| APP_KEY) live in .env and are never editable from the admin panel.
*/

return [

    // URL prefix of the admin panel (change via CMS_ADMIN_PATH to make it less guessable)
    'admin_path' => trim(env('CMS_ADMIN_PATH', 'admin'), '/'),

    // Content-Security-Policy header on public and admin pages
    'csp' => (bool) env('CMS_CSP', true),

    /*
    | Permissions. Keys are checked with Gate abilities of the same name.
    */
    'permissions' => [
        'pages.view' => ['Pages', 'View pages in the admin'],
        'pages.edit' => ['Pages', 'Create and edit pages'],
        'pages.publish' => ['Pages', 'Publish, unpublish and schedule pages'],
        'pages.delete' => ['Pages', 'Delete and restore pages'],
        'seo.edit' => ['SEO', 'Edit SEO fields, redirects and robots.txt'],
        'media.manage' => ['Media', 'Upload, edit and delete media'],
        'menus.manage' => ['Menus', 'Manage header and footer menus'],
        'settings.manage' => ['Settings', 'Change website-wide settings and global content'],
        'submissions.view' => ['Enquiries', 'View form submissions'],
        'submissions.delete' => ['Enquiries', 'Delete form submissions'],
        'users.manage' => ['Users', 'Manage users and roles'],
        'audit.view' => ['Audit log', 'View the audit log'],
    ],

    /*
    | Default role → permission map (seeded; editable by Super Admins except
    | for the super-admin role, which always has every permission).
    */
    'roles' => [
        'super-admin' => ['name' => 'Super Administrator', 'description' => 'Full access, including users, roles and all settings.', 'permissions' => '*'],
        'admin' => ['name' => 'Administrator', 'description' => 'Manages content, media, menus, SEO and settings. Cannot manage users.', 'permissions' => [
            'pages.view', 'pages.edit', 'pages.publish', 'pages.delete', 'seo.edit', 'media.manage',
            'menus.manage', 'settings.manage', 'submissions.view', 'submissions.delete', 'audit.view',
        ]],
        'editor' => ['name' => 'Content Editor', 'description' => 'Edits page content and media. Cannot publish, delete pages or change settings.', 'permissions' => [
            'pages.view', 'pages.edit', 'media.manage', 'submissions.view',
        ]],
    ],

    'page_templates' => [
        'inner' => 'Inner page (standard header, flow sections)',
        'home' => 'Home page (positioned home sections)',
    ],

    'robots_options' => [
        'index, follow' => 'Index, follow (default)',
        'index, follow, max-image-preview:large' => 'Index, follow, large image previews',
        'noindex, follow' => 'Noindex, follow',
        'noindex, nofollow' => 'Noindex, nofollow',
    ],

    'menu_locations' => [
        'header' => 'Header navigation',
        'footer-1' => 'Footer column 1',
        'footer-2' => 'Footer column 2',
        'footer-3' => 'Footer column 3',
    ],

    'menu_styles' => [
        '' => 'Normal',
        'visit' => 'Highlighted (arrow)',
    ],

    /*
    | Media library
    */
    'media' => [
        'max_upload_kb' => (int) env('CMS_MAX_UPLOAD_KB', 10240),
        'max_pixels' => 40_000_000,          // reject decompression bombs
        'max_dimension' => 10000,
        'min_dimension' => 16,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'svg'],
        'allowed_mimes' => [
            'image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif', 'image/svg+xml',
        ],
        'responsive_widths' => [320, 480, 768, 1024, 1440, 1920],
        'main_max_width' => 2560,
        'thumb' => [400, 400],
        'quality' => ['jpeg' => 82, 'webp' => 80, 'avif' => 55, 'png' => 9],
        'keep_originals' => true,
        // Crop presets used by the image editor. Ratios come from the approved design.
        'crop_presets' => [
            ['label' => 'Free', 'ratio' => null],
            ['label' => 'Home hero (16:9)', 'ratio' => 1092 / 615],
            ['label' => 'Inner hero (11:7)', 'ratio' => 872 / 556],
            ['label' => 'Landscape card (4:3)', 'ratio' => 4 / 3],
            ['label' => 'Wide banner (3.4:1)', 'ratio' => 1360 / 397],
            ['label' => 'Portrait card (4:5)', 'ratio' => 312 / 390],
            ['label' => 'Square thumbnail (1:1)', 'ratio' => 1],
            ['label' => 'Social share (1.91:1)', 'ratio' => 1200 / 630],
        ],
    ],

    /*
    | Global settings schema (Admin → Settings). Grouped into tabs.
    | type: text|textarea|email|url|tel|image|boolean|select|timezone
    */
    'settings' => [
        'General' => [
            'site_name' => ['type' => 'text', 'label' => 'Website name', 'default' => 'Ananya Tots', 'rules' => 'required|string|max:120'],
            'site_url' => ['type' => 'url', 'label' => 'Website URL (used for canonical URLs and the sitemap)', 'default' => '', 'rules' => 'nullable|url|max:255', 'help' => 'Leave empty to use APP_URL from the server configuration.'],
            'timezone' => ['type' => 'timezone', 'label' => 'Time zone', 'default' => 'Asia/Kolkata', 'rules' => 'required|timezone'],
            'locale' => ['type' => 'select', 'label' => 'Content language', 'default' => 'en_IN', 'options' => ['en_IN' => 'English (India)', 'en_GB' => 'English (UK)', 'en_US' => 'English (US)'], 'rules' => 'required|in:en_IN,en_GB,en_US'],
            'theme_color' => ['type' => 'text', 'label' => 'Browser theme colour', 'default' => '#673893', 'rules' => 'required|regex:/^#[0-9a-fA-F]{6}$/'],
        ],
        'Branding' => [
            'logo' => ['type' => 'image', 'label' => 'Header logo', 'default' => ['src' => 'assets/img/logo.png', 'media_id' => null, 'alt' => 'Ananya Tots', 'w' => 136, 'h' => 70]],
            'footer_logo' => ['type' => 'image', 'label' => 'Footer logo', 'default' => ['src' => 'assets/img/footer-logo.png', 'media_id' => null, 'alt' => 'Ananya Tots', 'w' => 202, 'h' => 106]],
            'favicon' => ['type' => 'image', 'label' => 'Favicon (square PNG)', 'default' => ['src' => 'assets/img/favicon.png', 'media_id' => null, 'alt' => '']],
        ],
        'Header & footer' => [
            'header_cta_label' => ['type' => 'text', 'label' => 'Header button label', 'default' => 'Book a Visit', 'rules' => 'required|string|max:40'],
            'header_cta_url' => ['type' => 'link', 'label' => 'Header button link', 'default' => '/contact#enquiry', 'rules' => 'required|string|max:255'],
            'footer_tagline' => ['type' => 'textarea', 'label' => 'Footer tagline (one line per row)', 'default' => "Play is meaningful.\nChildhood is precious.", 'rules' => 'nullable|string|max:200'],
            'footer_hand' => ['type' => 'text', 'label' => 'Footer handwritten note', 'default' => 'a little hello can start a lot.', 'rules' => 'nullable|string|max:120'],
            'footer_contact_title' => ['type' => 'text', 'label' => 'Footer contact column title', 'default' => 'Say Hello', 'rules' => 'nullable|string|max:60'],
            'newsletter_label' => ['type' => 'text', 'label' => 'Footer newsletter link label', 'default' => 'Newsletter', 'rules' => 'nullable|string|max:60'],
            'newsletter_url' => ['type' => 'link', 'label' => 'Footer newsletter link', 'default' => '#newsletter', 'rules' => 'nullable|string|max:255'],
            'social_label' => ['type' => 'text', 'label' => 'Footer social link label', 'default' => 'Follow us on social media', 'rules' => 'nullable|string|max:80'],
            'copyright' => ['type' => 'text', 'label' => 'Copyright text ({year} is replaced automatically)', 'default' => '© {year} Ananya Tots. All rights reserved.', 'rules' => 'required|string|max:200'],
            'footer_made' => ['type' => 'text', 'label' => 'Footer sign-off', 'default' => 'Made for little wonders.', 'rules' => 'nullable|string|max:80'],
        ],
        'Announcement bar' => [
            'announcement_enabled' => ['type' => 'boolean', 'label' => 'Show announcement bar above the header', 'default' => false],
            'announcement_text' => ['type' => 'text', 'label' => 'Announcement text', 'default' => 'Admissions are open for the new academic year.', 'rules' => 'nullable|string|max:160'],
            'announcement_link_label' => ['type' => 'text', 'label' => 'Announcement link label', 'default' => 'Enquire now', 'rules' => 'nullable|string|max:40'],
            'announcement_url' => ['type' => 'link', 'label' => 'Announcement link', 'default' => '/admissions#enquiry', 'rules' => 'nullable|string|max:255'],
        ],
        'Contact' => [
            'contact_email' => ['type' => 'email', 'label' => 'Public email address', 'default' => '', 'rules' => 'nullable|email|max:120'],
            'contact_phone' => ['type' => 'tel', 'label' => 'Public phone number (as displayed)', 'default' => '+91 93160 49220', 'rules' => 'nullable|string|max:40'],
            'contact_address' => ['type' => 'textarea', 'label' => 'Postal address (used in structured data)', 'default' => '', 'rules' => 'nullable|string|max:300'],
            'contact_city' => ['type' => 'text', 'label' => 'City', 'default' => 'Ahmedabad', 'rules' => 'nullable|string|max:80'],
            'contact_region' => ['type' => 'text', 'label' => 'State / region', 'default' => 'Gujarat', 'rules' => 'nullable|string|max:80'],
            'contact_country' => ['type' => 'text', 'label' => 'Country code', 'default' => 'IN', 'rules' => 'nullable|string|max:2'],
            'notify_email' => ['type' => 'email', 'label' => 'Send new enquiries to (leave empty to only store them)', 'default' => '', 'rules' => 'nullable|email|max:120'],
            'careers_email' => ['type' => 'email', 'label' => 'Send job applications to', 'default' => '', 'rules' => 'nullable|email|max:120'],
            'enquiry_centres' => ['type' => 'textarea', 'label' => 'Enquiry form: centres (one per line)', 'default' => "Zundal\nLilapur", 'rules' => 'required|string|max:1000'],
            'enquiry_programmes' => ['type' => 'textarea', 'label' => 'Enquiry form: programmes (one per line)', 'default' => "Mother-Infant\nPlaygroup\nNursery\nLKG (K1)\nUKG (K2)", 'rules' => 'required|string|max:1000'],
            'careers_positions' => ['type' => 'textarea', 'label' => 'Careers form: positions (one per line)', 'default' => "Early Years Educator\nAssistant Teacher\nOther", 'rules' => 'required|string|max:1000'],
            'privacy_url' => ['type' => 'link', 'label' => 'Privacy policy link (forms)', 'default' => '#privacy-policy', 'rules' => 'nullable|string|max:255'],
        ],
        'Social' => [
            'social_facebook' => ['type' => 'url', 'label' => 'Facebook URL', 'default' => '', 'rules' => 'nullable|url|max:255'],
            'social_instagram' => ['type' => 'url', 'label' => 'Instagram URL', 'default' => '', 'rules' => 'nullable|url|max:255'],
            'social_youtube' => ['type' => 'url', 'label' => 'YouTube URL', 'default' => '', 'rules' => 'nullable|url|max:255'],
            'social_linkedin' => ['type' => 'url', 'label' => 'LinkedIn URL', 'default' => '', 'rules' => 'nullable|url|max:255'],
            'social_primary_url' => ['type' => 'link', 'label' => 'Footer “Follow us” link target', 'default' => '#social', 'rules' => 'nullable|string|max:255'],
        ],
        'SEO defaults' => [
            'seo_title_suffix' => ['type' => 'text', 'label' => 'Title suffix added to page titles', 'default' => ' | Ananya Tots', 'rules' => 'nullable|string|max:60'],
            'seo_default_description' => ['type' => 'textarea', 'label' => 'Default meta description', 'default' => 'Ananya Tots is a play-based, experiential and child-centred preschool in Ahmedabad for children from 8 months to 6 years.', 'rules' => 'nullable|string|max:300'],
            'og_default_image' => ['type' => 'image', 'label' => 'Default social sharing image (1200×630)', 'default' => ['src' => 'assets/img/og-image.jpg', 'media_id' => null, 'alt' => 'Ananya Tots']],
            'org_type' => ['type' => 'select', 'label' => 'Organisation schema type', 'default' => 'Preschool', 'options' => ['Preschool' => 'Preschool', 'EducationalOrganization' => 'EducationalOrganization', 'ChildCare' => 'ChildCare'], 'rules' => 'required|in:Preschool,EducationalOrganization,ChildCare'],
            'robots_txt' => ['type' => 'textarea', 'label' => 'robots.txt extra rules', 'default' => '', 'rules' => 'nullable|string|max:5000', 'help' => 'Admin, login and preview URLs are always disallowed; the sitemap line is added automatically.'],
            'discourage_indexing' => ['type' => 'boolean', 'label' => 'Discourage all search engines (staging sites)', 'default' => false],
        ],
    ],
];
