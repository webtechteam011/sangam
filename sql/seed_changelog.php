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
