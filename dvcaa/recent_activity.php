<?php
$pageTitle = 'Recent Activity';
require_once __DIR__ . '/../includes/dvcaa_header.php';

$db = getDB();

// Recent graduates
$stmt = $db->query("SELECT g.*, ed.employment_status, vl.verification_status FROM graduates g LEFT JOIN employment_details ed ON g.id = ed.graduate_id LEFT JOIN verification_logs vl ON g.id = vl.graduate_id ORDER BY g.created_at DESC LIMIT 20");
$recent = $stmt->fetchAll();

// Recent verification activity
$stmt = $db->query("SELECT vl.*, g.full_name, g.reg_number FROM verification_logs vl JOIN graduates g ON vl.graduate_id = g.id ORDER BY vl.date_checked DESC LIMIT 20");
$recent_verifications = $stmt->fetchAll();
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Graduates</h5>
        <span class="badge bg-primary">Last 20</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reg No.</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Status</th>
                        <th>Verified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><small><?= htmlspecialchars($r['reg_number']) ?></small></td>
                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                        <td><small><?= htmlspecialchars($r['course']) ?></small></td>
                        <td><?= $r['graduation_year'] ?></td>
                        <td><?= getEmploymentBadge($r['employment_status'] ?? 'unemployed') ?></td>
                        <td><?= getVerificationBadge($r['verification_status'] ?? 'pending') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-shield-check"></i> Recent Verification Activity</h5>
        <span class="badge bg-info">Last 20</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Reg No.</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th>Date</th>
                        <th>Checked By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_verifications as $v): ?>
                    <tr>
                        <td><small><?= htmlspecialchars($v['reg_number']) ?></small></td>
                        <td><?= htmlspecialchars($v['full_name']) ?></td>
                        <td><?= getVerificationBadge($v['verification_status']) ?></td>
                        <td><small><?= ucfirst(str_replace('_', ' ', $v['verification_source'])) ?></small></td>
                        <td><small><?= formatDate($v['date_checked']) ?></small></td>
                        <td><small><?= htmlspecialchars($v['checked_by']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dvcaa_footer.php'; ?>
