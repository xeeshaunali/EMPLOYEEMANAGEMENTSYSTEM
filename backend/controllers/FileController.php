<?php
class FileController {
    public static function upload($pdo) {
        if (!isset($_SESSION['user'])) die('Access denied');
        $owner = $_SESSION['user']['id'];
        $user  = $_SESSION['user'];

        // Court assignment
        if ($user['role'] === 'admin') {
            $court = intval($_POST['court_id'] ?? 0);
        } else {
            $court = intval($user['court_id'] ?? 0);
        }

        // Employee from employee_details
        $emp_detail_id = intval($_POST['emp_detail_id'] ?? 0);
        if ($emp_detail_id <= 0) $emp_detail_id = null;

        // Validate court_id
        if ($court > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM courts WHERE id = ?");
            $stmt->execute([$court]);
            if (!$stmt->fetchColumn()) {
                $court = null;
            }
        } else {
            $court = null;
        }

        // File category
        $category = trim($_POST['category'] ?? 'General');
        $categorySafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $category);

        // File validation
        if (empty($_FILES['file'])) { header('Location: ?page=files&err=1'); exit; }
        $f = $_FILES['file'];
        if ($f['error'] !== 0) { header('Location: ?page=files&err=2'); exit; }

        // Create category folder if not exists
        $categoryDir = UPLOAD_DIR . '/' . $categorySafe;
        if (!is_dir($categoryDir)) {
            mkdir($categoryDir, 0777, true);
        }

        $safe   = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($f['name']));
        $target = $categoryDir . '/' . time() . '_' . $safe;

        if (move_uploaded_file($f['tmp_name'], $target)) {
            $pdo->prepare('
                INSERT INTO files (owner_id, court_id, emp_detail_id, file_path, file_name, category, created_at)
                VALUES (?,?,?,?,?,?,NOW())
            ')->execute([$owner, $court, $emp_detail_id, $target, $safe, $category]);
        }
        header('Location: ?page=files');
    }

    public static function files($pdo) {
        $user = $_SESSION['user'];
        $perPage = 10;
        $pageNum = max(1, intval($_GET['p'] ?? 1));
        $offset  = ($pageNum - 1) * $perPage;

        // Filters
        $search_name = trim($_GET['search_name'] ?? '');
        $filter_court = intval($_GET['filter_court'] ?? 0);

        $where = [];
        $params = [];

        if ($user['role'] !== 'admin') {
            $court_id = $user['court_id'] ?? 0;
            $where[] = "(f.court_id = ? OR f.owner_id = ?)";
            $params[] = $court_id;
            $params[] = $user['id'];
        }

        if ($filter_court > 0) {
            $where[] = "f.court_id = ?";
            $params[] = $filter_court;
        }

        if ($search_name !== '') {
            $where[] = "ed.name LIKE ?";
            $params[] = '%' . $search_name . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countSql = "SELECT COUNT(*) FROM files f LEFT JOIN employee_details ed ON f.emp_detail_id = ed.id $whereClause";
        $totalStmt = $pdo->prepare($countSql);
        $totalStmt->execute($params);
        $total = $totalStmt->fetchColumn();

        // Data query - LIMIT/OFFSET injected safely as integers
        $sql = "
            SELECT f.*, ed.name as employee_name, e.name as owner_name, c.name as court_name
            FROM files f
            LEFT JOIN employee_details ed ON f.emp_detail_id = ed.id
            LEFT JOIN employees e ON f.owner_id = e.id
            LEFT JOIN courts c ON f.court_id = c.id
            $whereClause
            ORDER BY f.created_at DESC
            LIMIT $perPage OFFSET $offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);  // Only binds the WHERE params, LIMIT is hardcoded safely
        $rows = $stmt->fetchAll();

        // Load courts and categories for form
        $courts = $pdo->query('SELECT * FROM courts ORDER BY name ASC')->fetchAll();
        $cats = $pdo->query("SELECT * FROM file_categories ORDER BY name ASC")->fetchAll();

        include __DIR__ . '/../views/files.php';
    }

    public static function download($pdo) {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) die('File not found');
        $stmt = $pdo->prepare('SELECT * FROM files WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $f = $stmt->fetch();
        if (!$f) die('File not found');
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin' && $user['court_id'] != $f['court_id'] && $user['id'] != $f['owner_id']) {
            die('Access denied');
        }
        if (!file_exists($f['file_path'])) die('Physical file missing');
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($f['file_name']) . '"');
        readfile($f['file_path']);
        exit;
    }

    public static function delete($pdo) {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) die('File not found');
        $stmt = $pdo->prepare('SELECT * FROM files WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $f = $stmt->fetch();
        if (!$f) die('File not found');
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin' && $user['court_id'] != $f['court_id'] && $user['id'] != $f['owner_id']) {
            die('Access denied');
        }
        if (file_exists($f['file_path'])) {
            unlink($f['file_path']);
        }
        $stmt = $pdo->prepare('DELETE FROM files WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: ?page=files&deleted=1');
    }
}
?>