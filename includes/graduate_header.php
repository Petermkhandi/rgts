<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
requireGraduateLogin();

if (isset($_SESSION['password_expiry_date']) && isPasswordExpired($_SESSION['password_expiry_date'])) {
    setFlash('warning', 'Your password has expired. Please reset it.');
    redirect('/graduate/reset_password.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Graduate - ' . APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --navy-900: #0b1a33;
            --navy-800: #0f2847;
            --navy-700: #1a3a5f;
            --blue-600: #2563eb;
            --blue-500: #3b82f6;
            --blue-400: #60a5fa;
            --sky-400: #38bdf8;
            --sky-300: #7dd3fc;
            --rucu-primary: #2563eb;
            --rucu-primary-light: #3b82f6;
            --rucu-accent: #0ea5e9;
            --rucu-surface: #ffffff;
            --rucu-bg: #f0f4f8;
            --rucu-text: #0f172a;
            --rucu-text-light: #64748b;
            --rucu-border: #e2e8f0;
            --rucu-radius: 14px;
            --rucu-radius-sm: 9px;
            --rucu-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 2px 8px rgba(0,0,0,0.04);
            --rucu-shadow-hover: 0 8px 24px rgba(37,99,235,0.1), 0 2px 8px rgba(0,0,0,0.04);
            --rucu-shadow-lg: 0 20px 40px rgba(37,99,235,0.12), 0 4px 16px rgba(0,0,0,0.06);
            --rucu-glow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f0f4f8;
            color: var(--rucu-text);
            font-weight: 400;
            -webkit-font-smoothing: antialiased;
        }
        ::selection { background: rgba(37,99,235,0.12); color: var(--rucu-primary); }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.94); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse-dot {
            0%, 100% { box-shadow: 0 0 0 0 rgba(37,99,235,0.3); }
            50% { box-shadow: 0 0 0 6px rgba(37,99,235,0); }
        }
        .animate-in { animation: fadeInUp 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .animate-delay-1 { animation-delay: 0.05s; opacity: 0; }
        .animate-delay-2 { animation-delay: 0.1s; opacity: 0; }
        .animate-delay-3 { animation-delay: 0.16s; opacity: 0; }
        .animate-delay-4 { animation-delay: 0.22s; opacity: 0; }
        .animate-delay-5 { animation-delay: 0.28s; opacity: 0; }

        .sidebar {
            width: 260px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background: linear-gradient(170deg, #0a1628 0%, #0d1f3c 50%, #09182e 100%);
            color: #fff;
            z-index: 1040;
            transition: transform 0.35s ease;
            display: flex;
            flex-direction: column;
        }
        .sidebar .brand {
            padding: 22px 20px 18px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .sidebar .brand h5 { font-weight: 700; margin-bottom: 0; font-size: 1rem; letter-spacing: 0.5px; }
        .sidebar .brand h5 i { color: var(--blue-400); margin-right: 7px; }
        .sidebar .brand small { font-size: 0.6rem; opacity: 0.35; font-weight: 400; letter-spacing: 0.8px; text-transform: uppercase; }

        .user-mini {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 11px;
            opacity: 0;
            animation: fadeIn 0.4s ease-out 0.1s forwards;
        }
        .user-mini .avatar {
            width: 34px; height: 34px; border-radius: 10px;
            background: linear-gradient(135deg, rgba(59,130,246,0.4), rgba(37,99,235,0.2));
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 0.82rem; flex-shrink: 0;
        }
        .user-mini span { font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 400; opacity: 0.8; }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.5);
            padding: 10px 15px;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 11px;
            border-radius: 10px;
            margin: 2px 11px;
            font-weight: 400;
            transition: all 0.2s ease;
            position: relative;
        }
        .sidebar .nav-link i { width: 20px; text-align: center; font-size: 1rem; opacity: 0.55; transition: all 0.2s; }
        .sidebar .nav-link:hover { color: #fff; background: rgba(59,130,246,0.12); }
        .sidebar .nav-link:hover i { opacity: 1; }
        .sidebar .nav-link.active {
            color: #fff;
            background: linear-gradient(135deg, rgba(37,99,235,0.2), rgba(59,130,246,0.1));
            font-weight: 500;
        }
        .sidebar .nav-link.active i { opacity: 1; color: var(--blue-400); }
        .sidebar .nav-link.active::before {
            content: '';
            position: absolute;
            left: -11px; top: 50%; transform: translateY(-50%);
            width: 3px; height: 22px; border-radius: 0 3px 3px 0;
            background: var(--blue-400);
            box-shadow: 0 0 6px rgba(96,165,250,0.4);
        }

        .sidebar .nav-spacer { flex: 1; }
        .sidebar .logout-link {
            color: rgba(255,255,255,0.3);
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 11px;
            border-top: 1px solid rgba(255,255,255,0.05);
            margin: 0 11px;
            font-size: 0.82rem;
            font-weight: 400;
            transition: all 0.2s ease;
            border-radius: 10px;
        }
        .sidebar .logout-link:hover { color: #fca5a5; background: rgba(239,68,68,0.08); }

        .main-content { margin-left: 260px; padding: 24px 28px; min-height: 100vh; transition: margin-left 0.35s ease; display: flex; flex-direction: column; }

        .topbar {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226,232,240,0.5);
            padding: 12px 22px;
            margin-bottom: 24px;
            border-radius: var(--rucu-radius);
            animation: fadeInUp 0.4s ease forwards;
        }
        .topbar h5 { font-weight: 600; font-size: 1rem; color: var(--rucu-text); }

        .card {
            border: 1px solid var(--rucu-border);
            border-radius: var(--rucu-radius);
            box-shadow: var(--rucu-shadow);
            transition: all 0.3s ease;
            overflow: hidden;
            background: var(--rucu-surface);
        }
        .card:hover { box-shadow: var(--rucu-shadow-hover); }
        .card-header {
            background: var(--rucu-surface);
            border-bottom: 1px solid var(--rucu-border);
            padding: 16px 22px;
        }
        .card-header h5 { font-weight: 600; font-size: 0.88rem; color: var(--rucu-text); }
        .card-body { padding: 20px 22px; }

        .stat-card {
            border: 1px solid var(--rucu-border);
            border-radius: var(--rucu-radius);
            box-shadow: var(--rucu-shadow);
            transition: all 0.3s ease;
            background: var(--rucu-surface);
            cursor: default;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--rucu-shadow-hover);
        }
        .icon-box {
            width: 42px; height: 42px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 12px; font-size: 1.1rem;
            flex-shrink: 0; transition: all 0.3s ease;
        }
        .stat-card:hover .icon-box { transform: scale(1.06); }

        .btn {
            font-weight: 500; font-size: 0.8rem;
            border-radius: var(--rucu-radius-sm);
            transition: all 0.2s ease;
            padding: 8px 18px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--rucu-primary), var(--rucu-primary-light));
            border: none; color: #fff;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--rucu-primary-light), var(--blue-400));
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37,99,235,0.2);
        }
        .btn-outline-primary {
            border: 1px solid var(--rucu-border);
            color: var(--rucu-primary);
            background: transparent;
        }
        .btn-outline-primary:hover {
            background: var(--rucu-primary);
            border-color: var(--rucu-primary);
            color: #fff;
            transform: translateY(-1px);
        }
        .btn-sm { padding: 6px 14px; font-size: 0.76rem; }

        .badge { font-weight: 500; padding: 4px 10px; border-radius: 7px; font-size: 0.67rem; letter-spacing: 0.2px; }

        .alert { border-radius: var(--rucu-radius-sm); border: none; font-weight: 400; font-size: 0.84rem; padding: 14px 18px; animation: slideDown 0.3s ease; }

        .info-item { margin-bottom: 0.9rem; }
        .info-item span {
            display: block; font-size: 0.66rem; color: var(--rucu-text-light);
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; font-weight: 500;
        }
        .info-item code, .info-item strong {
            font-size: 0.86rem; font-weight: 500; color: var(--rucu-text); background: none; border: none; padding: 0;
        }

        .table th { font-weight: 600; font-size: 0.7rem; color: var(--rucu-text-light); text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 14px; background: #f8fafc; border-bottom: 1px solid var(--rucu-border); }
        .table td { font-weight: 400; font-size: 0.83rem; padding: 11px 14px; border-bottom: 1px solid var(--rucu-border); vertical-align: middle; }
        .table-hover tbody tr:hover { background-color: #f1f5f9; }

        .form-control, .form-select {
            border: 1.5px solid var(--rucu-border);
            border-radius: var(--rucu-radius-sm);
            font-size: 0.84rem; font-weight: 400;
            padding: 9px 13px;
            transition: all 0.2s ease;
            background: #fafbfc;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--rucu-primary);
            box-shadow: var(--rucu-glow);
            background: #fff;
        }
        .form-label { font-weight: 500; font-size: 0.78rem; color: var(--rucu-text); margin-bottom: 5px; }
        .form-text { font-size: 0.73rem; color: var(--rucu-text-light); margin-top: 4px; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11,26,51,0.4); z-index: 1035; backdrop-filter: blur(4px); }
        .sidebar-overlay.show { display: block; animation: fadeIn 0.25s ease; }
        .sidebar-close { display: none; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .sidebar-close { display: inline-block !important; }
            .main-content { margin-left: 0; padding: 16px; }
            .topbar { padding: 10px 14px; }
            .card-body { padding: 16px; }
            .card-header { padding: 14px 16px; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="brand d-flex justify-content-between align-items-center">
        <div>
            <h5><i class="bi bi-mortarboard-fill"></i> RUCU GETS</h5>
            <small>Graduate Portal</small>
        </div>
        <button class="btn btn-sm btn-outline-light sidebar-close" id="closeSidebar">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="user-mini">
        <div class="avatar"><?= strtoupper(substr($_SESSION['graduate_name'] ?? 'G', 0, 1)) ?></div>
        <span><?= htmlspecialchars($_SESSION['graduate_name'] ?? 'Graduate') ?></span>
    </div>

    <nav class="nav flex-column mt-2">
        <a class="nav-link animate-in animate-delay-1" href="dashboard.php" data-tab="overview"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link animate-in animate-delay-2" href="dashboard.php" data-tab="profile"><i class="bi bi-person"></i> My Profile</a>
        <a class="nav-link animate-in animate-delay-3" href="dashboard.php" data-tab="employment"><i class="bi bi-briefcase"></i> Employment</a>
        <a class="nav-link animate-in animate-delay-4" href="dashboard.php" data-tab="verification"><i class="bi bi-shield-check"></i> Verification</a>
        <a class="nav-link animate-in animate-delay-5" href="dashboard.php" data-tab="jobs"><i class="bi bi-newspaper"></i> Job Opportunities</a>
    </nav>

    <div class="nav-spacer"></div>
    <a class="logout-link" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Logout</a>
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
            <span class="text-muted" style="font-weight:400"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['graduate_name'] ?? 'Graduate') ?></span>
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

document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('[data-tab]');
    tabs.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const tab = this.dataset.tab;
            window.location.hash = tab;
            switchTab(tab);
            document.getElementById('sidebar')?.classList.remove('show');
            document.getElementById('sidebarOverlay')?.classList.remove('show');
        });
    });
    if (window.location.hash) {
        const tab = window.location.hash.replace('#', '');
        switchTab(tab);
    }
});
function switchTab(tab) {
    document.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });
    document.querySelectorAll('[data-tab]').forEach(function(l) { l.classList.remove('active'); });
    var pane = document.getElementById('tab-' + tab);
    if (pane) pane.classList.add('active');
    var nav = document.querySelector('[data-tab="' + tab + '"]');
    if (nav) nav.classList.add('active');
    window.location.hash = tab;
}
</script>
