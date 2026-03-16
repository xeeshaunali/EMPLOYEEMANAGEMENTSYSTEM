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
        
        // Check if officer has any related records before deletion
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM judicial_officers WHERE id = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->fetchColumn() > 0) {
            $pdo->prepare("DELETE FROM judicial_officers WHERE id = ?")->execute([$id]);
            $success = "Judicial Officer deleted successfully.";
        }
        
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
    $judicialPosts = $pdo->query("SELECT id, post_name FROM judicial_post ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $judicialPosts = [];
    $error = $error ?: ("Failed to load judicial posts: " . htmlspecialchars($e->getMessage()));
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

// Get statistics
$stats = [
    'total' => count($officers),
    'posted' => 0,
    'transferred' => 0,
    'with_court' => 0
];

foreach ($officers as $o) {
    if ($o['status'] === 'Posted') $stats['posted']++;
    if ($o['status'] === 'Transferred') $stats['transferred']++;
    if (!empty($o['court_id'])) $stats['with_court']++;
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
        content: '\f4fe';
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
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border-left: 4px solid var(--primary-color);
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,85,102,0.15);
    }
    .stat-card .stat-icon {
        position: absolute;
        right: 1rem;
        top: 1rem;
        font-size: 2.5rem;
        opacity: 0.2;
        color: var(--primary-color);
    }
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
        margin: 0;
        line-height: 1.2;
    }
    .stat-label {
        color: #6c757d;
        margin: 0.3rem 0 0;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .stat-card.posted {
        border-left-color: var(--success-color);
    }
    .stat-card.posted .stat-value {
        color: var(--success-color);
    }
    .stat-card.transferred {
        border-left-color: var(--warning-color);
    }
    .stat-card.transferred .stat-value {
        color: var(--warning-color);
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
        width: 100%;
    }
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(40,167,69,0.3);
    }
    .btn-update {
        background: linear-gradient(90deg, var(--warning-color), #ffb300);
        color: #333;
        border: none;
        border-radius: 8px;
        padding: 0.8rem 2rem;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
    }
    .btn-update:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(255,193,7,0.3);
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
        width: 100%;
    }
    .btn-cancel:hover {
        background: #5a6268;
        color: white;
        transform: translateY(-2px);
    }

    /* Table Styles */
    .table-container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    .table-header {
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 1rem 1.5rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .table-header .badge {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
    }
    .table {
        margin: 0;
        font-size: 0.95rem;
    }
    .table thead {
        background: linear-gradient(90deg, #f8f9fa, #e9ecef);
    }
    .table thead th {
        color: var(--primary-color);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        padding: 1rem;
        border-bottom: 2px solid var(--primary-color);
        white-space: nowrap;
    }
    .table tbody tr {
        transition: all 0.2s;
    }
    .table tbody tr:hover {
        background-color: #f1f8ff !important;
        transform: scale(1.01);
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .table td {
        padding: 1rem;
        vertical-align: middle;
    }

    .status-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        display: inline-block;
    }
    .status-posted {
        background: #d4edda;
        color: #155724;
    }
    .status-transferred {
        background: #fff3cd;
        color: #856404;
    }

    .btn-action {
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        font-size: 0.85rem;
        transition: all 0.3s;
        margin: 0 2px;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .btn-edit {
        background: var(--warning-color);
        color: #333;
        border: none;
    }
    .btn-delete {
        background: var(--danger-color);
        color: white;
        border: none;
    }

    /* Toast Container */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1100;
    }
    .toast {
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .page-title h2 { font-size: 1.6rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .table td { white-space: nowrap; }
    }
</style>

<div class="container-fluid mt-4">
    <!-- Page Title -->
    <div class="page-title">
        <h2><i class="fas fa-gavel me-3"></i>Judicial Officers Management</h2>
        <p>Manage judicial officers, their postings, transfers, and court assignments</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?= $stats['total'] ?></div>
            <div class="stat-label">Total Officers</div>
        </div>
        <div class="stat-card posted">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?= $stats['posted'] ?></div>
            <div class="stat-label">Posted</div>
        </div>
        <div class="stat-card transferred">
            <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-value"><?= $stats['transferred'] ?></div>
            <div class="stat-label">Transferred</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-building"></i></div>
            <div class="stat-value"><?= $stats['with_court'] ?></div>
            <div class="stat-label">With Court</div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container">
        <?php if ($success): ?>
            <div class="toast align-items-center text-white bg-success border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="toast align-items-center text-white bg-danger border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-header">
            <i class="fas <?= $editOfficer ? 'fa-edit' : 'fa-user-plus' ?>"></i>
            <span><?= $editOfficer ? 'Edit Judicial Officer' : 'Add New Judicial Officer' ?></span>
        </div>
        <div class="form-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="id" value="<?= $editOfficer['id'] ?? '' ?>">

                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-user-tie"></i> Officer Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter full name"
                           value="<?= htmlspecialchars($editOfficer['name'] ?? '') ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-briefcase"></i> Post</label>
                    <select name="post" class="form-select" required>
                        <option value="">-- Select Post --</option>
                        <?php foreach ($judicialPosts as $jp): ?>
                            <option value="<?= htmlspecialchars($jp['post_name']) ?>"
                                <?= (isset($editOfficer['post']) && $editOfficer['post'] === $jp['post_name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($jp['post_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label"><i class="fas fa-layer-group"></i> BPS</label>
                    <select name="bps" class="form-select" required>
                        <option value="">-- BPS --</option>
                        <?php for($i = 1; $i <= 22; $i++): ?>
                            <option value="<?= $i ?>" <?= (isset($editOfficer['bps']) && $editOfficer['bps'] == $i) ? 'selected' : '' ?>>
                                BPS-<?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-building"></i> Court</label>
                    <select name="court_id" class="form-select">
                        <option value="">-- Select Court (Optional) --</option>
                        <?php foreach ($courts as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                <?= (isset($editOfficer['court_id']) && (string)$editOfficer['court_id'] === (string)$c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-info-circle"></i> Status</label>
                    <select name="status" id="status" class="form-select" required>
                        <?php $st = $editOfficer['status'] ?? 'Posted'; ?>
                        <option value="Posted" <?= ($st === 'Posted') ? 'selected' : '' ?>>Posted</option>
                        <option value="Transferred" <?= ($st === 'Transferred') ? 'selected' : '' ?>>Transferred</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-calendar-plus"></i> Joining Date</label>
                    <input type="date" name="joining_date" class="form-control"
                           value="<?= htmlspecialchars($editOfficer['joining_date'] ?? '') ?>"
                           placeholder="Joining Date">
                </div>

                <div class="col-md-3" id="transferredDateCol">
                    <label class="form-label"><i class="fas fa-calendar-times"></i> Transferred Date</label>
                    <input type="date" name="transferred_date" class="form-control"
                           value="<?= htmlspecialchars($editOfficer['transferred_date'] ?? '') ?>"
                           placeholder="Transferred Date">
                </div>

                <div class="col-md-3" id="transferredDistrictCol">
                    <label class="form-label"><i class="fas fa-map-marker-alt"></i> Transferred District</label>
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

                <div class="col-md-6 d-flex align-items-end">
                    <div class="row w-100 g-2">
                        <div class="col">
                            <button type="submit" class="btn <?= $editOfficer ? 'btn-update' : 'btn-submit' ?>">
                                <i class="fas <?= $editOfficer ? 'fa-sync' : 'fa-save' ?>"></i> 
                                <?= $editOfficer ? 'Update Officer' : 'Add Officer' ?>
                            </button>
                        </div>
                        <?php if ($editOfficer): ?>
                            <div class="col">
                                <a href="?page=judicial_officers" class="btn-cancel">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Officers Table -->
    <div class="table-container">
        <div class="table-header">
            <span>
                <i class="fas fa-list me-2"></i>
                Judicial Officers Directory
            </span>
            <span class="badge">
                <i class="fas fa-database me-1"></i> 
                <?= count($officers) ?> Records
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Officer Name</th>
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
                        <tr>
                            <td colspan="11" class="text-center py-5">
                                <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Judicial Officers Found</h5>
                                <p class="text-muted">Add your first judicial officer using the form above.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($officers as $o): ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?= (int)$o['id'] ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($o['name'] ?? '') ?></strong>
                                </td>
                                <td><?= htmlspecialchars($o['post'] ?? '') ?></td>
                                <td><span class="badge bg-info">BPS-<?= htmlspecialchars($o['bps'] ?? '') ?></span></td>
                                <td>
                                    <?php if (!empty($o['court_name'])): ?>
                                        <span class="d-flex align-items-center gap-1">
                                            <i class="fas fa-building text-muted small"></i>
                                            <?= htmlspecialchars($o['court_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $o['status'] === 'Posted' ? 'status-posted' : 'status-transferred' ?>">
                                        <?= htmlspecialchars($o['status'] ?? 'Posted') ?>
                                    </span>
                                </td>
                                <td><?= !empty($o['joining_date']) ? date('d-m-Y', strtotime($o['joining_date'])) : '—' ?></td>
                                <td><?= !empty($o['transferred_date']) ? date('d-m-Y', strtotime($o['transferred_date'])) : '—' ?></td>
                                <td><?= htmlspecialchars($o['transferred_district'] ?? '—') ?></td>
                                <td>
                                    <span title="<?= date('d M Y H:i', strtotime($o['created_at'])) ?>">
                                        <?= date('d-m-Y', strtotime($o['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="?page=judicial_officers&edit=<?= (int)$o['id'] ?>"
                                           class="btn-action btn-edit" 
                                           title="Edit Officer">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?page=judicial_officers&delete=<?= (int)$o['id'] ?>"
                                           class="btn-action btn-delete"
                                           onclick="return confirmDelete('<?= htmlspecialchars($o['name']) ?>')"
                                           title="Delete Officer">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Table Footer -->
        <?php if (!empty($officers)): ?>
        <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                <i class="fas fa-file me-1"></i>
                Showing <?= count($officers) ?> officers • 
                <i class="fas fa-check-circle ms-2 me-1 text-success"></i><?= $stats['posted'] ?> Posted • 
                <i class="fas fa-exchange-alt ms-2 me-1 text-warning"></i><?= $stats['transferred'] ?> Transferred
            </div>
            <div>
                <button class="btn btn-sm btn-outline-primary" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Export
                </button>
                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        <?php endif; ?>
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
        if (dateCol) {
            dateCol.style.display = '';
            dateCol.style.opacity = '1';
        }
        if (districtCol) {
            districtCol.style.display = '';
            districtCol.style.opacity = '1';
        }
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

// Delete confirmation with officer name
function confirmDelete(officerName) {
    return confirm(`Are you sure you want to delete "${officerName}"?\nThis action cannot be undone.`);
}

// Export to Excel
function exportToExcel() {
    const table = document.querySelector('.table');
    const rows = [];
    
    // Get headers
    const headers = [];
    table.querySelectorAll('thead th').forEach((th, index) => {
        // Skip actions column (last column)
        if (index < table.querySelectorAll('thead th').length - 1) {
            headers.push(th.innerText);
        }
    });
    rows.push(headers.join(','));
    
    // Get data rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        if (tr.querySelector('td[colspan]')) return; // Skip "no records" row
        const row = [];
        tr.querySelectorAll('td').forEach((td, index) => {
            // Skip actions column (last column)
            if (index < tr.querySelectorAll('td').length - 1) {
                // Clean the text - remove HTML tags and extra spaces
                let text = td.innerText.replace(/\s+/g, ' ').trim();
                // Escape quotes
                text = text.replace(/"/g, '""');
                row.push('"' + text + '"');
            }
        });
        rows.push(row.join(','));
    });
    
    // Download CSV
    const csvContent = rows.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'judicial_officers_' + new Date().toISOString().slice(0,10) + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize on page load
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

    // Add animation to status changes
    if (statusEl) {
        statusEl.addEventListener('change', function() {
            const dateCol = document.getElementById('transferredDateCol');
            const districtCol = document.getElementById('transferredDistrictCol');
            
            if (this.value === 'Transferred') {
                dateCol.style.opacity = '0';
                districtCol.style.opacity = '0';
                setTimeout(() => {
                    dateCol.style.opacity = '1';
                    districtCol.style.opacity = '1';
                }, 50);
            }
        });
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+N for new officer (when not editing)
    <?php if (!$editOfficer): ?>
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        document.querySelector('input[name="name"]').focus();
    }
    <?php endif; ?>
    
    // Ctrl+E for export
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        exportToExcel();
    }
});
</script>

<?php include __DIR__ . '/footer.php'; ?>