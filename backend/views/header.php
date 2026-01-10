<?php if (!isset($_SESSION)) session_start();
$user = $_SESSION['user'] ?? null;
$isAdmin = $user && (($user['role'] ?? '') === 'admin');
require_once __DIR__ . '/../config/db.php';

// Judicial Officer Info - Only Current District & Sessions Judge (Posted)
$judicialInfo = null;
if ($isAdmin && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT jo.name AS officer_name, 
                   jo.post AS officer_post, 
                   jo.bps AS officer_bps, 
                   c.name AS court_name
            FROM judicial_officers jo
            LEFT JOIN courts c ON c.id = jo.court_id
            WHERE jo.post = 'District & Sessions Judge'
              AND jo.status = 'Posted'
            LIMIT 1
        ");
        $stmt->execute();
        $judicialInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Silent fail - don't break page if query fails
    }
}

$currentPage = $_GET['page'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Court Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed: 70px;
            --bg-light: #f8f9fa;
            --bg-dark: #121212;
            --card-light: #ffffff;
            --card-dark: #1e1e1e;
            --text-light: #212529;
            --text-dark: #e0e0e0;
            --border-dark: #444;
        }

        body {
            background: var(--bg-light);
            color: var(--text-light);
            transition: background 0.3s ease, color 0.3s ease;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
        }

        body.dark-mode {
            background: var(--bg-dark);
            color: var(--text-dark);
        }

        .card {
            background: var(--card-light);
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }

        body.dark-mode .card {
            background: var(--card-dark);
            border-color: var(--border-dark);
        }

        /* Compact Sidebar with Scroll */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #005566, #003d4d);
            color: white;
            transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            box-shadow: 6px 0 15px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
        }

        .sidebar .logo {
            padding: 18px 12px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }

        .sidebar .logo img {
            height: 45px;
            max-width: 100%;
            object-fit: contain;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .logo img {
            opacity: 0;
        }

        /* Scrollable Menu Area */
        .sidebar .menu-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 8px 0;
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 9px 14px;
            margin: 2px 10px;
            border-radius: 6px;
            font-size: 0.92rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.18);
            color: white;
            transform: translateX(4px);
        }

        .sidebar .nav-link i {
            font-size: 1.25rem;
            width: 36px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar.collapsed .nav-text {
            opacity: 0;
            visibility: hidden;
            width: 0;
        }

        /* Dark Mode Toggle */
        .dark-toggle {
            padding: 10px 14px;
            margin: 6px 10px;
            text-align: center;
            cursor: pointer;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .dark-toggle:hover {
            background: rgba(255,255,255,0.15);
        }

        .dark-toggle i {
            font-size: 1.25rem;
        }

        /* User Section */
        .user-section {
            padding: 10px 14px;
            margin: 6px 10px 12px;
            border-top: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 25px;
        }

        .main-content.shifted {
            margin-left: var(--sidebar-collapsed);
        }

        /* Toggle Button */
        .toggle-btn {
            position: fixed;
            top: 18px;
            left: calc(var(--sidebar-width) + 10px);
            background: #005566;
            color: white;
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            z-index: 1001;
            transition: left 0.35s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .toggle-btn.shifted {
            left: calc(var(--sidebar-collapsed) + 10px);
        }

        .toggle-btn i {
            font-size: 1.2rem;
        }

        /* Judicial Officer Bar - Centered */
        .judicial-bar {
            background: linear-gradient(90deg, #e3f2fd, #bbdefb);
            padding: 16px 24px;
            text-align: center;
            font-size: 1.05rem;
            font-weight: 500;
            color: #1565c0;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 28px;
        }

        body.dark-mode .judicial-bar {
            background: linear-gradient(90deg, #1e3a5f, #2c5282);
            color: #90caf9;
        }

        .judicial-bar strong {
            color: #003d82;
            font-weight: 700;
        }

        body.dark-mode .judicial-bar strong {
            color: #64b5f6;
        }

        /* Scrollbar styling for sidebar */
        .sidebar .menu-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar .menu-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar .menu-scroll::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .sidebar .menu-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: var(--sidebar-collapsed);
            }
            .sidebar .nav-text {
                opacity: 0;
                visibility: hidden;
            }
            .main-content {
                margin-left: var(--sidebar-collapsed);
            }
            .toggle-btn {
                left: calc(var(--sidebar-collapsed) + 10px);
            }
        }
    </style>
</head>
<body>

<!-- Left Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo">
        <img src="../assets/images/DC-LOGO.png" alt="Court Logo">
    </div>

    <!-- Scrollable Menu -->
    <div class="menu-scroll">
        <nav class="nav flex-column">
            <a href="?page=dashboard" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span class="nav-text">Dashboard</span>
            </a>

            <?php if ($isAdmin): ?>
            <a href="?page=courts" class="nav-link <?= in_array($currentPage, ['courts']) ? 'active' : '' ?>">
                <i class="bi bi-bank"></i>
                <span class="nav-text">Courts</span>
            </a>
            <a href="?page=employees" class="nav-link <?= in_array($currentPage, ['employees']) ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span class="nav-text">System Users</span>
            </a>
            <a href="?page=employee_details" class="nav-link <?= in_array($currentPage, ['employee_details']) ? 'active' : '' ?>">
                <i class="bi bi-person-vcard"></i>
                <span class="nav-text">Add Staff</span>
            </a>

            <a href="?page=employee_search" class="nav-link <?= in_array($currentPage, ['employee_search', 'employee_profile']) ? 'active' : '' ?>">
                <i class="bi bi-search"></i>
                <span class="nav-text">Staff Search</span>
            </a>

            <a href="?page=files" class="nav-link <?= in_array($currentPage, ['files']) ? 'active' : '' ?>">
                <i class="bi bi-person-vcard"></i>
                <span class="nav-text">Staff Record</span>
            </a>

            <a href="?page=casual_leave_report" class="nav-link <?= in_array($currentPage, ['casual_leave_report']) ? 'active' : '' ?>">
                <i class="bi bi-person-vcard"></i>
                <span class="nav-text">Leave Report </span>
            </a>
            <a href="?page=leave_types" class="nav-link <?= in_array($currentPage, ['leave_types']) ? 'active' : '' ?>">
                <i class="bi bi-list-check"></i>
                <span class="nav-text">Leave Types</span>
            </a>
            <a href="?page=file_categories" class="nav-link <?= in_array($currentPage, ['file_categories']) ? 'active' : '' ?>">
                <i class="bi bi-tags"></i>
                <span class="nav-text">File Categories</span>
            </a>
            <a href="?page=posts" class="nav-link <?= in_array($currentPage, ['posts']) ? 'active' : '' ?>">
                <i class="bi bi-briefcase"></i>
                <span class="nav-text">Posts</span>
            </a>
            <a href="?page=judicial_officers" class="nav-link <?= in_array($currentPage, ['judicial_officers']) ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i>
                <span class="nav-text">Judicial Officers</span>
            </a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Bottom Section: Dark Toggle + User -->
    <div class="user-section">
        <div class="dark-toggle" id="darkToggle" title="Toggle Dark Mode">
            <i class="bi bi-moon-stars-fill" id="darkIcon"></i>
        </div>

        <div class="dropdown">
            <a class="nav-link dropdown-toggle text-white d-flex align-items-center px-0" href="#" role="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle fs-4 me-2"></i>
                <span class="nav-text"><?= htmlspecialchars($user['name'] ?? 'User') ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="?page=profile"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="?page=change_password"><i class="bi bi-key me-2"></i>Change Password</a></li>
                <?php if ($isAdmin): ?>
                <li><a class="dropdown-item" href="?page=reset_password"><i class="bi bi-shield-lock me-2"></i>Reset Password</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="?page=logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Toggle Button -->
<button class="toggle-btn" id="toggleBtn">
    <i class="bi bi-list"></i>
</button>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <?php if ($isAdmin && $judicialInfo): ?>
    <div class="judicial-bar">
        <strong>Judicial Officer:</strong> <?= htmlspecialchars($judicialInfo['officer_name']) ?> |
        <strong>Post:</strong> <?= htmlspecialchars($judicialInfo['officer_post']) ?> |
        <strong>BPS:</strong> <?= htmlspecialchars($judicialInfo['officer_bps']) ?> |
        <strong>Court:</strong> <?= htmlspecialchars($judicialInfo['court_name'] ?? 'District & Sessions Court Jamshoro') ?>
    </div>
    <?php endif; ?>

    <div class="container-fluid">