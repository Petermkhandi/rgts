<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
requireDVCaaLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'DVCAA - ' . APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        :root {
            --rucu-primary: #1a5276;
            --rucu-secondary: #2ecc71;
            --rucu-accent: #f39c12;
        }
        body { background-color: #f4f6f9; }

        .sidebar {
            min-height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--rucu-primary);
            color: #fff;
            z-index: 1040;
            transition: transform 0.3s ease;
        }
        .sidebar .brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 0;
            font-size: 0.95rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255,255,255,0.15);
        }
        .sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
        .main-content { margin-left: 250px; padding: 20px; min-height: 100vh; transition: margin-left 0.3s ease; display: flex; flex-direction: column; }
        .topbar {
            background: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 15px 30px;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        .stat-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card .stat-icon { font-size: 2.5rem; opacity: 0.8; }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1035;
        }
        .sidebar-overlay.show { display: block; }

        .sidebar-close { display: none; }

        @media print {
            .sidebar, .topbar, .sidebar-overlay, .btn { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .sidebar-close { display: inline-block !important; }
            .main-content { margin-left: 0; }
            .topbar { padding: 10px 15px; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="brand d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1"><i class="bi bi-mortarboard-fill"></i> RUCU GETS</h5>
            <small class="text-white-50">DVCAA Panel</small>
        </div>
        <button class="btn btn-sm btn-outline-light sidebar-close" id="closeSidebar">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <nav class="nav flex-column mt-3">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'graduates.php' ? 'active' : '' ?>" href="graduates.php"><i class="bi bi-people"></i> Graduates</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'verification.php' ? 'active' : '' ?>" href="verification.php"><i class="bi bi-shield-check"></i> Verification</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'recent_activity.php' ? 'active' : '' ?>" href="recent_activity.php"><i class="bi bi-clock-history"></i> Recent Activity</a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" href="settings.php"><i class="bi bi-gear"></i> Settings</a>
        <hr class="text-white-50">
        <a class="nav-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Logout</a>
    </nav>
</div>

<div class="main-content">
    <div class="topbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary d-md-none" id="openSidebar">
                <i class="bi bi-list fs-5"></i>
            </button>
            <h5 class="mb-0"><?= $pageTitle ?? 'Dashboard' ?></h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted"><i class="bi bi-person-circle"></i> <?= $_SESSION['admin_name'] ?? 'DVCAA' ?></span>
        </div>
    </div>
    <div class="container-fluid px-0">

<script>
document.getElementById('openSidebar').addEventListener('click', function() {
    document.getElementById('sidebar').classList.add('show');
    document.getElementById('sidebarOverlay').classList.add('show');
});
document.getElementById('closeSidebar').addEventListener('click', function() {
    document.getElementById('sidebar').classList.remove('show');
    document.getElementById('sidebarOverlay').classList.remove('show');
});
document.getElementById('sidebarOverlay').addEventListener('click', function() {
    document.getElementById('sidebar').classList.remove('show');
    this.classList.remove('show');
});
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        document.getElementById('sidebar').classList.remove('show');
        document.getElementById('sidebarOverlay').classList.remove('show');
    }
});
</script>
