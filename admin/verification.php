<?php
$pageTitle = 'Verification Management';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();
$error = '';
$success = '';

// Handle manual verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $graduate_id = intval($_POST['graduate_id']);
        $status = sanitize($_POST['verification_status']);
        $notes = sanitize($_POST['notes']);
        
        require_once __DIR__ . '/../api/verification_engine.php';
        manualVerify($db, $graduate_id, $status, $notes);
        logActivity('admin', $_SESSION['admin_id'], 'Manual Verification', "Manually set graduate {$graduate_id} to {$status}");
        $success = 'Verification status updated successfully.';
    }
}

// Get verification logs with graduate info
$status_filter = $_GET['status'] ?? '';
$where = $status_filter ? "WHERE vl.verification_status = ?" : "";
$params = $status_filter ? [$status_filter] : [];

$stmt = $db->prepare("
    SELECT vl.*, g.reg_number, g.full_name, g.course, g.form4_index_number
    FROM verification_logs vl
    JOIN graduates g ON vl.graduate_id = g.id
    {$where}
    ORDER BY vl.date_checked DESC
    LIMIT 50
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get pending graduates
$stmt = $db->query("
    SELECT g.*, ed.employment_status, ed.company_name
    FROM graduates g
    LEFT JOIN employment_details ed ON g.id = ed.graduate_id
    WHERE g.id NOT IN (SELECT graduate_id FROM verification_logs WHERE verification_status = 'verified')
    AND ed.employment_status IS NOT NULL
");
$pending = $stmt->fetchAll();

// Stats
$total_verified = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'verified'")->fetchColumn();
$total_pending = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'pending'")->fetchColumn();
$total_not = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'not_verified'")->fetchColumn();
?>

<?php flashAlert(); ?>

<!-- Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card stat-card border-start border-4 border-success">
            <div class="card-body text-center">
                <h3 class="text-success"><?= $total_verified ?></h3>
                <p class="text-muted mb-0">Verified</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-4 border-warning">
            <div class="card-body text-center">
                <h3 class="text-warning"><?= $total_pending ?></h3>
                <p class="text-muted mb-0">Pending</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-start border-4 border-danger">
            <div class="card-body text-center">
                <h3 class="text-danger"><?= $total_not ?></h3>
                <p class="text-muted mb-0">Not Verified</p>
            </div>
        </div>
    </div>
</div>

<?php if (count($pending) > 0): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-warning bg-opacity-10">
        <h5 class="mb-0"><i class="bi bi-clock"></i> Pending Verification (<?= count($pending) ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reg No.</th>
                        <th>Name</th>
                        <th>Employment</th>
                        <th>Company</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $p): ?>
                    <tr>
                        <td><small><?= htmlspecialchars($p['reg_number']) ?></small></td>
                        <td><?= htmlspecialchars($p['full_name']) ?></td>
                        <td><?= getEmploymentBadge($p['employment_status']) ?></td>
                        <td><?= htmlspecialchars($p['company_name'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#verifyModal<?= $p['id'] ?>">
                                <i class="bi bi-check-circle"></i> Verify
                            </button>
                            
                            <!-- Verify Modal -->
                            <div class="modal fade" id="verifyModal<?= $p['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="POST">
                                        <?= csrfField() ?>
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Manual Verification</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="graduate_id" value="<?= $p['id'] ?>">
                                                <p><strong><?= htmlspecialchars($p['full_name']) ?></strong> - <?= htmlspecialchars($p['reg_number']) ?></p>
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="verification_status" class="form-select" required>
                                                        <option value="verified">Verified</option>
                                                        <option value="pending">Pending</option>
                                                        <option value="not_verified">Not Verified</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Notes</label>
                                                    <textarea name="notes" class="form-control" rows="3"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="verify" class="btn btn-success">Update Status</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

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
                        <th>Status</th>
                        <th>Source</th>
                        <th>NECTA</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><small><?= formatDateTime($log['date_checked']) ?></small></td>
                        <td><small><?= htmlspecialchars($log['reg_number']) ?></small></td>
                        <td><?= htmlspecialchars($log['full_name']) ?></td>
                        <td><?= getVerificationBadge($log['verification_status']) ?></td>
                        <td><?= ucfirst(str_replace('_', ' ', $log['verification_source'])) ?></td>
                        <td><?= ucfirst($log['necta_status'] ?? '-') ?></td>
                        <td><small><?= htmlspecialchars(substr($log['notes'] ?? '', 0, 40)) ?>...</small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
