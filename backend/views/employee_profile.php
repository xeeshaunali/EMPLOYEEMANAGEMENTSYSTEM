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

/* ================= CASUAL LEAVES AVAILED ================= */
$leavesStmt = $pdo->prepare("
    SELECT l.leave_type, l.start_date, l.end_date, l.remarks, l.status,
           DATEDIFF(l.end_date, l.start_date) + 1 AS days
    FROM leaves l
    WHERE l.employee_detail_id = ?
      AND l.leave_type = 'Casual'
    ORDER BY l.start_date DESC
");
$leavesStmt->execute([$id]);
$casualLeaves = $leavesStmt->fetchAll(PDO::FETCH_ASSOC);

$totalCasualDays = 0;
foreach ($casualLeaves as $leave) {
    $totalCasualDays += (int)$leave['days'];
}
?>

<style>
/* Existing styles unchanged... */
.profile-header {
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: #fff;
    padding: 1.5rem;
    border-radius: 12px 12px 0 0;
}
.print-only { display: none; }

@media print {
    @page { size: A4 portrait; margin: 10mm; }
    html, body { margin: 0 !important; padding: 0 !important; font-family: Arial, sans-serif; font-size: 11pt; background: #fff !important; color: #000 !important; }
    header, footer, nav, .navbar, .breadcrumb, .btn, .modal, .no-print, .d-print-none { display: none !important; }
    .container, .container-fluid, #printSection { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    .print-only { display: block !important; }
    .card, .shadow-sm { box-shadow: none !important; border: none !important; }
    .print-header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #000; padding-bottom: 12px; }
    .print-header h3 { font-size: 18pt; margin: 0; font-weight: bold; }
    .print-header p { font-size: 12pt; margin: 6px 0 0; }
    .print-profile { display: flex; align-items: flex-start; gap: 30px; margin-bottom: 30px; }
    .print-photo-circle { width: 150px; height: 150px; border-radius: 50%; overflow: hidden; border: 3px solid #000; flex-shrink: 0; }
    .print-photo-circle img { width: 100%; height: 100%; object-fit: cover; }
    .print-details-inline { flex: 1; display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px 20px; }
    .print-label { font-weight: bold; min-width: 130px; display: inline-block; }
    .section-title { font-size: 14pt; font-weight: bold; border-bottom: 1px solid #000; margin: 25px 0 12px; padding-bottom: 4px; }
    table { width: 100%; border-collapse: collapse !important; margin-top: 8px; }
    table th, table td { border: 1px solid #000 !important; padding: 6px !important; font-size: 11pt; text-align: center; vertical-align: middle; }
    table th { background: #f0f0f0 !important; font-weight: bold; }
}
</style>

<div class="container-fluid px-4" id="printSection">
    <!-- Screen Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 profile-header d-print-none">
        <h4 class="fw-bold mb-0">Employee Profile</h4>
        <div class="btn-group">
            <a href="?page=employee_search" class="btn btn-outline-light btn-sm">Back</a>
            <a href="?page=employee_details&edit=<?= (int)$emp['id'] ?>" class="btn btn-outline-light btn-sm">Edit</a>
            <a href="?page=transfer_posting&emp_id=<?= (int)$emp['id'] ?>" class="btn btn-outline-light btn-sm">Transfer / Posting</a>
            <button onclick="window.print()" class="btn btn-light btn-sm">Print</button>
        </div>
    </div>

    <!-- Print Only Header -->
    <div class="print-only print-header">
        <h3>EMPLOYEE PROFILE</h3>
        <p>District & Subordinate Courts, Jamshoro</p>
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
        </div>
    </div>

    <!-- Screen View -->
    <div class="card shadow-sm mb-4 d-print-none">
        <div class="card-body">
            <div class="row g-4 align-items-start">
                <div class="col-md-3 text-center">
                    <div class="border rounded p-3 bg-light">
                        <?php if ($picUrl): ?>
                            <img src="<?= htmlspecialchars($picUrl) ?>"
                                 class="img-fluid rounded"
                                 style="max-height:260px; cursor:pointer"
                                 onclick="showPic('<?= htmlspecialchars($picUrl) ?>')"
                                 data-bs-toggle="modal" data-bs-target="#picModal">
                        <?php else: ?>
                            <div class="text-muted py-5">No Photo</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="row g-3">
                        <?php
                        function field($label, $value) {
                            echo "<div class='col-md-4'>
                                    <div class='small text-muted'>$label</div>
                                    <div class='fw-semibold'>" . htmlspecialchars($value ?: '-') . "</div>
                                  </div>";
                        }
                        field('Name', $emp['name']);
                        field("Father's Name", $emp['father_name']);
                        field('CNIC', $emp['cnic']);
                        field('Post', $emp['post_name']);
                        field('Court', $emp['court_name']);
                        field('BPS', $emp['bps']);
                        field('Date of Birth', $emp['date_of_birth'] ? date('d-m-Y', strtotime($emp['date_of_birth'])) : '-');
                        field('Appointment Date', date('d-m-Y', strtotime($emp['date_of_appointment'])));
                        field('Retirement Date', date('d-m-Y', strtotime($emp['date_of_retirement'])));
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transfer History -->
    <?php if ($history): ?>
        <h5 class="section-title d-print-none">Transfer / Posting History</h5>
        <div class="section-title print-only">Transfer / Posting History</div>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
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
                        <td><?= htmlspecialchars($h['type']) ?></td>
                        <td><?= htmlspecialchars($h['old_court'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($h['new_court'] ?? '—') ?></td>
                        <td><?= date('d-m-Y', strtotime($h['transfer_date'])) ?></td>
                        <td><?= htmlspecialchars($h['remarks'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Casual Leaves Availed -->
    <h5 class="section-title mt-4 d-print-none">Casual Leaves Availed</h5>
    <div class="section-title print-only mt-4">Casual Leaves Availed</div>
    <div class="table-responsive">
        <?php if (empty($casualLeaves)): ?>
            <p class="text-muted p-3">No casual leave records found.</p>
        <?php else: ?>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>S.No</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days</th>
                        <!-- <th>Status</th> -->
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($casualLeaves as $i => $leave): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= date('d-m-Y', strtotime($leave['start_date'])) ?></td>
                        <td><?= date('d-m-Y', strtotime($leave['end_date'])) ?></td>
                        <td class="text-center fw-bold"><?= htmlspecialchars($leave['days']) ?></td>
                        <!-- <td>
                            <span class="badge <?= $leave['status'] === 'approved' ? 'bg-success' : 'bg-warning' ?>">
                                <?= ucfirst(htmlspecialchars($leave['status'])) ?>
                            </span>
                        </td> -->
                        <td><?= htmlspecialchars($leave['remarks'] ?: '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-primary fw-bold">
                        <td colspan="3" class="text-end">Total Casual Leave Days Availed:</td>
                        <td class="text-center"><?= $totalCasualDays ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Files Section -->
    <h5 class="section-title mt-4 d-print-none">Employee Record Files</h5>
    <div class="section-title print-only mt-4">Employee Record Files</div>
    <div class="table-responsive">
        <?php if (!$files): ?>
            <p class="text-muted p-3">No files available.</p>
        <?php else: ?>
            <table class="table table-bordered">
                <thead>
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
                        <td><?= htmlspecialchars($file['file_name']) ?></td>
                        <td><?= htmlspecialchars($file['category'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($file['uploaded_by'] ?? '—') ?></td>
                        <td><?= date('d-m-Y', strtotime($file['created_at'])) ?></td>
                        <td class="d-print-none">
                            <a href="?page=download&id=<?= $file['id'] ?>" class="btn btn-sm btn-outline-primary">Download</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Photo Modal -->
<div class="modal fade" id="picModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <img id="picModalImg" class="img-fluid rounded shadow" src="">
            </div>
        </div>
    </div>
</div>

<script>
function showPic(src) {
    document.getElementById('picModalImg').src = src;
}
</script>

<?php include __DIR__ . '/footer.php'; ?>