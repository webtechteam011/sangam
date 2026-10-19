<?php
declare(strict_types=1);

/** RSS 2.0 feed of the latest 20 live posts. */
$rows = db()->query(
    'SELECT p.title, p.slug, p.excerpt, p.content, p.cover_image, p.published_at, p.updated_at, p.author_name, c.name AS category_name
     FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id
     WHERE ' . BLOG_LIVE . ' ORDER BY p.published_at DESC, p.id DESC LIMIT 20'
)->fetchAll();

$x = static fn(string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: public, max-age=900');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:media="http://search.yahoo.com/mrss/">
<channel>
  <title><?= $x(SITE_NAME) ?> Blog</title>
  <link><?= $x(abs_url('blog')) ?></link>
  <description>Devprayag travel guides, places to visit and stay tips from The Sangam House, Uttarakhand.</description>
  <language>en-in</language>
  <atom:link href="<?= $x(abs_url('blog/feed')) ?>" rel="self" type="application/rss+xml"/>
<?php if ($rows): ?>  <lastBuildDate><?= gmdate('D, d M Y H:i:s', strtotime((string)$rows[0]['updated_at'])) ?> +0000</lastBuildDate>
<?php endif; ?>
<?php foreach ($rows as $r):
    $url = abs_url('blog/' . $r['slug']);
    $desc = $r['excerpt'] ?: blog_auto_excerpt((string)$r['content']);
    [$html] = blog_render_content((string)$r['content']);
    $html = preg_replace('#(src|href)="/(?!/)#', '$1="' . SITE_URL . '/', $html) ?? $html;
?>
  <item>
    <title><?= $x($r['title']) ?></title>
    <link><?= $x($url) ?></link>
    <guid isPermaLink="true"><?= $x($url) ?></guid>
    <pubDate><?= gmdate('D, d M Y H:i:s', strtotime((string)$r['published_at'])) ?> +0000</pubDate>
    <dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/"><?= $x($r['author_name']) ?></dc:creator>
<?php if ($r['category_name']): ?>    <category><?= $x($r['category_name']) ?></category>
<?php endif; ?>
    <description><?= $x($desc) ?></description>
    <content:encoded><![CDATA[<?= str_replace(']]>', ']]]]><![CDATA[>', $html) ?>]]></content:encoded>
<?php if ($r['cover_image']): ?>    <media:content url="<?= $x(blog_img_abs($r['cover_image'])) ?>" medium="image"/>
<?php endif; ?>
  </item>
<?php endforeach; ?>
</channel>
</rss>
