<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/blog_admin.php';
require_admin();
$pdo = db();
$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $a = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $clip = fn(string $k, int $n) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n);

    if ($a === 'cat_save') {
        $name = $clip('name', 80);
        if ($name === '') {
            $err = 'Category name is required.';
        } else {
            $slug = blog_unique_slug('blog_categories', blog_slugify($clip('slug', 90) ?: $name, 90), $id, 'category');
            $vals = [$name, $slug, $clip('description', 500) ?: null, $clip('meta_title', 190) ?: null, $clip('meta_description', 400) ?: null, (int)($_POST['sort_order'] ?? 0)];
            if ($id) {
                $pdo->prepare('UPDATE blog_categories SET name=?, slug=?, description=?, meta_title=?, meta_description=?, sort_order=? WHERE id=?')->execute([...$vals, $id]);
            } else {
                $pdo->prepare('INSERT INTO blog_categories (name, slug, description, meta_title, meta_description, sort_order) VALUES (?,?,?,?,?,?)')->execute($vals);
            }
            header('Location: /admin/blog-taxonomy?ok=cat');
            exit;
        }
    } elseif ($a === 'cat_delete' && $id) {
        $pdo->prepare('DELETE FROM blog_categories WHERE id = ?')->execute([$id]);
        $pdo->exec('UPDATE blog_posts SET category_id = NULL WHERE category_id IS NOT NULL AND category_id NOT IN (SELECT id FROM blog_categories)');
        header('Location: /admin/blog-taxonomy?ok=catdel');
        exit;
    } elseif ($a === 'tag_rename' && $id) {
        $name = ltrim($clip('name', 60), '#');
        $slug = blog_slugify($name, 70);
        if ($slug !== '') {
            $st = $pdo->prepare('SELECT id FROM blog_tags WHERE slug = ? AND id <> ?');
            $st->execute([$slug, $id]);
            if ($other = (int)$st->fetchColumn()) {   // same tag already exists: merge into it
                $pdo->prepare('INSERT IGNORE INTO blog_post_tags (post_id, tag_id) SELECT post_id, ? FROM blog_post_tags WHERE tag_id = ?')->execute([$other, $id]);
                $pdo->prepare('DELETE FROM blog_tags WHERE id = ?')->execute([$id]);
                header('Location: /admin/blog-taxonomy?ok=merged');
                exit;
            }
            $pdo->prepare('UPDATE blog_tags SET name = ?, slug = ? WHERE id = ?')->execute([$name, $slug, $id]);
        }
        header('Location: /admin/blog-taxonomy?ok=tag');
        exit;
    } elseif ($a === 'tag_delete' && $id) {
        $pdo->prepare('DELETE FROM blog_tags WHERE id = ?')->execute([$id]);
        header('Location: /admin/blog-taxonomy?ok=tagdel');
        exit;
    }
}

$cats = blog_categories();
$tags = blog_tags_all();
$edit = null;
if (isset($_GET['edit'])) {
    foreach ($cats as $c) { if ((int)$c['id'] === (int)$_GET['edit']) { $edit = $c; } }
}
$okMsg = ['cat' => 'Category saved.', 'catdel' => 'Category deleted (its posts are now uncategorised).', 'tag' => 'Tag renamed.', 'tagdel' => 'Tag deleted.', 'merged' => 'Tags merged.'][$_GET['ok'] ?? ''] ?? '';

admin_head('Categories & tags');
?>
<div class="page-head"><div><h1>Categories &amp; tags</h1><p class="muted">Categories group posts into archive pages (<code>/blog/category/…</code>); tags are finer topics. Tags with 3+ posts are indexed by Google.</p></div></div>
<?php blog_admin_nav('tax'); ?>
<?php if ($okMsg): ?><div class="alert ok"><?= e($okMsg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<div class="grid-2 even">
  <section class="card">
    <div class="card-title"><?= $edit ? 'Edit category' : 'Add category' ?></div>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="cat_save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <label>Name<input name="name" required maxlength="80" value="<?= e($edit['name'] ?? '') ?>"></label>
      <label>Slug <small class="muted">(auto from name)</small><input name="slug" maxlength="90" value="<?= e($edit['slug'] ?? '') ?>"></label>
      <label>Description <small class="muted">Shown at the top of the category page</small><textarea name="description" rows="2" maxlength="500"><?= e($edit['description'] ?? '') ?></textarea></label>
      <label>SEO title<input name="meta_title" maxlength="190" value="<?= e($edit['meta_title'] ?? '') ?>"></label>
      <label>Meta description<textarea name="meta_description" rows="2" maxlength="400"><?= e($edit['meta_description'] ?? '') ?></textarea></label>
      <label>Sort order<input type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>"></label>
      <div class="row-actions"><button class="btn primary" type="submit"><?= $edit ? 'Save category' : 'Add category' ?></button><?php if ($edit): ?><a class="btn ghost" href="/admin/blog-taxonomy">Cancel</a><?php endif; ?></div>
    </form>
  </section>

  <section class="card">
    <div class="card-title">Categories</div>
    <?php if (!$cats): ?><p class="muted">No categories yet.</p><?php else: ?>
    <table><thead><tr><th>Name</th><th class="num">Posts</th><th></th></tr></thead><tbody>
    <?php foreach ($cats as $c): ?>
      <tr><td><a class="name" href="?edit=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a><div class="sub">/blog/category/<?= e($c['slug']) ?></div></td>
      <td class="num"><?= (int)$c['post_count'] ?></td>
      <td class="actions"><a class="btn ghost sm" href="?edit=<?= (int)$c['id'] ?>">Edit</a>
        <form method="post" class="inline" onsubmit="return confirm('Delete this category? Posts become uncategorised.')"><?= csrf_field() ?><input type="hidden" name="action" value="cat_delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn danger sm">Delete</button></form></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <div class="card-title">Tags <small class="muted">Rename a tag to an existing name to merge them.</small></div>
  <?php if (!$tags): ?><p class="muted">Tags are created when you add them to a post.</p><?php else: ?>
  <table><thead><tr><th>Tag</th><th class="num">Posts</th><th></th></tr></thead><tbody>
  <?php foreach ($tags as $t): ?>
    <tr><td><form method="post" class="inline tag-edit"><?= csrf_field() ?><input type="hidden" name="action" value="tag_rename"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <input name="name" value="<?= e($t['name']) ?>" maxlength="60" aria-label="Tag name"><button class="btn ghost sm">Rename</button></form><div class="sub">/blog/tag/<?= e($t['slug']) ?></div></td>
    <td class="num"><?= (int)$t['post_count'] ?></td>
    <td class="actions"><form method="post" class="inline" onsubmit="return confirm('Delete this tag?')"><?= csrf_field() ?><input type="hidden" name="action" value="tag_delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="btn danger sm">Delete</button></form></td></tr>
  <?php endforeach; ?></tbody></table>
  <?php endif; ?>
</section>
<link rel="stylesheet" href="/admin/assets/blog-editor.css?v=<?= (int)@filemtime(__DIR__ . '/assets/blog-editor.css') ?>">
<?php admin_foot();
