<?php
// backend/controllers/LibraryController.php
class LibraryController {

    private static function _isLibAdmin() {
        if (!isset($_SESSION['user'])) return false;
        $r = $_SESSION['user']['role'] ?? '';
        return in_array($r, ['admin','librarian'], true);
    }

    public static function index(PDO $pdo) {
        if (!self::_isLibAdmin()) { echo 'Access denied'; return; }
        $categories = LibraryModel::getCategories($pdo);
        $books = LibraryModel::getBooks($pdo);
        $loans = LibraryModel::getOpenLoans($pdo);
        $courts = LibraryModel::getCourts($pdo); // Updated from getEmployees()
        include __DIR__ . '/../views/library.php';
    }

    public static function saveCategory(PDO $pdo) {
        if (!self::_isLibAdmin()) { echo 'Access denied'; return; }
        $name = trim($_POST['name'] ?? '');
        $year = isset($_POST['year']) && $_POST['year'] !== '' ? (int)$_POST['year'] : null;
        if ($name === '') {
            $_SESSION['flash'] = 'Category name required';
            header('Location: ?page=library'); exit;
        }
        LibraryModel::saveCategory($pdo, $name, $year);
        $_SESSION['flash'] = 'Category saved';
        header('Location: ?page=library'); exit;
    }

    public static function deleteCategory(PDO $pdo) {
        if (!self::_isLibAdmin()) { echo 'Access denied'; return; }
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { header('Location: ?page=library'); exit; }
        $ok = LibraryModel::deleteCategory($pdo, $id);
        $_SESSION['flash'] = $ok ? 'Category deleted' : 'Category in use; cannot delete';
        header('Location: ?page=library'); exit;
    }

    public static function saveBook(PDO $pdo) {
        if (!self::_isLibAdmin()) { echo 'Access denied'; return; }
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'id' => $id,
            'title' => trim($_POST['title'] ?? ''),
            'author' => trim($_POST['author'] ?? ''),
            'isbn' => trim($_POST['isbn'] ?? ''),
            'category_id' => ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null,
            'rack_no' => trim($_POST['rack_no'] ?? ''),
            'total_qty' => max(1, (int)($_POST['total_qty'] ?? 1)),
            'publisher' => trim($_POST['publisher'] ?? ''),
            'edition' => trim($_POST['edition'] ?? ''),
            'published_year' => ($_POST['published_year'] ?? '') !== '' ? (int)$_POST['published_year'] : null,
            'price' => ($_POST['price'] ?? '') !== '' ? (float)$_POST['price'] : null,
            'language' => trim($_POST['language'] ?? ''),
            'acquisition_date' => $_POST['acquisition_date'] ?? null,
            'vendor' => trim($_POST['vendor'] ?? '')
        ];

        // File upload (if present)
        if (!empty($_FILES['book_file']) && $_FILES['book_file']['error'] === UPLOAD_ERR_OK) {
            $up = self::handleUpload('book_file');
            if ($up) {
                $data['file_path'] = $up['path'];
                $data['file_name'] = $up['name'];
            }
        }

        $uploader = $_SESSION['user']['id'] ?? null;
        $ok = LibraryModel::saveBook($pdo, $data, $uploader);
        $_SESSION['flash'] = $ok ? ($id ? 'Book updated' : 'Book added') : 'Could not save book';
        header('Location: ?page=library'); exit;
    }

    public static function deleteBook(PDO $pdo) {
        if (!self::_isLibAdmin()) { echo 'Access denied'; return; }
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { header('Location: ?page=library'); exit; }

        // fetch file_path and delete file if exists
        $book = LibraryModel::getBook($pdo, $id);
        if ($book && !empty($book['file_path']) && is_file($book['file_path'])) @unlink($book['file_path']);
        LibraryModel::deleteBook($pdo, $id);
        $_SESSION['flash'] = 'Book deleted';
        header('Location: ?page=library'); exit;
    }

    public static function issueBook(PDO $pdo) {
        if (!self::_isLibAdmin()) { echo 'Access denied'; return; }
        $book_id = (int)($_POST['book_id'] ?? 0);
        $borrower_id = (int)($_POST['borrower_id'] ?? 0);
        $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
        $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
        $issuer_id = $_SESSION['user']['id'] ?? 0;
        if (!$book_id || !$borrower_id) {
            $_SESSION['flash'] = 'Select book and borrower';
            header('Location: ?page=library'); exit;
        }
        $ok = LibraryModel::issueBook($pdo, $book_id, $borrower_id, $issuer_id, $issue_date, $due_date);
        $_SESSION['flash'] = $ok ? 'Book issued' : 'Issue failed (no copies available)';
        header('Location: ?page=library'); exit;
    }

    public static function returnBook(PDO $pdo) {
        if (!self::_isLibAdmin()) { echo 'Access denied'; return; }
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        if (!$loan_id) { header('Location: ?page=library'); exit; }
        $ok = LibraryModel::returnBook($pdo, $loan_id, date('Y-m-d'));
        $_SESSION['flash'] = $ok ? 'Book returned' : 'Return failed';
        header('Location: ?page=library'); exit;
    }

    public static function download(PDO $pdo) {
        if (!self::_isLibAdmin()) { echo 'Access denied'; return; }
        $id = (int)($_GET['id'] ?? 0);
        $book = LibraryModel::getBook($pdo, $id);
        if (!$book || empty($book['file_path']) || !is_file($book['file_path'])) {
            echo 'File not found'; return;
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($book['file_name'] ?: $book['file_path']) . '"');
        header('Content-Length: ' . filesize($book['file_path']));
        readfile($book['file_path']);
        exit;
    }

    // helper upload
    private static function handleUpload(string $field) {
        if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
        $orig = $_FILES[$field]['name'];
        $tmp = $_FILES[$field]['tmp_name'];
        $base = realpath(__DIR__ . '/../../uploads') ?: (__DIR__ . '/../../uploads');
        if (!is_dir($base)) @mkdir($base, 0777, true);
        $dir = $base . '/Library';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $safe = preg_replace('/[^A-Za-z0-9\.\-_]+/', '_', $orig);
        $dest = $dir . '/' . time() . '_' . $safe;
        if (!move_uploaded_file($tmp, $dest)) return null;
        return ['path'=>$dest, 'name'=>$orig];
    }
}