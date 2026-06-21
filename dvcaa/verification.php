<?php
$pageTitle = 'Verification Overview';
require_once __DIR__ . '/../includes/dvcaa_header.php';

$db = getDB();

$status_filter = $_GET['status'] ?? '';
$where = $status_filter ? "WHERE vl.verification_status = ?" : "";
$params = $status_filter ? [$status_filter] : [];

$stmt = $db->prepare("
    SELECT vl.*, g.reg_number, g.full_name, g.course
    FROM verification_logs vl
    JOIN graduates g ON vl.graduate_id = g.id
    {$where}
    ORDER BY vl.date_checked DESC
    LIMIT 50
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$verified = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'verified'")->fetchColumn();
$pending = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'pending'")->fetchColumn();
$not_verified = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'not_verified'")->fetchColumn();
$total = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs")->fetchColumn();
?>
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-start border-4 border-success text-center">
            <div class="card-body">
                <h3 class="text-success mb-0"><?= $verified ?></h3>
                <small class="text-muted">Verified</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-start border-4 border-warning text-center">
            <div class="card-body">
                <h3 class="text-warning mb-0"><?= $pending ?></h3>
                <small class="text-muted">Pending</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-start border-4 border-danger text-center">
            <div class="card-body">
                <h3 class="text-danger mb-0"><?= $not_verified ?></h3>
                <small class="text-muted">Not Verified</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-start border-4 border-info text-center">
            <div class="card-body">
                <h3 class="text-info mb-0"><?= $total ?></h3>
                <small class="text-muted">Total Checked</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="verified" <?= $status_filter == 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="not_verified" <?= $status_filter == 'not_verified' ? 'selected' : '' ?>>Not Verified</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                <a href="verification.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Verification History</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Reg No.</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><small><?= formatDateTime($log['date_checked']) ?></small></td>
                        <td><small><?= htmlspecialchars($log['reg_number']) ?></small></td>
                        <td><?= htmlspecialchars($log['full_name']) ?></td>
                        <td><small><?= htmlspecialchars($log['course']) ?></small></td>
                        <td><?= getVerificationBadge($log['verification_status']) ?></td>
                        <td><small><?= ucfirst(str_replace('_', ' ', $log['verification_source'])) ?></small></td>
                        <td><small><?= htmlspecialchars(substr($log['notes'] ?? '', 0, 50)) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No verification records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dvcaa_footer.php'; ?>
