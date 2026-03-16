<?php
include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php';
$user = $_SESSION['user'] ?? null;

if (!$user || ($user['role'] ?? '') !== 'admin') {
    echo "<div class='alert alert-danger m-4'>Access denied</div>";
    include __DIR__ . '/footer.php';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<div class='alert alert-danger m-4'>Invalid employee.</div>";
    include __DIR__ . '/footer.php';
    exit;
}

/* ================= FETCH EMPLOYEE ================= */
$sql = "
    SELECT ed.id, ed.name, ed.father_name, ed.cnic, ed.bps, ed.pic,
           ed.date_of_birth, ed.date_of_appointment, ed.date_of_retirement,
           p.post_name, c.name AS court_name
    FROM employee_details ed
    JOIN posts p ON p.id = ed.post_id
    JOIN courts c ON c.id = ed.court_id
    WHERE ed.id = ?
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    echo "<div class='alert alert-warning m-4'>Employee not found.</div>";
    include __DIR__ . '/footer.php';
    exit;
}

$picUrl = !empty($emp['pic']) ? '../uploads/employees/' . $emp['pic'] : null;

// Calculate age and service information
$today = new DateTime();
$retirementDate = !empty($emp['date_of_retirement']) ? new DateTime($emp['date_of_retirement']) : null;
$appointmentDate = !empty($emp['date_of_appointment']) ? new DateTime($emp['date_of_appointment']) : null;
$birthDate = !empty($emp['date_of_birth']) ? new DateTime($emp['date_of_birth']) : null;

$age = $birthDate ? $birthDate->diff($today)->y : null;
$yearsOfService = $appointmentDate ? $appointmentDate->diff($today)->y : null;
$daysToRetirement = $retirementDate ? $today->diff($retirementDate)->days : null;

$retirementStatus = '';
$retirementClass = '';
if ($retirementDate) {
    if ($retirementDate < $today) {
        $retirementStatus = 'Retired';
        $retirementClass = 'bg-danger';
    } elseif ($daysToRetirement <= 365) {
        $retirementStatus = 'Retiring Soon (' . $daysToRetirement . ' days)';
        $retirementClass = 'bg-warning text-dark';
    } else {
        $retirementStatus = 'Active';
        $retirementClass = 'bg-success';
    }
}

