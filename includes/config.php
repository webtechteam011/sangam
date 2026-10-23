<?php
declare(strict_types=1);

/**
 * Site configuration. Secrets (DB credentials) live OUTSIDE the web root in
 * ../config.private.php  — see config.private.example.php.
 */

date_default_timezone_set('Asia/Kolkata');   // matches the DB session time zone (+05:30)

const SITE_NAME    = 'The Sangam House';
const APP_VERSION  = '1.5.0';
const SITE_PHONE_1 = '+91 9889882696';
const SITE_PHONE_2 = '+91 9999683311';
const SITE_EMAIL   = 'info@thesangamhouse.com';
const SITE_WHATSAPP = '919999683311';
const SITE_LOCALITY = 'Devprayag';
const SITE_REGION   = 'Uttarakhand';
const SITE_LAT      = 30.1457808;
const SITE_LNG      = 78.6049213;
const GTAG_ID       = 'AW-18497672685';
const BOOKING_URL   = 'https://www.secure-booking-engine.com/accounts/N7SAVY4IvU12gH563XN2NQ/properties/uK9uqJbfQJjEr9POldilkw/booking-engine/web/source/4wsctBw6Oq6j-g9XuxeRzQ/';

$private = dirname(__DIR__, 2) . '/config.private.php';
$cfg = is_file($private) ? require $private : [];

// .env (project root, git-ignored): simple KEY=VALUE lines.
$env = [];
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] !== '#' && str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v, " \t\"'\r");
        }
    }
}
define('SMTP_HOST', $env['SMTP_HOST'] ?? '');
define('SMTP_PORT', (int)($env['SMTP_PORT'] ?? 465));
define('SMTP_SECURE', $env['SMTP_SECURE'] ?? 'ssl');
define('SMTP_USER', $env['SMTP_USER'] ?? '');
define('SMTP_PASS', $env['SMTP_PASS'] ?? '');
define('SMTP_FROM', $env['SMTP_FROM'] ?? '');
define('SMTP_FROM_NAME', $env['SMTP_FROM_NAME'] ?? SITE_NAME);
define('DB_HOST', $cfg['db_host'] ?? '127.0.0.1');
define('DB_NAME', $cfg['db_name'] ?? '');
define('DB_USER', $cfg['db_user'] ?? '');
define('DB_PASS', $cfg['db_pass'] ?? '');
// Canonical (production) origin used for canonical URLs, sitemap and schema.
define('SITE_URL', rtrim($cfg['site_url'] ?? 'https://thesangamhouse.com', '/'));
define('NOTIFY_EMAIL', $cfg['notify_email'] ?? SITE_EMAIL);
define('APP_DEBUG', (bool)($cfg['debug'] ?? false));
