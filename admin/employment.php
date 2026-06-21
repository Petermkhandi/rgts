<?php
$pageTitle = 'Employment Data';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Filters
$status = $_GET['status'] ?? '';
$org_type = $_GET['org_type'] ?? '';

$where = [];
$params = [];
if ($status) { $where[] = "ed.employment_status = ?"; $params[] = $status; }
if ($org_type) { $where[] = "ed.organization_type = ?"; $params[] = $org_type; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT g.reg_number, g.full_name, g.course, g.graduation_year, ed.*
    FROM employment_details ed
    JOIN graduates g ON ed.graduate_id = g.id
    {$whereSQL}
    ORDER BY ed.updated_at DESC
");
$stmt->execute($params);
$employment_data = $stmt->fetchAll();

$org_stats = $db->query("
    SELECT organization_type, COUNT(*) as count FROM employment_details GROUP BY organization_type
")->fetchAll();
?>

<div class="row g-2 mb-3 no-print">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body py-2 px-3 d-flex align-items-center gap-2 flex-wrap">
                <i class="bi bi-printer-fill text-primary fs-5"></i>
                <span class="fw-semibold text-muted me-1">Print:</span>
                <a href="print_employment.php?status=" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    <i class="bi bi-file-earmark-pdf"></i> All Records
                </a>
                <div class="vr d-none d-sm-inline"></div>
                <a href="print_employment.php?status=employed" class="btn btn-sm btn-outline-success rounded-pill px-3">
                    <i class="bi bi-briefcase"></i> Employed
                </a>
                <a href="print_employment.php?status=self_employed" class="btn btn-sm btn-outline-info rounded-pill px-3">
                    <i class="bi bi-person-badge"></i> Self Employed
                </a>
                <a href="print_employment.php?status=unemployed" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                    <i class="bi bi-person-x"></i> Unemployed
                </a>
                <a href="print_employment.php?status=further_studies" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    <i class="bi bi-mortarboard"></i> Further Studies
                </a>
                <a href="print_employment.php?status=seeking" class="btn btn-sm btn-outline-warning rounded-pill px-3">
                    <i class="bi bi-search"></i> Seeking
                </a>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .topbar, footer, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; break-inside: avoid; }
    body { background: #fff !important; }
    .table { font-size: 0.85rem; }
}
</style>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-briefcase"></i> Employment Records (<?= count($employment_data) ?>)</h5>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="filter_status" onchange="window.location.href='employment.php?status='+this.value">
                <option value="">All Status</option>
                <option value="employed" <?= $status === 'employed' ? 'selected' : '' ?>>Employed</option>
                <option value="self_employed" <?= $status === 'self_employed' ? 'selected' : '' ?>>Self Employed</option>
                <option value="unemployed" <?= $status === 'unemployed' ? 'selected' : '' ?>>Unemployed</option>
                <option value="further_studies" <?= $status === 'further_studies' ? 'selected' : '' ?>>Further Studies</option>
                <option value="seeking" <?= $status === 'seeking' ? 'selected' : '' ?>>Seeking</option>
            </select>
            <?php if ($status): ?>
            <a href="employment.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="emp_table">
                <thead class="table-light">
                    <tr>
                        <th>Reg No.</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Status</th>
                        <th>Organization</th>
                        <th>Type</th>
                        <th>Job Title</th>
                        <th>Location</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employment_data as $e): ?>
                    <tr>
                        <td><small><?= htmlspecialchars($e['reg_number']) ?></small></td>
                        <td><?= htmlspecialchars($e['full_name']) ?></td>
                        <td><small><?= htmlspecialchars($e['course']) ?></small></td>
                        <td><?= $e['graduation_year'] ?></td>
                        <td><?= getEmploymentBadge($e['employment_status']) ?></td>
                        <td><?= htmlspecialchars($e['company_name'] ?? '-') ?></td>
                        <td><?= ucfirst($e['organization_type'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($e['job_title'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($e['location'] ?? '-') ?></td>
                        <td><small><?= formatDate($e['updated_at']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
