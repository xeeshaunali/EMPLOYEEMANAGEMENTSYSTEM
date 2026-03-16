<?php
// backend/views/add_leave.php
include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php';
$user = $_SESSION['user'] ?? [];

$success = $error = '';

// Handle success/error messages
if (isset($_GET['success'])) {
    $success = "Leave added successfully!";
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'missing': $error = "All required fields must be filled."; break;
        case 'date_order': $error = "End date cannot be before start date."; break;
        case 'db': $error = "Database error occurred. Please try again."; break;
        case 'duplicate': $error = "Leave record already exists for this date range."; break;
        case 'exceed_limit': $error = "Leave days exceed available balance."; break;
        default: $error = "An error occurred. Please check your input.";
    }
}

/* =========================
   FETCH LEAVE TYPES WITH BALANCES
========================= */
$leave_types = [];
try {
    $stmt = $pdo->query("SELECT name FROM leave_types ORDER BY name ASC");
    $leave_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $leave_types = ['Casual', 'Earned', 'Medical', 'Optional'];
}

/* =========================
   DETERMINE CURRENT USER ROLE
========================= */
$isAdmin  = ($user['role'] ?? '') === 'admin';
$isReader = ($user['role'] ?? '') === 'reader';

// For readers: force their court only
$forcedCourtId = $isReader && !empty($user['court_id']) ? (int)$user['court_id'] : null;

/* =========================
   FETCH COURTS FOR DROPDOWN (Admin only)
========================= */
$courts = [];
if ($isAdmin) {
    try {
        $stmt = $pdo->query("SELECT id, name FROM courts ORDER BY name ASC");
        $courts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $courts = [];
    }
}

/* =========================
   GET SELECTED COURT (from GET, but respect reader restriction)
========================= */
$selectedCourtId = $_GET['court_id'] ?? '';

if ($isReader) {
    // Readers cannot change court – always use their own
    $selectedCourtId = $forcedCourtId;
} elseif ($selectedCourtId === '') {
    // Admin with no selection → show all employees
    $selectedCourtId = '';
} else {
    $selectedCourtId = (int)$selectedCourtId;
}

/* =========================
   FETCH READER'S COURT NAME (for display)
========================= */
$readerCourtName = '';
if ($isReader && $forcedCourtId) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM courts WHERE id = ?");
        $stmt->execute([$forcedCourtId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $readerCourtName = $row['name'] ?? 'Unknown Court';
    } catch (Exception $e) {
        $readerCourtName = 'Unknown Court';
    }
}

/* =========================
   FETCH EMPLOYEES BASED ON SELECTED COURT
========================= */
$staff = [];
try {
    $sql = "
        SELECT
            ed.id AS emp_id,
            ed.name,
            ed.bps,
            COALESCE(p.post_name, 'N/A') AS post_name,
            COALESCE(c.name, 'N/A') AS court_name,
            (
                SELECT COALESCE(SUM(DATEDIFF(l.end_date, l.start_date) + 1), 0)
                FROM leaves l
                WHERE l.employee_detail_id = ed.id
                AND l.leave_type = 'Casual'
                AND YEAR(l.start_date) = YEAR(CURDATE())
            ) AS casual_used,
            (
                SELECT COALESCE(SUM(DATEDIFF(l.end_date, l.start_date) + 1), 0)
                FROM leaves l
                WHERE l.employee_detail_id = ed.id
                AND l.leave_type = 'Earned'
                AND YEAR(l.start_date) = YEAR(CURDATE())
            ) AS earned_used,
            (
                SELECT COALESCE(SUM(DATEDIFF(l.end_date, l.start_date) + 1), 0)
                FROM leaves l
                WHERE l.employee_detail_id = ed.id
                AND l.leave_type = 'Medical'
                AND YEAR(l.start_date) = YEAR(CURDATE())
            ) AS medical_used
        FROM employee_details ed
        LEFT JOIN posts p ON p.id = ed.post_id
        LEFT JOIN courts c ON c.id = ed.court_id
    ";

    $params = [];

    if ($selectedCourtId !== '') {
        $sql .= " WHERE ed.court_id = :court_id";
        $params[':court_id'] = $selectedCourtId;
    }
    elseif ($isReader && $forcedCourtId) {
        $sql .= " WHERE ed.court_id = :court_id";
        $params[':court_id'] = $forcedCourtId;
    }

    $sql .= " ORDER BY ed.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error loading employee list.";
    $staff = [];
}

