<?php
class FileCategoryController {
    public static function listCategories($pdo) {
        $categories = $pdo->query("SELECT * FROM file_categories ORDER BY name ASC")->fetchAll();
        include __DIR__ . '/../views/file_categories.php';
    }

    public static function saveCategory($pdo) {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') die('Access denied');
        
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { header("Location: ?page=file_categories&err=1"); exit; }

        $stmt = $pdo->prepare("INSERT INTO file_categories (name) VALUES (?)");
        try {
            $stmt->execute([$name]);
        } catch (PDOException $e) {
            header("Location: ?page=file_categories&err=2"); exit;
        }
        header("Location: ?page=file_categories&success=1");
    }

    public static function deleteCategory($pdo) {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') die('Access denied');

        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM file_categories WHERE id = ?");
            $stmt->execute([$id]);
        }
        header("Location: ?page=file_categories&deleted=1");
    }
}
?>
