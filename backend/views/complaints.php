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

/** Allowed category list */
$categoryOptions = [
    'CFMS-DC',
    'Printer Repair',
    'Cartridge Refill',
    'Internet Connectivity',
    'Any Other'
];

/** Reader: create complaint/request */
if ($isReader && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_item'])) {
    $employee_id    = (int)$user['id'];
    $kind           = trim($_POST['kind'] ?? 'request');
    $category       = trim($_POST['category'] ?? '');
    $subject        = trim($_POST['subject'] ?? '');
    $details        = trim($_POST['details'] ?? '');
    $priority       = trim($_POST['priority'] ?? 'Normal');
    $requested_date = trim($_POST['requested_date'] ?? '');

    if (!in_array($category, $categoryOptions, true)) {
        $error = "Please select a valid category.";
    } elseif ($subject === '' || $details === '') {
        $error = "Please provide both Subject and Details.";
    } elseif ($requested_date === '') {
        $error = "Please select the requested date.";
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $requested_date);
        if (!$d || $d->format('Y-m-d') !== $requested_date) {
            $error = "Requested date is not valid.";
        } else {
            $chk = $pdo->prepare("SELECT court_id FROM employee_details WHERE id = ? LIMIT 1");
            $chk->execute([$employee_id]);
            $empRow = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$empRow) {
                $chk2 = $pdo->prepare("SELECT court_id FROM employees WHERE id = ? LIMIT 1");
                $chk2->execute([$employee_id]);
                $empRow = $chk2->fetch(PDO::FETCH_ASSOC);
            }
            if (!$empRow || (int)$empRow['court_id'] !== (int)$user['court_id']) {
                $error = "Your account is not associated with the expected court.";
            } else {
                try {
                    $ins = $pdo->prepare("
                        INSERT INTO complaints
                            (employee_id, kind, category, subject, description, priority, requested_date, status, created_at)
                        VALUES
                            (:employee_id, :kind, :category, :subject, :description, :priority, :requested_date, 'submitted', NOW())
                    ");
                    $ins->execute([
                        ':employee_id'    => $employee_id,
                        ':kind'           => $kind,
                        ':category'       => $category,
                        ':subject'        => $subject,
                        ':description'    => $details,
                        ':priority'       => $priority,
                        ':requested_date' => $requested_date,
                    ]);
                    $success = ucfirst($kind) . " submitted successfully.";
                } catch (PDOException $e) {
                    $error = "Failed to submit: " . $e->getMessage();
                }
            }
        }
    }
}

/** Admin actions */
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

/** Data for reader form */
$courtEmployees = [];
if ($isReader) {
    $courtEmployees = fetchCourtEmployees($pdo, $user['court_id']);
}

