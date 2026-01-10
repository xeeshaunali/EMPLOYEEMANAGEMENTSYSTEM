<?php
session_start();
require __DIR__ . '/../config/constants.php';
$pdo = require __DIR__ . '/../config/pdo.php';

// Restrict access to admin only
$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'admin') {
    echo "<div class='alert alert-danger'>Access denied</div>";
    exit;
}

$issuesFound = false;
$messages = [];

try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $stmt = $pdo->query("CHECK TABLE `$table`");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $msg = $row['Msg_text'];
            $status = strtoupper($msg);

            if ($status === "OK") {
                $messages[] = "<div class='alert alert-success mb-2'>✅ <b>$table</b> is healthy</div>";
            } else {
                $issuesFound = true;
                $messages[] = "<div class='alert alert-danger mb-2'>❌ <b>$table</b> issue: $msg</div>";

                // Try auto-repair if MyISAM crash
                if (strpos($msg, 'crashed') !== false) {
                    try {
                        $pdo->query("REPAIR TABLE `$table`");
                        $messages[] = "<div class='alert alert-warning mb-2'>🔧 Repair attempted for <b>$table</b></div>";
                    } catch (Exception $e) {
                        $messages[] = "<div class='alert alert-dark mb-2'>⚠️ Repair failed: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    $messages[] = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    $issuesFound = true;
}
?>

<div class="card shadow-sm border-0">
  <div class="card-header bg-primary text-white">
    <h5 class="mb-0">🩺 Database Health Check</h5>
  </div>
  <div class="card-body">
    <?= implode("", $messages) ?>
    <hr>
    <?php if ($issuesFound): ?>
      <div class="alert alert-danger"><b>⚠️ Warning:</b> Some issues found. Please check and take backup immediately.</div>
    <?php else: ?>
      <div class="alert alert-success"><b>✅ All tables are healthy.</b></div>
    <?php endif; ?>
  </div>
</div>
