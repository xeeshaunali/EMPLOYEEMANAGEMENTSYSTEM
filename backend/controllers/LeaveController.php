<?php
// backend/controllers/LeaveController.php

class LeaveController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function saveStaffLeave()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?page=add_leave&error=invalid');
            exit;
        }

        $employee_id = $_POST['employee_id'] ?? null;
        $leave_type  = $_POST['leave_type'] ?? null;
        $start_date  = $_POST['start_date'] ?? null;
        $end_date    = $_POST['end_date'] ?? null;
        $remarks     = trim($_POST['remarks'] ?? '');

        if (!$employee_id || !$leave_type || !$start_date || !$end_date) {
            header('Location: ?page=add_leave&error=missing');
            exit;
        }

        if ($end_date < $start_date) {
            header('Location: ?page=add_leave&error=date_order');
            exit;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO leaves
                (employee_detail_id, leave_type, start_date, end_date, remarks)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $employee_id,
                $leave_type,
                $start_date,
                $end_date,
                $remarks
            ]);

            header('Location: ?page=add_leave&success=1');
            exit;

        } catch (Exception $e) {
            header('Location: ?page=add_leave&error=db');
            exit;
        }
    }
}
