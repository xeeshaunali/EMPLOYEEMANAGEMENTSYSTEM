<?php include __DIR__ . '/header.php'; ?>

<div class="card">
  <div class="card-body">
    <h5>Create Users</h5>

    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="alert alert-info"><?= htmlspecialchars($_SESSION['flash']) ?></div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <form method="post" action="?page=save_employee" class="row g-2 mb-3">
      <input type="hidden" name="id" id="emp_id" value="">

      <div class="col-md-3">
        <input class="form-control" name="name" id="emp_name" placeholder="Name" required>
      </div>

      <div class="col-md-2">
        <input class="form-control" name="username" id="emp_username" placeholder="Username" required>
      </div>

      <div class="col-md-2">
        <select class="form-control" name="role" required>
          <?php
            // $roles should be provided by controller
            $roles = $roles ?? ['employee','reader','librarian','admin'];
            foreach ($roles as $r) {
                echo '<option value="'.htmlspecialchars($r).'">'.htmlspecialchars(ucfirst($r)).'</option>';
            }
          ?>
        </select>
      </div>

      <div class="col-md-2">
        <select class="form-control" name="court_id">
          <option value="">-- Court --</option>
          <?php foreach($courts as $ct): ?>
            <option value="<?= (int)$ct['id'] ?>"><?=htmlspecialchars($ct['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <select class="form-control" name="post_bps" required>
          <option value="">-- Select Post & BPS --</option>
          <?php foreach($posts as $p): ?>
            <option value="<?= htmlspecialchars($p['post_name']) ?>|<?= (int)$p['bps'] ?>">
              <?= htmlspecialchars($p['post_name']) ?> (BPS-<?= (int)$p['bps'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-1">
        <button class="btn btn-success">Add</button>
      </div>
    </form>

    <table class="table table-sm">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Username</th>
          <th>Role</th>
          <th>Court</th>
          <th>BPS</th>
          <th>Post</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($employees as $e): ?>
        <tr>
          <td><?= (int)$e['id'] ?></td>
          <td><?= htmlspecialchars($e['name']) ?></td>
          <td><?= htmlspecialchars($e['username']) ?></td>
          <td><?= htmlspecialchars($e['role']) ?></td>
          <td><?= htmlspecialchars($e['court_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($e['bps'] ?? '') ?></td>
          <td><?= htmlspecialchars($e['post'] ?? '') ?></td>
          <td>
            <?php if (isset($_SESSION['user']['id']) && (int)$_SESSION['user']['id'] === (int)$e['id']): ?>
              <span class="text-muted">You</span>
            <?php else: ?>
              <a href="?page=delete_employee&id=<?= urlencode($e['id'])?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?');">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
