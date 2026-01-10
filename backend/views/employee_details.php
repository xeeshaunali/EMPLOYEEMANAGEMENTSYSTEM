<?php
include __DIR__ . '/header.php';

// Resolve upload locations
$uploadsDir = __DIR__ . '/../../Uploads/employees/';
$uploadsUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/../Uploads/employees/';

// Fetch posts for dropdown
$posts = $pdo->query("SELECT id, post_name, bps FROM posts ORDER BY post_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch courts for dropdown
$courts = $pdo->query("SELECT id, name FROM courts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle Save (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_employee_detail'])) {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name']);
    $father_name = trim($_POST['father_name']);
    $post_id = (int) $_POST['post_id'];
    $court_id = (int) $_POST['court_id'];
    $bps = $_POST['bps'];
    $dob = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $doa = $_POST['date_of_appointment'];
    $dor = $_POST['date_of_retirement'];
    $cnic = !empty($_POST['cnic']) ? $_POST['cnic'] : null;

    if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0777, true); }

    // Fetch old pic if editing
    $oldPic = null;
    if ($id) {
        $stmt = $pdo->prepare("SELECT pic FROM employee_details WHERE id=?");
        $stmt->execute([$id]);
        $oldPic = $stmt->fetchColumn();
    }

    // Handle "remove pic"
    if (!empty($_POST['remove_pic']) && $oldPic) {
        $oldPath = $uploadsDir . $oldPic;
        if (is_file($oldPath)) { @unlink($oldPath); }
        $oldPic = null;
    }

    // Handle file upload
    $newPic = $oldPic;
    if (!empty($_FILES['pic']['name']) && is_uploaded_file($_FILES['pic']['tmp_name'])) {
        if ($oldPic) {
            $oldPath = $uploadsDir . $oldPic;
            if (is_file($oldPath)) { @unlink($oldPath); }
        }
        $safeName = preg_replace('/[^A-Za-z0-9_\.-]/', '_', basename($_FILES['pic']['name']));
        $newPic = time() . '_' . $safeName;
        move_uploaded_file($_FILES['pic']['tmp_name'], $uploadsDir . $newPic);
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE employee_details
            SET name=?, father_name=?, post_id=?, court_id=?, bps=?, date_of_birth=?,
                date_of_appointment=?, date_of_retirement=?, cnic=?, pic=?
            WHERE id=?");
        $stmt->execute([$name, $father_name, $post_id, $court_id, $bps, $dob, $doa, $dor, $cnic, $newPic, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO employee_details
            (name, father_name, post_id, court_id, bps, date_of_birth, date_of_appointment, date_of_retirement, cnic, pic)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $father_name, $post_id, $court_id, $bps, $dob, $doa, $dor, $cnic, $newPic]);
    }
    echo "<script>window.location.href='?page=employee_details';</script>";
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("SELECT pic FROM employee_details WHERE id=?");
    $stmt->execute([$id]);
    $oldPic = $stmt->fetchColumn();
    if ($oldPic) {
        $oldPath = $uploadsDir . $oldPic;
        if (is_file($oldPath)) { @unlink($oldPath); }
    }
    $stmt = $pdo->prepare("DELETE FROM employee_details WHERE id=?");
    $stmt->execute([$id]);
    echo "<script>window.location.href='?page=employee_details';</script>";
    exit;
}

