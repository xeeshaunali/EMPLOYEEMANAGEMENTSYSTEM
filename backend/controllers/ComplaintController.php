<?php
class ComplaintController {
    private static function _getTableColumns($pdo, $table) {
        try {
            $st = $pdo->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            ");
            $st->execute([$table]);
            $res = $st->fetchAll(PDO::FETCH_COLUMN);
            if ($res) return array_map('strtolower', $res);
        } catch (Exception $e) {
            // ignore
        }
        return [];
    }

    public static function save($pdo) {
        session_start();
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: ?page=login'); exit;
        }
        if (($user['role'] ?? '') !== 'reader') {
            echo 'Access denied'; exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?page=complaints'); exit;
        }

        $employee_id = intval($_POST['employee_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = trim($_POST['type'] ?? 'complaint');

        if ($employee_id <= 0 || $subject === '' || $message === '') {
            header('Location: ?page=complaints&err=' . urlencode('Please provide employee, subject and message.')); exit;
        }

        // Inspect employee_details columns to know how to look up court
        $edCols = self::_getTableColumns($pdo, 'employee_details');
        $courtId = null;
        if (in_array('employee_id', $edCols)) {
            $chk = $pdo->prepare("SELECT court_id FROM employee_details WHERE employee_id = ? LIMIT 1");
            $chk->execute([$employee_id]);
            $row = $chk->fetch();
            if ($row) $courtId = $row['court_id'];
        } else {
            // try ed.id
            $chk = $pdo->prepare("SELECT court_id FROM employee_details WHERE id = ? LIMIT 1");
            $chk->execute([$employee_id]);
            $row = $chk->fetch();
            if ($row) $courtId = $row['court_id'];
        }

        if ($courtId === null) {
            // fallback to employees table
            $chk2 = $pdo->prepare("SELECT court_id FROM employees WHERE id = ? LIMIT 1");
            $chk2->execute([$employee_id]);
            $row2 = $chk2->fetch();
            if ($row2) $courtId = $row2['court_id'];
        }

        if ($courtId === null || (int)$courtId !== (int)$user['court_id']) {
            header('Location: ?page=complaints&err=' . urlencode('Invalid employee for your court.')); exit;
        }

        $stmt = $pdo->prepare("INSERT INTO complaints (employee_id, subject, message, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        $stmt->execute([$employee_id, $subject, "[$type] ".$message]);

        header('Location: ?page=complaints&success=1'); exit;
    }
}
