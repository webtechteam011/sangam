<?php
declare(strict_types=1);

/** Admin-side blog logic: saving posts, taxonomy sync, image uploads. Loaded by admin/blog*.php and admin/media.php. */
require_once __DIR__ . '/blog.php';

const BLOG_MAX_UPLOAD = 8 * 1024 * 1024;
const BLOG_MAX_WIDTH = 1920;
const BLOG_THUMB_WIDTH = 640;
const BLOG_REVISION_KEEP = 25;

/* ------------------------------------------------------------- taxonomy */

/** Comma-separated tag names -> tag ids (creating missing tags). */
function blog_sync_tags(int $postId, string $csv): void
{
    $pdo = db();
    $names = [];
    foreach (preg_split('/[,\n]+/', $csv) ?: [] as $n) {
        $n = trim(preg_replace('/\s+/u', ' ', ltrim(trim($n), '#')) ?? '');
        if ($n !== '' && mb_strlen($n) <= 60) {
            $names[mb_strtolower($n)] = $n;
        }
    }
    $ids = [];
    foreach (array_slice($names, 0, 15) as $name) {
        $slug = blog_slugify($name, 70);
        if ($slug === '') {
            continue;
        }
        $st = $pdo->prepare('SELECT id FROM blog_tags WHERE slug = ?');
        $st->execute([$slug]);
        $id = $st->fetchColumn();
        if (!$id) {
            $pdo->prepare('INSERT INTO blog_tags (name, slug) VALUES (?, ?)')->execute([$name, $slug]);
            $id = $pdo->lastInsertId();
        }
        $ids[(int)$id] = true;
    }
    $pdo->prepare('DELETE FROM blog_post_tags WHERE post_id = ?')->execute([$postId]);
    $ins = $pdo->prepare('INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)');
    foreach (array_keys($ids) as $tid) {
        $ins->execute([$postId, $tid]);
    }
}

function blog_tag_csv(int $postId): string
{
    return implode(', ', array_column(blog_post_tags($postId), 'name'));
}

function blog_prune_tags(): void
{
    db()->exec('DELETE FROM blog_tags WHERE id NOT IN (SELECT tag_id FROM blog_post_tags)');
}

/* ------------------------------------------------------------ save post */

/**
 * Create or update a post from form input. Returns [postId, errors[]].
 * $in keys mirror the editor form fields.
 */
