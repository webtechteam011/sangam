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

// Best-effort emails; never block the response. Only fields the form actually collected are included.
require_once __DIR__ . '/mailer.php';
$clean = static fn(string $s): string => str_replace(["\r", "\n"], ' ', $s);
$fields = array_filter([
    'Name' => $name, 'Phone' => $phone, 'Email' => $email, 'Room' => $room,
    'Guests' => $guests, 'Message' => $message,
], static fn($v) => $v !== null && $v !== '' && $v !== 0);

$mailHtml = static function (string $heading, string $intro, array $rows) use ($clean): string {
    $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $tr = '';
    foreach ($rows as $k => $v) {
        $val = $k === 'Message' ? nl2br($h($v)) : $h($v);
        if ($k === 'Phone') {
            $val = '<a href="tel:' . $h(preg_replace('/[^\d+]/', '', (string)$v)) . '" style="color:#8a6d12;text-decoration:none">' . $h($v) . '</a>';
        } elseif ($k === 'Email') {
            $val = '<a href="mailto:' . $h($v) . '" style="color:#8a6d12;text-decoration:none">' . $h($v) . '</a>';
        }
        $tr .= '<tr><td style="padding:10px 14px;border-bottom:1px solid #eee;color:#777;font-size:13px;width:110px;vertical-align:top">' . $h($k)
            . '</td><td style="padding:10px 14px;border-bottom:1px solid #eee;color:#1c2025;font-size:15px">' . $val . '</td></tr>';
    }
    $logo = SITE_URL . '/img/logo.png';
    return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f1ea;font-family:Arial,Helvetica,sans-serif">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f1ea;padding:24px 12px"><tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#fff;border-radius:10px;overflow:hidden">'
        . '<tr><td align="center" style="background:#1c2025;padding:22px"><img src="' . $h($logo) . '" alt="The Sangam House" height="64" style="height:64px;max-width:240px"></td></tr>'
        . '<tr><td style="padding:26px 28px 8px"><h2 style="margin:0 0 8px;font-size:21px;color:#1c2025">' . $h($heading) . '</h2>'
        . '<p style="margin:0;font-size:15px;line-height:1.6;color:#444">' . $h($intro) . '</p></td></tr>'
        . '<tr><td style="padding:14px 28px 8px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;border-radius:8px">' . $tr . '</table></td></tr>'
        . '<tr><td style="padding:18px 28px 26px;font-size:13px;color:#777;line-height:1.6">The Sangam House, Devprayag, Uttarakhand<br>'
        . 'Call / WhatsApp: <a href="tel:+919999683311" style="color:#8a6d12;text-decoration:none">' . $h(SITE_PHONE_1) . '</a> &middot; '
        . '<a href="mailto:' . $h(SITE_EMAIL) . '" style="color:#8a6d12;text-decoration:none">' . $h(SITE_EMAIL) . '</a></td></tr>'
        . '</table></td></tr></table></body></html>';
};
$plain = static function (string $head, array $rows): string {
    $t = $head . "\n\n";
    foreach ($rows as $k => $v) {
        $t .= "$k: $v\n";
    }
    return $t;
};

$adminSubject = 'New enquiry from ' . $clean($name);
$adminHtml = $mailHtml('New website enquiry', 'A guest just sent an enquiry from the website.', $fields);
if (!smtp_send(NOTIFY_EMAIL, $adminSubject, $plain('New website enquiry', $fields), $email ?: null, $adminHtml)) {
    $host = preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    @mail(NOTIFY_EMAIL, $adminSubject, $plain('New website enquiry', $fields), 'From: ' . SITE_NAME . ' <noreply@' . $host . ">\r\nContent-Type: text/plain; charset=UTF-8\r\n" . ($email !== '' ? 'Reply-To: ' . $clean($email) . "\r\n" : ''));
}
// Confirmation to the guest, if they gave a valid email.
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $intro = 'Thank you for your enquiry. We have received it and our team will contact you shortly on ' . $phone . ' to confirm availability and the best rate.';
    smtp_send($email, 'We received your enquiry - The Sangam House',
        "Dear $name,\n\n$intro\n\n" . $plain('Your details', $fields) . "\nThe Sangam House\n" . SITE_PHONE_1 . "\n" . SITE_EMAIL . "\n",
        SITE_EMAIL, $mailHtml('Thank you, ' . $name, $intro, $fields));
}
json_response($ok);
