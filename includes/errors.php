<?php
declare(strict_types=1);

/**
 * Production error handling: never show a blank browser error. Unhandled exceptions
 * and fatal errors are logged (outside the web root) and a branded 500 page is shown.
 */
require_once __DIR__ . '/error_view.php';

$__logFile = dirname(__DIR__, 2) . '/app_errors.log';
ini_set('display_errors', (defined('APP_DEBUG') && APP_DEBUG) ? '1' : '0');
ini_set('log_errors', '1');
if (is_writable(dirname($__logFile))) {
    ini_set('error_log', $__logFile);
}

function show_fatal_error(): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (headers_sent()) {
        return;
    }
    render_error_view(500);
}

set_exception_handler(function (Throwable $e): void {
    error_log(sprintf('[%s] Uncaught %s: %s in %s:%d (%s)', date('c'), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $_SERVER['REQUEST_URI'] ?? ''));
    show_fatal_error();
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log(sprintf('[%s] Fatal: %s in %s:%d (%s)', date('c'), $err['message'], $err['file'], $err['line'], $_SERVER['REQUEST_URI'] ?? ''));
        show_fatal_error();
    }
});
