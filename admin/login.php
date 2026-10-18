<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

if (admin_user()) {
    header('Location: /admin/');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $pdo = db();
    $ip  = client_ip();

    // Bot traps: hidden field must stay empty and the form can't be posted instantly.
    if (trim((string)($_POST['website'] ?? '')) !== '' || time() - (int)($_SESSION['login_ts'] ?? 0) < 2) {
        usleep(600000);
        $error = 'Incorrect username or password.';
    }

    $cnt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $cnt->execute([$ip]);
    if ($error !== '') {
        // trap tripped: don't even check credentials
    } elseif ((int)$cnt->fetchColumn() >= 5) {
        $error = 'Too many attempts. Please wait 15 minutes and try again.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([trim((string)($_POST['username'] ?? ''))]);
        $row = $stmt->fetch();
        // Verify against a dummy hash when the user doesn't exist to keep timing similar.
        $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringforsalt.usesomesillystringfore0Zq2kVwqXkzXg6S';
        if ($row && password_verify((string)($_POST['password'] ?? ''), $hash)) {
            session_regenerate_id(true);
            $_SESSION['admin'] = ['id' => (int)$row['id'], 'name' => $row['name'], 'username' => $row['username']];
            $_SESSION['last_seen'] = time();
            $pdo->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$row['id']]);
            $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
            header('Location: /admin/');
            exit;
        }
        usleep(400000); // slow down guessing
        $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        $error = 'Incorrect username or password.';
    }
}
if (isset($_GET['expired'])) {
    $error = $error ?: 'Your session expired. Please sign in again.';
}
$_SESSION['login_ts'] = time();
admin_head('Sign in');
?>
<section class="login-card">
  <div class="login-logo"><img src="/img/logo.png" alt="The Sangam House" width="887" height="464"></div>
  <h1>Welcome back</h1>
  <p class="muted">Sign in to manage website enquiries.</p>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label>Username<input type="text" name="username" required autofocus autocomplete="username"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Leave empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <button class="btn primary block" type="submit">Sign in</button>
  </form>
</section>
<?php admin_foot();