// Leave balances by BPS (example - you can adjust these values)
$leaveBalances = [
    1 => ['casual' => 12, 'earned' => 15, 'medical' => 10],
    2 => ['casual' => 12, 'earned' => 15, 'medical' => 10],
    3 => ['casual' => 14, 'earned' => 16, 'medical' => 12],
    4 => ['casual' => 14, 'earned' => 16, 'medical' => 12],
    5 => ['casual' => 16, 'earned' => 18, 'medical' => 14],
    6 => ['casual' => 16, 'earned' => 18, 'medical' => 14],
    7 => ['casual' => 18, 'earned' => 20, 'medical' => 16],
    8 => ['casual' => 18, 'earned' => 20, 'medical' => 16],
    9 => ['casual' => 20, 'earned' => 22, 'medical' => 18],
    10 => ['casual' => 20, 'earned' => 22, 'medical' => 18],
    11 => ['casual' => 22, 'earned' => 24, 'medical' => 20],
    12 => ['casual' => 22, 'earned' => 24, 'medical' => 20],
    13 => ['casual' => 24, 'earned' => 26, 'medical' => 22],
    14 => ['casual' => 24, 'earned' => 26, 'medical' => 22],
    15 => ['casual' => 26, 'earned' => 28, 'medical' => 24],
    16 => ['casual' => 26, 'earned' => 28, 'medical' => 24],
    17 => ['casual' => 28, 'earned' => 30, 'medical' => 26],
    18 => ['casual' => 28, 'earned' => 30, 'medical' => 26],
    19 => ['casual' => 30, 'earned' => 32, 'medical' => 28],
    20 => ['casual' => 30, 'earned' => 32, 'medical' => 28],
    21 => ['casual' => 32, 'earned' => 34, 'medical' => 30],
    22 => ['casual' => 32, 'earned' => 34, 'medical' => 30],
];
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
    .info-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border-left: 4px solid var(--primary-color);
        margin-bottom: 2rem;
    }

    /* Form Card */
    .form-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 2rem;
        border: 1px solid rgba(0,85,102,0.1);
    }
    .form-header {
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 1rem 1.5rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .form-header i {
        font-size: 1.2rem;
    }
    .form-body {
        padding: 2rem;
        background: #f8fafc;
    }
    .form-label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.9rem;
        margin-bottom: 0.3rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .form-label i {
        color: var(--primary-color);
        font-size: 0.9rem;
    }
    .form-control, .form-select {
        border-radius: 8px;
        border: 2px solid #e9ecef;
        padding: 0.7rem 1rem;
        transition: all 0.3s;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(0,85,102,0.15);
    }
    .form-control:hover, .form-select:hover {
        border-color: #adb5bd;
    }

    .btn-submit {
        background: linear-gradient(90deg, var(--success-color), #20c997);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.8rem 2rem;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(40,167,69,0.3);
    }
    .btn-cancel {
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.8rem 2rem;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        text-decoration: none;
    }
    .btn-cancel:hover {
        background: #5a6268;
        color: white;
        transform: translateY(-2px);
    }

    /* Employee Card */
    .employee-info {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
        border-left: 4px solid var(--primary-color);
    }
    .employee-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--primary-color);
    }
    .employee-details {
        font-size: 0.9rem;
        color: #6c757d;
    }

    /* Balance Badge */
    .balance-badge {
        background: white;
        border-radius: 20px;
        padding: 0.2rem 0.8rem;
        font-size: 0.8rem;
        border: 1px solid var(--primary-color);
        color: var(--primary-color);
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }
    .balance-warning {
        background: #fff3cd;
        color: #856404;
        border-color: #ffc107;
    }
    .balance-critical {
        background: #f8d7da;
        color: #721c24;
        border-color: #dc3545;
    }

    /* Date Range Display */
    .date-range {
        background: #e9ecef;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .page-title h2 { font-size: 1.6rem; }
    }
