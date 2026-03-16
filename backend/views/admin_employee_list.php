<?php
// backend/views/admin_employee_list.php

$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'admin') {
    header('Location: ?page=dashboard');
    exit;
}

include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php';

// Get statistics
$statsStmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT court_id) as total_courts,
        COUNT(*) as total_employees,
        COUNT(CASE WHEN date_of_retirement < CURDATE() THEN 1 END) as retired_employees,
        COUNT(CASE WHEN date_of_retirement BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 YEAR) THEN 1 END) as retiring_soon
    FROM employee_details
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get all employees with additional info
$stmt = $pdo->query("
    SELECT 
        ed.id,
        ed.name,
        ed.father_name,
        ed.bps,
        ed.cnic,
        ed.date_of_birth,
        ed.date_of_appointment,
        ed.date_of_retirement,
        ed.pic,
        p.post_name AS post,
        COALESCE(c.name, 'Not Assigned') AS court_name,
        c.id as court_id
    FROM employee_details ed
    LEFT JOIN posts p ON ed.post_id = p.id
    LEFT JOIN courts c ON ed.court_id = c.id
    ORDER BY court_name ASC, ed.name ASC
");
$allEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by court
$employeesByCourt = [];
$courtStats = [];
foreach ($allEmployees as $emp) {
    $court = $emp['court_name'];
    $courtId = $emp['court_id'];
    $employeesByCourt[$court][] = $emp;
    
    if (!isset($courtStats[$court])) {
        $courtStats[$court] = [
            'total' => 0,
            'court_id' => $courtId
        ];
    }
    $courtStats[$court]['total']++;
}

// Calculate today's date for age calculations
$today = new DateTime();
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

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
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .stats-card {
        background: white;
        border-radius: 10px;
        padding: 1.2rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border-left: 4px solid var(--primary-color);
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        margin-bottom: 1rem;
    }
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.1);
    }
    .stats-number {
        font-size: 2rem;
        font-weight: bold;
        color: var(--primary-color);
        line-height: 1;
    }
    .stats-label {
        color: #666;
        font-size: 0.9rem;
        margin-top: 0.3rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .stats-icon {
        position: absolute;
        right: 1rem;
        top: 1rem;
        font-size: 2.5rem;
        opacity: 0.2;
        color: var(--primary-color);
    }

    .court-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
        transition: box-shadow 0.3s;
    }
    .court-card:hover {
        box-shadow: 0 8px 25px rgba(0,85,102,0.15);
    }

    .court-header {
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 1rem 1.5rem;
        cursor: pointer;
        transition: opacity 0.3s;
    }
    .court-header:hover {
        opacity: 0.95;
    }
    .court-header.collapsed .toggle-icon {
        transform: rotate(-90deg);
    }
    .toggle-icon {
        transition: transform 0.3s;
    }

    .court-badge {
        background: rgba(255,255,255,0.2);
        padding: 0.3rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
    }

    .table-container {
        padding: 0;
        background: white;
    }

    .employee-table {
        margin-bottom: 0;
        width: 100%;
    }
    .employee-table thead th {
        background: #f8f9fa;
        color: var(--primary-color);
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.75rem;
        border-bottom: 2px solid var(--primary-color);
        white-space: nowrap;
    }
    .employee-table tbody tr {
        transition: background-color 0.2s;
    }
    .employee-table tbody tr:hover {
        background-color: #f1f8ff !important;
    }
    .employee-table td {
        padding: 0.75rem;
        vertical-align: middle;
        font-size: 0.9rem;
        white-space: nowrap;
    }

    .status-badge {
        padding: 0.3rem 0.6rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    .status-retired {
        background: #f8d7da;
        color: #721c24;
    }
    .status-retiring-soon {
        background: #fff3cd;
        color: #856404;
    }

    .action-btn {
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        transition: all 0.3s;
        margin: 0 2px;
    }
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .search-box {
        position: relative;
        margin-bottom: 1rem;
    }
    .search-box i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        z-index: 10;
    }
    .search-box input {
        padding-left: 2.5rem;
        border-radius: 25px;
        border: 1px solid #dee2e6;
        width: 100%;
    }

    .quick-filter {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        margin: 0.25rem;
        border: 1px solid #dee2e6;
        background: white;
        color: #495057;
        transition: all 0.3s;
        cursor: pointer;
    }
    .quick-filter:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    .quick-filter.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    .btn-light {
        background: white;
        color: var(--primary-color);
        border: 1px solid rgba(255,255,255,0.3);
    }
    .btn-light:hover {
        background: rgba(255,255,255,0.9);
        color: var(--primary-color);
    }

    @media print {
        body * { visibility: hidden; }
        .card, .card * { visibility: visible; }
        .card { 
            position: relative; 
            left: 0; 
            top: 0; 
            box-shadow: none !important; 
            margin-bottom: 30px; 
            break-inside: avoid; 
        }
        .court-header { 
            background: #005566 !important; 
            color: white !important; 
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
        }
        .collapse { 
            display: block !important; 
            height: auto !important;
            opacity: 1 !important;
        }
        .employee-table { 
            font-size: 9px; 
            width: 100% !important;
        }
        .employee-table th, .employee-table td { 
            border: 1px solid #000 !important; 
            padding: 4px !important; 
        }
        .btn, .stats-card, .search-box, .quick-filter, .action-btn { 
            display: none !important; 
        }
        .page-title {
            background: #005566 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<div class="container-fluid py-4">
    <!-- Page Title -->
    <div class="page-title d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0"><i class="bi bi-building me-2"></i>Employees List - Court Wise</h3>
            <small class="opacity-75">Complete employee directory organized by court</small>
        </div>
        <div>
            <button type="button" class="btn btn-light me-2" onclick="printAll()">
                <i class="bi bi-printer me-1"></i> Print All
            </button>
            <button type="button" class="btn btn-success" onclick="exportAllToExcel()">
                <i class="bi bi-file-earmark-excel me-1"></i> Export All
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon"><i class="bi bi-building"></i></div>
                <div class="stats-number"><?= number_format($stats['total_courts'] ?? 0) ?></div>
                <div class="stats-label">Courts</div>
                <small class="text-muted">With assigned employees</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon"><i class="bi bi-people"></i></div>
                <div class="stats-number"><?= number_format($stats['total_employees'] ?? 0) ?></div>
                <div class="stats-label">Total Employees</div>
                <small class="text-muted">Active in system</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon"><i class="bi bi-clock-history"></i></div>
                <div class="stats-number"><?= number_format($stats['retiring_soon'] ?? 0) ?></div>
                <div class="stats-label">Retiring Soon</div>
                <small class="text-muted">Within 1 year</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon"><i class="bi bi-person-x"></i></div>
                <div class="stats-number"><?= number_format($stats['retired_employees'] ?? 0) ?></div>
                <div class="stats-label">Retired</div>
                <small class="text-muted">Already retired</small>
            </div>
        </div>
    </div>

    <!-- Search and Filter Bar -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="globalSearch" class="form-control" placeholder="Search across all employees...">
            </div>
        </div>
        <div class="col-md-6 text-end">
            <button class="quick-filter" onclick="filterAll('all')">All Courts</button>
            <button class="quick-filter" onclick="filterAll('active')">Active Only</button>
            <button class="quick-filter" onclick="filterAll('retiring')">Retiring Soon</button>
            <button class="quick-filter" onclick="expandAll()">
                <i class="bi bi-arrows-expand me-1"></i>Expand All
            </button>
            <button class="quick-filter" onclick="collapseAll()">
                <i class="bi bi-arrows-collapse me-1"></i>Collapse All
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($employeesByCourt)): ?>
                <div class="alert alert-info text-center py-5">
                    <i class="bi bi-inbox display-1 d-block mb-3"></i>
                    <h4>No Employees Found</h4>
                    <p class="mb-0">There are no employees in the database.</p>
                </div>
            <?php else: ?>
                <div id="courtList">
                    <?php foreach ($employeesByCourt as $courtName => $staff): 
                        $count = count($staff);
                        $safeId = 'court_' . preg_replace('/[^a-zA-Z0-9]/', '_', $courtName);
                        $courtData = $courtStats[$courtName] ?? ['court_id' => null];
                    ?>
                        <div class="court-card mb-3" data-court="<?= htmlspecialchars($courtName) ?>">
                            <!-- Court Header -->
                            <div class="court-header" onclick="toggleCourt('<?= $safeId ?>', this)">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0 d-inline-flex align-items-center">
                                            <i class="bi bi-building me-2"></i>
                                            <strong><?= htmlspecialchars($courtName) ?></strong>
                                            <span class="court-badge ms-3">
                                                <i class="bi bi-people me-1"></i> <?= $count ?> Employee<?= $count > 1 ? 's' : '' ?>
                                            </span>
                                        </h5>
                                    </div>
                                    <div>
                                        <?php if ($courtData['court_id']): ?>
                                            <a href="?page=employee_search&court_id=<?= $courtData['court_id'] ?>" 
                                               class="btn btn-sm btn-light me-2" onclick="event.stopPropagation()">
                                                <i class="bi bi-search"></i> View All
                                            </a>
                                        <?php endif; ?>
                                        <i class="bi bi-chevron-down toggle-icon"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Employee Table -->
                            <div class="court-content" id="<?= $safeId ?>" style="display: block;">
                                <div class="table-container">
                                    <div class="table-responsive">
                                        <table class="table employee-table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th width="50">#</th>
                                                    <th>Photo</th>
                                                    <th>Name</th>
                                                    <th>Father's Name</th>
                                                    <th>Post</th>
                                                    <th>BPS</th>
                                                    <th>CNIC</th>
                                                    <th>DOB</th>
                                                    <th>Appointment</th>
                                                    <th>Retirement</th>
                                                    <th>Status</th>
                                                    <th width="120">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($staff as $i => $emp): 
                                                    // Calculate retirement status
                                                    $retirementStatus = '';
                                                    $statusClass = '';
                                                    if (!empty($emp['date_of_retirement'])) {
                                                        $retDate = new DateTime($emp['date_of_retirement']);
                                                        if ($retDate < $today) {
                                                            $retirementStatus = 'Retired';
                                                            $statusClass = 'status-retired';
                                                        } elseif ($retDate->diff($today)->days <= 365) {
                                                            $retirementStatus = 'Retiring Soon';
                                                            $statusClass = 'status-retiring-soon';
                                                        } else {
                                                            $retirementStatus = 'Active';
                                                            $statusClass = 'status-active';
                                                        }
                                                    }
                                                    
                                                    // Calculate age
                                                    $age = '';
                                                    if (!empty($emp['date_of_birth'])) {
                                                        $dob = new DateTime($emp['date_of_birth']);
                                                        $age = $dob->diff($today)->y;
                                                    }
                                                ?>
                                                    <tr class="employee-row" 
                                                        data-name="<?= strtolower(htmlspecialchars($emp['name'])) ?>"
                                                        data-father="<?= strtolower(htmlspecialchars($emp['father_name'] ?? '')) ?>"
                                                        data-post="<?= strtolower(htmlspecialchars($emp['post'] ?? '')) ?>"
                                                        data-cnic="<?= htmlspecialchars($emp['cnic'] ?? '') ?>">
                                                        <td><?= $i + 1 ?></td>
                                                        <td>
                                                            <?php if (!empty($emp['pic'])): ?>
                                                                <img src="../uploads/employees/<?= htmlspecialchars($emp['pic']) ?>" 
                                                                     class="rounded-circle" width="35" height="35" 
                                                                     style="object-fit: cover; border: 2px solid var(--primary-color); cursor: pointer;"
                                                                     onclick="showPhoto('<?= htmlspecialchars($emp['pic']) ?>', '<?= htmlspecialchars($emp['name']) ?>')"
                                                                     data-bs-toggle="modal" data-bs-target="#photoModal"
                                                                     title="Click to enlarge">
                                                            <?php else: ?>
                                                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                                                     style="width: 35px; height: 35px;">
                                                                    <i class="bi bi-person text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($emp['name']) ?></strong>
                                                        </td>
                                                        <td><?= htmlspecialchars($emp['father_name'] ?? '—') ?></td>
                                                        <td><?= htmlspecialchars($emp['post'] ?? '—') ?></td>
                                                        <td><span class="badge bg-info">BPS-<?= htmlspecialchars($emp['bps'] ?? '—') ?></span></td>
                                                        <td><?= htmlspecialchars($emp['cnic'] ?? '—') ?></td>
                                                        <td>
                                                            <?= $emp['date_of_birth'] ? date('d-m-Y', strtotime($emp['date_of_birth'])) : '—' ?>
                                                            <?php if ($age): ?>
                                                                <br><small class="text-muted">(<?= $age ?> yrs)</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= $emp['date_of_appointment'] ? date('d-m-Y', strtotime($emp['date_of_appointment'])) : '—' ?></td>
                                                        <td>
                                                            <?= $emp['date_of_retirement'] ? date('d-m-Y', strtotime($emp['date_of_retirement'])) : '—' ?>
                                                            <?php if (!empty($emp['date_of_retirement'])): ?>
                                                                <?php $retDate = new DateTime($emp['date_of_retirement']); ?>
                                                                <?php if ($retDate > $today): ?>
                                                                    <br><small class="text-muted">(<?= $retDate->diff($today)->days ?> days left)</small>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($retirementStatus): ?>
                                                                <span class="status-badge <?= $statusClass ?>">
                                                                    <?= $retirementStatus ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="?page=employee_profile&id=<?= $emp['id'] ?>" 
                                                                   class="btn btn-sm btn-outline-primary action-btn" 
                                                                   title="View Profile">
                                                                    <i class="bi bi-eye"></i>
                                                                </a>
                                                                <a href="?page=employee_details&edit=<?= $emp['id'] ?>" 
                                                                   class="btn btn-sm btn-outline-success action-btn"
                                                                   title="Edit Employee">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                                <a href="?page=transfer_posting&emp_id=<?= $emp['id'] ?>" 
                                                                   class="btn btn-sm btn-outline-info action-btn"
                                                                   title="Transfer">
                                                                    <i class="bi bi-arrow-left-right"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Photo Modal -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="photoModalTitle">Employee Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="photoModalImg" src="" class="img-fluid rounded" style="max-height: 70vh;">
            </div>
        </div>
    </div>
</div>

<!-- Hidden table for Excel export -->
<table id="hiddenExportTable" style="display:none;">
    <thead>
        <tr>
            <th>Court Name</th>
            <th>Name</th>
            <th>Father's Name</th>
            <th>Post</th>
            <th>BPS</th>
            <th>CNIC</th>
            <th>Date of Birth</th>
            <th>Appointment Date</th>
            <th>Retirement Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($employeesByCourt as $courtName => $staff): ?>
            <?php foreach ($staff as $emp): 
                $retirementStatus = '';
                if (!empty($emp['date_of_retirement'])) {
                    $retDate = new DateTime($emp['date_of_retirement']);
                    if ($retDate < $today) {
                        $retirementStatus = 'Retired';
                    } elseif ($retDate->diff($today)->days <= 365) {
                        $retirementStatus = 'Retiring Soon';
                    } else {
                        $retirementStatus = 'Active';
                    }
                }
            ?>
                <tr>
                    <td><?= htmlspecialchars($courtName) ?></td>
                    <td><?= htmlspecialchars($emp['name']) ?></td>
                    <td><?= htmlspecialchars($emp['father_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($emp['post'] ?? '') ?></td>
                    <td><?= htmlspecialchars($emp['bps'] ?? '') ?></td>
                    <td><?= htmlspecialchars($emp['cnic'] ?? '') ?></td>
                    <td><?= $emp['date_of_birth'] ? date('d-m-Y', strtotime($emp['date_of_birth'])) : '' ?></td>
                    <td><?= $emp['date_of_appointment'] ? date('d-m-Y', strtotime($emp['date_of_appointment'])) : '' ?></td>
                    <td><?= $emp['date_of_retirement'] ? date('d-m-Y', strtotime($emp['date_of_retirement'])) : '' ?></td>
                    <td><?= $retirementStatus ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script>
// Global search functionality
document.getElementById('globalSearch').addEventListener('keyup', function() {
    let searchText = this.value.toLowerCase();
    let rows = document.querySelectorAll('.employee-row');
    
    rows.forEach(row => {
        let name = row.dataset.name || '';
        let father = row.dataset.father || '';
        let post = row.dataset.post || '';
        let cnic = row.dataset.cnic || '';
        
        if (name.includes(searchText) || father.includes(searchText) || 
            post.includes(searchText) || cnic.includes(searchText)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Filter function
function filterAll(type) {
    // Remove active class from all filter buttons
    document.querySelectorAll('.quick-filter').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    let rows = document.querySelectorAll('.employee-row');
    
    rows.forEach(row => {
        if (type === 'all') {
            row.style.display = '';
        } else if (type === 'active') {
            let statusCell = row.querySelector('td:nth-child(11) .status-badge');
            if (statusCell && statusCell.textContent.includes('Active')) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        } else if (type === 'retiring') {
            let statusCell = row.querySelector('td:nth-child(11) .status-badge');
            if (statusCell && statusCell.textContent.includes('Retiring Soon')) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// Toggle court content
function toggleCourt(courtId, header) {
    let content = document.getElementById(courtId);
    let icon = header.querySelector('.toggle-icon');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.classList.remove('bi-chevron-down');
        icon.classList.add('bi-chevron-up');
    } else {
        content.style.display = 'none';
        icon.classList.remove('bi-chevron-up');
        icon.classList.add('bi-chevron-down');
    }
}

// Expand all courts
function expandAll() {
    document.querySelectorAll('.court-content').forEach(content => {
        content.style.display = 'block';
    });
    document.querySelectorAll('.toggle-icon').forEach(icon => {
        icon.classList.remove('bi-chevron-down');
        icon.classList.add('bi-chevron-up');
    });
}

// Collapse all courts
function collapseAll() {
    document.querySelectorAll('.court-content').forEach(content => {
        content.style.display = 'none';
    });
    document.querySelectorAll('.toggle-icon').forEach(icon => {
        icon.classList.remove('bi-chevron-up');
        icon.classList.add('bi-chevron-down');
    });
}

// Print all function
function printAll() {
    // Expand all courts for printing
    expandAll();
    
    // Trigger print after a slight delay to ensure content is expanded
    setTimeout(() => {
        window.print();
    }, 100);
}

// Photo modal
function showPhoto(picName, empName) {
    document.getElementById('photoModalImg').src = '../uploads/employees/' + picName;
    document.getElementById('photoModalTitle').textContent = empName + ' - Photo';
}

// Excel export function
function exportAllToExcel() {
    // Create a new DataTable instance on the hidden table
    let table = $('#hiddenExportTable').DataTable({
        dom: 'B',
        buttons: [{
            extend: 'excelHtml5',
            title: 'Court Management System - Employees List - ' + new Date().toLocaleDateString(),
            sheetName: 'Employees',
            customize: function(xlsx) {
                let sheet = xlsx.xl.worksheets['sheet1.xml'];
                // You can add custom styling here if needed
            }
        }],
        destroy: true
    });
    
    // Trigger the export
    table.buttons().trigger();
    
    // Destroy the DataTable instance to clean up
    table.destroy();
}

// Alternative Excel export using pure JavaScript (fallback)
function exportToExcelFallback() {
    let table = document.getElementById('hiddenExportTable');
    let html = table.outerHTML;
    let url = 'data:application/vnd.ms-excel,' + escape(html);
    let link = document.createElement('a');
    link.download = 'employees_list_' + new Date().toISOString().slice(0,10) + '.xls';
    link.href = url;
    link.click();
}

// Keyboard shortcut (Ctrl+F) for search
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('globalSearch').focus();
    }
});

// Initialize all courts as expanded
document.addEventListener('DOMContentLoaded', function() {
    expandAll();
});
</script>

<?php include __DIR__ . '/footer.php'; ?>