# Deployment guide

## 1. Server requirements

| Component | Requirement |
|---|---|
| OS | Any Linux server (Ubuntu 22.04/24.04, Debian 12, AlmaLinux 9 …) or cPanel/Plesk hosting with SSH |
| PHP | **8.2 or newer** (tested on 8.3) |
| PHP extensions | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd` (with JPEG, PNG, WebP, FreeType), `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip` |
| Database | MySQL 8.0+ or MariaDB 10.6+ (utf8mb4) |
| Web server | Nginx or Apache 2.4 (mod_rewrite) |
| Tools | Composer 2.x. Node.js 20+ is only needed if you rebuild the admin assets; compiled copies are already in `public/build`. |
| HTTPS | Required in production (secure cookies are on) |

Optional extras:
* Redis (faster cache and sessions).
* GD built with AVIF support (lets the library optimise AVIF uploads). Without it, AVIF uploads are accepted and published unchanged.

### php.ini (recommended)
```ini
expose_php = Off
upload_max_filesize = 12M      ; ≥ CMS_MAX_UPLOAD_KB
post_max_size = 64M            ; several images can be uploaded at once
memory_limit = 512M            ; image resizing of large photos
max_execution_time = 120
```

## 2. Installation

```bash
cd /var/www
git clone <your-repository> ananya-tots     # or upload the project files
cd ananya-tots

composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env        # see section 3
php artisan key:generate

php artisan migrate --force --seed          # creates tables, roles and imports the approved pages
php artisan storage:link                    # public/storage → storage/app/public (media library)
php artisan cms:create-admin                # creates the first Super Administrator
php artisan optimize                        # caches config, routes, views
```

### Database
```sql
CREATE DATABASE ananya_tots CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ananya_tots'@'localhost' IDENTIFIED BY '<a long random password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON ananya_tots.* TO 'ananya_tots'@'localhost';
```
`db:seed` is safe to run again. It never overwrites pages or menus that already exist.

### Admin assets (only if you change `resources/css/admin.css` or `resources/js/admin.js`)
```bash
npm ci && npm run build
```
The public website does not use Vite. It loads the approved static files in `public/`.

### Optional: move the design photos into the media library
The site starts with the original design images, served from `public/assets`. To make them croppable and replaceable in the media library (with responsive WebP versions), run this once:
```bash
php artisan cms:import-images --dry-run   # list
php artisan cms:import-images             # import
```

## 3. Environment (`.env`)

| Key | Production value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (**never** `true` on a live site: it exposes internals) |
| `APP_URL` | `https://www.your-domain.com` (canonical URLs and the sitemap use it, unless *Settings → Website URL* is filled) |
| `DB_*` | Your database credentials |
| `SESSION_SECURE_COOKIE` | `true` (HTTPS only) |
| `SESSION_ENCRYPT` | `true` |
| `MAIL_*` | SMTP account for enquiry notifications and password-reset e-mails |
| `CMS_ADMIN_PATH` | Optional: a less guessable admin URL, e.g. `school-office` |
| `TRUSTED_PROXIES` | Set when behind Cloudflare or a load balancer (`*` or comma-separated IPs) |

Secrets live only in `.env`, never in the code or the admin panel. Keep `.env` out of version control (it is in `.gitignore`).

## 4. Web server

The document root must be the **`public/`** folder, never the project root.

### Nginx
```nginx
server {
    listen 443 ssl http2;
    server_name www.example.com;
    root /var/www/ananya-tots/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/www.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/www.example.com/privkey.pem;

    client_max_body_size 64m;
    charset utf-8;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    # long-lived caching for static design files and optimised media
    location ~* \.(?:css|js|woff2|png|jpe?g|webp|avif|gif|svg|ico)$ {
        expires 30d; add_header Cache-Control "public"; try_files $uri /index.php?$query_string;
    }
    # never execute anything inside uploaded media
    location ^~ /storage/ { location ~ \.php$ { return 403; } }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
server { listen 80; server_name www.example.com example.com; return 301 https://www.example.com$request_uri; }
```

