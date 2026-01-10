<?php
class AuthController {
    public static function doLogin($pdo) {
        $u = trim($_POST['username'] ?? '');
        $p = trim($_POST['password'] ?? '');

        if (!$u || !$p) {
            header('Location: ?page=login&err=1');
            exit;
        }

        // Query the actual table 'employees' instead of the view 'users'
        $stmt = $pdo->prepare('SELECT id, name, username, password_hash, role, court_id, bps, post, contact, cnic, joining_date, created_at 
                               FROM employees 
                               WHERE username = ? 
                               LIMIT 1');
        $stmt->execute([$u]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($p, $user['password_hash'])) {
            // Remove password from session for security
            unset($user['password_hash']);
            
            // Store user in session
            $_SESSION['user'] = $user;
            
            header('Location: ?page=dashboard');
            exit;
        }

        // Invalid credentials
        header('Location: ?page=login&err=1');
        exit;
    }

    public static function logout() {
        session_destroy();
        header('Location: ?page=login');
        exit;
    }
}
?>