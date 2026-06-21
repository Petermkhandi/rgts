<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
requireDVCaaLogin();

$db = getDB();
$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $errors = [];

        if (empty($name)) {
            $errors[] = 'Name is required';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }

        $stmt = $db->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $admin_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already in use';
        }

        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE admin_users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $admin_id]);
            $_SESSION['admin_name'] = $name;
            logActivity('dvcaa', $admin_id, 'profile_updated', 'Name and email updated');
            setFlash('success', 'Profile updated successfully');
            header('Location: settings.php');
            exit();
        } else {
            setFlash('error', implode(', ', $errors));
        }
    } else {
        setFlash('error', 'Invalid request');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $errors = [];

        $stmt = $db->prepare("SELECT password FROM admin_users WHERE id = ?");
        $stmt->execute([$admin_id]);
        $row = $stmt->fetch();

        if (!password_verify($current_password, $row['password'])) {
            $errors[] = 'Current password is incorrect';
        }
        if (strlen($new_password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $errors[] = 'Password must contain an uppercase letter';
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $errors[] = 'Password must contain a lowercase letter';
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $errors[] = 'Password must contain a number';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $errors[] = 'Password must contain a special character';
        }
        if ($new_password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }

        if (empty($errors)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $admin_id]);
            logActivity('dvcaa', $admin_id, 'password_changed', 'Password updated');
            setFlash('success', 'Password changed successfully');
            header('Location: settings.php');
            exit();
        } else {
            setFlash('error', implode(', ', $errors));
        }
    } else {
        setFlash('error', 'Invalid request');
    }
}

$pageTitle = 'Account Settings';
require_once __DIR__ . '/../includes/dvcaa_header.php';

$stmt = $db->prepare("SELECT name, email, role, last_login, created_at FROM admin_users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();
?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-person-gear"></i> Profile Information</h5>
            </div>
            <div class="card-body">
                <?php flashAlert(); ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($admin['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="<?= ucfirst(str_replace('_', ' ', $admin['role'])) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Login</label>
                        <input type="text" class="form-control" value="<?= $admin['last_login'] ? formatDate($admin['last_login']) : 'Never' ?>" disabled>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Profile</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-lock"></i> Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" id="dvcaaPassword" class="form-control" required>
                        <div class="password-strength mt-2" id="strengthBar"></div>
                        <ul class="password-rules mt-2 small text-muted" id="passwordRules">
                            <li id="rule-length"><i class="bi bi-circle"></i> At least 8 characters</li>
                            <li id="rule-upper"><i class="bi bi-circle"></i> Uppercase letter</li>
                            <li id="rule-lower"><i class="bi bi-circle"></i> Lowercase letter</li>
                            <li id="rule-number"><i class="bi bi-circle"></i> Number</li>
                            <li id="rule-special"><i class="bi bi-circle"></i> Special character</li>
                        </ul>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="dvcaaConfirmPassword" class="form-control" required>
                        <div id="matchMsg" class="mt-1 small"></div>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-warning"><i class="bi bi-key"></i> Change Password</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Account Details</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Account Created:</td>
                        <td><?= formatDate($admin['created_at']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">User ID:</td>
                        <td><?= $admin_id ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.password-strength { height: 5px; border-radius: 3px; background: #e9ecef; overflow: hidden; }
.password-strength .bar { height: 100%; width: 0%; border-radius: 3px; transition: all 0.3s ease; }
.password-strength .bar.weak { width: 25%; background: #dc3545; }
.password-strength .bar.fair { width: 50%; background: #ffc107; }
.password-strength .bar.good { width: 75%; background: #0dcaf0; }
.password-strength .bar.strong { width: 100%; background: #198754; }
.password-rules { list-style: none; padding: 0; margin: 0; }
.password-rules li { padding: 2px 0; transition: color 0.2s; }
.password-rules li.pass { color: #198754; }
.password-rules li.pass i { color: #198754; }
</style>

<script>
(function() {
    const pwd = document.getElementById('dvcaaPassword');
    const confirm = document.getElementById('dvcaaConfirmPassword');
    const bar = document.getElementById('strengthBar');
    const matchMsg = document.getElementById('matchMsg');
    const rules = {
        length: { el: document.getElementById('rule-length'), test: p => p.length >= 8 },
        upper: { el: document.getElementById('rule-upper'), test: p => /[A-Z]/.test(p) },
        lower: { el: document.getElementById('rule-lower'), test: p => /[a-z]/.test(p) },
        number: { el: document.getElementById('rule-number'), test: p => /[0-9]/.test(p) },
        special: { el: document.getElementById('rule-special'), test: p => /[^A-Za-z0-9]/.test(p) }
    };
    if (!pwd) return;
    pwd.addEventListener('input', function() {
        const p = this.value;
        let score = 0;
        for (const k in rules) {
            const pass = rules[k].test(p);
            rules[k].el.classList.toggle('pass', pass);
            rules[k].el.querySelector('i').className = pass ? 'bi bi-check-circle-fill' : 'bi bi-circle';
            if (pass) score++;
        }
        const cls = score <= 1 ? 'weak' : score <= 2 ? 'fair' : score <= 4 ? 'good' : score === 5 ? 'strong' : '';
        bar.innerHTML = score > 0 ? '<div class="bar ' + cls + '"></div>' : '';
        checkMatch();
    });
    confirm.addEventListener('input', checkMatch);
    function checkMatch() {
        if (!confirm.value) { matchMsg.innerHTML = ''; return; }
        matchMsg.innerHTML = confirm.value === pwd.value
            ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Passwords match</span>'
            : '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Passwords do not match</span>';
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/dvcaa_footer.php'; ?>
