<?php
class PasswordController {

    // User changes their own password
    public static function changePassword($pdo) {
        if (!isset($_SESSION['user'])) die('Access denied');

        $id = $_SESSION['user']['id'];
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm) {
            die('Passwords do not match.');
        }

        // Get current password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM employees WHERE id=?");
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            die('Current password is incorrect.');
        }

        // Save new password
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE employees SET password_hash=? WHERE id=?")->execute([$newHash, $id]);

        header("Location: ?page=dashboard&msg=password_changed");
    }

    // Admin resets another user's password
    public static function resetPassword($pdo) {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            die('Access denied');
        }

        $user_id = $_POST['user_id'] ?? 0;
        $new = $_POST['new_password'] ?? '';

        if (!$user_id || !$new) die('Missing fields.');

        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE employees SET password_hash=? WHERE id=?")->execute([$newHash, $user_id]);

        header("Location: ?page=employees&msg=password_reset");
    }
}
?>