function blog_save_post(int $id, array $in, string $adminName): array
{
    $pdo = db();
    $errors = [];
    $clip = static fn(string $k, int $max): string => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);
    $nullable = static fn(string $v): ?string => $v === '' ? null : $v;

    $title = $clip('title', 190);
    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    $content = blog_sanitize_html((string)($in['content'] ?? ''));
    $text = blog_plain_text($content);
    $words = blog_word_count($content);

    $status = ($in['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    if ($status === 'published' && $content === '') {
        $errors[] = 'A published post needs some content.';
    }

    $existing = null;
    if ($id > 0) {
        $st = $pdo->prepare('SELECT * FROM blog_posts WHERE id = ?');
        $st->execute([$id]);
        $existing = $st->fetch() ?: null;
        if (!$existing) {
            return [0, ['Post not found.']];
        }
    }

    $canonical = $clip('canonical_url', 255);
    if ($canonical !== '' && !preg_match('#^https?://\S+$#i', $canonical)) {
        $errors[] = 'Canonical URL must start with http:// or https://';
    }
    foreach (['cover_image' => 'Cover image', 'og_image' => 'Social image'] as $k => $label) {
        $v = $clip($k, 255);
        if ($v !== '' && !preg_match('#^(/[A-Za-z0-9_./-]+|https?://\S+)$#', $v)) {
            $errors[] = "$label must be a site path like /uploads/blog/… or a full URL.";
        }
    }

    // published_at: blank + publishing = now; blank draft = null
    $publishedAt = null;
    $rawDate = trim((string)($in['published_at'] ?? ''));
    if ($rawDate !== '') {
        $ts = strtotime(str_replace('T', ' ', $rawDate));
        if ($ts === false) {
            $errors[] = 'Publish date is not valid.';
        } else {
            $publishedAt = date('Y-m-d H:i:00', $ts);
        }
    }
    if ($status === 'published' && $publishedAt === null) {
        $publishedAt = $existing['published_at'] ?? (string)$pdo->query('SELECT NOW()')->fetchColumn();
    }

    if ($errors) {
        return [$id, $errors];
    }

    // slug
    $slugIn = blog_slugify($clip('slug', 190), 150);
    $slug = blog_unique_slug('blog_posts', $slugIn !== '' ? $slugIn : blog_slugify($title, 100), $id, 'post');

    // category (existing id, or create from new_category)
    $categoryId = (int)($in['category_id'] ?? 0) ?: null;
    $newCat = $clip('new_category', 80);
    if ($newCat !== '') {
        $cslug = blog_slugify($newCat, 90);
        $st = $pdo->prepare('SELECT id FROM blog_categories WHERE slug = ?');
        $st->execute([$cslug]);
        $categoryId = (int)$st->fetchColumn() ?: null;
        if (!$categoryId && $cslug !== '') {
            $pdo->prepare('INSERT INTO blog_categories (name, slug) VALUES (?, ?)')->execute([$newCat, $cslug]);
            $categoryId = (int)$pdo->lastInsertId();
        }
    }
    if ($categoryId) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM blog_categories WHERE id = ?');
        $st->execute([$categoryId]);
        if (!(int)$st->fetchColumn()) {
            $categoryId = null;
        }
    }

    // FAQ
    $faq = [];
    $qs = (array)($in['faq_q'] ?? []);
    $as = (array)($in['faq_a'] ?? []);
    foreach ($qs as $i => $q) {
        $q = mb_substr(trim((string)$q), 0, 250);
        $a = mb_substr(trim((string)($as[$i] ?? '')), 0, 2000);
        if ($q !== '' && $a !== '') {
            $faq[] = ['q' => $q, 'a' => $a];
        }
    }

    $excerpt = $clip('excerpt', 500);
    if ($excerpt === '' && $content !== '') {
        $excerpt = blog_auto_excerpt($content, 200);
    }

    $row = [
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $nullable($excerpt),
        'content' => $content,
        'content_text' => $text,
        'cover_image' => $nullable($clip('cover_image', 255)),
        'cover_alt' => $nullable($clip('cover_alt', 190)),
        'category_id' => $categoryId,
        'author_name' => $clip('author_name', 100) ?: SITE_NAME,
        'status' => $status,
        'published_at' => $publishedAt,
        'is_featured' => empty($in['is_featured']) ? 0 : 1,
        'meta_title' => $nullable($clip('meta_title', 190)),
        'meta_description' => $nullable($clip('meta_description', 400)),
        'focus_keyword' => $nullable($clip('focus_keyword', 120)),
        'meta_keywords' => $nullable($clip('meta_keywords', 500)),
        'canonical_url' => $nullable($canonical),
        'robots' => ($in['robots'] ?? '') === 'noindex' ? 'noindex' : 'index',
        'og_image' => $nullable($clip('og_image', 255)),
        'faq' => $faq ? json_encode($faq, JSON_UNESCAPED_UNICODE) : null,
        'word_count' => $words,
        'reading_time' => blog_reading_time($words),
    ];

    $pdo->beginTransaction();
    try {
        if ($existing) {
            // Keep a revision of what is about to be overwritten.
            if ($existing['content'] !== $content || $existing['title'] !== $title || (string)$existing['excerpt'] !== $excerpt) {
                $pdo->prepare('INSERT INTO blog_revisions (post_id, title, excerpt, content, saved_by) VALUES (?,?,?,?,?)')
                    ->execute([$id, $existing['title'], $existing['excerpt'], $existing['content'], $adminName]);
                $pdo->prepare(
                    'DELETE FROM blog_revisions WHERE post_id = ? AND id NOT IN (SELECT id FROM (SELECT id FROM blog_revisions WHERE post_id = ? ORDER BY id DESC LIMIT ' . BLOG_REVISION_KEEP . ') k)'
                )->execute([$id, $id]);
            }
            if ($existing['slug'] !== $slug) {
                $pdo->prepare('DELETE FROM blog_redirects WHERE old_slug = ?')->execute([$slug]);
                $pdo->prepare('INSERT INTO blog_redirects (old_slug, post_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE post_id = VALUES(post_id)')->execute([$existing['slug'], $id]);
            }
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($row)));
            $pdo->prepare("UPDATE blog_posts SET $set WHERE id = :id")->execute($row + ['id' => $id]);
        } else {
            $cols = implode(', ', array_keys($row));
            $ph = implode(', ', array_map(fn($k) => ":$k", array_keys($row)));
            $pdo->prepare("INSERT INTO blog_posts ($cols) VALUES ($ph)")->execute($row);
            $id = (int)$pdo->lastInsertId();
        }
        blog_sync_tags($id, (string)($in['tags'] ?? ''));
        blog_prune_tags();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[blog save] ' . $e->getMessage());
        return [$id, ['Could not save the post. Please try again.']];
    }
    return [$id, []];
}

