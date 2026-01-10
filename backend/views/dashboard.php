<?php
include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php'; // ensure $pdo is available

// current user
$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: ?page=login');
    exit;
}

$isAdmin     = ($user['role'] ?? '') === 'admin';
$isReader    = ($user['role'] ?? '') === 'reader';
$isLibrarian = ($user['role'] ?? '') === 'librarian';
$isLibAdmin = $isAdmin || $isLibrarian;

// Fetch statistics
try {
    if ($isAdmin) {
        $totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employee_details")->fetchColumn();
        $pendingLeaves  = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employee_details WHERE court_id = ?");
        $stmt->execute([$user['court_id']]);
        $totalEmployees = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            WHERE l.status = 'pending' AND e.court_id = ?
        ");
        $stmt->execute([$user['court_id']]);
        $pendingLeaves = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) {
    if ($isAdmin) {
        try { $totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn(); } catch (Exception $_) { $totalEmployees = 0; }
        try { $pendingLeaves = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")->fetchColumn(); } catch (Exception $_) { $pendingLeaves = 0; }
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE court_id = ?");
            $stmt->execute([$user['court_id']]);
            $totalEmployees = (int)$stmt->fetchColumn();
        } catch (Exception $_) { $totalEmployees = 0; }
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM leaves l
                JOIN employees e ON l.employee_id = e.id
                WHERE l.status = 'pending' AND e.court_id = ?
            ");
            $stmt->execute([$user['court_id']]);
            $pendingLeaves = (int)$stmt->fetchColumn();
        } catch (Exception $_) { $pendingLeaves = 0; }
    }
}

// libraryResources (safe)
$libraryResources = 0;
try {
    if ($isLibAdmin) {
        $libraryResources = (int)$pdo->query("SELECT COUNT(*) FROM library_books")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM library_books WHERE court_id = ?");
        $ok = $stmt->execute([$user['court_id'] ?? 0]);
        if ($ok) {
            $libraryResources = (int)$stmt->fetchColumn();
        } else {
            $libraryResources = (int)$pdo->query("SELECT COUNT(*) FROM library_books")->fetchColumn();
        }
    }
} catch (Exception $e) {
    $libraryResources = 0;
}

// Files
try {
    if ($isAdmin) {
        $myFiles = (int)$pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM files WHERE owner_id = ?");
        $stmt->execute([$user['id']]);
        $myFiles = (int)$stmt->fetchColumn();
    }
} catch (Exception $e) {
    $myFiles = 0;
}

$myTasks = 0;

// Get court name
$courtName = 'N/A';
if ($isAdmin) {
    $courtName = 'All Courts';
} elseif (!empty($user['court_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM courts WHERE id = ? LIMIT 1");
        $stmt->execute([$user['court_id']]);
        $name = $stmt->fetchColumn();
        if ($name) $courtName = $name;
    } catch (Exception $e) {
        $courtName = 'N/A';
    }
}

// ---------------- Reader Leave Logic ----------------
$success = $error = null;
$edit_id = $_GET['edit'] ?? null;

if ($isReader && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_leave'])) {
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        $leave_type  = trim($_POST['leave_type'] ?? 'Casual');
        $start_date  = trim($_POST['start_date'] ?? '');
        $end_date    = trim($_POST['end_date'] ?? '');
        $remarks     = trim($_POST['remarks'] ?? '');

        if ($employee_id <= 0 || $start_date === '' || $end_date === '' || $leave_type === '') {
            $error = "Please select employee, leave type, and both dates.";
        } elseif (strtotime($end_date) < strtotime($start_date)) {
            $error = "End date cannot be before start date.";
        } else {
            // Verify that the selected employee_id exists in the `employees` table
            $chk = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND court_id = ? LIMIT 1");
            $chk->execute([$employee_id, $user['court_id']]);
            $empRow = $chk->fetch();

            if (!$empRow) {
                $error = "Invalid or unauthorized employee selected.";
            } else {
                if ($edit_id) {
                    $upd = $pdo->prepare("
                        UPDATE leaves
                        SET employee_id = ?, leave_type = ?, start_date = ?, end_date = ?, remarks = ?
                        WHERE id = ?
                    ");
                    $upd->execute([$employee_id, $leave_type, $start_date, $end_date, $remarks, $edit_id]);
                    $success = "Leave updated successfully.";
                } else {
                    $status = ($leave_type === 'Earned') ? 'pending' : 'approved';
                    $ins = $pdo->prepare("
                        INSERT INTO leaves (employee_id, leave_type, start_date, end_date, status, remarks)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$employee_id, $leave_type, $start_date, $end_date, $status, $remarks]);
                    $success = ucfirst($leave_type) . " leave " . ($status === 'pending' ? "submitted for approval." : "added.");
                }
            }
        }
    }
}

