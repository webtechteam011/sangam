<?php
declare(strict_types=1);

/**
 * Per-page SEO overrides managed in Admin → SEO. Registry values in pages.php are the
 * defaults; any non-empty DB field replaces them. DB failures fall back to defaults.
 */
function seo_overrides(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }
    $rows = [];
    try {
        require_once __DIR__ . '/db.php';
        foreach (db()->query('SELECT * FROM seo_pages')->fetchAll() as $r) {
            $rows[$r['route']] = $r;
        }
    } catch (Throwable $e) {
        // keep defaults
    }
    return $rows;
}

function apply_seo_override(array $page, string $route): array
{
    $o = seo_overrides()[$route] ?? null;
    if (!$o) {
        return $page;
    }
    foreach (['title', 'description', 'keywords', 'og_title', 'og_description', 'canonical_url', 'priority', 'changefreq', 'extra_schema'] as $k) {
        if (isset($o[$k]) && trim((string)$o[$k]) !== '') {
            $page[$k] = trim((string)$o[$k]);
        }
    }
    if (!empty($o['og_image'])) {
        $page['image'] = $o['og_image'];
    }
    if (!empty($o['robots'])) {
        $page['robots'] = $o['robots'];
    }
    if ($o['in_sitemap'] !== null) {
        $page['in_sitemap'] = (bool)$o['in_sitemap'];
    }
    return $page;
}
