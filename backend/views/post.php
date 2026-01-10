<?php
// Start session + DB
if (!isset($_SESSION)) session_start();
require_once __DIR__ . '/../config/db.php';

// Get highest serial_no for auto-fill
$lastSerial = $pdo->query("SELECT MAX(serial_no) FROM posts")->fetchColumn();
$nextSerial = $lastSerial ? $lastSerial + 1 : 1;

// If editing, get post details
$editPost = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $postId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $editPost = $stmt->fetch();
}

// Delete post BEFORE header.php
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $postId = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    header('Location: ?page=posts&msg=deleted');
    exit;
}

// Add or Update post BEFORE header.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_name'], $_POST['bps'], $_POST['sanctioned_strength'], $_POST['working_strength'], $_POST['serial_no'], $_POST['court_name'])) {
    $serial_no = intval($_POST['serial_no']);
    $postName = trim($_POST['post_name']);
    $bps = intval($_POST['bps']);
    $sanctioned = intval($_POST['sanctioned_strength']);
    $working = intval($_POST['working_strength']);
    $courtName = trim($_POST['court_name']);
    $id = intval($_POST['id'] ?? 0);

    if ($postName && $bps > 0 && $sanctioned >= 0 && $working >= 0 && $serial_no > 0 && $courtName) {
        if ($id > 0) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE posts SET serial_no=?, post_name=?, bps=?, sanctioned_strength=?, working_strength=?, court_name=? WHERE id=?");
            $stmt->execute([$serial_no, $postName, $bps, $sanctioned, $working, $courtName, $id]);
            header('Location: ?page=posts&msg=updated');
            exit;
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO posts (serial_no, post_name, bps, sanctioned_strength, working_strength, court_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$serial_no, $postName, $bps, $sanctioned, $working, $courtName]);
            header('Location: ?page=posts&msg=added');
            exit;
        }
    } else {
        $errorMsg = 'Please enter valid data for all fields.';
    }
}

// ✅ Now include header.php (safe — no headers needed after this point)
include __DIR__ . '/header.php';

// Success messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') echo '<div class="alert alert-success">Post added successfully!</div>';
    if ($_GET['msg'] === 'updated') echo '<div class="alert alert-success">Post updated successfully!</div>';
    if ($_GET['msg'] === 'deleted') echo '<div class="alert alert-success">Post deleted successfully!</div>';
}
if (!empty($errorMsg)) {
    echo '<div class="alert alert-danger">'.htmlspecialchars($errorMsg).'</div>';
}

// Fetch all posts sorted by Serial No
$posts = $pdo->query("SELECT * FROM posts ORDER BY serial_no ASC")->fetchAll();
?>
<div class="container mt-4">
    <h4><?= $editPost ? 'Edit Post' : 'Manage Posts & BPS' ?></h4>
    <form method="post" class="row g-3 mb-3">
        <input type="hidden" name="id" value="<?= $editPost['id'] ?? '' ?>">
        <div class="col-md-1">
            <input type="number" name="serial_no" class="form-control" placeholder="Serial" required min="1" value="<?= htmlspecialchars($editPost['serial_no'] ?? $nextSerial) ?>">
        </div>
        <div class="col-md-2">
            <select name="court_name" class="form-control" required>
                <option value="">Select Court</option>
                <option value="District Court Jamshoro" <?= isset($editPost['court_name']) && $editPost['court_name'] === 'District Court Jamshoro' ? 'selected' : '' ?>>District Court Jamshoro</option>
                <option value="Consumer Protection Court" <?= isset($editPost['court_name']) && $editPost['court_name'] === 'Consumer Protection Court' ? 'selected' : '' ?>>Consumer Protection Court</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="post_name" class="form-control" placeholder="Post Name" required value="<?= htmlspecialchars($editPost['post_name'] ?? '') ?>">
        </div>
        <div class="col-md-1">
            <input type="number" name="bps" class="form-control" placeholder="BPS" required min="1" value="<?= htmlspecialchars($editPost['bps'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <input type="number" name="sanctioned_strength" class="form-control" placeholder="Sanctioned" required min="0" value="<?= htmlspecialchars($editPost['sanctioned_strength'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <input type="number" name="working_strength" class="form-control" placeholder="Working" required min="0" value="<?= htmlspecialchars($editPost['working_strength'] ?? '') ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-<?= $editPost ? 'success' : 'primary' ?> w-100">
                <?= $editPost ? 'Update' : 'Add' ?>
            </button>
        </div>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Serial No</th>
                <th>Court</th>
                <th>BPS</th>
                <th>Post</th>
                <th>Sanctioned</th>
                <th>Working</th>
                <th>Vacant</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($posts as $p): ?>
                <?php $vacant = max(0, $p['sanctioned_strength'] - $p['working_strength']); ?>
                <tr>
                    <td><?= htmlspecialchars($p['serial_no']) ?></td>
                    <td><?= htmlspecialchars($p['court_name']) ?></td>
                    <td><?= htmlspecialchars($p['bps']) ?></td>
                    <td><?= htmlspecialchars($p['post_name']) ?></td>
                    <td><?= htmlspecialchars($p['sanctioned_strength']) ?></td>
                    <td><?= htmlspecialchars($p['working_strength']) ?></td>
                    <td><?= $vacant ?></td>
                    <td>
                        <a href="?page=posts&edit=<?= $p['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="?page=posts&delete=<?= $p['id'] ?>" 
                           class="btn btn-sm btn-danger" 
                           onclick="return confirm('Are you sure you want to delete this post?');">
                           Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/footer.php'; ?>
