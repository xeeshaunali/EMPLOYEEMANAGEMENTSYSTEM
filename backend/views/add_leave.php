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
        default: $error = "An error occurred. Please check your input.";
    }
}

/* =========================
   FETCH LEAVE TYPES
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
            COALESCE(c.name, 'N/A') AS court_name
        FROM employee_details ed
        LEFT JOIN posts p ON p.id = ed.post_id
        LEFT JOIN courts c ON c.id = ed.court_id
    ";

    $params = [];

    if ($selectedCourtId !== '') {
        $sql .= " WHERE ed.court_id = :court_id";
        $params[':court_id'] = $selectedCourtId;
    }
    // If reader and no court selected (shouldn't happen), force it
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
?>

<div class="container mt-4">
    <h2>Add Staff Leave</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Court Selection (Only for Admin) -->
    <?php if ($isAdmin): ?>
    <form method="GET" id="courtForm" class="mb-4">
        <input type="hidden" name="page" value="add_leave">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-bold">Court</label>
                <select name="court_id" id="court_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- All Courts --</option>
                    <?php foreach ($courts as $court): ?>
                        <option value="<?= $court['id'] ?>" <?= ($selectedCourtId == $court['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($court['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-7 d-flex align-items-end">
                <small class="text-muted">Select a court to filter employees, or "All Courts" to see everyone.</small>
            </div>
        </div>
    </form>
    <?php else: ?>
    <!-- Reader: Show fixed court -->
    <div class="alert alert-info mb-4">
        <strong>Court:</strong> <?= htmlspecialchars($readerCourtName) ?> 
        <small>(You can only add leave for employees in your court)</small>
    </div>
    <?php endif; ?>

    <!-- Main Leave Form -->
    <form method="POST" action="?page=save_staff_leave" class="row g-3 needs-validation" novalidate>
        <div class="col-md-6">
            <label class="form-label fw-bold">Employee Name</label>
            <select name="employee_id" class="form-select" required>
                <option value="">-- Select Employee --</option>
                <?php if (empty($staff)): ?>
                    <option value="" disabled>No employees found</option>
                <?php else: ?>
                    <?php foreach ($staff as $emp): ?>
                        <option value="<?= (int)$emp['emp_id'] ?>">
                            <?= htmlspecialchars($emp['name']) ?>
                            (<?= htmlspecialchars($emp['post_name']) ?>)
                            - BPS <?= htmlspecialchars($emp['bps'] ?? 'N/A') ?>
                            - <?= htmlspecialchars($emp['court_name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <div class="invalid-feedback">Please select an employee.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold">Type of Leave</label>
            <select name="leave_type" class="form-select" required>
                <option value="">-- Select Type --</option>
                <?php foreach ($leave_types as $type): ?>
                    <option value="<?= htmlspecialchars($type) ?>">
                        <?= htmlspecialchars($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Please select leave type.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold">From Date</label>
            <input type="date" name="start_date" id="start_date" class="form-control" required>
            <div class="invalid-feedback">Required.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold">To Date</label>
            <input type="date" name="end_date" id="end_date" class="form-control" required>
            <div class="invalid-feedback">Required.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold">Total Days</label>
            <input type="number" id="total_days" class="form-control" readonly placeholder="Auto-calculated">
        </div>

        <div class="col-12">
            <label class="form-label fw-bold">Remarks (Optional)</label>
            <textarea name="remarks" class="form-control" rows="3"></textarea>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary btn-lg">
                Submit Leave
            </button>
            <a href="?page=dashboard" class="btn btn-secondary btn-lg ms-2">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
// Auto-calculate total leave days (inclusive)
function calculateLeaveDays() {
    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    const totalDaysInput = document.getElementById('total_days');

    const startDate = startInput?.value;
    const endDate = endInput?.value;

    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        start.setHours(0, 0, 0, 0);
        end.setHours(0, 0, 0, 0);

        if (end >= start) {
            const diffDays = (end - start) / (1000 * 60 * 60 * 24);
            totalDaysInput.value = diffDays + 1;
        } else {
            totalDaysInput.value = '';
        }
    } else {
        totalDaysInput.value = '';
    }
}

document.getElementById('start_date')?.addEventListener('change', calculateLeaveDays);
document.getElementById('end_date')?.addEventListener('change', calculateLeaveDays);
document.getElementById('start_date')?.addEventListener('input', calculateLeaveDays);
document.getElementById('end_date')?.addEventListener('input', calculateLeaveDays);

// Bootstrap validation
(() => {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>