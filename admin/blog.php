<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/blog_admin.php';
$user = require_admin();
$pdo  = db();

// Tables missing (migration not run yet): say so instead of a 500.
try {
    $pdo->query('SELECT 1 FROM blog_posts LIMIT 1');
} catch (PDOException $e) {
    admin_head('Blog');
    echo '<div class="card"><h1>Blog tables not found</h1><p class="muted">Run <code>php sql/create_admin.php</code> or <code>php sql/migrate_blog.php</code> on the server to create the blog tables, then reload.</p></div>';
    admin_foot();
    exit;
}

/* ---------------------------------------------------------- actions (POST) */
$flash = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($i) => $i > 0));
    if (!empty($_POST['id'])) {
        $ids = [(int)$_POST['id']];
    }
    $back = (string)($_POST['back'] ?? '/admin/blog');
    if (!str_starts_with($back, '/admin/blog')) {
        $back = '/admin/blog';
    }

    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $n = count($ids);
        switch ($action) {
            case 'publish':
                $pdo->prepare("UPDATE blog_posts SET status = 'published', published_at = COALESCE(published_at, NOW()) WHERE id IN ($in) AND content <> ''")->execute($ids);
                $flash = 'published';
                break;
            case 'draft':
                $pdo->prepare("UPDATE blog_posts SET status = 'draft' WHERE id IN ($in)")->execute($ids);
                $flash = 'drafted';
                break;
            case 'trash':
                $pdo->prepare("UPDATE blog_posts SET status = 'trash', is_featured = 0 WHERE id IN ($in)")->execute($ids);
                $flash = 'trashed';
                break;
            case 'restore':
                $pdo->prepare("UPDATE blog_posts SET status = 'draft' WHERE id IN ($in) AND status = 'trash'")->execute($ids);
                $flash = 'restored';
                break;
            case 'delete':
                $pdo->prepare("DELETE FROM blog_posts WHERE id IN ($in) AND status = 'trash'")->execute($ids);
                blog_prune_tags();
                $flash = 'deleted';
                break;
            case 'feature':
                $pdo->prepare("UPDATE blog_posts SET is_featured = 1 WHERE id IN ($in) AND status = 'published'")->execute($ids);
                $flash = 'featured';
                break;
            case 'unfeature':
                $pdo->prepare("UPDATE blog_posts SET is_featured = 0 WHERE id IN ($in)")->execute($ids);
                $flash = 'unfeatured';
                break;
            case 'category':
                $cat = (int)($_POST['category_id'] ?? 0) ?: null;
                $pdo->prepare("UPDATE blog_posts SET category_id = ? WHERE id IN ($in)")->execute(array_merge([$cat], $ids));
                $flash = 'moved';
                break;
            case 'duplicate':
                $st = $pdo->prepare('SELECT * FROM blog_posts WHERE id = ?');
                $st->execute([$ids[0]]);
                if ($src = $st->fetch()) {
                    $new = $src;
                    unset($new['id'], $new['created_at'], $new['updated_at']);
                    $new['title'] = mb_substr($src['title'], 0, 180) . ' (copy)';
                    $new['slug'] = blog_unique_slug('blog_posts', blog_slugify($new['title'], 120));
                    $new['status'] = 'draft';
                    $new['published_at'] = null;
                    $new['is_featured'] = 0;
                    $new['views'] = 0;
                    $pdo->prepare('INSERT INTO blog_posts (' . implode(',', array_keys($new)) . ') VALUES (' . implode(',', array_map(fn($k) => ":$k", array_keys($new))) . ')')->execute($new);
                    $newId = (int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO blog_post_tags (post_id, tag_id) SELECT ?, tag_id FROM blog_post_tags WHERE post_id = ?')->execute([$newId, $ids[0]]);
                    header('Location: /admin/blog-edit?id=' . $newId . '&duplicated=1');
                    exit;
                }
                break;
        }
    }
    header('Location: ' . $back . (str_contains($back, '?') ? '&' : '?') . 'done=' . $flash . '&n=' . count($ids));
    exit;
}

/* ----------------------------------------------------------------- filters */
$tab   = in_array($_GET['status'] ?? '', ['published', 'scheduled', 'draft', 'trash'], true) ? $_GET['status'] : '';
$q     = trim((string)($_GET['q'] ?? ''));
$catId = (int)($_GET['cat'] ?? 0);
$sort  = in_array($_GET['sort'] ?? '', ['new', 'old', 'views', 'title', 'updated'], true) ? $_GET['sort'] : 'new';

