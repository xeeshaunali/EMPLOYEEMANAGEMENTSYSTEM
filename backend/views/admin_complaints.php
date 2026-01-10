<?php
include __DIR__ . '/header.php';
$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'admin') { echo 'Access denied'; exit; }
?>
<div class="container mt-4">
    <h4>Complaints & Requests Management</h4>
    <p class="text-muted">Mark items resolved or reopen. Sorted by newest first.</p>

    <?php if (!empty($complaints)): ?>
    <table class="table table-sm table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Employee</th>
                <th>Subject</th>
                <th>Message</th>
                <th>Status</th>
                <th>Created</th>
                <th>Resolved At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($complaints as $c): ?>
            <tr>
                <td><?= (int)$c['id'] ?></td>
                <td><?= htmlspecialchars($c['employee_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($c['subject']) ?></td>
                <td><?= htmlspecialchars(mb_strimwidth($c['message'],0,140,'...')) ?></td>
                <td><?= htmlspecialchars(ucfirst($c['status'])) ?></td>
                <td><?= htmlspecialchars($c['created_at']) ?></td>
                <td><?= htmlspecialchars($c['resolved_at'] ?? '') ?></td>
                <td>
                    <form method="post" action="?page=update_complaint" style="display:inline">
                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        <?php if ($c['status'] !== 'resolved'): ?>
                            <button type="submit" name="action" value="resolve" class="btn btn-sm btn-success">Resolve</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="reopen" class="btn btn-sm btn-warning">Reopen</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="alert alert-info">No complaints found.</div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/footer.php'; ?>
