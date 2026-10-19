<?php
declare(strict_types=1);

/**
 * Public blog routes (called from index.php for "blog" and "blog/..."):
 *   /blog                       index (+ ?page=N, ?q=search)
 *   /blog/category/{slug}       category archive
 *   /blog/tag/{slug}            tag archive
 *   /blog/feed                  RSS 2.0
 *   /blog/{slug}                single post
 * Variables from index.php: $route (lower-cased path without slashes).
 */
require_once __DIR__ . '/blog.php';

/** @var string $route */
$parts = explode('/', $route);
$registry = require __DIR__ . '/pages.php';

if ($route === 'blog/feed' || $route === 'blog/rss') {
    require __DIR__ . '/blog_feed.php';
    exit;
}

$base = [
    'type' => 'blog', 'file' => 'blog', 'blog' => true, 'nav' => 'blog', 'priority' => '0.7', 'changefreq' => 'weekly',
    'image' => '/img/davparyag.jpg', 'keywords' => '',
];
$pageNo = max(1, (int)($_GET['page'] ?? 1));
$view = null;
$data = [];
$routeUrl = $route;

try {
    if ($route === 'blog') {
        $q = trim((string)($_GET['q'] ?? ''));
        $q = mb_substr($q, 0, 80);
        $page = apply_seo_override($registry['blog'], 'blog') + $base;
        $res = blog_list(['page' => $pageNo, 'q' => $q]);
        $data = ['mode' => $q !== '' ? 'search' : 'index', 'q' => $q, 'res' => $res, 'heading' => 'Devprayag Travel Guide & Blog', 'intro' => $page['description'], 'basePath' => '/blog'];
        if ($q !== '') {
            $page['title'] = 'Search results for "' . $q . '" | ' . SITE_NAME . ' Blog';
            $page['robots'] = 'noindex';
            $page['canonical_url'] = abs_url('blog');
            $data['heading'] = 'Search: ' . $q;
        }
        $page['extra_graph'] = blog_index_graph('The Sangam House Blog — Devprayag travel guide');
        $page['crumbs'] = [['Blog', 'blog']];
        $page['ptype'] = 'CollectionPage';
        $view = 'blog';
    } elseif (count($parts) === 3 && $parts[1] === 'category') {
        $st = db()->prepare('SELECT * FROM blog_categories WHERE slug = ?');
        $st->execute([$parts[2]]);
        $cat = $st->fetch();
        if (!$cat) {
            render_error_view(404);
            exit;
        }
        $res = blog_list(['page' => $pageNo, 'category_id' => $cat['id']]);
        $page = array_merge($base, [
            'title' => $cat['meta_title'] ?: ($cat['name'] . ' – Devprayag Travel Blog | ' . SITE_NAME),
            'description' => $cat['meta_description'] ?: ($cat['description'] ?: 'Read our ' . $cat['name'] . ' articles on Devprayag, Uttarakhand — travel tips, temples, rafting and stays by The Sangam House.'),
            'breadcrumb' => $cat['name'], 'ptype' => 'CollectionPage',
            'crumbs' => [['Blog', 'blog'], [$cat['name'], 'blog/category/' . $cat['slug']]],
        ]);
        $data = ['mode' => 'category', 'cat' => $cat, 'res' => $res, 'heading' => $cat['name'], 'intro' => $page['description'], 'basePath' => '/blog/category/' . $cat['slug']];
        $routeUrl = 'blog/category/' . $cat['slug'];
        if ($res['total'] === 0) {
            $page['robots'] = 'noindex';
        }
        $view = 'blog';
    } elseif (count($parts) === 3 && $parts[1] === 'tag') {
        $st = db()->prepare('SELECT * FROM blog_tags WHERE slug = ?');
        $st->execute([$parts[2]]);
        $tag = $st->fetch();
        if (!$tag) {
            render_error_view(404);
            exit;
        }
        $res = blog_list(['page' => $pageNo, 'tag_id' => $tag['id']]);
        $page = array_merge($base, [
            'title' => $tag['name'] . ' – Articles & Guides | ' . SITE_NAME . ' Blog',
            'description' => 'Articles tagged "' . $tag['name'] . '" from The Sangam House blog — Devprayag travel tips, places to visit and stay guides.',
            'breadcrumb' => $tag['name'], 'ptype' => 'CollectionPage',
            'crumbs' => [['Blog', 'blog'], [$tag['name'], 'blog/tag/' . $tag['slug']]],
        ]);
        if ($res['total'] < 3) {
            $page['robots'] = 'noindex';   // thin tag archive
        }
        $data = ['mode' => 'tag', 'tag' => $tag, 'res' => $res, 'heading' => '#' . $tag['name'], 'intro' => $page['description'], 'basePath' => '/blog/tag/' . $tag['slug']];
        $routeUrl = 'blog/tag/' . $tag['slug'];
        $view = 'blog';
    } elseif (count($parts) === 2) {
        $slug = $parts[1];
        $preview = false;
        $post = blog_find_post($slug);
        if (!$post && !empty($_GET['preview']) && blog_is_admin_preview()) {
            $post = blog_find_post($slug, true);
            $preview = $post !== null;
        }
        if (!$post) {
            if ($target = blog_redirect_target($slug)) {
                header('Location: /blog/' . $target, true, 301);
                exit;
            }
            render_error_view(404);
            exit;
        }
        $view = 'blog-post';
        $data = ['post' => $post, 'preview' => $preview];
        $routeUrl = 'blog/' . $post['slug'];
    } else {
        render_error_view(404);
        exit;
    }
} catch (PDOException $e) {
    // Tables not created yet / DB down: behave like a missing page, never a white screen.
    error_log('[blog] ' . $e->getMessage());
    render_error_view(404);
    exit;
}

