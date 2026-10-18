<?php
declare(strict_types=1);

/** POST /enquiry — validates and stores an enquiry, then notifies by email. */

require_once __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /contact', true, 303);
    exit;
}

require_once __DIR__ . '/ratelimit.php';

// Same-origin browser requests only: Origin/Referer must match this host and the
// form script must send its marker header (plain bot POSTs fail both).
$host   = $_SERVER['HTTP_HOST'] ?? '';
$source = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($source === '' || parse_url($source, PHP_URL_HOST) !== $host || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'sangam-form') {
    json_response(['ok' => false, 'message' => 'Invalid request.'], 403);
}
if (!rate_limit_allow('enquiry-min:' . client_ip(), 3, 60)) {
    json_response(['ok' => false, 'message' => 'Too many requests. Please wait a minute and try again.'], 429);
}

$ok = ['ok' => true, 'message' => 'Thank you! Your enquiry has been received. Our team will contact you on phone/WhatsApp shortly.'];

// Spam traps: honeypot, implausibly fast / missing timing, and link-stuffed messages.
$elapsed = (int)($_POST['elapsed'] ?? 0);
if (trim((string)($_POST['website'] ?? '')) !== '' || $elapsed < 2500 || $elapsed > 21600000
    || preg_match_all('#https?://|www\.#i', (string)($_POST['message'] ?? '')) > 1) {
    json_response($ok);
}

$clip = static fn(string $k, int $max): string => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);

$name    = $clip('name', 120);
$phone   = $clip('phone', 30);
$email   = $clip('email', 160);
$room    = $clip('room', 120);
$message = $clip('message', 2000);
$source  = preg_replace('/[^a-z0-9:_-]/i', '', $clip('source', 40)) ?: 'website';
$pageUrl = $clip('page', 255);
$guests  = (int)($_POST['guests'] ?? 0);

$errors = [];
if (mb_strlen($name) < 2) {
    $errors[] = 'Please enter your name.';
}
$digits = preg_replace('/\D/', '', $phone);
if (!preg_match('/^[0-9+()\s.-]+$/', $phone) || strlen($digits) < 7 || strlen($digits) > 15) {
    $errors[] = 'Please enter a valid phone number.';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

$date = static function (string $k): ?string {
    $v = trim((string)($_POST[$k] ?? ''));
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
};
$checkin  = $date('checkin');
$checkout = $date('checkout');
if ($checkin && $checkout && $checkout < $checkin) {
    $errors[] = 'Check-out date must be after check-in.';
}
if ($guests < 1 || $guests > 100) {
    $guests = 0;
}

if ($errors) {
    json_response(['ok' => false, 'message' => implode(' ', $errors)], 422);
}

try {
    $pdo = db();
    $ip  = client_ip();

    $recent = $pdo->prepare('SELECT COUNT(*) FROM enquiries WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $recent->execute([$ip]);
    if ((int)$recent->fetchColumn() >= 5) {
        json_response(['ok' => false, 'message' => 'Too many enquiries from your connection. Please call us instead.'], 429);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO enquiries (name, phone, email, room, checkin, checkout, guests, message, source, page_url, referrer, ip, user_agent)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $name, $phone, $email ?: null, $room ?: null, $checkin, $checkout, $guests ?: null,
        $message ?: null, $source, $pageUrl ?: null,
        mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255) ?: null,
        $ip, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
} catch (Throwable $ex) {
    error_log('Enquiry save failed: ' . $ex->getMessage());
    json_response(['ok' => false, 'message' => 'Sorry, we could not save your enquiry. Please call or WhatsApp us.'], 500);
}

// Best-effort email notification; never blocks the response.
$clean = static fn(string $s): string => str_replace(["\r", "\n"], ' ', $s);
$body  = "New website enquiry\n\n"
    . "Name: $name\nPhone: $phone\nEmail: " . ($email ?: '-') . "\nRoom: " . ($room ?: '-')
    . "\nCheck-in: " . ($checkin ?: '-') . "\nCheck-out: " . ($checkout ?: '-') . "\nGuests: " . ($guests ?: '-')
    . "\nSource: $source\n\nMessage:\n" . ($message ?: '-') . "\n";
$host = preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
$headers = 'From: ' . SITE_NAME . ' <noreply@' . $host . ">\r\nContent-Type: text/plain; charset=UTF-8\r\n";
if ($email !== '') {
    $headers .= 'Reply-To: ' . $clean($email) . "\r\n";
}
@mail(NOTIFY_EMAIL, 'New enquiry from ' . $clean($name), $body, $headers);

json_response($ok);
