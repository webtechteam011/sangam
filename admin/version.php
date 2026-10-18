<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_admin();
$pdo = db();

const CL_TYPES = [
    'new'      => ['New feature', '✦'],
    'improved' => ['Improved', '↑'],
    'fixed'    => ['Fixed', '✓'],
    'security' => ['Security', '🔒'],
    'content'  => ['Content', '✎'],
];

$flash = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $version = trim((string)($_POST['version'] ?? ''));
        $title   = trim((string)($_POST['title'] ?? ''));
        $type    = isset(CL_TYPES[$_POST['type'] ?? '']) ? $_POST['type'] : 'new';
        $date    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['released_on'] ?? '') ? $_POST['released_on'] : date('Y-m-d');
        if (preg_match('/^\d+\.\d+\.\d+$/', $version) && $title !== '') {
            $pdo->prepare('INSERT IGNORE INTO changelog (version, released_on, type, title, details, author) VALUES (?,?,?,?,?,?)')
                ->execute([$version, $date, $type, mb_substr($title, 0, 190), mb_substr(trim((string)($_POST['details'] ?? '')), 0, 3000) ?: null, mb_substr(trim((string)($_POST['author'] ?? '')) ?: admin_user()['name'], 0, 80)]);
            header('Location: /admin/version/?added=1');
            exit;
        }
        $flash = 'Enter a version like 1.2.0 and a title.';
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM changelog WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: /admin/version/');
        exit;
    }
}

$filter = isset(CL_TYPES[$_GET['type'] ?? '']) ? $_GET['type'] : '';
$sql = 'SELECT * FROM changelog' . ($filter ? ' WHERE type = ?' : '') . ' ORDER BY released_on DESC, INET_ATON(CONCAT(version, ".0")) DESC, version DESC, id ASC';
$st = $pdo->prepare($sql);
$st->execute($filter ? [$filter] : []);
$byVersion = [];
foreach ($st->fetchAll() as $r) {
    $byVersion[$r['version']][] = $r;
}
uksort($byVersion, fn($a, $b) => version_compare($b, $a));

$counts = $pdo->query('SELECT type, COUNT(*) c FROM changelog GROUP BY type')->fetchAll(PDO::FETCH_KEY_PAIR);
$latest = $pdo->query('SELECT version, released_on FROM changelog ORDER BY released_on DESC, id DESC LIMIT 1')->fetch();

admin_head('Version & change log');
?>
<div class="page-head">
  <div>
    <h1>Version &amp; change log</h1>
    <p class="muted">Everything the developer built, improved and fixed on this website.</p>
  </div>
  <div class="ver-badge">Running <strong>v<?= e(APP_VERSION) ?></strong><?php if ($latest): ?><span>latest entry v<?= e($latest['version']) ?> · <?= e(date('d M Y', strtotime($latest['released_on']))) ?></span><?php endif; ?></div>
</div>

<?php if (isset($_GET['added'])): ?><div class="alert ok">Change-log entry added.</div><?php endif; ?>
<?php if ($flash): ?><div class="alert err"><?= e($flash) ?></div><?php endif; ?>

<section class="stats cl-stats">
  <?php foreach (CL_TYPES as $k => [$label, $ic]): ?>
    <a class="stat cl-<?= $k ?> <?= $filter === $k ? 'sel' : '' ?>" href="/admin/version/<?= $filter === $k ? '' : '?type=' . $k ?>">
      <span><?= $ic ?> <?= e($label) ?></span><strong><?= (int)($counts[$k] ?? 0) ?></strong>
    </a>
  <?php endforeach; ?>
</section>

<?php if (!$byVersion): ?>
  <div class="card empty"><p>No entries yet.</p></div>
<?php endif; ?>

<?php foreach ($byVersion as $ver => $items): ?>
<section class="card release">
  <header>
    <h2>v<?= e($ver) ?> <?= $ver === APP_VERSION ? '<span class="pill s-booked">current</span>' : '' ?></h2>
    <span class="muted"><?= e(date('d M Y', strtotime($items[0]['released_on']))) ?> · <?= count($items) ?> change<?= count($items) === 1 ? '' : 's' ?></span>
  </header>
  <ul class="cl">
    <?php foreach ($items as $it): [$label, $ic] = CL_TYPES[$it['type']]; ?>
      <li>
        <span class="tag cl-<?= e($it['type']) ?>"><?= $ic ?> <?= e($label) ?></span>
        <div>
          <strong><?= e($it['title']) ?></strong>
          <?php if ($it['details']): ?><p><?= e($it['details']) ?></p><?php endif; ?>
          <small class="muted">by <?= e($it['author']) ?></small>
        </div>
        <form method="post" onsubmit="return confirm('Delete this entry?')">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
          <button class="icon-btn" title="Delete entry" type="submit">🗑</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>

<section class="card">
  <div class="card-title">Add a change-log entry</div>
  <form method="post" class="cl-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label>Version<input name="version" required pattern="\d+\.\d+\.\d+" placeholder="<?= e(APP_VERSION) ?>" value="<?= e(APP_VERSION) ?>"></label>
    <label>Type<select name="type"><?php foreach (CL_TYPES as $k => [$label]): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Date<input type="date" name="released_on" value="<?= date('Y-m-d') ?>"></label>
    <label>Developer<input name="author" maxlength="80" value="<?= e(admin_user()['name']) ?>"></label>
    <label class="wide">Title<input name="title" required maxlength="190" placeholder="What changed?"></label>
    <label class="wide">Details (optional)<textarea name="details" rows="3" maxlength="3000"></textarea></label>
    <button class="btn primary" type="submit">Add entry</button>
  </form>
</section>
<?php admin_foot();
