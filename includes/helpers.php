<?php
declare(strict_types=1);

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function is_canonical_host(): bool
{
    $host = strtolower(preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? ''));
    return $host === strtolower(preg_replace('#^https?://(www\.)?#', '', SITE_URL));
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Admin sign-in stays remembered for 30 days (sliding: every visit extends it). */
const SESSION_LIFETIME = 60 * 60 * 24 * 30;

function start_secure_session(string $name = 'sg_admin'): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    // Private session store outside the web root: shared-host cleanup jobs on the
    // default path would otherwise delete sessions after ~24 minutes.
    $dir = dirname(__DIR__, 2) . '/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
    }
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
    ini_set('session.use_strict_mode', '1');

    $params = [
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    session_name($name);
    session_set_cookie_params($params);
    session_start();

    // Sliding expiry: push the cookie forward on every request.
    if (!headers_sent() && session_id() !== '') {
        unset($params['lifetime']);
        setcookie($name, session_id(), ['expires' => time() + SESSION_LIFETIME] + $params);
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Session expired. Please go back and try again.');
    }
}

const ENQUIRY_STATUSES = [
    'new'       => 'New',
    'contacted' => 'Contacted',
    'booked'    => 'Booked',
    'closed'    => 'Closed',
];
