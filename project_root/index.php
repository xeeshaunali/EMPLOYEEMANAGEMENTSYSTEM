<?php
session_start();

require __DIR__ . '/../backend/config/constants.php';
$pdo = require __DIR__ . '/../backend/config/pdo.php';

/* =====================================================
   AJAX: Load employees for selected court (employee_details)
===================================================== */
if (isset($_GET['page']) && $_GET['page'] === 'ajax_employees') {
    header('Content-Type: application/json');

    $court_id = (int)($_GET['court_id'] ?? 0);

    if ($court_id > 0) {
        $stmt = $pdo->prepare("
            SELECT id, name
            FROM employee_details
            WHERE court_id = ?
            ORDER BY name ASC
        ");
        $stmt->execute([$court_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode([]);
    }
    exit;
}

/* =====================================================
   ROBUST AUTOLOADER (FIXED)
===================================================== */
spl_autoload_register(function ($class) {
    $basePaths = [
        __DIR__ . '/../backend/controllers/',
        __DIR__ . '/../backend/models/',
    ];

    foreach ($basePaths as $base) {
        $file = $base . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/* =====================================================
   BASIC ROUTING
===================================================== */
$page = $_GET['page'] ?? 'login';

/* Public pages */
$public = ['login', 'do_login', 'logout'];

if (!isset($_SESSION['user']) && !in_array($page, $public, true)) {
    header('Location: ?page=login');
    exit;
}

/* =====================================================
   DISPATCH
===================================================== */
switch ($page) {

    case 'login':
        include __DIR__ . '/../backend/views/login.php';
        break;

    case 'do_login':
        AuthController::doLogin($pdo);
        break;

    case 'logout':
        AuthController::logout();
        break;

    case 'dashboard':
        include __DIR__ . '/../backend/views/dashboard.php';
        break;

    /* ================= ADMIN ================= */

    case 'courts':
        AdminController::courts($pdo);
        break;

    case 'save_court':
        AdminController::saveCourt($pdo);
        break;

    case 'delete_court':
        AdminController::deleteCourt($pdo);
        break;

    case 'employees':
        AdminController::employees($pdo);
        break;

    case 'save_employee':
        AdminController::saveEmployee($pdo);
        break;

    case 'delete_employee':
        AdminController::deleteEmployee($pdo);
        break;

    /* ================= LEAVES ================= */

    case 'leave_requests':
        AdminController::leaveRequests($pdo);
        break;

    case 'approve_leave':
        AdminController::approveLeave($pdo);
        break;

    case 'apply_leave':
        EmployeeController::applyLeave($pdo);
        break;

    case 'my_leaves':
        EmployeeController::myLeaves($pdo);
        break;

    case 'add_leave':
        if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'reader'], true)) {
            include __DIR__ . '/../backend/views/add_leave.php';
        } else {
            echo 'Access denied';
        }
        break;

    /* ✅ FIXED: Instantiate LeaveController */
    case 'save_staff_leave':
        if (in_array($_SESSION['user']['role'] ?? '', ['admin', 'reader'], true)) {

            if (!class_exists('LeaveController')) {
                die('LeaveController not found. Check controller file.');
            }

            $controller = new LeaveController($pdo);
            $controller->saveStaffLeave();

        } else {
            echo 'Access denied';
        }
        break;

    case 'casual_leave_report':
        LeaveReportController::casualLeaveReport($pdo);
        break;

    /* ================= LEAVE TYPES ================= */

    case 'leave_types':
        if ($_SESSION['user']['role'] === 'admin') {
            include __DIR__ . '/../backend/views/leave_types.php';
        } else {
            echo 'Access denied';
        }
        break;

    case 'save_leave_type':
        LeaveTypeController::save($pdo);
        break;

    case 'delete_leave_type':
        LeaveTypeController::delete($pdo);
        break;

    /* ================= FILES ================= */

    case 'upload_file':
        FileController::upload($pdo);
        break;

    case 'files':
        FileController::files($pdo);
        break;

    case 'download':
        FileController::download($pdo);
        break;

    case 'delete_file':
        FileController::delete($pdo);
        break;

        // file Categories
        case 'file_categories': 
        FileCategoryController::listCategories($pdo); 
        break;
    case 'save_file_category': 
        FileCategoryController::saveCategory($pdo); 
        break;
    case 'delete_file_category': 
        FileCategoryController::deleteCategory($pdo); 
        break;

        // Posts

        // Posts & Reports
    case 'posts': 
        include __DIR__ . '/../backend/views/post.php'; 
        break;



        // Employee Details 
        case 'employee_details':
        if ($_SESSION['user']['role'] === 'admin') {
            include __DIR__ . '/../backend/views/employee_details.php';
        } else {
            echo 'Access denied';
        }
        break;

        // Employee Search
        case 'employee_search':
        if ($_SESSION['user']['role'] === 'admin') {
            include __DIR__ . '/../backend/views/employee_search.php';
        } else {
            echo 'Access denied';
        }
        break;

        // Judicial Officers
        // Judicial Officers (Admin only)
    case 'judicial_officers':
        if ($_SESSION['user']['role'] === 'admin') {
            include __DIR__ . '/../backend/views/judicial_officers.php';
        } else {
            echo 'Access denied';
        }
        break;

        // Staff Strength report

        case 'staff_strength_report': 
        include __DIR__ . '/../backend/views/admin_staff_strength_report.php'; 
        break;

        // Employee List

        case 'employee_list':
        if ($_SESSION['user']['role'] === 'admin') {
            include __DIR__ . '/../backend/views/admin_employee_list.php';
        } else {
            echo 'Access denied';
        }
        break;


        // Complaints & Requests
    case 'complaints':
        include __DIR__ . '/../backend/views/complaints.php';
        break;
    case 'save_complaint':
        ComplaintController::save($pdo);
        break;
    case 'manage_complaints':
        if ($_SESSION['user']['role'] === 'admin') {
            AdminController::manageComplaints($pdo);
        } else {
            echo 'Access denied';
        }
        break;
    case 'update_complaint':
        if ($_SESSION['user']['role'] === 'admin') {
            AdminController::updateComplaint($pdo);
        } else {
            echo 'Access denied';
        }
        break;

    // Library Module (Admin + Librarian)
    case 'library':
        if (in_array($_SESSION['user']['role'] ?? '', ['admin','librarian'], true)) {
            LibraryController::index($pdo);
        } else { 
            echo 'Access denied'; 
        }
        break;
    case 'save_library_category': 
        LibraryController::saveCategory($pdo); 
        break;
    case 'delete_library_category': 
        LibraryController::deleteCategory($pdo); 
        break;
    case 'save_book': 
        LibraryController::saveBook($pdo); 
        break;
    case 'delete_book': 
        LibraryController::deleteBook($pdo); 
        break;
    case 'issue_book': 
        LibraryController::issueBook($pdo); 
        break;
    case 'return_book': 
        LibraryController::returnBook($pdo); 
        break;
    case 'library_download': 
        LibraryController::download($pdo); 
        break;




        // Leave Management
    case 'leave_requests': 
        AdminController::leaveRequests($pdo); 
        break;
    case 'approve_leave': 
        AdminController::approveLeave($pdo); 
        break;
    case 'apply_leave': 
        EmployeeController::applyLeave($pdo); 
        break;
    case 'my_leaves': 
        EmployeeController::myLeaves($pdo); 
        break;


        // Password
    case 'change_password': 
        include __DIR__ . '/../backend/views/change_password.php'; 
        break;
    case 'do_change_password': 
        PasswordController::changePassword($pdo); 
        break;
    case 'reset_password':
        if ($_SESSION['user']['role'] === 'admin') {
            include __DIR__ . '/../backend/views/reset_password.php';
        } else {
            echo 'Access denied';
        }
        break;
    case 'do_reset_password': 
        PasswordController::resetPassword($pdo); 
        break;


        // Transfer & Posting
    case 'transfer_posting':
        if ($_SESSION['user']['role'] === 'admin') {
            include __DIR__ . '/../backend/views/transfer_posting.php';
        } else {
            echo 'Access denied';
        }
        break;
    case 'save_transfer_posting':
        if ($_SESSION['user']['role'] === 'admin') {
            AdminController::saveTransferPosting($pdo);
        } else {
            echo 'Access denied';
        }
        break;

// Posts & Reports
    case 'posts': 
        include __DIR__ . '/../backend/views/post.php'; 
        break;
    case 'staff_strength_report': 
        include __DIR__ . '/../backend/views/admin_staff_strength_report.php'; 
        break;
    case 'employee_list':
        if ($_SESSION['user']['role'] === 'admin') {
            include __DIR__ . '/../backend/views/admin_employee_list.php';
        } else {
            echo 'Access denied';
        }
        break;
    case 'employee_search':
        if ($_SESSION['user']['role'] === 'admin') {
            include __DIR__ . '/../backend/views/employee_search.php';
        } else {
            echo 'Access denied';
        }
        break;
    case 'employee_profile':
        if ($_SESSION['user']['role'] === 'admin') {
            include __DIR__ . '/../backend/views/employee_profile.php';
        } else {
            echo 'Access denied';
        }
        break;
    case 'employee_details':
        if ($_SESSION['user']['role'] === 'admin') {
            include __DIR__ . '/../backend/views/employee_details.php';
        } else {
            echo 'Access denied';
        }
        break;
    case 'save_employee_detail':
        if ($_SESSION['user']['role'] === 'admin') {
            AdminController::saveEmployeeDetail($pdo);
        } else {
            echo 'Access denied';
        }
        break;
    case 'delete_employee_detail':
        if ($_SESSION['user']['role'] === 'admin') {
            AdminController::deleteEmployeeDetail($pdo);
        } else {
            echo 'Access denied';
        }
        break;



        

    /* ================= PASSWORD ================= */

    case 'change_password':
        include __DIR__ . '/../backend/views/change_password.php';
        break;

    case 'do_change_password':
        PasswordController::changePassword($pdo);
        break;

    /* ================= DEFAULT ================= */

    default:
        echo 'Page not found';
        break;
}