</style>

<div class="container-fluid mt-4">
    <!-- Page Title -->
    <div class="page-title">
        <h2><i class="fas fa-calendar-plus me-3"></i>Add Staff Leave</h2>
        <p>Record and manage employee leave applications</p>
    </div>

    <!-- Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Quick Info Card -->
    <div class="info-card">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5><i class="fas fa-info-circle me-2" style="color: var(--primary-color);"></i>Leave Information</h5>
                <p class="text-muted mb-0">Select employee, leave type, and date range. Leave days are calculated automatically.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="badge bg-primary me-2">Casual: 24 days/year</span>
                <span class="badge bg-success me-2">Earned: 48 days/year</span>
                <span class="badge bg-info">Medical: 10-30 days/year</span>
            </div>
        </div>
    </div>

    <!-- Court Selection (Only for Admin) -->
    <?php if ($isAdmin): ?>
    <div class="form-card">
        <div class="form-header">
            <i class="fas fa-building"></i>
            <span>Select Court</span>
        </div>
        <div class="form-body">
            <form method="GET" id="courtForm">
                <input type="hidden" name="page" value="add_leave">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-gavel"></i> Court</label>
                        <select name="court_id" id="court_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- All Courts --</option>
                            <?php foreach ($courts as $court): ?>
                                <option value="<?= $court['id'] ?>" <?= ($selectedCourtId == $court['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($court['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Select a court to filter employees
                        </small>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- Reader: Show fixed court -->
    <div class="alert alert-info">
        <i class="fas fa-building me-2"></i>
        <strong>Court:</strong> <?= htmlspecialchars($readerCourtName) ?>
        <small class="ms-2">(You can only add leave for employees in your court)</small>
    </div>
    <?php endif; ?>

    <!-- Main Leave Form -->
    <div class="form-card">
        <div class="form-header">
            <i class="fas fa-pen-alt"></i>
            <span>Leave Application Form</span>
        </div>
        <div class="form-body">
            <form method="POST" action="?page=save_staff_leave" class="row g-3 needs-validation" novalidate id="leaveForm">
                <div class="col-md-12">
                    <label class="form-label"><i class="fas fa-user-tie"></i> Employee</label>
                    <select name="employee_id" id="employee_id" class="form-select" required>
                        <option value="">-- Select Employee --</option>
                        <?php if (empty($staff)): ?>
                            <option value="" disabled>No employees found</option>
                        <?php else: ?>
                            <?php foreach ($staff as $emp): 
                                $bps = (int)($emp['bps'] ?? 1);
                                $balance = $leaveBalances[$bps] ?? $leaveBalances[1];
                            ?>
                                <option value="<?= (int)$emp['emp_id'] ?>" 
                                        data-bps="<?= $bps ?>"
                                        data-casual-used="<?= $emp['casual_used'] ?? 0 ?>"
                                        data-earned-used="<?= $emp['earned_used'] ?? 0 ?>"
                                        data-medical-used="<?= $emp['medical_used'] ?? 0 ?>"
                                        data-casual-balance="<?= $balance['casual'] ?>"
                                        data-earned-balance="<?= $balance['earned'] ?>"
                                        data-medical-balance="<?= $balance['medical'] ?>">
                                    <?= htmlspecialchars($emp['name']) ?> - 
                                    <?= htmlspecialchars($emp['post_name']) ?> (BPS-<?= $emp['bps'] ?? 'N/A' ?>) - 
                                    <?= htmlspecialchars($emp['court_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div class="invalid-feedback">Please select an employee.</div>
                </div>

                <!-- Employee Info Card (hidden initially) -->
                <div id="employeeInfoCard" class="col-md-12" style="display: none;">
                    <div class="employee-info">
                        <div class="row">
                            <div class="col-md-4">
                                <span class="employee-name" id="selectedEmployeeName"></span>
                                <div class="employee-details" id="selectedEmployeePost"></div>
                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-4">
                                        <span class="balance-badge" id="casualBalance">
                                            <i class="fas fa-calendar"></i> Casual: <span>0</span>/<span>0</span>
                                        </span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="balance-badge" id="earnedBalance">
                                            <i class="fas fa-calendar"></i> Earned: <span>0</span>/<span>0</span>
                                        </span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="balance-badge" id="medicalBalance">
                                            <i class="fas fa-calendar"></i> Medical: <span>0</span>/<span>0</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-tag"></i> Leave Type</label>
                    <select name="leave_type" id="leave_type" class="form-select" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach ($leave_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>">
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select leave type.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-calendar"></i> From Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    <div class="invalid-feedback">Required.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-calendar"></i> To Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" required>
                    <div class="invalid-feedback">Required.</div>
                </div>

                <div class="col-md-6">
                    <div class="date-range">
                        <i class="fas fa-clock me-2"></i>
                        <span id="dateRangeDisplay">Select dates to calculate leave days</span>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-calculator"></i> Total Days</label>
                    <input type="number" id="total_days" name="total_days" class="form-control" readonly value="0">
                </div>

                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-exclamation-triangle"></i> Balance After</label>
                    <input type="number" id="balance_after" class="form-control" readonly value="0">
                </div>

                <div class="col-12">
                    <label class="form-label"><i class="fas fa-comment"></i> Remarks (Optional)</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Add any additional notes or comments..."></textarea>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-save"></i> Submit Leave Application
                    </button>
                    <a href="?page=dashboard" class="btn-cancel ms-2">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Employee selection handler
document.getElementById('employee_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const infoCard = document.getElementById('employeeInfoCard');
    
    if (this.value) {
        // Show employee info
        infoCard.style.display = 'block';
        
        // Get employee data
        const name = selectedOption.text.split(' - ')[0];
        const post = selectedOption.text.split(' - ')[1] || '';
        document.getElementById('selectedEmployeeName').textContent = name;
        document.getElementById('selectedEmployeePost').textContent = post;
        
        // Update balances
        const casualUsed = parseInt(selectedOption.dataset.casualUsed) || 0;
        const earnedUsed = parseInt(selectedOption.dataset.earnedUsed) || 0;
        const medicalUsed = parseInt(selectedOption.dataset.medicalUsed) || 0;
        const casualBalance = parseInt(selectedOption.dataset.casualBalance) || 12;
        const earnedBalance = parseInt(selectedOption.dataset.earnedBalance) || 15;
        const medicalBalance = parseInt(selectedOption.dataset.medicalBalance) || 10;
        
        document.getElementById('casualBalance').innerHTML = `<i class="fas fa-calendar"></i> Casual: ${casualUsed}/${casualBalance}`;
        document.getElementById('earnedBalance').innerHTML = `<i class="fas fa-calendar"></i> Earned: ${earnedUsed}/${earnedBalance}`;
        document.getElementById('medicalBalance').innerHTML = `<i class="fas fa-calendar"></i> Medical: ${medicalUsed}/${medicalBalance}`;
        
        // Color code based on usage
        updateBalanceColor('casualBalance', casualUsed, casualBalance);
        updateBalanceColor('earnedBalance', earnedUsed, earnedBalance);
        updateBalanceColor('medicalBalance', medicalUsed, medicalBalance);
    } else {
        infoCard.style.display = 'none';
    }
});

function updateBalanceColor(elementId, used, total) {
    const element = document.getElementById(elementId);
    element.classList.remove('balance-warning', 'balance-critical');
    
    const percentage = (used / total) * 100;
    if (percentage >= 80) {
        element.classList.add('balance-critical');
    } else if (percentage >= 60) {
        element.classList.add('balance-warning');
    }
}

// Auto-calculate total leave days (inclusive)
function calculateLeaveDays() {
    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    const totalDaysInput = document.getElementById('total_days');
    const dateRangeDisplay = document.getElementById('dateRangeDisplay');
    const balanceAfter = document.getElementById('balance_after');
    const leaveType = document.getElementById('leave_type');
    const employeeSelect = document.getElementById('employee_id');

    const startDate = startInput?.value;
    const endDate = endInput?.value;

    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        start.setHours(0, 0, 0, 0);
        end.setHours(0, 0, 0, 0);

        if (end >= start) {
            const diffDays = (end - start) / (1000 * 60 * 60 * 24);
            const totalDays = diffDays + 1;
            totalDaysInput.value = totalDays;
            
            // Format date range display
            const startFormatted = start.toLocaleDateString('en-GB');
            const endFormatted = end.toLocaleDateString('en-GB');
            dateRangeDisplay.innerHTML = `<i class="fas fa-calendar-alt me-2"></i>${startFormatted} to ${endFormatted} (${totalDays} day${totalDays > 1 ? 's' : ''})`;
            
            // Calculate balance after leave
            if (employeeSelect.value && leaveType.value) {
                const selectedOption = employeeSelect.options[employeeSelect.selectedIndex];
                let used = 0;
                let total = 0;
                
                switch(leaveType.value) {
                    case 'Casual':
                        used = parseInt(selectedOption.dataset.casualUsed) || 0;
                        total = parseInt(selectedOption.dataset.casualBalance) || 12;
                        break;
                    case 'Earned':
                        used = parseInt(selectedOption.dataset.earnedUsed) || 0;
                        total = parseInt(selectedOption.dataset.earnedBalance) || 15;
                        break;
                    case 'Medical':
                        used = parseInt(selectedOption.dataset.medicalUsed) || 0;
                        total = parseInt(selectedOption.dataset.medicalBalance) || 10;
                        break;
                }
                
                const remaining = total - used;
                const after = remaining - totalDays;
                balanceAfter.value = after;
                
                // Color code balance after
                if (after < 0) {
                    balanceAfter.style.color = '#dc3545';
                    document.getElementById('submitBtn').disabled = true;
                    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-exclamation-triangle"></i> Insufficient Balance';
                } else if (after <= 2) {
                    balanceAfter.style.color = '#ffc107';
                    document.getElementById('submitBtn').disabled = false;
                    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Submit Leave Application';
                } else {
                    balanceAfter.style.color = '#28a745';
                    document.getElementById('submitBtn').disabled = false;
                    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Submit Leave Application';
                }
            }
        } else {
            totalDaysInput.value = '';
            dateRangeDisplay.innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-2"></i>End date must be after start date';
            balanceAfter.value = '';
        }
    } else {
        totalDaysInput.value = '';
        dateRangeDisplay.innerHTML = '<i class="fas fa-clock me-2"></i>Select dates to calculate leave days';
        balanceAfter.value = '';
    }
}

// Event listeners for date calculation
document.getElementById('start_date')?.addEventListener('change', calculateLeaveDays);
document.getElementById('end_date')?.addEventListener('change', calculateLeaveDays);
document.getElementById('leave_type')?.addEventListener('change', calculateLeaveDays);
document.getElementById('employee_id')?.addEventListener('change', calculateLeaveDays);

// Set min date for end date based on start date
document.getElementById('start_date').addEventListener('change', function() {
    document.getElementById('end_date').min = this.value;
    if (document.getElementById('end_date').value < this.value) {
        document.getElementById('end_date').value = this.value;
        calculateLeaveDays();
    }
});

// Bootstrap validation
(() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            const totalDays = parseInt(document.getElementById('total_days').value) || 0;
            const balanceAfter = parseInt(document.getElementById('balance_after').value) || 0;
            
            if (!form.checkValidity() || balanceAfter < 0) {
                event.preventDefault();
                event.stopPropagation();
                
                if (balanceAfter < 0) {
                    alert('Insufficient leave balance!');
                }
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Auto-fill today's date as default for start date
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    if (!document.getElementById('start_date').value) {
        document.getElementById('start_date').value = today;
    }
});

// Keyboard shortcut (Ctrl+Enter) to submit
document.getElementById('leaveForm').addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        if (!document.getElementById('submitBtn').disabled) {
            this.submit();
        }
    }
});
</script>

<?php include __DIR__ . '/footer.php'; ?>