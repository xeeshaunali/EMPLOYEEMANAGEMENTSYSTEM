<?php
// casual_leave_report.php - Enhanced Professional Leave Report
include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php';
$user = $_SESSION['user'] ?? [];

// --- Input filters ---
$from       = trim($_GET['from'] ?? '');
$to         = trim($_GET['to'] ?? '');
$leaveType  = trim($_GET['leave_type'] ?? '');
$courtId    = trim($_GET['court_id'] ?? '');
$employeeId = trim($_GET['employee_id'] ?? '');
$status     = trim($_GET['status'] ?? '');

// Set default date range to current month if not specified
if (empty($from)) {
    $from = date('Y-m-01');
}
if (empty($to)) {
    $to = date('Y-m-d');
}

// --- Fetch courts ---
$courts = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM courts ORDER BY name ASC");
    $courts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $courts = [];
}

// --- Fetch leave types ---
$leaveTypes = [];
try {
    $stmt = $pdo->query("SELECT name FROM leave_types ORDER BY name ASC");
    $leaveTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $leaveTypes = [];
}

// --- Fetch employees based on court selection ---
$employees = [];
try {
    if ($courtId !== '') {
        $stmt = $pdo->prepare("SELECT id, name FROM employee_details WHERE court_id = ? ORDER BY name ASC");
        $stmt->execute([(int)$courtId]);
    } else {
        $stmt = $pdo->query("SELECT id, name FROM employee_details ORDER BY name ASC");
    }
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
}

// --- Main Leave Query ---
function get_table_columns($pdo, $table) {
    $cols = [];
    try {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $res = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($res) $cols = array_map('strtolower', $res);
    } catch (Exception $e) {}
    return $cols;
}

$leavesColumns = get_table_columns($pdo, 'leaves');
$edColumns = get_table_columns($pdo, 'employee_details');
$postsColumns = get_table_columns($pdo, 'posts');

$leavesEmpCol = in_array('employee_detail_id', $leavesColumns) ? 'employee_detail_id' : 'employee_id';

$hasEd = !empty($edColumns);
$edHasName = in_array('name', $edColumns);
$edHasCourtId = in_array('court_id', $edColumns);

$possiblePostCols = ['post', 'post_id', 'postname', 'post_name', 'designation', 'designation_id', 'position'];
$edPostCol = null;
foreach ($possiblePostCols as $c) {
    if (in_array($c, $edColumns)) { $edPostCol = $c; break; }
}

$postsHasName = in_array('post_name', $postsColumns) || in_array('post', $postsColumns) || in_array('post_title', $postsColumns);

$params = [];
$fromSql = "FROM `leaves` l\n";
$joinedEd = false;

if ($hasEd) {
    $fromSql .= "INNER JOIN `employee_details` ed ON ed.id = l.`{$leavesEmpCol}`\n";
    $joinedEd = true;
} else {
    $fromSql .= "INNER JOIN `employees` e ON e.id = l.`{$leavesEmpCol}`\n";
}

if ($joinedEd && $edPostCol !== null && $postsHasName) {
    $fromSql .= "LEFT JOIN `posts` p ON p.id = ed.`{$edPostCol}`\n";
}

if ($joinedEd && $edHasCourtId) {
    $fromSql .= "LEFT JOIN `courts` c ON c.id = ed.court_id\n";
}

$selectParts = [];
if ($joinedEd && $edHasName) {
    $selectParts[] = "ed.`name` AS name";
} else {
    $selectParts[] = "e.`name` AS name";
}

$postCandidates = [];
if ($joinedEd && $edPostCol !== null && $postsHasName) $postCandidates[] = "p.post_name";
if ($joinedEd && $edPostCol !== null) $postCandidates[] = "ed.`{$edPostCol}`";
$postCandidates[] = "'N/A'";
$selectParts[] = "COALESCE(" . implode(", ", $postCandidates) . ") AS post";

$selectParts[] = "c.`name` AS court_name";
$selectParts[] = "l.leave_type, l.start_date, l.end_date, l.status, l.remarks";

// Add calculation for number of days (inclusive)
$selectParts[] = "DATEDIFF(l.end_date, l.start_date) + 1 AS days";

$where = "WHERE 1=1\n";

if (($user['role'] ?? '') === 'reader' && !empty($user['court_id'])) {
    $where .= " AND ed.court_id = :cid\n";
    $params[':cid'] = $user['court_id'];
}

