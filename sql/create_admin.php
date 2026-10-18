<?php
declare(strict_types=1);

/**
 * CLI only:  php sql/create_admin.php <username> "<Full Name>" [password]
 * Creates (or resets the password of) an admin and applies sql/schema.sql.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

[$self, $user, $name] = array_pad($argv, 3, null);
$pass = $argv[3] ?? null;
if (!$user || !$name) {
    fwrite(STDERR, "Usage: php sql/create_admin.php <username> \"<Full Name>\" [password]\n");
    exit(1);
}
$generated = false;
if (!$pass) {
    $pass = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Ab'), '=');
    $generated = true;
}

$pdo = db();
foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__ . '/schema.sql')))) as $stmt) {
    $pdo->exec($stmt);
}
$pdo->prepare(
    'INSERT INTO admins (username, name, password_hash) VALUES (?,?,?)
     ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash)'
)->execute([$user, $name, password_hash($pass, PASSWORD_DEFAULT)]);

echo "Schema applied. Admin '$user' ready." . ($generated ? "\nPassword: $pass" : '') . "\n";
