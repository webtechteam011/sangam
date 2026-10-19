<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/blog_admin.php';
$user = require_admin();
$pdo  = db();

$item = static fn(array $m): array => [
    'id' => (int)$m['id'], 'path' => $m['path'], 'thumb' => $m['thumb_path'] ?: $m['path'], 'alt' => (string)$m['alt'], 'original' => (string)$m['original'],
    'width' => (int)$m['width'], 'height' => (int)$m['height'], 'size' => (int)$m['size_bytes'],
];

// JSON list for the editor's media picker
if (isset($_GET['json'])) {
    $per = 24;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $rows = $pdo->query('SELECT * FROM blog_media ORDER BY id DESC LIMIT ' . ($per + 1) . ' OFFSET ' . (($page - 1) * $per))->fetchAll();
    $more = count($rows) > $per;
    json_response(['items' => array_map($item, array_slice($rows, 0, $per)), 'more' => $more]);
}

$flash = '';
$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'upload') {
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
            json_response(['ok' => false, 'error' => 'Session expired — reload the page.'], 419);
        }
        [$row, $error] = blog_store_upload($_FILES['file'] ?? [], $user['name']);
        json_response($row ? ['ok' => true, 'item' => $item($row)] : ['ok' => false, 'error' => $error], $row ? 200 : 422);
    }
    csrf_check();
    if ($action === 'alt') {
        $pdo->prepare('UPDATE blog_media SET alt = ? WHERE id = ?')->execute([mb_substr(trim((string)($_POST['alt'] ?? '')), 0, 190) ?: null, (int)$_POST['id']]);
        header('Location: /admin/media?saved=1');
        exit;
    }
    if ($action === 'delete') {
        $msg = blog_media_delete((int)$_POST['id']);
        header('Location: /admin/media?' . ($msg ? 'err=' . rawurlencode($msg) : 'deleted=1'));
        exit;
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per = 30;
$q = trim((string)($_GET['q'] ?? ''));
$args = [];
$where = '';
if ($q !== '') {
    $where = 'WHERE original LIKE ? OR alt LIKE ? OR path LIKE ?';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $args = [$like, $like, $like];
}
$total = $pdo->prepare("SELECT COUNT(*) FROM blog_media $where");
$total->execute($args);
$total = (int)$total->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$st = $pdo->prepare("SELECT * FROM blog_media $where ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($args);
$rows = $st->fetchAll();
$bytes = (int)$pdo->query('SELECT COALESCE(SUM(size_bytes),0) FROM blog_media')->fetchColumn();

admin_head('Media library');
?>
<div class="page-head">
  <div><h1>Media library</h1><p class="muted"><?= $total ?> images · <?= number_format($bytes / 1048576, 1) ?> MB. Uploads are resized to <?= BLOG_MAX_WIDTH ?>px wide, stripped of metadata and given a thumbnail.</p></div>
</div>
<?php blog_admin_nav('media'); ?>
<?php if (isset($_GET['saved'])): ?><div class="alert ok">Alt text saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert ok">Image deleted.</div><?php endif; ?>
<?php if (!empty($_GET['err'])): ?><div class="alert err"><?= e((string)$_GET['err']) ?></div><?php endif; ?>

<section class="card">
  <div class="dropzone" id="drop">
    <input type="file" id="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
    <strong>Drop images here</strong> or <button type="button" class="btn ghost sm" id="browse">browse files</button>
    <div class="muted" id="status"></div>
  </div>
  <form class="filters" method="get"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search file name or alt text…"><button class="btn primary" type="submit">Search</button></form>

  <?php if (!$rows): ?><div class="empty"><div class="empty-ic">🖼</div><p>No images yet.</p></div><?php else: ?>
  <div class="mgrid" id="mgrid">
    <?php foreach ($rows as $m): $used = blog_media_usage($m['path']); ?>
    <figure class="mcard">
      <a href="<?= e($m['path']) ?>" target="_blank" rel="noopener"><img src="<?= e($m['thumb_path'] ?: $m['path']) ?>" alt="" loading="lazy"></a>
      <figcaption>
        <div class="sub"><?= (int)$m['width'] ?>×<?= (int)$m['height'] ?> · <?= number_format($m['size_bytes'] / 1024) ?> KB · <?= $used ? "used in $used post(s)" : 'unused' ?></div>
        <form method="post" class="alt-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="alt">
          <input name="alt" value="<?= e((string)$m['alt']) ?>" placeholder="Alt text" maxlength="190" aria-label="Alt text"><button class="btn ghost sm">Save</button></form>
        <div class="mrow">
          <button type="button" class="btn ghost sm" data-copy="<?= e($m['path']) ?>">Copy URL</button>
          <form method="post" onsubmit="return confirm('Delete this image?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn danger sm" <?= $used ? 'disabled title="In use"' : '' ?>>Delete</button></form>
        </div>
      </figcaption>
    </figure>
    <?php endforeach; ?>
  </div>
  <?php if ($pages > 1): ?><nav class="pager"><span class="muted">Page <?= $page ?> of <?= $pages ?></span><div>
    <?php if ($page > 1): ?><a class="btn ghost sm" href="?page=<?= $page - 1 ?>&q=<?= urlencode($q) ?>">← Prev</a><?php endif; ?>
    <?php if ($page < $pages): ?><a class="btn ghost sm" href="?page=<?= $page + 1 ?>&q=<?= urlencode($q) ?>">Next →</a><?php endif; ?></div></nav><?php endif; ?>
  <?php endif; ?>
</section>
<link rel="stylesheet" href="/admin/assets/blog-editor.css?v=<?= (int)@filemtime(__DIR__ . '/assets/blog-editor.css') ?>">
<script>
(function () {
  var csrf = <?= json_encode(csrf_token()) ?>, drop = document.getElementById('drop'), st = document.getElementById('status'), file = document.getElementById('file');
  function up(files) {
    var list = Array.prototype.slice.call(files), done = 0;
    if (!list.length) { return; }
    list.forEach(function (f) {
      var fd = new FormData(); fd.append('_csrf', csrf); fd.append('action', 'upload'); fd.append('file', f);
      st.textContent = 'Uploading ' + list.length + ' file(s)…';
      fetch('/admin/media', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok) { st.textContent = f.name + ': ' + d.error; return; }
        if (++done === list.length) { location.href = '/admin/media?saved=0'; }
      });
    });
  }
  document.getElementById('browse').onclick = function () { file.click(); };
  file.onchange = function () { up(file.files); };
  ['dragover', 'dragenter'].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.add('over'); }); });
  ['dragleave', 'drop'].forEach(function (e) { drop.addEventListener(e, function () { drop.classList.remove('over'); }); });
  drop.addEventListener('drop', function (ev) { ev.preventDefault(); up(ev.dataTransfer.files); });
  document.querySelectorAll('[data-copy]').forEach(function (b) { b.onclick = function () { navigator.clipboard.writeText(location.origin + b.dataset.copy); b.textContent = 'Copied!'; setTimeout(function () { b.textContent = 'Copy URL'; }, 1500); }; });
})();
</script>
<?php admin_foot();
