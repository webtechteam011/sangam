<?php
declare(strict_types=1);
require_once __DIR__ . '/_blog_card.php';
/** @var array $data @var array $page @var array $res @var array $categories @var array $popular @var ?array $featured */
$mode = $data['mode'];
$rows = $res['rows'];
if ($featured) {
    $rows = array_values(array_filter($rows, fn($r) => (int)$r['id'] !== (int)$featured['id']));
}
$pagerUrl = static function (int $n) use ($data): string {
    $q = [];
    if ($data['mode'] === 'search') { $q['q'] = $data['q']; }
    if ($n > 1) { $q['page'] = $n; }
    return $data['basePath'] . ($q ? '?' . http_build_query($q) : '');
};
$activeCat = $data['cat']['slug'] ?? '';
?>
<main class="bl-page">
  <section class="bl-hero">
    <div class="bl-container">
      <nav class="bl-crumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span>/</span>
        <?php if ($mode === 'index'): ?><span aria-current="page">Blog</span>
        <?php else: ?><a href="/blog">Blog</a><span>/</span><span aria-current="page"><?= e($mode === 'search' ? 'Search' : ($data['cat']['name'] ?? $data['tag']['name'] ?? '')) ?></span><?php endif; ?>
      </nav>
      <span class="bl-kicker">The Sangam House Journal</span>
      <h1><?= e($data['heading']) ?></h1>
      <?php if ($mode !== 'search'): ?><p class="bl-lead"><?= e($data['intro']) ?></p><?php endif; ?>
      <form class="bl-search" action="/blog" method="get" role="search">
        <label class="bl-sr" for="bl-q">Search articles</label>
        <input id="bl-q" type="search" name="q" value="<?= e($data['q'] ?? '') ?>" placeholder="Search Devprayag guides, temples, rafting…" maxlength="80">
        <button type="submit">Search</button>
      </form>
    </div>
  </section>

  <section class="bl-body">
    <div class="bl-container">
      <?php if ($categories): ?>
      <nav class="bl-cats" aria-label="Blog categories">
        <a class="<?= $mode === 'index' || $mode === 'search' ? 'on' : '' ?>" href="/blog">All</a>
        <?php foreach ($categories as $c): if (!(int)$c['live_count']) { continue; } ?>
          <a class="<?= $activeCat === $c['slug'] ? 'on' : '' ?>" href="/blog/category/<?= e($c['slug']) ?>"><?= e($c['name']) ?> <small><?= (int)$c['live_count'] ?></small></a>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>

      <?php if ($featured): ?>
        <?php blog_card($featured, true, true); ?>
      <?php endif; ?>

      <?php if (!$rows && !$featured): ?>
        <div class="bl-empty">
          <h2><?= $mode === 'search' ? 'No articles match your search' : 'Articles are coming soon' ?></h2>
          <p><?= $mode === 'search' ? 'Try a different word, or browse all articles.' : 'We are writing Devprayag travel guides for you. Meanwhile, explore our stays or ask us anything.' ?></p>
          <a class="bl-btn" href="<?= $mode === 'search' ? '/blog' : '/accommodation' ?>"><?= $mode === 'search' ? 'View all articles' : 'See our rooms' ?></a>
        </div>
      <?php else: ?>
        <div class="bl-grid">
          <?php foreach ($rows as $r) { blog_card($r); } ?>
        </div>
      <?php endif; ?>

      <?php if ($res['pages'] > 1): ?>
      <nav class="bl-pager" aria-label="Pagination">
        <?php if ($res['page'] > 1): ?><a rel="prev" href="<?= e($pagerUrl($res['page'] - 1)) ?>">← Newer</a><?php endif; ?>
        <?php for ($i = 1; $i <= $res['pages']; $i++):
            if ($i !== 1 && $i !== $res['pages'] && abs($i - $res['page']) > 2) { if (abs($i - $res['page']) === 3) { echo '<span class="gap">…</span>'; } continue; } ?>
          <a href="<?= e($pagerUrl($i)) ?>" <?= $i === $res['page'] ? 'class="on" aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($res['page'] < $res['pages']): ?><a rel="next" href="<?= e($pagerUrl($res['page'] + 1)) ?>">Older →</a><?php endif; ?>
      </nav>
      <?php endif; ?>

      <?php if ($popular && count($popular) > 1): ?>
      <section class="bl-popular" aria-labelledby="bl-pop">
        <h2 id="bl-pop">Most read</h2>
        <ol>
          <?php foreach ($popular as $i => $p): ?>
            <li><a href="<?= e(blog_url($p['slug'])) ?>"><span class="n"><?= $i + 1 ?></span><span><?= e($p['title']) ?><small><?= (int)$p['reading_time'] ?> min read</small></span></a></li>
          <?php endforeach; ?>
        </ol>
      </section>
      <?php endif; ?>
    </div>
  </section>

  <section class="bl-cta">
    <div class="bl-container bl-cta-in">
      <div>
        <h2>Stay where the Ganga begins</h2>
        <p>Rooms from ₹999 beside the Devprayag sangam — free parking, pure veg food, direct-booking rates.</p>
      </div>
      <div class="bl-cta-btns">
        <a class="bl-btn" href="<?= e(BOOKING_URL) ?>" target="_blank" rel="noopener">Book your stay</a>
        <a class="bl-btn ghost" href="/contact">Send an enquiry</a>
      </div>
    </div>
  </section>
</main>
