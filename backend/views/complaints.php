<?php
// backend/views/complaints.php
include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php';

$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: ?page=login'); exit; }

$isAdmin  = ($user['role'] ?? '') === 'admin';
$isReader = ($user['role'] ?? '') === 'reader';

$success = $error = null;

/** Fetch employees of a court (prefer employee_details, fallback to employees) */
function fetchCourtEmployees(PDO $pdo, $courtId) {
    $stmt = $pdo->prepare("SELECT id, name FROM employee_details WHERE court_id = ? ORDER BY name ASC");
    $stmt->execute([$courtId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) return $rows;
    $stmt = $pdo->prepare("SELECT id, name FROM employees WHERE court_id = ? ORDER BY name ASC");
    $stmt->execute([$courtId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Get employee's court ID and details — allows override of court_id */
function getEmployeeCourtDetails(PDO $pdo, $employeeId, $overrideCourtId = null) {
    // If admin explicitly selected a court, use that court's info
    if ($overrideCourtId) {
        // Get employee name
        $stmt = $pdo->prepare("SELECT name FROM employee_details WHERE id = ?");
        $stmt->execute([$employeeId]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$emp) {
            $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $stmt->execute([$employeeId]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        // Get court name
        $stmt = $pdo->prepare("SELECT name FROM courts WHERE id = ?");
        $stmt->execute([$overrideCourtId]);
        $court = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'court_id'      => $overrideCourtId,
            'employee_name' => $emp['name'] ?? null,
            'court_name'    => $court['name'] ?? null,
        ];
    }

    // Check in employee_details first
    $stmt = $pdo->prepare("
        SELECT ed.court_id, ed.name as employee_name, c.name as court_name 
        FROM employee_details ed 
        LEFT JOIN courts c ON c.id = ed.court_id 
        WHERE ed.id = ?
    ");
    $stmt->execute([$employeeId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        return $result;
    }
    
    // Then check in employees table
    $stmt = $pdo->prepare("
        SELECT e.court_id, e.name as employee_name, c.name as court_name 
        FROM employees e 
        LEFT JOIN courts c ON c.id = e.court_id 
        WHERE e.id = ?
    ");
    $stmt->execute([$employeeId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/** Generate complaint ID (format: COMP-YYYY-XXXX) */
function generateComplaintId(PDO $pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM complaints WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
    return 'COMP-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

/** Allowed category list */
$categoryOptions = [
    'CFMS-DC',
    'Printer Repair',
    'Cartridge Refill',
    'Internet Connectivity',
    'Any Other'
];

/** Handle file upload */
function handleFileUpload($file) {
    if ($file['error'] !== UPLOAD_ERR_OK && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        return ['error' => 'File upload error: ' . $file['error']];
    }
    
    if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['size'] == 0) {
        return ['success' => null]; // No file uploaded
    }
    
    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return ['error' => 'File size must be less than 5MB'];
    }
    
    // Validate file type
    $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedTypes)) {
        return ['error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes)];
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/a/project_root/uploads/complaints/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => $fileName];
    } else {
        return ['error' => 'Failed to move uploaded file'];
    }
}

/** Delete complaint with file */
function deleteComplaint(PDO $pdo, $id) {
    try {
        // First get the attachment filename to delete the file
        $stmt = $pdo->prepare("SELECT attachment FROM complaints WHERE id = ?");
        $stmt->execute([$id]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete the record from database
        $stmt = $pdo->prepare("DELETE FROM complaints WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result && !empty($complaint['attachment'])) {
            // Delete the physical file if it exists
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/a/project_root/uploads/complaints/' . $complaint['attachment'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/** Reader: create complaint/request */
if ($isReader && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_item'])) {
    $employee_id    = (int)$user['id'];
    $kind           = trim($_POST['kind'] ?? 'request');
    $category       = trim($_POST['category'] ?? '');
    $subject        = trim($_POST['subject'] ?? '');
    $details        = trim($_POST['details'] ?? '');
    $priority       = trim($_POST['priority'] ?? 'Normal');
    $requested_date = trim($_POST['requested_date'] ?? '');
    $cfms_case_code = ($category === 'CFMS-DC') ? trim($_POST['cfms_case_code'] ?? '') : null;
    $complaint_id   = generateComplaintId($pdo);
    
    // Get employee's court details (no override for reader — use their own court)
    $empDetails = getEmployeeCourtDetails($pdo, $employee_id);

    if (!in_array($category, $categoryOptions, true)) {
        $error = "Please select a valid category.";
    } elseif ($subject === '' || $details === '') {
        $error = "Please provide both Subject and Details.";
    } elseif ($requested_date === '') {
        $error = "Please select the requested date.";
    } elseif ($category === 'CFMS-DC' && $cfms_case_code === '') {
        $error = "Please provide CFMS-DC Case Code.";
    } elseif (!$empDetails || !$empDetails['court_id']) {
        $error = "Your account is not associated with any court.";
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $requested_date);
        if (!$d || $d->format('Y-m-d') !== $requested_date) {
            $error = "Requested date is not valid.";
        } else {
            try {
                // Handle file upload
                $attachment = null;
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadResult = handleFileUpload($_FILES['attachment']);
                    if (isset($uploadResult['error'])) {
                        $error = $uploadResult['error'];
                    } else {
                        $attachment = $uploadResult['success'];
                    }
                }
                
                if (!$error) {
                    $ins = $pdo->prepare("
                        INSERT INTO complaints
                            (complaint_id, employee_id, kind, category, subject, description, priority, requested_date, cfms_case_code, attachment, status, created_at)
                        VALUES
                            (:complaint_id, :employee_id, :kind, :category, :subject, :description, :priority, :requested_date, :cfms_case_code, :attachment, 'submitted', NOW())
                    ");
                    $ins->execute([
                        ':complaint_id'   => $complaint_id,
                        ':employee_id'    => $employee_id,
                        ':kind'           => $kind,
                        ':category'       => $category,
                        ':subject'        => $subject,
                        ':description'    => $details,
                        ':priority'       => $priority,
                        ':requested_date' => $requested_date,
                        ':cfms_case_code' => $cfms_case_code,
                        ':attachment'     => $attachment
                    ]);
                    $success = ucfirst($kind) . " submitted successfully. Complaint ID: " . $complaint_id;
                }
            } catch (PDOException $e) {
                $error = "Failed to submit: " . $e->getMessage();
            }
        }
    }
}

/** Admin: create complaint/request */
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_create_item'])) {
    $employee_id      = (int)($_POST['employee_id'] ?? 0);
    $selected_court_id = (int)($_POST['court_id'] ?? 0); // <-- FIX: capture admin's selected court
    $kind             = trim($_POST['kind'] ?? 'request');
    $category         = trim($_POST['category'] ?? '');
    $subject          = trim($_POST['subject'] ?? '');
    $details          = trim($_POST['details'] ?? '');
    $priority         = trim($_POST['priority'] ?? 'Normal');
    $requested_date   = trim($_POST['requested_date'] ?? '');
    $status           = trim($_POST['status'] ?? 'submitted');
    $cfms_case_code   = ($category === 'CFMS-DC') ? trim($_POST['cfms_case_code'] ?? '') : null;
    $complaint_id     = generateComplaintId($pdo);
    
    // FIX: Pass the admin-selected court_id as override so the complaint is
    // associated with the court chosen in the dropdown, not the employee's stored court.
    $overrideCourtId = $selected_court_id > 0 ? $selected_court_id : null;
    $empDetails = getEmployeeCourtDetails($pdo, $employee_id, $overrideCourtId);

    if ($employee_id <= 0) {
        $error = "Please select an employee.";
    } elseif ($selected_court_id <= 0) {
        $error = "Please select a court.";
    } elseif (!in_array($category, $categoryOptions, true)) {
        $error = "Please select a valid category.";
    } elseif ($subject === '' || $details === '') {
        $error = "Please provide both Subject and Details.";
    } elseif ($requested_date === '') {
        $error = "Please select the requested date.";
    } elseif ($category === 'CFMS-DC' && $cfms_case_code === '') {
        $error = "Please provide CFMS-DC Case Code.";
    } elseif (!$empDetails || !$empDetails['court_id']) {
        $error = "Selected employee is not associated with any court.";
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $requested_date);
        if (!$d || $d->format('Y-m-d') !== $requested_date) {
            $error = "Requested date is not valid.";
        } else {
            try {
                // Handle file upload
                $attachment = null;
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadResult = handleFileUpload($_FILES['attachment']);
                    if (isset($uploadResult['error'])) {
                        $error = $uploadResult['error'];
                    } else {
                        $attachment = $uploadResult['success'];
                    }
                }
                
                if (!$error) {
                    // FIX: Use the admin-selected court_id (via $empDetails['court_id'] which is now the override)
                    // We store it alongside the employee_id so queries display the correct court.
                    // If your complaints table has a court_id column, include it here.
                    // If not, we update the employee's court association temporarily — 
                    // the safest approach without schema changes is to store court_id in complaints.
                    // Check if complaints table has court_id column and insert accordingly:
                    $columns = $pdo->query("SHOW COLUMNS FROM complaints LIKE 'court_id'")->fetchAll();
                    $hasCourtIdColumn = !empty($columns);

                    if ($hasCourtIdColumn) {
                        $ins = $pdo->prepare("
                            INSERT INTO complaints
                                (complaint_id, employee_id, court_id, kind, category, subject, description, priority, requested_date, cfms_case_code, attachment, status, created_at)
                            VALUES
                                (:complaint_id, :employee_id, :court_id, :kind, :category, :subject, :description, :priority, :requested_date, :cfms_case_code, :attachment, :status, NOW())
                        ");
                        $ins->execute([
                            ':complaint_id'   => $complaint_id,
                            ':employee_id'    => $employee_id,
                            ':court_id'       => $empDetails['court_id'],
                            ':kind'           => $kind,
                            ':category'       => $category,
                            ':subject'        => $subject,
                            ':description'    => $details,
                            ':priority'       => $priority,
                            ':requested_date' => $requested_date,
                            ':cfms_case_code' => $cfms_case_code,
                            ':attachment'     => $attachment,
                            ':status'         => $status
                        ]);
                    } else {
                        // No court_id column — update the employee's court_id to match admin selection
                        // before inserting (ensures the complaint lookup shows the right court)
                        $updateEmpCourt = $pdo->prepare("
                            UPDATE employee_details SET court_id = :court_id WHERE id = :id
                        ");
                        $affected = $updateEmpCourt->execute([
                            ':court_id' => $empDetails['court_id'],
                            ':id'       => $employee_id
                        ]);
                        if (!$affected || $updateEmpCourt->rowCount() === 0) {
                            // Try employees table if employee_details had no match
                            $pdo->prepare("UPDATE employees SET court_id = :court_id WHERE id = :id")
                                ->execute([':court_id' => $empDetails['court_id'], ':id' => $employee_id]);
                        }

                        $ins = $pdo->prepare("
                            INSERT INTO complaints
                                (complaint_id, employee_id, kind, category, subject, description, priority, requested_date, cfms_case_code, attachment, status, created_at)
                            VALUES
                                (:complaint_id, :employee_id, :kind, :category, :subject, :description, :priority, :requested_date, :cfms_case_code, :attachment, :status, NOW())
                        ");
                        $ins->execute([
                            ':complaint_id'   => $complaint_id,
                            ':employee_id'    => $employee_id,
                            ':kind'           => $kind,
                            ':category'       => $category,
                            ':subject'        => $subject,
                            ':description'    => $details,
                            ':priority'       => $priority,
                            ':requested_date' => $requested_date,
                            ':cfms_case_code' => $cfms_case_code,
                            ':attachment'     => $attachment,
                            ':status'         => $status
                        ]);
                    }
                    $success = ucfirst($kind) . " created successfully for employee. Complaint ID: " . $complaint_id;
                }
            } catch (PDOException $e) {
                $error = "Failed to create: " . $e->getMessage();
            }
        }
    }
}

/** Admin actions (update status, etc) */
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
    $id             = (int)($_POST['id'] ?? 0);
    $status         = trim($_POST['status'] ?? '');
    $notes          = trim($_POST['resolution_notes'] ?? '');
    $completed_date = trim($_POST['completed_date'] ?? '');

    if ($id <= 0 || $status === '') {
        $error = "Invalid request.";
    } else {
        if ($status === 'completed') {
            if ($completed_date === '') {
                $completed_date = date('Y-m-d');
            } else {
                $cd = DateTime::createFromFormat('Y-m-d', $completed_date);
                if (!$cd || $cd->format('Y-m-d') !== $completed_date) {
                    $error = "Completed date is invalid.";
                }
            }
        } else {
            $completed_date = null;
        }

        if (!$error) {
            try {
                $upd = $pdo->prepare("
                    UPDATE complaints
                       SET status = :status,
                           resolution_notes = :notes,
                           completed_date = :completed_date,
                           updated_at = NOW()
                     WHERE id = :id
                ");
                if ($completed_date === null) {
                    $upd->bindValue(':completed_date', null, PDO::PARAM_NULL);
                } else {
                    $upd->bindValue(':completed_date', $completed_date);
                }
                $upd->bindValue(':status', $status);
                $upd->bindValue(':notes', $notes);
                $upd->bindValue(':id', $id, PDO::PARAM_INT);
                $upd->execute();
                $success = "Updated successfully.";
            } catch (PDOException $e) {
                $error = "Update failed: " . $e->getMessage();
            }
        }
    }
}

/** Admin: delete complaint */
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_complaint'])) {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        $error = "Invalid complaint ID.";
    } else {
        if (deleteComplaint($pdo, $id)) {
            $success = "Complaint deleted successfully.";
        } else {
            $error = "Failed to delete complaint.";
        }
    }
}

/** Handle print request */
$printComplaint = null;
if (isset($_GET['print']) && !empty($_GET['print'])) {
    $printId = $_GET['print'];
    $printStmt = $pdo->prepare("
        SELECT c.*,
               COALESCE(ed.name, e.name) AS employee_name,
               ct.name AS court_name,
               ed.father_name,
               e.post,
               e.bps,
               e.contact,
               e.cnic
        FROM complaints c
        LEFT JOIN employee_details ed ON ed.id = c.employee_id
        LEFT JOIN employees e        ON e.id  = c.employee_id
        LEFT JOIN courts ct          ON ct.id = COALESCE(c.court_id, ed.court_id, e.court_id)
        WHERE c.complaint_id = ? OR c.id = ?
    ");
    $printStmt->execute([$printId, $printId]);
    $printComplaint = $printStmt->fetch(PDO::FETCH_ASSOC);
}

/** Data for reader form */
$courtEmployees = [];
if ($isReader) {
    $courtEmployees = fetchCourtEmployees($pdo, $user['court_id']);
}

/** Admin data */
$courts = $pdo->query("SELECT id, name FROM courts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get all employees with their court information - INCLUDING ADMIN USERS
$allEmployeesStmt = $pdo->query("
    SELECT ed.id, ed.name, ed.court_id, c.name as court_name, 'employee_detail' as source
    FROM employee_details ed
    LEFT JOIN courts c ON c.id = ed.court_id
    UNION
    SELECT e.id, e.name, e.court_id, c.name as court_name, 'employee' as source
    FROM employees e
    LEFT JOIN courts c ON c.id = e.court_id
    WHERE e.role IN ('reader', 'admin', 'employee', 'librarian')
    ORDER BY name
");
$allEmployees = $allEmployeesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for dashboard
$stats = [];
if ($isAdmin) {
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN priority = 'Urgent' THEN 1 ELSE 0 END) as urgent
        FROM complaints
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
}

/** List items with search */
$items = [];
try {
    // FIX: All listing queries now prefer c.court_id if present, then fall back to employee's court
    if ($isAdmin) {
        $isReport = (isset($_GET['report']) && $_GET['report'] === '1');
        $isSearch = (isset($_GET['search']) && $_GET['search'] === '1');
        
        if ($isReport || $isSearch) {
            $where = "1=1";
            $params = [];
            
            // Search by Complaint ID
            if (!empty($_GET['search_complaint_id'])) {
                $where .= " AND c.complaint_id LIKE :complaint_id";
                $params[':complaint_id'] = '%' . $_GET['search_complaint_id'] . '%';
            }
            
            // Date range filters
            if (!empty($_GET['report_from'])) {
                $where .= " AND c.created_at >= :from";
                $params[':from'] = $_GET['report_from'] . ' 00:00:00';
            }
            if (!empty($_GET['report_to'])) {
                $where .= " AND c.created_at <= :to";
                $params[':to'] = $_GET['report_to'] . ' 23:59:59';
            }
            
            // Court filter — FIX: check c.court_id first, then employee's court
            if (!empty($_GET['report_court_id'])) {
                $where .= " AND COALESCE(c.court_id, ed.court_id, e.court_id) = :court_id";
                $params[':court_id'] = (int)$_GET['report_court_id'];
            }
            
            // Employee filter
            if (!empty($_GET['report_employee_id'])) {
                $where .= " AND c.employee_id = :employee_id";
                $params[':employee_id'] = (int)$_GET['report_employee_id'];
            }
            
            // Status filter
            if (!empty($_GET['report_status'])) {
                $where .= " AND c.status = :status";
                $params[':status'] = $_GET['report_status'];
            }
            
            // Kind filter
            if (!empty($_GET['report_kind'])) {
                $where .= " AND c.kind = :kind";
                $params[':kind'] = $_GET['report_kind'];
            }
            
            // Priority filter
            if (!empty($_GET['report_priority'])) {
                $where .= " AND c.priority = :priority";
                $params[':priority'] = $_GET['report_priority'];
            }

            $listSql = "
                SELECT c.*,
                       COALESCE(ed.name, e.name) AS employee_name,
                       ct.name AS court_name,
                       COALESCE(c.court_id, ed.court_id, e.court_id) AS effective_court_id
                FROM complaints c
                LEFT JOIN employee_details ed ON ed.id = c.employee_id
                LEFT JOIN employees e        ON e.id  = c.employee_id
                LEFT JOIN courts ct          ON ct.id = COALESCE(c.court_id, ed.court_id, e.court_id)
                WHERE $where
                ORDER BY 
                    CASE c.priority 
                        WHEN 'Urgent' THEN 1 
                        WHEN 'High' THEN 2 
                        WHEN 'Normal' THEN 3 
                        WHEN 'Low' THEN 4 
                    END,
                    c.created_at DESC
            ";
            $stmt = $pdo->prepare($listSql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $listSql = "
                SELECT c.*,
                       COALESCE(ed.name, e.name) AS employee_name,
                       ct.name AS court_name
                FROM complaints c
                LEFT JOIN employee_details ed ON ed.id = c.employee_id
                LEFT JOIN employees e        ON e.id  = c.employee_id
                LEFT JOIN courts ct          ON ct.id = COALESCE(c.court_id, ed.court_id, e.court_id)
                WHERE c.status IN ('submitted','in_review')
                ORDER BY 
                    CASE c.priority 
                        WHEN 'Urgent' THEN 1 
                        WHEN 'High' THEN 2 
                        WHEN 'Normal' THEN 3 
                        WHEN 'Low' THEN 4 
                    END,
                    c.created_at DESC
            ";
            $items = $pdo->query($listSql)->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $listSql = "
            SELECT c.*,
                   COALESCE(ed.name, e.name) AS employee_name,
                   ct.name AS court_name
            FROM complaints c
            LEFT JOIN employee_details ed ON ed.id = c.employee_id
            LEFT JOIN employees e        ON e.id  = c.employee_id
            LEFT JOIN courts ct          ON ct.id = COALESCE(c.court_id, ed.court_id, e.court_id)
            WHERE COALESCE(c.court_id, ed.court_id, e.court_id) = :cid
            ORDER BY 
                CASE c.priority 
                    WHEN 'Urgent' THEN 1 
                    WHEN 'High' THEN 2 
                    WHEN 'Normal' THEN 3 
                    WHEN 'Low' THEN 4 
                END,
                c.created_at DESC
        ";
        $listStmt = $pdo->prepare($listSql);
        $listStmt->execute([':cid' => $user['court_id']]);
        $items = $listStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $items = [];
    $error = "Failed to load records.";
}

// Get today's date for default filters
$today = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
?>

<style>
    .page-title {
        background: linear-gradient(135deg, #005566, #007bff);
        color: white;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .complaint-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
    }
    .card-header-gradient {
        background: linear-gradient(90deg, #005566, #007bff);
        color: white;
        padding: 1.2rem 1.5rem;
        font-weight: 600;
    }
    .form-label {
        font-weight: 600;
        color: #333;
        font-size: 0.95rem;
    }
    .form-control, .form-select {
        border-radius: 8px;
        padding: 0.6rem 1rem;
        font-size: 0.95rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: #005566;
        box-shadow: 0 0 0 0.2rem rgba(0, 85, 102, 0.2);
    }
    .btn-primary {
        background: linear-gradient(90deg, #005566, #007bff);
        border: none;
        border-radius: 8px;
        padding: 0.6rem 1.5rem;
        font-weight: 500;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,123,255,0.3);
    }
    .btn-success {
        background: linear-gradient(90deg, #28a745, #20c997);
        border: none;
        border-radius: 8px;
        padding: 0.6rem 1.5rem;
        font-weight: 500;
    }
    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(40,167,69,0.3);
    }
    .btn-info {
        background: linear-gradient(90deg, #17a2b8, #138496);
        border: none;
        border-radius: 8px;
        padding: 0.6rem 1.5rem;
        font-weight: 500;
        color: white;
    }
    .btn-info:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(23,162,184,0.3);
    }
    .btn-warning {
        background: linear-gradient(90deg, #ffc107, #ffb300);
        border: none;
        border-radius: 8px;
        padding: 0.6rem 1.5rem;
        font-weight: 500;
        color: #333;
    }
    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(255,193,7,0.3);
    }
    .btn-danger {
        background: linear-gradient(90deg, #dc3545, #c82333);
        border: none;
        border-radius: 8px;
        padding: 0.6rem 1.5rem;
        font-weight: 500;
        color: white;
    }
    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(220,53,69,0.3);
    }
    .table {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .table thead {
        background: linear-gradient(90deg, #f8f9fa, #e9ecef);
        font-weight: 600;
        color: #333;
    }
    .table tbody tr:hover {
        background-color: #f1f8ff !important;
        transition: background-color 0.2s;
    }
    .badge {
        padding: 0.5em 0.8em;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .badge-submitted { background: #6c757d; color: white; }
    .badge-in_review { background: #17a2b8; color: white; }
    .badge-approved { background: #28a745; color: white; }
    .badge-rejected { background: #dc3545; color: white; }
    .badge-completed { background: #007bff; color: white; }

    .priority-low { background: #d4edda; color: #155724; }
    .priority-normal { background: #d1ecf1; color: #0c5460; }
    .priority-high { background: #fff3cd; color: #856404; }
    .priority-urgent { background: #f8d7da; color: #721c24; }

    .stats-card {
        background: white;
        border-radius: 10px;
        padding: 1.2rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        margin-bottom: 1.5rem;
        border-left: 4px solid #005566;
    }
    .stats-number {
        font-size: 2rem;
        font-weight: bold;
        color: #005566;
        line-height: 1;
    }
    .stats-label {
        color: #666;
        font-size: 0.9rem;
        margin-top: 0.3rem;
    }
    .stats-urgent {
        border-left-color: #dc3545;
    }
    .stats-urgent .stats-number {
        color: #dc3545;
    }

    .report-filters, .search-filters {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 1.5rem;
        border: 1px solid #dee2e6;
        margin-bottom: 1.5rem;
    }
    .admin-form {
        background: #f0f7ff;
        border-radius: 10px;
        padding: 1.5rem;
        border: 2px dashed #005566;
        margin-bottom: 2rem;
    }
    .attachment-link {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        background: #e9ecef;
        border-radius: 4px;
        color: #005566;
        text-decoration: none;
        font-size: 0.85rem;
    }
    .attachment-link:hover {
        background: #005566;
        color: white;
    }
    .print-btn {
        margin-left: 5px;
        padding: 0.25rem 0.5rem;
        font-size: 0.85rem;
    }
    .complaint-id {
        font-family: monospace;
        font-weight: bold;
        color: #005566;
    }
    .export-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .timeline-badge {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    
    /* Print Styles */
    @media print {
        body { background: white; }
        .no-print, .btn, .report-filters, .admin-form, .search-filters, .table-responsive, .stats-card, .export-btn { display: none !important; }
        .print-complaint {
            display: block !important;
            padding: 30px;
            font-size: 12pt;
            font-family: Arial, sans-serif;
        }
        .print-header {
            text-align: center;
            border-bottom: 3px solid #005566;
            margin-bottom: 30px;
            padding-bottom: 15px;
        }
        .print-header h2 {
            color: #005566;
            margin: 0 0 10px 0;
            font-size: 24pt;
        }
        .print-header h3 {
            color: #333;
            margin: 0 0 5px 0;
            font-size: 18pt;
        }
        .print-header h4 {
            color: #666;
            margin: 0;
            font-size: 14pt;
        }
        .print-details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .print-details th {
            text-align: left;
            width: 25%;
            padding: 12px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            font-weight: bold;
            color: #333;
        }
        .print-details td {
            padding: 12px;
            border: 1px solid #ddd;
            vertical-align: top;
            background: white;
        }
        .print-subject {
            font-weight: bold;
            font-size: 14pt;
            color: #005566;
        }
        .print-description {
            white-space: pre-wrap;
            line-height: 1.8;
            font-size: 12pt;
            background: #fafafa;
            padding: 15px;
            border-radius: 5px;
        }
        .print-footer {
            margin-top: 50px;
            text-align: right;
            font-size: 11pt;
        }
        .print-signature-line {
            margin-top: 70px;
            width: 300px;
            border-top: 2px solid #333;
            margin-left: auto;
            text-align: center;
            padding-top: 8px;
            font-style: italic;
        }
    }
    
    .print-complaint {
        display: none;
    }
    
    .print-complaint.active {
        display: block;
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .timeline {
        position: relative;
        padding: 20px 0;
    }
    .timeline-item {
        padding: 10px 0;
        border-left: 2px solid #005566;
        margin-left: 20px;
        padding-left: 20px;
        position: relative;
    }
    .timeline-item:before {
        content: '';
        width: 12px;
        height: 12px;
        background: #005566;
        border-radius: 50%;
        position: absolute;
        left: -7px;
        top: 15px;
    }
    .timeline-date {
        font-size: 0.85rem;
        color: #666;
    }
    .timeline-content {
        margin-top: 5px;
    }
    
    .delete-btn {
        color: #dc3545;
        cursor: pointer;
        transition: all 0.3s;
    }
    .delete-btn:hover {
        color: #a71d2a;
        transform: scale(1.1);
    }
</style>

<div class="container-fluid">
    <div class="page-title text-center">
        <h3 class="mb-0"><?= $isAdmin ? 'Manage Requests & Complaints' : 'Submit Request / Complaint' ?></h3>
        <small class="opacity-75"><?= $isAdmin ? 'Administrator Panel' : 'Court Reader Portal' ?></small>
    </div>

    <!-- Print Complaint View -->
    <?php if ($printComplaint): ?>
    <div class="print-complaint active" id="print-area">
        <div class="print-header">
            <h2>DISTRICT COURT JAMSHORO</h2>
            <h3><?= ucfirst($printComplaint['kind']) ?> Details</h3>
            <h4>Complaint ID: <?= htmlspecialchars($printComplaint['complaint_id']) ?></h4>
        </div>
        
        <table class="print-details">
            <tr>
                <th>Date Submitted:</th>
                <td><?= date('d F Y, h:i A', strtotime($printComplaint['created_at'])) ?></td>
            </tr>
            <tr>
                <th>Employee Name:</th>
                <td><?= htmlspecialchars($printComplaint['employee_name'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th>Father's Name:</th>
                <td><?= htmlspecialchars($printComplaint['father_name'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th>Court:</th>
                <td><?= htmlspecialchars($printComplaint['court_name'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th>Post / BPS:</th>
                <td><?= htmlspecialchars($printComplaint['post'] ?? 'N/A') ?> (<?= htmlspecialchars($printComplaint['bps'] ?? 'N/A') ?>)</td>
            </tr>
            <tr>
                <th>Contact / CNIC:</th>
                <td><?= htmlspecialchars($printComplaint['contact'] ?? 'N/A') ?> / <?= htmlspecialchars($printComplaint['cnic'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th>Type:</th>
                <td><?= ucfirst($printComplaint['kind'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th>Category:</th>
                <td><?= htmlspecialchars($printComplaint['category'] ?? 'N/A') ?></td>
            </tr>
            <?php if (!empty($printComplaint['cfms_case_code'])): ?>
            <tr>
                <th>CFMS-DC Case Code:</th>
                <td><?= htmlspecialchars($printComplaint['cfms_case_code']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Subject:</th>
                <td class="print-subject"><?= htmlspecialchars($printComplaint['subject'] ?? 'N/A') ?></td>
            </tr>
            <tr>
                <th>Description:</th>
                <td>
                    <div class="print-description">
                        <?= nl2br(htmlspecialchars($printComplaint['description'] ?? 'N/A')) ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th>Priority:</th>
                <td><?= ucfirst($printComplaint['priority'] ?? 'Normal') ?></td>
            </tr>
            <tr>
                <th>Requested Date:</th>
                <td><?= $printComplaint['requested_date'] ? date('d F Y', strtotime($printComplaint['requested_date'])) : 'N/A' ?></td>
            </tr>
            <tr>
                <th>Current Status:</th>
                <td><?= ucfirst(str_replace('_', ' ', $printComplaint['status'] ?? 'submitted')) ?></td>
            </tr>
            <?php if (!empty($printComplaint['resolution_notes'])): ?>
            <tr>
                <th>Resolution Notes:</th>
                <td><?= nl2br(htmlspecialchars($printComplaint['resolution_notes'])) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($printComplaint['completed_date'])): ?>
            <tr>
                <th>Completed Date:</th>
                <td><?= date('d F Y', strtotime($printComplaint['completed_date'])) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($printComplaint['attachment'])): ?>
            <tr>
                <th>Attachment:</th>
                <td>
                    <a href="/a/project_root/download_complaint.php?file=<?= urlencode($printComplaint['attachment']) ?>" target="_blank">
                        <?= htmlspecialchars($printComplaint['attachment']) ?>
                    </a>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        
        <div class="timeline">
            <h5>Status Timeline</h5>
            <div class="timeline-item">
                <div class="timeline-date"><?= date('d M Y, h:i A', strtotime($printComplaint['created_at'])) ?></div>
                <div class="timeline-content">Complaint submitted</div>
            </div>
            <?php if (!empty($printComplaint['completed_date'])): ?>
            <div class="timeline-item">
                <div class="timeline-date"><?= date('d M Y', strtotime($printComplaint['completed_date'])) ?></div>
                <div class="timeline-content">Complaint completed</div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="print-footer">
            <p>Generated on: <?= date('d F Y, h:i A') ?></p>
            <div class="print-signature-line">
                Authorized Signature
            </div>
        </div>
        
        <div class="text-center no-print mt-4">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print This Form
            </button>
            <a href="?page=complaints" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="row" <?= $printComplaint ? 'style="display:none;"' : '' ?>>
        <div class="col-12">
            <div class="card complaint-card">
                <div class="card-header card-header-gradient">
                    <h5 class="mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= $isReader ? 'Submit New Request / Complaint' : 'Request / Complaint Management' ?>
                    </h5>
                </div>
                <div class="card-body p-4">

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards for Admin -->
                    <?php if ($isAdmin && !empty($stats)): ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?= $stats['total'] ?></div>
                                <div class="stats-label">Total Complaints</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?= $stats['submitted'] ?></div>
                                <div class="stats-label">Pending Review</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?= $stats['in_review'] ?></div>
                                <div class="stats-label">In Review</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card stats-urgent">
                                <div class="stats-number"><?= $stats['urgent'] ?></div>
                                <div class="stats-label">Urgent Priority</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Admin: Create New Request/Complaint Form -->
                    <?php if ($isAdmin): ?>
                    <div class="admin-form no-print">
                        <h6 class="mb-3"><i class="bi bi-plus-circle me-2"></i> Create New Request / Complaint for Employee</h6>
                        <form method="post" enctype="multipart/form-data" class="row g-3">
                            <input type="hidden" name="admin_create_item" value="1">

                            <div class="col-md-4">
                                <label class="form-label">Court <span class="text-danger">*</span></label>
                                <select name="court_id" id="court_id" class="form-select" required onchange="loadEmployees(this.value)">
                                    <option value="">-- Select Court --</option>
                                    <?php foreach ($courts as $court): ?>
                                        <option value="<?= $court['id'] ?>"><?= htmlspecialchars($court['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Employee <span class="text-danger">*</span></label>
                                <select name="employee_id" id="employee_id" class="form-select" required>
                                    <option value="">-- Select Court First --</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="submitted">Submitted</option>
                                    <option value="in_review">In Review</option>
                                    <option value="approved">Approved</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Type <span class="text-danger">*</span></label>
                                <select name="kind" class="form-select" required>
                                    <option value="request">Request</option>
                                    <option value="complaint">Complaint</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Priority <span class="text-danger">*</span></label>
                                <select name="priority" class="form-select" required>
                                    <option value="Normal">Normal</option>
                                    <option value="Low">Low</option>
                                    <option value="High">High</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Requested Date <span class="text-danger">*</span></label>
                                <input type="date" name="requested_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <select name="category" id="admin_category" class="form-select" required onchange="toggleCFMSCaseCode('admin')">
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categoryOptions as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12" id="admin_cfms_case_code_container" style="display: none;">
                                <label class="form-label">CFMS-DC Case Code</label>
                                <input type="text" name="cfms_case_code" id="admin_cfms_case_code" class="form-control" placeholder="Enter CFMS-DC Case Code">
                                <small class="text-muted">Required for CFMS-DC category</small>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" class="form-control" placeholder="Brief title" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Details / Description <span class="text-danger">*</span></label>
                                <textarea name="details" rows="4" class="form-control" placeholder="Describe the issue or request in detail..." required></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Attachment (Optional)</label>
                                <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
                                <small class="text-muted">Max 5MB. Allowed: PDF, DOC, DOCX, JPG, PNG, TXT</small>
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-success px-5">
                                    <i class="bi bi-plus-circle me-2"></i> Create Request/Complaint
                                </button>
                            </div>
                        </form>
                    </div>

                    <script>
                    function loadEmployees(courtId) {
                        if (!courtId) {
                            document.getElementById('employee_id').innerHTML = '<option value="">-- Select Court First --</option>';
                            return;
                        }
                        
                        fetch('?page=ajax_employees&court_id=' + courtId)
                            .then(response => response.json())
                            .then(data => {
                                let options = '<option value="">-- Select Employee --</option>';
                                
                                // Add admin user as an option
                                options += `<option value="<?= $user['id'] ?>" data-role="admin"><?= htmlspecialchars($user['name']) ?> (Admin)</option>`;
                                
                                // Add regular employees
                                data.forEach(emp => {
                                    options += `<option value="${emp.id}">${emp.name}</option>`;
                                });
                                
                                document.getElementById('employee_id').innerHTML = options;
                            })
                            .catch(error => {
                                console.error('Error loading employees:', error);
                                // If error, still show admin option
                                let options = '<option value="">-- Select Employee --</option>';
                                options += `<option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?> (Admin)</option>`;
                                document.getElementById('employee_id').innerHTML = options;
                            });
                    }
                    
                    function toggleCFMSCaseCode(type) {
                        const category = document.getElementById(type + '_category').value;
                        const container = document.getElementById(type + '_cfms_case_code_container');
                        const input = document.getElementById(type + '_cfms_case_code');
                        
                        if (category === 'CFMS-DC') {
                            container.style.display = 'block';
                            input.required = true;
                        } else {
                            container.style.display = 'none';
                            input.required = false;
                            input.value = '';
                        }
                    }
                    </script>
                    <?php endif; ?>

                    <!-- Reader: Submission Form -->
                    <?php if ($isReader): ?>
                    <div class="border rounded-3 p-4 mb-4" style="background:#f8fdff;">
                        <form method="post" enctype="multipart/form-data" class="row g-3">
                            <input type="hidden" name="create_item" value="1">
                            <input type="hidden" name="employee_id" value="<?= (int)$user['id'] ?>">

                            <div class="col-md-4">
                                <label class="form-label">Submitted By</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type</label>
                                <select name="kind" class="form-select" required>
                                    <option value="request">Request</option>
                                    <option value="complaint" selected>Complaint</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select">
                                    <option value="Normal" selected>Normal</option>
                                    <option value="Low">Low</option>
                                    <option value="High">High</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Requested Date</label>
                                <input type="date" name="requested_date" class="form-control" required>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Category</label>
                                <select name="category" id="reader_category" class="form-select" required onchange="toggleCFMSCaseCode('reader')">
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categoryOptions as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-7" id="reader_cfms_case_code_container" style="display: none;">
                                <label class="form-label">CFMS-DC Case Code</label>
                                <input type="text" name="cfms_case_code" id="reader_cfms_case_code" class="form-control" placeholder="Enter CFMS-DC Case Code">
                                <small class="text-muted">Required for CFMS-DC category</small>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control" placeholder="Brief title" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Details / Description</label>
                                <textarea name="details" rows="4" class="form-control" placeholder="Describe the issue or request in detail..." required></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Attachment (Optional)</label>
                                <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
                                <small class="text-muted">Max 5MB. Allowed: PDF, DOC, DOCX, JPG, PNG, TXT</small>
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="bi bi-send me-2"></i> Submit
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <script>
                    function toggleCFMSCaseCode(type) {
                        const category = document.getElementById(type + '_category').value;
                        const container = document.getElementById(type + '_cfms_case_code_container');
                        const input = document.getElementById(type + '_cfms_case_code');
                        
                        if (category === 'CFMS-DC') {
                            container.style.display = 'block';
                            input.required = true;
                        } else {
                            container.style.display = 'none';
                            input.required = false;
                            input.value = '';
                        }
                    }
                    </script>
                    <?php endif; ?>

                    <!-- Admin: Search and Report Filters -->
                    <?php if ($isAdmin): ?>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <ul class="nav nav-tabs" id="filterTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="search-tab" data-bs-toggle="tab" data-bs-target="#search" type="button" role="tab">Search by ID</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="advanced-tab" data-bs-toggle="tab" data-bs-target="#advanced" type="button" role="tab">Advanced Filters</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="quick-tab" data-bs-toggle="tab" data-bs-target="#quick" type="button" role="tab">Quick Filters</button>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="tab-content w-100">
                            <div class="tab-pane fade show active" id="search" role="tabpanel">
                                <div class="search-filters no-print">
                                    <form method="get" class="row g-3">
                                        <input type="hidden" name="page" value="complaints">
                                        <input type="hidden" name="search" value="1">
                                        
                                        <div class="col-md-8">
                                            <input type="text" name="search_complaint_id" class="form-control" placeholder="Enter Complaint ID (e.g., COMP-2024-0001)" value="<?= htmlspecialchars($_GET['search_complaint_id'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-info w-100">
                                                <i class="bi bi-search"></i> Search
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="advanced" role="tabpanel">
                                <div class="report-filters no-print">
                                    <form method="get" class="row g-3">
                                        <input type="hidden" name="page" value="complaints">
                                        <input type="hidden" name="report" value="1">

                                        <div class="col-md-3">
                                            <label class="form-label">From Date</label>
                                            <input type="date" name="report_from" class="form-control" value="<?= htmlspecialchars($_GET['report_from'] ?? $firstDayOfMonth) ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">To Date</label>
                                            <input type="date" name="report_to" class="form-control" value="<?= htmlspecialchars($_GET['report_to'] ?? $today) ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Court</label>
                                            <select name="report_court_id" class="form-select">
                                                <option value="">All Courts</option>
                                                <?php foreach ($courts as $c): ?>
                                                    <option value="<?= $c['id'] ?>" <?= ($_GET['report_court_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($c['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Employee</label>
                                            <select name="report_employee_id" class="form-select">
                                                <option value="">All Employees</option>
                                                <?php foreach ($allEmployees as $ae): ?>
                                                    <option value="<?= $ae['id'] ?>" <?= ($_GET['report_employee_id'] ?? '') == $ae['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($ae['name']) ?> (<?= htmlspecialchars($ae['court_name'] ?? 'No Court') ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Status</label>
                                            <select name="report_status" class="form-select">
                                                <option value="">All Status</option>
                                                <option value="submitted" <?= ($_GET['report_status'] ?? '') == 'submitted' ? 'selected' : '' ?>>Submitted</option>
                                                <option value="in_review" <?= ($_GET['report_status'] ?? '') == 'in_review' ? 'selected' : '' ?>>In Review</option>
                                                <option value="approved" <?= ($_GET['report_status'] ?? '') == 'approved' ? 'selected' : '' ?>>Approved</option>
                                                <option value="rejected" <?= ($_GET['report_status'] ?? '') == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                <option value="completed" <?= ($_GET['report_status'] ?? '') == 'completed' ? 'selected' : '' ?>>Completed</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Type</label>
                                            <select name="report_kind" class="form-select">
                                                <option value="">All Types</option>
                                                <option value="request" <?= ($_GET['report_kind'] ?? '') == 'request' ? 'selected' : '' ?>>Request</option>
                                                <option value="complaint" <?= ($_GET['report_kind'] ?? '') == 'complaint' ? 'selected' : '' ?>>Complaint</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Priority</label>
                                            <select name="report_priority" class="form-select">
                                                <option value="">All Priorities</option>
                                                <option value="Low" <?= ($_GET['report_priority'] ?? '') == 'Low' ? 'selected' : '' ?>>Low</option>
                                                <option value="Normal" <?= ($_GET['report_priority'] ?? '') == 'Normal' ? 'selected' : '' ?>>Normal</option>
                                                <option value="High" <?= ($_GET['report_priority'] ?? '') == 'High' ? 'selected' : '' ?>>High</option>
                                                <option value="Urgent" <?= ($_GET['report_priority'] ?? '') == 'Urgent' ? 'selected' : '' ?>>Urgent</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 d-flex align-items-end gap-2">
                                            <button type="submit" class="btn btn-primary flex-fill">Apply Filters</button>
                                            <a href="?page=complaints" class="btn btn-outline-secondary">Reset</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="quick" role="tabpanel">
                                <div class="report-filters no-print">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <a href="?page=complaints&report=1&report_status=submitted" class="btn btn-outline-secondary w-100 mb-2">Pending Review</a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="?page=complaints&report=1&report_status=in_review" class="btn btn-outline-info w-100 mb-2">In Review</a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="?page=complaints&report=1&report_status=completed" class="btn btn-outline-success w-100 mb-2">Completed</a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="?page=complaints&report=1&report_priority=Urgent" class="btn btn-outline-danger w-100 mb-2">Urgent</a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="?page=complaints&report=1&report_from=<?= $firstDayOfMonth ?>&report_to=<?= $today ?>" class="btn btn-outline-primary w-100 mb-2">This Month</a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="?page=complaints&report=1&report_kind=complaint" class="btn btn-outline-warning w-100 mb-2">Complaints Only</a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="?page=complaints&report=1&report_kind=request" class="btn btn-outline-info w-100 mb-2">Requests Only</a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="?page=complaints&report=1&report_status=rejected" class="btn btn-outline-dark w-100 mb-2">Rejected</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Records Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Complaint ID</th>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Priority</th>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>CFMS Code</th>
                                    <th>Req. Date</th>
                                    <th>Status</th>
                                    <th>Attachment</th>
                                    <th>Completed</th>
                                    <th>Court</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="13" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox display-6 d-block mb-3"></i>
                                            No records found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $row): ?>
                                    <tr class="<?= ($row['priority'] ?? '') === 'Urgent' ? 'table-danger' : '' ?>">
                                        <td>
                                            <span class="complaint-id"><?= htmlspecialchars($row['complaint_id'] ?? '#' . $row['id']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($row['employee_name'] ?? '—') ?></td>
                                        <td><span class="badge bg-info"><?= ucfirst($row['kind'] ?? '') ?></span></td>
                                        <td>
                                            <span class="badge priority-<?= strtolower($row['priority'] ?? 'normal') ?>">
                                                <?= ucfirst($row['priority'] ?? 'Normal') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['subject'] ?? '') ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($row['category'] ?? '') ?></td>
                                        <td>
                                            <?php if (!empty($row['cfms_case_code'])): ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($row['cfms_case_code']) ?></span>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $row['requested_date'] ? date('d M Y', strtotime($row['requested_date'])) : '—' ?></td>
                                        <td>
                                            <span class="badge badge-<?= $row['status'] ?? 'submitted' ?>">
                                                <?= ucfirst(str_replace('_', ' ', $row['status'] ?? 'submitted')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['attachment'])): ?>
                                                <a href="/a/project_root/download_complaint.php?file=<?= urlencode($row['attachment']) ?>" class="attachment-link" target="_blank">
                                                    <i class="bi bi-paperclip"></i> View
                                                </a>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $row['completed_date'] ? date('d M Y', strtotime($row['completed_date'])) : '—' ?></td>
                                        <td><?= htmlspecialchars($row['court_name'] ?? '—') ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?page=complaints&print=<?= urlencode($row['complaint_id'] ?? $row['id']) ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="Print">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                                <?php if ($isAdmin): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#updateModal<?= $row['id'] ?>" title="Update Status">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['id'] ?>" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($isAdmin): ?>
                                            <!-- Update Modal -->
                                            <div class="modal fade" id="updateModal<?= $row['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Update Complaint #<?= htmlspecialchars($row['complaint_id'] ?? $row['id']) ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="post">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="admin_action" value="1">
                                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Status</label>
                                                                    <select name="status" class="form-select">
                                                                        <?php foreach (['submitted','in_review','approved','rejected','completed'] as $s): ?>
                                                                            <option value="<?= $s ?>" <?= ($row['status'] ?? '') === $s ? 'selected' : '' ?>>
                                                                                <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Completed Date</label>
                                                                    <input type="date" name="completed_date" class="form-control" value="<?= htmlspecialchars($row['completed_date'] ?? '') ?>">
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Resolution Notes</label>
                                                                    <textarea name="resolution_notes" rows="3" class="form-control" placeholder="Enter resolution notes..."><?= htmlspecialchars($row['resolution_notes'] ?? '') ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-primary">Update</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?= $row['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Delete Complaint #<?= htmlspecialchars($row['complaint_id'] ?? $row['id']) ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete this complaint?</p>
                                                            <?php if (!empty($row['attachment'])): ?>
                                                            <p class="text-danger"><i class="bi bi-exclamation-triangle"></i> This will also delete the attached file: <strong><?= htmlspecialchars($row['attachment']) ?></strong></p>
                                                            <?php endif; ?>
                                                            <p class="text-warning">This action cannot be undone.</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <form method="post">
                                                                <input type="hidden" name="delete_complaint" value="1">
                                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Delete</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Export and Action Buttons -->
                    <div class="mt-4 d-flex justify-content-between align-items-center no-print">
                        <div>
                            <a href="?page=dashboard" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
                            </a>
                        </div>
                        <div>
                            <?php if ($isAdmin && !empty($items)): ?>
                            <button onclick="exportToExcel()" class="btn btn-success me-2">
                                <i class="bi bi-file-excel"></i> Export to Excel
                            </button>
                            <button onclick="printTableReport()" class="btn btn-primary">
                                <i class="bi bi-printer"></i> Print Report
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Button -->
<?php if ($isAdmin): ?>
<a href="#" onclick="exportToExcel()" class="btn btn-success export-btn no-print">
    <i class="bi bi-file-excel"></i> Export
</a>
<?php endif; ?>

<script>
function printReport() {
    window.print();
}

function printTableReport() {
    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Complaints Report</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
    printWindow.document.write('h2 { text-align: center; color: #005566; }');
    printWindow.document.write('h4 { text-align: center; color: #666; margin-bottom: 30px; }');
    printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
    printWindow.document.write('th { background: #005566; color: white; padding: 12px; text-align: left; }');
    printWindow.document.write('td { padding: 10px; border-bottom: 1px solid #ddd; }');
    printWindow.document.write('tr:nth-child(even) { background: #f9f9f9; }');
    printWindow.document.write('.badge { padding: 3px 8px; border-radius: 4px; font-size: 12px; }');
    printWindow.document.write('.footer { margin-top: 40px; text-align: right; font-size: 12px; }');
    printWindow.document.write('.signature-line { margin-top: 60px; width: 250px; border-top: 1px solid #000; margin-left: auto; text-align: center; padding-top: 5px; }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    
    printWindow.document.write('<h2>DISTRICT COURT JAMSHORO</h2>');
    printWindow.document.write('<h4>Complaints & Requests Report</h4>');
    printWindow.document.write('<p>Generated on: ' + new Date().toLocaleString() + '</p>');
    
    var table = document.querySelector('.table').cloneNode(true);
    
    var headers = table.querySelectorAll('th');
    for (var i = headers.length - 1; i >= 0; i--) {
        if (headers[i].textContent.includes('Actions')) {
            var headerIndex = i;
            var rows = table.querySelectorAll('tr');
            rows.forEach(row => {
                var cells = row.querySelectorAll('td, th');
                if (cells.length > headerIndex) {
                    cells[headerIndex].remove();
                }
            });
        }
    }
    
    printWindow.document.write(table.outerHTML);
    
    printWindow.document.write('<div class="footer">');
    printWindow.document.write('<div class="signature-line">Authorized Signature</div>');
    printWindow.document.write('</div>');
    
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    
    printWindow.onload = function() {
        printWindow.print();
    };
}

function exportToExcel() {
    var table = document.querySelector('.table').cloneNode(true);
    
    var headers = table.querySelectorAll('th');
    for (var i = headers.length - 1; i >= 0; i--) {
        if (headers[i].textContent.includes('Actions')) {
            var headerIndex = i;
            var rows = table.querySelectorAll('tr');
            rows.forEach(row => {
                var cells = row.querySelectorAll('td, th');
                if (cells.length > headerIndex) {
                    cells[headerIndex].remove();
                }
            });
        }
    }
    
    var rows = table.querySelectorAll('tr');
    var csv = [];
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        for (var j = 0; j < cols.length; j++) {
            var data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/\s+/g, ' ').trim();
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        csv.push(row.join(','));
    }
    
    var csvContent = csv.join('\n');
    var blob = new Blob([csvContent], { type: 'text/csv' });
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'complaints_report_' + new Date().toISOString().slice(0,10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<?php include __DIR__ . '/footer.php'; ?>