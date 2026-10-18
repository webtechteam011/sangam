<?php
declare(strict_types=1);

/** Front controller: every non-file URL lands here (see .htaccess). */

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/errors.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/seo.php';
require __DIR__ . '/includes/seo_overrides.php';
require __DIR__ . '/includes/ratelimit.php';

guard_page_request();

$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$route = strtolower(trim($path, '/'));

// Canonicalise legacy / duplicate URLs with a 301.
$clean = preg_replace('#(^|/)index(\.php|\.html)?$#', '', $route);
$clean = preg_replace('#\.(html|php)$#', '', $clean);
if ($clean !== $route || ($path !== '/' && str_ends_with($path, '/'))) {
    $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: /' . $clean . $qs, true, 301);
    exit;
}

if ($route === 'enquiry') {
    require __DIR__ . '/includes/enquiry_handler.php';
    exit;
}
if ($route === 'sitemap.xml') {
    require __DIR__ . '/includes/sitemap.php';
    exit;
}
if ($route === 'robots.txt') {
    require __DIR__ . '/includes/robots.php';
    exit;
}

$registry = require __DIR__ . '/includes/pages.php';

if (!isset($registry[$route])) {
    render_error_view(404);
    exit;
}

$page = apply_seo_override($registry[$route], $route);
$nav  = $page['nav'] ?? '';
$faq  = [];
$pageJs = '';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');

if ($page['type'] === 'landing') {
    $content = (require __DIR__ . '/includes/landing.php')[$route];
    $faq = $content['faq'];
    $view = __DIR__ . '/pages/_landing.php';
} else {
    $view = __DIR__ . '/pages/' . $page['file'] . '.php';
    $pageJs = 'page-' . $page['file'] . '.js';
}

// Render the view first so the header can preload the page's LCP (first) image.
ob_start();
require $view;
$body = ob_get_clean();
if (!empty($page['preload'])) {
    $preload = $page['preload'];
} elseif (preg_match('#/img/[A-Za-z0-9_-]+\.(?:jpe?g|webp|png)#', $body, $m)) {
    $preload = $m[0];
}

require __DIR__ . '/includes/header.php';
echo $body;
require __DIR__ . '/includes/footer.php';