$where = [];
$args  = [];
if ($tab === '') {
    $where[] = "p.status <> 'trash'";
} elseif ($tab === 'published') {
    $where[] = "p.status = 'published' AND p.published_at <= NOW()";
} elseif ($tab === 'scheduled') {
    $where[] = "p.status = 'published' AND p.published_at > NOW()";
} else {
    $where[] = 'p.status = ?';
    $args[] = $tab;
}
if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.slug LIKE ? OR p.focus_keyword LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($args, $like, $like, $like);
}
if ($catId) {
    $where[] = 'p.category_id = ?';
    $args[] = $catId;
}
$sqlWhere = 'WHERE ' . implode(' AND ', $where);
$order = ['new' => 'COALESCE(p.published_at, p.created_at) DESC', 'old' => 'COALESCE(p.published_at, p.created_at) ASC', 'views' => 'p.views DESC',
          'title' => 'p.title ASC', 'updated' => 'p.updated_at DESC'][$sort];

$perPage = 20;
$total = $pdo->prepare("SELECT COUNT(*) FROM blog_posts p $sqlWhere");
$total->execute($args);
$total = (int)$total->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);

$list = $pdo->prepare(
    "SELECT p.id, p.title, p.slug, p.status, p.published_at, p.updated_at, p.views, p.is_featured, p.word_count, p.reading_time, p.cover_image, p.robots,
            p.meta_description, p.focus_keyword, (p.published_at > NOW()) AS is_future, c.name AS category_name
     FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id
     $sqlWhere ORDER BY $order, p.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage)
);
$list->execute($args);
$rows = $list->fetchAll();

/* ------------------------------------------------------------------ stats */
$stats = $pdo->query(
    "SELECT SUM(status <> 'trash') total,
            SUM(status = 'published' AND published_at <= NOW()) live,
            SUM(status = 'published' AND published_at > NOW()) scheduled,
            SUM(status = 'draft') drafts,
            SUM(status = 'trash') trash,
            COALESCE(SUM(views), 0) views
     FROM blog_posts"
)->fetch();
$views7 = (int)$pdo->query('SELECT COALESCE(SUM(views), 0) FROM blog_views_daily WHERE day >= (CURDATE() - INTERVAL 6 DAY)')->fetchColumn();

$series = [];
foreach ($pdo->query('SELECT day, SUM(views) v FROM blog_views_daily WHERE day >= (CURDATE() - INTERVAL 13 DAY) GROUP BY day')->fetchAll() as $r) {
    $series[$r['day']] = (int)$r['v'];
}
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $days[$d] = $series[$d] ?? 0;
}
$max = max(1, max($days));
$top = $pdo->query("SELECT id, title, slug, views FROM blog_posts p WHERE status = 'published' AND views > 0 ORDER BY views DESC LIMIT 5")->fetchAll();
$cats = blog_categories(false);

$qs = static function (array $over = []) use ($tab, $q, $catId, $sort): string {
    $p = array_filter(array_merge(['status' => $tab, 'q' => $q, 'cat' => $catId ?: '', 'sort' => $sort === 'new' ? '' : $sort], $over), fn($v) => $v !== '' && $v !== null);
    return $p ? '?' . http_build_query($p) : '';
};
$back = '/admin/blog' . $qs(['page' => $page > 1 ? $page : null]);

$done = (string)($_GET['done'] ?? '');
$doneMsg = ['published' => 'published', 'drafted' => 'moved to drafts', 'trashed' => 'moved to trash', 'restored' => 'restored as draft', 'deleted' => 'deleted permanently',
            'featured' => 'marked as featured', 'unfeatured' => 'removed from featured', 'moved' => 'moved to the chosen category'][$done] ?? '';

admin_head('Blog');
?>
<div class="page-head">
  <div>
    <h1>Blog</h1>
    <p class="muted">Write, schedule and optimise articles. Published posts appear at <a href="/blog" target="_blank" rel="noopener">/blog</a> and in the sitemap automatically.</p>
  </div>
  <a class="btn primary" href="/admin/blog-edit">+ New post</a>
</div>
<?php blog_admin_nav('posts'); ?>
<?php if ($doneMsg): ?><div class="alert ok"><?= (int)($_GET['n'] ?? 0) ?> post(s) <?= e($doneMsg) ?>.</div><?php endif; ?>

<section class="stats">
  <div class="stat accent"><span>Live posts</span><strong><?= (int)$stats['live'] ?></strong></div>
  <div class="stat"><span>Drafts</span><strong><?= (int)$stats['drafts'] ?></strong></div>
  <div class="stat"><span>Scheduled</span><strong><?= (int)$stats['scheduled'] ?></strong></div>
  <div class="stat"><span>Total views</span><strong><?= number_format((int)$stats['views']) ?></strong></div>
  <div class="stat"><span>Views · 7 days</span><strong><?= number_format($views7) ?></strong></div>
  <div class="stat"><span>In trash</span><strong><?= (int)$stats['trash'] ?></strong></div>
