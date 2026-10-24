<?php
declare(strict_types=1);

function abs_url(string $path): string
{
    return SITE_URL . '/' . ltrim($path, '/');
}

function nav_cls(string $key): string
{
    global $nav;
    return ($nav ?? '') === $key ? 'class="active"' : '';
}

/** Schema.org graph: LodgingBusiness + WebSite + WebPage + Breadcrumbs (+ FAQ). */
function schema_graph(array $page, string $route, array $faq = []): array
{
    $url   = abs_url($route);
    $image = abs_url($page['image'] ?? '/img/slider-1.jpg');
    $hotelId = SITE_URL . '/#hotel';

    $graph = [
        [
            '@type'       => ['Hotel', 'LodgingBusiness'],
            '@id'         => $hotelId,
            'name'        => SITE_NAME,
            'url'         => SITE_URL . '/',
            'image'       => abs_url('/img/slider-1.jpg'),
            'logo'        => abs_url('/img/logo.png'),
            'description' => 'Peaceful Himalayan hotel in Devprayag, Uttarakhand, beside the Alaknanda and Bhagirathi confluence.',
            'telephone'   => SITE_PHONE_1,
            'email'       => SITE_EMAIL,
            'priceRange'  => '₹999 - ₹4,999',
            'currenciesAccepted' => 'INR',
            'address'     => [
                '@type'           => 'PostalAddress',
                'addressLocality' => SITE_LOCALITY,
                'addressRegion'   => SITE_REGION,
                'addressCountry'  => 'IN',
            ],
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => SITE_LAT, 'longitude' => SITE_LNG],
            'hasMap' => 'https://maps.app.goo.gl/oBABLFd3XfHZSdLr5',
            'numberOfRooms' => 20,
            'amenityFeature' => array_map(
                fn($n) => ['@type' => 'LocationFeatureSpecification', 'name' => $n, 'value' => true],
                ['Free WiFi', 'Free parking', 'Restaurant', 'Air conditioning', 'Vegetarian food', 'Mountain view']
            ),
            'sameAs' => ['https://www.instagram.com/thesangamhouse/'],
            'contactPoint' => [[
                '@type' => 'ContactPoint', 'contactType' => 'reservations',
                'telephone' => SITE_PHONE_1, 'email' => SITE_EMAIL,
                'availableLanguage' => ['en', 'hi'], 'areaServed' => 'IN',
            ]],
            'openingHoursSpecification' => [[
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'opens' => '00:00', 'closes' => '23:59',
            ]],
        ],
        [
            '@type' => 'WebSite',
            '@id'   => SITE_URL . '/#website',
            'url'   => SITE_URL . '/',
            'name'  => SITE_NAME,
            'inLanguage' => 'en-IN',
            'publisher'  => ['@id' => $hotelId],
        ],
        [
            '@type' => $page['ptype'] ?? 'WebPage',
            '@id'   => $url . '#webpage',
            'url'   => $url,
            'name'  => $page['title'],
            'description' => $page['description'],
            'isPartOf' => ['@id' => SITE_URL . '/#website'],
            'about'    => ['@id' => $hotelId],
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $image],
            'inLanguage' => 'en-IN',
        ],
    ];

    $roomDefs = [
        'deluxe' => ['Deluxe Mountain View Room', 'King-size bed, 43-inch smart TV, air conditioning and en-suite bathroom with hot water. Sleeps 2 adults and 1 child.', 4999, '/deluxe-mountain-view-room', '/img/roomone.jpg', 3],
        'dorm'   => ['Luxury Himalayan Dormitory', 'Bunk-bed dormitory with individual lockers for groups and trekkers. Two dorms of 20 beds each (40 guests).', 999, '/luxury-himalayan-dormitory', '/img/room12.jpg', 40],
    ];
    foreach ($page['rooms'] ?? [] as $key) {
        [$rn, $rd, $price, $rpath, $rimg, $occ] = $roomDefs[$key];
        $graph[] = [
            '@type' => 'HotelRoom', '@id' => abs_url($rpath) . '#room',
            'name' => $rn, 'description' => $rd, 'url' => abs_url($rpath), 'image' => abs_url($rimg),
            'occupancy' => ['@type' => 'QuantitativeValue', 'maxValue' => $occ],
            'containedInPlace' => ['@id' => $hotelId],
            'offers' => [
                '@type' => 'Offer', 'price' => $price, 'priceCurrency' => 'INR',
                'url' => abs_url($rpath), 'availability' => 'https://schema.org/InStock',
                'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => $price, 'priceCurrency' => 'INR', 'unitText' => $key === 'dorm' ? 'per bed' : 'per room'],
            ],
        ];
    }
    if (!empty($page['attractions'])) {
        $places = [
            ['Devprayag Sangam', 'Sacred confluence of the Alaknanda and Bhagirathi rivers where the Ganga is formed.'],
            ['Raghunathji Temple', 'Ancient temple dedicated to Lord Rama overlooking Devprayag.'],
            ['Chandrabadani Temple', 'Heritage temple near Devprayag.'],
            ['Dasrathshila', 'Sacred rock near the Devprayag confluence.'],
            ['Bungee Jumping & River Rafting', 'Adventure activities on the Ganga near Devprayag.'],
        ];
        $graph[] = [
            '@type' => 'ItemList', '@id' => $url . '#attractions', 'name' => 'Things to do in Devprayag',
            'itemListElement' => array_map(fn($pl, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1,
                'item' => ['@type' => 'TouristAttraction', 'name' => $pl[0], 'description' => $pl[1],
                           'address' => ['@type' => 'PostalAddress', 'addressLocality' => SITE_LOCALITY, 'addressRegion' => SITE_REGION, 'addressCountry' => 'IN']],
            ], $places, array_keys($places)),
        ];
    }

    $crumbs = [['Home', '']];
    if (!empty($page['crumbs'])) {
        $crumbs = array_merge($crumbs, $page['crumbs']);
    } elseif (!empty($page['parent'])) {
        $parent = (require __DIR__ . '/pages.php')[$page['parent']];
        $crumbs[] = [$parent['breadcrumb'], $page['parent']];
    }
    if ($route !== '' && empty($page['crumbs'])) {
        $crumbs[] = [$page['breadcrumb'], $route];
    }
    if (count($crumbs) > 1) {
        $items = [];
        foreach ($crumbs as $i => [$name, $r]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => abs_url($r)];
        }
        $graph[] = ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $items];
    }

    if ($faq) {
        $graph[] = [
            '@type' => 'FAQPage',
            '@id'   => $url . '#faq',
            'mainEntity' => array_map(fn($f) => [
                '@type' => 'Question', 'name' => $f[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
            ], $faq),
        ];
    }
    foreach ($page['extra_graph'] ?? [] as $node) {
        $graph[] = $node;
    }
    return ['@context' => 'https://schema.org', '@graph' => $graph];
}

