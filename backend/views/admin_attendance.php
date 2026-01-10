<?php include __DIR__ . '/header.php'; ?>

<div class="card"><div class="card-body">

  <h5>Attendance</h5>

  <form method="post" action="?page=mark_attendance" class="row g-2 mb-3">

    <div class="col-md-4"><select class="form-control" name="employee_id">

      <?php foreach($employees as $em): ?> <option value="<?=$em['id']?>"><?=htmlspecialchars($em['name'])?></option> <?php endforeach; ?>
    </select>

    </div>

    <div class="col-md-3">
      <input type="date" name="date" class="form-control" value="<?=date('Y-m-d')?>">
    </div>

    <div class="col-md-3">
      <select name="status" class="form-control"><option>Present</option><option>Absent</option><option>Leave</option></select>
    </div>

    <div class="col-md-2">
      <button class="btn btn-primary">Mark</button>
    </div>


  </form>
  
  <table class="table table-sm">

    <thead><tr><th>Date</th><th>Employee</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach($att as $a): ?>
      <tr><td><?=htmlspecialchars($a['date'])?></td><td><?=htmlspecialchars($a['name'])?></td><td><?=htmlspecialchars($a['status'])?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
<?php include __DIR__ . '/footer.php'; ?>