<?php include __DIR__ . '/header.php'; ?>
<div class="container mt-4">
    <h2>Reset User Password</h2>
    <form action="?page=do_reset_password" method="post">
        <div class="mb-3">
            <label class="form-label">Select User</label>
            <select name="user_id" class="form-control" required>
                <?php
                // Fetch all users except current admin
                $stmt = $pdo->prepare("SELECT id, name, username FROM employees WHERE id != ?");
                $stmt->execute([$_SESSION['user']['id']]);
                while ($u = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <option value="<?= htmlspecialchars($u['id']) ?>">
                        <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['username']) ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-danger">Reset Password</button>
    </form>
</div>