/* ================= TRANSFER HISTORY ================= */
$hst = $pdo->prepare("
    SELECT th.*, co.name AS old_court, cn.name AS new_court
    FROM transfer_history th
    LEFT JOIN courts co ON co.id = th.old_court_id
    LEFT JOIN courts cn ON cn.id = th.new_court_id
    WHERE th.employee_id = ?
    ORDER BY th.transfer_date DESC
");
$hst->execute([$id]);
$history = $hst->fetchAll(PDO::FETCH_ASSOC);

/* ================= FILES ================= */
$filesStmt = $pdo->prepare("
    SELECT f.id, f.file_name, f.category, f.created_at,
           e.name AS uploaded_by
    FROM files f
    LEFT JOIN employees e ON f.owner_id = e.id
    WHERE f.emp_detail_id = ?
    ORDER BY f.created_at DESC
");
$filesStmt->execute([$id]);
$files = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= LEAVE STATISTICS ================= */
$leavesStmt = $pdo->prepare("
    SELECT 
        l.leave_type,
        COUNT(*) as leave_count,
        SUM(DATEDIFF(l.end_date, l.start_date) + 1) as total_days,
        SUM(CASE WHEN l.status = 'approved' THEN DATEDIFF(l.end_date, l.start_date) + 1 ELSE 0 END) as approved_days,
        SUM(CASE WHEN l.status = 'pending' THEN 1 ELSE 0 END) as pending_count
    FROM leaves l
    WHERE l.employee_detail_id = ?
    GROUP BY l.leave_type
    ORDER BY l.leave_type
");
$leavesStmt->execute([$id]);
$leaveStats = $leavesStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= CASUAL LEAVES AVAILED (Detailed) ================= */
$leavesDetailStmt = $pdo->prepare("
    SELECT l.leave_type, l.start_date, l.end_date, l.remarks, l.status,
           DATEDIFF(l.end_date, l.start_date) + 1 AS days
    FROM leaves l
    WHERE l.employee_detail_id = ?
    ORDER BY l.start_date DESC
");
$leavesDetailStmt->execute([$id]);
$leavesDetail = $leavesDetailStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total leave days by type
$totalLeaveDays = [];
foreach ($leavesDetail as $leave) {
    $type = $leave['leave_type'];
    if (!isset($totalLeaveDays[$type])) {
        $totalLeaveDays[$type] = 0;
    }
    $totalLeaveDays[$type] += (int)$leave['days'];
}

/* ================= COMPLAINTS HISTORY ================= */
$complaintsStmt = $pdo->prepare("
    SELECT c.*, 
           COALESCE(ed.name, e.name) AS employee_name
    FROM complaints c
    LEFT JOIN employee_details ed ON ed.id = c.employee_id
    LEFT JOIN employees e ON e.id = c.employee_id
    WHERE c.employee_id = ?
    ORDER BY c.created_at DESC
    LIMIT 5
");
$complaintsStmt->execute([$id]);
$complaints = $complaintsStmt->fetchAll(PDO::FETCH_ASSOC);
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

    .profile-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: #fff;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .profile-stats-card {
        background: white;
        border-radius: 10px;
        padding: 1rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border-left: 4px solid var(--primary-color);
        transition: transform 0.3s;
    }
    .profile-stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.1);
    }
    .stats-number {
        font-size: 1.8rem;
        font-weight: bold;
        color: var(--primary-color);
        line-height: 1;
    }
    .stats-label {
        color: #666;
        font-size: 0.85rem;
        margin-top: 0.3rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        margin-bottom: 1.5rem;
        border: 1px solid #e9ecef;
    }
    .info-card-title {
        color: var(--primary-color);
        font-weight: 600;
        margin-bottom: 1.2rem;
        padding-bottom: 0.8rem;
        border-bottom: 2px solid #e9ecef;
        display: flex;
        align-items: center;
    }
    .info-card-title i {
        margin-right: 10px;
        font-size: 1.2rem;
    }

    .detail-item {
        margin-bottom: 1rem;
        padding: 0.5rem;
        border-radius: 8px;
        transition: background-color 0.2s;
    }
    .detail-item:hover {
        background-color: #f8f9fa;
    }
    .detail-label {
        font-size: 0.85rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.2rem;
    }
    .detail-value {
        font-size: 1.1rem;
        font-weight: 500;
        color: #333;
    }

    .profile-photo {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transition: transform 0.3s;
        cursor: pointer;
    }
    .profile-photo:hover {
        transform: scale(1.02);
    }

    .status-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        display: inline-block;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-retired { background: #f8d7da; color: #721c24; }
    .status-retiring-soon { background: #fff3cd; color: #856404; }

    .action-btn {
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        transition: all 0.3s;
        margin: 0 0.2rem;
    }
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }

    .timeline {
        position: relative;
        padding: 20px 0;
    }
    .timeline-item {
        padding: 15px 0;
        border-left: 2px solid var(--primary-color);
        margin-left: 20px;
        padding-left: 20px;
        position: relative;
    }
    .timeline-item:before {
        content: '';
        width: 12px;
        height: 12px;
        background: var(--primary-color);
        border-radius: 50%;
        position: absolute;
        left: -7px;
        top: 20px;
    }
    .timeline-date {
        font-size: 0.85rem;
        color: #6c757d;
    }
    .timeline-content {
        margin-top: 5px;
        font-weight: 500;
    }

    .leave-stat-card {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 10px;
        padding: 1rem;
        text-align: center;
        border-bottom: 3px solid var(--primary-color);
    }
    .leave-stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: var(--primary-color);
    }

    .print-only { display: none; }

    @media print {
        @page { 
            size: A4 portrait; 
            margin: 10mm; 
        }
        html, body { 
            margin: 0 !important; 
            padding: 0 !important; 
            font-family: Arial, sans-serif; 
            font-size: 11pt; 
            background: #fff !important; 
            color: #000 !important; 
        }
        header, footer, nav, .navbar, .breadcrumb, .btn, .modal, 
        .no-print, .d-print-none, .action-btn, .profile-header { 
            display: none !important; 
        }
        .container, .container-fluid, #printSection { 
            max-width: 100% !important; 
            width: 100% !important; 
            margin: 0 !important; 
            padding: 0 !important; 
        }
        .print-only { 
            display: block !important; 
        }
        .card, .shadow-sm { 
            box-shadow: none !important; 
            border: none !important; 
        }
        .print-header { 
            text-align: center; 
            margin-bottom: 25px; 
            border-bottom: 2px solid #000; 
            padding-bottom: 12px; 
        }
        .print-header h3 { 
            font-size: 18pt; 
            margin: 0; 
            font-weight: bold; 
        }
        .print-header p { 
            font-size: 12pt; 
            margin: 6px 0 0; 
        }
        .print-profile { 
            display: flex; 
            align-items: flex-start; 
            gap: 30px; 
            margin-bottom: 30px; 
        }
        .print-photo-circle { 
            width: 150px; 
            height: 150px; 
            border-radius: 50%; 
            overflow: hidden; 
            border: 3px solid #000; 
            flex-shrink: 0; 
        }
        .print-photo-circle img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }
        .print-details-inline { 
            flex: 1; 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 12px 20px; 
        }
        .print-label { 
            font-weight: bold; 
            min-width: 130px; 
            display: inline-block; 
        }
        .section-title { 
            font-size: 14pt; 
            font-weight: bold; 
            border-bottom: 1px solid #000; 
            margin: 25px 0 12px; 
            padding-bottom: 4px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse !important; 
            margin-top: 8px; 
        }
        table th, table td { 
            border: 1px solid #000 !important; 
            padding: 6px !important; 
            font-size: 11pt; 
            text-align: center; 
            vertical-align: middle; 
        }
        table th { 
            background: #f0f0f0 !important; 
            font-weight: bold; 
        }
    }
