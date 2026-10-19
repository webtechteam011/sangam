<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
if (!is_canonical_host()) {
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /enquiry\nDisallow: /blog?q=\n\nSitemap: " . SITE_URL . "/sitemap.xml\n";
