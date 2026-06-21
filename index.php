<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

if (isGraduateLoggedIn()) {
    redirect('/graduate/dashboard.php');
}
if (isAdminLoggedIn()) {
    redirect('/admin/dashboard.php');
}
if (isDVCaaLoggedIn()) {
    redirect('/dvcaa/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reg_number = sanitize($_POST['reg_number'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($reg_number) || empty($password)) {
        $error = 'Please enter your registration number/email and password.';
    } else {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE email = ?");
        $stmt->execute([$reg_number]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            unset($_SESSION['flash']);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['last_activity'] = time();
            
            $stmt = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$admin['id']]);
            
            logActivity($admin['role'], $admin['id'], 'Login', $admin['role'] . ' logged in successfully');
            
            if ($admin['role'] === 'dvcaa') {
                redirect('/dvcaa/dashboard.php');
            } else {
                redirect('/admin/dashboard.php');
            }
        }
        
        $stmt = $db->prepare("SELECT * FROM graduates WHERE reg_number = ?");
        $stmt->execute([$reg_number]);
        $user = $stmt->fetch();
        
        if ($user) {
            if ($user['first_login'] || $user['password'] === null) {
                session_regenerate_id(true);
                unset($_SESSION['flash']);
                $_SESSION['graduate_id'] = $user['id'];
                $_SESSION['graduate_name'] = $user['full_name'];
                $_SESSION['graduate_reg'] = $user['reg_number'];
                $_SESSION['graduate_role'] = 'graduate';
                $_SESSION['first_login'] = true;
                $_SESSION['last_activity'] = time();
                
                logActivity('graduate', $user['id'], 'First Login', 'First time login - setting password');
                redirect('/graduate/set_password.php');
            } else {
                if (password_verify($password, $user['password'])) {
                    if (isPasswordExpired($user['password_expiry_date'])) {
                        session_regenerate_id(true);
                        unset($_SESSION['flash']);
                        $_SESSION['graduate_id'] = $user['id'];
                        $_SESSION['graduate_name'] = $user['full_name'];
                        $_SESSION['graduate_reg'] = $user['reg_number'];
                        $_SESSION['graduate_role'] = 'graduate';
                        $_SESSION['password_expired'] = true;
                        $_SESSION['last_activity'] = time();
                        redirect('/graduate/reset_password.php');
                    }
                    
                    session_regenerate_id(true);
                    unset($_SESSION['flash']);
                    $_SESSION['graduate_id'] = $user['id'];
                    $_SESSION['graduate_name'] = $user['full_name'];
                    $_SESSION['graduate_reg'] = $user['reg_number'];
                    $_SESSION['graduate_role'] = 'graduate';
                    $_SESSION['password_expiry_date'] = $user['password_expiry_date'];
                    $_SESSION['last_activity'] = time();
                    
                    logActivity('graduate', $user['id'], 'Login', 'Graduate logged in successfully');
                    redirect('/graduate/dashboard.php');
                } else {
                    $error = 'Invalid credentials. Please check your registration number/email and password.';
                }
            }
        } else {
            $error = 'Invalid credentials. Please check your registration number/email and password.';
        }
    }
}

