<?php
declare(strict_types=1);

/**
 * Blog engine: slugs, HTML sanitising, table of contents, queries, schema, view counting.
 * Used by the public router (includes/blog_router.php) and the admin screens.
 */
require_once __DIR__ . '/db.php';

const BLOG_PER_PAGE = 9;
const BLOG_RESERVED_SLUGS = ['feed', 'category', 'tag', 'page', 'search', 'rss', 'amp'];
const BLOG_UPLOAD_DIR = '/uploads/blog';

/* ------------------------------------------------------------------ slugs */

function blog_slugify(string $text, int $max = 100): string
{
    $text = trim($text);
    if (class_exists('Transliterator')) {
        $t = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        if ($t) {
            $text = (string)$t->transliterate($text);
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    if (strlen($text) > $max) {
        $text = rtrim(substr($text, 0, $max), '-');
        $cut = strrpos($text, '-');
        if ($cut !== false && $cut > $max * 0.6) {
            $text = substr($text, 0, $cut);
        }
    }
    return $text;
}

/** Make a slug unique in $table (posts/categories/tags); $ignoreId lets a row keep its own slug. */
function blog_unique_slug(string $table, string $slug, int $ignoreId = 0, string $fallback = 'post'): string
{
    if (!in_array($table, ['blog_posts', 'blog_categories', 'blog_tags'], true)) {
        throw new InvalidArgumentException('bad table');
    }
    $base = $slug !== '' ? $slug : $fallback;
    if ($table === 'blog_posts' && in_array($base, BLOG_RESERVED_SLUGS, true)) {
        $base .= '-post';
    }
    $try = $base;
    for ($i = 2; $i < 500; $i++) {
        $st = db()->prepare("SELECT COUNT(*) FROM $table WHERE slug = ? AND id <> ?");
        $st->execute([$try, $ignoreId]);
        if ((int)$st->fetchColumn() === 0) {
            return $try;
        }
        $try = $base . '-' . $i;
    }
    return $base . '-' . bin2hex(random_bytes(3));
}

function blog_url(string $slug = ''): string
{
    return '/blog' . ($slug !== '' ? '/' . $slug : '');
}

/* -------------------------------------------------------------- sanitiser */

const BLOG_ALLOWED_TAGS = [
    'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'strong' => [], 'em' => [], 'u' => [], 's' => [],
    'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [], 'code' => [], 'pre' => [], 'sup' => [], 'sub' => [], 'mark' => [],
    'a' => ['href', 'title', 'target', 'rel'],
    'img' => ['src', 'alt', 'title', 'width', 'height'],
    'figure' => [], 'figcaption' => [],
    'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    'div' => [], 'iframe' => ['src', 'title', 'width', 'height', 'allowfullscreen'],
];
const BLOG_CLASS_WHITELIST = ['callout', 'cta-button', 'video-embed', 'align-center', 'align-left', 'align-right'];
const BLOG_RENAME_TAGS = ['h1' => 'h2', 'b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's'];
const BLOG_DROP_TAGS = ['script', 'style', 'link', 'meta', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'noscript', 'template', 'title', 'head'];

function blog_safe_url(string $url, bool $allowRelative = true): string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
    if ($url === '' || preg_match('/[\x00-\x1f\x7f]/', $url)) {
        return '';
    }
    if (preg_match('#^(https?://|mailto:|tel:)#i', $url)) {
        return $url;
    }
    if ($allowRelative && preg_match('#^(/(?!/)|\#)#', $url)) {
        return $url;
    }
    return '';
}

function blog_is_youtube(string $src): bool
{
    return (bool)preg_match('#^https://(www\.)?(youtube\.com|youtube-nocookie\.com)/embed/[A-Za-z0-9_-]{6,20}(\?[A-Za-z0-9_=&%.-]*)?$#', $src);
}

function blog_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="sg-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $doc->getElementById('sg-root');
    if (!$root) {
        return '';
    }
    blog_clean_node($root, $doc);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    // Drop empty paragraphs the editor leaves behind.
    $out = preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $out) ?? $out;
    return trim($out);
}

