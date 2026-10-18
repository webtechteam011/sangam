<?php
declare(strict_types=1);

/**
 * Tiny file-based sliding-window rate limiter (no DB / APCu needed).
 * Counters live in ../ratelimit (outside the web root). Fails open if the folder is unusable.
 */
function rate_limit_allow(string $bucket, int $max, int $windowSeconds): bool
{
    $dir = dirname(__DIR__, 2) . '/ratelimit';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        return true;
    }
    if (!is_writable($dir)) {
        return true;
    }
    $file = $dir . '/' . hash('sha256', $bucket) . '.rl';
    $fh = @fopen($file, 'c+');
    if (!$fh) {
        return true;
    }
    flock($fh, LOCK_EX);
    $now  = time();
    $hits = array_filter(array_map('intval', explode(',', (string)stream_get_contents($fh))), fn($t) => $t > $now - $windowSeconds);
    $ok = count($hits) < $max;
    if ($ok) {
        $hits[] = $now;
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, implode(',', $hits));
    flock($fh, LOCK_UN);
    fclose($fh);

    // Occasional garbage collection of stale counters.
    if (random_int(1, 200) === 1) {
        foreach (glob($dir . '/*.rl') ?: [] as $f) {
            if (@filemtime($f) < $now - 3600) {
                @unlink($f);
            }
        }
    }
    return $ok;
}

/** Throttle page requests per visitor. Legit search crawlers are exempt. */
function guard_page_request(): void
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (preg_match('/(Googlebot|bingbot|DuckDuckBot|Applebot|facebookexternalhit|Twitterbot|WhatsApp|LinkedInBot)/i', $ua)) {
        return;
    }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!rate_limit_allow('page:' . $ip, 90, 60)) {
        header('Retry-After: 60');
        render_error_view(429);
        exit;
    }
}