/* ------------------------------------------------------------ images */

function blog_gd_ready(): bool
{
    return function_exists('imagecreatefromstring') && function_exists('imagejpeg');
}

/**
 * Validate and store an uploaded image (resized, EXIF-stripped, thumbnail). Returns [row|null, error].
 */
function blog_store_upload(array $file, string $adminName): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = [UPLOAD_ERR_INI_SIZE => 'File is larger than the server allows.', UPLOAD_ERR_FORM_SIZE => 'File is too large.', UPLOAD_ERR_NO_FILE => 'No file selected.'];
        return [null, $msg[$file['error'] ?? 0] ?? 'Upload failed.'];
    }
    if (!is_uploaded_file($file['tmp_name']) || $file['size'] > BLOG_MAX_UPLOAD) {
        return [null, 'Image must be under ' . (BLOG_MAX_UPLOAD / 1048576) . ' MB.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($types[$mime])) {
        return [null, 'Only JPG, PNG, WebP or GIF images are allowed.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] > 12000 || $info[1] > 12000) {
        return [null, 'That file is not a valid image.'];
    }
    if (!blog_gd_ready()) {
        return [null, 'Image library (GD) is not available on this server.'];
    }

    $ext = $types[$mime];
    $base = blog_slugify(pathinfo((string)$file['name'], PATHINFO_FILENAME), 50) ?: 'image';
    $sub = date('Y/m');
    $dir = dirname(__DIR__) . BLOG_UPLOAD_DIR . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return [null, 'Upload folder is not writable.'];
    }
    $name = $base . '-' . bin2hex(random_bytes(3));
    $path = $dir . '/' . $name . '.' . $ext;
    $thumbPath = $dir . '/' . $name . '-thumb.' . $ext;

    $w = $info[0];
    $h = $info[1];
    if ($mime === 'image/gif') {
        // Keep animation: store untouched (validated above), no thumbnail resize.
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return [null, 'Could not save the image.'];
        }
        @copy($path, $thumbPath);
    } else {
        $src = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
        if (!$src) {
            return [null, 'Could not read that image.'];
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orient = (int)(@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$orient] ?? 0;
            if ($angle && ($rot = imagerotate($src, $angle, 0))) {
                $src = $rot;
            }
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $writeImage = static function ($img, string $to) use ($mime): bool {
            if ($mime === 'image/png') {
                imagesavealpha($img, true);
                return imagepng($img, $to, 6);
            }
            if ($mime === 'image/webp' && function_exists('imagewebp')) {
                return imagewebp($img, $to, 84);
            }
            if ($mime === 'image/webp') {
                return false;
            }
            return imagejpeg($img, $to, 85);
        };
        $resize = static function ($img, int $maxW) use ($mime) {
            $ow = imagesx($img);
            $oh = imagesy($img);
            if ($ow <= $maxW) {
                return $img;
            }
            $nh = max(1, (int)round($oh * $maxW / $ow));
            $dst = imagecreatetruecolor($maxW, $nh);
            if ($mime !== 'image/jpeg') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            }
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $maxW, $nh, $ow, $oh);
            return $dst;
        };
        $main = $resize($src, BLOG_MAX_WIDTH);
        if (!$writeImage($main, $path)) {
            return [null, 'Could not save the image (WebP not supported by this server?).'];
        }
        $w = imagesx($main);
        $h = imagesy($main);
        $writeImage($resize($main, BLOG_THUMB_WIDTH), $thumbPath);
    }

    $rel = BLOG_UPLOAD_DIR . '/' . $sub . '/' . $name . '.' . $ext;
    $thumbRel = is_file($thumbPath) ? BLOG_UPLOAD_DIR . '/' . $sub . '/' . $name . '-thumb.' . $ext : null;
    $orig = mb_substr(preg_replace('/[^\p{L}\p{N} ._()-]/u', '', (string)$file['name']) ?? '', 0, 190);
    $alt = ucfirst(str_replace('-', ' ', $base));
    db()->prepare('INSERT INTO blog_media (path, thumb_path, original, alt, mime, width, height, size_bytes, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$rel, $thumbRel, $orig, $alt, $mime, $w, $h, (int)filesize($path), $adminName]);
    $row = db()->query('SELECT * FROM blog_media WHERE id = ' . (int)db()->lastInsertId())->fetch();
    return [$row, ''];
}

