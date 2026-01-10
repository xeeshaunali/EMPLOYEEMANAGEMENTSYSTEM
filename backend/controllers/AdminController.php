<?php
class AdminController {
    public static function _isAdmin() {
        return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
    }

    // Helper: get columns for a table (lowercased)
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

    // Courts
    public static function courts($pdo) {
        if (!self::_isAdmin()) die('Access denied');
        $courts = $pdo->query('SELECT * FROM courts')->fetchAll();
        include __DIR__ . '/../views/admin_courts.php';
    }

    public static function saveCourt($pdo) {
        if (!self::_isAdmin()) die('Access denied');
        $name = trim($_POST['name'] ?? '');
        $district = trim($_POST['district'] ?? '');
        if (!$name) { header('Location: ?page=courts&err=1'); exit; }

        if (!empty($_POST['id'])) {
            $pdo->prepare('UPDATE courts SET name=?, district=? WHERE id=?')
                ->execute([$name, $district, $_POST['id']]);
        } else {
            $pdo->prepare('INSERT INTO courts (name, district) VALUES (?, ?)')
                ->execute([$name, $district]);
        }
        header('Location: ?page=courts');
    }

    public static function deleteCourt($pdo) {
        if (!self::_isAdmin()) die('Access denied');
        $id = intval($_GET['id'] ?? 0);
        if ($id) $pdo->prepare('DELETE FROM courts WHERE id=?')->execute([$id]);
        header('Location: ?page=courts');
    }

