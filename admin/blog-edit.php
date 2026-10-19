<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/blog_admin.php';
require_once __DIR__ . '/../includes/seo.php';
$user = require_admin();
$pdo  = db();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$post = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT p.*, (p.published_at > NOW()) AS is_future FROM blog_posts p WHERE p.id = ?');
    $st->execute([$id]);
    $post = $st->fetch() ?: null;
    if (!$post) {
        http_response_code(404);
        admin_head('Not found');
        echo '<div class="card"><p>Post not found. <a href="/admin/blog">Back to posts</a></p></div>';
        admin_foot();
        exit;
    }
}

/** Form-shaped array from a stored post (used for the form and for revision restore). */
function post_to_input(array $p): array
{
    $faq = json_decode((string)$p['faq'], true) ?: [];
    return [
        'title' => $p['title'], 'slug' => $p['slug'], 'content' => $p['content'], 'excerpt' => $p['excerpt'], 'cover_image' => $p['cover_image'],
        'cover_alt' => $p['cover_alt'], 'category_id' => $p['category_id'], 'author_name' => $p['author_name'], 'status' => $p['status'] === 'trash' ? 'draft' : $p['status'],
        'published_at' => $p['published_at'] ? date('Y-m-d\TH:i', strtotime($p['published_at'])) : '', 'is_featured' => $p['is_featured'],
        'meta_title' => $p['meta_title'], 'meta_description' => $p['meta_description'], 'focus_keyword' => $p['focus_keyword'], 'meta_keywords' => $p['meta_keywords'],
        'canonical_url' => $p['canonical_url'], 'robots' => $p['robots'], 'og_image' => $p['og_image'], 'tags' => blog_tag_csv((int)$p['id']),
        'faq_q' => array_column($faq, 'q'), 'faq_a' => array_column($faq, 'a'),
    ];
}

$errors = [];
$wantsJson = ($_POST['ajax'] ?? '') === '1';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($wantsJson && !hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        json_response(['ok' => false, 'errors' => ['Session expired — reload the page and try again.']], 419);
    }
    csrf_check();
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'restore_rev' && $post) {
        $st = $pdo->prepare('SELECT * FROM blog_revisions WHERE id = ? AND post_id = ?');
        $st->execute([(int)($_POST['rev_id'] ?? 0), $id]);
        if ($rev = $st->fetch()) {
            $in = post_to_input($post);
            $in['title'] = $rev['title'];
            $in['content'] = $rev['content'];
            $in['excerpt'] = $rev['excerpt'];
            [, $errors] = blog_save_post($id, $in, $user['name']);
            if (!$errors) {
                header('Location: /admin/blog-edit?id=' . $id . '&restored=1');
                exit;
            }
        }
    } elseif ($action === 'trash' && $post) {
        $pdo->prepare("UPDATE blog_posts SET status = 'trash', is_featured = 0 WHERE id = ?")->execute([$id]);
        header('Location: /admin/blog?done=trashed&n=1');
        exit;
    } else {
        [$newId, $errors] = blog_save_post($id, $_POST, $user['name']);
        if ($wantsJson) {
            if ($errors) {
                json_response(['ok' => false, 'errors' => $errors], 422);
            }
            $st = $pdo->prepare('SELECT slug, status, updated_at, published_at, word_count, reading_time, (published_at > NOW()) AS is_future FROM blog_posts WHERE id = ?');
            $st->execute([$newId]);
            $row = $st->fetch();
            json_response([
                'ok' => true, 'id' => $newId, 'slug' => $row['slug'], 'status' => $row['status'], 'state' => blog_state($row),
                'updated' => date('h:i:s A', strtotime($row['updated_at'])),
                'published_at' => $row['published_at'] ? date('Y-m-d\TH:i', strtotime($row['published_at'])) : '',
                'words' => (int)$row['word_count'], 'reading' => (int)$row['reading_time'],
            ]);
        }
        if (!$errors) {
            header('Location: /admin/blog-edit?id=' . $newId . '&saved=1');
            exit;
        }
        $id = $newId;
    }
}