/** How many posts reference an uploaded file (cover, social image or inline). */
function blog_media_usage(string $path): int
{
    $like = '%' . addcslashes($path, '%_\\') . '%';
    $st = db()->prepare('SELECT COUNT(*) FROM blog_posts WHERE status <> \'trash\' AND (cover_image = ? OR og_image = ? OR content LIKE ?)');
    $st->execute([$path, $path, $like]);
    return (int)$st->fetchColumn();
}

function blog_media_delete(int $id): string
{
    $st = db()->prepare('SELECT * FROM blog_media WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) {
        return 'Image not found.';
    }
    if (blog_media_usage($m['path']) > 0) {
        return 'This image is used in a post. Remove it from the post first.';
    }
    foreach ([$m['path'], $m['thumb_path']] as $rel) {
        $file = dirname(__DIR__) . (string)$rel;
        if ($rel && str_starts_with((string)$rel, BLOG_UPLOAD_DIR . '/') && is_file($file)) {
            @unlink($file);
        }
    }
    db()->prepare('DELETE FROM blog_media WHERE id = ?')->execute([$id]);
    return '';
}

/* ------------------------------------------------------------- misc */

/** Display state of a post row (needs is_future from SQL). */
function blog_state(array $p): string
{
    if ($p['status'] === 'trash') {
        return 'trash';
    }
    if ($p['status'] === 'draft') {
        return 'draft';
    }
    return !empty($p['is_future']) ? 'scheduled' : 'published';
}

const BLOG_STATE_LABELS = ['published' => 'Published', 'scheduled' => 'Scheduled', 'draft' => 'Draft', 'trash' => 'Trash'];

function blog_admin_nav(string $active): void
{
    $items = ['posts' => ['/admin/blog', 'All posts'], 'new' => ['/admin/blog-edit', '+ New post'], 'tax' => ['/admin/blog-taxonomy', 'Categories & tags'], 'media' => ['/admin/media', 'Media library']];
    echo '<nav class="subnav" aria-label="Blog sections">';
    foreach ($items as $k => [$href, $label]) {
        echo '<a class="' . ($k === $active ? 'on' : '') . '" href="' . e($href) . '">' . e($label) . '</a>';
    }
    echo '<a href="/blog" target="_blank" rel="noopener" class="ext">View blog ↗</a></nav>';
}