</section>

<div class="grid-2 even">
  <section class="card chart">
    <div class="card-title">Blog views · last 14 days</div>
    <div class="bars" role="img" aria-label="Blog views per day for the last 14 days">
      <?php foreach ($days as $d => $c): ?>
        <div class="bar" title="<?= e(date('d M', strtotime($d))) ?>: <?= $c ?>">
          <i style="height:<?= max(4, (int)round($c / $max * 100)) ?>%"></i><b><?= $c ?: '' ?></b><span><?= e(date('d', strtotime($d))) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="card">
    <div class="card-title">Top posts</div>
    <?php if (!$top): ?><p class="muted">Views will show up here once visitors read your posts.</p><?php else: ?>
    <ol class="toplist">
      <?php foreach ($top as $t): ?>
        <li><a href="/admin/blog-edit?id=<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a><span><?= number_format((int)$t['views']) ?> views</span></li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <div class="tabs">
    <a class="<?= $tab === '' ? 'on' : '' ?>" href="/admin/blog<?= $qs(['status' => null, 'page' => null]) ?>">All <small><?= (int)$stats['total'] ?></small></a>
    <a class="<?= $tab === 'published' ? 'on' : '' ?>" href="/admin/blog<?= $qs(['status' => 'published', 'page' => null]) ?>">Published <small><?= (int)$stats['live'] ?></small></a>
    <a class="<?= $tab === 'scheduled' ? 'on' : '' ?>" href="/admin/blog<?= $qs(['status' => 'scheduled', 'page' => null]) ?>">Scheduled <small><?= (int)$stats['scheduled'] ?></small></a>
    <a class="<?= $tab === 'draft' ? 'on' : '' ?>" href="/admin/blog<?= $qs(['status' => 'draft', 'page' => null]) ?>">Drafts <small><?= (int)$stats['drafts'] ?></small></a>
    <a class="<?= $tab === 'trash' ? 'on' : '' ?>" href="/admin/blog<?= $qs(['status' => 'trash', 'page' => null]) ?>">Trash <small><?= (int)$stats['trash'] ?></small></a>
  </div>

  <form class="filters" method="get">
    <?php if ($tab): ?><input type="hidden" name="status" value="<?= e($tab) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search title, slug or focus keyword…">
    <select name="cat" aria-label="Category">
      <option value="">All categories</option>
      <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="sort" aria-label="Sort">
      <?php foreach (['new' => 'Newest first', 'old' => 'Oldest first', 'updated' => 'Recently edited', 'views' => 'Most viewed', 'title' => 'Title A–Z'] as $k => $l): ?>
        <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn primary" type="submit">Filter</button>
    <?php if ($q || $catId): ?><a class="btn ghost" href="/admin/blog<?= $tab ? '?status=' . e($tab) : '' ?>">Reset</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty"><div class="empty-ic">✎</div>
      <p><?= $stats['total'] || $stats['trash'] ? 'No posts match this view.' : 'No posts yet. Write your first Devprayag travel article.' ?></p>
      <?php if (!$stats['total']): ?><a class="btn primary" href="/admin/blog-edit">+ Write the first post</a><?php endif; ?>
    </div>
  <?php else: ?>
  <form method="post" id="bulk-form">
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="bulk-bar">
      <select name="action" id="bulk-action" aria-label="Bulk action">
        <option value="">Bulk actions…</option>
        <?php if ($tab === 'trash'): ?>
          <option value="restore">Restore</option><option value="delete">Delete permanently</option>
        <?php else: ?>
          <option value="publish">Publish</option><option value="draft">Move to drafts</option>
          <option value="feature">Mark featured</option><option value="unfeature">Remove featured</option>
          <option value="category">Change category…</option><option value="trash">Move to trash</option>
        <?php endif; ?>
      </select>
      <select name="category_id" id="bulk-cat" hidden aria-label="Category">
        <option value="0">No category</option>
        <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="btn ghost sm" type="submit">Apply</button>
      <span class="muted" id="bulk-count"></span>
    </div>
    <div class="table-wrap">
    <table class="posts">
      <thead><tr><th class="chk"><input type="checkbox" id="chk-all" aria-label="Select all"></th><th>Post</th><th>Category</th><th>Status</th><th class="num">Views</th><th>Updated</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $state = blog_state($r); ?>
        <tr>
          <td class="chk"><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" aria-label="Select <?= e($r['title']) ?>"></td>
          <td><div class="post-cell">
            <?php if ($r['cover_image']): ?><img class="thumb" src="<?= e(blog_thumb($r['cover_image'])) ?>" alt="" loading="lazy"><?php else: ?><span class="thumb ph">✎</span><?php endif; ?>
            <div>
              <a class="name" href="/admin/blog-edit?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a><?= $r['is_featured'] ? ' <span class="star" title="Featured">★</span>' : '' ?>
              <div class="sub">/blog/<?= e($r['slug']) ?> · <?= number_format((int)$r['word_count']) ?> words<?= $r['robots'] === 'noindex' ? ' · <b class="warn-t">noindex</b>' : '' ?></div>
            </div>
          </div></td>
          <td><?= $r['category_name'] ? e($r['category_name']) : '<span class="muted">—</span>' ?></td>
          <td>
            <span class="pill st-<?= $state ?>"><?= e(BLOG_STATE_LABELS[$state]) ?></span>
            <div class="sub"><?= $r['published_at'] ? e(blog_date($r['published_at'], 'd M Y, h:i A')) : '—' ?></div>
          </td>
          <td class="num"><?= number_format((int)$r['views']) ?></td>
          <td><?= e(blog_date($r['updated_at'], 'd M Y')) ?><div class="sub"><?= e(blog_date($r['updated_at'], 'h:i A')) ?></div></td>
          <td class="actions">
            <?php if ($state === 'trash'): ?>
              <button type="button" class="btn ghost sm" onclick="rowAct(this,<?= (int)$r['id'] ?>,'restore')">Restore</button>
            <?php else: ?>
              <a class="icon-btn" title="Edit" href="/admin/blog-edit?id=<?= (int)$r['id'] ?>">✎</a>
              <a class="icon-btn" title="<?= $state === 'published' ? 'View live' : 'Preview' ?>" target="_blank" rel="noopener" href="/blog/<?= e($r['slug']) ?><?= $state === 'published' ? '' : '?preview=1' ?>">👁</a>
              <button type="button" class="icon-btn" title="Duplicate" onclick="rowAct(this,<?= (int)$r['id'] ?>,'duplicate')">⧉</button>
              <button type="button" class="icon-btn" title="Move to trash" onclick="rowAct(this,<?= (int)$r['id'] ?>,'trash')">🗑</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </form>

  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pagination">
    <span class="muted"><?= $total ?> posts · page <?= $page ?> of <?= $pages ?></span>
    <div>
      <?php if ($page > 1): ?><a class="btn ghost sm" href="/admin/blog<?= $qs(['page' => $page - 1]) ?>">← Prev</a><?php endif; ?>
      <?php if ($page < $pages): ?><a class="btn ghost sm" href="/admin/blog<?= $qs(['page' => $page + 1]) ?>">Next →</a><?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</section>

