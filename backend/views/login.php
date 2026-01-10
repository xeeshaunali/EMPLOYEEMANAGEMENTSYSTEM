<?php include __DIR__ . '/header.php'; ?>

<style>
    /* Card Styling */
    .login-card {
        max-width: 450px;
        margin: 2rem auto;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        background: #fff;
    }
    .login-card .card-header {
        background: linear-gradient(90deg, #005566, #007bff);
        border-radius: 20px 20px 20px 20px;
        padding: 0.3rem;
        text-align: center;
    }
    .login-card .card-title {
        color: #fff;
        font-weight: 200;
        margin-bottom: 0;
    }
    .login-card .card-body {
        padding: 1rem;
    }

    /* Alerts */
    .alert {
        border-radius: 8px;
        animation: fadeIn 0.3s ease-in;
        margin-bottom: 1.5rem;
        padding: 0.75rem;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Form Styling */
    .form-control {
        border-radius: 5px;
        border: 1px solid #ced4da;
        font-size: 0.9rem;
        transition: border-color 0.2s ease;
        padding-left: 2.5rem;
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
    .input-group {
        position: relative;
    }
    .input-group .bi {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        font-size: 1rem;
        z-index: 10;
    }
    .btn-primary {
        font-weight: 500;
        border-radius: 5px;
        padding: 0.5rem 1.5rem;
        transition: background-color 0.2s ease, transform 0.1s ease;
        width: 100%;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
    }

    /* Responsive Adjustments */
    @media (max-width: 576px) {
        .login-card {
            margin: 1rem;
        }
        .login-card .card-body {
            padding: 1.5rem;
        }
        .form-control {
            font-size: 0.85rem;
        }
    }

    /* Accessibility */
    .form-control:focus, .btn-primary:focus {
        outline: 2px solid #b3bfccff;
        outline-offset: 2px;
    }
</style>

<div class="row justify-content-center">
    <div class="col-md-6 col-sm-8 col-10">
        <div class="card login-card border-0">            
            <div class="card-header">
                <h5 >
                    <img src="../assets/images/DC-LOGO.png" alt="logo" class="img-fluid w-50 shadow ronded">
                </h5>                
                <h5 class="card-title">EMS / COURT Management</h5><br>
                <h5 class="card-title">Login</h5>
            </div>
            <div class="card-body">
                <?php if(isset($_GET['err'])): ?>
                    <div class="alert alert-danger">Invalid credentials</div>
                <?php endif; ?>
                <form method="post" action="?page=do_login">
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-group">
                            <i class="bi bi-person-fill"></i>
                            <input class="form-control" id="username" name="username" required aria-label="Username">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-group">
                            <i class="bi bi-lock-fill"></i>
                            <input type="password" class="form-control" id="password" name="password" required aria-label="Password">
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>