    // Employees (Admin page)
    public static function employees($pdo) {
        if (!self::_isAdmin()) die('Access denied');

        // fetch employees with court names (if any)
        $employees = $pdo->query('SELECT e.*, c.name as court_name 
                                  FROM employees e 
                                  LEFT JOIN courts c ON e.court_id = c.id
                                  ORDER BY e.id DESC')->fetchAll();

        $courts = $pdo->query('SELECT * FROM courts ORDER BY name ASC')->fetchAll();
        $posts = $pdo->query('SELECT * FROM posts ORDER BY bps ASC')->fetchAll();

        // Roles available in the system (keeps view consistent)
        $roles = ['employee','reader','librarian','admin'];

        include __DIR__ . '/../views/admin_employees.php';
    }

    public static function saveEmployee($pdo) {
        if (!self::_isAdmin()) die('Access denied');

        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role = trim($_POST['role'] ?? 'employee');
        $court_id = $_POST['court_id'] !== '' ? intval($_POST['court_id']) : null;
        $post_bps = $_POST['post_bps'] ?? '';

        // safe explode — if format not correct, set defaults
        $post = null; $bps = null;
        if ($post_bps !== '') {
            $parts = explode('|', $post_bps);
            $post = trim($parts[0] ?? '');
            $bps = isset($parts[1]) ? intval($parts[1]) : null;
        }

        // validate
        if (!$name || !$username) {
            header('Location: ?page=employees&err=1'); exit;
        }

        // allow only known roles (prevents injection)
        $allowedRoles = ['employee','reader','librarian','admin'];
        if (!in_array($role, $allowedRoles, true)) $role = 'employee';

        // check duplicate username (case-insensitive)
        if (empty($_POST['id'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE LOWER(username) = LOWER(?)");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                header('Location: ?page=employees&err=duplicate_username'); exit;
            }
        } else {
            // updating: ensure username not used by other user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE LOWER(username) = LOWER(?) AND id != ?");
            $stmt->execute([$username, intval($_POST['id'])]);
            if ($stmt->fetchColumn() > 0) {
                header('Location: ?page=employees&err=duplicate_username'); exit;
            }
        }

        if (!empty($_POST['id'])) {
            // update existing
            $pdo->prepare('UPDATE employees SET name=?, username=?, role=?, court_id=?, bps=?, post=? WHERE id=?')
                ->execute([$name, $username, $role, $court_id, $bps, $post, intval($_POST['id'])]);
        } else {
            // create new user with default password (you may change this behavior)
            $defaultPassword = 'password123';
            $pass = password_hash($defaultPassword, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO employees (name, username, password_hash, role, court_id, bps, post) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$name, $username, $pass, $role, $court_id, $bps, $post]);
            // Optionally: set a flash message to show default password to admin
            $_SESSION['flash'] = "Created user '{$username}' with default password '{$defaultPassword}'. Please tell the user to change it.";
        }

        header('Location: ?page=employees');
    }

    public static function deleteEmployee($pdo) {
        if (!self::_isAdmin()) die('Access denied');
        $id = intval($_GET['id'] ?? 0);
        if ($id) {
            // Prevent deleting yourself accidentally
            if (isset($_SESSION['user']['id']) && intval($_SESSION['user']['id']) === $id) {
                $_SESSION['flash'] = "You cannot delete your own account.";
            } else {
                $pdo->prepare('DELETE FROM employees WHERE id=?')->execute([$id]);
            }
        }
        header('Location: ?page=employees');
    }

    // Leave Requests
    public static function leaveRequests($pdo) {
        if (!self::_isAdmin()) die('Access denied');
        $stmt = $pdo->query("SELECT l.*, e.name AS emp_name
                             FROM leaves l
                             LEFT JOIN employees e ON l.employee_id = e.id
                             ORDER BY l.start_date DESC");
        $leaves = $stmt->fetchAll(); // matches admin_leaves.php
        include __DIR__ . '/../views/admin_leaves.php';
    }

    public static function approveLeave($pdo) {
        if (!self::_isAdmin()) die('Access denied');
        $id = intval($_GET['id'] ?? 0);
        $status = $_GET['s'] ?? 'approved'; // approve or reject
        if ($id) {
            $pdo->prepare("UPDATE leaves SET status=? WHERE id=?")->execute([$status, $id]);
        }
        header('Location: ?page=leave_requests');
    }

    // Attendance
    public static function attendance($pdo) {
        if (!self::_isAdmin()) die('Access denied');
        $employees = $pdo->query("SELECT * FROM employees ORDER BY name ASC")->fetchAll();
        $att = $pdo->query("SELECT a.date, e.name, a.status
                            FROM attendance a
                            LEFT JOIN employees e ON a.employee_id = e.id
                            ORDER BY a.date DESC")->fetchAll();
        include __DIR__ . '/../views/admin_attendance.php';
    }

    // Employee Details save/delete
    public static function saveEmployeeDetail($pdo){
        $id = $_POST['id'] ?? null;

        // Handle file upload
        $picName = null;
        if (!empty($_FILES['pic']['name'])) {
            $picName = time().'_'.basename($_FILES['pic']['name']);
            $target = __DIR__ . '/../../uploads/'.$picName;
            move_uploaded_file($_FILES['pic']['tmp_name'], $target);
        }

        if ($id) {
            // Update
            $sql = "UPDATE employee_details 
                       SET name=?, father_name=?, post_id=?, court_id=?, 
                           date_of_birth=?, date_of_appointment=?, date_of_retirement=?, 
                           cnic=?, updated_at=NOW()".
                    ($picName ? ", pic=?" : "") . "
                     WHERE id=?";
            $params = [
                $_POST['name'], $_POST['father_name'], $_POST['post_id'], $_POST['court_id'],
                $_POST['date_of_birth'] ?: null, $_POST['date_of_appointment'], $_POST['date_of_retirement'],
                $_POST['cnic']
            ];
            if ($picName) $params[] = $picName;
            $params[] = $id;
            $st = $pdo->prepare($sql);
            $st->execute($params);
        } else {
            // Insert
            $sql = "INSERT INTO employee_details 
                      (name,father_name,post_id,court_id,bps,date_of_birth,date_of_appointment,date_of_retirement,cnic,pic) 
                    VALUES (?,?,?,?, (SELECT bps FROM posts WHERE id=?),?,?,?,?,?)";
            $st = $pdo->prepare($sql);
            $st->execute([
                $_POST['name'], $_POST['father_name'], $_POST['post_id'], $_POST['court_id'],
                $_POST['post_id'],
                $_POST['date_of_birth'] ?: null, $_POST['date_of_appointment'], $_POST['date_of_retirement'],
                $_POST['cnic'], $picName
            ]);
        }

        header("Location: ?page=employee_details");
    }

    public static function deleteEmployeeDetail($pdo){
        if (!empty($_GET['id'])) {
            $st = $pdo->prepare("DELETE FROM employee_details WHERE id=?");
            $st->execute([$_GET['id']]);
        }
        header("Location: ?page=employee_details");
    }

    // Transfer Posting
    public static function saveTransferPosting($pdo) {
        if (!self::_isAdmin()) die('Access denied');

        $transfers     = $_POST['transfer_to'] ?? [];
        $remarks       = $_POST['remarks'] ?? [];
        $transferDate  = $_POST['transfer_date'] ?? date('Y-m-d');
        $transferType  = $_POST['transfer_type'] ?? 'Transfer';

        foreach ($transfers as $empId => $newCourtId) {
            if ($newCourtId) {
                // Get old court
                $stmt = $pdo->prepare("SELECT court_id FROM employee_details WHERE id=?");
                $stmt->execute([$empId]);
                $oldCourtId = $stmt->fetchColumn();

                // Update employee current court
                $pdo->prepare("UPDATE employee_details SET court_id=? WHERE id=?")
                    ->execute([$newCourtId, $empId]);

                // Save in transfer history table
                $pdo->prepare("INSERT INTO transfer_history 
                    (employee_id, old_court_id, new_court_id, type, remarks, transfer_date) 
                    VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([
                        $empId, 
                        $oldCourtId, 
                        $newCourtId, 
                        $transferType, 
                        $remarks[$empId] ?? null, 
                        $transferDate
                    ]);
            }
        }

        header("Location: ?page=transfer_posting&success=1");
        exit;
    }

    // Attendance Marking
    public static function markAttendance($pdo) {
        if (!self::_isAdmin()) die('Access denied');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $employee_id = intval($_POST['employee_id']);
            $status = $_POST['status'] ?? 'Present';
            $date = $_POST['date'] ?? date('Y-m-d');

            $pdo->prepare("INSERT INTO attendance (employee_id, date, status)
                           VALUES (?, ?, ?)")->execute([$employee_id, $date, $status]);
            header('Location: ?page=attendance');
            exit;
        }
        $employees = $pdo->query("SELECT * FROM employees ORDER BY name ASC")->fetchAll();
        include __DIR__ . '/../views/admin_mark_attendance.php';
    }

    // ========== Complaints Management (Admin) ==========
    public static function manageComplaints($pdo) {
        if (!self::_isAdmin()) die('Access denied');

        // Detect which column to join employee_details on (employee_id vs id)
        $edCols = self::_getTableColumns($pdo, 'employee_details');
        if (in_array('employee_id', $edCols)) {
            $joinOn = "ed.employee_id = c.employee_id";
        } else {
            // fallback: ed.id matches complaints.employee_id
            $joinOn = "ed.id = c.employee_id";
        }

        $sql = "
            SELECT c.*,
                   COALESCE(ed.name, e.name) AS employee_name
            FROM complaints c
            LEFT JOIN employee_details ed ON {$joinOn}
            LEFT JOIN employees e ON e.id = c.employee_id
            ORDER BY c.created_at DESC
        ";
        $stmt = $pdo->query($sql);
        $complaints = $stmt->fetchAll();
        include __DIR__ . '/../views/admin_complaints.php';
    }

    public static function updateComplaint($pdo) {
        if (!self::_isAdmin()) die('Access denied');
        $id = intval($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($id <= 0 || !in_array($action, ['resolve','reopen'])) {
            header('Location: ?page=manage_complaints'); exit;
        }

        if ($action === 'resolve') {
            $pdo->prepare("UPDATE complaints SET status='resolved', resolved_at = NOW() WHERE id = ?")->execute([$id]);
        } else {
            $pdo->prepare("UPDATE complaints SET status='pending', resolved_at = NULL WHERE id = ?")->execute([$id]);
        }

        header('Location: ?page=manage_complaints');
    }
}
