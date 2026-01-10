<?php
/**
 * One-time safe migration for employee_details (MySQL 5.7 friendly).
 * - Creates table if missing.
 * - Adds any missing columns.
 * - Adds indexes if missing.
 * - Adds FK to courts if safe (no orphan rows).
 * - Adds FK to posts only if posts is InnoDB and safe; otherwise adds an index.
 * - Never drops data. Only widens bps to VARCHAR(20) if it’s smaller.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$base = dirname(__DIR__); // backend/
$pdo  = require $base . '/config/pdo.php';

function logln($msg){ echo htmlspecialchars($msg) . "<br>\n"; }

function tableExists(PDO $pdo, $table){
    $sql = "SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?";
    $st = $pdo->prepare($sql); $st->execute([$table]);
    return (bool)$st->fetchColumn();
}

function columnExists(PDO $pdo, $table, $column){
    $sql = "SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
    $st = $pdo->prepare($sql); $st->execute([$table, $column]);
    return (bool)$st->fetchColumn();
}

function indexOnColumnExists(PDO $pdo, $table, $column){
    $sql = "SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
    $st = $pdo->prepare($sql); $st->execute([$table, $column]);
    return (bool)$st->fetchColumn();
}

function fkExistsOnColumn(PDO $pdo, $table, $column){
    $sql = "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
              AND referenced_table_name IS NOT NULL";
    $st = $pdo->prepare($sql); $st->execute([$table, $column]);
    return (bool)$st->fetchColumn();
}

function getEngine(PDO $pdo, $table){
    $sql = "SELECT ENGINE FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?";
    $st = $pdo->prepare($sql); $st->execute([$table]);
    return $st->fetchColumn() ?: null;
}

function hasOrphans(PDO $pdo, $localTable, $localCol, $refTable, $refCol='id'){
    $sql = "SELECT COUNT(*) AS n
            FROM {$localTable} t
            LEFT JOIN {$refTable} r ON t.{$localCol} = r.{$refCol}
            WHERE t.{$localCol} IS NOT NULL AND r.{$refCol} IS NULL";
    return (int)$pdo->query($sql)->fetchColumn() > 0;
}

function getVarcharLength(PDO $pdo, $table, $column){
    $sql = "SELECT character_maximum_length
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name=? AND column_name=?";
    $st = $pdo->prepare($sql); $st->execute([$table, $column]);
    return (int)$st->fetchColumn();
}

logln("Running employee_details migration...");

$needCreate = !tableExists($pdo, 'employee_details');

if ($needCreate) {
    logln("employee_details not found. Creating table...");
    $pdo->exec("
        CREATE TABLE employee_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            father_name VARCHAR(150) NOT NULL,
            post_id INT NOT NULL,
            court_id INT NOT NULL,
            bps VARCHAR(20) NOT NULL,
            date_of_birth DATE NULL,
            date_of_appointment DATE NOT NULL,
            date_of_retirement DATE NOT NULL,
            cnic VARCHAR(20) NULL,
            pic VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    logln("Created employee_details.");
} else {
    logln("employee_details exists. Checking columns...");

    // Add missing columns (MySQL 5.7 => no IF NOT EXISTS; we check first)
    $adds = [];

    if (!columnExists($pdo, 'employee_details', 'name'))                 $adds[] = "ADD COLUMN name VARCHAR(150) NOT NULL";
    if (!columnExists($pdo, 'employee_details', 'father_name'))          $adds[] = "ADD COLUMN father_name VARCHAR(150) NOT NULL";
    if (!columnExists($pdo, 'employee_details', 'post_id'))              $adds[] = "ADD COLUMN post_id INT NOT NULL";
    if (!columnExists($pdo, 'employee_details', 'court_id'))             $adds[] = "ADD COLUMN court_id INT NOT NULL";
    if (!columnExists($pdo, 'employee_details', 'bps'))                  $adds[] = "ADD COLUMN bps VARCHAR(20) NOT NULL";
    if (!columnExists($pdo, 'employee_details', 'date_of_birth'))        $adds[] = "ADD COLUMN date_of_birth DATE NULL";
    if (!columnExists($pdo, 'employee_details', 'date_of_appointment'))  $adds[] = "ADD COLUMN date_of_appointment DATE NOT NULL";
    if (!columnExists($pdo, 'employee_details', 'date_of_retirement'))   $adds[] = "ADD COLUMN date_of_retirement DATE NOT NULL";
    if (!columnExists($pdo, 'employee_details', 'cnic'))                 $adds[] = "ADD COLUMN cnic VARCHAR(20) NULL";
    if (!columnExists($pdo, 'employee_details', 'pic'))                  $adds[] = "ADD COLUMN pic VARCHAR(255) NULL";
    if (!columnExists($pdo, 'employee_details', 'created_at'))           $adds[] = "ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    if (!columnExists($pdo, 'employee_details', 'updated_at'))           $adds[] = "ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";

    if ($adds){
        $sql = "ALTER TABLE employee_details " . implode(",\n", $adds) . ";";
        $pdo->exec($sql);
        logln("Added missing columns.");
    } else {
        logln("All required columns already present.");
    }

    // Widen bps to VARCHAR(20) if smaller (safe change)
    if (columnExists($pdo, 'employee_details', 'bps')) {
        $len = getVarcharLength($pdo, 'employee_details', 'bps');
        if ($len > 0 && $len < 20) {
            $pdo->exec("ALTER TABLE employee_details MODIFY COLUMN bps VARCHAR(20) NOT NULL;");
            logln("Widened bps to VARCHAR(20).");
        }
    }
}

// Ensure helpful indexes on FK columns (if no FK yet)
if (columnExists($pdo, 'employee_details', 'court_id') && !indexOnColumnExists($pdo, 'employee_details', 'court_id')) {
    // If there is a FK later, InnoDB will create/require an index anyway.
    $pdo->exec("ALTER TABLE employee_details ADD INDEX idx_employee_details_court_id (court_id);");
    logln("Added index on court_id.");
}
if (columnExists($pdo, 'employee_details', 'post_id') && !indexOnColumnExists($pdo, 'employee_details', 'post_id')) {
    $pdo->exec("ALTER TABLE employee_details ADD INDEX idx_employee_details_post_id (post_id);");
    logln("Added index on post_id.");
}

// Add FK to courts if safe and not already present
if (columnExists($pdo, 'employee_details', 'court_id') && !fkExistsOnColumn($pdo, 'employee_details', 'court_id')) {
    $courtsEngine = getEngine($pdo, 'courts');
    if (strtoupper($courtsEngine) === 'INNODB') {
        if (!hasOrphans($pdo, 'employee_details', 'court_id', 'courts')) {
            try {
                $pdo->exec("ALTER TABLE employee_details
                            ADD CONSTRAINT fk_employee_details_court
                            FOREIGN KEY (court_id) REFERENCES courts(id)
                            ON DELETE CASCADE;");
                logln("Added FK fk_employee_details_court.");
            } catch (Throwable $e) {
                logln("Skipped FK to courts (MySQL said: ".$e->getMessage().") — keeping index only.");
            }
        } else {
            logln("Skipped FK to courts (found orphan court_id values).");
        }
    } else {
        logln("Skipped FK to courts (courts table engine is {$courtsEngine}).");
    }
}

// Add FK to posts ONLY if posts is InnoDB and safe; otherwise keep index
if (columnExists($pdo, 'employee_details', 'post_id') && !fkExistsOnColumn($pdo, 'employee_details', 'post_id')) {
    $postsEngine = getEngine($pdo, 'posts'); // In your dump posts is MyISAM
    if (strtoupper($postsEngine) === 'INNODB') {
        if (!hasOrphans($pdo, 'employee_details', 'post_id', 'posts')) {
            try {
                $pdo->exec("ALTER TABLE employee_details
                            ADD CONSTRAINT fk_employee_details_post
                            FOREIGN KEY (post_id) REFERENCES posts(id)
                            ON DELETE CASCADE;");
                logln("Added FK fk_employee_details_post.");
            } catch (Throwable $e) {
                logln("Skipped FK to posts (MySQL said: ".$e->getMessage().") — keeping index only.");
            }
        } else {
            logln("Skipped FK to posts (found orphan post_id values).");
        }
    } else {
        logln("Skipped FK to posts (posts engine is {$postsEngine}; FK requires InnoDB). Keeping index only.");
    }
}

logln("Done. You can now remove this file.");
