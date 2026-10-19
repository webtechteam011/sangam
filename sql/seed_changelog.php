<?php
declare(strict_types=1);

/** CLI only: php sql/seed_changelog.php — idempotent (safe to re-run). */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$pdo = db();
foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__ . '/schema.sql')))) as $stmt) {
    $pdo->exec($stmt);
}

$D = '2026-10-18';
$entries = [
  // ---------------- v1.5.0 ----------------
  ['1.5.0', 'new', 'Blog management system', 'Admin → Blog: write, schedule and publish articles with a rich editor (headings, images, tables, YouTube, callouts, buttons, HTML mode), categories, tags, featured posts, FAQ blocks, revisions with one-click restore, duplicate, trash and bulk actions, plus per-post view analytics.'],
  ['1.5.0', 'new', 'Media library with automatic image optimisation', 'Drag-and-drop uploads are validated, resized to 1920px, stripped of metadata and given thumbnails. Alt text can be edited per image; images in use are protected from deletion.'],
  ['1.5.0', 'new', 'Blog SEO tools', 'Live Google preview, focus-keyword checklist and 0-100 score in the editor; BlogPosting, Blog, Breadcrumb and FAQ JSON-LD; Open Graph article tags; blog posts, categories and tags added to sitemap.xml; RSS feed at /blog/feed; 301 redirects when a post URL changes; thin tag pages auto-noindex.'],
  ['1.5.0', 'new', 'Public blog at /blog', 'Featured post, category filters, search, pagination with rel prev/next, auto table of contents, reading progress, related posts, share buttons and booking call-to-actions. Scheduled posts go live automatically.'],
  // ---------------- v1.4.0 ----------------
  ['1.4.0', 'new', 'Clean URLs everywhere: no more ".php" in the address bar', 'Admin pages are now /admin/login, /admin/seo, /admin/settings, /admin/version, /admin/enquiry and /admin/export. Any old .php address 301-redirects to the clean URL.'],
  ['1.4.0', 'security', 'Bot protection: scanners and aggressive scrapers are blocked', 'Known vulnerability scanners (sqlmap, nikto, nmap, wpscan …) and heavy scrapers/AI crawlers get 403. Real search engines (Google, Bing) and social previews are unaffected.'],
  ['1.4.0', 'security', 'Exploit-probe blocking', 'Requests for /wp-login, /xmlrpc, /phpmyadmin, /.env, /.git, shells and backup files return 404/403 immediately and never reach the app.'],
  ['1.4.0', 'security', 'Per-visitor rate limiting', 'Page requests are throttled (90/min per IP, search-engine bots exempt) with a friendly "Slow down" page (HTTP 429). Enquiry form limited to 3/min and 5/hour per IP.'],
  ['1.4.0', 'security', 'Stronger enquiry form anti-spam', 'Requests must come from our own pages (Origin/Referer + marker header), include human-like timing, pass the honeypot and contain at most one link. Direct bot POSTs are rejected.'],
  ['1.4.0', 'security', 'Admin login hardened', 'Hidden honeypot, minimum fill time, slower responses on wrong passwords, existing 5-attempt/15-minute IP lockout kept. Internal admin files (_bootstrap) can no longer be opened directly.'],
  ['1.4.0', 'security', 'More security headers', 'Content-Security-Policy (frame-ancestors, base-uri, form-action, object-src), Cross-Origin-Opener-Policy, stricter Permissions-Policy; X-Powered-By removed; dotfiles, backups, logs and source files blocked.'],
  // ---------------- v1.3.1 ----------------
  ['1.3.1', 'improved', 'Admin login is remembered for 30 days', 'Sign-in now persists across browser restarts. The 30-day window slides forward on every visit, so active admins stay signed in. Previously it ended when the browser closed or after 2 idle hours.'],
  ['1.3.1', 'fixed', 'Admin could be logged out unexpectedly on shared hosting', 'Sessions are now stored in a private folder outside the web root, so the server\'s default clean-up (about 24 minutes) no longer deletes them.'],
  // ---------------- v1.3.0 ----------------
  ['1.3.0', 'new', 'Branded error pages for 403, 404, 500 and 503', 'Dark/gold design with the logo, a clear message, Back-to-Home / Contact / WhatsApp buttons, popular-page links and phone/email. Replaces the plain browser "This page isn\'t working" screen.'],
  ['1.3.0', 'fixed', 'Visitors could see a blank browser HTTP 500 error', 'Uncaught exceptions and fatal errors are now caught, logged to app_errors.log (outside the web root) and answered with the branded 500 page with a Try-again button.'],
  ['1.3.0', 'improved', 'Apache ErrorDocument rules route server-level errors to the same branded page', 'Blocked folders (403) and missing files (404) look consistent with the rest of the site; error pages are never indexed.'],
  // ---------------- v1.2.0 ----------------
  ['1.2.0', 'new', 'Admin "SEO" manager: control SEO of every page from the dashboard', 'Edit title, meta description, keywords, Open Graph title/description/image, canonical URL, index/noindex, sitemap inclusion, priority, change frequency and custom JSON-LD per page. Includes live Google-style preview and length counters. Empty fields fall back to the built-in defaults, with one-click reset.'],
  ['1.2.0', 'improved', 'Sitemap respects SEO manager', 'Pages set to noindex or excluded from the sitemap are omitted; custom canonical, priority and change frequency are used.'],
  // ---------------- v1.1.0 ----------------
  ['1.1.0', 'new', 'Admin "Scripts" settings: Header, Body-first and Footer script boxes', 'Paste Google Tag Manager, Analytics, Meta Pixel, chat widgets or verification tags from the dashboard (Admin → Scripts). Saved in the database and injected into every public page without touching code.'],
  ['1.1.0', 'improved', 'SEO: unique, descriptive image alt text on every page', 'Replaced generic/duplicate alts ("Suite View 2", "tiger safari india") with descriptive, unique text for accessibility and image search.'],
  ['1.1.0', 'improved', 'Page speed: lazy-loaded images, LCP image preload, deferred JS, non-blocking fonts & icons', 'Below-the-fold images use loading="lazy"; the hero/LCP image is preloaded with high priority; all scripts are deferred; Google Fonts and Font Awesome no longer block first paint.'],
  ['1.1.0', 'new', 'Richer JSON-LD structured data', 'Added HotelRoom + Offer (price in INR), ContactPoint, opening hours, AboutPage / ContactPage / CollectionPage types and an ItemList of TouristAttractions for Activities, on top of Hotel, WebSite, Breadcrumb and FAQ schema.'],
  ['1.1.0', 'improved', 'Meta tags: og:image width/height/type, twitter:image:alt, format-detection, app titles', 'Open Graph image size is now read from the actual file so social previews render correctly.'],
  ['1.1.0', 'improved', 'HSTS header and tighter security headers', 'Strict-Transport-Security sent over HTTPS.'],
  ['1.1.0', 'new', 'Terms of Service page (/terms-of-service)', 'Footer link previously pointed to "#". Page is in the sitemap.'],
  ['1.1.0', 'new', 'Cancellation & Refund Policy page (/cancellation-policy)', 'Footer link previously pointed to "#". Terms reference the booking confirmation; confirm exact refund rules with the property.'],
  ['1.1.0', 'new', 'Admin "Version" page with full change log (/admin/version)', 'Developer-maintained log of every new feature, improvement and fix, stored in the database, with an add-entry form.'],
  ['1.1.0', 'new', 'SEO audit script (tools/seo_audit.py)', 'Runs 20+ checks per page: title, description, H1, canonical, robots, viewport, alt text, Open Graph, JSON-LD, lazy-loading, preload, defer, sitemap, robots.txt.'],
  ['1.1.0', 'fixed', 'Removed unverified check-in/check-out times from schema', 'Structured data should only contain facts confirmed by the property.'],
  ['1.1.0', 'fixed', 'Broken inline onerror handlers on gallery images', 'Malformed JavaScript in onerror attributes removed.'],
  ['1.1.0', 'fixed', 'Home page meta description trimmed to ideal length', 'Was over 165 characters and could be truncated in search results.'],
  // ---------------- v1.0.0 ----------------
  ['1.0.0', 'new', 'Static HTML site converted to Core PHP', 'Shared header/footer/booking modal includes, page registry, front controller (index.php) and clean URLs (/about, /accommodation …).'],
  ['1.0.0', 'new', 'Enquiry system backed by MySQL', 'Contact form, booking pop-up and landing-page forms submit via AJAX to /enquiry; stored in the enquiries table with spam honeypot, timing check, same-origin check, IP rate limit and email notification.'],
  ['1.0.0', 'new', 'Admin dashboard (/admin)', 'Secure login (hashed passwords, rate-limited), stat cards, 14-day chart, status tabs (New/Contacted/Booked/Closed), search and date filters, call/WhatsApp shortcuts, notes, delete and CSV export.'],
  ['1.0.0', 'new', 'Four SEO landing pages', '/hotels-in-devprayag, /resorts-in-devprayag, /stay-near-devprayag-sangam, /devprayag-weekend-getaway — each with unique content, FAQ and enquiry form targeting the keyword list.'],
  ['1.0.0', 'new', 'Per-page SEO: titles, descriptions, canonical, Open Graph, Twitter Cards, hreflang, geo tags', 'Declared once in includes/pages.php and rendered into every <head>.'],
  ['1.0.0', 'new', 'JSON-LD: Hotel/LodgingBusiness, WebSite, WebPage, BreadcrumbList, FAQPage', 'Generated per page from the same registry.'],
  ['1.0.0', 'new', 'Dynamic sitemap.xml and robots.txt', 'Sitemap lists every page with lastmod and image; robots.txt blocks /admin/ and flips to noindex on non-production hosts.'],
  ['1.0.0', 'security', 'Secrets kept outside the web root', 'DB credentials live in config.private.php above public_html; includes/, pages/ and sql/ are blocked from direct access.'],
  ['1.0.0', 'security', 'HTTPS + www redirects, security headers, admin noindex', 'X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy; /admin never indexed.'],
  ['1.0.0', 'improved', 'Images optimised from 58 MB to 23 MB', 'Large PNG photos converted to JPEG and oversized JPEGs re-compressed (max 1600px).'],
  ['1.0.0', 'improved', 'Old .html URLs 301-redirect to clean URLs', 'Preserves existing rankings for index.html, about.html, etc.'],
  ['1.0.0', 'improved', 'Caching & compression', 'Gzip/deflate and long-lived cache headers for images, CSS and JS.'],
  ['1.0.0', 'fixed', 'Contact and booking forms only showed an alert and sent nothing', 'Both now submit to the database and show inline success/error messages.'],
  ['1.0.0', 'fixed', 'Multiple H1s on the home page and missing H1 on About and Accommodation', 'Exactly one H1 per page.'],
  ['1.0.0', 'fixed', 'Broken footer links (adventure.html, #adventure)', 'Now point to /activities and the new landing pages.'],
  ['1.0.0', 'fixed', 'Booking pop-up phone field had a WhatsApp URL as placeholder', 'Replaced with a proper placeholder.'],
  ['1.0.0', 'fixed', 'Wrong alt text copied from another site ("tiger safari india", "jungle camps india")', 'Replaced on the WhatsApp/Call buttons.'],
  ['1.0.0', 'fixed', 'No-cache meta tags, invalid AMP analytics tag and duplicate Google Fonts request', 'Removed/merged to improve caching and speed.'],
  ['1.0.0', 'fixed', 'Header logo rendered stretched after migration', 'Removed hard-coded width/height that overrode the CSS.'],
];

$st = $pdo->prepare('INSERT IGNORE INTO changelog (version, released_on, type, title, details, author) VALUES (?,?,?,?,?,?)');
$n = 0;
foreach ($entries as [$v, $t, $title, $d]) {
    $st->execute([$v, $D, $t, $title, $d, 'Developer']);
    $n += $st->rowCount();
}
echo "Seeded $n new change-log entries.\n";