// Fetch all employees
$stmt = $pdo->query("
    SELECT ed.id,
           ed.name,
           ed.father_name,
           ed.bps,
           ed.date_of_birth,
           ed.date_of_appointment,
           ed.date_of_retirement,
           ed.cnic,
           ed.pic,
           p.post_name AS post_name,
           c.name AS court_name
    FROM employee_details ed
    JOIN posts p ON p.id = ed.post_id
    JOIN courts c ON c.id = ed.court_id
    ORDER BY ed.id DESC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If editing
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM employee_details WHERE id=?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<style>
    .page-title {
        background: linear-gradient(135deg, #005566, #007bff);
        color: white;
        padding: 2rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        text-align: center;
        box-shadow: 0 6px 15px rgba(0,0,0,0.1);
    }
    .page-title h3 {
        margin: 0;
        font-weight: 600;
        font-size: 1.8rem;
    }
    .page-title small {
        opacity: 0.9;
        font-size: 1rem;
    }

    .form-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .form-header {
        background: linear-gradient(90deg, #005566, #007bff);
        color: white;
        padding: 1.2rem 1.5rem;
        font-size: 1.3rem;
        font-weight: 600;
    }
    .form-body {
        padding: 2rem;
    }
    .form-label {
        font-weight: 600;
        color: #333;
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
    }
    .form-control, .form-select {
        border-radius: 8px;
        padding: 0.7rem 1rem;
        font-size: 0.95rem;
        border: 1px solid #ced4da;
    }
    .form-control:focus, .form-select:focus {
        border-color: #005566;
        box-shadow: 0 0 0 0.25rem rgba(0, 85, 102, 0.2);
    }
    .photo-preview {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 10px;
        border: 3px solid #e9ecef;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .btn-primary {
        background: linear-gradient(90deg, #005566, #007bff);
        border: none;
        border-radius: 8px;
        padding: 0.7rem 1.8rem;
        font-weight: 500;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0,123,255,0.3);
    }

    .table-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .table-header {
        background: linear-gradient(90deg, #005566, #007bff);
        color: white;
        padding: 1.2rem 1.5rem;
        font-size: 1.3rem;
        font-weight: 600;
    }
    .table {
        margin: 0;
    }
    .table thead {
        background: #f1f3f5;
        font-weight: 600;
    }
    .table tbody tr:hover {
        background-color: #f8fdff;
        transition: 0.2s;
    }
    .table img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid #e9ecef;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .table img:hover {
        transform: scale(1.1);
    }
    .action-btns .btn {
        font-size: 0.85rem;
        padding: 0.4rem 0.8rem;
    }

    @media (max-width: 768px) {
        .form-body {
            padding: 1.5rem;
        }
        .photo-preview {
            width: 100px;
            height: 100px;
        }
        .table-responsive {
            font-size: 0.9rem;
        }
    }
</style>

<div class="container-fluid mt-4">
    <div class="page-title">
        <h3>Employee Details Management</h3>
        <small>Add, Edit, and Manage Court Staff Records</small>
    </div>

    <!-- Add/Edit Form -->
    <div class="form-card">
        <div class="form-header">
            <?= $edit ? 'Edit Employee' : 'Add New Employee' ?>
        </div>
        <div class="form-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id'] ?? '') ?>">

                <div class="row g-4">
                    <div class="col-lg-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">Father's Name</label>
                        <input type="text" name="father_name" class="form-control" required value="<?= htmlspecialchars($edit['father_name'] ?? '') ?>">
                    </div>

                    <div class="col-lg-4">
                        <label class="form-label">Designation / Post</label>
                        <select name="post_id" class="form-select" required onchange="updateBPS(this)">
                            <option value="">-- Select Post --</option>
                            <?php foreach ($posts as $p): ?>
                            <option value="<?= $p['id'] ?>" data-bps="<?= $p['bps'] ?>"
                                <?= ($edit['post_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['post_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Court</label>
                        <select name="court_id" class="form-select" required>
                            <option value="">-- Select Court --</option>
                            <?php foreach ($courts as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($edit['court_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">BPS Scale</label>
                        <input type="text" name="bps" id="bps" class="form-control" readonly value="<?= htmlspecialchars($edit['bps'] ?? '') ?>">
                    </div>

                    <div class="col-lg-4">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($edit['date_of_birth'] ?? '') ?>">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Date of Appointment</label>
                        <input type="date" name="date_of_appointment" class="form-control" required value="<?= htmlspecialchars($edit['date_of_appointment'] ?? '') ?>">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Date of Retirement</label>
                        <input type="date" name="date_of_retirement" class="form-control" required value="<?= htmlspecialchars($edit['date_of_retirement'] ?? '') ?>">
                    </div>

                    <div class="col-lg-6">
                        <label class="form-label">CNIC Number</label>
                        <input type="text" name="cnic" class="form-control" placeholder="xxxxx-xxxxxxx-x" value="<?= htmlspecialchars($edit['cnic'] ?? '') ?>">
                    </div>

                    <div class="col-lg-6">
                        <label class="form-label">Employee Photo</label>
                        <input type="file" name="pic" class="form-control" accept="image/*">
                        <?php if (!empty($edit['pic'])): ?>
                        <div class="mt-3">
                            <img src="<?= htmlspecialchars($uploadsUrl . $edit['pic']) ?>" alt="Current Photo" class="photo-preview">
                            <div class="form-check mt-2">
                                <input type="checkbox" name="remove_pic" id="removePic" class="form-check-input">
                                <label for="removePic" class="form-check-label text-danger">Remove current photo</label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-end mt-4">
                    <button type="submit" name="save_employee_detail" class="btn btn-primary px-5">
                        <?= $edit ? 'Update Employee' : 'Add Employee' ?>
                    </button>
                    <?php if ($edit): ?>
                    <a href="?page=employee_details" class="btn btn-outline-secondary ms-3">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Employees List Table -->
    <div class="table-card">
        <div class="table-header">
            All Registered Employees
        </div>
        <div class="table-responsive p-3">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Father Name</th>
                        <th>Post</th>
                        <th>Court</th>
                        <th>BPS</th>
                        <th>CNIC</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">No employees added yet.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($employees as $i => $e): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <?php if (!empty($e['pic'])): ?>
                            <img src="<?= htmlspecialchars($uploadsUrl . $e['pic']) ?>" alt="Photo" onclick="showImage(this.src)">
                            <?php else: ?>
                            <div class="bg-light border rounded d-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                                <i class="bi bi-person text-muted" style="font-size:24px;"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($e['name']) ?></strong></td>
                        <td><?= htmlspecialchars($e['father_name']) ?></td>
                        <td><?= htmlspecialchars($e['post_name']) ?></td>
                        <td><?= htmlspecialchars($e['court_name']) ?></td>
                        <td><?= htmlspecialchars($e['bps']) ?></td>
                        <td><?= htmlspecialchars($e['cnic'] ?: '—') ?></td>
                        <td class="action-btns">
                            <a href="?page=employee_details&edit=<?= $e['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                            <a href="?page=employee_details&delete=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this employee permanently?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body p-0 text-center">
                <img id="modalImage" src="" class="img-fluid rounded" style="max-height:80vh;">
            </div>
        </div>
    </div>
</div>

<script>
function updateBPS(select) {
    const bps = select.selectedOptions[0].dataset.bps || '';
    document.getElementById('bps').value = bps;
}

// Auto-fill BPS on page load if editing
document.addEventListener('DOMContentLoaded', function() {
    const postSelect = document.querySelector('select[name="post_id"]');
    if (postSelect) updateBPS(postSelect);
});

function showImage(src) {
    document.getElementById('modalImage').src = src;
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}
</script>

<?php include __DIR__ . '/footer.php'; ?>