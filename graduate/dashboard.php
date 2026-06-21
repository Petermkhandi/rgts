<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/graduate_header.php';

$db = getDB();
$graduate_id = $_SESSION['graduate_id'];

$stmt = $db->prepare("SELECT * FROM graduates WHERE id = ?");
$stmt->execute([$graduate_id]);
$graduate = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM employment_details WHERE graduate_id = ?");
$stmt->execute([$graduate_id]);
$employment = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM verification_logs WHERE graduate_id = ? ORDER BY date_checked DESC LIMIT 1");
$stmt->execute([$graduate_id]);
$verification = $stmt->fetch();

$stmt = $db->query("SELECT COUNT(*) as count FROM job_feed WHERE deadline >= CURDATE()");
$job_count = $stmt->fetch()['count'];

// Profile section POST handler
$profile_error = '';
$profile_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    if (empty($email)) {
        $profile_error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profile_error = 'Invalid email format.';
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
            }
        }
        $graduate = $db->prepare("SELECT * FROM graduates WHERE id = ?");
        $graduate->execute([$graduate_id]);
        $graduate = $graduate->fetch();
        $profile_success = true;
    }
}

// Employment section POST handler
$emp_error = '';
$emp_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_employment') {
    $status = sanitize($_POST['employment_status'] ?? '');
    $company = sanitize($_POST['company_name'] ?? '');
    $org_type = sanitize($_POST['organization_type'] ?? '');
    $job_title = sanitize($_POST['job_title'] ?? '');
    $salary = sanitize($_POST['salary_range'] ?? '');
    $start_date = sanitize($_POST['start_date'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    if (empty($status)) {
        $emp_error = 'Please select your employment status.';
    } elseif ($status === 'employed' && (empty($company) || empty($job_title))) {
        $emp_error = 'Company name and job title are required for employed status.';
    } else {
        try {
            $db->beginTransaction();
            if ($employment) {
                $stmt = $db->prepare("UPDATE employment_details SET employment_status = ?, company_name = ?, organization_type = ?, job_title = ?, salary_range = ?, start_date = ?, location = ? WHERE graduate_id = ?");
                $stmt->execute([$status, $company, $org_type, $job_title, $salary, $start_date, $location, $graduate_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO employment_details (graduate_id, employment_status, company_name, organization_type, job_title, salary_range, start_date, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$graduate_id, $status, $company, $org_type, $job_title, $salary, $start_date, $location]);
            }
            require_once __DIR__ . '/../api/verification_engine.php';
            runVerification($db, $graduate_id, $status);
            $db->commit();
            $employment = $db->prepare("SELECT * FROM employment_details WHERE graduate_id = ?");
            $employment->execute([$graduate_id]);
            $employment = $employment->fetch();
            $emp_success = true;
        } catch (Exception $e) {
            $db->rollBack();
            $emp_error = 'An error occurred. Please try again.';
        }
    }
}

$stmt = $db->prepare("SELECT * FROM verification_logs WHERE graduate_id = ? ORDER BY date_checked DESC");
$stmt->execute([$graduate_id]);
$v_logs = $stmt->fetchAll();

$stmt = $db->query("SELECT * FROM job_feed WHERE status = 'active' AND deadline >= NOW() ORDER BY deadline ASC");
$all_jobs = $stmt->fetchAll();

flashAlert();

// Calculate profile completion
$profile_complete = !empty($graduate['email']) && !empty($graduate['phone']);
$emp_complete = $employment && in_array($employment['employment_status'], ['employed', 'self_employed', 'unemployed', 'further_studies', 'seeking']);
$ver_complete = $verification && $verification['verification_status'] === 'verified';
$steps_done = ($profile_complete ? 1 : 0) + ($emp_complete ? 1 : 0) + ($ver_complete ? 1 : 0);
$progress_pct = round(($steps_done / 3) * 100);
?>

<!-- Progress Bar -->
<div class="card animate-in animate-delay-1 mb-4" style="background:linear-gradient(135deg,rgba(79,70,229,0.03),rgba(6,182,212,0.03))">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span style="font-weight:600;font-size:0.84rem;color:var(--rucu-text)">Profile Completion</span>
            <span style="font-weight:600;font-size:0.8rem;color:var(--rucu-primary)"><?= $steps_done ?>/3 &middot; <?= $progress_pct ?>%</span>
        </div>
        <div style="height:8px;background:var(--rucu-border);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?= $progress_pct ?>%;border-radius:4px;background:linear-gradient(90deg,var(--rucu-primary),#06b6d4);transition:width 0.6s ease"></div>
        </div>
        <div class="d-flex justify-content-between mt-2" style="font-size:0.72rem;color:var(--rucu-text-light)">
            <span><span style="color:<?= $profile_complete ? '#10b981' : '#94a3b8' ?>"><i class="bi bi-<?= $profile_complete ? 'check-circle-fill' : 'circle' ?>"></i></span> Profile</span>
            <span><span style="color:<?= $emp_complete ? '#10b981' : '#94a3b8' ?>"><i class="bi bi-<?= $emp_complete ? 'check-circle-fill' : 'circle' ?>"></i></span> Employment</span>
            <span><span style="color:<?= $ver_complete ? '#10b981' : '#94a3b8' ?>"><i class="bi bi-<?= $ver_complete ? 'check-circle-fill' : 'circle' ?>"></i></span> Verification</span>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6 animate-in animate-delay-1">
        <div class="stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 text-uppercase" style="font-weight:500;font-size:0.68rem;letter-spacing:0.5px">Employment</p>
                        <h5 class="mb-0" style="font-weight:600;color:var(--rucu-text)"><?= $employment ? ucfirst(str_replace('_', ' ', $employment['employment_status'])) : 'Not Set' ?></h5>
                    </div>
                    <div class="icon-box" style="background:rgba(79,70,229,0.1);color:var(--rucu-primary)"><i class="bi bi-briefcase"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 animate-in animate-delay-2">
        <div class="stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 text-uppercase" style="font-weight:500;font-size:0.68rem;letter-spacing:0.5px">Verification</p>
                        <h5 class="mb-0" style="font-weight:600;color:var(--rucu-text)"><?= $verification ? ucfirst($verification['verification_status']) : 'Pending' ?></h5>
                    </div>
                    <div class="icon-box" style="background:rgba(16,185,129,0.1);color:#10b981"><i class="bi bi-shield-check"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 animate-in animate-delay-3">
        <div class="stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 text-uppercase" style="font-weight:500;font-size:0.68rem;letter-spacing:0.5px">Jobs</p>
                        <h5 class="mb-0" style="font-weight:600;color:var(--rucu-text)"><?= $job_count ?></h5>
                    </div>
                    <div class="icon-box" style="background:rgba(6,182,212,0.1);color:#06b6d4"><i class="bi bi-newspaper"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 animate-in animate-delay-4">
        <div class="stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 text-uppercase" style="font-weight:500;font-size:0.68rem;letter-spacing:0.5px">Password</p>
                        <h5 class="mb-0" style="font-weight:600;color:var(--rucu-text)"><?= date('M d', strtotime($graduate['password_expiry_date'])) ?></h5>
                    </div>
                    <div class="icon-box" style="background:rgba(245,158,11,0.1);color:#f59e0b"><i class="bi bi-clock"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab Bar -->
<div class="d-flex gap-1 mb-4 animate-in animate-delay-1" style="background:var(--rucu-surface);border-radius:var(--rucu-radius);border:1px solid var(--rucu-border);padding:4px;overflow-x:auto">
    <button class="tab-btn active" data-tab="overview" onclick="switchTab('overview')" style="flex:1;padding:10px 16px;border:none;border-radius:var(--rucu-radius-sm);font-size:0.8rem;font-weight:500;color:var(--rucu-text-light);background:transparent;cursor:pointer;transition:all 0.2s ease;white-space:nowrap"><i class="bi bi-grid-1x2 me-1"></i> Overview</button>
    <button class="tab-btn" data-tab="profile" onclick="switchTab('profile')" style="flex:1;padding:10px 16px;border:none;border-radius:var(--rucu-radius-sm);font-size:0.8rem;font-weight:500;color:var(--rucu-text-light);background:transparent;cursor:pointer;transition:all 0.2s ease;white-space:nowrap"><i class="bi bi-person me-1"></i> Profile</button>
    <button class="tab-btn" data-tab="employment" onclick="switchTab('employment')" style="flex:1;padding:10px 16px;border:none;border-radius:var(--rucu-radius-sm);font-size:0.8rem;font-weight:500;color:var(--rucu-text-light);background:transparent;cursor:pointer;transition:all 0.2s ease;white-space:nowrap"><i class="bi bi-briefcase me-1"></i> Employment</button>
    <button class="tab-btn" data-tab="verification" onclick="switchTab('verification')" style="flex:1;padding:10px 16px;border:none;border-radius:var(--rucu-radius-sm);font-size:0.8rem;font-weight:500;color:var(--rucu-text-light);background:transparent;cursor:pointer;transition:all 0.2s ease;white-space:nowrap"><i class="bi bi-shield-check me-1"></i> Verification</button>
    <button class="tab-btn" data-tab="jobs" onclick="switchTab('jobs')" style="flex:1;padding:10px 16px;border:none;border-radius:var(--rucu-radius-sm);font-size:0.8rem;font-weight:500;color:var(--rucu-text-light);background:transparent;cursor:pointer;transition:all 0.2s ease;white-space:nowrap"><i class="bi bi-newspaper me-1"></i> Jobs</button>
</div>

<style>
.tab-btn.active { background:var(--rucu-primary) !important; color:#fff !important; box-shadow:0 2px 8px rgba(79,70,229,0.2); }
.tab-btn:hover:not(.active) { background:#f1f5f9 !important; }
.tab-pane { display:none; animation:fadeInUp 0.4s ease forwards; }
.tab-pane.active { display:block; }
</style>

<!-- Tab: Overview -->
<div id="tab-overview" class="tab-pane active">
    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-person-circle me-2" style="color:var(--rucu-accent)"></i>My Profile</h5>
                    <a href="#" onclick="switchTab('profile');return false" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-item"><span>Registration No</span><code><?= htmlspecialchars($graduate['reg_number']) ?></code></div>
                            <div class="info-item"><span>Full Name</span><code><?= htmlspecialchars($graduate['full_name']) ?></code></div>
                            <div class="info-item"><span>Course</span><code><?= htmlspecialchars($graduate['course']) ?></code></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item"><span>Email</span><code><?= htmlspecialchars($graduate['email'] ?? 'Not set') ?></code></div>
                            <div class="info-item"><span>Phone</span><code><?= htmlspecialchars($graduate['phone'] ?? 'Not set') ?></code></div>
                            <div class="info-item"><span>Graduation Year</span><code><?= $graduate['graduation_year'] ?></code></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-briefcase me-2" style="color:var(--rucu-accent)"></i>Employment</h5>
                    <a href="#" onclick="switchTab('employment');return false" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                </div>
                <div class="card-body">
                    <?php if ($employment): ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-item"><span>Status</span><?= getEmploymentBadge($employment['employment_status']) ?></div>
                            <div class="info-item"><span>Organization</span><code><?= htmlspecialchars($employment['company_name'] ?? '-') ?></code></div>
                            <div class="info-item"><span>Job Title</span><code><?= htmlspecialchars($employment['job_title'] ?? '-') ?></code></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item"><span>Location</span><code><?= htmlspecialchars($employment['location'] ?? '-') ?></code></div>
                            <div class="info-item"><span>Type</span><code><?= ucfirst($employment['organization_type'] ?? '-') ?></code></div>
                            <div class="info-item"><span>Updated</span><code><?= formatDate($employment['updated_at']) ?></code></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3">
                        <p class="text-muted mb-3">Employment details not set.</p>
                        <a href="#" onclick="switchTab('employment');return false" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Add Details</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card text-center">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-check me-2" style="color:var(--rucu-accent)"></i>Verification</h5></div>
                <div class="card-body">
                    <?php if ($verification): ?>
                        <div class="mb-2"><?= getVerificationBadge($verification['verification_status']) ?></div>
                        <p class="text-muted small mb-1" style="font-weight:400">via <?= ucfirst($verification['verification_source']) ?></p>
                        <p class="text-muted small" style="font-weight:400"><?= formatDate($verification['date_checked']) ?></p>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark mb-2">Pending</span>
                        <p class="text-muted small mb-0" style="font-weight:400">Update employment to trigger verification</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-lightning me-2" style="color:var(--rucu-accent)"></i>Quick Actions</h5></div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="#" onclick="switchTab('employment');return false" class="btn btn-outline-primary btn-sm"><i class="bi bi-briefcase"></i> Update Employment</a>
                        <a href="#" onclick="switchTab('verification');return false" class="btn btn-outline-primary btn-sm"><i class="bi bi-shield-check"></i> Verification History</a>
                        <a href="#" onclick="switchTab('jobs');return false" class="btn btn-outline-primary btn-sm"><i class="bi bi-newspaper"></i> View Jobs</a>
                        <a href="reset_password.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-key"></i> Change Password</a>
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-star me-2" style="color:var(--rucu-accent)"></i>Latest Jobs</h5></div>
                <div class="card-body">
                    <?php
                    $stmt3 = $db->query("SELECT * FROM job_feed WHERE deadline >= CURDATE() ORDER BY deadline ASC LIMIT 3");
                    $job_list = $stmt3->fetchAll();
                    foreach ($job_list as $i => $j): ?>
                    <div class="mb-3 pb-2<?= $i < count($job_list) - 1 ? ' border-bottom' : '' ?>" style="border-color:var(--rucu-border)!important">
                        <h6 style="font-size:0.84rem;font-weight:500"><?= htmlspecialchars($j['title']) ?></h6>
                        <small class="text-muted"><i class="bi bi-building me-1"></i><?= htmlspecialchars($j['organization']) ?></small><br>
                        <small class="text-danger"><i class="bi bi-calendar me-1"></i><?= formatDate($j['deadline']) ?></small>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($job_list)): ?><p class="text-muted small mb-0" style="font-weight:400">No jobs available.</p><?php endif; ?>
                    <a href="#" onclick="switchTab('jobs');return false" class="btn btn-primary btn-sm w-100 mt-2">View All Jobs</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab: Profile -->
<div id="tab-profile" class="tab-pane">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <div class="icon-box" style="background:rgba(79,70,229,0.1);color:var(--rucu-primary);width:36px;height:36px;font-size:0.95rem"><i class="bi bi-person-badge"></i></div>
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <?php if ($profile_error): ?><div class="alert alert-danger py-2"><?= $profile_error ?></div><?php endif; ?>
                    <?php if ($profile_success): ?><div class="alert alert-success py-2">Profile updated successfully!</div><?php endif; ?>
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
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
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
                        <div class="mb-4 text-center">
                            <img src="../uploads/<?= htmlspecialchars($graduate['profile_image']) ?>" alt="Profile" class="rounded-3" style="max-height:120px;box-shadow:var(--rucu-shadow);border:2px solid var(--rucu-border)">
                        </div>
                        <?php endif; ?>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab: Employment -->
<div id="tab-employment" class="tab-pane">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <div class="icon-box" style="background:rgba(79,70,229,0.1);color:var(--rucu-primary);width:36px;height:36px;font-size:0.95rem"><i class="bi bi-briefcase"></i></div>
                    <h5 class="mb-0">Employment Information</h5>
                </div>
                <div class="card-body">
                    <?php if ($emp_error): ?><div class="alert alert-danger py-2"><?= $emp_error ?></div><?php endif; ?>
                    <?php if ($emp_success): ?><div class="alert alert-success py-2">Employment information updated successfully!</div><?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_employment">
                        <div class="mb-4">
                            <label class="form-label">Employment Status <span style="color:#e74c3c">*</span></label>
                            <select name="employment_status" class="form-select" id="emp_status" required>
                                <option value="">-- Select Status --</option>
                                <option value="employed" <?= ($employment['employment_status'] ?? '') == 'employed' ? 'selected' : '' ?>>Employed</option>
                                <option value="self_employed" <?= ($employment['employment_status'] ?? '') == 'self_employed' ? 'selected' : '' ?>>Self Employed</option>
                                <option value="unemployed" <?= ($employment['employment_status'] ?? '') == 'unemployed' ? 'selected' : '' ?>>Unemployed</option>
                                <option value="further_studies" <?= ($employment['employment_status'] ?? '') == 'further_studies' ? 'selected' : '' ?>>Further Studies</option>
                                <option value="seeking" <?= ($employment['employment_status'] ?? '') == 'seeking' ? 'selected' : '' ?>>Actively Seeking</option>
                            </select>
                        </div>
                        <div id="emp_fields" style="display:<?= in_array($employment['employment_status'] ?? '', ['employed', 'self_employed']) ? 'block' : 'none' ?>">
                            <hr>
                            <h6 style="font-size:0.78rem;font-weight:600;color:var(--rucu-text);margin-bottom:14px;letter-spacing:0.3px"><i class="bi bi-building me-1" style="color:var(--rucu-accent)"></i> Employment Details</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Company / Organization</label>
                                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($employment['company_name'] ?? '') ?>" placeholder="e.g., Ruaha Catholic University">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Organization Type</label>
                                    <select name="organization_type" class="form-select">
                                        <option value="">-- Select --</option>
                                        <option value="government" <?= ($employment['organization_type'] ?? '') == 'government' ? 'selected' : '' ?>>Government</option>
                                        <option value="private" <?= ($employment['organization_type'] ?? '') == 'private' ? 'selected' : '' ?>>Private Sector</option>
                                        <option value="ngo" <?= ($employment['organization_type'] ?? '') == 'ngo' ? 'selected' : '' ?>>NGO</option>
                                        <option value="self_employed" <?= ($employment['organization_type'] ?? '') == 'self_employed' ? 'selected' : '' ?>>Self Employed</option>
                                        <option value="international" <?= ($employment['organization_type'] ?? '') == 'international' ? 'selected' : '' ?>>International</option>
                                        <option value="other" <?= ($employment['organization_type'] ?? '') == 'other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Job Title</label>
                                    <input type="text" name="job_title" class="form-control" value="<?= htmlspecialchars($employment['job_title'] ?? '') ?>" placeholder="e.g., Lecturer">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Salary Range (TZS/month)</label>
                                    <select name="salary_range" class="form-select">
                                        <option value="">-- Select --</option>
                                        <option value="200000-500000" <?= ($employment['salary_range'] ?? '') == '200000-500000' ? 'selected' : '' ?>>200,000 - 500,000</option>
                                        <option value="500000-800000" <?= ($employment['salary_range'] ?? '') == '500000-800000' ? 'selected' : '' ?>>500,000 - 800,000</option>
                                        <option value="800000-1200000" <?= ($employment['salary_range'] ?? '') == '800000-1200000' ? 'selected' : '' ?>>800,000 - 1,200,000</option>
                                        <option value="1200000-2000000" <?= ($employment['salary_range'] ?? '') == '1200000-2000000' ? 'selected' : '' ?>>1,200,000 - 2,000,000</option>
                                        <option value="2000000+" <?= ($employment['salary_range'] ?? '') == '2000000+' ? 'selected' : '' ?>>Above 2,000,000</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?= $employment['start_date'] ?? '' ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($employment['location'] ?? '') ?>" placeholder="e.g., Dar es Salaam">
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save Employment Details</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab: Verification -->
<div id="tab-verification" class="tab-pane">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-check me-2" style="color:var(--rucu-accent)"></i>Current Status</h5></div>
                <div class="card-body">
                    <?php if ($verification): ?>
                        <div class="mb-3"><?= getVerificationBadge($verification['verification_status']) ?></div>
                        <div class="info-item text-start"><span>Source</span><code><?= ucfirst($verification['verification_source']) ?></code></div>
                        <div class="info-item text-start"><span>NECTA</span><code><?= ucfirst($verification['necta_status'] ?? 'N/A') ?></code></div>
                        <div class="info-item text-start"><span>Checked</span><code><?= formatDateTime($verification['date_checked']) ?></code></div>
                    <?php else: ?>
                        <span class="badge bg-secondary mb-2">Not Yet Verified</span>
                        <p class="text-muted small mt-2 mb-0">Update employment to trigger verification.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-info-circle me-2" style="color:var(--rucu-accent)"></i>Info</h5></div>
                <div class="card-body">
                    <div class="info-item"><span>Form IV Index</span><code><?= htmlspecialchars($graduate['form4_index_number']) ?></code></div>
                    <div class="info-item"><span>Registration</span><code><?= htmlspecialchars($graduate['reg_number']) ?></code></div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history me-2" style="color:var(--rucu-accent)"></i>History</h5></div>
                <div class="card-body p-0">
                    <?php if (count($v_logs) > 0): ?>
                    <div class="p-3">
                        <?php foreach ($v_logs as $i => $log):
                            $is_last = $i === count($v_logs) - 1;
                            $dot = $log['verification_status'] === 'verified' ? '#10b981' : ($log['verification_status'] === 'not_verified' ? '#ef4444' : '#f59e0b');
                        ?>
                        <div class="d-flex gap-3" style="position:relative;padding-bottom:<?= $is_last ? '0' : '20' ?>px">
                            <div style="display:flex;flex-direction:column;align-items:center;width:20px;flex-shrink:0">
                                <div style="width:12px;height:12px;border-radius:50%;background:<?= $dot ?>;border:2px solid <?= $dot ?>;box-shadow:0 0 0 3px <?= $dot ?>15"></div>
                                <?php if (!$is_last): ?><div style="width:2px;flex:1;background:var(--rucu-border);margin-top:4px"></div><?php endif; ?>
                            </div>
                            <div style="flex:1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div><?= getVerificationBadge($log['verification_status']) ?> <span class="ms-2" style="font-size:0.78rem;color:var(--rucu-text-light)">via <?= ucfirst(str_replace('_', ' ', $log['verification_source'])) ?></span></div>
                                    <small style="color:var(--rucu-text-light);white-space:nowrap"><?= formatDateTime($log['date_checked']) ?></small>
                                </div>
                                <div style="margin-top:4px;font-size:0.8rem;color:var(--rucu-text-light)">NECTA: <?= ucfirst($log['necta_status'] ?? '-') ?><?= $log['notes'] ? ' &middot; Notes: '.htmlspecialchars($log['notes']) : '' ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-shield-exclamation display-4 text-muted" style="opacity:0.3"></i>
                        <p class="text-muted mt-3">No verification records found.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab: Jobs -->
<div id="tab-jobs" class="tab-pane">
    <div class="alert animate-in" style="background:rgba(79,70,229,0.04);border-left:3px solid var(--rucu-primary);padding:14px 18px;margin-bottom:20px">
        <div class="d-flex align-items-center gap-2" style="font-size:0.82rem">
            <i class="bi bi-info-circle-fill" style="color:var(--rucu-primary)"></i>
            <span>Click <strong>Apply Now</strong> to open the original vacancy page and submit your application there.</span>
        </div>
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-newspaper me-2" style="color:var(--rucu-accent)"></i>Job Opportunities</h5>
            <span class="badge" style="background:var(--rucu-primary)"><?= count($all_jobs) ?> Active</span>
        </div>
        <div class="card-body">
            <?php if (count($all_jobs) > 0): ?>
            <div class="row g-3">
                <?php foreach ($all_jobs as $i => $job):
                    $urgent = (strtotime($job['deadline']) - time()) / 86400 <= 7;
                ?>
                <div class="col-md-6">
                    <div class="card h-100 border-0" style="border:1px solid var(--rucu-border);position:relative">
                        <?php if ($urgent): ?><span style="position:absolute;top:10px;right:10px;background:#fee2e2;color:#dc2626;font-size:0.65rem;font-weight:600;padding:2px 8px;border-radius:5px">URGENT</span><?php endif; ?>
                        <div class="card-body">
                            <h6 style="font-weight:600;font-size:0.88rem;color:var(--rucu-primary)"><?= htmlspecialchars($job['title']) ?></h6>
                            <div style="display:flex;flex-wrap:wrap;gap:12px;margin:8px 0 10px">
                                <span style="font-size:0.78rem;color:var(--rucu-text-light)"><i class="bi bi-building me-1"></i><?= htmlspecialchars($job['organization']) ?></span>
                                <?php if ($job['location']): ?><span style="font-size:0.78rem;color:var(--rucu-text-light)"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($job['location']) ?></span><?php endif; ?>
                            </div>
                            <p style="font-size:0.8rem;color:var(--rucu-text-light);line-height:1.5;margin-bottom:12px"><?= htmlspecialchars(substr($job['description'] ?? '', 0, 120)) ?>...</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span style="font-size:0.75rem;color:<?= $urgent ? '#dc2626' : '#6b7280' ?>"><i class="bi bi-calendar-event me-1"></i><?= formatDate($job['deadline']) ?></span>
                                <a href="<?= htmlspecialchars($job['source_url']) ?>" target="_blank" class="btn btn-sm" style="background:#059669;color:#fff;font-weight:500;border:none"><?= $job['source_name'] === 'JobwebTanzania' ? 'View Details' : 'Apply Now' ?> <i class="bi bi-box-arrow-up-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox display-4 text-muted" style="opacity:0.3"></i>
                <p class="text-muted mt-3">No opportunities at the moment.</p>
                <a href="https://fursa.co.tz/" target="_blank" class="btn btn-primary me-2">Browse Fursa.co.tz <i class="bi bi-box-arrow-up-right ms-1"></i></a>
                <a href="https://www.jobwebtanzania.com/" target="_blank" class="btn btn-outline-primary">Browse JobwebTanzania <i class="bi bi-box-arrow-up-right ms-1"></i></a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('emp_status')?.addEventListener('change', function() {
    var f = document.getElementById('emp_fields');
    if (f) f.style.display = ['employed','self_employed'].includes(this.value) ? 'block' : 'none';
});
(function() {
    var hash = window.location.hash.replace('#', '');
    if (hash) switchTab(hash);
})();
</script>

<?php require_once __DIR__ . '/../includes/graduate_footer.php'; ?>
