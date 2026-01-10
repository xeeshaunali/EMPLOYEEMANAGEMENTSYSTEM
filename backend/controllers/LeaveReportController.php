<?php

class LeaveReportController
{
    public static function casualLeaveReport(PDO $pdo)
    {
        $user = $_SESSION['user'] ?? [];
        $role = $user['role'] ?? '';

        $from        = $_GET['from'] ?? null;
        $to          = $_GET['to'] ?? null;
        $employee_id = $_GET['employee_id'] ?? null; // This is employee_details.id
        $leave_type  = $_GET['leave_type'] ?? null;

        $params = [];
        $where  = " l.status = 'approved' ";

        /* ================= FILTERS ================= */

        if (!empty($leave_type)) {
            $where .= " AND l.leave_type = :ltype ";
            $params[':ltype'] = $leave_type;
        }

        if (!empty($from) && !empty($to)) {
            $where .= " AND l.start_date BETWEEN :from AND :to ";
            $params[':from'] = $from;
            $params[':to']   = $to;
        }

        /* ================= ROLE-BASED SCOPING ================= */

        if ($role === 'employee') {
            // If employee has linked employee_detail_id in session
            if (!empty($user['employee_detail_id'])) {
                $where .= " AND l.employee_detail_id = :eid ";
                $params[':eid'] = (int)$user['employee_detail_id'];
            } else {
                // Fallback: no leaves visible if not linked
                $where .= " AND 1=0 ";
            }
        }
        elseif ($role === 'reader') {
            if (!empty($user['court_id'])) {
                $where .= " AND ed.court_id = :cid ";
                $params[':cid'] = (int)$user['court_id'];
            }

            if (!empty($employee_id)) {
                $where .= " AND l.employee_detail_id = :empid ";
                $params[':empid'] = (int)$employee_id;
            }
        }
        elseif ($role === 'admin') {
            if (!empty($employee_id)) {
                $where .= " AND l.employee_detail_id = :empid ";
                $params[':empid'] = (int)$employee_id;
            }
        }

        /* ================= MAIN QUERY ================= */

        $sql = "
            SELECT
                l.id,
                l.leave_type,
                l.start_date,
                l.end_date,
                l.status,
                l.remarks,
                ed.name,
                COALESCE(p.post_name, ed.post, ed.designation, 'N/A') AS post,
                c.name AS court_name
            FROM leaves l
            INNER JOIN employee_details ed ON ed.id = l.employee_detail_id
            LEFT JOIN posts p ON p.id = ed.post_id
            LEFT JOIN courts c ON c.id = ed.court_id
            WHERE {$where}
            ORDER BY l.start_date DESC, l.id DESC
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // In case of error, return empty result gracefully
            $leaves = [];
            error_log("Leave Report Query Error: " . $e->getMessage());
        }

        // Include the view
        include __DIR__ . '/../views/casual_leave_report.php';
    }
}

/* ======================================================
   LEAVE CONTROLLER - SAVE STAFF LEAVE (Already Correct)
====================================================== */

class LeaveController
{
    public static function saveStaffLeave(PDO $pdo)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ?page=add_leave');
            exit;
        }

        $employee_detail_id = (int)($_POST['employee_id'] ?? 0); // This is employee_details.id
        $leave_type         = trim($_POST['leave_type'] ?? '');
        $start_date         = trim($_POST['start_date'] ?? '');
        $end_date           = trim($_POST['end_date'] ?? '');
        $remarks            = trim($_POST['remarks'] ?? '');

        if ($employee_detail_id <= 0 || !$leave_type || !$start_date || !$end_date) {
            header('Location: ?page=add_leave&error=missing');
            exit;
        }

        if (strtotime($end_date) < strtotime($start_date)) {
            header('Location: ?page=add_leave&error=date_order');
            exit;
        }

        $status = ($leave_type === 'Earned') ? 'pending' : 'approved';

        try {
            $stmt = $pdo->prepare("
                INSERT INTO leaves
                (employee_detail_id, leave_type, start_date, end_date, status, remarks)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $employee_detail_id,
                $leave_type,
                $start_date,
                $end_date,
                $status,
                $remarks
            ]);

            header('Location: ?page=add_leave&success=1');
            exit;

        } catch (PDOException $e) {
            error_log("Save Leave Error: " . $e->getMessage());
            header('Location: ?page=add_leave&error=db');
            exit;
        }
    }
}