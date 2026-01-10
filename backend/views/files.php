<?php include __DIR__ . '/header.php'; ?>

<style>
    .page-title {
        background: linear-gradient(135deg, #005566, #007bff);
        color: white;
        padding: 2.5rem 1.5rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        text-align: center;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .page-title h3 {
        margin: 0;
        font-weight: 700;
        font-size: 2rem;
        letter-spacing: 0.5px;
    }
    .page-title small {
        opacity: 0.9;
        font-size: 1.1rem;
        font-weight: 400;
    }

    .upload-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .upload-header {
        background: linear-gradient(90deg, #005566, #007bff);
        color: white;
        padding: 1.5rem;
        font-size: 1.4rem;
        font-weight: 600;
        text-align: center;
    }

    .form-body {
        padding: 2rem;
    }
    .form-label {
        font-weight: 600;
        color: #333;
        font-size: 1rem;
        margin-bottom: 0.6rem;
    }
    .form-control, .form-select {
        border-radius: 10px;
        padding: 0.8rem 1rem;
        font-size: 1rem;
        border: 1px solid #ced4da;
        transition: all 0.3s ease;
    }
    .form-control:focus, .form-select:focus {
        border-color: #005566;
        box-shadow: 0 0 0 0.25rem rgba(0, 85, 102, 0.2);
    }
    .btn-upload {
        background: linear-gradient(90deg, #28a745, #20c997);
        border: none;
        border-radius: 12px;
        padding: 1rem 2rem;
        font-size: 1.1rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s;
    }
    .btn-upload:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(40,167,69,0.4);
    }

    .files-table-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 2rem;
    }
    .table-header {
        background: linear-gradient(90deg, #005566, #007bff);
        color: white;
        padding: 1.5rem;
        font-size: 1.4rem;
        font-weight: 600;
    }
    .table {
        margin: 0;
        font-size: 0.95rem;
    }
    .table thead {
        background: #f1f3f5;
        font-weight: 600;
        color: #333;
    }
    .table tbody tr:hover {
        background-color: #f8fdff !important;
        transition: 0.3s;
    }
    .table .btn-sm {
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
    .btn-outline-primary {
        border-color: #007bff;
        color: #007bff;
    }
    .btn-outline-primary:hover {
        background: #007bff;
        color: white;
    }
    .btn-outline-danger {
        border-color: #dc3545;
        color: #dc3545;
    }
    .btn-outline-danger:hover {
        background: #dc3545;
        color: white;
    }

    .filter-form {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        border: 1px solid #e9ecef;
    }

    @media (max-width: 768px) {
        .page-title h3 { font-size: 1.6rem; }
        .upload-header, .table-header { font-size: 1.2rem; padding: 1rem; }
        .form-body { padding: 1.5rem; }
        .btn-upload { width: 100%; }
    }
</style>

<div class="container-fluid mt-4">
    <!-- <div class="page-title">
        <h5>Court Management System</h5>
        <small>Upload, Organize & Access Court Documents Securely</small>
    </div> -->

    <!-- Upload Section -->
    <div class="upload-card">
        <div class="upload-header">
            <i class="fas fa-cloud-upload-alt me-2"></i> Upload Staff Record
        </div>
        <div class="form-body">
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4">
                    <i class="fas fa-check-circle me-2"></i> File deleted successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post" action="?page=upload_file" enctype="multipart/form-data" class="row g-4 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label">Select File</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select" required>
                        <?php foreach ($cats as $c): ?>
                            <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label">Court</label>
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
                <div class="col-lg-6 col-md-6">
                    <label class="form-label">Employee (Optional)</label>
                    <select name="emp_detail_id" id="employeeSelect" class="form-select">
                        <option value="">-- Select Employee --</option>
                    </select>
                </div>
                <div class="col-lg-6 col-md-12">
                    <button type="submit" class="btn btn-upload w-100">
                        <i class="fas fa-upload me-2"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-form">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="files">
            <div class="col-md-5">
                <input type="text" name="search_name" class="form-control" placeholder="Search by Employee Name..." value="<?= htmlspecialchars($_GET['search_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <select name="filter_court" class="form-select">
                    <option value="">All Courts</option>
                    <?php foreach ($courts as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($filter_court ?? 0) == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill">
                    <i class="fas fa-filter me-1"></i> Apply Filter
                </button>
                <a href="?page=files" class="btn btn-outline-secondary flex-fill">Clear</a>
            </div>
        </form>
    </div>

    <!-- Files List -->
    <div class="files-table-card">
        <div class="table-header">
            <i class="fas fa-folder-open me-2"></i> Uploaded Files
        </div>
        <div class="table-responsive p-3">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>File Name</th>
                        <th>Category</th>
                        <th>Employee</th>
                        <!-- <th>Court</th> -->
                        <th>Uploaded By</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fas fa-folder-open fa-4x mb-4 opacity-50"></i>
                            <br><strong>No files found</strong>
                            <br><small>Upload your first document to get started</small>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($rows as $f): ?>
                    <tr>
                        <td class="fw-medium"><?= htmlspecialchars($f['file_name']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($f['category']) ?></span></td>
                        <td><?= htmlspecialchars($f['employee_name'] ?? '—') ?></td>

                        <!-- Commented Out but works -->
                        <!-- <td><?= htmlspecialchars($f['court_name'] ?? '—') ?></td> -->

                        <td><?= htmlspecialchars($f['owner_name']) ?></td>
                        <td><?= date('d M Y', strtotime($f['created_at'])) ?></td>
                        <td>
                            <a href="?page=download&id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary" title="Download">Download
                                <i class="fas fa-download"></i>
                            </a>
                            <?php if ($_SESSION['user']['role'] === 'admin' || $_SESSION['user']['id'] == $f['owner_id']): ?>
                            <a href="?page=delete_file&id=<?= $f['id'] ?>"
                               onclick="return confirm('Delete this file permanently?')"
                               class="btn btn-sm btn-outline-danger ms-1" title="Delete">Delete
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php
        $totalPages = ceil($total / $perPage);
        if ($totalPages > 1): ?>
        <nav aria-label="Page navigation" class="p-3 bg-light">
            <ul class="pagination justify-content-center mb-0">
                <?php for($p=1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= ($p == $pageNum) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=files&p=<?= $p ?>&search_name=<?= urlencode($_GET['search_name'] ?? '') ?>&filter_court=<?= $filter_court ?? '' ?>">
                        <?= $p ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Dynamic Employee Loading Script -->
<script>
document.getElementById('courtSelect').addEventListener('change', function() {
    const courtId = this.value;
    const empSelect = document.getElementById('employeeSelect');
    empSelect.innerHTML = '<option value="">-- Loading... --</option>';

    if (!courtId) {
        empSelect.innerHTML = '<option value="">-- Select Court First --</option>';
        return;
    }

    fetch(`?page=ajax_employees&court_id=${courtId}`)
        .then(response => response.json())
        .then(data => {
            empSelect.innerHTML = '<option value="">-- Select Employee (Optional) --</option>';
            data.forEach(emp => {
                const opt = document.createElement('option');
                opt.value = emp.id;
                opt.textContent = emp.name;
                empSelect.appendChild(opt);
            });
        })
        .catch(err => {
            console.error('Error loading employees:', err);
            empSelect.innerHTML = '<option value="">Error loading employees</option>';
        });
});

// Load employees on page load if court is pre-selected (non-admin users)
window.addEventListener('load', function() {
    const courtSelect = document.getElementById('courtSelect');
    if (courtSelect && courtSelect.value) {
        courtSelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php include __DIR__ . '/footer.php'; ?>