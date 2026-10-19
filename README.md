# The Sangam House — Devprayag (Core PHP)

Dynamic Core PHP website + enquiry dashboard for The Sangam House hotel, Devprayag, Uttarakhand.

## Stack
PHP 8.2, MySQL (PDO), Apache `.htaccess` front controller. No framework.

## Layout
```
index.php            front controller / router
includes/            config, db, helpers, SEO (head + JSON-LD), page registry, landing content, enquiry handler
pages/               page templates (static pages + _landing.php)
admin/               dashboard: enquiries, /admin/version (change log), /admin/settings.php (scripts)
sql/schema.sql       database schema
sql/create_admin.php CLI: apply schema + create/reset an admin
sql/seed_changelog.php CLI: seed the change log
tools/seo_audit.py   SEO audit (python3 tools/seo_audit.py https://site)
```

## Setup
1. Create `config.private.php` **one level above the web root** (see `config.private.example.php`).
2. `php sql/create_admin.php admin "Your Name"` — applies the schema and prints a generated password.
3. `php sql/seed_changelog.php` — optional change-log history.
4. Point the domain to the web root. Admin: `/admin/`.

## SEO
Titles, descriptions, canonical, Open Graph and JSON-LD come from `includes/pages.php`. `/sitemap.xml` and `/robots.txt` are generated. Non-production hosts are automatically `noindex`.

Secrets never live in this repo.

## Blog (v1.5.0)
Public: `/blog`, `/blog/{slug}`, `/blog/category/{slug}`, `/blog/tag/{slug}`, `/blog/feed` (RSS). Admin: `/admin/blog` (posts), `/admin/blog-edit`, `/admin/blog-taxonomy`, `/admin/media`.
- Deploy: run `php sql/migrate_blog.php` once on the server (creates tables + default categories), then upload the code. Keep `uploads/blog/` out of `rsync --delete` / git deploys — it holds user images.
- Scheduling needs no cron: a post with status Published and a future date goes live automatically.
- Post HTML is whitelisted server-side (`blog_sanitize_html`); uploads are re-encoded and PHP execution is disabled in `uploads/`.
- Slug changes create 301 redirects; JSON-LD (BlogPosting/Blog/FAQ/Breadcrumb), sitemap and RSS are generated.
