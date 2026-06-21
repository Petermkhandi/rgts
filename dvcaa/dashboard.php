<?php
$pageTitle = 'DVCAA Academic Affairs Dashboard';
require_once __DIR__ . '/../includes/dvcaa_header.php';

$db = getDB();

// Get overall statistics
$total_graduates = $db->query("SELECT COUNT(*) FROM graduates")->fetchColumn();
$employed = $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status IN ('employed', 'self_employed')")->fetchColumn();
$unemployed = $db->query("SELECT COUNT(*) FROM graduates WHERE id NOT IN (SELECT graduate_id FROM employment_details WHERE employment_status IN ('employed', 'self_employed'))")->fetchColumn();
$employment_rate = $total_graduates > 0 ? round(($employed / $total_graduates) * 100, 1) : 0;

// Verification statistics
$verified_count = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'verified'")->fetchColumn();
$pending_count = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'pending' OR graduate_id NOT IN (SELECT graduate_id FROM verification_logs)")->fetchColumn();
$not_verified_count = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'not_verified'")->fetchColumn();
$verification_rate = $total_graduates > 0 ? round(($verified_count / $total_graduates) * 100, 1) : 0;

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

// Employment distribution
$employed_only = $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status = 'employed'")->fetchColumn();
$self_employed = $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status = 'self_employed'")->fetchColumn();
$further_studies = $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status = 'further_studies'")->fetchColumn();
$seeking = $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status = 'seeking'")->fetchColumn();

// Graduates per year
$stmt = $db->query("SELECT graduation_year, COUNT(*) as count FROM graduates GROUP BY graduation_year ORDER BY graduation_year");
$by_grad_year = $stmt->fetchAll();

// Course distribution
$stmt = $db->query("SELECT course, COUNT(*) as total FROM graduates GROUP BY course ORDER BY total DESC");
$by_course_dist = $stmt->fetchAll();
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
                    <h6 class="text-muted mb-1">Employment Rate</h6>
                    <h3 class="mb-0"><?= $employment_rate ?>%</h3>
                </div>
                <div class="stat-icon text-success"><i class="bi bi-graph-up"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card border-start border-4 border-info">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1">Graduates Employed</h6>
                    <h3 class="mb-0"><?= $employed ?></h3>
                </div>
                <div class="stat-icon text-info"><i class="bi bi-briefcase-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card stat-card border-start border-4 border-warning">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-1">Verification Rate</h6>
                    <h3 class="mb-0"><?= $verification_rate ?>%</h3>
                </div>
                <div class="stat-icon text-warning"><i class="bi bi-shield-check"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Verification Status Row -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-shield-check text-info"></i> Verification Status</h5>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="verificationChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Employment Trends by Year</h5>
            </div>
            <div class="card-body">
                <canvas id="trendChart" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Employment Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-building"></i> Employment by Course</h5>
            </div>
            <div class="card-body">
                <canvas id="courseChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Course Distribution Table -->
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-book"></i> Graduate Distribution by Course</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Course</th>
                                <th>Total Graduates</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($by_course_dist as $c): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($c['course']) ?></td>
                                <td><?= $c['total'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar bg-primary" style="width: <?= round(($c['total'] / $total_graduates) * 100) ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= round(($c['total'] / $total_graduates) * 100) ?>%</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
Chart.register(ChartDataLabels);

// Verification Status Chart
const verCtx = document.getElementById('verificationChart').getContext('2d');
const verTotal = <?= $verified_count + $pending_count + $not_verified_count ?>;
new Chart(verCtx, {
    type: 'doughnut',
    data: {
        labels: ['Verified', 'Pending', 'Not Verified'],
        datasets: [{
            data: [<?= $verified_count ?>, <?= $pending_count ?>, <?= $not_verified_count ?>],
            backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c']
        }]
    },
    options: { responsive: true, plugins: { datalabels: { color: '#1a5276', font: { weight: 'bold', size: 10 }, formatter: (v, ctx) => ctx.chart.data.labels[ctx.dataIndex] + ' ' + ((v / verTotal) * 100).toFixed(1) + '%' } } }
});

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

// Employment Distribution Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const empDistData = [<?= $employed_only ?>, <?= $self_employed ?>, <?= $further_studies ?>, <?= $seeking ?>, <?= $unemployed ?>];
const empDistTotal = empDistData.reduce((a, b) => a + b, 0);
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Employed', 'Self-Employed', 'Further Studies', 'Seeking', 'Unemployed'],
        datasets: [{
            data: empDistData,
            backgroundColor: ['#2ecc71', '#3498db', '#9b59b6', '#f39c12', '#e74c3c']
        }]
    },
    options: { responsive: true, plugins: { datalabels: { color: '#1a5276', font: { weight: 'bold', size: 10 }, formatter: (v, ctx) => v > 0 ? ctx.chart.data.labels[ctx.dataIndex] + ' ' + ((v / empDistTotal) * 100).toFixed(1) + '%' : '' } } }
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
</script>

<?php require_once __DIR__ . '/../includes/dvcaa_footer.php'; ?>