function blog_clean_node(DOMNode $node, DOMDocument $doc): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMComment) {
            $node->removeChild($child);
            continue;
        }
        if (!$child instanceof DOMElement) {
            continue;
        }
        $tag = strtolower($child->tagName);

        if (in_array($tag, BLOG_DROP_TAGS, true)) {
            $node->removeChild($child);
            continue;
        }
        if (isset(BLOG_RENAME_TAGS[$tag])) {
            $new = $doc->createElement(BLOG_RENAME_TAGS[$tag]);
            while ($child->firstChild) {
                $new->appendChild($child->firstChild);
            }
            $node->replaceChild($new, $child);
            $child = $new;
            $tag = strtolower($child->tagName);
        }
        // span / font / section etc: keep the text, lose the wrapper.
        if (!isset(BLOG_ALLOWED_TAGS[$tag])) {
            blog_clean_node($child, $doc);
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }

        $allowed = BLOG_ALLOWED_TAGS[$tag];
        foreach (iterator_to_array($child->attributes) as $attr) {
            $name = strtolower($attr->name);
            if ($name === 'class') {
                $keep = array_values(array_intersect(preg_split('/\s+/', trim($attr->value)) ?: [], BLOG_CLASS_WHITELIST));
                if ($keep) {
                    $child->setAttribute('class', implode(' ', $keep));
                } else {
                    $child->removeAttribute('class');
                }
                continue;
            }
            if (!in_array($name, $allowed, true)) {
                $child->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a') {
            $href = blog_safe_url($child->getAttribute('href'));
            if ($href === '') {
                // dead link: keep the text
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            $child->setAttribute('href', $href);
            $external = preg_match('#^https?://#i', $href) && !preg_match('#^https?://(www\.)?thesangamhouse\.com(/|$)#i', $href);
            if ($external) {
                $child->setAttribute('target', '_blank');
                $rel = array_filter(explode(' ', $child->getAttribute('rel')));
                $rel = array_unique(array_merge($rel, ['noopener']));
                $child->setAttribute('rel', implode(' ', array_intersect($rel, ['noopener', 'nofollow', 'sponsored', 'ugc'])));
            } else {
                $child->removeAttribute('target');
                $rel = array_intersect(array_filter(explode(' ', $child->getAttribute('rel'))), ['nofollow']);
                $rel ? $child->setAttribute('rel', implode(' ', $rel)) : $child->removeAttribute('rel');
            }
        } elseif ($tag === 'img') {
            $src = blog_safe_url($child->getAttribute('src'));
            if ($src === '' || str_starts_with(strtolower($src), 'mailto:') || str_starts_with(strtolower($src), 'tel:')) {
                $node->removeChild($child);
                continue;
            }
            $child->setAttribute('src', $src);
            foreach (['width', 'height'] as $dim) {
                $v = $child->getAttribute($dim);
                if ($v !== '' && !ctype_digit($v)) {
                    $child->removeAttribute($dim);
                }
            }
        } elseif ($tag === 'iframe') {
            $src = trim($child->getAttribute('src'));
            if (!blog_is_youtube($src)) {
                $node->removeChild($child);
                continue;
            }
            $child->setAttribute('loading', 'lazy');
            $child->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
            $child->setAttribute('allow', 'accelerometer; encrypted-media; picture-in-picture');
            $child->setAttribute('allowfullscreen', '');
            if ($child->getAttribute('title') === '') {
                $child->setAttribute('title', 'Video');
            }
        }
        blog_clean_node($child, $doc);
    }
}

