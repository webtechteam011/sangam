<?php
declare(strict_types=1);

/**
 * CLI only: php sql/migrate_blog.php
 * Creates the blog tables (idempotent) and seeds default categories. Safe to re-run on the live server.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$pdo = db();
foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__ . '/schema.sql')))) as $stmt) {
    $pdo->exec($stmt);
}
$cats = [
    ['Travel Guide', 'travel-guide', 'Plan your Devprayag trip: routes, timings, temples and local tips.'],
    ['Places to Visit', 'places-to-visit', 'Sangam, Raghunathji Temple, Chandrabadani, Dasrathshila and more around Devprayag.'],
    ['Adventure', 'adventure', 'River rafting, bungee jumping and trekking near Devprayag and Rishikesh.'],
    ['Stay & Food', 'stay-and-food', 'Where to stay, what to eat and how to book the best rooms in Devprayag.'],
];
$ins = $pdo->prepare('INSERT IGNORE INTO blog_categories (name, slug, description, sort_order) VALUES (?,?,?,?)');
foreach ($cats as $i => [$n, $s, $d]) { $ins->execute([$n, $s, $d, $i]); }
@mkdir(dirname(__DIR__) . '/uploads/blog', 0755, true);
echo "Blog tables ready.\n";
