<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!isset($_SESSION['graduate_id']) || !isset($_SESSION['first_login'])) {
    redirect('/index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must contain at least one special character (!@#$%^&*...).';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $expiry = date('Y-m-d H:i:s', strtotime('+' . PASSWORD_EXPIRY_DAYS . ' days'));
        
        $stmt = $db->prepare("UPDATE graduates SET password = ?, password_expiry_date = ?, first_login = 0 WHERE id = ?");
        $stmt->execute([$hashed, $expiry, $_SESSION['graduate_id']]);
        
        $_SESSION['password_expiry_date'] = $expiry;
        unset($_SESSION['first_login']);
        
        logActivity('graduate', $_SESSION['graduate_id'], 'Password Set', 'First-time password set');
        
        setFlash('success', 'Password set successfully! You can now access your dashboard.');
        redirect('/graduate/dashboard.php');
    }
}

$pageTitle = 'Set Password - ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .pwd-card { border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.03); transition:all 0.25s ease; font-family:'Lato',sans-serif; }
    .pwd-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.08); }
    .pwd-input { border:1px solid #e2e8f0; border-radius:8px; font-size:0.85rem; padding:8px 12px; transition:all 0.2s ease; }
    .pwd-input:focus { border-color:#3498db; box-shadow:0 0 0 3px rgba(52,152,219,0.1); }
    .pwd-btn { background:#1a5276; border:none; border-radius:8px; font-weight:450; transition:all 0.2s ease; }
    .pwd-btn:hover { background:#2980b9; transform:translateY(-1px); box-shadow:0 4px 12px rgba(26,82,118,0.2); }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
    .animate-pwd { animation:fadeInUp 0.4s ease-out forwards; }
    .password-strength { height:4px; border-radius:3px; background:#e2e8f0; overflow:hidden; }
    .password-strength .bar { height:100%; width:0%; border-radius:3px; transition:all 0.3s ease; }
    .password-strength .bar.weak { width:25%; background:#e53e3e; }
    .password-strength .bar.fair { width:50%; background:#ecc94b; }
    .password-strength .bar.good { width:75%; background:#4299e1; }
    .password-strength .bar.strong { width:100%; background:#38a169; }
    .password-rules { list-style:none; padding:0; margin:0; }
    .password-rules li { padding:3px 0; font-size:0.8rem; color:#718096; transition:color 0.2s; }
    .password-rules li.pass { color:#38a169; }
</style>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-5">
            <div class="pwd-card animate-pwd">
                <div class="p-5">
                    <div class="text-center mb-4">
                        <div class="d-flex align-items-center justify-content-center rounded-3 mx-auto mb-3" style="width:52px;height:52px;background:rgba(26,82,118,0.08);color:#1a5276">
                            <i class="bi bi-key-fill fs-5"></i>
                        </div>
                        <h4 style="font-weight:500; color:#1a5276">Set Your Password</h4>
                        <p class="text-muted small">Welcome, <?= htmlspecialchars($_SESSION['graduate_name']) ?></p>
                        <p class="text-muted small">This is your first login. Please set a secure password.</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert mb-3" style="background:#fef2f2; border:none; border-radius:8px; font-size:0.85rem"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:0.8rem; font-weight:450">Password</label>
                            <input type="password" name="password" id="password" class="form-control pwd-input" required>
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
                            <label class="form-label" style="font-size:0.8rem; font-weight:450">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control pwd-input" required>
                        </div>
                        <button type="submit" class="btn pwd-btn w-100 py-2" style="color:#fff">
                            <i class="bi bi-check-circle me-1"></i> Set Password & Continue
                        </button>
                    </form>
                </div>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
