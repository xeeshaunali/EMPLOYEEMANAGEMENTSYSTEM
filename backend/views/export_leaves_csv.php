<?php
// export_leaves_csv.php
session_start();
require_once __DIR__ . '/../config/db.php';

$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'reader') {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$court_id = (int)$user['court_id'];
$f_emp  = isset($_GET['f_emp']) && $_GET['f_emp'] !== '' ? (int)$_GET['f_emp'] : null;
$f_from = isset($_GET['f_from']) ? trim($_GET['f_from']) : '';
$f_to   = isset($_GET['f_to'])   ? trim($_GET['f_to'])   : '';

$conds = ["e.court_id = :court", "l.leave_type = 'Casual'"];
$params = [':court' => $court_id];

if ($f_emp) { $conds[] = "e.id = :emp"; $params[':emp'] = $f_emp; }
if ($f_from !== '' && $f_to !== '') {
    $conds[] = "l.start_date BETWEEN :from AND :to";
    $params[':from'] = $f_from; $params[':to'] = $f_to;
} elseif ($f_from !== '') {
    $conds[] = "l.start_date >= :from";
    $params[':from'] = $f_from;
} elseif ($f_to !== '') {
    $conds[] = "l.start_date <= :to";
    $params[':to'] = $f_to;
}

$sql = "
    SELECT e.name AS employee_name, l.start_date, l.end_date, l.status, l.remarks
    FROM leaves l
    JOIN employees e ON e.id = l.employee_id
    WHERE " . implode(' AND ', $conds) . "
    ORDER BY l.start_date DESC
    LIMIT 2000
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=casual_leaves.csv');

$out = fopen('php://output', 'w');
fputcsv($out, ['Employee', 'Start Date', 'End Date', 'Status', 'Remarks']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['employee_name'],
        $r['start_date'],
        $r['end_date'],
        ucfirst($r['status']),
        $r['remarks']
    ]);
}
fclose($out);
exit;