$v = $errors ? $_POST : ($post ? post_to_input($post) : [
    'title' => '', 'slug' => '', 'content' => '', 'excerpt' => '', 'cover_image' => '', 'cover_alt' => '', 'category_id' => '', 'author_name' => SITE_NAME,
    'status' => 'draft', 'published_at' => '', 'is_featured' => 0, 'meta_title' => '', 'meta_description' => '', 'focus_keyword' => '', 'meta_keywords' => '',
    'canonical_url' => '', 'robots' => 'index', 'og_image' => '', 'tags' => '', 'faq_q' => [], 'faq_a' => [],
]);
$f = static fn(string $k): string => (string)($v[$k] ?? '');
$faqRows = [];
foreach ((array)($v['faq_q'] ?? []) as $i => $q) {
    $faqRows[] = [(string)$q, (string)(((array)($v['faq_a'] ?? []))[$i] ?? '')];
}
$cats = blog_categories(false);
$allTags = array_column(blog_tags_all(), 'name');
$revs = $post ? $pdo->query('SELECT id, title, saved_by, created_at, CHAR_LENGTH(content) len FROM blog_revisions WHERE post_id = ' . (int)$post['id'] . ' ORDER BY id DESC LIMIT 12')->fetchAll() : [];
$state = $post ? blog_state($post) : 'draft';
$isNew = !$post;

admin_head($isNew ? 'New post' : 'Edit post');
?>
<link rel="stylesheet" href="/admin/assets/blog-editor.css?v=<?= (int)@filemtime(__DIR__ . '/assets/blog-editor.css') ?>">
<div class="page-head">
  <div>
    <a class="muted" href="/admin/blog">← All posts</a>
    <h1><?= $isNew ? 'New post' : 'Edit post' ?> <?php if (!$isNew): ?><span class="pill st-<?= $state ?>"><?= e(BLOG_STATE_LABELS[$state]) ?></span><?php endif; ?></h1>
  </div>
  <div class="row-actions">
    <span class="savestate" id="savestate" aria-live="polite"></span>
    <a class="btn ghost" id="btn-preview" target="_blank" rel="noopener" href="<?= $post ? '/blog/' . e($post['slug']) . '?preview=1' : '#' ?>" <?= $post ? '' : 'hidden' ?>>Preview ↗</a>
  </div>
</div>
<?php if (isset($_GET['saved'])): ?><div class="alert ok">Saved.</div><?php endif; ?>
<?php if (isset($_GET['restored'])): ?><div class="alert ok">Revision restored.</div><?php endif; ?>
<?php if (isset($_GET['duplicated'])): ?><div class="alert ok">Duplicated as a draft. Edit the title and slug, then publish.</div><?php endif; ?>
<?php if ($errors): ?><div class="alert err"><strong>Could not save:</strong><ul class="errs"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="alert err" id="js-errors" hidden></div>

