<?php
include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php';

$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'admin') {
    echo "<div class='alert alert-danger'>Access denied</div>";
    include __DIR__ . '/footer.php';
    exit;
}

// Fetch posts for dropdown
$posts = $pdo->query("SELECT id, post_name FROM posts ORDER BY post_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch courts for dropdown
$courts = $pdo->query("SELECT id, name FROM courts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for dashboard
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total_employees,
        COUNT(DISTINCT court_id) as total_courts,
        COUNT(DISTINCT post_id) as total_posts
    FROM employee_details
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Build search query
$searchResults = [];
$searchPerformed = false;

if ($_GET && (!empty($_GET['search_emp']) || !empty($_GET['court_id']) || !empty($_GET['post_id']))) {
    $searchPerformed = true;
    $where = [];
    $params = [];

    if (!empty($_GET['search_emp'])) {
        $where[] = "(ed.name LIKE :search OR ed.father_name LIKE :search OR ed.cnic LIKE :search 
                     OR p.post_name LIKE :search OR c.name LIKE :search)";
        $params[':search'] = "%" . trim($_GET['search_emp']) . "%";
    }
    if (!empty($_GET['court_id'])) {
        $where[] = "ed.court_id = :court";
        $params[':court'] = (int)$_GET['court_id'];
    }
    if (!empty($_GET['post_id'])) {
        $where[] = "ed.post_id = :post";
        $params[':post'] = (int)$_GET['post_id'];
    }

    $sql = "
        SELECT ed.id, ed.name, ed.father_name, ed.cnic, ed.bps, ed.pic,
               p.post_name, c.name AS court_name,
               ed.date_of_birth, ed.date_of_appointment, ed.date_of_retirement
        FROM employee_details ed
        JOIN posts p ON p.id = ed.post_id
        JOIN courts c ON c.id = ed.court_id
    ";
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY ed.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get today's date for calculations
$today = new DateTime();
?>

<style>
    :root {
        --primary-color: #005566;
        --secondary-color: #007bff;
        --success-color: #28a745;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
    }

    .page-title {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .stats-card {
        background: white;
        border-radius: 10px;
        padding: 1.2rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border-left: 4px solid var(--primary-color);
        transition: transform 0.3s;
    }
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.1);
    }
    .stats-number {
        font-size: 2rem;
        font-weight: bold;
        color: var(--primary-color);
        line-height: 1;
    }
    .stats-label {
        color: #666;
        font-size: 0.9rem;
        margin-top: 0.3rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .search-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        margin-bottom: 2rem;
    }
    .search-header {
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 1rem 1.5rem;
        font-weight: 600;
    }
    .search-header i {
        margin-right: 10px;
    }

    .filter-badge {
        background: #e9ecef;
        color: #495057;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        margin-right: 0.5rem;
        margin-bottom: 0.5rem;
    }
    .filter-badge .remove-filter {
        margin-left: 0.5rem;
        color: #dc3545;
        cursor: pointer;
        font-weight: bold;
    }
    .filter-badge .remove-filter:hover {
        color: #a71d2a;
    }

    .table-container {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .table {
        margin-bottom: 0;
    }
    .table thead th {
        background: linear-gradient(90deg, #f8f9fa, #e9ecef);
        color: var(--primary-color);
        font-weight: 600;
        border-bottom: 2px solid var(--primary-color);
        padding: 1rem;
    }
    .table tbody tr {
        transition: background-color 0.2s;
    }
    .table tbody tr:hover {
        background-color: #f1f8ff !important;
    }
    .table td {
        padding: 1rem;
        vertical-align: middle;
    }

    .employee-pic {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--primary-color);
        cursor: pointer;
        transition: transform 0.3s;
    }
    .employee-pic:hover {
        transform: scale(1.1);
    }

    .no-pic {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
        font-size: 0.7rem;
        text-align: center;
        border: 2px dashed #dee2e6;
    }

    .btn-profile {
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        color: white;
        border: none;
        padding: 0.4rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        transition: all 0.3s;
    }
    .btn-profile:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,123,255,0.3);
        color: white;
    }

    .search-input-group {
        position: relative;
    }
    .search-input-group i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
    }
    .search-input-group input {
        padding-left: 40px;
    }

    .clear-filters {
        color: #dc3545;
        text-decoration: none;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        margin-left: 1rem;
    }
    .clear-filters:hover {
        color: #a71d2a;
        text-decoration: underline;
    }

    .result-count {
        background: var(--primary-color);
        color: white;
        padding: 0.3rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        display: inline-block;
    }

    .badge-retirement {
        background: #ffc107;
        color: #856404;
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
        border-radius: 12px;
        margin-left: 0.5rem;
    }
