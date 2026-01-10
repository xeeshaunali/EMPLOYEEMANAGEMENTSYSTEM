<?php
if (!isset($_SESSION)) session_start();
$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'admin') {
    die("Access denied");
}

require_once __DIR__ . '/../config/db.php'; // provides $pdo

$success = $error = null;

/**
 * Utility check functions
 */
function columnExists(PDO $pdo, string $table, string $column): bool {
    $q = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = ? 
          AND COLUMN_NAME = ?
    ");
    $q->execute([$table, $column]);
    return (bool)$q->fetchColumn();
}

function tableExists(PDO $pdo, string $table): bool {
    $q = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $q->execute([$table]);
    return (bool)$q->fetchColumn();
}

/**
 * Ensure schema exists
 */
try {
    // Judicial Officers table
    if (!tableExists($pdo, 'judicial_officers')) {
        $pdo->exec("
            CREATE TABLE judicial_officers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                post VARCHAR(255) NOT NULL,
                bps VARCHAR(50) NOT NULL,
                court_id INT NULL,
                status ENUM('Posted','Transferred') DEFAULT 'Posted',
                joining_date DATE NULL,
                transferred_date DATE NULL,
                transferred_district VARCHAR(255) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_jo_court FOREIGN KEY (court_id) REFERENCES courts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    if (!columnExists($pdo, 'judicial_officers', 'transferred_district')) {
        $pdo->exec("ALTER TABLE judicial_officers ADD COLUMN transferred_district VARCHAR(255) NULL AFTER transferred_date");
    }

    // Districts table
    if (!tableExists($pdo, 'districts')) {
        $pdo->exec("
            CREATE TABLE districts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Insert default districts
        $districts = [
            'Jamshoro','Karachi (South)','Karachi (West)','Karachi (East)','Karachi (Central)','Karachi (Malir)',
            'Hyderabad','Thata','Dadu','Tharparker @ Mithi','Mirpurkhas','Umerkot','Sanghar',
            'Naushero Feroz','Shaheed Benazirabad','Sukkur','Khairpur','Ghotki','Larkana',
            'Kambar Shahdadkot @ Kambar','Tando Allahyar','Tando Muhammad Khan','Matiyari'
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO districts (name) VALUES (?)");
        foreach ($districts as $d) {
            $stmt->execute([$d]);
        }
    }
} catch (Exception $e) {
    $error = "Schema check/upgrade failed: " . htmlspecialchars($e->getMessage());
}

// ---------------- Handle Form Submission ----------------
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id                  = $_POST['id'] ?? null;
        $name                = trim($_POST['name'] ?? '');
        $post                = trim($_POST['post'] ?? '');
        $bps                 = trim($_POST['bps'] ?? '');
        $court               = $_POST['court_id'] ?? null;
        $status              = $_POST['status'] ?? 'Posted';
        $joiningDate         = $_POST['joining_date'] ?? null;
        $transferredDate     = $_POST['transferred_date'] ?? null;
        $transferredDistrict = trim($_POST['transferred_district'] ?? '');

        if ($name === '' || $post === '' || $bps === '') {
            $error = "Please fill all required fields.";
        } else {
            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE judicial_officers 
                    SET name = ?, post = ?, bps = ?, court_id = ?, status = ?, joining_date = ?, transferred_date = ?, transferred_district = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $post, $bps, ($court ?: null), $status, $joiningDate ?: null, $transferredDate ?: null, ($transferredDistrict ?: null), $id]);
                $success = "Judicial Officer updated successfully.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO judicial_officers (name, post, bps, court_id, status, joining_date, transferred_date, transferred_district)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $post, $bps, ($court ?: null), $status, $joiningDate ?: null, $transferredDate ?: null, ($transferredDistrict ?: null)]);
                $success = "Judicial Officer added successfully.";
            }
        }
    }

    // Delete
    if (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        $pdo->prepare("DELETE FROM judicial_officers WHERE id = ?")->execute([$id]);
        $success = "Judicial Officer deleted.";
        header('Location: ?page=judicial_officers');
        exit;
    }
} catch (Exception $e) {
    $error = "Save/Delete failed: " . htmlspecialchars($e->getMessage());
}

