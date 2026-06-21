<?php
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../includes/dvcaa_header.php';

$db = getDB();

$total_graduates = $db->query("SELECT COUNT(*) FROM graduates")->fetchColumn();
$employed_count = $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status IN ('employed', 'self_employed')")->fetchColumn();
$unemployed_count = $db->query("SELECT COUNT(*) FROM graduates WHERE id NOT IN (SELECT graduate_id FROM employment_details WHERE employment_status IN ('employed', 'self_employed'))")->fetchColumn();
$further_studies = $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status = 'further_studies'")->fetchColumn();
$seeking = $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status = 'seeking'")->fetchColumn();
$verified_count = $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'verified'")->fetchColumn();

$employment_rate = $total_graduates > 0 ? round(($employed_count / $total_graduates) * 100, 1) : 0;
$verification_rate = $total_graduates > 0 ? round(($verified_count / $total_graduates) * 100, 1) : 0;

$stmt = $db->query("
    SELECT g.course, COUNT(g.id) as total,
           SUM(CASE WHEN ed.employment_status IN ('employed', 'self_employed') THEN 1 ELSE 0 END) as employed
    FROM graduates g
    LEFT JOIN employment_details ed ON g.id = ed.graduate_id
    GROUP BY g.course
    ORDER BY total DESC
");
$course_stats = $stmt->fetchAll();

$stmt = $db->query("
    SELECT graduation_year, COUNT(*) as total,
           SUM(CASE WHEN ed.employment_status IN ('employed', 'self_employed') THEN 1 ELSE 0 END) as employed,
           ROUND(SUM(CASE WHEN ed.employment_status IN ('employed', 'self_employed') THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as rate
    FROM graduates g
    LEFT JOIN employment_details ed ON g.id = ed.graduate_id
    GROUP BY graduation_year
    ORDER BY graduation_year
");
$year_stats = $stmt->fetchAll();

$org_stats = $db->query("SELECT organization_type, COUNT(*) as count FROM employment_details WHERE organization_type IS NOT NULL GROUP BY organization_type")->fetchAll();
$location_stats = $db->query("SELECT location, COUNT(*) as count FROM employment_details WHERE location IS NOT NULL GROUP BY location ORDER BY count DESC LIMIT 10")->fetchAll();
$salary_stats = $db->query("SELECT salary_range, COUNT(*) as count FROM employment_details WHERE salary_range IS NOT NULL GROUP BY salary_range ORDER BY salary_range")->fetchAll();
?>
<div class="row g-4 mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h4 class="mb-0"><i class="bi bi-bar-chart-line"></i> Reports & Analytics</h4>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?= $total_graduates ?></h3>
                <p class="mb-0 small">Total Graduates</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body text-center">
                <h3><?= $employment_rate ?>%</h3>
                <p class="mb-0 small">Employment Rate</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body text-center">
                <h3><?= $verification_rate ?>%</h3>
                <p class="mb-0 small">Verification Rate</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-dark">
            <div class="card-body text-center">
                <h3><?= $further_studies ?></h3>
                <p class="mb-0 small">Further Studies</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Employment Rate by Year</h5>
            </div>
            <div class="card-body">
                <canvas id="yearRateChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Employment Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="distChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-table"></i> Employment by Course</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Course</th>
                        <th>Total</th>
                        <th>Employed</th>
                        <th>Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($course_stats as $cs):
                        $rate = $cs['total'] > 0 ? round(($cs['employed'] / $cs['total']) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($cs['course']) ?></td>
                        <td><?= $cs['total'] ?></td>
                        <td><?= $cs['employed'] ?></td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?= $rate ?>%"><?= $rate ?>%</div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Employment Trends by Graduation Year</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Year</th>
                        <th>Total</th>
                        <th>Employed</th>
                        <th>Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($year_stats as $ys): ?>
                    <tr>
                        <td><strong><?= $ys['graduation_year'] ?></strong></td>
                        <td><?= $ys['total'] ?></td>
                        <td><?= $ys['employed'] ?></td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar <?= $ys['rate'] >= 50 ? 'bg-success' : ($ys['rate'] >= 30 ? 'bg-warning' : 'bg-danger') ?>" style="width: <?= $ys['rate'] ?>%"><?= $ys['rate'] ?>%</div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><h5 class="mb-0">Organization Types</h5></div>
            <div class="card-body"><canvas id="orgChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><h5 class="mb-0">Top Locations</h5></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($location_stats as $loc): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <?= htmlspecialchars($loc['location']) ?>
                    <span class="badge bg-primary"><?= $loc['count'] ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (empty($location_stats)): ?>
                <li class="list-group-item text-muted text-center">No data</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><h5 class="mb-0">Salary Ranges</h5></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($salary_stats as $sal): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <?= htmlspecialchars($sal['salary_range']) ?> TZS
                    <span class="badge bg-success"><?= $sal['count'] ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (empty($salary_stats)): ?>
                <li class="list-group-item text-muted text-center">No data</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-person-lines-fill"></i> Graduate Details Report</h5>
        <span class="text-muted small">Print full details for all graduates</span>
    </div>
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="print_graduates.php" target="_blank">
            <div class="col-md-4">
                <label class="form-label small">Course</label>
                <select name="course" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    <?php foreach ($db->query("SELECT DISTINCT course FROM graduates ORDER BY course")->fetchAll(PDO::FETCH_COLUMN) as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Graduation Year</label>
                <select name="year" class="form-select form-select-sm">
                    <option value="">All Years</option>
                    <?php foreach ($db->query("SELECT DISTINCT graduation_year FROM graduates ORDER BY graduation_year DESC")->fetchAll(PDO::FETCH_COLUMN) as $y): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-file-earmark-pdf"></i> View & Print Report</button>
            </div>
        </form>
    </div>
</div>

<script>
Chart.register(ChartDataLabels);

new Chart(document.getElementById('yearRateChart'), {
    type: 'line',
    data: {
        labels: [<?= implode(',', array_map(fn($y) => "'" . $y['graduation_year'] . "'", $year_stats)) ?>],
        datasets: [{
            label: 'Employment Rate (%)',
            data: [<?= implode(',', array_column($year_stats, 'rate')) ?>],
            borderColor: '#2ecc71',
            backgroundColor: 'rgba(46, 204, 113, 0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: { responsive: true, plugins: { datalabels: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
});

const distData = [
    <?= $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status='employed'")->fetchColumn() ?>,
    <?= $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status='self_employed'")->fetchColumn() ?>,
    <?= $unemployed_count ?>,
    <?= $further_studies ?>,
    <?= $seeking ?>
];
const distTotal = distData.reduce((a, b) => a + b, 0);
new Chart(document.getElementById('distChart'), {
    type: 'doughnut',
    data: {
        labels: ['Employed', 'Self Employed', 'Unemployed', 'Studies', 'Seeking'],
        datasets: [{
            data: distData,
            backgroundColor: ['#2ecc71', '#3498db', '#e74c3c', '#f39c12', '#9b59b6']
        }]
    },
    options: { responsive: true, plugins: { datalabels: { color: '#1a5276', font: { weight: 'bold', size: 8 }, formatter: (v, ctx) => v > 0 ? ctx.chart.data.labels[ctx.dataIndex] + ' ' + ((v / distTotal) * 100).toFixed(1) + '%' : '' } } }
});

const orgTotal = [<?= implode(',', array_column($org_stats, 'count')) ?>].reduce((a, b) => a + b, 0);
new Chart(document.getElementById('orgChart'), {
    type: 'doughnut',
    data: {
        labels: [<?= implode(',', array_map(fn($o) => "'" . ucfirst($o['organization_type']) . "'", $org_stats)) ?>],
        datasets: [{
            data: [<?= implode(',', array_column($org_stats, 'count')) ?>],
            backgroundColor: ['#16a085', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6', '#3498db']
        }]
    },
    options: { responsive: true, plugins: { datalabels: { color: '#1a5276', font: { weight: 'bold', size: 8 }, formatter: (v, ctx) => ctx.chart.data.labels[ctx.dataIndex] + ' ' + ((v / orgTotal) * 100).toFixed(1) + '%' } } }
});
</script>
<script>
function downloadPDF() {
    const el = document.querySelector('.main-content');
    html2pdf().set({
        margin: [10, 10, 10, 10],
        filename: 'RUCU_GETS_Report_' + new Date().toISOString().slice(0, 10) + '.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    }).from(el).save();
}
function exportCSV() {
    const rows = [];
    const tables = document.querySelectorAll('.table');
    tables.forEach((table, ti) => {
        const caption = table.closest('.card').querySelector('.card-header h5, .card-header .mb-0');
        if (caption) rows.push(['--- ' + caption.textContent.trim() + ' ---']);
        table.querySelectorAll('tr').forEach(tr => {
            const cols = [];
            tr.querySelectorAll('th, td').forEach(td => cols.push('"' + td.textContent.trim().replace(/"/g, '""') + '"'));
            if (cols.length) rows.push(cols.join(','));
        });
        rows.push([]);
    });
    const csv = rows.join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'RUCU_GETS_Report_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
}
</script>
<?php require_once __DIR__ . '/../includes/dvcaa_footer.php'; ?>
