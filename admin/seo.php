<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/seo.php';
require_once __DIR__ . '/../includes/seo_overrides.php';
require_admin();
$pdo = db();

$registry = require __DIR__ . '/../includes/pages.php';
$route = isset($_GET['route']) ? (string)$_GET['route'] : null;
if ($route === 'home') { $route = ''; }
if ($route !== null && !isset($registry[$route])) { $route = null; }

$overrides = seo_overrides();
$err = '';

/** Empty string -> NULL, trimmed and clipped. */
$val = static fn(string $k, int $max): ?string => (($v = mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max)) === '') ? null : $v;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $r = (string)($_POST['route'] ?? '');
    if (!isset($registry[$r])) { http_response_code(400); exit('Unknown page.'); }
    $back = '/admin/seo.php?route=' . ($r === '' ? 'home' : rawurlencode($r));

    if (($_POST['action'] ?? '') === 'reset') {
        $pdo->prepare('DELETE FROM seo_pages WHERE route = ?')->execute([$r]);
        header('Location: ' . $back . '&reset=1');
        exit;
    }

    $extra = $val('extra_schema', 20000);
    if ($extra !== null && json_decode($extra, true) === null) {
        $err = 'Custom JSON-LD is not valid JSON. Paste the JSON only (without <script> tags).';
    }
    $canon = $val('canonical_url', 255);
    if ($canon !== null && !preg_match('#^https?://#i', $canon)) {
        $err = $err ?: 'Canonical URL must start with http:// or https://';
    }
    $image = $val('og_image', 255);
    if ($image !== null && !preg_match('#^(/[A-Za-z0-9_./-]+|https?://\S+)$#', $image)) {
        $err = $err ?: 'OG image must be a path like /img/slider-1.jpg or a full URL.';
    }
    $prio = $val('priority', 4);
    if ($prio !== null && !preg_match('/^(0(\.\d)?|1(\.0)?)$/', $prio)) {
        $err = $err ?: 'Priority must be between 0.0 and 1.0.';
    }
    $freq = $val('changefreq', 10);
    if ($freq !== null && !in_array($freq, ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'], true)) { $freq = null; }
    $robots = in_array($_POST['robots'] ?? '', ['index', 'noindex'], true) ? $_POST['robots'] : null;
    $inMap  = ($_POST['in_sitemap'] ?? '') === '' ? null : (int)($_POST['in_sitemap'] === '1');

    if (!$err) {
        $pdo->prepare(
            'INSERT INTO seo_pages (route, title, description, keywords, og_title, og_description, og_image, canonical_url, robots, in_sitemap, priority, changefreq, extra_schema)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), keywords=VALUES(keywords), og_title=VALUES(og_title),
               og_description=VALUES(og_description), og_image=VALUES(og_image), canonical_url=VALUES(canonical_url), robots=VALUES(robots),
               in_sitemap=VALUES(in_sitemap), priority=VALUES(priority), changefreq=VALUES(changefreq), extra_schema=VALUES(extra_schema)'
        )->execute([$r, $val('title', 190), $val('description', 400), $val('keywords', 500), $val('og_title', 190), $val('og_description', 400),
            $image, $canon, $robots, $inMap, $prio, $freq, $extra]);
        header('Location: ' . $back . '&saved=1');
        exit;
    }
    $route = $r;
    $overrides[$r] = array_merge($overrides[$r] ?? [], array_intersect_key($_POST, array_flip(['title','description','keywords','og_title','og_description','og_image','canonical_url','robots','in_sitemap','priority','changefreq','extra_schema'])));
}

$images = array_map(fn($f) => '/img/' . basename($f), glob(__DIR__ . '/../img/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: []);
sort($images);

admin_head('SEO manager');

/* ------------------------------ LIST ------------------------------ */
if ($route === null):
?>
<div class="page-head">
  <div>
    <h1>SEO manager</h1>
    <p class="muted">Control title, description, keywords, robots, canonical, social image and structured data for every page. Changes go live instantly.</p>
  </div>
</div>
<?php if (!is_canonical_host()): ?><div class="alert warn">This host is not <?= e(SITE_URL) ?>, so search engines are told <strong>noindex</strong> automatically until the real domain points here.</div><?php endif; ?>
<section class="card">
  <div class="table-wrap">
  <table>
    <thead><tr><th>Page</th><th>Title &amp; description</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($registry as $r => $d):
        $o = $overrides[$r] ?? null;
        $p = apply_seo_override($d, (string)$r);
        $tl = mb_strlen($p['title']); $dl = mb_strlen($p['description']);
        $idx = ($p['robots'] ?? 'index') !== 'noindex';
        $url = '/' . $r;
    ?>
      <tr>
        <td>
          <a class="name" href="/admin/seo.php?route=<?= $r === '' ? 'home' : rawurlencode((string)$r) ?>"><?= e($d['breadcrumb']) ?></a>
          <div class="sub"><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($url) ?></a></div>
        </td>
        <td class="msg" style="max-width:420px">
          <div><?= e($p['title']) ?></div>
          <div class="sub"><?= e(mb_strimwidth($p['description'], 0, 110, '…')) ?></div>
          <div class="sub"><span class="len <?= $tl >= 30 && $tl <= 62 ? 'ok' : 'bad' ?>">Title <?= $tl ?></span> <span class="len <?= $dl >= 100 && $dl <= 165 ? 'ok' : 'bad' ?>">Desc <?= $dl ?></span></div>
        </td>
        <td>
          <span class="pill <?= $idx ? 's-booked' : 's-closed' ?>"><?= $idx ? 'Indexed' : 'noindex' ?></span>
          <?php if (($p['in_sitemap'] ?? true) === false): ?><div class="sub">Not in sitemap</div><?php endif; ?>
          <?php if ($o): ?><div class="sub">Customised</div><?php endif; ?>
        </td>
        <td class="actions"><a class="btn ghost sm" href="/admin/seo.php?route=<?= $r === '' ? 'home' : rawurlencode((string)$r) ?>">Edit SEO</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p class="muted" style="margin:14px 0 0">Sitemap: <a href="/sitemap.xml" target="_blank" rel="noopener">/sitemap.xml</a> · Robots: <a href="/robots.txt" target="_blank" rel="noopener">/robots.txt</a></p>
</section>
<?php
/* ------------------------------ EDIT ------------------------------ */
else:
    $d = $registry[$route];
    $o = $overrides[$route] ?? [];
    $f = fn(string $k) => (string)($o[$k] ?? '');
    $ph = fn(string $k) => (string)($d[$k] ?? '');
    $url = '/' . $route;
?>
<div class="page-head">
  <div>
    <a class="muted" href="/admin/seo.php">← All pages</a>
    <h1>SEO · <?= e($d['breadcrumb']) ?></h1>
    <p class="muted"><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e(SITE_URL . $url) ?></a></p>
  </div>
</div>
<?php if (isset($_GET['saved'])): ?><div class="alert ok">SEO saved and live.</div><?php endif; ?>
<?php if (isset($_GET['reset'])): ?><div class="alert ok">Reset to defaults.</div><?php endif; ?>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<div class="grid-2">
<form method="post" class="seo-form-admin">
  <?= csrf_field() ?><input type="hidden" name="route" value="<?= e($route) ?>">
  <section class="card">
    <div class="card-title">Search result</div>
    <label>Title <span class="cnt" data-for="title" data-min="30" data-max="62"></span>
      <input name="title" id="f-title" maxlength="190" value="<?= e($f('title')) ?>" placeholder="<?= e($ph('title')) ?>"></label>
    <label>Meta description <span class="cnt" data-for="description" data-min="100" data-max="165"></span>
      <textarea name="description" id="f-description" rows="3" maxlength="400" placeholder="<?= e($ph('description')) ?>"><?= e($f('description')) ?></textarea></label>
    <label>Keywords <small class="muted">(comma separated)</small>
      <textarea name="keywords" rows="2" maxlength="500" placeholder="<?= e($ph('keywords')) ?>"><?= e($f('keywords')) ?></textarea></label>
  </section>

  <section class="card">
    <div class="card-title">Social sharing (Open Graph / Twitter)</div>
    <label>Social title <small class="muted">(blank = page title)</small><input name="og_title" maxlength="190" value="<?= e($f('og_title')) ?>"></label>
    <label>Social description <small class="muted">(blank = meta description)</small><textarea name="og_description" rows="2" maxlength="400"><?= e($f('og_description')) ?></textarea></label>
    <label>Social image
      <input name="og_image" id="f-img" list="imgs" maxlength="255" value="<?= e($f('og_image')) ?>" placeholder="<?= e($ph('image')) ?>">
      <datalist id="imgs"><?php foreach ($images as $im): ?><option value="<?= e($im) ?>"><?php endforeach; ?></datalist>
    </label>
    <img id="img-prev" class="og-prev" src="<?= e($f('og_image') ?: $ph('image')) ?>" alt="Social image preview" loading="lazy">
  </section>

  <section class="card">
    <div class="card-title">Indexing &amp; sitemap</div>
    <div class="cl-form">
      <label>Search engines
        <select name="robots">
          <option value="" <?= $f('robots') === '' ? 'selected' : '' ?>>Default (index)</option>
          <option value="index" <?= $f('robots') === 'index' ? 'selected' : '' ?>>index, follow</option>
          <option value="noindex" <?= $f('robots') === 'noindex' ? 'selected' : '' ?>>noindex, nofollow</option>
        </select></label>
      <label>In sitemap
        <select name="in_sitemap">
          <?php $im = $o['in_sitemap'] ?? null; ?>
          <option value="" <?= $im === null ? 'selected' : '' ?>>Default (yes)</option>
          <option value="1" <?= (string)$im === '1' ? 'selected' : '' ?>>Yes</option>
          <option value="0" <?= (string)$im === '0' ? 'selected' : '' ?>>No</option>
        </select></label>
      <label>Priority <input name="priority" maxlength="4" value="<?= e($f('priority')) ?>" placeholder="<?= e($ph('priority')) ?>"></label>
      <label>Change frequency
        <select name="changefreq"><option value="">Default (<?= e($ph('changefreq')) ?>)</option>
          <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $cf): ?><option <?= $f('changefreq') === $cf ? 'selected' : '' ?>><?= $cf ?></option><?php endforeach; ?></select></label>
      <label class="wide">Canonical URL <small class="muted">(blank = <?= e(SITE_URL . $url) ?>)</small><input name="canonical_url" maxlength="255" value="<?= e($f('canonical_url')) ?>" placeholder="<?= e(SITE_URL . $url) ?>"></label>
    </div>
  </section>

  <section class="card">
    <div class="card-title">Extra structured data (JSON-LD)</div>
    <p class="muted" style="margin-top:-6px">Optional. Paste one JSON object (e.g. an Event, Review or extra FAQ). It is added alongside the automatic Hotel/Page/Breadcrumb schema. Do not include &lt;script&gt; tags.</p>
    <textarea name="extra_schema" rows="8" class="code" spellcheck="false" placeholder='{"@context":"https://schema.org","@type":"Event", ...}'><?= e($f('extra_schema')) ?></textarea>
  </section>

  <button class="btn primary" type="submit">Save SEO</button>
</form>

<aside>
  <section class="card" style="position:sticky;top:76px">
    <div class="card-title">Google preview</div>
    <div class="serp">
      <div class="serp-url"><?= e(preg_replace('#^https?://#', '', SITE_URL)) ?> › <?= e($route === '' ? 'home' : $route) ?></div>
      <div class="serp-title" id="serp-title"></div>
      <div class="serp-desc" id="serp-desc"></div>
    </div>
    <p class="muted" style="font-size:12.5px">Ideal: title 30–62 characters, description 100–165. Leave a field empty to use the built-in default shown as placeholder.</p>
    <form method="post" onsubmit="return confirm('Remove all custom SEO for this page and use defaults?')">
      <?= csrf_field() ?><input type="hidden" name="route" value="<?= e($route) ?>"><input type="hidden" name="action" value="reset">
      <button class="btn danger sm" type="submit">Reset to defaults</button>
    </form>
  </section>
</aside>
</div>
<script>
(function(){
  var def={title:<?= json_encode($ph('title'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,description:<?= json_encode($ph('description'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>};
  function upd(){
    ['title','description'].forEach(function(k){
      var el=document.getElementById('f-'+k), v=(el.value.trim()||def[k]);
      var c=document.querySelector('.cnt[data-for="'+k+'"]'), min=+c.dataset.min, max=+c.dataset.max;
      c.textContent=v.length+' chars'; c.className='cnt '+(v.length>=min&&v.length<=max?'ok':'bad');
      document.getElementById('serp-'+k).textContent = k==='description'&&v.length>165? v.slice(0,160)+'…' : (k==='title'&&v.length>62? v.slice(0,60)+'…' : v);
    });
  }
  ['f-title','f-description'].forEach(function(id){document.getElementById(id).addEventListener('input',upd);}); upd();
  var im=document.getElementById('f-img'), pv=document.getElementById('img-prev');
  im.addEventListener('input',function(){ if(im.value) pv.src=im.value; });
})();
</script>
<?php endif;
admin_foot();
