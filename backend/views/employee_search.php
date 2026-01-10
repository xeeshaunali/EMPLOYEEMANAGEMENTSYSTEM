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

// Build search query
$searchResults = [];
if ($_GET) {
    $where = [];
    $params = [];

    if (!empty($_GET['search_emp'])) {
        $where[] = "(ed.name LIKE :t OR ed.father_name LIKE :t OR ed.cnic LIKE :t 
                     OR p.post_name LIKE :t OR c.name LIKE :t)";
        $params[':t'] = "%" . trim($_GET['search_emp']) . "%";
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
               p.post_name, c.name AS court_name
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
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-dark text-white">
        <h6 class="mb-0">🔍 Employee Search</h6>
    </div>
    <div class="card-body">
        <form method="get" class="row g-2 mb-3" id="searchForm">
            <input type="hidden" name="page" value="employee_search">

            <!-- Search text -->
            <div class="col-md-4">
                <input type="text" name="search_emp" value="<?= htmlspecialchars($_GET['search_emp'] ?? '') ?>"
                       class="form-control" placeholder="Search by name, father name, CNIC">
            </div>

            <!-- Court filter -->
            <div class="col-md-3">
                <select name="court_id" class="form-select" onchange="document.getElementById('searchForm').submit()">
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
                <select name="post_id" class="form-select" onchange="document.getElementById('searchForm').submit()">
                    <option value="">-- All Posts --</option>
                    <?php foreach ($posts as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($_GET['post_id'] ?? '') == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['post_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Button -->
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>
        </form>

        <?php if ($_GET): ?>
            <?php if (empty($searchResults)): ?>
                <div class="alert alert-warning">No employees found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Pic</th>
                                <th>Name</th>
                                <th>Father Name</th>
                                <th>Post</th>
                                <th>Court</th>
                                <th>BPS</th>
                                <th>CNIC</th>
                                <th style="width:120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($searchResults as $e): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($e['pic'])): ?>
                                            <img src="<?= '../uploads/employees/' . htmlspecialchars($e['pic']) ?>" 
                                                 alt="Pic" width="40" style="cursor:pointer"
                                                 data-bs-toggle="modal" data-bs-target="#picModal"
                                                 onclick="showPic('../uploads/employees/<?= htmlspecialchars($e['pic']) ?>')">
                                        <?php else: ?>
                                            <span class="text-muted">No Pic</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($e['name']) ?></td>
                                    <td><?= htmlspecialchars($e['father_name']) ?></td>
                                    <td><?= htmlspecialchars($e['post_name']) ?></td>
                                    <td><?= htmlspecialchars($e['court_name']) ?></td>
                                    <td><?= htmlspecialchars($e['bps']) ?></td>
                                    <td><?= htmlspecialchars($e['cnic']) ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="?page=employee_profile&id=<?= (int)$e['id'] ?>">
                                           Profile
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Bootstrap Modal for full pic -->
<div class="modal fade" id="picModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-body text-center">
        <img id="picModalImg" src="" class="img-fluid rounded">
      </div>
    </div>
  </div>
</div>

<script>
function showPic(src) {
    document.getElementById("picModalImg").src = src;
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
