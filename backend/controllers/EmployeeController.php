<?php
class EmployeeController {
    public static function applyLeave($pdo) {
        if (!isset($_SESSION['user'])) die('Access denied');

        $emp_id = $_SESSION['user']['id'];
        $type   = trim($_POST['type'] ?? '');
        $start  = $_POST['start_date'] ?? null;
        $end    = $_POST['end_date'] ?? null;
        $remarks = trim($_POST['remarks'] ?? '');

        // ✅ Validate dates
        if (!$start || !$end) {
            header('Location: ?page=my_leaves&err=1');
            exit;
        }

        // ✅ Validate leave type exists in leave_types table
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_types WHERE name = ?");
        $stmt->execute([$type]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            // Invalid leave type → prevent tampering
            header('Location: ?page=my_leaves&err=invalid_type');
            exit;
        }

        // ✅ Insert leave request
        $pdo->prepare(
            'INSERT INTO leaves (employee_id, leave_type, start_date, end_date, status, remarks, created_at) 
             VALUES (?,?,?,?,?,?,NOW())'
        )->execute([$emp_id, $type, $start, $end, 'pending', $remarks]);

        header('Location: ?page=my_leaves&success=1');
    }

    public static function myLeaves($pdo) {
        $emp = $_SESSION['user']['id'];

        $leaves = $pdo->prepare(
            'SELECT * FROM leaves WHERE employee_id = ? ORDER BY created_at DESC'
        );
        $leaves->execute([$emp]);
        $rows = $leaves->fetchAll();

        include __DIR__ . '/../views/my_leaves.php';
    }
}
?>