<form method="post" id="post-form" autocomplete="off" data-id="<?= (int)$id ?>" data-new="<?= $isNew ? '1' : '0' ?>"
      data-site="<?= e(SITE_URL) ?>" data-live="<?= $state === 'published' ? '1' : '0' ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" id="f-id" value="<?= (int)$id ?>">
  <textarea name="content" id="f-content" hidden><?= e(blog_sanitize_html($f('content'))) ?></textarea>

  <div class="editor-grid">
    <div class="col-main">
      <section class="card">
        <input class="title-input" name="title" id="f-title" maxlength="190" placeholder="Post title" value="<?= e($f('title')) ?>" required>
        <div class="permalink">
          <span><?= e(SITE_URL) ?>/blog/</span><input name="slug" id="f-slug" maxlength="150" value="<?= e($f('slug')) ?>" placeholder="auto-generated-from-title" aria-label="URL slug">
        </div>

        <div class="toolbar" id="toolbar" role="toolbar" aria-label="Formatting">
          <select data-cmd="block" aria-label="Paragraph style">
            <option value="p">Paragraph</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option><option value="h4">Heading 4</option><option value="blockquote">Quote</option>
          </select>
          <span class="sep"></span>
          <button type="button" data-cmd="bold" title="Bold (Ctrl+B)"><b>B</b></button>
          <button type="button" data-cmd="italic" title="Italic (Ctrl+I)"><i>I</i></button>
          <button type="button" data-cmd="underline" title="Underline (Ctrl+U)"><u>U</u></button>
          <button type="button" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
          <span class="sep"></span>
          <button type="button" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
          <button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
          <span class="sep"></span>
          <button type="button" data-cmd="link" title="Insert link (Ctrl+K)">🔗 Link</button>
          <button type="button" data-cmd="unlink" title="Remove link">Unlink</button>
          <span class="sep"></span>
          <button type="button" data-cmd="image" title="Insert image">🖼 Image</button>
          <button type="button" data-cmd="video" title="Embed YouTube video">▶ Video</button>
          <button type="button" data-cmd="table" title="Insert table">▦ Table</button>
          <button type="button" data-cmd="callout" title="Highlighted note">💡 Note</button>
          <button type="button" data-cmd="button" title="Call-to-action button">⬭ Button</button>
          <button type="button" data-cmd="hr" title="Divider">― Rule</button>
          <span class="sep"></span>
          <button type="button" data-cmd="removeFormat" title="Clear formatting">Tx</button>
          <button type="button" data-cmd="undo" title="Undo">↶</button>
          <button type="button" data-cmd="redo" title="Redo">↷</button>
          <span class="grow"></span>
          <button type="button" data-cmd="source" title="Edit HTML" id="btn-source">&lt;/&gt;</button>
          <button type="button" data-cmd="fullscreen" title="Distraction-free mode" id="btn-full">⛶</button>
        </div>
        <div class="imgbar" id="imgbar" hidden>
          <label>Alt text <input type="text" id="img-alt" placeholder="Describe the image for search engines & screen readers" maxlength="190"></label>
          <button type="button" class="btn ghost sm" id="img-caption">Add caption</button>
          <button type="button" class="btn danger sm" id="img-remove">Remove image</button>
        </div>
        <div id="editor" class="editor bl-content" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Post content" data-placeholder="Start writing… Use the toolbar for headings, images and lists."></div>
        <textarea id="source" class="source code" spellcheck="false" hidden></textarea>
        <div class="editor-foot"><span id="wc">0 words</span> · <span id="rt">1 min read</span></div>
      </section>

      <section class="card">
        <div class="card-title">Excerpt <small class="muted">Shown on cards and in the intro. Leave blank to auto-generate.</small></div>
        <textarea name="excerpt" id="f-excerpt" rows="3" maxlength="500"><?= e($f('excerpt')) ?></textarea>
      </section>

      <section class="card">
        <div class="card-title">FAQ <small class="muted">Adds an FAQ section and FAQPage structured data (rich results).</small></div>
        <div id="faq-list">
          <?php foreach ($faqRows as [$q, $a]): ?>
          <div class="faq-row">
            <input name="faq_q[]" maxlength="250" placeholder="Question" value="<?= e($q) ?>">
            <textarea name="faq_a[]" rows="2" maxlength="2000" placeholder="Answer"><?= e($a) ?></textarea>
            <button type="button" class="btn danger sm faq-del" aria-label="Remove question">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn ghost sm" id="faq-add">+ Add question</button>
      </section>

      <section class="card seo-card">
        <div class="card-title">SEO &amp; social <span class="score" id="seo-score" title="Live SEO score">–</span></div>
        <div class="serp" id="serp" aria-label="Google preview">
          <div class="serp-url" id="serp-url"></div>
          <div class="serp-title" id="serp-title"></div>
          <div class="serp-desc" id="serp-desc"></div>
        </div>
        <label>Focus keyword <small class="muted">The main phrase this post should rank for</small>
          <input name="focus_keyword" id="f-kw" maxlength="120" value="<?= e($f('focus_keyword')) ?>" placeholder="e.g. places to visit in devprayag"></label>
        <label>SEO title <span class="cnt" data-for="f-mtitle" data-min="30" data-max="60"></span>
          <input name="meta_title" id="f-mtitle" maxlength="190" value="<?= e($f('meta_title')) ?>" placeholder="Defaults to: post title | The Sangam House"></label>
        <label>Meta description <span class="cnt" data-for="f-mdesc" data-min="100" data-max="160"></span>
          <textarea name="meta_description" id="f-mdesc" rows="3" maxlength="400" placeholder="Defaults to the excerpt"><?= e($f('meta_description')) ?></textarea></label>
        <div class="two">
          <label>Extra keywords <small class="muted">(comma separated)</small><input name="meta_keywords" maxlength="500" value="<?= e($f('meta_keywords')) ?>"></label>
          <label>Search engines
            <select name="robots"><option value="index" <?= $f('robots') !== 'noindex' ? 'selected' : '' ?>>Index this post</option><option value="noindex" <?= $f('robots') === 'noindex' ? 'selected' : '' ?>>Hide from Google (noindex)</option></select></label>
        </div>
        <label>Canonical URL <small class="muted">Only if this content exists elsewhere</small>
          <input name="canonical_url" maxlength="255" value="<?= e($f('canonical_url')) ?>" placeholder="https://…"></label>
        <label>Social share image <small class="muted">Defaults to the cover image (1200×630 is ideal)</small></label>
        <div class="img-pick" data-target="f-og" data-preview="og-prev">
          <input name="og_image" id="f-og" maxlength="255" value="<?= e($f('og_image')) ?>" placeholder="/uploads/blog/…">
          <button type="button" class="btn ghost sm pick-btn">Choose</button>
        </div>
        <ul class="checks" id="seo-checks" aria-label="SEO checklist"></ul>
      </section>
    </div>

    <aside class="col-side">
      <section class="card">
        <div class="card-title">Publish</div>
        <label>Status
          <select name="status" id="f-status">
            <option value="draft" <?= $f('status') !== 'published' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= $f('status') === 'published' ? 'selected' : '' ?>>Published</option>
          </select></label>
        <label>Publish date &amp; time <small class="muted">Future date = scheduled</small>
          <input type="datetime-local" name="published_at" id="f-date" value="<?= e($f('published_at')) ?>"></label>
        <p class="hint" id="date-hint"></p>
        <label class="check"><input type="checkbox" name="is_featured" value="1" <?= !empty($v['is_featured']) ? 'checked' : '' ?>> Featured post <small class="muted">(big card on top of /blog)</small></label>
        <label>Author name<input name="author_name" maxlength="100" value="<?= e($f('author_name')) ?>"></label>
        <div class="publish-actions">
          <button class="btn primary block" type="submit" id="btn-save"><?= $state === 'published' || $state === 'scheduled' ? 'Update post' : 'Save draft' ?></button>
          <button class="btn ghost block" type="button" id="btn-publish" <?= $state === 'published' || $state === 'scheduled' ? 'hidden' : '' ?>>Publish now</button>
        </div>
        <p class="hint">Tip: press <kbd>Ctrl</kbd>/<kbd>⌘</kbd> + <kbd>S</kbd> to save without leaving the page.</p>
        <?php if (!$isNew): ?>
          <div class="danger-zone">
            <button class="btn danger sm" type="submit" name="action" value="trash" formnovalidate onclick="return confirm('Move this post to trash?')">Move to trash</button>
          </div>
        <?php endif; ?>
      </section>

      <section class="card">
        <div class="card-title">Category</div>
        <select name="category_id" id="f-cat" aria-label="Category">
          <option value="">— Uncategorised —</option>
          <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$f('category_id') === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <input name="new_category" id="f-newcat" maxlength="80" placeholder="…or create a new category" style="margin-top:8px">
      </section>

      <section class="card">
        <div class="card-title">Tags</div>
        <div class="tag-input" id="tag-box">
          <input type="text" id="tag-text" list="tag-suggest" placeholder="Type a tag, press Enter" maxlength="60" aria-label="Add tag">
        </div>
        <datalist id="tag-suggest"><?php foreach ($allTags as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
        <input type="hidden" name="tags" id="f-tags" value="<?= e($f('tags')) ?>">
      </section>

      <section class="card">
        <div class="card-title">Cover image</div>
        <div class="cover-prev" id="cover-prev"><span>No image</span></div>
        <div class="img-pick" data-target="f-cover" data-preview="cover-prev" data-alt="f-cover-alt">
          <input name="cover_image" id="f-cover" maxlength="255" value="<?= e($f('cover_image')) ?>" placeholder="/uploads/blog/…">
          <button type="button" class="btn ghost sm pick-btn">Choose</button>
        </div>
        <label>Alt text<input name="cover_alt" id="f-cover-alt" maxlength="190" value="<?= e($f('cover_alt')) ?>" placeholder="Describe the image"></label>
        <button type="button" class="btn ghost sm" id="cover-clear">Remove cover</button>
      </section>

      <?php if ($revs): ?>
      <section class="card">
        <div class="card-title">Revisions <small class="muted">Earlier saved versions</small></div>
        <ul class="revs">
          <?php foreach ($revs as $r): ?>
            <li>
              <div><strong><?= e(blog_date($r['created_at'], 'd M, h:i A')) ?></strong><div class="sub"><?= e($r['saved_by'] ?: 'admin') ?> · <?= number_format((int)$r['len']) ?> chars</div></div>
              <button class="btn ghost sm" type="submit" name="action" value="restore_rev" formnovalidate
                      onclick="document.getElementById('rev-id').value=<?= (int)$r['id'] ?>;return confirm('Restore this version? The current text is kept as a revision.')">Restore</button>
            </li>
          <?php endforeach; ?>
        </ul>
        <input type="hidden" name="rev_id" id="rev-id" value="">
      </section>
      <?php endif; ?>
    </aside>
  </div>
</form>

<!-- link / url dialog -->
<dialog id="ask-dlg" class="dlg">
  <form method="dialog" id="ask-form">
    <h3 id="ask-title">Insert link</h3>
    <label>URL <input type="text" id="ask-url" placeholder="https://…  or  /contact" autocomplete="off"></label>
    <label id="ask-label-wrap" hidden>Button text <input type="text" id="ask-label" maxlength="60" autocomplete="off"></label>
    <div class="dlg-actions"><button class="btn ghost" value="cancel" formnovalidate>Cancel</button><button class="btn primary" value="ok" id="ask-ok">Insert</button></div>
  </form>
</dialog>

<!-- media picker -->
<dialog id="media-dlg" class="dlg dlg-wide">
  <div class="dlg-head"><h3>Media library</h3><button type="button" class="btn ghost sm" id="media-close">Close</button></div>
  <div class="dropzone" id="media-drop">
    <input type="file" id="media-file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
    <strong>Drop images here</strong> or <button type="button" class="btn ghost sm" id="media-browse">browse</button>
    <span class="muted"> · JPG, PNG, WebP, GIF · max 8 MB · auto-resized</span>
    <div class="muted" id="media-status"></div>
  </div>
  <div class="media-grid" id="media-grid"></div>
  <div class="dlg-actions"><button type="button" class="btn ghost sm" id="media-more" hidden>Load more</button></div>
</dialog>

<script>
window.BLOG_EDITOR = {
  csrf: <?= json_encode(csrf_token()) ?>,
  mediaUrl: '/admin/media',
  postUrl: '/admin/blog-edit',
  siteUrl: <?= json_encode(SITE_URL) ?>,
  siteName: <?= json_encode(SITE_NAME) ?>
};
</script>
<script src="/admin/assets/blog-editor.js?v=<?= (int)@filemtime(__DIR__ . '/assets/blog-editor.js') ?>"></script>
<?php admin_foot();
