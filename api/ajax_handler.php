<?php
/**
 * AJAX API Handler
 * Handles AJAX requests for live search, autocomplete, etc.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!isset($_GET['action'])) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit;
}

$db = getDB();
$action = sanitize($_GET['action']);

switch ($action) {
    case 'search_graduates':
        $query = sanitize($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            echo json_encode(['success' => false, 'message' => 'Query too short']);
            exit;
        }
        $stmt = $db->prepare("SELECT reg_number, full_name, course, graduation_year FROM graduates WHERE full_name LIKE ? OR reg_number LIKE ? LIMIT 10");
        $stmt->execute(["%{$query}%", "%{$query}%"]);
        $results = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $results]);
        break;

    case 'get_graduate':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT g.*, ed.*, vl.verification_status, vl.verification_source, vl.necta_status, vl.employer_match, vl.notes, vl.date_checked, vl.checked_by FROM graduates g LEFT JOIN employment_details ed ON g.id = ed.graduate_id LEFT JOIN (SELECT * FROM verification_logs ORDER BY date_checked DESC LIMIT 1) vl ON g.id = vl.graduate_id WHERE g.id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
        break;

    case 'update_verification':
        $id = intval($_POST['graduate_id'] ?? 0);
        $status = sanitize($_POST['verification_status'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $source = sanitize($_POST['verification_source'] ?? 'manual_review');
        if (!$id || !in_array($status, ['verified', 'not_verified', 'pending'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        $stmt = $db->prepare("SELECT id FROM verification_logs WHERE graduate_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()) {
            $stmt = $db->prepare("UPDATE verification_logs SET verification_status = ?, verification_source = ?, notes = ?, date_checked = NOW(), checked_by = ? WHERE graduate_id = ?");
            $stmt->execute([$status, $source, $notes, $_SESSION['admin_name'] ?? 'admin', $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO verification_logs (graduate_id, verification_status, verification_source, notes, date_checked, checked_by) VALUES (?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([$id, $status, $source, $notes, $_SESSION['admin_name'] ?? 'admin']);
        }
        logActivity('admin', $_SESSION['admin_id'], 'verification_updated', "Graduate {$id} status set to {$status}");
        echo json_encode(['success' => true, 'message' => 'Verification updated']);
        break;

    case 'reset_password':
        $input = sanitize($_POST['input'] ?? '');
        if (empty($input)) {
            echo json_encode(['success' => false, 'message' => 'Please enter your registration number or email']);
            exit;
        }
        $stmt = $db->prepare("SELECT id, full_name, reg_number FROM graduates WHERE reg_number = ? OR email = ?");
        $stmt->execute([$input, $input]);
        $user = $stmt->fetch();
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'No account found with that registration number or email']);
            exit;
        }
        $defaultPassword = 'Rucu@2026';
        $hashed = password_hash($defaultPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE graduates SET password = ?, first_login = 1, password_expiry_date = NULL WHERE id = ?");
        $stmt->execute([$hashed, $user['id']]);
        logActivity('graduate', $user['id'], 'Password Reset', 'Password reset via forgot password');
        echo json_encode(['success' => true, 'message' => 'Password reset to default. Use Rucu@2026 to login and change it immediately.']);
        break;

    case 'get_stats':
        $stats = [
            'total_graduates' => $db->query("SELECT COUNT(*) FROM graduates")->fetchColumn(),
            'employed' => $db->query("SELECT COUNT(*) FROM employment_details WHERE employment_status IN ('employed', 'self_employed')")->fetchColumn(),
            'verified' => $db->query("SELECT COUNT(DISTINCT graduate_id) FROM verification_logs WHERE verification_status = 'verified'")->fetchColumn(),
            'active_jobs' => $db->query("SELECT COUNT(*) FROM job_feed WHERE deadline >= CURDATE()")->fetchColumn(),
        ];
        echo json_encode(['success' => true, 'data' => $stats]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