if ($courtId !== '') {
    $where .= " AND ed.court_id = :court_id\n";
    $params[':court_id'] = (int)$courtId;
}

if ($leaveType !== '') {
    $where .= " AND l.leave_type = :lt\n";
    $params[':lt'] = $leaveType;
}

if ($status !== '') {
    $where .= " AND l.status = :status\n";
    $params[':status'] = $status;
}

if ($employeeId !== '') {
    $where .= " AND l.`{$leavesEmpCol}` = :eid\n";
    $params[':eid'] = (int)$employeeId;
}
if ($from !== '') {
    $where .= " AND l.start_date >= :from\n";
    $params[':from'] = $from;
}
if ($to !== '') {
    $where .= " AND l.end_date <= :to\n";
    $params[':to'] = $to;
}

$sql = "SELECT " . implode(", ", $selectParts) . " " . $fromSql . $where . " ORDER BY l.start_date DESC, l.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $leaves = [];
    error_log("Leave Report Error: " . $e->getMessage());
}

// --- Calculate statistics ---
$employeeStats = [];
$totalDays = 0;
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

foreach ($leaves as $leave) {
    $empName = $leave['name'] ?? 'Unknown';
    $days = (int)($leave['days'] ?? 0);
    $leaveStatus = $leave['status'] ?? 'pending';
    
    // Employee totals
    if (!isset($employeeStats[$empName])) {
        $employeeStats[$empName] = [
            'total_days' => 0,
            'leave_count' => 0,
            'leaves' => []
        ];
    }
    $employeeStats[$empName]['total_days'] += $days;
    $employeeStats[$empName]['leave_count']++;
    $employeeStats[$empName]['leaves'][] = $leave;
    
    // Global totals
    $totalDays += $days;
    
    // Status counts
    if ($leaveStatus === 'pending') $pendingCount++;
    elseif ($leaveStatus === 'approved') $approvedCount++;
    elseif ($leaveStatus === 'rejected') $rejectedCount++;
}

// Group by leave type for summary
$leaveTypeStats = [];
foreach ($leaves as $leave) {
    $type = $leave['leave_type'] ?? 'Other';
    $days = (int)($leave['days'] ?? 0);
    if (!isset($leaveTypeStats[$type])) {
        $leaveTypeStats[$type] = [
            'count' => 0,
            'days' => 0
        ];
    }
    $leaveTypeStats[$type]['count']++;
    $leaveTypeStats[$type]['days'] += $days;
}
?>

