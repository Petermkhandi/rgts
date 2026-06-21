<?php
$pageTitle = 'Verification Status';
require_once __DIR__ . '/../includes/graduate_header.php';

$db = getDB();
$graduate_id = $_SESSION['graduate_id'];

$stmt = $db->prepare("SELECT form4_index_number, reg_number FROM graduates WHERE id = ?");
$stmt->execute([$graduate_id]);
$graduate = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM verification_logs WHERE graduate_id = ? ORDER BY date_checked DESC");
$stmt->execute([$graduate_id]);
$logs = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM verification_logs WHERE graduate_id = ? ORDER BY date_checked DESC LIMIT 1");
$stmt->execute([$graduate_id]);
$current_verification = $stmt->fetch();
?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card animate-in animate-delay-1 text-center">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-shield-check me-2" style="color:var(--rucu-accent)"></i>Current Status</h5>
            </div>
            <div class="card-body">
                <?php if ($current_verification): ?>
                    <div class="mb-3"><?= getVerificationBadge($current_verification['verification_status']) ?></div>
                    <div class="info-item text-start"><span>Source</span><code><?= ucfirst($current_verification['verification_source']) ?></code></div>
                    <div class="info-item text-start"><span>NECTA Status</span><code><?= ucfirst($current_verification['necta_status'] ?? 'N/A') ?></code></div>
                    <div class="info-item text-start"><span>Checked</span><code><?= formatDateTime($current_verification['date_checked']) ?></code></div>
                <?php else: ?>
                    <span class="badge bg-secondary mb-2">Not Yet Verified</span>
                    <p class="text-muted small mt-2 mb-0">Update your employment details to trigger verification.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card animate-in animate-delay-2 mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2" style="color:var(--rucu-accent)"></i>Verification Info</h5>
            </div>
            <div class="card-body">
                <div class="info-item"><span>Form IV Index</span><code><?= htmlspecialchars($graduate['form4_index_number']) ?></code></div>
                <div class="info-item"><span>Registration</span><code><?= htmlspecialchars($graduate['reg_number']) ?></code></div>
                <hr style="border-color:var(--rucu-border)">
                <p class="small text-muted mb-1">Verification uses:</p>
                <ul class="small text-muted mb-0 ps-3">
                    <li>NECTA Database (Form IV Index)</li>
                    <li>Employer Records Simulation</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card animate-in animate-delay-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2" style="color:var(--rucu-accent)"></i>Verification History</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($logs) > 0): ?>
                <div class="p-3">
                    <?php $v_count = count($logs); ?>
                    <?php foreach ($logs as $i => $log): 
                        $is_last = $i === $v_count - 1;
                        $dot_color = $log['verification_status'] === 'verified' ? '#10b981' : ($log['verification_status'] === 'not_verified' ? '#ef4444' : '#f59e0b');
                    ?>
                    <div class="d-flex gap-3" style="position:relative; padding-bottom: <?= $is_last ? '0' : '20' ?>px">
                        <div style="display:flex; flex-direction:column; align-items:center; width:20px; flex-shrink:0">
                            <div style="width:12px;height:12px;border-radius:50%;background:<?= $dot_color ?>;border:2px solid <?= $dot_color ?>; box-shadow: 0 0 0 3px <?= $dot_color ?>15"></div>
                            <?php if (!$is_last): ?>
                            <div style="width:2px;flex:1;background:var(--rucu-border);margin-top:4px"></div>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;padding-bottom: <?= $is_last ? '0' : '4' ?>px">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span style="font-weight:500;font-size:0.84rem;color:var(--rucu-text)"><?= getVerificationBadge($log['verification_status']) ?></span>
                                    <span class="ms-2" style="font-size:0.78rem;color:var(--rucu-text-light)">via <?= ucfirst(str_replace('_', ' ', $log['verification_source'])) ?></span>
                                </div>
                                <small style="color:var(--rucu-text-light);white-space:nowrap;margin-left:12px"><?= formatDateTime($log['date_checked']) ?></small>
                            </div>
                            <div style="margin-top:4px;font-size:0.8rem;color:var(--rucu-text-light)">
                                <span>NECTA: <?= ucfirst($log['necta_status'] ?? '-') ?></span>
                                <?php if ($log['notes']): ?>
                                <span class="ms-3">Notes: <?= htmlspecialchars($log['notes']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-shield-exclamation display-4 text-muted" style="opacity:0.3"></i>
                    <p class="text-muted mt-3">No verification records found.</p>
                    <a href="employment.php" class="btn btn-primary btn-sm">Update Employment to Trigger</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/graduate_footer.php'; ?>
