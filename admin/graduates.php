<?php
$pageTitle = 'Manage Graduates';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDB();

// Filters
$course = $_GET['course'] ?? '';
$year = $_GET['year'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * RECORDS_PER_PAGE;

// Build query
$where = [];
$params = [];

if ($course) { $where[] = "g.course = ?"; $params[] = $course; }
if ($year) { $where[] = "g.graduation_year = ?"; $params[] = $year; }
if ($status) {
    if ($status === 'employed') {
        $where[] = "ed.employment_status IN ('employed', 'self_employed')";
    } elseif ($status === 'unemployed') {
        $where[] = "(ed.employment_status = 'unemployed' OR ed.employment_status IS NULL)";
    } elseif ($status === 'verified') {
        $where[] = "vl.verification_status = 'verified'";
    }
}
if ($search) {
    $where[] = "(g.full_name LIKE ? OR g.reg_number LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countQuery = "SELECT COUNT(*) FROM graduates g LEFT JOIN employment_details ed ON g.id = ed.graduate_id LEFT JOIN verification_logs vl ON g.id = vl.graduate_id {$whereSQL}";
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Get graduates
$query = "SELECT g.*, ed.employment_status, vl.verification_status as verified_status
          FROM graduates g
          LEFT JOIN employment_details ed ON g.id = ed.graduate_id
          LEFT JOIN (SELECT graduate_id, verification_status, date_checked FROM verification_logs ORDER BY date_checked DESC) vl ON g.id = vl.graduate_id
          {$whereSQL}
          GROUP BY g.id
          ORDER BY g.graduation_year DESC, g.full_name ASC
          LIMIT " . RECORDS_PER_PAGE . " OFFSET {$offset}";
$stmt = $db->prepare($query);
$stmt->execute($params);
$graduates = $stmt->fetchAll();

// Get courses for filter
$courses = $db->query("SELECT DISTINCT course FROM graduates ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);
$years = $db->query("SELECT DISTINCT graduation_year FROM graduates ORDER BY graduation_year DESC")->fetchAll(PDO::FETCH_COLUMN);

$baseUrl = "graduates.php?course={$course}&year={$year}&status={$status}&search={$search}";
?>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search name or reg no..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
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
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="employed" <?= $status == 'employed' ? 'selected' : '' ?>>Employed</option>
                    <option value="unemployed" <?= $status == 'unemployed' ? 'selected' : '' ?>>Unemployed</option>
                    <option value="verified" <?= $status == 'verified' ? 'selected' : '' ?>>Verified</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-rucu"><i class="bi bi-search"></i> Filter</button>
                <a href="graduates.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-people"></i> Graduates (<?= $total ?> total)</h5>
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
                        <th>Password Set</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($graduates as $g): ?>
                    <tr>
                        <td><small><?= htmlspecialchars($g['reg_number']) ?></small></td>
                        <td><a href="#" class="text-decoration-none fw-semibold graduate-link" data-id="<?= $g['id'] ?>"><?= htmlspecialchars($g['full_name']) ?></a></td>
                        <td><small><?= htmlspecialchars($g['course']) ?></small></td>
                        <td><?= $g['graduation_year'] ?></td>
                        <td><?= getEmploymentBadge($g['employment_status'] ?? 'unemployed') ?></td>
                        <td><?= getVerificationBadge($g['verified_status'] ?? 'pending') ?></td>
                        <td><?= $g['password'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning text-dark">No</span>' ?></td>
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

<div class="modal fade" id="graduateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge"></i> Graduate Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="graduateModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2 text-muted">Loading...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.graduate-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const id = this.dataset.id;
        const modal = new bootstrap.Modal(document.getElementById('graduateModal'));
        const body = document.getElementById('graduateModalBody');
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Loading...</p></div>';
        modal.show();

        fetch('../api/ajax_handler.php?action=get_graduate&id=' + id)
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.data) {
                    body.innerHTML = '<div class="alert alert-danger">Graduate not found.</div>';
                    return;
                }
                const g = data.data;
                body.innerHTML = `
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-person"></i> Personal Information</h6>
                            <table class="table table-borderless mb-0">
                                <tr><td class="text-muted" width="40%">Name</td><td>${esc(g.full_name)}</td></tr>
                                <tr><td class="text-muted">Reg Number</td><td>${esc(g.reg_number)}</td></tr>
                                <tr><td class="text-muted">Email</td><td>${esc(g.email) || '-'}</td></tr>
                                <tr><td class="text-muted">Phone</td><td>${esc(g.phone) || '-'}</td></tr>
                                <tr><td class="text-muted">Form 4 Index</td><td>${esc(g.form4_index_number)}</td></tr>
                                <tr><td class="text-muted">Password Set</td><td>${g.password ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning text-dark">No</span>'}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-mortarboard"></i> Academic Information</h6>
                            <table class="table table-borderless mb-0">
                                <tr><td class="text-muted" width="40%">Course</td><td>${esc(g.course)}</td></tr>
                                <tr><td class="text-muted">Graduation Year</td><td>${g.graduation_year}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-briefcase"></i> Employment Details</h6>
                            <table class="table table-borderless mb-0">
                                <tr><td class="text-muted" width="40%">Status</td><td>${employmentBadge(g.employment_status)}</td></tr>
                                <tr><td class="text-muted">Company</td><td>${esc(g.company_name) || '-'}</td></tr>
                                <tr><td class="text-muted">Job Title</td><td>${esc(g.job_title) || '-'}</td></tr>
                                <tr><td class="text-muted">Organization</td><td>${orgTypeBadge(g.organization_type)}</td></tr>
                                <tr><td class="text-muted">Salary</td><td>${esc(g.salary_range) || '-'}</td></tr>
                                <tr><td class="text-muted">Start Date</td><td>${g.start_date ? formatDate(g.start_date) : '-'}</td></tr>
                                <tr><td class="text-muted">Location</td><td>${esc(g.location) || '-'}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-shield-check"></i> Verification</h6>
                            <table class="table table-borderless mb-2">
                                <tr><td class="text-muted" width="40%">Status</td><td id="vl_status">${verificationBadge(g.verification_status)}</td></tr>
                                <tr><td class="text-muted">Source</td><td id="vl_source">${esc(g.verification_source) || '-'}</td></tr>
                                <tr><td class="text-muted">NECTA</td><td>${esc(g.necta_status) || '-'}</td></tr>
                                <tr><td class="text-muted">Employer Match</td><td>${g.employer_match !== null ? (g.employer_match ? 'Yes' : 'No') : '-'}</td></tr>
                                <tr><td class="text-muted">Date</td><td>${g.date_checked ? formatDateTime(g.date_checked) : '-'}</td></tr>
                                <tr><td class="text-muted">Checked By</td><td>${esc(g.checked_by) || '-'}</td></tr>
                                <tr><td class="text-muted">Notes</td><td id="vl_notes">${esc(g.notes) || '-'}</td></tr>
                            </table>
                            <hr class="my-2">
                            <div class="card bg-light border-0">
                                <div class="card-body p-2">
                                    <small class="fw-semibold d-block mb-2">Update Verification</small>
                                    <div class="d-flex gap-2 flex-wrap align-items-end">
                                        <div>
                                            <select class="form-select form-select-sm" id="v_status" style="width:140px">
                                                <option value="verified" ${g.verification_status === 'verified' ? 'selected' : ''}>Verified</option>
                                                <option value="not_verified" ${g.verification_status === 'not_verified' ? 'selected' : ''}>Not Verified</option>
                                                <option value="pending" ${(!g.verification_status || g.verification_status === 'pending') ? 'selected' : ''}>Pending</option>
                                            </select>
                                        </div>
                                        <div>
                                            <select class="form-select form-select-sm" id="v_source" style="width:140px">
                                                <option value="necta" ${g.verification_source === 'necta' ? 'selected' : ''}>NECTA</option>
                                                <option value="employer_simulation" ${g.verification_source === 'employer_simulation' ? 'selected' : ''}>Employer Sim</option>
                                                <option value="manual_review" ${g.verification_source === 'manual_review' ? 'selected' : ''}>Manual</option>
                                            </select>
                                        </div>
                                        <div class="flex-grow-1">
                                            <input type="text" class="form-control form-control-sm" id="v_notes" placeholder="Notes..." value="${esc(g.notes) || ''}">
                                        </div>
                                        <div>
                                            <button class="btn btn-sm btn-success" onclick="updateVerification(${g.id})"><i class="bi bi-check-lg"></i> Save</button>
                                        </div>
                                    </div>
                                    <div id="v_msg" class="mt-1"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            })
            .catch(() => {
                body.innerHTML = '<div class="alert alert-danger">Failed to load graduate details.</div>';
            });
    });
});

function esc(str) { return str ? String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }
function employmentBadge(status) {
    if (!status) return '<span class="badge bg-danger">Unemployed</span>';
    const map = { employed: 'success', self_employed: 'info', unemployed: 'danger', further_studies: 'primary', seeking: 'warning text-dark' };
    const labels = { employed: 'Employed', self_employed: 'Self Employed', unemployed: 'Unemployed', further_studies: 'Further Studies', seeking: 'Seeking' };
    const cls = map[status] || 'secondary';
    return `<span class="badge bg-${cls}">${labels[status] || status}</span>`;
}
function orgTypeBadge(type) {
    if (!type) return '-';
    return `<span class="badge bg-secondary">${type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>`;
}
function verificationBadge(status) {
    if (!status) return '<span class="badge bg-warning text-dark">Pending</span>';
    const map = { verified: 'success', not_verified: 'danger', pending: 'warning text-dark' };
    const labels = { verified: 'Verified', not_verified: 'Not Verified', pending: 'Pending' };
    const cls = map[status] || 'secondary';
    return `<span class="badge bg-${cls}">${labels[status] || status}</span>`;
}
function formatDate(d) { const dt = new Date(d); return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); }
function formatDateTime(d) { const dt = new Date(d); return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
function updateVerification(id) {
    const msg = document.getElementById('v_msg');
    const status = document.getElementById('v_status').value;
    const source = document.getElementById('v_source').value;
    const notes = document.getElementById('v_notes').value;
    const btn = document.querySelector('#graduateModal button[onclick="updateVerification(' + id + ')"]');
    btn.disabled = true;
    msg.innerHTML = '<small class="text-muted">Saving...</small>';

    const fd = new FormData();
    fd.append('graduate_id', id);
    fd.append('verification_status', status);
    fd.append('verification_source', source);
    fd.append('notes', notes);

    fetch('../api/ajax_handler.php?action=update_verification', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                msg.innerHTML = '<small class="text-success"><i class="bi bi-check-circle"></i> ' + esc(data.message) + '</small>';
                document.getElementById('vl_status').innerHTML = verificationBadge(status);
                document.getElementById('vl_source').innerHTML = source;
                document.getElementById('vl_notes').innerHTML = esc(notes) || '-';
            } else {
                msg.innerHTML = '<small class="text-danger"><i class="bi bi-x-circle"></i> ' + esc(data.message) + '</small>';
            }
            btn.disabled = false;
        })
        .catch(() => {
            msg.innerHTML = '<small class="text-danger"><i class="bi bi-x-circle"></i> Failed to update</small>';
            btn.disabled = false;
        });
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
