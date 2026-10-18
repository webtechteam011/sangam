<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_admin();

$status = isset(ENQUIRY_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$q      = trim((string)($_GET['q'] ?? ''));
$from   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$to     = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';

$where = []; $args = [];
if ($status) { $where[] = 'status = ?'; $args[] = $status; }
if ($q !== '') {
    $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR message LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($args, $like, $like, $like, $like);
}
if ($from) { $where[] = 'created_at >= ?'; $args[] = $from . ' 00:00:00'; }
if ($to)   { $where[] = 'created_at <= ?'; $args[] = $to . ' 23:59:59'; }

$stmt = db()->prepare('SELECT id, created_at, name, phone, email, room, checkin, checkout, guests, message, status, notes, source, page_url FROM enquiries'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC');
$stmt->execute($args);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sangam-enquiries-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
fputcsv($out, ['ID', 'Received', 'Name', 'Phone', 'Email', 'Room', 'Check-in', 'Check-out', 'Guests', 'Message', 'Status', 'Notes', 'Source', 'Page']);
while ($row = $stmt->fetch()) {
    // Neutralise spreadsheet formula injection.
    foreach ($row as $k => $v) {
        if ($k !== 'phone' && is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) {
            $row[$k] = "'" . $v;
        }
    }
    fputcsv($out, $row);
}
