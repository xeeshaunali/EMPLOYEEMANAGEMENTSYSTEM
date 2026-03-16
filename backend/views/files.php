<?php include __DIR__ . '/header.php'; ?>

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
        padding: 2.5rem 1.5rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        text-align: center;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        position: relative;
        overflow: hidden;
    }
    .page-title::before {
        content: '\f0c5';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        right: 20px;
        bottom: -20px;
        font-size: 8rem;
        opacity: 0.1;
        color: white;
        transform: rotate(15deg);
    }
    .page-title h3 {
        margin: 0;
        font-weight: 700;
        font-size: 2.2rem;
        letter-spacing: 0.5px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
    }
    .page-title small {
        opacity: 0.95;
        font-size: 1.1rem;
        font-weight: 400;
        display: block;
        margin-top: 0.5rem;
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
        transition: transform 0.3s, box-shadow 0.3s;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,85,102,0.15);
    }
    .stat-info h4 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-color);
        margin: 0;
        line-height: 1.2;
    }
    .stat-info p {
        color: #6c757d;
        margin: 0;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .stat-icon {
        font-size: 2.5rem;
        color: rgba(0,85,102,0.2);
    }

    /* Upload Card */
    .upload-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 2rem;
        border: 1px solid rgba(0,85,102,0.1);
    }
    .upload-header {
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 1.5rem 2rem;
        font-size: 1.3rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .upload-header i {
        font-size: 1.8rem;
        opacity: 0.9;
    }
    .upload-header .header-stats {
        margin-left: auto;
        font-size: 0.9rem;
        background: rgba(255,255,255,0.2);
        padding: 0.3rem 1rem;
        border-radius: 20px;
    }

    .form-body {
        padding: 2rem;
        background: #f8fafc;
    }
    .form-label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .form-label i {
        color: var(--primary-color);
        font-size: 1rem;
    }
    .form-control, .form-select {
        border-radius: 10px;
        padding: 0.7rem 1rem;
        font-size: 0.95rem;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
        background: white;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(0, 85, 102, 0.15);
    }
    .form-control:hover, .form-select:hover {
        border-color: #adb5bd;
    }

    .file-upload-area {
        border: 2px dashed #cbd5e0;
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        background: #f1f5f9;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: 1rem;
    }
    .file-upload-area:hover {
        border-color: var(--primary-color);
        background: #e9ecef;
    }
    .file-upload-area i {
        font-size: 3rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }
    .file-upload-area p {
        margin: 0;
        color: #4a5568;
    }
    .file-upload-area small {
        color: #718096;
    }

    .btn-upload {
        background: linear-gradient(90deg, var(--success-color), #20c997);
        border: none;
        border-radius: 12px;
        padding: 1rem 2rem;
        font-size: 1.1rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .btn-upload:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(40,167,69,0.3);
    }
    .btn-upload:active {
        transform: translateY(0);
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
    .filter-title {
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .filter-title i {
        font-size: 1.2rem;
    }

    .btn-filter {
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.7rem 1.5rem;
        font-weight: 500;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .btn-filter:hover {
        background: var(--secondary-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,123,255,0.3);
    }
    .btn-clear {
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.7rem 1.5rem;
        font-weight: 500;
        transition: all 0.3s;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .btn-clear:hover {
        background: #5a6268;
        color: white;
        transform: translateY(-2px);
    }

    /* Files Table */
    .files-table-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        overflow: hidden;
        border: 1px solid rgba(0,85,102,0.1);
    }
    .table-header {
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 1.2rem 2rem;
        font-size: 1.2rem;
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

    .file-badge {
        background: #e9ecef;
        color: var(--primary-color);
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .file-badge i {
        font-size: 0.9rem;
    }

    .btn-action {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: all 0.3s;
        margin: 0 2px;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .btn-download {
        background: var(--primary-color);
        color: white;
        border: none;
    }
    .btn-download:hover {
        background: var(--secondary-color);
    }
    .btn-delete {
        background: var(--danger-color);
        color: white;
        border: none;
    }
    .btn-delete:hover {
        background: #c82333;
    }

    /* Pagination */
    .pagination {
        gap: 0.3rem;
        margin: 1.5rem 0;
    }
    .page-link {
        border-radius: 8px;
        border: none;
        padding: 0.6rem 1rem;
        color: var(--primary-color);
        font-weight: 500;
        transition: all 0.3s;
    }
    .page-link:hover {
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,85,102,0.2);
    }
    .page-item.active .page-link {
        background: var(--primary-color);
        color: white;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }
    .empty-state i {
        font-size: 5rem;
        color: #dee2e6;
        margin-bottom: 1.5rem;
    }
    .empty-state h5 {
        color: #495057;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    .empty-state p {
        color: #6c757d;
        max-width: 400px;
        margin: 0 auto;
    }

    /* Loading Spinner */
    .spinner-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0.8);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }
    .spinner-overlay.show {
        display: flex;
    }
    .spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid var(--primary-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .page-title h3 { font-size: 1.6rem; }
        .page-title small { font-size: 0.9rem; }
        .stats-grid { grid-template-columns: 1fr; }
        .upload-header, .table-header { 
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
        .btn-upload { width: 100%; }
        .table td { white-space: nowrap; }
    }
</style>

<?php
// Calculate statistics
$totalFiles = count($rows ?? []);
$totalSize = 0;
$categories = [];
foreach ($rows as $f) {
    $categories[$f['category']] = ($categories[$f['category']] ?? 0) + 1;
}
$uniqueCategories = count($categories);
$mostUsedCategory = $categories ? array_search(max($categories), $categories) : 'None';
?>

<div class="container-fluid mt-4">
    <!-- Page Title -->
    <div class="page-title">
        <h3><i class="fas fa-folder-open me-3"></i>Document Management System</h3>
        <small>Upload, organize and manage court documents and employee records securely</small>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h4><?= number_format($totalFiles) ?></h4>
                <p>Total Files</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-file-alt"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h4><?= $uniqueCategories ?></h4>
                <p>Categories</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-tags"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h4><?= htmlspecialchars($mostUsedCategory) ?></h4>
                <p>Most Used</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h4><?= date('Y') ?></h4>
                <p>Current Year</p>
            </div>
            <div class="stat-icon">
                <i class="fas fa-calendar"></i>
            </div>
        </div>
    </div>

    <!-- Upload Card -->
    <div class="upload-card">
        <div class="upload-header">
            <i class="fas fa-cloud-upload-alt"></i>
            <span>Upload New Document</span>
            <span class="header-stats">
                <i class="fas fa-info-circle me-1"></i>
                Max size: 10MB | Allowed: PDF, DOC, DOCX, JPG, PNG
            </span>
        </div>
        <div class="form-body">
            <?php if (isset($_GET['uploaded']) && $_GET['uploaded'] == 1): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4">
                    <i class="fas fa-check-circle me-2"></i> 
                    <strong>Success!</strong> File uploaded successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4">
                    <i class="fas fa-check-circle me-2"></i> 
                    <strong>Success!</strong> File deleted successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i> 
                    <strong>Error!</strong> <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post" action="?page=upload_file" enctype="multipart/form-data" class="row g-4" id="uploadForm">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">
                        <i class="fas fa-file"></i> Select File
                    </label>
                    <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to browse or drag & drop</p>
                        <small class="text-muted">PDF, DOC, DOCX, JPG, PNG (Max 10MB)</small>
                        <input type="file" name="file" id="fileInput" class="d-none" required>
                    </div>
                    <div id="fileName" class="mt-2 small text-muted"></div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label">
                        <i class="fas fa-tag"></i> Category
                    </label>
                    <select name="category" class="form-select" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($cats as $c): ?>
                            <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label">
                        <i class="fas fa-building"></i> Court
                    </label>
                    <select name="court_id" id="courtSelect" class="form-select" <?= ($_SESSION['user']['role'] !== 'admin') ? 'disabled' : '' ?> required>
                        <option value="">-- Select Court --</option>
                        <?php foreach ($courts as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (isset($_SESSION['user']['court_id']) && $_SESSION['user']['court_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($_SESSION['user']['role'] !== 'admin'): ?>
                        <input type="hidden" name="court_id" value="<?= htmlspecialchars($_SESSION['user']['court_id'] ?? '') ?>">
                    <?php endif; ?>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label">
                        <i class="fas fa-user-tie"></i> Employee (Optional)
                    </label>
                    <select name="emp_detail_id" id="employeeSelect" class="form-select">
                        <option value="">-- Select Employee --</option>
                    </select>
                    <small class="text-muted">Associate file with specific employee</small>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn-upload" id="submitBtn">
                        <i class="fas fa-upload"></i> Upload Document
                        <span class="spinner-border spinner-border-sm d-none" id="uploadSpinner"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filter-section">
        <div class="filter-title">
            <i class="fas fa-filter"></i>
            <span>Filter Documents</span>
        </div>
        <form method="get" class="row g-3">
            <input type="hidden" name="page" value="files">
            
            <div class="col-md-5">
                <label class="form-label">
                    <i class="fas fa-search"></i> Search by Employee
                </label>
                <input type="text" name="search_name" class="form-control" 
                       placeholder="Enter employee name..." 
                       value="<?= htmlspecialchars($_GET['search_name'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-building"></i> Filter by Court
                </label>
                <select name="filter_court" class="form-select">
                    <option value="">All Courts</option>
                    <?php foreach ($courts as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($filter_court ?? 0) == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn-filter flex-fill">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="?page=files" class="btn-clear flex-fill">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>

        <?php if (!empty($_GET['search_name']) || !empty($_GET['filter_court'])): ?>
            <div class="mt-3 pt-2 border-top">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Active filters: 
                    <?php if (!empty($_GET['search_name'])): ?>
                        <span class="badge bg-info me-1">Search: "<?= htmlspecialchars($_GET['search_name']) ?>"</span>
                    <?php endif; ?>
                    <?php if (!empty($_GET['filter_court'])): ?>
                        <span class="badge bg-info me-1">Court filter applied</span>
                    <?php endif; ?>
                </small>
            </div>
        <?php endif; ?>
    </div>

    <!-- Files List -->
    <div class="files-table-card">
        <div class="table-header">
            <span>
                <i class="fas fa-folder-open me-2"></i>
                Document Repository
            </span>
            <span class="badge">
                <i class="fas fa-file me-1"></i> <?= $totalFiles ?> Total Files
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th width="30%">File Name</th>
                        <th width="10%">Category</th>
                        <th width="15%">Employee</th>
                        <th width="15%">Uploaded By</th>
                        <th width="15%">Date</th>
                        <th width="15%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <h5>No Documents Found</h5>
                            <p class="text-muted">Upload your first document using the form above to get started.</p>
                            <?php if (!empty($_GET['search_name']) || !empty($_GET['filter_court'])): ?>
                                <a href="?page=files" class="btn btn-outline-primary mt-3">
                                    <i class="fas fa-times me-2"></i>Clear Filters
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($rows as $f): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-file-alt fa-lg" style="color: var(--primary-color);"></i>
                                <div>
                                    <strong><?= htmlspecialchars($f['file_name']) ?></strong>
                                    <?php if (strlen($f['file_name']) > 40): ?>
                                        <br><small class="text-muted"><?= substr(htmlspecialchars($f['file_name']), 0, 40) ?>...</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="file-badge">
                                <i class="fas fa-tag"></i>
                                <?= htmlspecialchars($f['category']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($f['employee_name'])): ?>
                                <span class="d-flex align-items-center gap-1">
                                    <i class="fas fa-user-tie text-muted"></i>
                                    <?= htmlspecialchars($f['employee_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="d-flex align-items-center gap-1">
                                <i class="fas fa-user-circle text-muted"></i>
                                <?= htmlspecialchars($f['owner_name']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="d-flex align-items-center gap-1">
                                <i class="fas fa-calendar-alt text-muted"></i>
                                <?= date('d M Y', strtotime($f['created_at'])) ?>
                            </span>
                            <br>
                            <small class="text-muted"><?= date('h:i A', strtotime($f['created_at'])) ?></small>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="?page=download&id=<?= $f['id'] ?>" 
                                   class="btn btn-action btn-download" 
                                   title="Download File">
                                    <i class="fas fa-download"></i>
                                    <span class="d-none d-md-inline">Download</span>
                                </a>
                                
                                <?php if ($_SESSION['user']['role'] === 'admin' || $_SESSION['user']['id'] == $f['owner_id']): ?>
                                <a href="?page=delete_file&id=<?= $f['id'] ?>"
                                   onclick="return confirmDelete('<?= htmlspecialchars($f['file_name']) ?>')"
                                   class="btn btn-action btn-delete ms-1"
                                   title="Delete File">
                                    <i class="fas fa-trash"></i>
                                    <span class="d-none d-md-inline">Delete</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if (!empty($rows)): ?>
        <?php
        $totalPages = ceil($total / $perPage);
        if ($totalPages > 1): 
        ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <div class="text-muted small">
                <i class="fas fa-file me-1"></i>
                Showing <?= (($pageNum - 1) * $perPage) + 1 ?> to 
                <?= min($pageNum * $perPage, $total) ?> of <?= $total ?> entries
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination mb-0">
                    <li class="page-item <?= ($pageNum <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=files&p=<?= $pageNum - 1 ?>&search_name=<?= urlencode($_GET['search_name'] ?? '') ?>&filter_court=<?= $filter_court ?? '' ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php
                    $start = max(1, $pageNum - 2);
                    $end = min($totalPages, $pageNum + 2);
                    
                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=files&p=1&search_name=' . urlencode($_GET['search_name'] ?? '') . '&filter_court=' . ($filter_court ?? '') . '">1</a></li>';
                        if ($start > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($p = $start; $p <= $end; $p++): 
                    ?>
                    <li class="page-item <?= ($p == $pageNum) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=files&p=<?= $p ?>&search_name=<?= urlencode($_GET['search_name'] ?? '') ?>&filter_court=<?= $filter_court ?? '' ?>">
                            <?= $p ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=files&p=<?= $totalPages ?>&search_name=<?= urlencode($_GET['search_name'] ?? '') ?>&filter_court=<?= $filter_court ?? '' ?>">
                                <?= $totalPages ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="page-item <?= ($pageNum >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=files&p=<?= $pageNum + 1 ?>&search_name=<?= urlencode($_GET['search_name'] ?? '') ?>&filter_court=<?= $filter_court ?? '' ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Loading Spinner -->
<div class="spinner-overlay" id="loadingSpinner">
    <div class="spinner"></div>
</div>

<script>
// File input handling
document.getElementById('fileInput').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name;
    const fileSize = e.target.files[0]?.size;
    const fileNameDiv = document.getElementById('fileName');
    
    if (fileName) {
        const sizeInMB = (fileSize / (1024 * 1024)).toFixed(2);
        fileNameDiv.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i>Selected: ${fileName} (${sizeInMB} MB)`;
        
        // Validate file size (10MB max)
        if (fileSize > 10 * 1024 * 1024) {
            alert('File size must be less than 10MB');
            e.target.value = '';
            fileNameDiv.innerHTML = '';
        }
    } else {
        fileNameDiv.innerHTML = '';
    }
});

// Drag and drop functionality
const uploadArea = document.querySelector('.file-upload-area');
const fileInput = document.getElementById('fileInput');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    uploadArea.addEventListener(eventName, () => {
        uploadArea.style.borderColor = 'var(--primary-color)';
        uploadArea.style.background = '#e9ecef';
    });
});

['dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, () => {
        uploadArea.style.borderColor = '#cbd5e0';
        uploadArea.style.background = '#f1f5f9';
    });
});

uploadArea.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    fileInput.files = files;
    
    // Trigger change event
    const event = new Event('change', { bubbles: true });
    fileInput.dispatchEvent(event);
});

// Employee loading
document.getElementById('courtSelect').addEventListener('change', function() {
    const courtId = this.value;
    const empSelect = document.getElementById('employeeSelect');
    
    // Show loading state
    empSelect.innerHTML = '<option value="">Loading employees...</option>';
    empSelect.disabled = true;

    if (!courtId) {
        empSelect.innerHTML = '<option value="">-- Select Court First --</option>';
        empSelect.disabled = false;
        return;
    }

    fetch(`?page=ajax_employees&court_id=${courtId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            empSelect.innerHTML = '<option value="">-- Select Employee (Optional) --</option>';
            data.forEach(emp => {
                const opt = document.createElement('option');
                opt.value = emp.id;
                opt.textContent = emp.name;
                empSelect.appendChild(opt);
            });
            empSelect.disabled = false;
        })
        .catch(err => {
            console.error('Error loading employees:', err);
            empSelect.innerHTML = '<option value="">Error loading employees</option>';
            empSelect.disabled = false;
        });
});

// Load employees on page load if court is pre-selected
window.addEventListener('load', function() {
    const courtSelect = document.getElementById('courtSelect');
    if (courtSelect && courtSelect.value) {
        courtSelect.dispatchEvent(new Event('change'));
    }
});

// Form submission with loading spinner
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('fileInput');
    if (!fileInput.files.length) {
        e.preventDefault();
        alert('Please select a file to upload');
        return;
    }
    
    // Show loading spinner
    document.getElementById('loadingSpinner').classList.add('show');
    
    // Disable submit button
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.querySelector('.spinner-border').classList.remove('d-none');
});

// Delete confirmation with filename
function confirmDelete(fileName) {
    return confirm(`Are you sure you want to delete "${fileName}"?\nThis action cannot be undone.`);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + F to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.querySelector('input[name="search_name"]').focus();
    }
    
    // Ctrl/Cmd + U to focus upload
    if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
        e.preventDefault();
        document.getElementById('fileInput').click();
    }
});

// Tooltips initialization (if using Bootstrap)
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});
</script>

<?php include __DIR__ . '/footer.php'; ?>