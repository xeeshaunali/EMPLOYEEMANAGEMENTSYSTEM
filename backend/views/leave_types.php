<?php
include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php';

if ($user['role'] !== 'admin') {
    echo "<div class='alert alert-danger'>Access denied!</div>";
    include __DIR__ . '/footer.php';
    exit;
}

// Add Leave Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $name = trim($_POST['name']);
    if ($name !== '') {
        $stmt = $pdo->prepare("INSERT INTO leave_types (name) VALUES (?)");
        try {
            $stmt->execute([$name]);
            echo "<div class='alert alert-success'>Leave type added successfully.</div>";
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Delete Leave Type
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->prepare("DELETE FROM leave_types WHERE id=?")->execute([$id]);
    echo "<div class='alert alert-warning'>Leave type deleted.</div>";
}

// Fetch All
$types = $pdo->query("SELECT * FROM leave_types ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card shadow-sm">
  <div class="card-body">
    <h5 class="mb-3"><i class="bi bi-list-check me-2"></i>Manage Leave Types</h5>

    <form method="post" class="row g-2 mb-3">
      <div class="col-md-6">
        <input type="text" name="name" class="form-control" placeholder="Enter new leave type" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add</button>
      </div>
    </form>

    <table class="table table-bordered table-striped">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Leave Type</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($types as $t): ?>
          <tr>
            <td><?= $t['id'] ?></td>
            <td><?= htmlspecialchars($t['name']) ?></td>
            <td>
              <a href="?page=leave_types&delete=<?= $t['id'] ?>" 
                 class="btn btn-sm btn-danger"
                 onclick="return confirm('Delete this leave type?')">
                 <i class="bi bi-trash"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
