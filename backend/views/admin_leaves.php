<?php
include __DIR__ . '/header.php';
require_once __DIR__ . '/../config/db.php';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: ?page=login');
    exit;
}

$isAdmin = ($user['role'] ?? '') === 'admin';

// Fetch all leave requests
$stmt = $pdo->prepare("
    SELECT l.id, e.name AS emp_name, l.leave_type, l.start_date, l.end_date, l.status
    FROM leaves l
    JOIN employees e ON l.employee_id = e.id
    ORDER BY l.start_date DESC
");
$stmt->execute();
$leaves = $stmt->fetchAll();

// Handle delete for admin
if ($isAdmin && isset($_POST['delete_leave'])) {
    $leave_id = (int)$_POST['leave_id'];
    $del = $pdo->prepare("DELETE FROM leaves WHERE id = ? LIMIT 1");
    $del->execute([$leave_id]);
    echo "<div class='alert alert-success'>Leave deleted successfully.</div>";
}
?>

<div class="card">
    <div class="card-body">
        <h5>Leave Requests</h5>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th>Action</th>
                    <?php if ($isAdmin): ?><th>Delete</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaves as $l): ?>
                <tr>
                    <td><?= $l['id'] ?></td>
                    <td><?= htmlspecialchars($l['emp_name']) ?></td>
                    <td><?= htmlspecialchars($l['leave_type']) ?></td>
                    <td><?= htmlspecialchars($l['start_date']) ?> to <?= htmlspecialchars($l['end_date']) ?></td>
                    <td><?= htmlspecialchars($l['status']) ?></td>
                    <td>
                        <a href="?page=approve_leave&id=<?= $l['id'] ?>&s=approved" class="btn btn-sm btn-success">Approve</a>
                        <a href="?page=approve_leave&id=<?= $l['id'] ?>&s=rejected" class="btn btn-sm btn-danger">Reject</a>
                    </td>
                    <?php if ($isAdmin): ?>
                    <td>
                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this leave request?')">
                            <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                            <button type="submit" name="delete_leave" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
