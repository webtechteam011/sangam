<?php
declare(strict_types=1);
// Target of Apache ErrorDocument (403/404/500/503). Standalone on purpose.
require __DIR__ . '/includes/error_view.php';
$code = (int)($_GET['c'] ?? $_SERVER['REDIRECT_STATUS'] ?? 404);
if (!in_array($code, [403, 404, 429, 500, 503], true)) {
    $code = 404; // direct visit to /error
}
render_error_view($code);
