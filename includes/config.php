<?php
declare(strict_types=1);

/**
 * Site configuration. Secrets (DB credentials) live OUTSIDE the web root in
 * ../config.private.php  — see config.private.example.php.
 */

const SITE_NAME    = 'The Sangam House';
const APP_VERSION  = '1.2.0';
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

define('DB_HOST', $cfg['db_host'] ?? '127.0.0.1');
define('DB_NAME', $cfg['db_name'] ?? '');
define('DB_USER', $cfg['db_user'] ?? '');
define('DB_PASS', $cfg['db_pass'] ?? '');
// Canonical (production) origin used for canonical URLs, sitemap and schema.
define('SITE_URL', rtrim($cfg['site_url'] ?? 'https://thesangamhouse.com', '/'));
define('NOTIFY_EMAIL', $cfg['notify_email'] ?? SITE_EMAIL);
define('APP_DEBUG', (bool)($cfg['debug'] ?? false));
