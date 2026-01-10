<?php include __DIR__ . '/header.php'; ?>
<div class="card">
  <div class="card-body">
    <h5>Talukas</h5>
    <form method="post" action="?page=save_taluka" class="row g-2 mb-3">
      <div class="col-md-4">
        <input class="form-control" name="name" placeholder="Taluka Name" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-success">Add Taluka</button>
      </div>
    </form>

    <table class="table table-sm">
      <thead>
        <tr>
          <th>ID</th>
          <th>Taluka Name</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($talukas as $t): ?>
        <tr>
          <td><?=$t['id']?></td>
          <td><?=htmlspecialchars($t['name'])?></td>
          <td>
            <a href="?page=delete_taluka&id=<?=$t['id']?>" class="btn btn-sm btn-danger">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
