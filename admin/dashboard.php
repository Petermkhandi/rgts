<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Get statistics
$total_graduates = $db->query("SELECT COUNT(*) FROM graduates")->fetchColumn();
$employed = $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status IN ('employed', 'self_employed')")->fetchColumn();
$unemployed = $db->query("SELECT COUNT(*) FROM graduates WHERE id NOT IN (SELECT graduate_id FROM employment_details WHERE employment_status IN ('employed', 'self_employed'))")->fetchColumn();
$verified = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'verified'")->fetchColumn();
$pending = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'pending' OR graduate_id NOT IN (SELECT graduate_id FROM verification_logs)")->fetchColumn();
$employment_rate = $total_graduates > 0 ? round(($employed / $total_graduates) * 100, 1) : 0;

// Employment by course
$stmt = $db->query("
    SELECT g.course,
           COUNT(g.id) as total,
           SUM(CASE WHEN ed.employment_status IN ('employed', 'self_employed') THEN 1 ELSE 0 END) as employed
    FROM graduates g
    LEFT JOIN employment_details ed ON g.id = ed.graduate_id
    GROUP BY g.course
    ORDER BY total DESC
");
$by_course = $stmt->fetchAll();

// Employment trends by year
$stmt = $db->query("
    SELECT graduation_year,
           COUNT(*) as total,
           SUM(CASE WHEN ed.employment_status IN ('employed', 'self_employed') THEN 1 ELSE 0 END) as employed
    FROM graduates g
    LEFT JOIN employment_details ed ON g.id = ed.graduate_id
    GROUP BY graduation_year
    ORDER BY graduation_year
");
$by_year = $stmt->fetchAll();

// Recent graduates
$stmt = $db->query("SELECT g.*, ed.employment_status, vl.verification_status FROM graduates g LEFT JOIN employment_details ed ON g.id = ed.graduate_id LEFT JOIN verification_logs vl ON g.id = vl.graduate_id ORDER BY g.created_at DESC LIMIT 2");
$recent = $stmt->fetchAll();

// Organization type distribution
$stmt = $db->query("SELECT organization_type, COUNT(*) as count FROM employment_details WHERE organization_type IS NOT NULL GROUP BY organization_type");
$by_org_type = $stmt->fetchAll();
?>

<!-- Stats Row -->
<div class="row g-4 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card border-start border-4 border-primary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1">Total Graduates</h6>
                    <h3 class="mb-0"><?= $total_graduates ?></h3>
                </div>
                <div class="stat-icon text-primary"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card border-start border-4 border-success">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1">Employed</h6>
                    <h3 class="mb-0"><?= $employed ?></h3>
                </div>
                <div class="stat-icon text-success"><i class="bi bi-briefcase-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card border-start border-4 border-info">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1">Employment Rate</h6>
                    <h3 class="mb-0"><?= $employment_rate ?>%</h3>
                </div>
                <div class="stat-icon text-info"><i class="bi bi-graph-up"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card border-start border-4 border-warning">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1">Verified</h6>
                    <h3 class="mb-0"><?= $verified ?></h3>
                </div>
                <div class="stat-icon text-warning"><i class="bi bi-shield-check"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Employment Trends by Year</h5>
            </div>
            <div class="card-body">
                <canvas id="trendChart" height="150"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Employment Status</h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-building"></i> Employment by Course</h5>
            </div>
            <div class="card-body">
                <canvas id="courseChart" height="150"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Organization Type Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="orgChart" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row g-4 mt-2">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Graduates</h5>
                <a href="graduates.php" class="btn btn-sm btn-outline-primary">View All</a>
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
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="graduates.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-people"></i> Manage Graduates</a>
                    <a href="verification.php" class="btn btn-outline-success btn-sm"><i class="bi bi-shield-check"></i> Verification Log</a>
                    <a href="employment.php" class="btn btn-outline-info btn-sm"><i class="bi bi-briefcase"></i> Employment Data</a>
                    <a href="reports.php" class="btn btn-outline-warning btn-sm"><i class="bi bi-file-earmark-bar-graph"></i> Generate Reports</a>
                    <a href="jobs.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-newspaper"></i> Manage Job Feed</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
Chart.register(ChartDataLabels);

// Employment Trends Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'bar',
    data: {
        labels: [<?= implode(',', array_map(fn($y) => "'" . $y['graduation_year'] . "'", $by_year)) ?>],
        datasets: [{
            label: 'Total',
            data: [<?= implode(',', array_column($by_year, 'total')) ?>],
            backgroundColor: 'rgba(26, 82, 118, 0.7)'
        }, {
            label: 'Employed',
            data: [<?= implode(',', array_column($by_year, 'employed')) ?>],
            backgroundColor: 'rgba(46, 204, 113, 0.7)'
        }]
    },
    options: { responsive: true, plugins: { datalabels: { display: false } }, scales: { y: { beginAtZero: true } } }
});

// Status Doughnut Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusTotal = <?= $employed + $unemployed ?>;
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Employed', 'Unemployed'],
        datasets: [{
            data: [<?= $employed ?>, <?= $unemployed ?>],
            backgroundColor: ['#2ecc71', '#e74c3c']
        }]
    },
    options: { responsive: true, plugins: { datalabels: { color: '#1a5276', font: { weight: 'bold', size: 10 }, formatter: (v, ctx) => ctx.chart.data.labels[ctx.dataIndex] + ' ' + ((v / statusTotal) * 100).toFixed(1) + '%' } } }
});

// Course Chart
const courseCtx = document.getElementById('courseChart').getContext('2d');
new Chart(courseCtx, {
    type: 'bar',
    data: {
        labels: [<?= implode(',', array_map(fn($c) => "'" . substr($c['course'], 0, 20) . "...'", $by_course)) ?>],
        datasets: [{
            label: 'Total',
            data: [<?= implode(',', array_column($by_course, 'total')) ?>],
            backgroundColor: 'rgba(26, 82, 118, 0.7)'
        }, {
            label: 'Employed',
            data: [<?= implode(',', array_column($by_course, 'employed')) ?>],
            backgroundColor: 'rgba(46, 204, 113, 0.7)'
        }]
    },
    options: { responsive: true, indexAxis: 'y', plugins: { datalabels: { display: false } }, scales: { x: { beginAtZero: true } } }
});

// Organization Type Chart
const orgCtx = document.getElementById('orgChart').getContext('2d');
const orgTotal = [<?= implode(',', array_column($by_org_type, 'count')) ?>].reduce((a, b) => a + b, 0);
new Chart(orgCtx, {
    type: 'doughnut',
    data: {
        labels: [<?= implode(',', array_map(fn($o) => "'" . ucfirst($o['organization_type']) . "'", $by_org_type)) ?>],
        datasets: [{
            data: [<?= implode(',', array_column($by_org_type, 'count')) ?>],
            backgroundColor: ['#16a085', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6', '#3498db']
        }]
    },
    options: { responsive: true, plugins: { datalabels: { color: '#1a5276', font: { weight: 'bold', size: 10 }, formatter: (v, ctx) => ctx.chart.data.labels[ctx.dataIndex] + ' ' + ((v / orgTotal) * 100).toFixed(1) + '%' } } }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
