<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
start_secure_session();

function admin_user(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_admin(): array
{
    $u = admin_user();
    if (!$u) {
        header('Location: /admin/login');
        exit;
    }
    // Idle timeout: 30 days (sliding).
    if (time() - ($_SESSION['last_seen'] ?? 0) > SESSION_LIFETIME) {
        $_SESSION = [];
        session_destroy();
        header('Location: /admin/login?expired=1');
        exit;
    }
    $_SESSION['last_seen'] = time();
    return $u;
}

function admin_head(string $title): void
{
    $u = admin_user();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> · Sangam Admin</title>
<link rel="icon" href="/img/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap">
<link rel="stylesheet" href="/admin/assets/admin.css?v=<?= (int)@filemtime(__DIR__ . '/assets/admin.css') ?>">
</head>
<body>
<?php if ($u): ?>
<header class="topbar">
  <a class="brand" href="/admin/"><span class="dot"></span> Sangam <em>Admin</em></a>
  <nav>
    <a href="/admin/" class="<?= basename($_SERVER["SCRIPT_NAME"]) === "index.php" && !str_contains($_SERVER["SCRIPT_NAME"], "/version/") ? "on" : "" ?>">Enquiries</a>
    <a href="/admin/blog" class="<?= str_starts_with(basename($_SERVER["SCRIPT_NAME"]), "blog") || basename($_SERVER["SCRIPT_NAME"]) === "media.php" ? "on" : "" ?>">Blog</a>
    <a href="/admin/seo" class="<?= basename($_SERVER["SCRIPT_NAME"]) === "seo.php" ? "on" : "" ?>">SEO</a>
    <a href="/admin/version" class="<?= str_contains($_SERVER["SCRIPT_NAME"], "version") ? "on" : "" ?>">Version <em class="vtag">v<?= e(APP_VERSION) ?></em></a>
    <a href="/admin/settings" class="<?= basename($_SERVER["SCRIPT_NAME"]) === "settings.php" ? "on" : "" ?>">Scripts</a>
    <a href="/" target="_blank" rel="noopener">View site ↗</a>
  </nav>
  <div class="who">
    <span><?= e($u['name']) ?></span>
    <a class="btn ghost sm" href="/admin/logout">Sign out</a>
  </div>
</header>
<?php endif; ?>
<main class="wrap">
<?php
}

function admin_foot(): void
{
    echo "</main>\n</body>\n</html>\n";
}