// ---------------- Fetch Data ----------------
try {
    $courts = $pdo->query("SELECT id, name FROM courts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $courts = [];
    $error = $error ?: ("Failed to load courts: " . htmlspecialchars($e->getMessage()));
}

try {
    $districts = $pdo->query("SELECT id, name FROM districts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $districts = [];
    $error = $error ?: ("Failed to load districts: " . htmlspecialchars($e->getMessage()));
}

try {
    $officers = $pdo->query("
        SELECT jo.*, c.name AS court_name
        FROM judicial_officers jo
        LEFT JOIN courts c ON jo.court_id = c.id
        ORDER BY jo.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $officers = [];
    $error = $error ?: ("Failed to load officers: " . htmlspecialchars($e->getMessage()));
}

// If editing
$editOfficer = null;
if (isset($_GET['edit'])) {
    try {
        $id = (int)$_GET['edit'];
        $stmt = $pdo->prepare("SELECT * FROM judicial_officers WHERE id = ?");
        $stmt->execute([$id]);
        $editOfficer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        $error = $error ?: ("Failed to load record for editing: " . htmlspecialchars($e->getMessage()));
    }
}

include __DIR__ . '/header.php';
?>

<!-- Toast Container -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
    <?php if ($success): ?>
        <div class="toast align-items-center text-bg-success border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body"><?= htmlspecialchars($success) ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    <?php elseif ($error): ?>
        <div class="toast align-items-center text-bg-danger border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body"><?= htmlspecialchars($error) ?></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between">
        <h5 class="mb-0"><i class="bi bi-person-badge me-1 rounded shadow"></i>Add Judicial Officers</h5>
    </div>
    <div class="card-body">

        <!-- Add / Edit Form -->
        <form method="post" class="row g-2 mb-4">
            <input type="hidden" name="id" value="<?= $editOfficer['id'] ?? '' ?>">

            <div class="col-md-3 shadow rounded">
                <input type="text" name="name" class="form-control" placeholder="Officer Name"
                       value="<?= htmlspecialchars($editOfficer['name'] ?? '') ?>" required>                
            </div>

            <select name="post" class="form-control col-md-3" required>
                <option value="">-- Select Post --</option>
                <?php
                $stmt = $pdo->query("SELECT id, post_name FROM judicial_post ORDER BY id ASC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<option value='{$row['post_name']}'>{$row['post_name']}</option>";
                }
                ?>
            </select>



            <!-- <div class="col-md-3">
                <input type="text" name="post" class="form-control" placeholder="Post"
                       value="<?= htmlspecialchars($editOfficer['post'] ?? '') ?>" required>
            </div> -->
            <div class="col-md-3">
                <input type="text" name="bps" class="form-control" placeholder="BPS"
                       value="<?= htmlspecialchars($editOfficer['bps'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
                <select name="court_id" class="form-select">
                    <option value="">-- Court --</option>
                    <?php foreach ($courts as $c): ?>
                        <option value="<?= $c['id'] ?>"
                            <?= (isset($editOfficer['court_id']) && (string)$editOfficer['court_id'] === (string)$c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <select name="status" id="status" class="form-select" required>
                    <?php $st = $editOfficer['status'] ?? 'Posted'; ?>
                    <option value="Posted" <?= ($st === 'Posted') ? 'selected' : '' ?>>Posted</option>
                    <option value="Transferred" <?= ($st === 'Transferred') ? 'selected' : '' ?>>Transferred</option>
                </select>
            </div>

            <div class="col-md-3">
                <input type="date" name="joining_date" class="form-control"
                       value="<?= htmlspecialchars($editOfficer['joining_date'] ?? '') ?>"
                       placeholder="Joining Date">
            </div>

            <div class="col-md-2 rounded shadow" id="transferredDateCol">
                <input type="date" name="transferred_date" class="form-control"
                       value="<?= htmlspecialchars($editOfficer['transferred_date'] ?? '') ?>"
                       placeholder="Transferred Date">
            </div>

            <div class="col-md-3" id="transferredDistrictCol">
                <select name="transferred_district" class="form-select">
                    <option value="">-- Select District --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= htmlspecialchars($d['name']) ?>"
                            <?= (isset($editOfficer['transferred_district']) && $editOfficer['transferred_district'] === $d['name']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 d-flex">
                <button type="submit" class="btn btn-success me-1">
                    <?= $editOfficer ? 'Update' : 'Add' ?>
                </button>
                <?php if ($editOfficer): ?>
                    <a href="?page=judicial_officers" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Officers Table -->
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Post</th>
                    <th>BPS</th>
                    <th>Court</th>
                    <th>Status</th>
                    <th>Joining Date</th>
                    <th>Transferred Date</th>
                    <th>Transferred District</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($officers)): ?>
                    <tr><td colspan="11" class="text-center">No officers found.</td></tr>
                <?php else: ?>
                    <?php foreach ($officers as $o): ?>
                        <tr>
                            <td><?= (int)$o['id'] ?></td>
                            <td><?= htmlspecialchars($o['name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($o['post'] ?? '') ?></td>
                            <td><?= htmlspecialchars($o['bps'] ?? '') ?></td>
                            <td><?= htmlspecialchars($o['court_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($o['status'] ?? 'Posted') ?></td>
                            <td><?= htmlspecialchars($o['joining_date'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($o['transferred_date'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($o['transferred_district'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($o['created_at'] ?? '—') ?></td>
                            <td>
                                <a href="?page=judicial_officers&edit=<?= (int)$o['id'] ?>"
                                   class="btn btn-sm btn-warning">Edit</a>
                                <a href="?page=judicial_officers&delete=<?= (int)$o['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete this officer?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Show/Hide Transferred fields based on Status
function toggleTransferredFields() {
    var statusEl = document.getElementById('status');
    var dateCol = document.getElementById('transferredDateCol');
    var districtCol = document.getElementById('transferredDistrictCol');
    if (!statusEl) return;

    if (statusEl.value === 'Transferred') {
        if (dateCol) dateCol.style.display = '';
        if (districtCol) districtCol.style.display = '';
    } else {
        if (dateCol) {
            dateCol.style.display = 'none';
            var inp = dateCol.querySelector('input[name="transferred_date"]');
            if (inp) inp.value = '';
        }
        if (districtCol) {
            districtCol.style.display = 'none';
            var sel = districtCol.querySelector('select[name="transferred_district"]');
            if (sel) sel.value = '';
        }
    }
}
document.addEventListener('DOMContentLoaded', function() {
    toggleTransferredFields();
    var statusEl = document.getElementById('status');
    if (statusEl) statusEl.addEventListener('change', toggleTransferredFields);

    // Auto-hide toasts after 3s
    document.querySelectorAll('.toast').forEach(function(toastEl) {
        if (window.bootstrap) {
            var t = new bootstrap.Toast(toastEl, { delay: 3000 });
            t.show();
        }
    });
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
