<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_admin();
$pdo = db();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM enquiries WHERE id = ?');
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) {
    http_response_code(404);
    admin_head('Not found');
    echo '<div class="card"><p>Enquiry not found. <a href="/admin/">Back to list</a></p></div>';
    admin_foot();
    exit;
}

$saved = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM enquiries WHERE id = ?')->execute([$id]);
        header('Location: /admin/');
        exit;
    }
    $status = isset(ENQUIRY_STATUSES[$_POST['status'] ?? '']) ? $_POST['status'] : $r['status'];
    $notes  = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 5000);
    $pdo->prepare('UPDATE enquiries SET status = ?, notes = ? WHERE id = ?')->execute([$status, $notes ?: null, $id]);
    header('Location: /admin/enquiry.php?id=' . $id . '&saved=1');
    exit;
}
$saved = isset($_GET['saved']);

$wa = preg_replace('/\D/', '', $r['phone']);
if (strlen($wa) === 10) { $wa = '91' . $wa; }

admin_head('Enquiry #' . $id);
?>
<div class="page-head">
  <div>
    <a class="muted" href="/admin/">← All enquiries</a>
    <h1><?= e($r['name']) ?> <span class="pill s-<?= e($r['status']) ?>"><?= e(ENQUIRY_STATUSES[$r['status']]) ?></span></h1>
    <p class="muted">Enquiry #<?= $id ?> · received <?= e(date('d M Y, h:i A', strtotime($r['created_at']))) ?></p>
  </div>
  <div class="row-actions">
    <a class="btn ghost" href="tel:<?= e($r['phone']) ?>">📞 Call</a>
    <a class="btn primary" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hello ' . $r['name'] . ', thank you for your enquiry at The Sangam House, Devprayag.') ?>">💬 WhatsApp</a>
  </div>
</div>

<?php if ($saved): ?><div class="alert ok">Changes saved.</div><?php endif; ?>

<div class="grid-2">
  <section class="card">
    <div class="card-title">Guest &amp; stay details</div>
    <dl class="details">
      <dt>Name</dt><dd><?= e($r['name']) ?></dd>
      <dt>Phone</dt><dd><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></dd>
      <dt>Email</dt><dd><?= $r['email'] ? '<a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>' : '—' ?></dd>
      <dt>Room</dt><dd><?= e($r['room'] ?: '—') ?></dd>
      <dt>Check-in</dt><dd><?= $r['checkin'] ? e(date('D, d M Y', strtotime($r['checkin']))) : '—' ?></dd>
      <dt>Check-out</dt><dd><?= $r['checkout'] ? e(date('D, d M Y', strtotime($r['checkout']))) : '—' ?></dd>
      <dt>Guests</dt><dd><?= $r['guests'] ? (int)$r['guests'] : '—' ?></dd>
      <dt>Message</dt><dd class="pre"><?= e($r['message'] ?: '—') ?></dd>
    </dl>
    <div class="card-title" style="margin-top:22px">Source</div>
    <dl class="details small">
      <dt>Form</dt><dd><?= e($r['source']) ?></dd>
      <dt>Page</dt><dd><?= e($r['page_url'] ?: '—') ?></dd>
      <dt>Referrer</dt><dd><?= e($r['referrer'] ?: '—') ?></dd>
      <dt>IP</dt><dd><?= e($r['ip'] ?: '—') ?></dd>
    </dl>
  </section>

  <section class="card">
    <div class="card-title">Follow-up</div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <label>Status
        <select name="status">
          <?php foreach (ENQUIRY_STATUSES as $k => $label): ?>
            <option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Internal notes
        <textarea name="notes" rows="8" placeholder="Call summary, quoted rate, next steps…"><?= e($r['notes']) ?></textarea>
      </label>
      <button class="btn primary" type="submit">Save changes</button>
    </form>
    <form method="post" onsubmit="return confirm('Delete this enquiry permanently?')" class="danger-zone">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="delete">
      <button class="btn danger sm" type="submit">Delete enquiry</button>
    </form>
  </section>
</div>
<?php admin_foot();
