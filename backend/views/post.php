<?php
// Start session + DB
if (!isset($_SESSION)) session_start();
require_once __DIR__ . '/../config/db.php';

// Get highest serial_no for auto-fill
$lastSerial = $pdo->query("SELECT MAX(serial_no) FROM posts")->fetchColumn();
$nextSerial = $lastSerial ? $lastSerial + 1 : 1;

// Get statistics
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total_posts,
        SUM(sanctioned_strength) as total_sanctioned,
        SUM(working_strength) as total_working,
        SUM(sanctioned_strength - working_strength) as total_vacant,
        COUNT(DISTINCT court_name) as total_courts,
        COUNT(DISTINCT bps) as total_bps_levels
    FROM posts
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get unique courts for filter
$courts = $pdo->query("SELECT DISTINCT court_name FROM posts ORDER BY court_name")->fetchAll(PDO::FETCH_COLUMN);

// If editing, get post details
$editPost = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $postId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $editPost = $stmt->fetch();
}

// Delete post BEFORE header.php
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $postId = intval($_GET['delete']);
    
    // Check if any employees are using this post
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employee_details WHERE post_id = ?");
    $checkStmt->execute([$postId]);
    $employeeCount = $checkStmt->fetchColumn();
    
    if ($employeeCount > 0) {
        $errorMsg = "Cannot delete this post as it is assigned to {$employeeCount} employee(s). Please reassign them first.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        header('Location: ?page=posts&msg=deleted');
        exit;
    }
}

// Add or Update post BEFORE header.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_name'], $_POST['bps'], $_POST['sanctioned_strength'], $_POST['working_strength'], $_POST['serial_no'], $_POST['court_name'])) {
    $serial_no = intval($_POST['serial_no']);
    $postName = trim($_POST['post_name']);
    $bps = intval($_POST['bps']);
    $sanctioned = intval($_POST['sanctioned_strength']);
    $working = intval($_POST['working_strength']);
    $courtName = trim($_POST['court_name']);
    $id = intval($_POST['id'] ?? 0);

    // Validation
    $errors = [];
    
    if (!$postName) {
        $errors[] = "Post name is required.";
    }
    
    if ($bps <= 0) {
        $errors[] = "BPS must be greater than 0.";
    }
    
    if ($sanctioned < 0) {
        $errors[] = "Sanctioned strength cannot be negative.";
    }
    
    if ($working < 0) {
        $errors[] = "Working strength cannot be negative.";
    }
    
    if ($working > $sanctioned) {
        $errors[] = "Working strength cannot exceed sanctioned strength.";
    }
    
    if ($serial_no <= 0) {
        $errors[] = "Serial number must be greater than 0.";
    }
    
    if (!$courtName) {
        $errors[] = "Court name is required.";
    }

    if (empty($errors)) {
        if ($id > 0) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE posts SET serial_no=?, post_name=?, bps=?, sanctioned_strength=?, working_strength=?, court_name=? WHERE id=?");
            $stmt->execute([$serial_no, $postName, $bps, $sanctioned, $working, $courtName, $id]);
            header('Location: ?page=posts&msg=updated');
            exit;
        } else {
            // Check for duplicate serial
            $checkSerial = $pdo->prepare("SELECT id FROM posts WHERE serial_no = ?");
            $checkSerial->execute([$serial_no]);
            if ($checkSerial->fetch()) {
                $errorMsg = "Serial number already exists. Please use a different serial number.";
            } else {
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO posts (serial_no, post_name, bps, sanctioned_strength, working_strength, court_name) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$serial_no, $postName, $bps, $sanctioned, $working, $courtName]);
                header('Location: ?page=posts&msg=added');
                exit;
            }
        }
    } else {
        $errorMsg = implode('<br>', $errors);
    }
}

// ✅ Now include header.php
include __DIR__ . '/header.php';

// Success messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        echo '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>Post added successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    if ($_GET['msg'] === 'updated') {
        echo '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>Post updated successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    if ($_GET['msg'] === 'deleted') {
        echo '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i>Post deleted successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}
