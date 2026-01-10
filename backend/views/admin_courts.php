<?php include __DIR__ . '/header.php'; ?>

<style>
    /* Card Styling */
    .courts-card {
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        background: #fff;
        margin: 1rem 0;
    }
    .courts-card .card-header {
        background: linear-gradient(90deg, #005566, #007bff);
        border-radius: 8px 8px 0 0;
        padding: 1rem;
        text-align: center;
    }
    .courts-card .card-title {
        color: #fff;
        font-weight: 600;
        margin-bottom: 0;
        font-size: 1.25rem;
    }
    .courts-card .card-body {
        padding: 1.5rem;
    }

    /* Form Styling */
    .form-control {
        border-radius: 5px;
        border: 1px solid #ced4da;
        font-size: 0.9rem;
        transition: border-color 0.2s ease;
    }
    .form-control:focus {
        border-color: #005566;
        box-shadow: 0 0 0 0.2rem rgba(0, 85, 102, 0.25);
    }
    .form-label {
        font-size: 0.85rem;
        color: #333;
        margin-bottom: 0.25rem;
    }
    .btn-sm {
        font-weight: 500;
        border-radius: 5px;
        padding: 0.4rem 1rem;
        transition: background-color 0.2s ease, transform 0.1s ease;
    }
    .btn-success:hover, .btn-primary:hover, .btn-danger:hover {
        transform: translateY(-2px);
    }

    /* Table Styling */
    .table {
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .table thead {
        background: #f8f9fa;
        color: #333;
    }
    .table th, .table td {
        border: 1px solid #dee2e6;
        padding: 0.75rem;
        vertical-align: middle;
    }
    .table tr:hover {
        background: #f1f3f5;
    }

    /* Responsive Adjustments */
    @media (max-width: 767.98px) {
        .courts-card .card-body {
            padding: 1rem;
        }
        .form-control {
            font-size: 0.85rem;
        }
        .table {
            font-size: 0.85rem;
        }
        .table th, .table td {
            padding: 0.5rem;
        }
        .row.g-2 > .col-md-4, .row.g-2 > .col-md-2 {
            margin-bottom: 0.5rem;
        }
    }

    /* Accessibility */
    .form-control:focus, .btn-sm:focus {
        outline: 2px solid #007bff;
        outline-offset: 2px;
    }
</style>

<div class="container">
    <div class="courts-card shadow-sm border-0">
        <div class="card-header">
            <h5 class="card-title">Courts</h5>
        </div>
        <div class="card-body">
            <!-- Form -->
            <form method="post" action="?page=save_court" class="row g-2 mb-4">
                <input type="hidden" name="id" id="court_id">

                <div class="col-md-4 col-12">
                    <label class="form-label" for="court_name">Court Name</label>
                    <input class="form-control" name="name" id="court_name" placeholder="Court name" required aria-label="Court Name">
                </div>

                <div class="col-md-4 col-12">
                    <label class="form-label" for="court_district">District</label>
                    <input class="form-control" name="district" id="court_district" placeholder="District" required aria-label="District">
                </div>

                <div class="col-md-4 col-12">
                    <label class="form-label" for="court_taluka">Taluka</label>
                    <input class="form-control" name="court_taluka" id="court_taluka" placeholder="Taluka" aria-label="Taluka">
                </div>

                <div class="col-md-2 col-12 d-flex align-items-end">
                    <button class="btn btn-success btn-sm" type="submit" aria-label="Save Court">Save</button>
                </div>
            </form>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>District</th>
                            <th>Taluka</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($courts as $c): ?>
                        <tr>
                            <td><?= $c['id'] ?></td>
                            <td><?= htmlspecialchars($c['name']) ?></td>
                            <td><?= htmlspecialchars($c['district']) ?></td>
                            <td><?= htmlspecialchars($c['taluka']) ?></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-primary" 
                                   onclick="editCourt(<?= $c['id'] ?>,'<?= addslashes($c['name']) ?>','<?= addslashes($c['district']) ?>','<?= addslashes($c['taluka']) ?>')"
                                   aria-label="Edit Court">
                                    Edit
                                </a>
                                <a href="?page=delete_court&id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Delete this court?')" aria-label="Delete Court">
                                    Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editCourt(id, name, district, taluka) {
    document.getElementById('court_id').value = id;
    document.getElementById('court_name').value = name;
    document.getElementById('court_district').value = district;
    document.getElementById('court_taluka').value = taluka;
}
</script>

<?php include __DIR__ . '/footer.php'; ?>