// Fetch employees for reader form — ONLY from `employees` table (required for FK)
$courtEmployees = [];
$recentLeaves = [];
$editLeave = null;

if ($isReader) {
    try {
        $empStmt = $pdo->prepare("SELECT id, name FROM employees WHERE court_id = ? ORDER BY name ASC");
        $empStmt->execute([$user['court_id']]);
        $courtEmployees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

        $listStmt = $pdo->prepare("
            SELECT
                l.id,
                e.name AS employee_name,
                l.leave_type,
                l.start_date,
                l.end_date,
                l.status,
                l.remarks
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            WHERE e.court_id = ?
            ORDER BY l.id DESC
            LIMIT 10
        ");
        $listStmt->execute([$user['court_id']]);
        $recentLeaves = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($edit_id) {
            $stmt = $pdo->prepare("SELECT * FROM leaves WHERE id = ? LIMIT 1");
            $stmt->execute([$edit_id]);
            $editLeave = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $courtEmployees = [];
        $recentLeaves = [];
        $editLeave = null;
    }
}

// ---------------- Admin Approval Logic ----------------
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_leave'])) {
        $lid = (int)($_POST['leave_id'] ?? 0);
        $start = trim($_POST['start_date'] ?? '');
        $end   = trim($_POST['end_date'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');
        if ($lid > 0 && $start !== '' && $end !== '' && strtotime($end) >= strtotime($start)) {
            $upd = $pdo->prepare("UPDATE leaves SET start_date = ?, end_date = ?, status = 'approved', remarks = ? WHERE id = ?");
            $upd->execute([$start, $end, $remarks, $lid]);
            $success = "Leave approved successfully.";
        } else {
            $error = "Please provide valid From/To dates.";
        }
    } elseif (isset($_POST['reject_leave'])) {
        $lid = (int)($_POST['leave_id'] ?? 0);
        if ($lid > 0) {
            $upd = $pdo->prepare("UPDATE leaves SET status = 'rejected' WHERE id = ?");
            $upd->execute([$lid]);
            $success = "Leave rejected.";
        }
    }
}

// Fetch pending leaves for admin view
$pendingList = [];
if ($isAdmin) {
    try {
        $stmt = $pdo->query("
            SELECT l.id, e.name AS employee_name, l.leave_type, l.start_date, l.end_date, l.remarks
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            WHERE l.status = 'pending'
            ORDER BY l.start_date ASC
        ");
        $pendingList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $pendingList = [];
    }
}

// Casual Leave Report Count
$casualCount = 0;
try {
    $role = $user['role'] ?? 'employee';
    $params = [];
    $where = "l.leave_type='Casual' AND l.status='approved'";
    if ($role === 'employee') {
        $where .= " AND l.employee_id = :eid";
        $params[':eid'] = $user['id'];
    } elseif ($role === 'reader') {
        $where .= " AND e.court_id = :cid";
        $params[':cid'] = $user['court_id'];
    }
    $sql = "SELECT COUNT(*) FROM leaves l JOIN employees e ON l.employee_id = e.id WHERE $where";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $casualCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $casualCount = 0;
}
?>

<style>
    /* Print styles */
    @media print {
        .card, .alert, .form-control, .btn {
            border: none !important;
            box-shadow: none !important;
        }
        .card-header {
            background: none !important;
            color: #000 !important;
        }
    }
    /* Stat Cards */
    .stat-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border-radius: 8px;
        background: #fff;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15) !important;
    }
    .stat-card .card-header {
        background: linear-gradient(90deg, #005566, #007bff);
        color: #fff;
    }
</style>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Dashboard - <?php echo htmlspecialchars($courtName); ?></h5>
                </div>
                <div class="card-body">
                    <p id="currentDateTime" class="text-muted"></p>
                </div>
            </div>
        </div>
    </div>

<!-- MODULE NOT IN USE START -->

    <!-- <?php if ($isReader): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-info text-dark">
                    <h6 class="mb-0"><i class="bi bi-calendar-event me-1"></i> Manage Court Leaves</h6>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="save_leave" value="1">
                        <div class="col-md-4">
                            <label class="form-label">Employee</label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($courtEmployees as $emp): ?>
                                    <option value="<?= (int)$emp['id'] ?>" <?= ($editLeave && $editLeave['employee_id'] == $emp['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Leave Type</label>
                            <select name="leave_type" class="form-select" required>
                                <option value="Casual" <?= ($editLeave && $editLeave['leave_type'] === 'Casual') ? 'selected' : '' ?>>Casual</option>
                                <option value="Earned" <?= ($editLeave && $editLeave['leave_type'] === 'Earned') ? 'selected' : '' ?>>Earned</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From</label>
                            <input type="date" name="start_date" class="form-control" value="<?= $editLeave ? htmlspecialchars($editLeave['start_date']) : '' ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To</label>
                            <input type="date" name="end_date" class="form-control" value="<?= $editLeave ? htmlspecialchars($editLeave['end_date']) : '' ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"><?= $editLeave ? htmlspecialchars($editLeave['remarks']) : '' ?></textarea>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Save Leave</button>
                            <?php if ($edit_id): ?>
                                <a href="?page=dashboard" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <h6 class="fw-bold mt-4">Recent Leaves</h6>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentLeaves)): ?>
                                <tr><td colspan="7" class="text-center">No leaves found</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentLeaves as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                        <td><?= htmlspecialchars($row['leave_type']) ?></td>
                                        <td><?= htmlspecialchars($row['start_date']) ?></td>
                                        <td><?= htmlspecialchars($row['end_date']) ?></td>
                                        <td><?= htmlspecialchars(ucfirst($row['status'])) ?></td>
                                        <td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
                                        <td>
                                            <a href="?page=dashboard&edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?> -->

    <!-- MODULE NOT IN USE END -->


    <!-- Stat Cards -->
    <div class="row g-4 mb-1">
        <div class="col-md-3 col-sm-12">
            <div class="card stat-card shadow-sm border-0 text-center">
                <div class="card-header">
                    <h6 class="card-title">Total Employees</h6>
                </div>
                <div class="card-body">
                    <div class="text-primary"><i class="bi bi-people"></i></div>
                    <h4 class="fw-bold"><?= htmlspecialchars($totalEmployees) ?></h4>
                </div>
            </div>
        </div>

        

        <?php if ($isAdmin): ?>
        <div class="col-md-3 col-sm-6">
            <a href="?page=staff_strength_report" class="text-decoration-none">
                <div class="card stat-card shadow-sm border-0 text-center h-100">
                    <div class="card-header">
                        <h6 class="card-title">Staff Strength Report</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-info"><i class="bi bi-bar-chart-line"></i></div>
                        <h6>Staff Strength Report</h6>
                        <!-- <h4 class="fw-bold">View</h4> -->
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <div class="col-md-3 col-sm-6">
            <a href="?page=casual_leave_report" class="text-decoration-none">
                <div class="card stat-card shadow-sm border-0 text-center h-100">
                    <div class="card-header">
                        <h6 class="card-title">Casual Leave Report</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-primary"><i class="bi bi-calendar-check"></i></div>
                        <h6>Casual Leave Report</h6>
                        <p class="text-muted mb-1">View / Generate</p>
                        <!-- <h4 class="fw-bold"><?= htmlspecialchars($casualCount) ?></h4> -->
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-3 col-sm-6">
            <a href="?page=add_leave" class="text-decoration-none">
                <div class="card stat-card shadow-sm border-0 text-center h-100">
                    <div class="card-header">
                        <h6 class="card-title">Add Casual Leave</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-success"><i class="bi bi-calendar-plus"></i></div>
                        <h6>Add Leave</h6>
                        <p class="text-muted mb-1">Add leaves for staff</p>
                        <!-- <h4 class="fw-bold">Open</h4> -->
                    </div>
                </div>
            </a>
        </div>

        <?php if ($isAdmin): ?>
        <div class="col-md-3 col-sm-6">
            <a href="?page=employee_list" class="text-decoration-none">
                <div class="card stat-card shadow-sm border-0 text-center h-100">
                    <div class="card-header">
                        <h6 class="card-title">All Employees List</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-info"><i class="bi bi-list-ul"></i></div>
                        <h6>Employee Directory</h6>
                        <p class="text-muted mb-1">View, print & export full list</p>
                        <!-- <h4 class="fw-bold">Open</h4> -->
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="col-md-3 col-sm-6">
            <a href="?page=employee_search" class="text-decoration-none">
                <div class="card stat-card shadow-sm border-0 text-center h-100">
                    <div class="card-header">
                        <h6 class="card-title">Employee Search</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-secondary"><i class="bi bi-search"></i></div>
                        <h6>Employee Search</h6>
                        <p class="text-muted mb-1">Find employees by name, CNIC, post, or court</p>
                        <!-- <h4 class="fw-bold">Open</h4> -->
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3 col-sm-6">
            <a href="?page=transfer_posting" class="text-decoration-none">
                <div class="card stat-card shadow-sm border-0 text-center h-100">
                    <div class="card-header">
                        <h6 class="card-title">Transfer & Posting</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-success"><i class="bi bi-arrow-left-right"></i></div>
                        <h6>Transfer & Posting</h6>
                        <p class="text-muted mb-1">Manage employee transfers</p>
                        <!-- <h4 class="fw-bold">Open</h4> -->
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <div class="col-md-3 col-sm-6">
            <a href="?page=complaints" class="text-decoration-none">
                <div class="card stat-card shadow-sm border-0 text-center h-100">
                    <div class="card-header">
                        <h6 class="card-title">Requests & Complaints</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-success"><i class="bi bi-exclamation-circle"></i></div>
                        <h6>Requests & Complaints</h6>
                        <p class="text-muted mb-1">Manage Requests and Complaints</p>
                        <!-- <h4 class="fw-bold">Open</h4> -->
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Admin Pending Leave Requests -->

                <!-- MODULE NOT IN USE WILL BE USED WHEN REQUIRED START -->

    <!-- <?php if ($isAdmin): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="bi bi-hourglass-split me-1"></i> Pending Leave Requests</h6>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Remarks</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingList)): ?>
                                <tr><td colspan="6" class="text-center">No pending leaves</td></tr>
                            <?php else: ?>
                                <?php foreach ($pendingList as $row): ?>
                                    <tr>
                                        <form method="post">
                                            <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                            <td><?= htmlspecialchars($row['leave_type']) ?></td>
                                            <td><input type="date" name="start_date" value="<?= htmlspecialchars($row['start_date']) ?>" class="form-control form-control-sm" required></td>
                                            <td><input type="date" name="end_date" value="<?= htmlspecialchars($row['end_date']) ?>" class="form-control form-control-sm" required></td>
                                            <td><input type="text" name="remarks" value="<?= htmlspecialchars($row['remarks']) ?>" class="form-control form-control-sm"></td>
                                            <td>
                                                <input type="hidden" name="leave_id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="approve_leave" class="btn btn-sm btn-success me-1">Approve</button>
                                                <button type="submit" name="reject_leave" class="btn btn-sm btn-danger">Reject</button>
                                            </td>
                                        </form>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?> -->

    <!-- MODULE NOT USED END -->

    <script>
        // Update date/time dynamically
        function updateDateTime() {
            document.getElementById("currentDateTime").textContent = new Date().toLocaleString('en-US', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: true
            });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000); // Update every second
    </script>

<?php include __DIR__ . '/footer.php'; ?>