if (!empty($errorMsg)) {
    echo '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i>' . $errorMsg . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// Fetch filter parameters
$filterCourt = $_GET['filter_court'] ?? '';
$filterBps = $_GET['filter_bps'] ?? '';

// Fetch all posts with optional filtering
$query = "SELECT * FROM posts";
$params = [];
$where = [];

if ($filterCourt) {
    $where[] = "court_name = ?";
    $params[] = $filterCourt;
}
if ($filterBps) {
    $where[] = "bps = ?";
    $params[] = $filterBps;
}

if ($where) {
    $query .= " WHERE " . implode(" AND ", $where);
}
$query .= " ORDER BY serial_no ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Get unique BPS values for filter
$bpsLevels = $pdo->query("SELECT DISTINCT bps FROM posts ORDER BY bps")->fetchAll(PDO::FETCH_COLUMN);
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
        content: '\f0ae';
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
    .stat-card.vacant {
        border-left-color: var(--danger-color);
    }
    .stat-card.vacant .stat-value {
        color: var(--danger-color);
    }
    .stat-card.vacant .stat-icon {
        color: var(--danger-color);
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
    }
    .btn-cancel:hover {
        background: #5a6268;
        color: white;
        transform: translateY(-2px);
    }

    /* Filter Section */
    .filter-section {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: 1px solid #e9ecef;
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

    .vacant-badge {
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        display: inline-block;
    }
    .vacant-high {
        background: #f8d7da;
        color: #721c24;
    }
    .vacant-medium {
        background: #fff3cd;
        color: #856404;
    }
    .vacant-low {
        background: #d4edda;
        color: #155724;
    }
    .vacant-filled {
        background: #cce5ff;
        color: #004085;
    }

    .btn-action {
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        font-size: 0.85rem;
        transition: all 0.3s;
        margin: 0 2px;
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

    /* Progress Bar */
    .progress {
        height: 8px;
        border-radius: 4px;
        margin-top: 0.3rem;
    }
    .progress-bar {
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
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
        <h2><i class="fas fa-briefcase me-3"></i>Posts & BPS Management</h2>
        <p>Manage employee designations, sanctioned strength, and vacancy tracking</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
            <div class="stat-value"><?= number_format($stats['total_posts'] ?? 0) ?></div>
            <div class="stat-label">Total Posts</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-building"></i></div>
            <div class="stat-value"><?= number_format($stats['total_courts'] ?? 0) ?></div>
            <div class="stat-label">Courts</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
            <div class="stat-value"><?= number_format($stats['total_bps_levels'] ?? 0) ?></div>
            <div class="stat-label">BPS Levels</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?= number_format($stats['total_sanctioned'] ?? 0) ?></div>
            <div class="stat-label">Sanctioned</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
            <div class="stat-value"><?= number_format($stats['total_working'] ?? 0) ?></div>
            <div class="stat-label">Working</div>
        </div>
        <div class="stat-card vacant">
            <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
            <div class="stat-value"><?= number_format($stats['total_vacant'] ?? 0) ?></div>
            <div class="stat-label">Vacant</div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-header">
            <i class="fas <?= $editPost ? 'fa-edit' : 'fa-plus-circle' ?>"></i>
            <span><?= $editPost ? 'Edit Post' : 'Add New Post' ?></span>
        </div>
        <div class="form-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="id" value="<?= $editPost['id'] ?? '' ?>">
                
                <div class="col-md-1">
                    <label class="form-label"><i class="fas fa-hashtag"></i> Serial</label>
                    <input type="number" name="serial_no" class="form-control" placeholder="Serial" required min="1" value="<?= htmlspecialchars($editPost['serial_no'] ?? $nextSerial) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label"><i class="fas fa-gavel"></i> Court</label>
                    <select name="court_name" class="form-select" required>
                        <option value="">Select Court</option>
                        <option value="District Court Jamshoro" <?= isset($editPost['court_name']) && $editPost['court_name'] === 'District Court Jamshoro' ? 'selected' : '' ?>>District Court Jamshoro</option>
                        <option value="Consumer Protection Court" <?= isset($editPost['court_name']) && $editPost['court_name'] === 'Consumer Protection Court' ? 'selected' : '' ?>>Consumer Protection Court</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-user-tie"></i> Post Name</label>
                    <input type="text" name="post_name" class="form-control" placeholder="e.g., Office Superintendent" required value="<?= htmlspecialchars($editPost['post_name'] ?? '') ?>">
                </div>

                <div class="col-md-1">
                    <label class="form-label"><i class="fas fa-layer-group"></i> BPS</label>
                    <input type="number" name="bps" class="form-control" placeholder="BPS" required min="1" value="<?= htmlspecialchars($editPost['bps'] ?? '') ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label"><i class="fas fa-check-circle"></i> Sanctioned</label>
                    <input type="number" name="sanctioned_strength" class="form-control" placeholder="Sanctioned" required min="0" value="<?= htmlspecialchars($editPost['sanctioned_strength'] ?? '') ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label"><i class="fas fa-user-check"></i> Working</label>
                    <input type="number" name="working_strength" class="form-control" placeholder="Working" required min="0" value="<?= htmlspecialchars($editPost['working_strength'] ?? '') ?>">
                </div>

                <div class="col-md-1">
                    <label class="form-label"><i class="fas <?= $editPost ? 'fa-sync' : 'fa-save' ?>"></i> Action</label>
                    <button type="submit" class="btn <?= $editPost ? 'btn-update' : 'btn-submit' ?>">
                        <i class="fas <?= $editPost ? 'fa-sync' : 'fa-save' ?>"></i> 
                        <?= $editPost ? 'Update' : 'Save' ?>
                    </button>
                </div>

                <?php if ($editPost): ?>
                <div class="col-md-1">
                    <label class="form-label"><i class="fas fa-times"></i> Cancel</label>
                    <a href="?page=posts" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="posts">
            
            <div class="col-md-4">
                <label class="form-label"><i class="fas fa-building"></i> Filter by Court</label>
                <select name="filter_court" class="form-select" onchange="this.form.submit()">
                    <option value="">All Courts</option>
                    <?php foreach ($courts as $court): ?>
                        <option value="<?= htmlspecialchars($court) ?>" <?= ($filterCourt == $court) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($court) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label"><i class="fas fa-layer-group"></i> Filter by BPS</label>
                <select name="filter_bps" class="form-select" onchange="this.form.submit()">
                    <option value="">All BPS Levels</option>
                    <?php foreach ($bpsLevels as $bps): ?>
                        <option value="<?= $bps ?>" <?= ($filterBps == $bps) ? 'selected' : '' ?>>
                            BPS-<?= $bps ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <a href="?page=posts" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            </div>

            <?php if ($filterCourt || $filterBps): ?>
                <div class="col-12 mt-2">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Active filters: 
                        <?php if ($filterCourt): ?>
                            <span class="badge bg-info me-1">Court: <?= htmlspecialchars($filterCourt) ?></span>
                        <?php endif; ?>
                        <?php if ($filterBps): ?>
                            <span class="badge bg-info me-1">BPS-<?= $filterBps ?></span>
                        <?php endif; ?>
                    </small>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Posts Table -->
    <div class="table-container">
        <div class="table-header">
            <span>
                <i class="fas fa-list me-2"></i>
                Posts Directory
            </span>
            <span class="badge">
                <i class="fas fa-database me-1"></i> 
                <?= count($posts) ?> Records
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th>Court</th>
                        <th>Post</th>
                        <th>BPS</th>
                        <th>Sanctioned</th>
                        <th>Working</th>
                        <th>Vacant</th>
                        <th>Fill Rate</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($posts)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Posts Found</h5>
                                <p class="text-muted">Add your first post using the form above.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($posts as $p): 
                            $vacant = max(0, $p['sanctioned_strength'] - $p['working_strength']);
                            $fillRate = $p['sanctioned_strength'] > 0 ? round(($p['working_strength'] / $p['sanctioned_strength']) * 100) : 0;
                            
                            // Determine vacancy badge class
                            if ($vacant == 0) {
                                $vacantClass = 'vacant-filled';
                                $statusText = 'Filled';
                            } elseif ($vacant <= 2) {
                                $vacantClass = 'vacant-low';
                                $statusText = 'Low Vacancy';
                            } elseif ($vacant <= 5) {
                                $vacantClass = 'vacant-medium';
                                $statusText = 'Medium Vacancy';
                            } else {
                                $vacantClass = 'vacant-high';
                                $statusText = 'High Vacancy';
                            }
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['serial_no']) ?></strong></td>
                                <td>
                                    <span class="d-flex align-items-center gap-1">
                                        <i class="fas fa-building text-muted"></i>
                                        <?= htmlspecialchars($p['court_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($p['post_name']) ?></strong>
                                </td>
                                <td><span class="badge bg-secondary">BPS-<?= htmlspecialchars($p['bps']) ?></span></td>
                                <td class="text-center"><?= htmlspecialchars($p['sanctioned_strength']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($p['working_strength']) ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $vacantClass ?>"><?= $vacant ?></span>
                                </td>
                                <td style="min-width: 120px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <span><?= $fillRate ?>%</span>
                                        <div class="progress flex-grow-1">
                                            <div class="progress-bar" style="width: <?= $fillRate ?>%;" 
                                                 role="progressbar" aria-valuenow="<?= $fillRate ?>" 
                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="vacant-badge <?= $vacantClass ?>">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="?page=posts&edit=<?= $p['id'] ?>" 
                                           class="btn-action btn-edit" 
                                           title="Edit Post">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?page=posts&delete=<?= $p['id'] ?>" 
                                           class="btn-action btn-delete" 
                                           onclick="return confirmDelete('<?= htmlspecialchars($p['post_name']) ?>')"
                                           title="Delete Post">
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
        <?php if (!empty($posts)): ?>
        <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                <i class="fas fa-file me-1"></i>
                Showing <?= count($posts) ?> posts • 
                <i class="fas fa-users ms-2 me-1"></i>Total sanctioned: <?= array_sum(array_column($posts, 'sanctioned_strength')) ?> • 
                <i class="fas fa-user-check ms-2 me-1"></i>Working: <?= array_sum(array_column($posts, 'working_strength')) ?> • 
                <i class="fas fa-user-slash ms-2 me-1 text-danger"></i>Vacant: <?= array_sum(array_map(function($p) { return max(0, $p['sanctioned_strength'] - $p['working_strength']); }, $posts)) ?>
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
// Delete confirmation with post name
function confirmDelete(postName) {
    return confirm(`Are you sure you want to delete "${postName}"?\nThis action cannot be undone.`);
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
    link.setAttribute('download', 'posts_report_' + new Date().toISOString().slice(0,10) + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Working strength validation
document.querySelector('input[name="working_strength"]').addEventListener('input', function() {
    const sanctioned = parseInt(document.querySelector('input[name="sanctioned_strength"]').value) || 0;
    const working = parseInt(this.value) || 0;
    
    if (working > sanctioned) {
        this.setCustomValidity('Working strength cannot exceed sanctioned strength');
        this.classList.add('is-invalid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
    }
});

document.querySelector('input[name="sanctioned_strength"]').addEventListener('input', function() {
    const working = parseInt(document.querySelector('input[name="working_strength"]').value) || 0;
    const sanctioned = parseInt(this.value) || 0;
    
    if (working > sanctioned) {
        document.querySelector('input[name="working_strength"]').setCustomValidity('Working strength cannot exceed sanctioned strength');
        document.querySelector('input[name="working_strength"]').classList.add('is-invalid');
    } else {
        document.querySelector('input[name="working_strength"]').setCustomValidity('');
        document.querySelector('input[name="working_strength"]').classList.remove('is-invalid');
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+N for new post (when not editing)
    <?php if (!$editPost): ?>
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        document.querySelector('input[name="post_name"]').focus();
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