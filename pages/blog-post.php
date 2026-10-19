<?php
declare(strict_types=1);
require_once __DIR__ . '/_blog_card.php';
/** @var array $post @var array $tags @var string $bodyHtml @var array $toc @var array $faqItems @var array $related @var ?array $prevPost @var ?array $nextPost @var bool $preview */
$postUrl = abs_url('blog/' . $post['slug']);
$cover = blog_cover($post);
$coverSize = image_size(parse_url($cover, PHP_URL_PATH) ?: $cover);
$shareText = rawurlencode($post['title'] . ' — ' . SITE_NAME);
$shareUrl = rawurlencode($postUrl);
$showToc = count($toc) >= 3;
$isFuture = !empty($post['is_future']);
?>
<main class="bl-page">
  <?php if ($preview): ?>
    <div class="bl-preview">Preview — this post is <strong><?= $post['status'] === 'draft' ? 'a draft' : ($isFuture ? 'scheduled for ' . e(blog_date($post['published_at'], 'd M Y, h:i A')) : e($post['status'])) ?></strong> and is not public yet. <a href="/admin/blog-edit?id=<?= (int)$post['id'] ?>">Back to editor</a></div>
  <?php endif; ?>
  <div class="bl-progress" aria-hidden="true"><i></i></div>

  <header class="bl-hero bl-hero-post">
    <div class="bl-container bl-narrow">
      <nav class="bl-crumbs" aria-label="Breadcrumb">
        <a href="/">Home</a><span>/</span><a href="/blog">Blog</a><span>/</span>
        <?php if ($post['category_slug']): ?><a href="/blog/category/<?= e($post['category_slug']) ?>"><?= e($post['category_name']) ?></a><span>/</span><?php endif; ?>
        <span aria-current="page"><?= e(mb_strimwidth($post['title'], 0, 48, '…')) ?></span>
      </nav>
      <?php if ($post['category_slug']): ?><a class="bl-chip" href="/blog/category/<?= e($post['category_slug']) ?>"><?= e($post['category_name']) ?></a><?php endif; ?>
      <h1><?= e($post['title']) ?></h1>
      <?php if ($post['excerpt']): ?><p class="bl-lead"><?= e($post['excerpt']) ?></p><?php endif; ?>
      <div class="bl-meta bl-meta-lg">
        <span><i class="fa-regular fa-user" aria-hidden="true"></i> <?= e($post['author_name']) ?></span>
        <time datetime="<?= e(blog_iso($post['published_at'])) ?>"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= e(blog_date($post['published_at'], 'd M Y')) ?></time>
        <span><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= (int)$post['reading_time'] ?> min read</span>
        <?php if (strtotime((string)$post['updated_at']) - strtotime((string)$post['published_at']) > 86400 * 2): ?>
          <span>Updated <time datetime="<?= e(blog_iso($post['updated_at'])) ?>"><?= e(blog_date($post['updated_at'], 'd M Y')) ?></time></span>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <div class="bl-container">
    <figure class="bl-cover">
      <img src="<?= e($cover) ?>" alt="<?= e($post['cover_alt'] ?: $post['title']) ?>"<?= $coverSize ? ' width="' . $coverSize[0] . '" height="' . $coverSize[1] . '"' : '' ?> fetchpriority="high">
    </figure>

    <div class="bl-layout">
      <article class="bl-article" id="bl-article">
        <?php if ($showToc): ?>
        <details class="bl-toc bl-toc-inline" open>
          <summary>In this article</summary>
          <ol><?php foreach ($toc as [$lvl, $id, $text]): ?><li class="l<?= $lvl ?>"><a href="#<?= e($id) ?>"><?= e($text) ?></a></li><?php endforeach; ?></ol>
        </details>
        <?php endif; ?>

        <div class="bl-content"><?= $bodyHtml ?></div>

        <?php if ($faqItems): ?>
        <section class="bl-faq" aria-labelledby="bl-faq-h">
          <h2 id="bl-faq-h">Frequently asked questions</h2>
          <?php foreach ($faqItems as [$q, $a]): ?>
            <details><summary><?= e($q) ?></summary><p><?= nl2br(e($a)) ?></p></details>
          <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if ($tags): ?>
        <div class="bl-tags" aria-label="Tags">
          <?php foreach ($tags as $t): ?><a href="/blog/tag/<?= e($t['slug']) ?>">#<?= e($t['name']) ?></a><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="bl-share" aria-label="Share this article">
          <strong>Share</strong>
          <a href="https://wa.me/?text=<?= $shareText ?>%20<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="https://twitter.com/intent/tweet?text=<?= $shareText ?>&amp;url=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="Share on X"><i class="fa-brands fa-x-twitter"></i></a>
          <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
          <button type="button" data-copy="<?= e($postUrl) ?>" aria-label="Copy link"><i class="fa-regular fa-copy"></i> <span>Copy link</span></button>
        </div>

        <aside class="bl-author">
          <img src="/img/logo.png" alt="<?= e(SITE_NAME) ?>" width="64" height="64" loading="lazy">
          <div>
            <strong><?= e($post['author_name']) ?></strong>
            <p>Local hosts at The Sangam House, beside the Devprayag sangam in Uttarakhand — sharing honest travel tips from the place where the Ganga begins.</p>
          </div>
        </aside>

        <?php if ($prevPost || $nextPost): ?>
        <nav class="bl-adjacent" aria-label="More articles">
          <?php if ($prevPost): ?><a class="prev" href="<?= e(blog_url($prevPost['slug'])) ?>" rel="prev"><small>← Previous</small><span><?= e($prevPost['title']) ?></span></a><?php else: ?><span></span><?php endif; ?>
          <?php if ($nextPost): ?><a class="next" href="<?= e(blog_url($nextPost['slug'])) ?>" rel="next"><small>Next →</small><span><?= e($nextPost['title']) ?></span></a><?php endif; ?>
        </nav>
        <?php endif; ?>
      </article>

      <aside class="bl-side">
        <?php if ($showToc): ?>
        <nav class="bl-toc" aria-label="Table of contents">
          <strong>On this page</strong>
          <ol><?php foreach ($toc as [$lvl, $id, $text]): ?><li class="l<?= $lvl ?>"><a href="#<?= e($id) ?>"><?= e($text) ?></a></li><?php endforeach; ?></ol>
        </nav>
        <?php endif; ?>
        <div class="bl-book">
          <strong>Plan your Devprayag stay</strong>
          <p>Deluxe rooms from ₹4,999 and a 40-bed dormitory from ₹999 — beside the sangam.</p>
          <a class="bl-btn" href="<?= e(BOOKING_URL) ?>" target="_blank" rel="noopener">Book direct</a>
          <a class="bl-btn ghost" href="https://wa.me/<?= e(SITE_WHATSAPP) ?>?text=<?= rawurlencode('Hello, I read "' . $post['title'] . '" and would like to know about staying at The Sangam House.') ?>" target="_blank" rel="noopener">WhatsApp us</a>
        </div>
      </aside>
    </div>

    <?php if ($related): ?>
    <section class="bl-related" aria-labelledby="bl-rel">
      <h2 id="bl-rel">Keep reading</h2>
      <div class="bl-grid">
        <?php foreach ($related as $r) { blog_card($r); } ?>
      </div>
    </section>
    <?php endif; ?>
  </div>

  <section class="bl-cta">
    <div class="bl-container bl-cta-in">
      <div>
        <h2>Ready to wake up beside the sangam?</h2>
        <p>Call <a href="tel:<?= e(preg_replace('/\s+/', '', SITE_PHONE_1)) ?>"><?= e(SITE_PHONE_1) ?></a> or send an enquiry — we reply quickly with the best direct rate.</p>
      </div>
      <div class="bl-cta-btns">
        <a class="bl-btn" href="<?= e(BOOKING_URL) ?>" target="_blank" rel="noopener">Book your stay</a>
        <a class="bl-btn ghost" href="/contact">Send an enquiry</a>
      </div>
    </div>
  </section>
</main>