/** Plain text of an HTML fragment (for search, excerpt, word count). */
function blog_plain_text(string $html): string
{
    $html = preg_replace('#<(br|/p|/h[1-6]|/li|/tr|/blockquote|/div|/figcaption)\b[^>]*>#i', ' ', $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}

function blog_word_count(string $html): int
{
    $text = blog_plain_text($html);
    return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
}

function blog_reading_time(int $words): int
{
    return max(1, (int)ceil($words / 200));
}

function blog_auto_excerpt(string $html, int $len = 155): string
{
    // Prefer running paragraph text over headings glued together.
    $text = '';
    if (preg_match_all('#<p\b[^>]*>(.*?)</p>#is', $html, $m)) {
        $text = blog_plain_text(implode(' ', $m[1]));
    }
    if ($text === '') {
        $text = blog_plain_text($html);
    }
    return mb_strlen($text) <= $len ? $text : rtrim(mb_substr($text, 0, $len - 1), " ,.;:-") . '…';
}

/* ----------------------------------------------- rendering (TOC, lazy img) */

/**
 * Prepare stored HTML for display: heading ids, lazy images with dimensions,
 * responsive iframes. Returns [html, toc] where toc = [[level, id, text], ...].
 */
function blog_render_content(string $html): array
{
    if (trim($html) === '') {
        return ['', []];
    }
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="sg-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('sg-root');
    if (!$root) {
        return [$html, []];
    }

    $toc = [];
    $used = [];
    $xp = new DOMXPath($doc);
    foreach ($xp->query('//h2|//h3') as $h) {
        $text = trim(preg_replace('/\s+/u', ' ', $h->textContent) ?? '');
        if ($text === '') {
            continue;
        }
        $id = blog_slugify($text, 60) ?: 'section';
        $base = $id;
        for ($i = 2; isset($used[$id]); $i++) {
            $id = $base . '-' . $i;
        }
        $used[$id] = true;
        $h->setAttribute('id', $id);
        $toc[] = [(int)substr($h->nodeName, 1), $id, $text];
    }

    foreach ($root->getElementsByTagName('img') as $img) {
        $img->setAttribute('loading', 'lazy');
        $img->setAttribute('decoding', 'async');
        $src = $img->getAttribute('src');
        if (str_starts_with($src, '/') && (!$img->hasAttribute('width') || !$img->hasAttribute('height'))) {
            $size = image_size(parse_url($src, PHP_URL_PATH) ?: $src);
            if ($size) {
                $img->setAttribute('width', (string)$size[0]);
                $img->setAttribute('height', (string)$size[1]);
            }
        }
        if (!$img->hasAttribute('alt')) {
            $img->setAttribute('alt', '');
        }
    }
    foreach (iterator_to_array($root->getElementsByTagName('iframe')) as $frame) {
        $wrap = $frame->parentNode;
        if (!$wrap instanceof DOMElement || !str_contains($wrap->getAttribute('class'), 'video-embed')) {
            $div = $doc->createElement('div');
            $div->setAttribute('class', 'video-embed');
            $wrap->replaceChild($div, $frame);
            $div->appendChild($frame);
        }
    }

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return [$out, $toc];
}

/* --------------------------------------------------------------- queries */

/** SQL condition for "visible to the public right now". */
const BLOG_LIVE = "p.status = 'published' AND p.published_at IS NOT NULL AND p.published_at <= NOW()";

const BLOG_CARD_COLS = 'p.id, p.title, p.slug, p.excerpt, p.cover_image, p.cover_alt, p.published_at, p.updated_at, p.author_name,
    p.reading_time, p.views, p.is_featured, p.category_id, c.name AS category_name, c.slug AS category_slug';

function blog_list(array $opts = []): array
{
    $where = [BLOG_LIVE];
    $args = [];
    if (!empty($opts['category_id'])) { $where[] = 'p.category_id = ?'; $args[] = (int)$opts['category_id']; }
    if (!empty($opts['tag_id'])) { $where[] = 'EXISTS (SELECT 1 FROM blog_post_tags pt WHERE pt.post_id = p.id AND pt.tag_id = ?)'; $args[] = (int)$opts['tag_id']; }
    if (!empty($opts['exclude_id'])) { $where[] = 'p.id <> ?'; $args[] = (int)$opts['exclude_id']; }
    if (!empty($opts['exclude_ids'])) {
        $where[] = 'p.id NOT IN (' . implode(',', array_map('intval', $opts['exclude_ids'])) . ')';
    }
    if (!empty($opts['featured'])) { $where[] = 'p.is_featured = 1'; }
    if (isset($opts['q']) && trim((string)$opts['q']) !== '') {
        $like = '%' . addcslashes(trim((string)$opts['q']), '%_\\') . '%';
        $where[] = '(p.title LIKE ? OR p.excerpt LIKE ? OR p.content_text LIKE ?)';
        array_push($args, $like, $like, $like);
    }
    $sqlWhere = 'WHERE ' . implode(' AND ', $where);
    $from = 'FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id';

    $count = db()->prepare("SELECT COUNT(*) $from $sqlWhere");
    $count->execute($args);
    $total = (int)$count->fetchColumn();

    $per = max(1, (int)($opts['per_page'] ?? BLOG_PER_PAGE));
    $pages = max(1, (int)ceil($total / $per));
    $page = min(max(1, (int)($opts['page'] ?? 1)), $pages);
    $order = ($opts['order'] ?? '') === 'popular' ? 'p.views DESC, p.published_at DESC' : 'p.published_at DESC, p.id DESC';
    $limit = isset($opts['limit']) ? (int)$opts['limit'] : $per;
    $offset = isset($opts['limit']) ? 0 : ($page - 1) * $per;

    $st = db()->prepare('SELECT ' . BLOG_CARD_COLS . " $from $sqlWhere ORDER BY $order LIMIT $limit OFFSET $offset");
    $st->execute($args);
    return ['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function blog_find_post(string $slug, bool $includeHidden = false): ?array
{
    $st = db()->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug, (p.published_at > NOW()) AS is_future
         FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id WHERE p.slug = ?'
    );
    $st->execute([$slug]);
    $p = $st->fetch();
    if (!$p || $p['status'] === 'trash') {
        return null;
    }
    $live = $p['status'] === 'published' && $p['published_at'] !== null && !(int)$p['is_future'];
    return ($live || $includeHidden) ? $p : null;
}

function blog_redirect_target(string $slug): ?string
{
    $st = db()->prepare(
        "SELECT p.slug FROM blog_redirects r JOIN blog_posts p ON p.id = r.post_id AND " . BLOG_LIVE . " WHERE r.old_slug = ?"
    );
    $st->execute([$slug]);
    $s = $st->fetchColumn();
    return $s ? (string)$s : null;
}

function blog_post_tags(int $postId): array
{
    $st = db()->prepare('SELECT t.id, t.name, t.slug FROM blog_tags t JOIN blog_post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name');
    $st->execute([$postId]);
    return $st->fetchAll();
}

function blog_categories(bool $withCounts = true): array
{
    if (!$withCounts) {
        return db()->query('SELECT * FROM blog_categories ORDER BY sort_order, name')->fetchAll();
    }
    return db()->query(
        'SELECT c.*, (SELECT COUNT(*) FROM blog_posts p WHERE p.category_id = c.id AND ' . BLOG_LIVE . ') AS live_count,
                (SELECT COUNT(*) FROM blog_posts p WHERE p.category_id = c.id AND p.status <> \'trash\') AS post_count
         FROM blog_categories c ORDER BY c.sort_order, c.name'
    )->fetchAll();
}

function blog_tags_all(): array
{
    return db()->query(
        'SELECT t.*, (SELECT COUNT(*) FROM blog_post_tags pt JOIN blog_posts p ON p.id = pt.post_id AND p.status <> \'trash\' WHERE pt.tag_id = t.id) AS post_count
         FROM blog_tags t ORDER BY t.name'
    )->fetchAll();
}

/** Related by shared tags first, then same category, then newest. */
function blog_related(array $post, int $limit = 3): array
{
    $st = db()->prepare(
        'SELECT ' . BLOG_CARD_COLS . ',
            (SELECT COUNT(*) FROM blog_post_tags a JOIN blog_post_tags b ON b.tag_id = a.tag_id AND b.post_id = ? WHERE a.post_id = p.id) AS shared,
            (p.category_id <=> ?) AS same_cat
         FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id
         WHERE ' . BLOG_LIVE . ' AND p.id <> ?
         ORDER BY shared DESC, same_cat DESC, p.published_at DESC LIMIT ' . (int)$limit
    );
    $st->execute([$post['id'], $post['category_id'], $post['id']]);
    return $st->fetchAll();
}

function blog_adjacent(array $post): array
{
    $prev = db()->prepare('SELECT p.title, p.slug FROM blog_posts p WHERE ' . BLOG_LIVE . ' AND (p.published_at < ? OR (p.published_at = ? AND p.id < ?)) ORDER BY p.published_at DESC, p.id DESC LIMIT 1');
    $prev->execute([$post['published_at'], $post['published_at'], $post['id']]);
    $next = db()->prepare('SELECT p.title, p.slug FROM blog_posts p WHERE ' . BLOG_LIVE . ' AND (p.published_at > ? OR (p.published_at = ? AND p.id > ?)) ORDER BY p.published_at ASC, p.id ASC LIMIT 1');
    $next->execute([$post['published_at'], $post['published_at'], $post['id']]);
    return [$prev->fetch() ?: null, $next->fetch() ?: null];
}

function blog_faq_items(?string $json): array
{
    $items = $json ? json_decode($json, true) : null;
    if (!is_array($items)) {
        return [];
    }
    $out = [];
    foreach ($items as $it) {
        $q = trim((string)($it['q'] ?? ''));
        $a = trim((string)($it['a'] ?? ''));
        if ($q !== '' && $a !== '') {
            $out[] = [$q, $a];
        }
    }
    return $out;
}

/* ---------------------------------------------------------------- views */

function blog_is_bot(): bool
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $ua === '' || (bool)preg_match('/(bot|crawl|spider|slurp|facebookexternalhit|preview|fetch|monitor|curl|wget|python|java|headless|lighthouse|pingdom|uptime)/i', $ua);
}

function blog_count_view(int $postId): void
{
    if (blog_is_bot()) {
        return;
    }
    $cookie = 'sg_pv';
    $seen = array_filter(array_map('intval', explode('.', (string)($_COOKIE[$cookie] ?? ''))));
    if (in_array($postId, $seen, true)) {
        return;
    }
    try {
        $pdo = db();
        $pdo->prepare('UPDATE blog_posts SET views = views + 1 WHERE id = ?')->execute([$postId]);
        $pdo->prepare('INSERT INTO blog_views_daily (post_id, day, views) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE views = views + 1')->execute([$postId]);
    } catch (Throwable $e) {
        return;
    }
    $seen[] = $postId;
    $seen = array_slice($seen, -30);
    if (!headers_sent()) {
        setcookie($cookie, implode('.', $seen), [
            'expires' => time() + 1800, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }
}

/** True when the visitor holds a live admin session (lets editors preview drafts and scheduled posts). */
function blog_is_admin_preview(): bool
{
    if (empty($_COOKIE['sg_admin'])) {
        return false;
    }
    start_secure_session();
    return !empty($_SESSION['admin']);
}

/* ----------------------------------------------------------- date / text */

function blog_date(?string $dt, string $fmt = 'd M Y'): string
{
    return $dt ? date($fmt, strtotime($dt)) : '';
}

function blog_iso(?string $dt): string
{
    return $dt ? date('c', strtotime($dt)) : '';
}

/** Absolute URL for any stored image path (local path or full URL). */
function blog_img_abs(?string $path, string $fallback = '/img/slider-1.jpg'): string
{
    $path = (string)$path;
    if ($path === '') {
        return abs_url($fallback);
    }
    return preg_match('#^https?://#i', $path) ? $path : abs_url($path);
}

/** Cover image path, falling back to a site photo so every card has artwork. */
function blog_cover(array $p): string
{
    return !empty($p['cover_image']) ? $p['cover_image'] : '/img/davparyag.jpg';
}

function blog_thumb(string $path): string
{
    if (preg_match('#^(/uploads/blog/.+)\.(jpe?g|png|webp)$#i', $path, $m)) {
        $thumb = $m[1] . '-thumb.' . $m[2];
        if (is_file(dirname(__DIR__) . $thumb)) {
            return $thumb;
        }
    }
    return $path;
}

/* ------------------------------------------------------------ SEO / JSON-LD */

/** Nodes appended to the page's @graph for a single post. */
function blog_post_graph(array $post, array $tags, array $toc = []): array
{
    $url = abs_url('blog/' . $post['slug']);
    $img = blog_img_abs(!empty($post['og_image']) ? $post['og_image'] : blog_cover($post));
    $size = image_size(parse_url($img, PHP_URL_PATH) ?: '');
    $node = [
        '@type' => 'BlogPosting',
        '@id' => $url . '#article',
        'mainEntityOfPage' => ['@id' => $url . '#webpage'],
        'headline' => mb_substr((string)$post['title'], 0, 110),
        'description' => $post['meta_description'] ?: ($post['excerpt'] ?: blog_auto_excerpt((string)$post['content'])),
        'image' => $size ? ['@type' => 'ImageObject', 'url' => $img, 'width' => $size[0], 'height' => $size[1]] : [$img],
        'datePublished' => blog_iso($post['published_at']),
        'dateModified' => blog_iso($post['updated_at']),
        'author' => ['@type' => 'Organization', 'name' => $post['author_name'] ?: SITE_NAME, 'url' => SITE_URL . '/'],
        'publisher' => ['@type' => 'Organization', '@id' => SITE_URL . '/#hotel', 'name' => SITE_NAME,
            'logo' => ['@type' => 'ImageObject', 'url' => abs_url('/img/logo.png')]],
        'inLanguage' => 'en-IN',
        'wordCount' => (int)$post['word_count'],
        'timeRequired' => 'PT' . (int)$post['reading_time'] . 'M',
        'isPartOf' => ['@id' => SITE_URL . '/blog#blog'],
        'about' => ['@id' => SITE_URL . '/#hotel'],
    ];
    if (!empty($post['category_name'])) {
        $node['articleSection'] = $post['category_name'];
    }
    if ($tags) {
        $node['keywords'] = implode(', ', array_column($tags, 'name'));
    }
    return [$node];
}

function blog_index_graph(string $name): array
{
    return [[
        '@type' => 'Blog',
        '@id' => SITE_URL . '/blog#blog',
        'url' => abs_url('blog'),
        'name' => $name,
        'publisher' => ['@id' => SITE_URL . '/#hotel'],
        'inLanguage' => 'en-IN',
    ]];
}
