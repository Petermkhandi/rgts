<?php
$pageTitle = 'Change Password';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

requireGraduateLogin();

$error = '';
$success = '';
$is_expired = isset($_SESSION['password_expired']) && $_SESSION['password_expired'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (!$is_expired && empty($current_password)) {
        $error = 'Please enter your current password.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $error = 'Password must contain at least one special character (!@#$%^&*...).';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        
        if (!$is_expired) {
            $stmt = $db->prepare("SELECT password FROM graduates WHERE id = ?");
            $stmt->execute([$_SESSION['graduate_id']]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                $error = 'Current password is incorrect.';
            }
        }
        
        if (!$error) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $expiry = date('Y-m-d H:i:s', strtotime('+' . PASSWORD_EXPIRY_DAYS . ' days'));
            
            $stmt = $db->prepare("UPDATE graduates SET password = ?, password_expiry_date = ? WHERE id = ?");
            $stmt->execute([$hashed, $expiry, $_SESSION['graduate_id']]);
            
            $_SESSION['password_expiry_date'] = $expiry;
            unset($_SESSION['password_expired']);
            
            logActivity('graduate', $_SESSION['graduate_id'], 'Password Reset', 'Password reset successfully');
            
            if ($is_expired) {
                setFlash('success', 'Password reset successfully! You can now access your dashboard.');
                redirect('/graduate/dashboard.php');
            } else {
                $success = 'Password changed successfully.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/graduate_header.php';
flashAlert();
?>

<style>
.password-strength { height:4px; background:var(--rucu-border); border-radius:3px; overflow:hidden; }
.password-strength .bar { height:100%; width:0%; border-radius:3px; transition:all 0.3s ease; }
.password-strength .bar.weak { width:25%; background:#ef4444; }
.password-strength .bar.fair { width:50%; background:#eab308; }
.password-strength .bar.good { width:75%; background:#3b82f6; }
.password-strength .bar.strong { width:100%; background:#10b981; }
.password-rules { list-style:none; padding:0; margin:0; }
.password-rules li { padding:3px 0; font-size:0.8rem; color:#94a3b8; transition:color 0.2s; }
.password-rules li.pass { color:#10b981; }
</style>

<div class="row justify-content-center animate-in animate-delay-1">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <div class="icon-box" style="background:rgba(37,99,235,0.1);color:var(--rucu-primary);width:36px;height:36px;font-size:0.95rem">
                    <i class="bi bi-lock-fill"></i>
                </div>
                <h5 class="mb-0"><?= $is_expired ? 'Password Expired' : 'Change Password' ?></h5>
            </div>
            <div class="card-body">
                <?php if ($is_expired): ?>
                    <div class="alert alert-warning">Your password has expired. Please set a new password.</div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <?= csrfField() ?>
                    <?php if (!$is_expired): ?>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" id="password" class="form-control" required>
                        <div class="password-strength mt-2" id="strengthBar"></div>
                        <ul class="password-rules mt-2">
                            <li id="rule-length"><i class="bi bi-circle"></i> At least 8 characters</li>
                            <li id="rule-upper"><i class="bi bi-circle"></i> Uppercase letter</li>
                            <li id="rule-lower"><i class="bi bi-circle"></i> Lowercase letter</li>
                            <li id="rule-number"><i class="bi bi-circle"></i> Number</li>
                            <li id="rule-special"><i class="bi bi-circle"></i> Special character</li>
                        </ul>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i><?= $is_expired ? 'Reset Password' : 'Change Password' ?></button>
                        <?php if (!$is_expired): ?>
                        <a href="dashboard.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Back</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const pwd = document.getElementById('password');
    const bar = document.getElementById('strengthBar');
    const rules = {
        length: { el: document.getElementById('rule-length'), test: p => p.length >= 8 },
        upper: { el: document.getElementById('rule-upper'), test: p => /[A-Z]/.test(p) },
        lower: { el: document.getElementById('rule-lower'), test: p => /[a-z]/.test(p) },
        number: { el: document.getElementById('rule-number'), test: p => /[0-9]/.test(p) },
        special: { el: document.getElementById('rule-special'), test: p => /[^A-Za-z0-9]/.test(p) }
    };
    if (!pwd || !bar) return;
    pwd.addEventListener('input', function() {
        const p = this.value;
        let score = 0;
        for (const k in rules) {
            if (!rules[k].el) continue;
            const pass = rules[k].test(p);
            rules[k].el.classList.toggle('pass', pass);
            rules[k].el.querySelector('i').className = pass ? 'bi bi-check-circle-fill' : 'bi bi-circle';
            if (pass) score++;
        }
        const cls = score <= 1 ? 'weak' : score <= 2 ? 'fair' : score <= 4 ? 'good' : score === 5 ? 'strong' : '';
        bar.innerHTML = score > 0 ? '<div class="bar ' + cls + '"></div>' : '';
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/graduate_footer.php'; ?>
