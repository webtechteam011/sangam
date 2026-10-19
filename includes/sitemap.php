<?php
declare(strict_types=1);

require_once __DIR__ . '/seo_overrides.php';
$registry = require __DIR__ . '/pages.php';
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=300');

$blogLast = 0;
$blogPosts = $blogCats = $blogTags = [];
try {
    require_once __DIR__ . '/blog.php';
    $blogPosts = db()->query('SELECT p.slug, p.cover_image, p.robots, p.canonical_url, p.updated_at FROM blog_posts p WHERE ' . BLOG_LIVE . ' ORDER BY p.published_at DESC')->fetchAll();
    $blogCats = db()->query(
        'SELECT c.slug, MAX(p.updated_at) AS last_mod FROM blog_categories c JOIN blog_posts p ON p.category_id = c.id AND ' . BLOG_LIVE . ' GROUP BY c.id'
    )->fetchAll();
    $blogTags = db()->query(
        'SELECT t.slug, MAX(p.updated_at) AS last_mod FROM blog_tags t JOIN blog_post_tags pt ON pt.tag_id = t.id JOIN blog_posts p ON p.id = pt.post_id AND ' . BLOG_LIVE . '
         GROUP BY t.id HAVING COUNT(*) >= 3'
    )->fetchAll();
    foreach ($blogPosts as $bp) {
        $blogLast = max($blogLast, (int)strtotime((string)$bp['updated_at']));
    }
} catch (Throwable $e) {
    // blog tables not created yet: sitemap still lists the static pages
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach ($registry as $route => $p) {
    $p = apply_seo_override($p, (string)$route);
    if (($p['robots'] ?? 'index') === 'noindex' || ($p['in_sitemap'] ?? true) === false) {
        continue;
    }
    $src = $p['type'] === 'landing' ? __DIR__ . '/landing.php' : __DIR__ . '/../pages/' . $p['file'] . '.php';
    $mtime = @filemtime($src) ?: time();
    if (($p['type'] ?? '') === 'blog') {
        $mtime = max($mtime, $blogLast);
    }
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

$emit = static function (string $loc, ?string $lastmod, string $freq, string $prio, ?string $image = null): void {
    echo "  <url>\n    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
    if ($lastmod) {
        echo '    <lastmod>' . date('c', (int)strtotime($lastmod)) . "</lastmod>\n";
    }
    echo "    <changefreq>$freq</changefreq>\n    <priority>$prio</priority>\n";
    if ($image) {
        echo '    <image:image><image:loc>' . htmlspecialchars(blog_img_abs($image), ENT_XML1) . "</image:loc></image:image>\n";
    }
    echo "  </url>\n";
};
foreach ($blogPosts as $bp) {
    if ($bp['robots'] === 'noindex' || ($bp['canonical_url'] && rtrim($bp['canonical_url'], '/') !== rtrim(abs_url('blog/' . $bp['slug']), '/'))) {
        continue;
    }
    $emit(abs_url('blog/' . $bp['slug']), $bp['updated_at'], 'monthly', '0.7', $bp['cover_image'] ?: null);
}
foreach ($blogCats as $bc) {
    $emit(abs_url('blog/category/' . $bc['slug']), $bc['last_mod'], 'weekly', '0.5');
}
foreach ($blogTags as $bt) {
    $emit(abs_url('blog/tag/' . $bt['slug']), $bt['last_mod'], 'weekly', '0.4');
}
echo "</urlset>\n";
