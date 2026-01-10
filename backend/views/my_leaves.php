<?php 
include __DIR__ . '/header.php'; 

// ✅ Fetch leave types dynamically from leave_types table (admin-managed)
$types = $pdo->query("SELECT name FROM leave_types ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="card">
  <div class="card-body">
    <h5 class="mb-3">My Leaves</h5>

    <!-- Apply Leave Form -->
    <form method="post" action="?page=apply_leave" class="row g-2 mb-4 align-items-end">
      
      <!-- Leave Type Dropdown -->
      <div class="col-md-3">
        <label class="form-label">Leave Type</label>
        <select class="form-select" name="type" required>
          <option value="">-- Select Type --</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Start Date -->
      <div class="col-md-3">
        <label class="form-label">Start Date</label>
        <input type="date" name="start_date" class="form-control" required>
      </div>

      <!-- End Date -->
      <div class="col-md-3">
        <label class="form-label">End Date</label>
        <input type="date" name="end_date" class="form-control" required>
      </div>

      <!-- Remarks -->
      <div class="col-md-2">
        <label class="form-label">Remarks</label>
        <input type="text" name="remarks" class="form-control" placeholder="Optional">
      </div>

      <!-- Submit -->
      <div class="col-md-1 d-grid">
        <button class="btn btn-success">Apply</button>
      </div>
    </form>

    <!-- Leaves Table -->
    <table class="table table-bordered table-striped">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Type</th>
          <th>Dates</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="4" class="text-center">No leaves found</td>
          </tr>
        <?php else: ?>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><?= htmlspecialchars($r['leave_type']) ?></td>
              <td><?= htmlspecialchars($r['start_date']) ?> to <?= htmlspecialchars($r['end_date']) ?></td>
              <td>
                <?php if ($r['status'] === 'approved'): ?>
                  <span class="badge bg-success">Approved</span>
                <?php elseif ($r['status'] === 'pending'): ?>
                  <span class="badge bg-warning text-dark">Pending</span>
                <?php else: ?>
                  <span class="badge bg-danger">Rejected</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