/** Admin data */
$courts = $pdo->query("SELECT id, name FROM courts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$allEmployeesStmt = $pdo->query("
    SELECT id, name, court_id FROM employee_details
    UNION
    SELECT id, name, court_id FROM employees
    ORDER BY name
");
$allEmployees = $allEmployeesStmt->fetchAll(PDO::FETCH_ASSOC);

/** List items */
$items = [];
try {
    if ($isAdmin) {
        $isReport = (isset($_GET['report']) && $_GET['report'] === '1');
        if ($isReport) {
            $where = "1=1";
            $params = [];
            if (!empty($_GET['report_from'])) {
                $where .= " AND c.created_at >= :from";
                $params[':from'] = $_GET['report_from'] . ' 00:00:00';
            }
            if (!empty($_GET['report_to'])) {
                $where .= " AND c.created_at <= :to";
                $params[':to'] = $_GET['report_to'] . ' 23:59:59';
            }
            if (!empty($_GET['report_court_id'])) {
                $where .= " AND COALESCE(ed.court_id, e.court_id) = :court_id";
                $params[':court_id'] = (int)$_GET['report_court_id'];
            }
            if (!empty($_GET['report_employee_id'])) {
                $where .= " AND c.employee_id = :employee_id";
                $params[':employee_id'] = (int)$_GET['report_employee_id'];
            }
            if (!empty($_GET['report_status'])) {
                $where .= " AND c.status = :status";
                $params[':status'] = $_GET['report_status'];
            }
            if (!empty($_GET['report_kind'])) {
                $where .= " AND c.kind = :kind";
                $params[':kind'] = $_GET['report_kind'];
            }

            $listSql = "
                SELECT c.*,
                       COALESCE(ed.name, e.name) AS employee_name,
                       ct.name AS court_name,
                       COALESCE(ed.court_id, e.court_id) AS court_id
                FROM complaints c
                LEFT JOIN employee_details ed ON ed.id = c.employee_id
                LEFT JOIN employees e        ON e.id  = c.employee_id
                LEFT JOIN courts ct          ON ct.id = COALESCE(ed.court_id, e.court_id)
                WHERE $where
                ORDER BY c.created_at DESC
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
                LEFT JOIN courts ct          ON ct.id = COALESCE(ed.court_id, e.court_id)
                WHERE c.status IN ('submitted','in_review')
                ORDER BY c.created_at DESC
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
            LEFT JOIN courts ct          ON ct.id = COALESCE(ed.court_id, e.court_id)
            WHERE COALESCE(ed.court_id, e.court_id) = :cid
            ORDER BY c.created_at DESC
        ";
        $listStmt = $pdo->prepare($listSql);
        $listStmt->execute([':cid' => $user['court_id']]);
        $items = $listStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $items = [];
    $error = "Failed to load records.";
}
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

    .report-filters {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 1.5rem;
        border: 1px solid #dee2e6;
        margin-bottom: 1.5rem;
    }
    @media print {
        body { background: white; }
        .no-print, .btn, .report-filters { display: none !important; }
        .table { font-size: 10pt; }
        .badge { background: #eee !important; color: black !important; border: 1px solid #ccc; }
    }
</style>

<div class="container-fluid">
    <div class="page-title text-center">
        <h3 class="mb-0"><?= $isAdmin ? 'Manage Requests & Complaints' : 'Submit Request / Complaint' ?></h3>
        <small class="opacity-75"><?= $isAdmin ? 'Administrator Panel' : 'Court Reader Portal' ?></small>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card complaint-card">
                <div class="card-header card-header-gradient">
                    <h5 class="mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= $isReader ? 'Submit New Request / Complaint' : 'All Requests & Complaints' ?>
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

                    <!-- Reader: Submission Form -->
                    <?php if ($isReader): ?>
                    <div class="border rounded-3 p-4 mb-4" style="background:#f8fdff;">
                        <form method="post" class="row g-3">
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
                                <select name="category" class="form-select" required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categoryOptions as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control" placeholder="Brief title" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Details / Description</label>
                                <textarea name="details" rows="4" class="form-control" placeholder="Describe the issue or request in detail..." required></textarea>
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="bi bi-send me-2"></i> Submit
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Admin: Report Filters -->
                    <?php if ($isAdmin): ?>
                    <div class="report-filters no-print">
                        <h6 class="mb-3"><i class="bi bi-funnel me-2"></i> Filter Report</h6>
                        <form method="get" class="row g-3">
                            <input type="hidden" name="page" value="complaints">
                            <input type="hidden" name="report" value="1">

                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="report_from" class="form-control" value="<?= htmlspecialchars($_GET['report_from'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="report_to" class="form-control" value="<?= htmlspecialchars($_GET['report_to'] ?? '') ?>">
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
                                            <?= htmlspecialchars($ae['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary flex-fill">Apply</button>
                                <a href="?page=complaints" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Records Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Priority</th>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>Req. Date</th>
                                    <th>Status</th>
                                    <th>Completed</th>
                                    <th>Court</th>
                                    <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="<?= $isAdmin ? 11 : 10 ?>" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox display-6 d-block mb-3"></i>
                                            No records found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $row): ?>
                                    <tr>
                                        <td><strong>#<?= $row['id'] ?></strong></td>
                                        <td><?= htmlspecialchars($row['employee_name'] ?? '—') ?></td>
                                        <td><span class="badge bg-info"><?= ucfirst($row['kind'] ?? '') ?></span></td>
                                        <td>
                                            <span class="badge priority-<?= strtolower($row['priority'] ?? 'normal') ?>">
                                                <?= ucfirst($row['priority'] ?? 'Normal') ?>
                                            </span>
                                        </td>
                                        <td><strong><?= htmlspecialchars($row['subject'] ?? '') ?></strong></td>
                                        <td><?= htmlspecialchars($row['category'] ?? '') ?></td>
                                        <td><?= $row['requested_date'] ? date('d M Y', strtotime($row['requested_date'])) : '—' ?></td>
                                        <td>
                                            <span class="badge badge-<?= $row['status'] ?? 'submitted' ?>">
                                                <?= ucfirst(str_replace('_', ' ', $row['status'] ?? 'submitted')) ?>
                                            </span>
                                        </td>
                                        <td><?= $row['completed_date'] ? date('d M Y', strtotime($row['completed_date'])) : '—' ?></td>
                                        <td><?= htmlspecialchars($row['court_name'] ?? '—') ?></td>
                                        <?php if ($isAdmin): ?>
                                        <td>
                                            <form method="post" class="d-flex flex-column gap-2">
                                                <input type="hidden" name="admin_action" value="1">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <div class="input-group input-group-sm">
                                                    <select name="status" class="form-select">
                                                        <?php foreach (['submitted','in_review','approved','rejected','completed'] as $s): ?>
                                                            <option value="<?= $s ?>" <?= ($row['status'] ?? '') === $s ? 'selected' : '' ?>>
                                                                <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-success btn-sm">Update</button>
                                                </div>
                                                <input type="date" name="completed_date" class="form-control form-control-sm" value="<?= htmlspecialchars($row['completed_date'] ?? '') ?>" placeholder="Completed date">
                                                <input type="text" name="resolution_notes" class="form-control form-control-sm" value="<?= htmlspecialchars($row['resolution_notes'] ?? '') ?>" placeholder="Resolution notes">
                                            </form>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-center no-print">
                        <a href="?page=dashboard" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
                        </a>
                        <?php if ($isAdmin): ?>
                        <button onclick="printReport()" class="btn btn-outline-primary ms-3">
                            <i class="bi bi-printer me-2"></i> Print Report
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printReport() {
    window.print();
}
</script>

<?php include __DIR__ . '/footer.php'; ?>