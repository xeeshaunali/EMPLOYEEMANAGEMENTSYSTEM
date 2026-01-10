<?php
// backend/views/admin_employee_list.php

$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'admin') {
    header('Location: ?page=dashboard');
    exit;
}

include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-building me-2"></i> Employees List - Court Wise
                    </h5>
                    <div>
                        <button type="button" class="btn btn-light btn-sm me-2" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print All
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="exportAllToExcel()">
                            <i class="bi bi-file-earmark-excel"></i> Export All Excel
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        All employees grouped by court. Click on court name to expand/collapse.
                    </p>

                    <?php
                    try {
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
                                p.post_name AS post,
                                COALESCE(c.name, 'Not Assigned') AS court_name
                            FROM employee_details ed
                            LEFT JOIN posts p ON ed.post_id = p.id
                            LEFT JOIN courts c ON ed.court_id = c.id
                            ORDER BY court_name ASC, ed.name ASC
                        ");
                        $allEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Group by court
                        $employeesByCourt = [];
                        foreach ($allEmployees as $emp) {
                            $employeesByCourt[$emp['court_name']][] = $emp;
                        }

                        if (empty($employeesByCourt)) {
                            echo '<div class="alert alert-info text-center">No employees found.</div>';
                        } else {
                            foreach ($employeesByCourt as $courtName => $staff) :
                                $count = count($staff);
                                // Create safe ID for collapse
                                $safeId = 'court_' . preg_replace('/[^a-zA-Z0-9]/', '_', $courtName);
                    ?>
                                <div class="card mb-4 border-primary shadow-sm">
                                    <!-- Court Header - Always Visible -->
                                    <div class="card-header bg-gradient text-white" 
                                         style="background: linear-gradient(90deg, #005566, #007bff); cursor: pointer;"
                                         data-bs-toggle="collapse" 
                                         data-bs-target="#<?= $safeId ?>">
                                        <h5 class="mb-0 d-flex justify-content-between align-items-center">
                                            <strong><?= htmlspecialchars($courtName) ?></strong>
                                            <span class="badge bg-light text-dark fs-6">
                                                <?= $count ?> Employee<?= $count > 1 ? 's' : '' ?>
                                            </span>
                                        </h5>
                                    </div>

                                    <!-- Employee Table -->
                                    <div class="collapse show" id="<?= $safeId ?>">
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <thead class="table-primary">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Court Name</th>
                                                            <th>Name</th>
                                                            <th>Father Name</th>
                                                            <th>Post</th>
                                                            <th>BPS</th>
                                                            <th>CNIC</th>
                                                            <th>DOB</th>
                                                            <th>Appointment</th>
                                                            <th>Retirement</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($staff as $i => $emp): ?>
                                                            <tr>
                                                                <td><?= $i + 1 ?></td>
                                                                <td><strong><?= htmlspecialchars($courtName) ?></strong></td>
                                                                <td><strong><?= htmlspecialchars($emp['name']) ?></strong></td>
                                                                <td><?= htmlspecialchars($emp['father_name'] ?? '—') ?></td>
                                                                <td><?= htmlspecialchars($emp['post'] ?? '—') ?></td>
                                                                <td><?= htmlspecialchars($emp['bps'] ?? '—') ?></td>
                                                                <td><?= htmlspecialchars($emp['cnic'] ?? '—') ?></td>
                                                                <td><?= $emp['date_of_birth'] ? date('d-m-Y', strtotime($emp['date_of_birth'])) : '—' ?></td>
                                                                <td><?= $emp['date_of_appointment'] ? date('d-m-Y', strtotime($emp['date_of_appointment'])) : '—' ?></td>
                                                                <td><?= $emp['date_of_retirement'] ? date('d-m-Y', strtotime($emp['date_of_retirement'])) : '—' ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                    <?php
                            endforeach;
                        }
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden table for Excel export (includes Court column) -->
<table id="hiddenExportTable" style="display:none;">
    <thead>
        <tr>
            <th>Court Name</th>
            <th>Name</th>
            <th>Father Name</th>
            <th>Post</th>
            <th>BPS</th>
            <th>CNIC</th>
            <th>Date of Birth</th>
            <th>Appointment</th>
            <th>Retirement</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($employeesByCourt as $courtName => $staff): ?>
            <?php foreach ($staff as $emp): ?>
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
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script>
function exportAllToExcel() {
    let table = $('#hiddenExportTable').DataTable({
        dom: 'B',
        buttons: [{
            extend: 'excelHtml5',
            title: 'Court Management System - Employees Court Wise - <?= date("d-m-Y") ?>'
        }],
        destroy: true
    });
    table.buttons().trigger();
}

window.addEventListener('beforeprint', () => {
    document.querySelectorAll('.collapse').forEach(el => el.classList.add('show'));
});
</script>

<style>
@media print {
    body * { visibility: hidden; }
    .card, .card * { visibility: visible; }
    .card { position: relative; left: 0; top: 0; box-shadow: none !important; margin-bottom: 30px; break-inside: avoid; }
    .card-header { background: #005566 !important; color: white !important; -webkit-print-color-adjust: exact; }
    .collapse { display: block !important; }
    .table { font-size: 10px; }
    th, td { border: 1px solid #000 !important; padding: 5px !important; }
    .btn { display: none !important; }
}
</style>

<?php include __DIR__ . '/footer.php'; ?>