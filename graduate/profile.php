<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
requireGraduateLogin();
$db = getDB();
$graduate_id = $_SESSION['graduate_id'];
$error = '';
$success = '';

$stmt = $db->prepare("SELECT * FROM graduates WHERE id = ?");
$stmt->execute([$graduate_id]);
$graduate = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    
    if (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        $stmt = $db->prepare("UPDATE graduates SET email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$email, $phone, $graduate_id]);
        
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, ALLOWED_EXTENSIONS) && $file['size'] <= MAX_FILE_SIZE) {
                $filename = 'profile_' . $graduate_id . '_' . time() . '.' . $ext;
                $upload_path = UPLOAD_DIR . '/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $stmt = $db->prepare("UPDATE graduates SET profile_image = ? WHERE id = ?");
                    $stmt->execute([$filename, $graduate_id]);
                }
            } else {
                $error = 'Invalid file type or file too large (max 2MB).';
            }
        }
        
        if (!$error) {
            logActivity('graduate', $graduate_id, 'Profile Update', 'Updated profile information');
            setFlash('success', 'Profile updated successfully.');
            header('Location: profile.php');
            exit();
        }
    }
}

require_once __DIR__ . '/../includes/graduate_header.php';
flashAlert();
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card animate-in animate-delay-1">
            <div class="card-header d-flex align-items-center gap-2">
                <div class="icon-box" style="background:rgba(37,99,235,0.1);color:var(--rucu-primary);width:36px;height:36px;font-size:0.95rem">
                    <i class="bi bi-person-badge"></i>
                </div>
                <h5 class="mb-0">Profile Information</h5>
            </div>
            <div class="card-body">
                <div style="background:#f8fafc;border-radius:var(--rucu-radius-sm);padding:16px 20px;margin-bottom:20px">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-item"><span>Registration Number</span><code><?= htmlspecialchars($graduate['reg_number']) ?></code></div>
                            <div class="info-item"><span>Full Name</span><code><?= htmlspecialchars($graduate['full_name']) ?></code></div>
                            <div class="info-item"><span>Course</span><code><?= htmlspecialchars($graduate['course']) ?></code></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item"><span>Graduation Year</span><code><?= $graduate['graduation_year'] ?></code></div>
                            <div class="info-item"><span>Form IV Index</span><code><?= htmlspecialchars($graduate['form4_index_number']) ?></code></div>
                            <div class="info-item"><span>Password Expires</span><code><?= formatDate($graduate['password_expiry_date']) ?></code></div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    
                    <h6 style="font-size:0.78rem;font-weight:600;color:var(--rucu-text);margin-bottom:14px;letter-spacing:0.3px"><i class="bi bi-pencil me-1" style="color:var(--rucu-accent)"></i> Edit Contact Details</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($graduate['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($graduate['phone'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Profile Image</label>
                        <input type="file" name="profile_image" class="form-control" accept="image/*">
                        <div class="form-text">JPG, PNG, GIF (max 2MB)</div>
                    </div>
                    
                    <?php if ($graduate['profile_image']): ?>
                    <div class="mb-4 text-center animate-in">
                        <img src="../uploads/<?= htmlspecialchars($graduate['profile_image']) ?>" alt="Profile" class="rounded-3" style="max-height:120px;box-shadow:var(--rucu-shadow);border:2px solid var(--rucu-border)">
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Changes</button>
                        <a href="dashboard.php" class="btn btn-outline-primary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/graduate_footer.php'; ?>
