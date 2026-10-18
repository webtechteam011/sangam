<?php
declare(strict_types=1);

require_once __DIR__ . '/seo_overrides.php';
$registry = require __DIR__ . '/pages.php';
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=300');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach ($registry as $route => $p) {
    $p = apply_seo_override($p, (string)$route);
    if (($p['robots'] ?? 'index') === 'noindex' || ($p['in_sitemap'] ?? true) === false) {
        continue;
    }
    $src = $p['type'] === 'landing' ? __DIR__ . '/landing.php' : __DIR__ . '/../pages/' . $p['file'] . '.php';
    $mtime = @filemtime($src) ?: time();
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars((!empty($p['canonical_url']) ? $p['canonical_url'] : abs_url((string)$route)), ENT_XML1) . "</loc>\n";
    echo '    <lastmod>' . date('c', $mtime) . "</lastmod>\n";
    echo '    <changefreq>' . $p['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $p['priority'] . "</priority>\n";
    if (!empty($p['image'])) {
        echo '    <image:image><image:loc>' . htmlspecialchars(abs_url($p['image']), ENT_XML1) . "</image:loc></image:image>\n";
    }
    echo "  </url>\n";
}
echo "</urlset>\n";