<style>
    :root {
        --primary-color: #005566;
        --secondary-color: #007bff;
        --success-color: #28a745;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --info-color: #17a2b8;
    }

    .page-title {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        position: relative;
        overflow: hidden;
    }
    .page-title::before {
        content: '\f073';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: 20px;
        bottom: -20px;
        font-size: 8rem;
        opacity: 0.1;
        color: white;
    }
    .page-title h2 {
        margin: 0;
        font-weight: 700;
        font-size: 2.2rem;
    }
    .page-title p {
        margin: 0.5rem 0 0;
        opacity: 0.95;
        font-size: 1.1rem;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border-left: 4px solid var(--primary-color);
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,85,102,0.15);
    }
    .stat-card .stat-icon {
        position: absolute;
        right: 1rem;
        top: 1rem;
        font-size: 2.5rem;
        opacity: 0.2;
        color: var(--primary-color);
    }
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
        margin: 0;
        line-height: 1.2;
    }
    .stat-label {
        color: #6c757d;
        margin: 0.3rem 0 0;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .stat-total {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        border-left: none;
    }
    .stat-total .stat-value,
    .stat-total .stat-label {
        color: white;
    }
    .stat-total .stat-icon {
        color: white;
        opacity: 0.3;
    }

    /* Filter Card */
    .filter-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 2rem;
        border: 1px solid rgba(0,85,102,0.1);
    }
    .filter-header {
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 1rem 1.5rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .filter-header i {
        font-size: 1.2rem;
    }
    .filter-body {
        padding: 1.5rem;
        background: #f8fafc;
    }
    .filter-label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.9rem;
        margin-bottom: 0.3rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .filter-label i {
        color: var(--primary-color);
        font-size: 0.9rem;
    }
    .filter-control {
        border-radius: 8px;
        border: 2px solid #e9ecef;
        padding: 0.6rem 1rem;
        transition: all 0.3s;
    }
    .filter-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(0,85,102,0.15);
    }
    .filter-control:hover {
        border-color: #adb5bd;
    }

    .btn-apply {
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.7rem 2rem;
        font-weight: 500;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-apply:hover {
        background: var(--secondary-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,123,255,0.3);
    }
    .btn-print {
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.7rem 2rem;
        font-weight: 500;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-print:hover {
        background: #5a6268;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(108,117,125,0.3);
    }
    .btn-excel {
        background: var(--success-color);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.7rem 2rem;
        font-weight: 500;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-excel:hover {
        background: #218838;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(40,167,69,0.3);
    }

    /* Summary Cards */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .summary-card {
        background: white;
        border-radius: 10px;
        padding: 1rem;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        border: 1px solid #e9ecef;
    }
    .summary-title {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }
    .summary-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-color);
    }

    /* Table Styles */
    .table-container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .table-header {
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 1rem 1.5rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .table-header .badge {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
    }
    .table {
        margin: 0;
        font-size: 0.95rem;
    }
    .table thead {
        background: linear-gradient(90deg, #f8f9fa, #e9ecef);
    }
    .table thead th {
        color: var(--primary-color);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        padding: 1rem;
        border-bottom: 2px solid var(--primary-color);
        white-space: nowrap;
    }
    .table tbody tr {
        transition: all 0.2s;
    }
    .table tbody tr:hover {
        background-color: #f1f8ff !important;
        transform: scale(1.01);
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .table td {
        padding: 1rem;
        vertical-align: middle;
    }

    .status-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        display: inline-block;
    }
    .status-approved {
        background: #d4edda;
        color: #155724;
    }
    .status-pending {
        background: #fff3cd;
        color: #856404;
    }
    .status-rejected {
        background: #f8d7da;
        color: #721c24;
    }

    .total-row {
        background: linear-gradient(90deg, #e9ecef, #dee2e6);
        font-weight: 700;
        border-top: 2px solid var(--primary-color);
    }
    .total-row td {
        padding: 1rem;
    }

    /* Chart Container */
    .chart-container {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: 1px solid #e9ecef;
    }
    .chart-title {
        color: var(--primary-color);
        font-weight: 600;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .chart-bars {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        height: 200px;
    }
    .chart-bar-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }
    .chart-bar {
        width: 60px;
        background: linear-gradient(to top, var(--primary-color), var(--secondary-color));
        border-radius: 8px 8px 0 0;
        transition: height 0.3s;
        position: relative;
    }
    .chart-bar:hover {
        opacity: 0.9;
    }
    .chart-bar .tooltip {
        position: absolute;
        top: -30px;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: white;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
        white-space: nowrap;
        display: none;
    }
    .chart-bar:hover .tooltip {
        display: block;
    }
    .chart-label {
        font-size: 0.8rem;
        color: #6c757d;
        text-align: center;
        max-width: 100px;
        word-wrap: break-word;
    }

    /* Print Styles */
    @media print {
        .page-title,
        .filter-card,
        .btn-apply,
        .btn-print,
        .btn-excel,
        .stats-grid,
        .summary-grid,
        .chart-container,
        footer,
        header,
        nav {
            display: none !important;
        }
        .table-container {
            box-shadow: none;
            border: 1px solid #ddd;
        }
        .table-header {
            background: #005566 !important;
            color: white !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .table thead th {
            background: #f0f0f0 !important;
            color: black !important;
        }
        .status-badge {
            border: 1px solid #000;
            background: none !important;
            color: black !important;
        }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .page-title h2 { font-size: 1.6rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .table td { white-space: nowrap; }
        .filter-body .row > div {
            margin-bottom: 1rem;
        }
    }
</style>

<div class="container-fluid mt-4">
    <!-- Page Title -->
    <div class="page-title">
        <h2><i class="fas fa-calendar-alt me-3"></i>Leave Management Report</h2>
        <p>Comprehensive leave analysis and employee attendance tracking</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?= count($employeeStats) ?></div>
            <div class="stat-label">Employees with Leaves</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-value"><?= $totalDays ?></div>
            <div class="stat-label">Total Leave Days</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?= $pendingCount ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?= $approvedCount ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card stat-total">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value"><?= count($leaves) ?></div>
            <div class="stat-label">Total Records</div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="filter-header">
            <i class="fas fa-filter"></i>
            <span>Filter Leave Records</span>
        </div>
        <div class="filter-body">
            <form method="get" id="filterForm">
                <input type="hidden" name="page" value="casual_leave_report">
                
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="filter-label">
                            <i class="fas fa-building"></i> Court
                        </label>
                        <select name="court_id" class="form-select filter-control" onchange="this.form.submit()">
                            <option value="">-- All Courts --</option>
                            <?php foreach ($courts as $court): ?>
                                <option value="<?= $court['id'] ?>" <?= ($courtId == $court['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($court['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="filter-label">
                            <i class="fas fa-user-tie"></i> Employee
                        </label>
                        <select name="employee_id" class="form-select filter-control">
                            <option value="">-- All Employees --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= ($employeeId == $emp['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="filter-label">
                            <i class="fas fa-tag"></i> Leave Type
                        </label>
                        <select name="leave_type" class="form-select filter-control">
                            <option value="">-- All Types --</option>
                            <?php foreach ($leaveTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>" <?= ($leaveType === $type) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="filter-label">
                            <i class="fas fa-check-circle"></i> Status
                        </label>
                        <select name="status" class="form-select filter-control">
                            <option value="">-- All Status --</option>
                            <option value="pending" <?= ($status === 'pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= ($status === 'approved') ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= ($status === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="filter-label">
                            <i class="fas fa-calendar-range"></i> Date Range
                        </label>
                        <div class="row g-2">
                            <div class="col">
                                <input type="date" name="from" class="form-control filter-control" value="<?= htmlspecialchars($from) ?>">
                            </div>
                            <div class="col">
                                <input type="date" name="to" class="form-control filter-control" value="<?= htmlspecialchars($to) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn-apply">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button type="button" onclick="printReport()" class="btn-print">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" onclick="exportToExcel()" class="btn-excel">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                        <a href="?page=casual_leave_report" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>

            <?php if (!empty($from) || !empty($to) || !empty($courtId) || !empty($employeeId) || !empty($leaveType) || !empty($status)): ?>
                <div class="mt-3 pt-2 border-top">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Active filters: 
                        <?php if (!empty($from)): ?>
                            <span class="badge bg-info me-1">From: <?= date('d-m-Y', strtotime($from)) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($to)): ?>
                            <span class="badge bg-info me-1">To: <?= date('d-m-Y', strtotime($to)) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($courtId)): ?>
                            <span class="badge bg-info me-1">Court selected</span>
                        <?php endif; ?>
                        <?php if (!empty($employeeId)): ?>
                            <span class="badge bg-info me-1">Specific employee</span>
                        <?php endif; ?>
                        <?php if (!empty($leaveType)): ?>
                            <span class="badge bg-info me-1"><?= htmlspecialchars($leaveType) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($status)): ?>
                            <span class="badge bg-info me-1"><?= ucfirst($status) ?></span>
                        <?php endif; ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary by Leave Type -->
    <?php if (!empty($leaveTypeStats)): ?>
    <div class="summary-grid">
        <?php foreach ($leaveTypeStats as $type => $stats): ?>
        <div class="summary-card">
            <div class="summary-title"><?= htmlspecialchars($type) ?></div>
            <div class="summary-value"><?= $stats['days'] ?> days</div>
            <small class="text-muted"><?= $stats['count'] ?> leaves</small>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main Report Table -->
    <div class="table-container">
        <div class="table-header">
            <span>
                <i class="fas fa-table me-2"></i>
                Leave Records
            </span>
            <span class="badge">
                <i class="fas fa-calendar me-1"></i> 
                <?= date('d M Y', strtotime($from)) ?> - <?= date('d M Y', strtotime($to)) ?>
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="leaveTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Post</th>
                        <th>Court</th>
                        <th>Leave Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Total Availed</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaves)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-5">
                                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Leave Records Found</h5>
                                <p class="text-muted">Try adjusting your filters or select a different date range.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $serial = 1; ?>
                        <?php foreach ($leaves as $lv): 
                            $empName = $lv['name'] ?? 'Unknown';
                            $totalAvailed = $employeeStats[$empName]['total_days'] ?? 0;
                            $statusClass = '';
                            $statusText = $lv['status'] ?? 'pending';
                            
                            if ($statusText === 'approved') $statusClass = 'status-approved';
                            elseif ($statusText === 'pending') $statusClass = 'status-pending';
                            elseif ($statusText === 'rejected') $statusClass = 'status-rejected';
                        ?>
                            <tr>
                                <td><?= $serial++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($lv['name'] ?? '') ?></strong>
                                </td>
                                <td><?= htmlspecialchars($lv['post'] ?? '') ?></td>
                                <td>
                                    <span class="d-flex align-items-center gap-1">
                                        <i class="fas fa-building text-muted small"></i>
                                        <?= htmlspecialchars($lv['court_name'] ?? '') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($lv['leave_type'] ?? '') ?></span>
                                </td>
                                <td><?= date('d-m-Y', strtotime($lv['start_date'])) ?></td>
                                <td><?= date('d-m-Y', strtotime($lv['end_date'])) ?></td>
                                <td class="text-center fw-bold"><?= $lv['days'] ?? '0' ?></td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= ucfirst($statusText) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary rounded-pill"><?= $totalAvailed ?> days</span>
                                </td>
                                <td>
                                    <?php if (!empty($lv['remarks'])): ?>
                                        <span title="<?= htmlspecialchars($lv['remarks']) ?>">
                                            <?= strlen($lv['remarks']) > 20 ? substr(htmlspecialchars($lv['remarks']), 0, 20) . '...' : htmlspecialchars($lv['remarks']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Total Row -->
                        <tr class="total-row">
                            <td colspan="7" class="text-end fw-bold">Grand Total:</td>
                            <td class="text-center fw-bold"><?= $totalDays ?></td>
                            <td colspan="3"></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Table Footer -->
        <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                <i class="fas fa-file me-1"></i>
                Showing <?= count($leaves) ?> records
                <?php if (!empty($employeeStats)): ?>
                    • <?= count($employeeStats) ?> employees
                <?php endif; ?>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyTable()">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printReport()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Print Report Function
function printReport() {
    const printContents = document.getElementById('leaveTable').outerHTML;
    const title = `
        <div style="text-align:center; margin:40px 0; font-family: Arial, sans-serif;">
            <h2>Leave Management Report</h2>
            <p><strong>Generated on:</strong> ${new Date().toLocaleDateString('en-GB')}</p>
            <p><strong>Period:</strong> <?= date('d-m-Y', strtotime($from)) ?> to <?= date('d-m-Y', strtotime($to)) ?></p>
        </div>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Leave Report</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th { background: #005566; color: white; padding: 10px; }
                    td, th { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    tr:nth-child(even) { background: #f9f9f9; }
                    .status-badge { padding: 3px 8px; border-radius: 4px; }
                    .footer { margin-top: 30px; text-align: right; }
                </style>
            </head>
            <body>
                ${title}
                ${printContents}
                <div class="footer">
                    <p>Generated by Court Management System</p>
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Export to Excel
function exportToExcel() {
    const table = document.getElementById('leaveTable');
    const rows = [];
    
    // Get headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.innerText);
    });
    rows.push(headers.join(','));
    
    // Get data rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        if (tr.classList.contains('total-row')) return; // Skip total row for Excel
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            // Clean the text - remove HTML tags and extra spaces
            let text = td.innerText.replace(/\s+/g, ' ').trim();
            // Escape quotes
            text = text.replace(/"/g, '""');
            row.push('"' + text + '"');
        });
        rows.push(row.join(','));
    });
    
    // Add total row
    const totalRow = [];
    table.querySelectorAll('tbody tr.total-row td').forEach(td => {
        let text = td.innerText.replace(/\s+/g, ' ').trim();
        text = text.replace(/"/g, '""');
        totalRow.push('"' + text + '"');
    });
    if (totalRow.length) rows.push(totalRow.join(','));
    
    // Download CSV
    const csvContent = rows.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'leave_report_' + new Date().toISOString().slice(0,10) + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Copy table to clipboard
function copyTable() {
    const table = document.getElementById('leaveTable');
    const range = document.createRange();
    range.selectNode(table);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    
    try {
        document.execCommand('copy');
        alert('Table copied to clipboard!');
    } catch (err) {
        alert('Failed to copy table');
    }
    
    window.getSelection().removeAllRanges();
}

// Auto-submit form when employee selects change (optional)
document.querySelector('select[name="employee_id"]').addEventListener('change', function() {
    document.getElementById('filterForm').submit();
});

// Date range validation
document.querySelector('input[name="to"]').addEventListener('change', function() {
    const from = document.querySelector('input[name="from"]').value;
    const to = this.value;
    
    if (from && to && to < from) {
        alert('To date cannot be earlier than From date');
        this.value = from;
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+P for print
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        printReport();
    }
    
    // Ctrl+E for export
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        exportToExcel();
    }
});
</script>

<?php include __DIR__ . '/footer.php'; ?>