</style>

<div class="container-fluid">
    <!-- Page Title -->
    <div class="page-title">
        <h3 class="mb-0"><i class="bi bi-search me-2"></i>Employee Search</h3>
        <small class="opacity-75">Advanced employee directory with powerful search filters</small>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-number"><?= number_format($stats['total_employees']) ?></div>
                <div class="stats-label">Total Employees</div>
                <small class="text-muted">Active in database</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-number"><?= number_format($stats['total_courts']) ?></div>
                <div class="stats-label">Courts</div>
                <small class="text-muted">With assigned employees</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-number"><?= number_format($stats['total_posts']) ?></div>
                <div class="stats-label">Posts/Designations</div>
                <small class="text-muted">Different positions</small>
            </div>
        </div>
    </div>

    <!-- Search Card -->
    <div class="search-card">
        <div class="search-header">
            <i class="bi bi-funnel"></i> Search Filters
            <?php if ($searchPerformed): ?>
                <span class="result-count ms-3">
                    <i class="bi bi-people me-1"></i> <?= count($searchResults) ?> result(s) found
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body p-4">
            <form method="get" class="row g-3" id="searchForm">
                <input type="hidden" name="page" value="employee_search">

                <!-- Search text -->
                <div class="col-md-4">
                    <label class="form-label fw-bold">🔍 Keyword Search</label>
                    <div class="search-input-group">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search_emp" 
                               value="<?= htmlspecialchars($_GET['search_emp'] ?? '') ?>"
                               class="form-control" 
                               placeholder="Search by name, father name, CNIC, post, court...">
                    </div>
                </div>

                <!-- Court filter -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">🏛️ Court</label>
                    <select name="court_id" class="form-select">
                        <option value="">-- All Courts --</option>
                        <?php foreach ($courts as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($_GET['court_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Post filter -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">📋 Post</label>
                    <select name="post_id" class="form-select">
                        <option value="">-- All Posts --</option>
                        <?php foreach ($posts as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($_GET['post_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['post_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-2"></i>Search
                    </button>
                </div>
            </form>

            <!-- Active Filters Display -->
            <?php if (!empty($_GET['search_emp']) || !empty($_GET['court_id']) || !empty($_GET['post_id'])): ?>
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex align-items-center flex-wrap">
                        <span class="me-2 text-muted"><i class="bi bi-funnel-fill"></i> Active filters:</span>
                        <?php if (!empty($_GET['search_emp'])): ?>
                            <span class="filter-badge">
                                <i class="bi bi-search"></i> "<?= htmlspecialchars($_GET['search_emp']) ?>"
                                <a href="?page=employee_search&court_id=<?= urlencode($_GET['court_id'] ?? '') ?>&post_id=<?= urlencode($_GET['post_id'] ?? '') ?>" class="remove-filter" title="Remove">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($_GET['court_id'])): 
                            $courtName = '';
                            foreach ($courts as $c) {
                                if ($c['id'] == $_GET['court_id']) {
                                    $courtName = $c['name'];
                                    break;
                                }
                            }
                        ?>
                            <span class="filter-badge">
                                <i class="bi bi-building"></i> <?= htmlspecialchars($courtName) ?>
                                <a href="?page=employee_search&search_emp=<?= urlencode($_GET['search_emp'] ?? '') ?>&post_id=<?= urlencode($_GET['post_id'] ?? '') ?>" class="remove-filter" title="Remove">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($_GET['post_id'])): 
                            $postName = '';
                            foreach ($posts as $p) {
                                if ($p['id'] == $_GET['post_id']) {
                                    $postName = $p['post_name'];
                                    break;
                                }
                            }
                        ?>
                            <span class="filter-badge">
                                <i class="bi bi-briefcase"></i> <?= htmlspecialchars($postName) ?>
                                <a href="?page=employee_search&search_emp=<?= urlencode($_GET['search_emp'] ?? '') ?>&court_id=<?= urlencode($_GET['court_id'] ?? '') ?>" class="remove-filter" title="Remove">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            </span>
                        <?php endif; ?>
                        <a href="?page=employee_search" class="clear-filters ms-auto">
                            <i class="bi bi-x-circle me-1"></i> Clear All Filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Results Section -->
    <?php if ($searchPerformed): ?>
        <div class="table-container">
            <?php if (empty($searchResults)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-search display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">No employees found</h4>
                    <p class="text-muted">Try adjusting your search filters or clear them to see all employees.</p>
                    <a href="?page=employee_search" class="btn btn-outline-primary mt-2">
                        <i class="bi bi-arrow-repeat me-2"></i>Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="bi bi-people-fill me-2" style="color: var(--primary-color);"></i>
                        Search Results
                        <span class="badge bg-secondary ms-2"><?= count($searchResults) ?></span>
                    </h5>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="exportToExcel()">
                            <i class="bi bi-file-excel me-1"></i> Export
                        </button>
                        <button class="btn btn-sm btn-outline-primary ms-2" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Father's Name</th>
                                <th>Post</th>
                                <th>Court</th>
                                <th>BPS</th>
                                <th>CNIC</th>
                                <th>Age/Retirement</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($searchResults as $e): 
                                // Calculate age and retirement info
                                $age = null;
                                $retirementStatus = '';
                                $retirementClass = '';
                                
                                if (!empty($e['date_of_birth'])) {
                                    $dob = new DateTime($e['date_of_birth']);
                                    $age = $dob->diff($today)->y;
                                }
                                
                                if (!empty($e['date_of_retirement'])) {
                                    $retirementDate = new DateTime($e['date_of_retirement']);
                                    $daysToRetirement = $today->diff($retirementDate)->days;
                                    
                                    if ($retirementDate < $today) {
                                        $retirementStatus = 'Retired';
                                        $retirementClass = 'badge bg-danger';
                                    } elseif ($daysToRetirement <= 365) {
                                        $retirementStatus = 'Retiring soon';
                                        $retirementClass = 'badge bg-warning text-dark';
                                    } else {
                                        $retirementStatus = 'Active';
                                        $retirementClass = 'badge bg-success';
                                    }
                                }
                            ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($e['pic'])): ?>
                                            <img src="<?= '../uploads/employees/' . htmlspecialchars($e['pic']) ?>" 
                                                 alt="Profile" class="employee-pic"
                                                 data-bs-toggle="modal" data-bs-target="#picModal"
                                                 onclick="showPic('../uploads/employees/<?= htmlspecialchars($e['pic']) ?>')"
                                                 title="Click to enlarge">
                                        <?php else: ?>
                                            <div class="no-pic">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($e['name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($e['father_name']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($e['post_name']) ?>
                                        <?php if (!empty($e['bps'])): ?>
                                            <br><small class="text-muted">BPS-<?= htmlspecialchars($e['bps']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($e['court_name']) ?></td>
                                    <td><span class="badge bg-info">BPS-<?= htmlspecialchars($e['bps']) ?></span></td>
                                    <td>
                                        <?php if (!empty($e['cnic'])): ?>
                                            <?= htmlspecialchars($e['cnic']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($age): ?>
                                            <span class="badge bg-secondary"><?= $age ?> years</span>
                                        <?php endif; ?>
                                        <?php if ($retirementStatus): ?>
                                            <span class="<?= $retirementClass ?>"><?= $retirementStatus ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a class="btn btn-sm btn-profile" 
                                               href="?page=employee_profile&id=<?= (int)$e['id'] ?>"
                                               title="View Full Profile">
                                                <i class="bi bi-person-badge me-1"></i> Profile
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="quickView(<?= $e['id'] ?>)">
                                                        <i class="bi bi-eye me-2"></i>Quick View
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="printEmployee(<?= $e['id'] ?>)">
                                                        <i class="bi bi-printer me-2"></i>Print Details
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Results Summary -->
                <div class="mt-3 text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Showing <?= count($searchResults) ?> employee(s) • 
                    <a href="#" onclick="exportToExcel()" class="text-decoration-none">Export to Excel</a> • 
                    <a href="#" onclick="window.print()" class="text-decoration-none">Print</a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Initial State - No Search Performed -->
        <div class="table-container text-center py-5">
            <i class="bi bi-search display-1 text-muted"></i>
            <h4 class="mt-3 text-muted">Start Your Search</h4>
            <p class="text-muted mb-4">Use the filters above to find employees by name, court, post, or other criteria.</p>
            <div class="row justify-content-center">
                <div class="col-md-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <i class="bi bi-person display-4" style="color: var(--primary-color);"></i>
                            <h6>Search by Name</h6>
                            <small class="text-muted">Employee name or father's name</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <i class="bi bi-building display-4" style="color: var(--secondary-color);"></i>
                            <h6>Filter by Court</h6>
                            <small class="text-muted">Select specific court</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <i class="bi bi-briefcase display-4" style="color: var(--success-color);"></i>
                            <h6>Filter by Post</h6>
                            <small class="text-muted">Choose designation</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Bootstrap Modal for full pic -->
<div class="modal fade" id="picModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="picModalImg" src="" class="img-fluid rounded" style="max-height: 80vh;">
            </div>
        </div>
    </div>
</div>

<!-- Quick View Modal -->
<div class="modal fade" id="quickViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Employee Quick View</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="quickViewContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
function showPic(src) {
    document.getElementById("picModalImg").src = src;
}

function quickView(employeeId) {
    // You can implement AJAX call here to load employee details
    // For now, redirect to profile page
    window.location.href = '?page=employee_profile&id=' + employeeId;
}

function printEmployee(employeeId) {
    // Open profile in print-friendly mode
    window.open('?page=employee_profile&id=' + employeeId + '&print=1', '_blank');
}

function exportToExcel() {
    // Get the table
    var table = document.querySelector('.table').cloneNode(true);
    
    // Remove action buttons column (last column)
    var rows = table.querySelectorAll('tr');
    rows.forEach(row => {
        var cells = row.querySelectorAll('td, th');
        if (cells.length > 0) {
            cells[cells.length - 1].remove(); // Remove last cell (actions)
        }
    });
    
    // Remove image column (first column)
    rows.forEach(row => {
        var cells = row.querySelectorAll('td, th');
        if (cells.length > 0) {
            cells[0].remove(); // Remove first cell (photo)
        }
    });
    
    // Convert to CSV
    var csv = [];
    rows.forEach(row => {
        var rowData = [];
        var cells = row.querySelectorAll('td, th');
        cells.forEach(cell => {
            // Clean the text - remove HTML tags and extra spaces
            var data = cell.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/\s+/g, ' ').trim();
            // Escape quotes
            data = data.replace(/"/g, '""');
            // Wrap in quotes
            rowData.push('"' + data + '"');
        });
        csv.push(rowData.join(','));
    });
    
    // Download CSV
    var csvContent = csv.join('\n');
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    var url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'employee_search_' + new Date().toISOString().slice(0,10) + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Auto-submit form when select changes (optional - remove if you don't want auto-submit)
document.querySelectorAll('select[name="court_id"], select[name="post_id"]').forEach(select => {
    select.addEventListener('change', function() {
        document.getElementById('searchForm').submit();
    });
});

// Add keyboard shortcut (Ctrl/Cmd + Enter) to search
document.getElementById('searchForm').addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        this.submit();
    }
});
</script>

<?php include __DIR__ . '/footer.php'; ?>