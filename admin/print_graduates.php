<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
requireAdminLogin();

$db = getDB();

$course = $_GET['course'] ?? '';
$year = $_GET['year'] ?? '';
$status = $_GET['status'] ?? '';

$where = [];
$params = [];
if ($course) { $where[] = "g.course = ?"; $params[] = $course; }
if ($year) { $where[] = "g.graduation_year = ?"; $params[] = $year; }
if ($status) { $where[] = "ed.employment_status = ?"; $params[] = $status; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT g.*, ed.*, vl.verification_status as v_status, vl.verification_source, vl.necta_status, vl.employer_match, vl.notes, vl.date_checked, vl.checked_by
    FROM graduates g
    LEFT JOIN employment_details ed ON g.id = ed.graduate_id
    LEFT JOIN (SELECT * FROM verification_logs ORDER BY date_checked DESC LIMIT 1) vl ON g.id = vl.graduate_id
    {$whereSQL}
    ORDER BY g.full_name ASC
");
$stmt->execute($params);
$graduates = $stmt->fetchAll();

$courses = $db->query("SELECT DISTINCT course FROM graduates ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);
$years = $db->query("SELECT DISTINCT graduation_year FROM graduates ORDER BY graduation_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$statuses = ['employed', 'self_employed', 'unemployed', 'further_studies', 'seeking'];

$generated_date = date('F d, Y \a\t H:i');
$total = count($graduates);
$filter_label = [];
if ($course) $filter_label[] = "Course: $course";
if ($year) $filter_label[] = "Year: $year";
if ($status) $filter_label[] = "Status: " . ucfirst(str_replace('_', ' ', $status));
$filter_text = $filter_label ? ' - ' . implode(', ', $filter_label) : ' - All Graduates';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graduate Details Report<?= htmlspecialchars($filter_text) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 11px; color: #333; background: #fff; }
        .page { padding: 20px; }

        .report-header { text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 3px solid #1a5276; }
        .report-header h1 { font-size: 20px; color: #1a5276; margin-bottom: 5px; }
        .report-header h2 { font-size: 14px; color: #555; font-weight: normal; margin-bottom: 5px; }
        .report-header .subtitle { font-size: 12px; color: #777; }

        .report-meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 11px; color: #555; }

        .graduate-card {
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 20px;
            page-break-inside: avoid;
            overflow: hidden;
        }
        .graduate-card .card-header {
            background: #1a5276;
            color: #fff;
            padding: 10px 15px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .graduate-card .card-header .reg-number { font-weight: normal; font-size: 11px; opacity: 0.85; }
        .graduate-card .card-body { padding: 0; }
        .graduate-card .section { padding: 12px 15px; border-bottom: 1px solid #eee; }
        .graduate-card .section:last-child { border-bottom: none; }
        .graduate-card .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #1a5276;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .graduate-card .section-title i { margin-right: 5px; }
        .graduate-card .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 20px;
        }
        .graduate-card .detail-item {
            display: flex;
            font-size: 10.5px;
            padding: 3px 0;
            border-bottom: 1px dotted #f0f0f0;
        }
        .graduate-card .detail-item .label {
            color: #777;
            width: 120px;
            flex-shrink: 0;
        }
        .graduate-card .detail-item .value { color: #333; font-weight: 500; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-primary { background: #cce5ff; color: #004085; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }

        .footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #1a5276; text-align: center; font-size: 9px; color: #999; }

        @media print {
            body { font-size: 9.5px; }
            .page { padding: 0; }
            .graduate-card { break-inside: avoid; border-color: #ccc; }
            .graduate-card .card-header { background: #1a5276 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="report-header">
            <h1><i class="bi bi-mortarboard-fill"></i> RUCU Graduate Employment Tracking System</h1>
            <h2>Graduate Detailed Report<?= htmlspecialchars($filter_text) ?></h2>
            <div class="subtitle">Ruaha Catholic University (RUCU) - Admin Panel</div>
        </div>

        <div class="report-meta">
            <span><strong>Generated:</strong> <?= $generated_date ?></span>
            <span><strong>Generated by:</strong> <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
            <span><strong>Total Graduates:</strong> <?= $total ?></span>
        </div>

        <?php if ($total === 0): ?>
            <div style="text-align:center;padding:40px;color:#999;font-size:14px;">No graduates found matching the selected filters.</div>
        <?php endif; ?>

        <?php foreach ($graduates as $g): ?>
        <div class="graduate-card">
            <div class="card-header">
                <span><?= htmlspecialchars($g['full_name']) ?></span>
                <span class="reg-number"><?= htmlspecialchars($g['reg_number']) ?></span>
            </div>
            <div class="card-body">
                <div class="section">
                    <div class="section-title"><i class="bi bi-person"></i> Personal Information</div>
                    <div class="detail-grid">
                        <div class="detail-item"><span class="label">Reg Number</span><span class="value"><?= htmlspecialchars($g['reg_number']) ?></span></div>
                        <div class="detail-item"><span class="label">Email</span><span class="value"><?= htmlspecialchars($g['email'] ?? '-') ?></span></div>
                        <div class="detail-item"><span class="label">Phone</span><span class="value"><?= htmlspecialchars($g['phone'] ?? '-') ?></span></div>
                        <div class="detail-item"><span class="label">Form 4 Index</span><span class="value"><?= htmlspecialchars($g['form4_index_number'] ?? '-') ?></span></div>
                    </div>
                </div>
                <div class="section">
                    <div class="section-title"><i class="bi bi-mortarboard"></i> Academic Information</div>
                    <div class="detail-grid">
                        <div class="detail-item"><span class="label">Course</span><span class="value"><?= htmlspecialchars($g['course']) ?></span></div>
                        <div class="detail-item"><span class="label">Graduation Year</span><span class="value"><?= htmlspecialchars($g['graduation_year']) ?></span></div>
                    </div>
                </div>
                <div class="section">
                    <div class="section-title"><i class="bi bi-briefcase"></i> Employment Details</div>
                    <div class="detail-grid">
                        <div class="detail-item"><span class="label">Status</span><span class="value"><?= getEmploymentBadge($g['employment_status'] ?? 'unemployed') ?></span></div>
                        <div class="detail-item"><span class="label">Company</span><span class="value"><?= htmlspecialchars($g['company_name'] ?? '-') ?></span></div>
                        <div class="detail-item"><span class="label">Job Title</span><span class="value"><?= htmlspecialchars($g['job_title'] ?? '-') ?></span></div>
                        <div class="detail-item"><span class="label">Organization Type</span><span class="value"><?= $g['organization_type'] ? ucfirst(str_replace('_', ' ', $g['organization_type'])) : '-' ?></span></div>
                        <div class="detail-item"><span class="label">Salary Range</span><span class="value"><?= htmlspecialchars($g['salary_range'] ?? '-') ?></span></div>
                        <div class="detail-item"><span class="label">Start Date</span><span class="value"><?= $g['start_date'] ? date('M d, Y', strtotime($g['start_date'])) : '-' ?></span></div>
                        <div class="detail-item"><span class="label">Location</span><span class="value"><?= htmlspecialchars($g['location'] ?? '-') ?></span></div>
                    </div>
                </div>
                <div class="section">
                    <div class="section-title"><i class="bi bi-shield-check"></i> Verification</div>
                    <div class="detail-grid">
                        <div class="detail-item"><span class="label">Status</span><span class="value"><?= getVerificationBadge($g['v_status'] ?? 'pending') ?></span></div>
                        <div class="detail-item"><span class="label">Source</span><span class="value"><?= htmlspecialchars($g['verification_source'] ?? '-') ?></span></div>
                        <div class="detail-item"><span class="label">NECTA Status</span><span class="value"><?= htmlspecialchars($g['necta_status'] ?? '-') ?></span></div>
                        <div class="detail-item"><span class="label">Employer Match</span><span class="value"><?= $g['employer_match'] !== null ? ($g['employer_match'] ? 'Yes' : 'No') : '-' ?></span></div>
                        <div class="detail-item"><span class="label">Date Checked</span><span class="value"><?= $g['date_checked'] ? date('M d, Y H:i', strtotime($g['date_checked'])) : '-' ?></span></div>
                        <div class="detail-item"><span class="label">Checked By</span><span class="value"><?= htmlspecialchars($g['checked_by'] ?? '-') ?></span></div>
                        <div class="detail-item" style="grid-column:1/-1;"><span class="label">Notes</span><span class="value"><?= htmlspecialchars($g['notes'] ?? '-') ?></span></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="footer">
            <p>&copy; <?= date('Y') ?> Ruaha Catholic University (RUCU) - Graduate Employment Tracking & Verification System</p>
            <p>This report is confidential and intended for internal use only.</p>
        </div>
    </div>

    <script>
    window.onload = function() {
        setTimeout(function() { window.print(); }, 500);
    };
    window.onafterprint = function() {
        window.close();
    };
    </script>
</body>
</html>