</style>

<div class="container-fluid px-4" id="printSection">
    <!-- Screen Header -->
    <div class="profile-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-person-badge me-2"></i>Employee Profile
            </h4>
            <small class="opacity-75">View detailed employee information and records</small>
        </div>
        <div class="btn-group">
            <a href="?page=employee_search" class="btn btn-outline-light action-btn">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
            <a href="?page=employee_details&edit=<?= (int)$emp['id'] ?>" class="btn btn-outline-light action-btn">
                <i class="bi bi-pencil-square me-1"></i>Edit
            </a>
            <a href="?page=transfer_posting&emp_id=<?= (int)$emp['id'] ?>" class="btn btn-outline-light action-btn">
                <i class="bi bi-arrow-left-right me-1"></i>Transfer
            </a>
            <button onclick="window.print()" class="btn btn-light action-btn">
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
    </div>

    <!-- Print Only Header -->
    <div class="print-only print-header">
        <h3>EMPLOYEE PROFILE</h3>
        <p>District & Subordinate Courts, Jamshoro</p>
    </div>

    <!-- Quick Stats Row -->
    <div class="row mb-4 d-print-none">
        <div class="col-md-3">
            <div class="profile-stats-card">
                <div class="stats-number"><?= $age ?? '—' ?></div>
                <div class="stats-label">Age (Years)</div>
                <small class="text-muted">As of today</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="profile-stats-card">
                <div class="stats-number"><?= $yearsOfService ?? '—' ?></div>
                <div class="stats-label">Years of Service</div>
                <small class="text-muted">Total experience</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="profile-stats-card">
                <div class="stats-number"><?= $daysToRetirement !== null ? $daysToRetirement : '—' ?></div>
                <div class="stats-label">Days to Retirement</div>
                <small class="text-muted">Remaining service</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="profile-stats-card">
                <div class="stats-number"><?= count($history) ?></div>
                <div class="stats-label">Transfers</div>
                <small class="text-muted">Total movements</small>
            </div>
        </div>
    </div>

    <!-- Print Layout -->
    <div class="print-only print-profile">
        <div class="print-photo-circle">
            <?php if ($picUrl): ?>
                <img src="<?= htmlspecialchars($picUrl) ?>" alt="Employee Photo">
            <?php else: ?>
                <div style="width:100%;height:100%;background:#ddd;display:flex;align-items:center;justify-content:center;color:#666;font-size:10pt;">
                    No Photo
                </div>
            <?php endif; ?>
        </div>
        <div class="print-details-inline">
            <div><span class="print-label">Name:</span> <?= htmlspecialchars($emp['name']) ?></div>
            <div><span class="print-label">Father's Name:</span> <?= htmlspecialchars($emp['father_name']) ?></div>
            <div><span class="print-label">CNIC:</span> <?= htmlspecialchars($emp['cnic'] ?: '-') ?></div>
            <div><span class="print-label">Designation:</span> <?= htmlspecialchars($emp['post_name']) ?></div>
            <div><span class="print-label">Court:</span> <?= htmlspecialchars($emp['court_name']) ?></div>
            <div><span class="print-label">BPS:</span> <?= htmlspecialchars($emp['bps']) ?></div>
            <div><span class="print-label">Date of Birth:</span> <?= $emp['date_of_birth'] ? date('d-m-Y', strtotime($emp['date_of_birth'])) : '-' ?></div>
            <div><span class="print-label">Appointment Date:</span> <?= date('d-m-Y', strtotime($emp['date_of_appointment'])) ?></div>
            <div><span class="print-label">Retirement Date:</span> <?= date('d-m-Y', strtotime($emp['date_of_retirement'])) ?></div>
            <div><span class="print-label">Status:</span> <?= $retirementStatus ?: 'Active' ?></div>
        </div>
    </div>

    <!-- Main Profile Card -->
    <div class="info-card">
        <div class="info-card-title">
            <i class="bi bi-person-vcard"></i> Personal Information
            <?php if ($retirementStatus): ?>
                <span class="status-badge ms-3 status-<?= strtolower(str_replace(' ', '-', $retirementStatus)) ?>">
                    <?= $retirementStatus ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="row">
            <div class="col-md-3 text-center">
                <div class="profile-photo" onclick="showPic('<?= htmlspecialchars($picUrl) ?>')" data-bs-toggle="modal" data-bs-target="#picModal">
                    <?php if ($picUrl): ?>
                        <img src="<?= htmlspecialchars($picUrl) ?>" class="img-fluid w-100" alt="Profile Photo">
                    <?php else: ?>
                        <div class="bg-light p-5 text-center">
                            <i class="bi bi-person display-1 text-muted"></i>
                            <p class="mt-2">No Photo</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-9">
                <div class="row">
                    <div class="col-md-4">
                        <div class="detail-item">
                            <div class="detail-label">Full Name</div>
                            <div class="detail-value"><?= htmlspecialchars($emp['name']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-item">
                            <div class="detail-label">Father's Name</div>
                            <div class="detail-value"><?= htmlspecialchars($emp['father_name']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-item">
                            <div class="detail-label">CNIC Number</div>
                            <div class="detail-value"><?= htmlspecialchars($emp['cnic'] ?: '—') ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-item">
                            <div class="detail-label">Date of Birth</div>
                            <div class="detail-value">
                                <?= $emp['date_of_birth'] ? date('d F Y', strtotime($emp['date_of_birth'])) : '—' ?>
                                <?php if ($age): ?>
                                    <small class="text-muted">(<?= $age ?> years)</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-item">
                            <div class="detail-label">Age</div>
                            <div class="detail-value"><?= $age ?? '—' ?> years</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-item">
                            <div class="detail-label">BPS</div>
                            <div class="detail-value"><span class="badge bg-info">BPS-<?= htmlspecialchars($emp['bps']) ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Information -->
    <div class="info-card">
        <div class="info-card-title">
            <i class="bi bi-briefcase"></i> Service Details
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="detail-item">
                    <div class="detail-label">Designation</div>
                    <div class="detail-value"><?= htmlspecialchars($emp['post_name']) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="detail-item">
                    <div class="detail-label">Court</div>
                    <div class="detail-value"><?= htmlspecialchars($emp['court_name']) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="detail-item">
                    <div class="detail-label">Appointment Date</div>
                    <div class="detail-value">
                        <?= date('d F Y', strtotime($emp['date_of_appointment'])) ?>
                        <?php if ($yearsOfService): ?>
                            <small class="text-muted">(<?= $yearsOfService ?> years ago)</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="detail-item">
                    <div class="detail-label">Retirement Date</div>
                    <div class="detail-value">
                        <?= date('d F Y', strtotime($emp['date_of_retirement'])) ?>
                        <?php if ($daysToRetirement !== null): ?>
                            <small class="text-muted">(<?= $daysToRetirement ?> days left)</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="detail-item">
                    <div class="detail-label">Years of Service</div>
                    <div class="detail-value"><?= $yearsOfService ?? '—' ?> years</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="detail-item">
                    <div class="detail-label">Service Status</div>
                    <div class="detail-value">
                        <span class="badge <?= $retirementClass ?>"><?= $retirementStatus ?: 'Active' ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Statistics -->
    <div class="info-card">
        <div class="info-card-title">
            <i class="bi bi-calendar-check"></i> Leave Statistics
        </div>
        <div class="row mb-3">
            <?php if (!empty($leaveStats)): ?>
                <?php foreach ($leaveStats as $stat): ?>
                    <div class="col-md-3 mb-3">
                        <div class="leave-stat-card">
                            <div class="leave-stat-number"><?= $stat['total_days'] ?? 0 ?></div>
                            <div class="fw-bold"><?= htmlspecialchars($stat['leave_type']) ?></div>
                            <small class="text-muted">
                                <?= $stat['leave_count'] ?> leaves (<?= $stat['approved_days'] ?> approved)
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <p class="text-muted text-center py-3">No leave records found.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Detailed Leave History -->
        <?php if (!empty($leavesDetail)): ?>
            <div class="table-responsive mt-3">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leavesDetail as $i => $leave): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($leave['leave_type']) ?></td>
                            <td><?= date('d-m-Y', strtotime($leave['start_date'])) ?></td>
                            <td><?= date('d-m-Y', strtotime($leave['end_date'])) ?></td>
                            <td class="text-center fw-bold"><?= $leave['days'] ?></td>
                            <td>
                                <span class="badge <?= $leave['status'] === 'approved' ? 'bg-success' : ($leave['status'] === 'pending' ? 'bg-warning' : 'bg-secondary') ?>">
                                    <?= ucfirst(htmlspecialchars($leave['status'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($leave['remarks'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Transfer History -->
    <?php if ($history): ?>
    <div class="info-card">
        <div class="info-card-title">
            <i class="bi bi-arrow-left-right"></i> Transfer / Posting History
        </div>
        <div class="timeline d-print-none">
            <?php foreach (array_slice($history, 0, 3) as $h): ?>
            <div class="timeline-item">
                <div class="timeline-date">
                    <i class="bi bi-calendar me-1"></i><?= date('d F Y', strtotime($h['transfer_date'])) ?>
                    <span class="badge bg-info ms-2"><?= htmlspecialchars($h['type']) ?></span>
                </div>
                <div class="timeline-content">
                    <?= htmlspecialchars($h['old_court'] ?? '—') ?> 
                    <i class="bi bi-arrow-right mx-2"></i> 
                    <?= htmlspecialchars($h['new_court'] ?? '—') ?>
                    <?php if (!empty($h['remarks'])): ?>
                        <br><small class="text-muted">Note: <?= htmlspecialchars($h['remarks']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>S.No</th>
                        <th>Type</th>
                        <th>From Court</th>
                        <th>To Court</th>
                        <th>Date</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $i => $h): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <span class="badge bg-info"><?= htmlspecialchars($h['type']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($h['old_court'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($h['new_court'] ?? '—') ?></td>
                        <td><?= date('d-m-Y', strtotime($h['transfer_date'])) ?></td>
                        <td><?= htmlspecialchars($h['remarks'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Complaints -->
    <?php if (!empty($complaints)): ?>
    <div class="info-card">
        <div class="info-card-title">
            <i class="bi bi-exclamation-triangle"></i> Recent Complaints / Requests
        </div>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['complaint_id'] ?? '#' . $c['id']) ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= ucfirst($c['kind']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($c['subject']) ?></td>
                        <td><?= htmlspecialchars($c['category'] ?: '—') ?></td>
                        <td>
                            <span class="badge priority-<?= strtolower($c['priority']) ?>">
                                <?= ucfirst($c['priority']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $c['status'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $c['status'])) ?>
                            </span>
                        </td>
                        <td><?= date('d-m-Y', strtotime($c['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Files Section -->
    <div class="info-card">
        <div class="info-card-title">
            <i class="bi bi-files"></i> Employee Record Files
            <?php if ($files): ?>
                <span class="badge bg-secondary ms-2"><?= count($files) ?> files</span>
            <?php endif; ?>
        </div>
        <?php if (!$files): ?>
            <p class="text-muted text-center py-3">No files available.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>S.No</th>
                            <th>File Name</th>
                            <th>Category</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th class="d-print-none">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $i => $file): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <i class="bi bi-file-earmark-text me-2"></i>
                                <?= htmlspecialchars($file['file_name']) ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?= htmlspecialchars($file['category'] ?: '—') ?></span>
                            </td>
                            <td><?= htmlspecialchars($file['uploaded_by'] ?? '—') ?></td>
                            <td><?= date('d-m-Y', strtotime($file['created_at'])) ?></td>
                            <td class="d-print-none">
                                <a href="?page=download&id=<?= $file['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-download"></i> Download
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Export and Action Buttons -->
    <div class="mt-4 text-center d-print-none">
        <button onclick="exportToPDF()" class="btn btn-success me-2">
            <i class="bi bi-file-pdf me-2"></i>Export PDF
        </button>
        <button onclick="window.print()" class="btn btn-primary me-2">
            <i class="bi bi-printer me-2"></i>Print Profile
        </button>
        <a href="?page=employee_search" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i>Back to Search
        </a>
    </div>
</div>

<!-- Photo Modal -->
<div class="modal fade" id="picModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img id="picModalImg" class="img-fluid rounded shadow" src="" style="max-height: 80vh;">
            </div>
        </div>
    </div>
</div>

<script>
function showPic(src) {
    document.getElementById('picModalImg').src = src;
}

function exportToPDF() {
    // Create a printable version for PDF
    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Employee Profile - <?= htmlspecialchars($emp['name']) ?></title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
    printWindow.document.write('h2 { text-align: center; color: #005566; }');
    printWindow.document.write('h4 { text-align: center; color: #666; margin-bottom: 30px; }');
    printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
    printWindow.document.write('th { background: #005566; color: white; padding: 12px; text-align: left; }');
    printWindow.document.write('td { padding: 10px; border-bottom: 1px solid #ddd; }');
    printWindow.document.write('.header { margin-bottom: 30px; }');
    printWindow.document.write('.photo { width: 150px; height: 150px; border-radius: 50%; margin: 0 auto 20px; display: block; }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    
    // Add content
    printWindow.document.write('<h2>DISTRICT COURT JAMSHORO</h2>');
    printWindow.document.write('<h4>Employee Profile - <?= htmlspecialchars($emp['name']) ?></h4>');
    
    // Get the main content
    var content = document.getElementById('printSection').cloneNode(true);
    
    // Remove buttons and action elements
    var buttons = content.querySelectorAll('.btn, .action-btn, .profile-header, .export-btn');
    buttons.forEach(btn => btn.remove());
    
    printWindow.document.write(content.outerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

// Add keyboard shortcut (Ctrl+P) to print
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        window.print();
    }
});
</script>

<?php include __DIR__ . '/footer.php'; ?>