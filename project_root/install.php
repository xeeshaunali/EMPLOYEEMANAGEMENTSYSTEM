<?php
// Run this file once via browser to install DB and create sample users.
// Adjust DB credentials in backend/config/db.php before running if needed.
$cfg = require __DIR__ . '/../backend/config/db.php';
$host = $cfg['host']; $dbname = $cfg['dbname']; $user = $cfg['user']; $pass = $cfg['pass'];

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Create tables
    $sql = file_get_contents(__DIR__ . '/sql/schema.sql');

    // We will execute statements, but skip the initial CREATE DATABASE/USE lines if present
    $parts = preg_split('/;\s*\n/', $sql);
    foreach($parts as $stmt){
        $s = trim($stmt);
        if(!$s) continue;
        // Skip CREATE DATABASE or USE lines since we already handled DB
        if(preg_match('/^CREATE DATABASE/i', $s)) continue;
        if(preg_match('/^USE\s+/i', $s)) continue;
        $pdo->exec($s);
    }

    // Insert sample users with proper password_hash
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    // Clean up any existing sample users
    $pdo->exec("DELETE FROM employees WHERE username IN ('admin','reader','emp1')");
    $stmt = $pdo->prepare("INSERT INTO employees (name,username,password_hash,role,court_id,bps,post,contact,cnic,joining_date) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute(['Super Admin','admin',$hash,'admin',1,'BPS-21','Administrator','0123456789','12345-6789012-3','2020-01-01']);
    $stmt->execute(['Reader User','reader',$hash,'reader',1,'BPS-17','Reader','0123456789','12345-6789012-4','2021-05-01']);
    $stmt->execute(['Sample Employee','emp1',$hash,'employee',2,'BPS-05','Clerk','0123456789','12345-6789012-5','2022-03-01']);

    echo "Installation complete. Sample users created (password123). Delete install.php after use for security.";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
