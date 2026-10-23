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
        header('Location: /admin/settings?saved=1');
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

<?php
$gid = GTAG_ID;
$builtin = [
    ['Google tag (gtag.js) — HTML pages', 'Active on every public page (inside &lt;head&gt;, built into the site header). Do not paste it again below or it will count twice.',
"<!-- Google tag (gtag.js) -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id=$gid\"></script>\n<script>\n  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag('js', new Date());\n  gtag('config', '$gid');\n</script>"],
    ['Event snippet — WhatsApp Click conversion', 'Active on every public page, right after the Google tag. Every link to WhatsApp (wa.me) also fires this conversion automatically when clicked.',
"<!-- Event snippet for WhatsApp Click conversion page -->\n<script>\nfunction gtag_report_conversion(url) {\n  var callback = function () {\n    if (typeof(url) != 'undefined') { window.location = url; }\n  };\n  gtag('event', 'conversion', {\n    'send_to': '$gid/2PluCIuOopodEO2rsPRE',\n    'value': 1.0,\n    'currency': 'INR',\n    'event_callback': callback\n  });\n  return false;\n}\n</script>"],
    ['Click tracking — text link / button / image (reference)', 'Use this on a new link or button if you want it counted as a WhatsApp Click conversion (WhatsApp links already are).',
"<a onclick=\"return gtag_report_conversion('http://example.com/your-link');\" href=\"http://example.com/your-link\">Link text</a>\n\n<button onclick=\"return gtag_report_conversion('http://example.com/your-link')\">Submit</button>\n\n<img src=\"button.gif\" alt=\"Button\" width=\"32\" height=\"32\" onclick=\"return gtag_report_conversion('http://example.com/your-link')\" />"],
    ['AMP pages — Google tag (reference, not used: this site has no AMP pages)', 'Only needed if AMP versions of pages are ever added. Head first, then the body tag.',
"<!-- in <head>, before the AMP JS library -->\n<script async custom-element=\"amp-analytics\" src=\"https://cdn.ampproject.org/v0/amp-analytics-0.1.js\"></script>\n\n<!-- in <body> -->\n<amp-analytics type=\"gtag\" data-credentials=\"include\">\n<script type=\"application/json\">\n{\n  \"vars\": {\n    \"gtag_id\": \"$gid\",\n    \"config\": { \"$gid\": { \"groups\": \"default\" } }\n  },\n  \"triggers\": { }\n}\n</script>\n</amp-analytics>"],
    ['AMP pages — click event trigger (reference)', 'Paste inside "triggers" of the AMP Google tag; change CSS_SELECTOR to the button or link.',
"\"C_xaBPaJOiUho\": {\n  \"on\": \"click\",\n  \"selector\": \"CSS_SELECTOR\",\n  \"vars\": {\n    \"event_name\": \"conversion\",\n    \"value\": 1.0,\n    \"currency\": \"INR\",\n    \"send_to\": [\"$gid/2PluCIuOopodEO2rsPRE\"]\n  }\n}"],
];
?>
<section class="card">
  <div class="card-title">Google Ads tracking <span class="slot-pos">built in</span></div>
  <p class="muted" style="margin-top:-6px">Conversion account <strong><?= e($gid) ?></strong> (WhatsApp Click). These are already installed in the site code; they are shown here for reference and copy.</p>
  <?php foreach ($builtin as [$t, $d, $code]): ?>
    <h3 style="margin:16px 0 4px;font-size:15px"><?= e($t) ?></h3>
    <p class="muted" style="margin:0 0 6px"><?= $d ?></p>
    <textarea rows="<?= min(14, substr_count($code, "\n") + 2) ?>" spellcheck="false" class="code" readonly onclick="this.select()"><?= e($code) ?></textarea>
  <?php endforeach; ?>
</section>
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
