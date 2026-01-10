<?php
// casual_leave_report.php - With Leave Days Calculation & Total Availed
include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php'; // $pdo
$user = $_SESSION['user'] ?? [];

// --- Input filters ---
$from       = trim($_GET['from'] ?? '');
$to         = trim($_GET['to'] ?? '');
$leaveType  = trim($_GET['leave_type'] ?? '');
$courtId    = trim($_GET['court_id'] ?? '');
$employeeId = trim($_GET['employee_id'] ?? '');

// --- Fetch courts ---
$courts = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM courts ORDER BY name ASC");
    $courts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $courts = [];
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
$selectParts[] = "l.leave_type, l.start_date, l.end_date, l.remarks";

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
}

// --- Calculate total leave days per employee (for "Total Availed" column) ---
$employeeTotalDays = [];
foreach ($leaves as $leave) {
    $empName = $leave['name'] ?? 'Unknown';
    $days = (int)($leave['days'] ?? 0);
    if (!isset($employeeTotalDays[$empName])) {
        $employeeTotalDays[$empName] = 0;
    }
    $employeeTotalDays[$empName] += $days;
}

// Grand total leave days
$grandTotalDays = array_sum($employeeTotalDays);
?>

<div class="container mt-4">
    <h2>Leave Report</h2>

    <form method="get" class="mb-4 row g-3 align-items-end">
        <input type="hidden" name="page" value="casual_leave_report">

        <div class="col-md-3">
            <label class="form-label fw-bold">Court</label>
            <select name="court_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- All Courts --</option>
                <?php foreach ($courts as $court): ?>
                    <option value="<?= $court['id'] ?>" <?= ($courtId == $court['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($court['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold">Employee</label>
            <select name="employee_id" class="form-select">
                <option value="">-- All Employees --</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= ($employeeId == $emp['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emp['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">From Date</label>
            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
        </div>

        <div class="col-md-2">
            <label class="form-label">To Date</label>
            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
        </div>

        <div class="col-md-2">
            <label class="form-label">Leave Type</label>
            <select name="leave_type" class="form-select">
                <option value="">-- All Types --</option>
                <?php
                try {
                    $types = $pdo->query("SELECT name FROM leave_types ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($types as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>" <?= ($leaveType === $type) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type) ?>
                        </option>
                    <?php endforeach;
                } catch (Exception $e) {}
                ?>
            </select>
        </div>

        <div class="col-md-12 d-flex justify-content-between mt-3">
            <button type="submit" class="btn btn-primary btn-lg">Apply Filter</button>
            <button type="button" onclick="printReport()" class="btn btn-outline-secondary btn-lg">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>
    </form>

    <div id="reportArea">
        <table class="table table-bordered table-hover table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Employee</th>
                    <th>Post</th>
                    <th>Court</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Total Leaves Availed</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaves)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">No leave records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leaves as $lv): 
                        $empName = $lv['name'] ?? 'Unknown';
                        $totalAvailed = $employeeTotalDays[$empName] ?? 0;
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($lv['name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($lv['post'] ?? '') ?></td>
                            <td><?= htmlspecialchars($lv['court_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($lv['leave_type'] ?? '') ?></td>
                            <td><?= htmlspecialchars($lv['start_date'] ?? '') ?></td>
                            <td><?= htmlspecialchars($lv['end_date'] ?? '') ?></td>
                            <td class="text-center fw-bold"><?= htmlspecialchars($lv['days'] ?? '0') ?></td>
                            <td class="text-center text-primary fw-bold"><?= $totalAvailed ?></td>
                            <td><?= htmlspecialchars($lv['remarks'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-success fw-bold">
                        <td colspan="6" class="text-end">Grand Total Leave Days:</td>
                        <td class="text-center"><?= $grandTotalDays ?></td>
                        <td colspan="2"></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function printReport() {
    const printContents = document.getElementById('reportArea').innerHTML;
    const originalContents = document.body.innerHTML;

    document.body.innerHTML = `
        <div style="text-align:center; margin:40px 0; font-family: Arial, sans-serif;">
            <h2>Leave Report</h2>
            <p><strong>Generated on:</strong> ${new Date().toLocaleDateString('en-GB')}</p>
            <p><strong>Period:</strong> <?= $from ?: 'Beginning' ?> to <?= $to ?: 'Present' ?></p>
        </div>
        ${printContents}
    `;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}
</script>

<?php include __DIR__ . '/footer.php'; ?>