<?php include __DIR__ . '/header.php'; ?>
<div class="card shadow-sm border-0">
  <div class="card-header bg-primary text-white">
    <h5 class="mb-0">Manage File Categories</h5>
  </div>
  <div class="card-body">

    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">Category added successfully.</div>
    <?php elseif (isset($_GET['deleted'])): ?>
      <div class="alert alert-success">Category deleted successfully.</div>
    <?php elseif (isset($_GET['err']) && $_GET['err'] == 1): ?>
      <div class="alert alert-danger">Category name cannot be empty.</div>
    <?php elseif (isset($_GET['err']) && $_GET['err'] == 2): ?>
      <div class="alert alert-danger">Category already exists.</div>
    <?php endif; ?>

    <form method="post" action="?page=save_file_category" class="row g-2 mb-3">
      <div class="col-md-6">
        <input type="text" name="name" placeholder="Category Name" class="form-control" required>
      </div>
      <div class="col-md-3">
        <button class="btn btn-success w-100">Add Category</button>
      </div>
    </form>

    <table class="table table-bordered">
      <thead>
        <tr>
          <th>ID</th>
          <th>Category Name</th>
          <th>Created At</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $cat): ?>
          <tr>
            <td><?= $cat['id'] ?></td>
            <td><?= htmlspecialchars($cat['name']) ?></td>
            <td><?= $cat['created_at'] ?></td>
            <td>
              <a href="?page=delete_file_category&id=<?= $cat['id'] ?>" 
                 class="btn btn-sm btn-danger" 
                 onclick="return confirm('Delete this category?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
