<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
$user = require_admin();
$pdo  = db();

// ---- Quick actions (POST) ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    if ($id > 0 && $action === 'status' && isset(ENQUIRY_STATUSES[$_POST['status'] ?? ''])) {
        $pdo->prepare('UPDATE enquiries SET status = ? WHERE id = ?')->execute([$_POST['status'], $id]);
    } elseif ($id > 0 && $action === 'delete') {
        $pdo->prepare('DELETE FROM enquiries WHERE id = ?')->execute([$id]);
    }
    header('Location: ' . ($_POST['back'] ?? '/admin/'));
    exit;
}

// ---- Filters ----
$status = isset(ENQUIRY_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$q      = trim((string)($_GET['q'] ?? ''));
$from   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$to     = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';

$where = [];
$args  = [];
if ($status) { $where[] = 'status = ?'; $args[] = $status; }
if ($q !== '') {
    $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR message LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($args, $like, $like, $like, $like);
}
if ($from) { $where[] = 'created_at >= ?'; $args[] = $from . ' 00:00:00'; }
if ($to)   { $where[] = 'created_at <= ?'; $args[] = $to . ' 23:59:59'; }
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));

$total = $pdo->prepare("SELECT COUNT(*) FROM enquiries $sqlWhere");
$total->execute($args);
$total = (int)$total->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);

$list = $pdo->prepare("SELECT * FROM enquiries $sqlWhere ORDER BY created_at DESC, id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
$list->execute($args);
$rows = $list->fetchAll();

// ---- Stats ----
$stats = $pdo->query(
    "SELECT COUNT(*) total,
            SUM(status='new') new_cnt,
            SUM(status='contacted') contacted_cnt,
            SUM(status='booked') booked_cnt,
            SUM(DATE(created_at)=CURDATE()) today_cnt,
            SUM(created_at >= (NOW() - INTERVAL 7 DAY)) week_cnt
     FROM enquiries"
)->fetch();

$series = [];
foreach ($pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM enquiries WHERE created_at >= (CURDATE() - INTERVAL 13 DAY) GROUP BY d")->fetchAll() as $r) {
    $series[$r['d']] = (int)$r['c'];
}
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $days[$d] = $series[$d] ?? 0;
}
$max = max(1, max($days));

$qs = static function (array $over = []) use ($status, $q, $from, $to): string {
    $p = array_filter(array_merge(['status' => $status, 'q' => $q, 'from' => $from, 'to' => $to], $over), fn($v) => $v !== '' && $v !== null);
    return $p ? '?' . http_build_query($p) : '';
};
$back = '/admin/' . $qs(['page' => $page > 1 ? $page : null]);

admin_head('Enquiries');
?>
<div class="page-head">
  <div>
    <h1>Enquiries</h1>
    <p class="muted">Hello <?= e($user['name']) ?> — here's what's coming in from the website.</p>
  </div>
  <a class="btn ghost" href="/admin/export.php<?= $qs() ?>">⬇ Export CSV</a>
</div>

<section class="stats">
  <div class="stat"><span>Total</span><strong><?= (int)$stats['total'] ?></strong></div>
  <div class="stat accent"><span>New</span><strong><?= (int)$stats['new_cnt'] ?></strong></div>
  <div class="stat"><span>Today</span><strong><?= (int)$stats['today_cnt'] ?></strong></div>
  <div class="stat"><span>Last 7 days</span><strong><?= (int)$stats['week_cnt'] ?></strong></div>
  <div class="stat"><span>Contacted</span><strong><?= (int)$stats['contacted_cnt'] ?></strong></div>
  <div class="stat good"><span>Booked</span><strong><?= (int)$stats['booked_cnt'] ?></strong></div>
</section>

<section class="card chart">
  <div class="card-title">Enquiries · last 14 days</div>
  <div class="bars" role="img" aria-label="Enquiries per day for the last 14 days">
    <?php foreach ($days as $d => $c): ?>
      <div class="bar" title="<?= e(date('d M', strtotime($d))) ?>: <?= $c ?>">
        <i style="height:<?= max(4, (int)round($c / $max * 100)) ?>%"></i>
        <b><?= $c ?: '' ?></b>
        <span><?= e(date('d', strtotime($d))) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="card">
  <div class="tabs">
    <a class="<?= $status === '' ? 'on' : '' ?>" href="/admin/<?= $qs(['status' => null, 'page' => null]) ?>">All</a>
    <?php foreach (ENQUIRY_STATUSES as $k => $label): ?>
      <a class="<?= $status === $k ? 'on' : '' ?>" href="/admin/<?= $qs(['status' => $k, 'page' => null]) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <form class="filters" method="get">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, phone, email, message…">
    <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
    <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
    <button class="btn primary" type="submit">Filter</button>
    <?php if ($q || $from || $to): ?><a class="btn ghost" href="/admin/<?= $status ? '?status=' . e($status) : '' ?>">Reset</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty">
      <div class="empty-ic">✉</div>
      <p><?= $total === 0 && !$where ? 'No enquiries yet. They will appear here as soon as someone submits the website form.' : 'No enquiries match your filters.' ?></p>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Guest</th><th>Stay</th><th>Message</th><th>Received</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r):
          $wa = preg_replace('/\D/', '', $r['phone']);
          if (strlen($wa) === 10) { $wa = '91' . $wa; }
      ?>
        <tr class="<?= $r['status'] === 'new' ? 'is-new' : '' ?>">
          <td>
            <a class="name" href="/admin/enquiry.php?id=<?= (int)$r['id'] ?>"><?= e($r['name']) ?></a>
            <div class="sub"><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a><?= $r['email'] ? ' · ' . e($r['email']) : '' ?></div>
          </td>
          <td>
            <?= e($r['room'] ?: '—') ?>
            <div class="sub"><?= $r['checkin'] ? e(date('d M', strtotime($r['checkin']))) . ' → ' . ($r['checkout'] ? e(date('d M', strtotime($r['checkout']))) : '?') : 'No dates' ?><?= $r['guests'] ? ' · ' . (int)$r['guests'] . ' guests' : '' ?></div>
          </td>
          <td class="msg"><?= e(mb_strimwidth((string)$r['message'], 0, 90, '…')) ?: '<span class="muted">—</span>' ?></td>
          <td><?= e(date('d M Y', strtotime($r['created_at']))) ?><div class="sub"><?= e(date('h:i A', strtotime($r['created_at']))) ?> · <?= e($r['source']) ?></div></td>
          <td>
            <form method="post" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="back" value="<?= e($back) ?>">
              <select name="status" class="pill s-<?= e($r['status']) ?>" onchange="this.form.submit()" aria-label="Status">
                <?php foreach (ENQUIRY_STATUSES as $k => $label): ?>
                  <option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="actions">
            <a class="icon-btn" title="Call" href="tel:<?= e($r['phone']) ?>">📞</a>
            <a class="icon-btn" title="WhatsApp" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hello ' . $r['name'] . ', thank you for your enquiry at The Sangam House, Devprayag.') ?>">💬</a>
            <a class="icon-btn" title="Open" href="/admin/enquiry.php?id=<?= (int)$r['id'] ?>">👁</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pagination">
    <span class="muted"><?= $total ?> enquiries · page <?= $page ?> of <?= $pages ?></span>
    <div>
      <?php if ($page > 1): ?><a class="btn ghost sm" href="/admin/<?= $qs(['page' => $page - 1]) ?>">← Prev</a><?php endif; ?>
      <?php if ($page < $pages): ?><a class="btn ghost sm" href="/admin/<?= $qs(['page' => $page + 1]) ?>">Next →</a><?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</section>
<?php admin_foot();
