<?php
$pageTitle = 'SIMS Integration';
require_once __DIR__ . '/../includes/admin_header.php';

require_once __DIR__ . '/../api/sims_api.php';

$sync_result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_sims'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        try {
            $sync_type = sanitize($_POST['sync_type'] ?? 'incremental');
            $sync_result = syncFromSIMS($sync_type);
            logActivity('admin', $_SESSION['admin_id'], 'SIMS Sync', "Performed {$sync_type} sync: {$sync_result['added']} added, {$sync_result['updated']} updated");
        } catch (Exception $e) {
            $error = 'Sync failed: ' . $e->getMessage();
        }
    }
}

// Get sync history
$db = getDB();
$sync_logs = $db->query("SELECT * FROM sims_sync_log ORDER BY started_at DESC LIMIT 10")->fetchAll();

// Get current graduate count
$total = $db->query("SELECT COUNT(*) FROM graduates")->fetchColumn();
?>

<?php flashAlert(); ?>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Sync from SIMS</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Synchronize graduate data from the RUCU Student Information Management System (SIMS).</p>
                <p><strong>Total Graduates in System:</strong> <?= $total ?></p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($sync_result): ?>
                    <div class="alert alert-success">
                        <strong>Sync Complete!</strong><br>
                        Records Processed: <?= $sync_result['processed'] ?><br>
                        New Records Added: <?= $sync_result['added'] ?><br>
                        Records Updated: <?= $sync_result['updated'] ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Sync Type</label>
                        <select name="sync_type" class="form-select">
                            <option value="incremental">Incremental (New & Updated Only)</option>
                            <option value="full">Full Sync (All Records)</option>
                        </select>
                    </div>
                    <button type="submit" name="sync_sims" class="btn btn-rucu" onclick="this.disabled=true;this.innerHTML='<span class=\'spinner-border spinner-border-sm\'></span> Syncing...'">
                        <i class="bi bi-arrow-clockwise"></i> Sync Now
                    </button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> SIMS Connection Info</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><td class="text-muted">Source:</td><td>RUCU SIMS Database</td></tr>
                    <tr><td class="text-muted">Mode:</td><td>Read Only</td></tr>
                    <tr><td class="text-muted">Fields Synced:</td><td>Reg No, Name, Email, Phone, Course, Year, Form IV Index</td></tr>
                    <tr><td class="text-muted">Password:</td><td>Not overwritten on sync</td></tr>
                </table>
                <div class="alert alert-info small">
                    <i class="bi bi-lightbulb"></i> <strong>Note:</strong> SIMS is treated as a read-only data source. Student passwords and employment data are not affected by sync.
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Sync History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Processed</th>
                                <th>Added</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sync_logs as $log): ?>
                            <tr>
                                <td><small><?= formatDateTime($log['started_at']) ?></small></td>
                                <td><?= ucfirst($log['sync_type']) ?></td>
                                <td><?= $log['records_processed'] ?></td>
                                <td><?= $log['records_added'] ?></td>
                                <td>
                                    <?php
                                    $badge = match($log['status']) {
                                        'success' => 'bg-success',
                                        'partial' => 'bg-warning',
                                        'failed' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= ucfirst($log['status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sync_logs)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No sync history</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Graduates by Year</h5>
            </div>
            <div class="card-body">
                <?php
                $by_year = $db->query("SELECT graduation_year, COUNT(*) as count FROM graduates GROUP BY graduation_year ORDER BY graduation_year DESC")->fetchAll();
                foreach ($by_year as $year):
                ?>
                <div class="d-flex justify-content-between mb-2">
                    <span><?= $year['graduation_year'] ?></span>
                    <span class="badge bg-primary"><?= $year['count'] ?> graduates</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