/* ---------- single post page ---------- */
if ($view === 'blog-post') {
    $post = $data['post'];
    $preview = $data['preview'];
    $tags = blog_post_tags((int)$post['id']);
    [$bodyHtml, $toc] = blog_render_content((string)$post['content']);
    $faqItems = blog_faq_items($post['faq']);
    $cover = blog_cover($post);
    $ogImage = !empty($post['og_image']) ? $post['og_image'] : $cover;
    $desc = $post['meta_description'] ?: ($post['excerpt'] ?: blog_auto_excerpt((string)$post['content']));

    $page = array_merge($base, [
        'title' => $post['meta_title'] ?: $post['title'] . ' | ' . SITE_NAME,
        'description' => $desc,
        'keywords' => (string)($post['meta_keywords'] ?: implode(', ', array_filter([$post['focus_keyword'], ...array_column($tags, 'name')]))),
        'image' => $ogImage, 'image_alt' => $post['cover_alt'] ?: $post['title'],
        'breadcrumb' => $post['title'], 'og_type' => 'article', 'ptype' => 'WebPage',
        'canonical_url' => $post['canonical_url'] ?: '',
        'preload' => $cover,
        'robots' => ($post['robots'] === 'noindex' || $preview) ? 'noindex' : 'index',
        'crumbs' => array_values(array_filter([
            ['Blog', 'blog'],
            $post['category_slug'] ? [$post['category_name'], 'blog/category/' . $post['category_slug']] : null,
            [$post['title'], 'blog/' . $post['slug']],
        ])),
        'article' => [
            'published' => blog_iso($post['published_at']), 'modified' => blog_iso($post['updated_at']),
            'author' => $post['author_name'], 'section' => $post['category_name'] ?? '', 'tags' => array_column($tags, 'name'),
        ],
        'extra_graph' => blog_post_graph($post, $tags, $toc),
    ]);
    $faq = $faqItems;
    $related = blog_related($post, 3);
    [$prevPost, $nextPost] = blog_adjacent($post);

    if (!$preview) {
        // Conditional GET: lets crawlers skip unchanged articles.
        $tplMtime = max((int)@filemtime(__DIR__ . '/../pages/blog-post.php'), (int)@filemtime(__DIR__ . '/../assets/css/blog.css'));
        $lastMod = max(strtotime((string)$post['updated_at']), $tplMtime);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastMod) . ' GMT');
        $ims = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        if ($ims !== '' && strtotime($ims) !== false && strtotime($ims) >= $lastMod) {
            http_response_code(304);
            exit;
        }
        blog_count_view((int)$post['id']);
    }
} else {
    /* ---------- listing pages ---------- */
    $res = $data['res'];
    if ($pageNo > 1 && $pageNo > $res['pages']) {
        header('Location: /' . $routeUrl . ($res['pages'] > 1 ? '?page=' . $res['pages'] : ''), true, 301);
        exit;
    }
    $page['breadcrumb'] = $page['breadcrumb'] ?? 'Blog';
    $pageUrl = static fn(int $n): string => abs_url($routeUrl) . ($n > 1 ? '?page=' . $n : '');
    if ($data['mode'] !== 'search') {
        if ($res['page'] > 1) {
            $page['title'] = preg_replace('/\s*\|\s*/', ' – Page ' . $res['page'] . ' | ', $page['title'], 1);
            $page['canonical_url'] = $pageUrl($res['page']);
            $page['prev_url'] = $pageUrl($res['page'] - 1);
        }
        if ($res['page'] < $res['pages']) {
            $page['next_url'] = $pageUrl($res['page'] + 1);
        }
    }
    $categories = blog_categories();
    $popular = $data['mode'] === 'index' && $res['page'] === 1 ? blog_list(['order' => 'popular', 'limit' => 4])['rows'] : [];
    $featured = null;
    if ($data['mode'] === 'index' && $res['page'] === 1) {
        $f = blog_list(['featured' => true, 'limit' => 1])['rows'];
        $featured = $f[0] ?? null;
    }
    $faq = [];
    $page['preload'] = $featured ? blog_cover($featured) : ($page['preload'] ?? null);
}

$route = $routeUrl;
$nav = 'blog';
$pageJs = 'blog.js';
$preview = $preview ?? false;

header('Content-Type: text/html; charset=utf-8');
header($preview ? 'Cache-Control: no-store' : 'Cache-Control: public, max-age=300');
if ($preview) {
    header('X-Robots-Tag: noindex, nofollow');
}

ob_start();
require __DIR__ . '/../pages/' . $view . '.php';
$body = ob_get_clean();
if (!empty($page['preload'])) {
    $preload = $page['preload'];
}

require __DIR__ . '/header.php';
echo $body;
require __DIR__ . '/footer.php';
