<?php
declare(strict_types=1);
// Target of Apache ErrorDocument (403/404/500/503). Standalone on purpose.
require __DIR__ . '/includes/error_view.php';
$code = (int)($_GET['c'] ?? $_SERVER['REDIRECT_STATUS'] ?? 404);
render_error_view($code);