<form method="post" id="row-form" hidden>
  <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
  <input type="hidden" name="id" id="row-id"><input type="hidden" name="action" id="row-action">
</form>
<script>
function rowAct(el, id, action) {
  if (action === 'trash' && !confirm('Move this post to trash?')) { return false; }
  document.getElementById('row-id').value = id;
  document.getElementById('row-action').value = action;
  document.getElementById('row-form').submit();
  return false;
}
(function () {
  var all = document.getElementById('chk-all');
  var boxes = Array.prototype.slice.call(document.querySelectorAll('input[name="ids[]"]'));
  var count = document.getElementById('bulk-count');
  var action = document.getElementById('bulk-action');
  var cat = document.getElementById('bulk-cat');
  function sync() {
    var n = boxes.filter(function (b) { return b.checked; }).length;
    if (count) { count.textContent = n ? n + ' selected' : ''; }
  }
  if (all) { all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); sync(); }); }
  boxes.forEach(function (b) { b.addEventListener('change', sync); });
  if (action) { action.addEventListener('change', function () { cat.hidden = action.value !== 'category'; }); }
  var form = document.getElementById('bulk-form');
  if (form) {
    form.addEventListener('submit', function (ev) {
      var n = boxes.filter(function (b) { return b.checked; }).length;
      if (!action.value || !n) { ev.preventDefault(); alert(!action.value ? 'Choose a bulk action first.' : 'Select at least one post.'); return; }
      if (action.value === 'delete' && !confirm('Delete ' + n + ' post(s) permanently? This cannot be undone.')) { ev.preventDefault(); }
      if (action.value === 'trash' && !confirm('Move ' + n + ' post(s) to trash?')) { ev.preventDefault(); }
    });
  }
})();
</script>
<?php admin_foot();
