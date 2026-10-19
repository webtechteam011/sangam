<?php
declare(strict_types=1);

/** One post card. $large = featured layout. */
function blog_card(array $p, bool $large = false, bool $eager = false): void
{
    $img = blog_cover($p);
    $thumb = $large ? $img : blog_thumb($img);
    $size = image_size(parse_url($thumb, PHP_URL_PATH) ?: $thumb);
    $url = blog_url($p['slug']);
    ?>
<article class="bl-card<?= $large ? ' bl-card-lg' : '' ?>">
  <a class="bl-card-img" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
    <img src="<?= e($thumb) ?>" alt="<?= e($p['cover_alt'] ?: $p['title']) ?>"<?= $size ? ' width="' . $size[0] . '" height="' . $size[1] . '"' : '' ?> <?= $eager ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"' ?>>
  </a>
  <div class="bl-card-body">
    <?php if (!empty($p['category_name'])): ?><a class="bl-chip" href="/blog/category/<?= e($p['category_slug']) ?>"><?= e($p['category_name']) ?></a><?php endif; ?>
    <h<?= $large ? '2' : '3' ?> class="bl-card-title"><a href="<?= e($url) ?>"><?= e($p['title']) ?></a></h<?= $large ? '2' : '3' ?>>
    <?php if (!empty($p['excerpt'])): ?><p class="bl-card-excerpt"><?= e($p['excerpt']) ?></p><?php endif; ?>
    <div class="bl-meta">
      <time datetime="<?= e(blog_iso($p['published_at'])) ?>"><?= e(blog_date($p['published_at'])) ?></time>
      <span aria-hidden="true">·</span><span><?= (int)$p['reading_time'] ?> min read</span>
    </div>
  </div>
</article>
<?php
}
