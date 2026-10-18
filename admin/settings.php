<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();
$pdo = db();

const MAX_SCRIPT_BYTES = 60000;
$msg = '';
$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $new = [];
    foreach (array_keys(SCRIPT_SLOTS) as $key) {
        $val = str_replace("\r\n", "\n", (string)($_POST[$key] ?? ''));
        if (strlen($val) > MAX_SCRIPT_BYTES) {
            $err = SCRIPT_SLOTS[$key][0] . ' is too large (max ' . (int)(MAX_SCRIPT_BYTES / 1000) . ' KB).';
            break;
        }
        $new[$key] = $val;
    }
    if (!$err) {
        $st = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($new as $k => $v) {
            $st->execute([$k, $v]);
        }
        header('Location: /admin/settings.php?saved=1');
        exit;
    }
}
if (isset($_GET['saved'])) {
    $msg = 'Scripts saved. They are now live on every public page.';
}

$cur = site_scripts();
if ($err) {
    foreach (array_keys(SCRIPT_SLOTS) as $k) {
        $cur[$k] = (string)($_POST[$k] ?? '');
    }
}

admin_head('Scripts');
?>
<div class="page-head">
  <div>
    <h1>Script settings</h1>
    <p class="muted">Add extra tracking or widget code without editing files. Changes apply to the whole public website.</p>
  </div>
</div>

<?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<form method="post" class="script-form">
  <?= csrf_field() ?>
  <?php foreach (SCRIPT_SLOTS as $key => [$label, $help]): ?>
  <section class="card">
    <div class="card-title"><?= e($label) ?> <span class="slot-pos"><?= $key === 'scripts_head' ? '&lt;head&gt;' : ($key === 'scripts_body_start' ? '&lt;body&gt; start' : '&lt;/body&gt; end') ?></span></div>
    <p class="muted" style="margin-top:-6px"><?= e($help) ?></p>
    <textarea name="<?= e($key) ?>" rows="9" spellcheck="false" class="code" placeholder="<!-- Paste your &lt;script&gt; or tag here -->"><?= e($cur[$key]) ?></textarea>
    <small class="muted"><?= strlen($cur[$key]) ? number_format(strlen($cur[$key])) . ' characters saved' : 'Empty — nothing is added.' ?></small>
  </section>
  <?php endforeach; ?>
  <div class="alert warn"><strong>Be careful:</strong> this code runs for every visitor exactly as written. Only paste code from sources you trust (Google, Meta, your chat provider). A wrong tag can break the site — clear the box and save to remove it.</div>
  <button class="btn primary" type="submit">Save scripts</button>
</form>
<?php admin_foot();