function image_size(string $path): ?array
{
    $file = dirname(__DIR__) . $path;
    $info = is_file($file) ? @getimagesize($file) : false;
    return $info ? [$info[0], $info[1], $info['mime']] : null;
}

function render_head(array $page, string $route, array $faq = [], string $robots = 'index,follow,max-image-preview:large,max-snippet:-1'): void
{
    $canonical = !empty($page['canonical_url']) ? $page['canonical_url'] : abs_url($route);
    $ogTitle   = $page['og_title'] ?? $page['title'];
    $ogDesc    = $page['og_description'] ?? $page['description'];
    $extra     = '';
    if (!empty($page['extra_schema']) && ($decoded = json_decode($page['extra_schema'], true)) !== null) {
        $extra = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }
    $image     = abs_url($page['image'] ?? '/img/slider-1.jpg');
    $schema    = json_encode(schema_graph($page, $route, $faq), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page['title']) ?></title>
<meta name="description" content="<?= e($page['description']) ?>">
<?php if (!empty($page['keywords'])): ?><meta name="keywords" content="<?= e($page['keywords']) ?>">
<?php endif; ?>
<meta name="author" content="<?= e(SITE_NAME) ?>">
<meta name="robots" content="<?= e($robots) ?>">
<meta name="geo.region" content="IN-UT">
<meta name="geo.placename" content="Devprayag, Uttarakhand">
<meta name="geo.position" content="<?= SITE_LAT ?>;<?= SITE_LNG ?>">
<meta name="theme-color" content="#111111">
<link rel="canonical" href="<?= e($canonical) ?>">
<link rel="alternate" hreflang="en-IN" href="<?= e($canonical) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e($canonical) ?>">
<link rel="alternate" type="application/rss+xml" title="<?= e(SITE_NAME) ?> Blog" href="<?= e(abs_url('blog/feed')) ?>">
<?php if (!empty($page['prev_url'])): ?><link rel="prev" href="<?= e($page['prev_url']) ?>">
<?php endif; if (!empty($page['next_url'])): ?><link rel="next" href="<?= e($page['next_url']) ?>">
<?php endif; ?>
<meta property="og:type" content="<?= e($page['og_type'] ?? 'website') ?>">
<meta property="og:locale" content="en_IN">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<meta property="og:description" content="<?= e($ogDesc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($image) ?>">
<?php if ($size = image_size($page['image'] ?? '/img/slider-1.jpg')): ?><meta property="og:image:width" content="<?= $size[0] ?>">
<meta property="og:image:height" content="<?= $size[1] ?>">
<meta property="og:image:type" content="<?= e($size[2]) ?>">
<?php endif; ?>
<meta property="og:image:alt" content="<?= e($page['image_alt'] ?? $page['title']) ?>">
<?php if (!empty($page['article'])): $ar = $page['article']; ?><meta property="article:published_time" content="<?= e($ar['published']) ?>">
<meta property="article:modified_time" content="<?= e($ar['modified']) ?>">
<meta property="article:author" content="<?= e($ar['author']) ?>">
<?php if (!empty($ar['section'])): ?><meta property="article:section" content="<?= e($ar['section']) ?>">
<?php endif; foreach ($ar['tags'] ?? [] as $t): ?><meta property="article:tag" content="<?= e($t) ?>">
<?php endforeach; endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($ogTitle) ?>">
<meta name="twitter:description" content="<?= e($ogDesc) ?>">
<meta name="twitter:image" content="<?= e($image) ?>">
<meta name="twitter:image:alt" content="<?= e($page['image_alt'] ?? $page['title']) ?>">
<meta name="format-detection" content="telephone=yes">
<meta name="application-name" content="<?= e(SITE_NAME) ?>">
<meta name="apple-mobile-web-app-title" content="<?= e(SITE_NAME) ?>">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32.png">
<link rel="icon" type="image/png" sizes="192x192" href="/img/icon-192.png">
<link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon.png">
<script type="application/ld+json"><?= $schema ?></script>
<?php if ($extra): ?><script type="application/ld+json"><?= $extra ?></script>
<?php endif; ?>
<?php
}
