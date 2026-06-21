<?php
$pageTitle = 'Job Feed Management';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();
$success = '';
$error = '';

// Handle manual job add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_job'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $title = sanitize($_POST['title']);
        $org = sanitize($_POST['organization']);
        $location = sanitize($_POST['location']);
        $desc = sanitize($_POST['description']);
        $deadline = sanitize($_POST['deadline']);
        $url = sanitize($_POST['source_url']);
        $source = sanitize($_POST['source_name']);
        
        $stmt = $db->prepare("INSERT INTO job_feed (title, organization, location, description, deadline, source_url, source_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$title, $org, $location, $desc, $deadline, $url, $source]);
        logActivity('admin', $_SESSION['admin_id'], 'Add Job', "Added job: {$title}");
        $success = 'Job added successfully.';
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $id = intval($_POST['job_id']);
        $stmt = $db->prepare("DELETE FROM job_feed WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Job deleted successfully.';
    }
}

// Handle delete all expired
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_expired'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $stmt = $db->prepare("DELETE FROM job_feed WHERE status = 'expired'");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        $success = "{$deleted} expired job(s) deleted successfully.";
    }
}

// Trigger scraper
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scrape_jobs'])) {
    require_once __DIR__ . '/../jobs_feed/scraper.php';
    $result = scrapeJobs();
    $success = "Job feed updated. {$result['added']} new jobs added from {$result['sources']} sources, {$result['total']} total, {$result['active']} active.";
}

// Mark expired jobs
$db->exec("UPDATE job_feed SET status = 'expired' WHERE deadline < NOW() AND status = 'active'");

// Get all jobs
$jobs = $db->query("SELECT * FROM job_feed ORDER BY deadline ASC")->fetchAll();

// Stats
$active_jobs = $db->query("SELECT COUNT(*) FROM job_feed WHERE status = 'active'")->fetchColumn();
$expired_jobs = $db->query("SELECT COUNT(*) FROM job_feed WHERE status = 'expired'")->fetchColumn();
?>

<?php flashAlert(); ?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-start border-4 border-success">
            <div class="card-body text-center">
                <h3 class="text-success"><?= $active_jobs ?></h3>
                <p class="text-muted mb-0">Active Jobs</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-4 border-danger">
            <div class="card-body text-center">
                <h3 class="text-danger"><?= $expired_jobs ?></h3>
                <p class="text-muted mb-0">Expired Jobs</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-4 border-info">
            <div class="card-body text-center">
                <h3 class="text-info"><?= count($jobs) ?></h3>
                <p class="text-muted mb-0">Total in Feed</p>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mb-4">
    <form method="POST" class="d-inline">
        <?= csrfField() ?>
        <button type="submit" name="scrape_jobs" class="btn btn-rucu"><i class="bi bi-arrow-clockwise"></i> Refresh Job Feed</button>
    </form>
    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addJobModal">
        <i class="bi bi-plus-circle"></i> Add Job Manually
    </button>
    <form method="POST" class="d-inline" onsubmit="return confirm('Delete all expired jobs? This action cannot be undone.')">
        <?= csrfField() ?>
        <button type="submit" name="delete_expired" class="btn btn-outline-danger"><i class="bi bi-trash-fill"></i> Delete Expired</button>
    </form>
</div>

<!-- Add Job Modal -->
<div class="modal fade" id="addJobModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Job Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Organization</label>
                        <input type="text" name="organization" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deadline</label>
                        <input type="datetime-local" name="deadline" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Source URL</label>
                        <input type="url" name="source_url" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Source Name</label>
                        <input type="text" name="source_name" class="form-control" value="Manual Entry">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_job" class="btn btn-success">Add Job</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-newspaper"></i> Job Listings</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Organization</th>
                        <th>Location</th>
                        <th>Deadline</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): 
                        $status = $job['status'] ?? 'active';
                        $statusClass = $status === 'active' ? 'bg-success' : 'bg-secondary';
                        $statusLabel = $status === 'active' ? 'Active' : 'Expired';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($job['title']) ?></td>
                        <td><?= htmlspecialchars($job['organization']) ?></td>
                        <td><?= htmlspecialchars($job['location'] ?? '-') ?></td>
                        <td><?= formatDate($job['deadline']) ?></td>
                        <td><?= htmlspecialchars($job['source_name']) ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                        <td>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this job?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                <button type="submit" name="delete_job" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php if ($job['source_url']): ?>
                            <a href="<?= htmlspecialchars($job['source_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