$pageTitle = 'Login - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --rucu-primary: #1a5276;
            --rucu-accent: #2c7bb8;
            --rucu-light: #e8f0f8;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-wrapper {
            width: 100%;
            max-width: 780px;
            display: flex;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }
        .side-panel {
            flex: 1;
            background: linear-gradient(160deg, var(--rucu-primary) 0%, var(--rucu-accent) 100%);
            color: white;
            padding: 2rem 1.75rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .side-panel::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }
        .side-panel::after {
            content: '';
            position: absolute;
            bottom: -40px;
            left: -40px;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
        }
        .side-panel .brand {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            position: relative;
            z-index: 1;
        }
        .side-panel .brand i { margin-right: 0.4rem; }
        .side-panel .tagline {
            font-size: 0.75rem;
            opacity: 0.85;
            font-weight: 300;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        .side-panel .features {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        .side-panel .features li {
            display: flex;
            align-items: center;
            padding: 0.4rem 0;
            font-size: 0.78rem;
            font-weight: 400;
        }
        .side-panel .features li i {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.6rem;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .side-panel .quote {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            font-style: italic;
            font-size: 0.75rem;
            opacity: 0.8;
            position: relative;
            z-index: 1;
        }
        .form-panel {
            flex: 0.7;
            background: #fff;
            padding: 2rem 1.75rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-panel h4 {
            font-weight: 600;
            color: var(--rucu-primary);
            margin-bottom: 0.3rem;
            font-size: 1.25rem;
        }
        .form-panel .subtitle {
            color: #6c757d;
            font-size: 0.8rem;
            margin-bottom: 1.5rem;
        }
        .form-label-modern {
            font-size: 0.75rem;
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        .input-modern {
            height: 42px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0 12px 0 38px;
            font-size: 0.85rem;
            transition: all 0.25s ease;
            background: #f8f9fa;
        }
        .input-modern:focus {
            border-color: var(--rucu-primary);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(26, 82, 118, 0.08);
            outline: none;
        }
        .input-wrap {
            position: relative;
            margin-bottom: 1rem;
        }
        .input-wrap .icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            font-size: 1rem;
            transition: color 0.25s;
        }
        .input-wrap .input-modern:focus + .icon {
            color: var(--rucu-primary);
        }
        .input-wrap .toggle-pwd {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #adb5bd;
            font-size: 1rem;
            transition: color 0.25s;
        }
        .input-wrap .toggle-pwd:hover {
            color: var(--rucu-primary);
        }
        .btn-signin {
            height: 42px;
            background: var(--rucu-primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            transition: all 0.25s ease;
        }
        .btn-signin:hover {
            background: var(--rucu-accent);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(26, 82, 118, 0.25);
        }
        .btn-signin:active {
            transform: translateY(0);
        }
        .error-alert {
            animation: fadeInDown 0.4s ease-out;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.5rem;
        }
        .error-alert .icon-circle {
            width: 38px;
            height: 38px;
            background: #ef4444;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-3px); }
            20%, 40%, 60%, 80% { transform: translateX(3px); }
        }
        .error-icon-shake { animation: shake 0.5s ease-in-out; }
        .footer-text {
            text-align: center;
            font-size: 0.7rem;
            color: #adb5bd;
            margin-top: 1.5rem;
        }
        .forgot-link {
            color: var(--rucu-primary);
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s;
        }
        .forgot-link:hover {
            color: var(--rucu-accent);
            text-decoration: underline;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            font-size: 1rem;
            pointer-events: none;
        }
        @media (max-width: 767.98px) {
            .side-panel { display: none !important; }
            .login-wrapper { flex-direction: column; max-width: 420px; }
            .form-panel { flex: 1; padding: 1.75rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="side-panel">
        <div class="brand"><i class="bi bi-mortarboard-fill"></i> RUCU GETS</div>
        <div class="tagline">Graduate Employment Tracking & Verification System</div>
        <ul class="features">
            <li><i class="bi bi-people-fill"></i>Graduate Data Management</li>
            <li><i class="bi bi-briefcase-fill"></i>Employment Tracking</li>
            <li><i class="bi bi-shield-check"></i>Credential Verification</li>
            <li><i class="bi bi-graph-up"></i>Reports & Analytics</li>
            <li><i class="bi bi-newspaper"></i>Live Job Feed</li>
        </ul>
        <div class="quote">
            "Empowering graduates through data-driven employment tracking and academic verification."
        </div>
    </div>

    <div class="form-panel">
        <h4>Welcome Back</h4>
        <p class="subtitle">Sign in to your account to continue</p>

        <?php if ($error): ?>
            <div class="error-alert">
                <div class="d-flex align-items-center gap-2">
                    <div class="error-icon-shake">
                        <div class="icon-circle text-white d-flex align-items-center justify-content-center rounded-circle">
                            <i class="bi bi-exclamation-triangle-fill fs-6"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <p class="mb-0 text-danger" style="font-size: 0.8rem;"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <?= csrfField() ?>
            <label class="form-label-modern">Registration Number / Email</label>
            <div class="input-wrap">
                <input type="text" id="reg_number" name="reg_number" class="form-control input-modern" required autofocus>
                <i class="bi bi-person icon"></i>
            </div>

            <label class="form-label-modern">Password</label>
            <div class="input-wrap">
                <input type="password" id="password" name="password" class="form-control input-modern" required>
                <i class="bi bi-lock icon"></i>
                <i class="bi bi-eye toggle-pwd" id="togglePassword"></i>
            </div>

            <button type="submit" class="btn btn-signin w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>SIGN IN
            </button>
            <div class="text-center mt-3">
                <a href="#" class="forgot-link" data-bs-toggle="modal" data-bs-target="#resetModal">Forgot Password?</a>
            </div>
        </form>

        <div class="footer-text">&copy; <?= date('Y') ?> Ruaha Catholic University</div>
    </div>
</div>

<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius: 12px; border: none; overflow: hidden;">
            <div class="modal-header bg-light border-0 pb-0">
                <h6 class="modal-title fw-bold"><i class="bi bi-key-fill text-primary me-2"></i>Reset Password</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-3">Enter your registration number or email to reset your password to the default.</p>
                <div class="input-wrap mb-3">
                    <input type="text" id="resetInput" class="form-control input-modern" placeholder="Reg. Number or Email">
                    <i class="bi bi-person input-icon"></i>
                </div>
                <div id="resetMsg" class="mb-2"></div>
                <button class="btn btn-signin w-100" id="resetBtn" onclick="resetPassword()">
                    <i class="bi bi-arrow-clockwise me-2"></i>RESET
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const togglePwd = document.getElementById('togglePassword');
const pwdInput = document.getElementById('password');
togglePwd.addEventListener('click', function () {
    const isPassword = pwdInput.getAttribute('type') === 'password';
    pwdInput.setAttribute('type', isPassword ? 'text' : 'password');
    this.classList.toggle('bi-eye');
    this.classList.toggle('bi-eye-slash');
});

function resetPassword() {
    const input = document.getElementById('resetInput').value.trim();
    const msg = document.getElementById('resetMsg');
    const btn = document.getElementById('resetBtn');
    if (!input) {
        msg.innerHTML = '<div class="text-danger small"><i class="bi bi-exclamation-circle"></i> Please enter your reg number or email</div>';
        return;
    }
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    msg.innerHTML = '';

    const fd = new FormData();
    fd.append('input', input);

    fetch('api/ajax_handler.php?action=reset_password', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                msg.innerHTML = '<div class="text-success small"><i class="bi bi-check-circle"></i> ' + data.message + '</div>';
                document.getElementById('resetInput').value = '';
            } else {
                msg.innerHTML = '<div class="text-danger small"><i class="bi bi-x-circle"></i> ' + data.message + '</div>';
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>RESET';
        })
        .catch(() => {
            msg.innerHTML = '<div class="text-danger small"><i class="bi bi-x-circle"></i> Request failed. Try again.</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>RESET';
        });
}
</script>

</body>
</html>
