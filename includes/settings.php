<?php
declare(strict_types=1);

/** Custom scripts managed from Admin → Scripts. Keys: scripts_head, scripts_body_start, scripts_footer. */
const SCRIPT_SLOTS = [
    'scripts_head'       => ['Header scripts', 'Printed just before </head>. Good for Google Tag Manager, Analytics, Meta Pixel, verification meta tags, extra CSS.'],
    'scripts_body_start' => ['Body first scripts', 'Printed immediately after the opening <body> tag. Good for the Google Tag Manager <noscript> iframe.'],
    'scripts_footer'     => ['Footer scripts', 'Printed just before </body>. Good for chat widgets, remarketing tags and other late-loading scripts.'],
];

function site_scripts(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = array_fill_keys(array_keys(SCRIPT_SLOTS), '');
    try {
        require_once __DIR__ . '/db.php';
        $rows = db()->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'scripts\\_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($rows as $k => $v) {
            if (isset($cache[$k])) {
                $cache[$k] = (string)$v;
            }
        }
    } catch (Throwable $e) {
        // Never break the public site because of settings.
    }
    return $cache;
}

/** Output a script slot verbatim (admin-authored, trusted HTML). */
function render_scripts(string $slot): void
{
    $html = site_scripts()[$slot] ?? '';
    if (trim($html) !== '') {
        echo "\n<!-- custom:{$slot} -->\n" . $html . "\n<!-- /custom:{$slot} -->\n";
    }
}
