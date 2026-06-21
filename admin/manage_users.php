<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
requireAdminLogin();

$db = getDB();
$admin_id = $_SESSION['admin_id'];

// Add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $role = sanitize($_POST['role']);
        $errors = [];

        if (empty($name)) $errors[] = 'Name is required';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
        if (!in_array($role, ['super_admin', 'admin', 'staff', 'dvcaa'])) $errors[] = 'Invalid role';

        $stmt = $db->prepare("SELECT id FROM admin_users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'Email already in use';

        if (empty($errors)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO admin_users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashed, $role]);
            logActivity('admin', $admin_id, 'user_added', "Added user: $name ($email) as $role");
            setFlash('success', 'User added successfully');
            header('Location: manage_users.php');
            exit();
        } else {
            setFlash('error', implode(', ', $errors));
        }
    }
}

// Edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $uid = intval($_POST['user_id']);
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);

        if ($uid === $admin_id) {
            setFlash('error', 'You cannot change your own role here. Use Settings.');
            header('Location: manage_users.php');
            exit();
        }

        $stmt = $db->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $uid]);
        if ($stmt->fetch()) {
            setFlash('error', 'Email already in use');
        } else {
            $stmt = $db->prepare("UPDATE admin_users SET name = ?, email = ?, role = ? WHERE id = ?");
            $stmt->execute([$name, $email, $role, $uid]);
            logActivity('admin', $admin_id, 'user_updated', "Updated user ID: $uid");
            setFlash('success', 'User updated successfully');
        }
        header('Location: manage_users.php');
        exit();
    }
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $uid = intval($_POST['user_id']);
        if ($uid === $admin_id) {
            setFlash('error', 'You cannot delete yourself');
        } else {
            $stmt = $db->prepare("DELETE FROM admin_users WHERE id = ?");
            $stmt->execute([$uid]);
            logActivity('admin', $admin_id, 'user_deleted', "Deleted user ID: $uid");
            setFlash('success', 'User deleted successfully');
        }
        header('Location: manage_users.php');
        exit();
    }
}

// Reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $uid = intval($_POST['user_id']);
        $new_pass = password_hash('Rucu@2026', PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
        $stmt->execute([$new_pass, $uid]);
        logActivity('admin', $admin_id, 'user_password_reset', "Reset password for user ID: $uid");
        setFlash('success', 'Password reset to Rucu@2026');
        header('Location: manage_users.php');
        exit();
    }
}

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/admin_header.php';

$users = $db->query("SELECT id, name, email, role, last_login, created_at FROM admin_users ORDER BY created_at ASC")->fetchAll();
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-people-fill"></i> System Users (<?= count($users) ?>)</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus-lg"></i> Add User</button>
    </div>
    <div class="card-body p-0">
        <?php flashAlert(); ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Last Login</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($users as $u): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($u['name']) ?> <?= $u['id'] == $admin_id ? '<span class="badge bg-info">You</span>' : '' ?></td>
                        <td><small><?= htmlspecialchars($u['email']) ?></small></td>
                        <td><span class="badge bg-<?= match($u['role']) { 'super_admin' => 'danger', 'admin' => 'primary', 'staff' => 'secondary', 'dvcaa' => 'dark' } ?>"><?= ucfirst(str_replace('_', ' ', $u['role'])) ?></span></td>
                        <td><small><?= $u['last_login'] ? formatDate($u['last_login']) : 'Never' ?></small></td>
                        <td><small><?= formatDate($u['created_at']) ?></small></td>
                        <td>
                            <?php if ($u['id'] != $admin_id): ?>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetPwdModal<?= $u['id'] ?>" title="Reset Password"><i class="bi bi-key"></i></button>
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?= $u['id'] ?>" title="Delete"><i class="bi bi-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <div class="modal-content">
                                    <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Name</label>
                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($u['name']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <select name="role" class="form-select" required>
                                                <option value="super_admin" <?= $u['role'] == 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                                <option value="admin" <?= $u['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                                <option value="staff" <?= $u['role'] == 'staff' ? 'selected' : '' ?>>Staff</option>
                                                <option value="dvcaa" <?= $u['role'] == 'dvcaa' ? 'selected' : '' ?>>DVCAA</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="edit_user" class="btn btn-primary">Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Reset Password Modal -->
                    <div class="modal fade" id="resetPwdModal<?= $u['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <div class="modal-content">
                                    <div class="modal-header"><h5 class="modal-title">Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <p>Reset password for <strong><?= htmlspecialchars($u['name']) ?></strong>?</p>
                                        <p class="small text-muted">Password will be reset to <strong>Rucu@2026</strong></p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="reset_password" class="btn btn-warning">Reset</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Delete Modal -->
                    <div class="modal fade" id="deleteUserModal<?= $u['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <form method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <div class="modal-content">
                                    <div class="modal-header"><h5 class="modal-title">Delete User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <p class="text-danger">Delete <strong><?= htmlspecialchars($u['name']) ?></strong>?</p>
                                        <p class="small text-muted">This action cannot be undone.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="text" name="password" class="form-control" value="Rucu@2026" required>
                        <small class="text-muted">Default password: Rucu@2026</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                            <option value="dvcaa">DVCAA</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
