<?php
$pageTitle = 'Graduates List';
require_once __DIR__ . '/../includes/dvcaa_header.php';

$db = getDB();

$search = $_GET['search'] ?? '';
$course = $_GET['course'] ?? '';
$year = $_GET['year'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * RECORDS_PER_PAGE;

$where = [];
$params = [];
if ($search) {
    $where[] = "(g.full_name LIKE ? OR g.reg_number LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($course) { $where[] = "g.course = ?"; $params[] = $course; }
if ($year) { $where[] = "g.graduation_year = ?"; $params[] = $year; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM graduates g {$whereSQL}");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT g.*, ed.employment_status, vl.verification_status
    FROM graduates g
    LEFT JOIN employment_details ed ON g.id = ed.graduate_id
    LEFT JOIN (SELECT graduate_id, verification_status FROM verification_logs ORDER BY date_checked DESC) vl ON g.id = vl.graduate_id
    {$whereSQL}
    GROUP BY g.id
    ORDER BY g.graduation_year DESC, g.full_name ASC
    LIMIT " . RECORDS_PER_PAGE . " OFFSET {$offset}
");
$stmt->execute($params);
$graduates = $stmt->fetchAll();

$courses = $db->query("SELECT DISTINCT course FROM graduates ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);
$years = $db->query("SELECT DISTINCT graduation_year FROM graduates ORDER BY graduation_year DESC")->fetchAll(PDO::FETCH_COLUMN);

$baseUrl = "graduates.php?search={$search}&course={$course}&year={$year}";
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search name or reg no..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="course" class="form-select">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= $course == $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="year" class="form-select">
                    <option value="">All Years</option>
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
                <a href="graduates.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-people"></i> Graduates (<?= $total ?>)</h5>
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
                        <th>Employment</th>
                        <th>Verification</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($graduates as $g): ?>
                    <tr>
                        <td><small><?= htmlspecialchars($g['reg_number']) ?></small></td>
                        <td><?= htmlspecialchars($g['full_name']) ?></td>
                        <td><small><?= htmlspecialchars($g['course']) ?></small></td>
                        <td><?= $g['graduation_year'] ?></td>
                        <td><?= getEmploymentBadge($g['employment_status'] ?? 'unemployed') ?></td>
                        <td><?= getVerificationBadge($g['verification_status'] ?? 'pending') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white">
        <?= paginate($total, RECORDS_PER_PAGE, $page, $baseUrl) ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/dvcaa_footer.php'; ?>
