<?php
require_once __DIR__ . '/settings.php';
/** @var array $page  @var string $route  @var string $nav */
$faq    = $faq ?? [];
$robots = (is_canonical_host() && empty($robots404) && ($page['robots'] ?? 'index') !== 'noindex')
    ? 'index,follow,max-image-preview:large,max-snippet:-1' : 'noindex,nofollow';
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
<?php render_head($page, $route, $faq, $robots); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<?php if (!empty($preload)): ?><link rel="preload" as="image" href="<?= e($preload) ?>" fetchpriority="high">
<?php endif; ?>
<link rel="stylesheet" href="/vendor/swiper-bundle.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..700;1,400..700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
<noscript>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..700;1,400..700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</noscript>
<link rel="stylesheet" href="/assets/css/style.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="/assets/css/extra.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/css/extra.css') ?>">
<?php if (is_canonical_host()): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= GTAG_ID ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?= GTAG_ID ?>');
function gtag_report_conversion(url) {
  var callback = function () { if (typeof(url) != 'undefined') { window.location = url; } };
  gtag('event', 'conversion', {'send_to': '<?= GTAG_ID ?>/2PluCIuOopodEO2rsPRE', 'value': 1.0, 'currency': 'INR', 'event_callback': callback});
  return false;
}
</script>
<?php endif; ?>
<?php render_scripts('scripts_head'); ?>
</head>
<body>
<?php render_scripts('scripts_body_start'); ?>
<?php require __DIR__ . '/_nav_body.php'; ?>