### Apache
Point the VirtualHost's `DocumentRoot` to `/var/www/ananya-tots/public` with `AllowOverride All`. The bundled `public/.htaccess` handles routing. To block script execution in uploads, add this to the VirtualHost:
```apache
<Directory /var/www/ananya-tots/storage/app/public>
    php_admin_flag engine off
    Options -ExecCGI -Indexes
</Directory>
```

### HTTPS
Use Let's Encrypt (`certbot --nginx` or `certbot --apache`) or your host's SSL tool. Redirect all HTTP to HTTPS. The application sends `Strict-Transport-Security` on HTTPS requests.

## 5. File permissions (least privilege)

```bash
sudo chown -R deploy:www-data /var/www/ananya-tots          # files owned by the deploy user
sudo find /var/www/ananya-tots -type f -exec chmod 644 {} \;
sudo find /var/www/ananya-tots -type d -exec chmod 755 {} \;
sudo chmod -R ug+rwX /var/www/ananya-tots/storage /var/www/ananya-tots/bootstrap/cache
chmod 640 /var/www/ananya-tots/.env
```
The web server needs write access only to `storage/` and `bootstrap/cache/`.

Where things are stored:
* `storage/app/private`: original uploads, CVs and photos from the careers form. Never served directly; only signed-in users can download them.
* `storage/app/public`: optimised images, served through `public/storage`.

## 6. Queue, scheduler, cache

* **Queue:** not required. Image optimisation runs during the upload request and typically takes 1–3 seconds per photo.
* **Scheduler:** not required. Scheduled pages become visible automatically at their publish time. Menus refresh within 5 minutes and the sitemap within 10 minutes.
* **Cache:**
  * Public pages are cached per section and cleared automatically whenever content, menus, media or settings change.
  * *Settings → Clear website cache* clears it by hand.
  * Use `CACHE_STORE=redis` if available, otherwise `database`.
  * After deploying new code, run `php artisan optimize`.

## 7. Deploying updates

```bash
php artisan down --render="errors::503"
git pull                      # or upload changed files
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
php artisan up
```

## 8. Backups and recovery

Back up **both** the database and the uploaded files:

```bash
# nightly (cron), keep 30 days
mysqldump --single-transaction --routines ananya_tots | gzip > /backups/db-$(date +%F).sql.gz
tar czf /backups/files-$(date +%F).tgz -C /var/www/ananya-tots storage/app .env
find /backups -mtime +30 -delete
```
* Copy backups off the server (S3, another host, …) and test a restore every few months.
* **Restore:** `gunzip < db.sql.gz | mysql ananya_tots`, extract `storage/app`, run `php artisan storage:link`, then `php artisan optimize:clear`.
* Deleted pages stay in the admin **trash** until permanently deleted. Original uploads are kept, so crops can always be undone.

## 9. Admin accounts

* **First account:** `php artisan cms:create-admin`. It prompts for name, e-mail and password; passwords need at least 12 characters with upper/lower case and a number.
* **Further accounts:** create them in **Admin → Users**, with roles Super Administrator, Administrator or Content Editor.
* **Forgotten password:** use the *Forgot your password?* link on the login page. This requires working `MAIL_*` settings. Alternatively, run `php artisan cms:create-admin` again with a new e-mail address.

## 10. Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generated
- [ ] `APP_URL` (or Settings → Website URL) is the final `https://` address. Canonicals, the sitemap and social tags depend on it.
- [ ] HTTPS active, HTTP → HTTPS redirect, `SESSION_SECURE_COOKIE=true`
- [ ] Document root is `public/`; `.env` is not web-accessible
- [ ] `php artisan migrate --force --seed`, `storage:link`, `optimize` done
- [ ] First Super Administrator created; strong unique passwords
- [ ] SMTP configured; *Settings → Contact → Send new enquiries to* filled in; a test enquiry received
- [ ] Placeholder content replaced (see docs/STATUS.md): principal's name, careers e-mail, centres, positions, privacy policy link, social links, postal address
- [ ] *Settings → SEO defaults → Discourage search engines* is **off**
- [ ] Sitemap submitted in Google Search Console: `https://your-domain/sitemap.xml`
- [ ] Nightly backups configured and a restore tested
- [ ] `storage/` and `bootstrap/cache/` writable; everything else read-only for the web server
