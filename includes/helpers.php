<?php
require_once __DIR__ . '/../config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect helper
function redirect($url) {
    header("Location: " . APP_URL . $url);
    exit();
}

// Flash messages
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function flashAlert() {
    $flash = getFlash();
    if ($flash) {
        $class = match($flash['type']) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info',
            default => 'alert-info'
        };
        echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

// CSRF Token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize input
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Check if user is logged in (graduate)
function isGraduateLoggedIn() {
    return isset($_SESSION['graduate_id']) && isset($_SESSION['graduate_role']) && $_SESSION['graduate_role'] === 'graduate';
}

// Check if admin is logged in (super_admin, admin, or staff)
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], ['super_admin', 'admin', 'staff']);
}

// Check if DVCAA is logged in
function isDVCaaLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'dvcaa';
}

// Require graduate login
function requireGraduateLogin() {
    if (!isGraduateLoggedIn()) {
        setFlash('error', 'Please login to access this page.');
        redirect('/index.php');
    }
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_destroy();
        setFlash('error', 'Session expired. Please login again.');
        redirect('/index.php');
    }
    $_SESSION['last_activity'] = time();
}

// Require admin login
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        setFlash('error', 'Admin access required.');
        redirect('/index.php');
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_destroy();
        setFlash('error', 'Session expired. Please login again.');
        redirect('/index.php');
    }
    $_SESSION['last_activity'] = time();
}

// Require DVCAA login
function requireDVCaaLogin() {
    if (!isDVCaaLoggedIn()) {
        setFlash('error', 'DVCAA access required.');
        redirect('/index.php');
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_destroy();
        setFlash('error', 'Session expired. Please login again.');
        redirect('/index.php');
    }
    $_SESSION['last_activity'] = time();
}

// Check password expiry
function isPasswordExpired($expiryDate) {
    if (!$expiryDate) return true;
    return strtotime($expiryDate) < time();
}

// Format date
function formatDate($date) {
    $ts = strtotime($date);
    if (date('H:i:s', $ts) === '00:00:00') {
        return date('M d, Y', $ts);
    }
    return date('M d, Y \a\t H:i', $ts);
}

function formatDateTime($date) {
    return date('M d, Y H:i', strtotime($date));
}

// Get verification badge
function getVerificationBadge($status) {
    return match($status) {
        'verified' => '<span class="badge bg-success">Verified</span>',
        'not_verified' => '<span class="badge bg-danger">Not Verified</span>',
        'pending' => '<span class="badge bg-warning text-dark">Pending</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };
}

// Get employment status badge
function getEmploymentBadge($status) {
    return match($status) {
        'employed' => '<span class="badge bg-success">Employed</span>',
        'self_employed' => '<span class="badge bg-info">Self Employed</span>',
        'unemployed' => '<span class="badge bg-danger">Unemployed</span>',
        'further_studies' => '<span class="badge bg-primary">Further Studies</span>',
        'seeking' => '<span class="badge bg-warning text-dark">Seeking</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };
}

// Log activity
function logActivity($userType, $userId, $action, $details = '') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO activity_logs (user_type, user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userType, $userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
}

// Pagination helper
function paginate($totalRecords, $recordsPerPage, $currentPage, $baseUrl) {
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $html = '';
    
    if ($totalPages > 1) {
        $html .= '<nav><ul class="pagination justify-content-center">';
        
        $prev = $currentPage - 1;
        if ($prev > 0) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $prev . '">Previous</a></li>';
        }
        
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $currentPage) ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
        }
        
        $next = $currentPage + 1;
        if ($next <= $totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $next . '">Next</a></li>';
        }
        
        $html .= '</ul></nav>';
    }
    
    return $html;
}
