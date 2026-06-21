<?php
$pageTitle = 'Employment Information';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
requireGraduateLogin();
$db = getDB();
$graduate_id = $_SESSION['graduate_id'];
$error = '';
$success = '';

$stmt = $db->prepare("SELECT * FROM employment_details WHERE graduate_id = ?");
$stmt->execute([$graduate_id]);
$employment = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = sanitize($_POST['employment_status'] ?? '');
    $company = sanitize($_POST['company_name'] ?? '');
    $org_type = sanitize($_POST['organization_type'] ?? '');
    $job_title = sanitize($_POST['job_title'] ?? '');
    $salary = sanitize($_POST['salary_range'] ?? '');
    $start_date = sanitize($_POST['start_date'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    
    if (empty($status)) {
        $error = 'Please select your employment status.';
    } else {
        if ($status === 'employed' && (empty($company) || empty($job_title))) {
            $error = 'Company name and job title are required for employed status.';
        } else {
            $db->beginTransaction();
            try {
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
                logActivity('graduate', $graduate_id, 'Employment Update', 'Updated employment status to: ' . $status);
                setFlash('success', 'Employment information updated successfully. Verification is in progress.');
                header('Location: employment.php');
                exit();
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'An error occurred. Please try again.';
            }
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
                    <i class="bi bi-briefcase"></i>
                </div>
                <h5 class="mb-0">Update Employment Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrfField() ?>
                    
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
                    
                    <div id="employment_fields" class="animate-in" style="display: <?= in_array($employment['employment_status'] ?? '', ['employed', 'self_employed']) ? 'block' : 'none' ?>">
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
                                    <option value="">-- Select Type --</option>
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
                                    <option value="">-- Select Range --</option>
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
                        <a href="dashboard.php" class="btn btn-outline-primary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('emp_status').addEventListener('change', function() {
    const fields = document.getElementById('employment_fields');
    if (['employed', 'self_employed'].includes(this.value)) {
        fields.style.display = 'block';
        fields.classList.remove('animate-in');
        void fields.offsetWidth;
        fields.classList.add('animate-in');
    } else {
        fields.style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/graduate_footer.php'; ?>
