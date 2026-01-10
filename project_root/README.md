# Court Employee Management System - Test App

## Quick start (XAMPP / Laragon / PHP environment)

1. Copy the `project_root` folder into your web server root directory (e.g., `htdocs` for XAMPP).
   - Full path in this zip: `full_app/project_root`

2. Create the MySQL database and tables:
   - Import `project_root/sql/schema.sql` into your MySQL server (e.g., using phpMyAdmin or MySQL CLI).
   - After import, you must replace the `REPLACE_HASH` placeholders with actual password hashes for the sample users.

3. Generate password hashes for the initial users (password: `password123`):
   - You can generate a hash using PHP CLI: `<?php echo password_hash('password123', PASSWORD_DEFAULT); ?>`
   - Or use the provided PHP helper file `generate_hash.php` (run via CLI or browser) to get the hash, then update the `employees` table and set the `password_hash` values for `admin`, `reader`, and `emp1`.

4. Configure DB connection:
   - Edit `backend/config/db.php` to match your MySQL credentials (host, dbname, user, pass).

5. Ensure `uploads/` folder is writable by web server (permissions 755 or 775):
   - Path: `full_app/uploads`

6. Open the app in your browser:
   - `http://localhost/full_app/project_root/index.php` (or `/?page=login`)

## Features included in this test build

- User login (Admin, Reader, Employee) via `employees` table (view `users`).
- Admin pages: Manage courts, manage employees, view leave requests, mark attendance.
- Employee: Apply for leaves, view leaves.
- Simple file upload and download with court-level access controls.
- PDO prepared statements, password hashing.

## Notes & Next steps

- This is a minimal functional prototype meant for testing and development.
- For production you should add CSRF tokens, input validation sanitization, stronger file checks, role enforcement on every action, and use a proper routing framework.
- If you want, I can now replace `REPLACE_HASH` in `sql/schema.sql` with real hashes (I can compute them with PHP here), and also pre-populate the database automatically. Would you like me to do that and recreate the zip?

