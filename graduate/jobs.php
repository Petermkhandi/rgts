<?php
$pageTitle = 'Job Opportunities';
require_once __DIR__ . '/../includes/graduate_header.php';

$db = getDB();

$stmt = $db->query("SELECT * FROM job_feed WHERE status = 'active' AND deadline >= NOW() ORDER BY deadline ASC");
$jobs = $stmt->fetchAll();
?>

<div class="alert animate-in animate-delay-1" style="background:rgba(37,99,235,0.05); border-left:3px solid var(--rucu-primary)">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-info-circle-fill" style="color:var(--rucu-primary)"></i>
        <div class="small" style="font-weight:400">
            <strong>All jobs link directly to the source posting.</strong> Click <strong>Apply Now</strong> or <strong>View Details</strong> to open the original vacancy page and submit your application there.
        </div>
    </div>
</div>

<div class="card animate-in animate-delay-2">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-newspaper me-2" style="color:var(--rucu-accent)"></i>Latest Job Opportunities</h5>
        <span class="badge bg-primary" style="font-weight:500"><?= count($jobs) ?> Active Vacancies</span>
    </div>
    <div class="card-body">
        <?php if (count($jobs) > 0): ?>
        <div class="row g-3">
            <?php foreach ($jobs as $i => $job): 
                $days_left = max(0, (strtotime($job['deadline']) - time()) / 86400);
                $urgent = $days_left <= 7;
            ?>
            <div class="col-md-6">
                <div class="card h-100 animate-in border-0" style="animation-delay: <?= 0.03 * $i ?>s; background:#fff; border:1px solid var(--rucu-border); position:relative">
                    <?php if ($urgent): ?>
                    <span style="position:absolute;top:10px;right:10px;background:#fee2e2;color:#dc2626;font-size:0.65rem;font-weight:600;padding:2px 8px;border-radius:5px">URGENT</span>
                    <?php endif; ?>
                    <div class="card-body">
                        <h6 class="card-title mb-1" style="font-weight:600; font-size:0.88rem; color:var(--rucu-primary); line-height:1.3"><?= htmlspecialchars($job['title']) ?></h6>
                        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;margin-bottom:10px">
                            <span style="font-size:0.78rem;color:var(--rucu-text-light)"><i class="bi bi-building me-1"></i><?= htmlspecialchars($job['organization']) ?></span>
                            <?php if ($job['location']): ?>
                            <span style="font-size:0.78rem;color:var(--rucu-text-light)"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($job['location']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size:0.8rem;color:var(--rucu-text-light);line-height:1.5;margin-bottom:12px"><?= htmlspecialchars(substr($job['description'] ?? '', 0, 120)) ?>...</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span style="font-size:0.75rem;color:<?= $urgent ? '#dc2626' : '#6b7280' ?>"><i class="bi bi-calendar-event me-1"></i><?= formatDate($job['deadline']) ?></span>
                            <a href="<?= htmlspecialchars($job['source_url']) ?>" target="_blank" class="btn btn-sm" style="background:#059669; color:#fff; font-weight:500; border:none">
                                <?= $job['source_name'] === 'JobwebTanzania' ? 'View Details' : 'Apply Now' ?> <i class="bi bi-box-arrow-up-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox display-4 text-muted" style="opacity:0.3"></i>
            <p class="text-muted mt-3">No job opportunities available at the moment. Check back later!</p>
            <a href="https://fursa.co.tz/" target="_blank" class="btn btn-primary me-2">Browse Fursa.co.tz <i class="bi bi-box-arrow-up-right ms-1"></i></a>
            <a href="https://www.jobwebtanzania.com/" target="_blank" class="btn btn-outline-primary">Browse JobwebTanzania <i class="bi bi-box-arrow-up-right ms-1"></i></a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/graduate_footer.php'; ?>
