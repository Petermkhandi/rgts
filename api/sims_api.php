<?php
/**
 * SIMS Integration API
 * Simulates fetching student data from RUCU SIMS system
 * In production, replace with actual API/database connection
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Mock SIMS data
function getSIMSData() {
    return [
        [
            'reg_number' => 'RUCU/2025/001',
            'full_name' => 'Grace Mbowe',
            'email' => 'grace.mbowe@email.com',
            'phone' => '+255712345020',
            'course' => 'Bachelor of Education',
            'graduation_year' => 2025,
            'form4_index_number' => 'S0113/0013/2021'
        ],
        [
            'reg_number' => 'RUCU/2025/002',
            'full_name' => 'James Mrema',
            'email' => 'james.mrema@email.com',
            'phone' => '+255712345021',
            'course' => 'Bachelor of Business Administration',
            'graduation_year' => 2025,
            'form4_index_number' => 'S0114/0014/2021'
        ],
        [
            'reg_number' => 'RUCU/2025/003',
            'full_name' => 'Lucy Mwangosi',
            'email' => 'lucy.mwangosi@email.com',
            'phone' => '+255712345022',
            'course' => 'Bachelor of Science in ICT',
            'graduation_year' => 2025,
            'form4_index_number' => 'S0115/0015/2021'
        ],
        [
            'reg_number' => 'RUCU/2025/004',
            'full_name' => 'Samuel Mushi',
            'email' => 'samuel.mushi@email.com',
            'phone' => '+255712345023',
            'course' => 'Bachelor of Nursing',
            'graduation_year' => 2025,
            'form4_index_number' => 'S0116/0016/2021'
        ],
        [
            'reg_number' => 'RUCU/2025/005',
            'full_name' => 'Esther Mlay',
            'email' => 'esther.mlay@email.com',
            'phone' => '+255712345024',
            'course' => 'Bachelor of Theology',
            'graduation_year' => 2025,
            'form4_index_number' => 'S0117/0017/2021'
        ],
    ];
}

// Sync graduates from SIMS
function syncFromSIMS($sync_type = 'incremental') {
    $db = getDB();
    $sims_data = getSIMSData();
    
    $processed = 0;
    $added = 0;
    $updated = 0;
    
    // Log sync start
    $stmt = $db->prepare("INSERT INTO sims_sync_log (sync_type, status, started_at) VALUES (?, 'success', NOW())");
    $stmt->execute([$sync_type]);
    $log_id = $db->lastInsertId();
    
    foreach ($sims_data as $student) {
        $processed++;
        
        // Check if student already exists
        $stmt = $db->prepare("SELECT id FROM graduates WHERE reg_number = ?");
        $stmt->execute([$student['reg_number']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing record
            $stmt = $db->prepare("UPDATE graduates SET full_name = ?, email = ?, phone = ?, course = ?, graduation_year = ?, form4_index_number = ? WHERE reg_number = ?");
            $stmt->execute([
                $student['full_name'],
                $student['email'],
                $student['phone'],
                $student['course'],
                $student['graduation_year'],
                $student['form4_index_number'],
                $student['reg_number']
            ]);
            $updated++;
        } else {
            // Insert new record
            $stmt = $db->prepare("INSERT INTO graduates (reg_number, full_name, email, phone, course, graduation_year, form4_index_number, first_login) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([
                $student['reg_number'],
                $student['full_name'],
                $student['email'],
                $student['phone'],
                $student['course'],
                $student['graduation_year'],
                $student['form4_index_number']
            ]);
            $added++;
        }
    }
    
    // Update sync log
    $stmt = $db->prepare("UPDATE sims_sync_log SET records_processed = ?, records_added = ?, records_updated = ?, completed_at = NOW() WHERE id = ?");
    $stmt->execute([$processed, $added, $updated, $log_id]);
    
    return [
        'processed' => $processed,
        'added' => $added,
        'updated' => $updated,
        'log_id' => $log_id
    ];
}

// API endpoint
if (basename($_SERVER['PHP_SELF']) === 'sims_api.php') {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'sync':
            requireAdminLogin();
            $result = syncFromSIMS($_GET['type'] ?? 'incremental');
            echo json_encode(['success' => true, 'data' => $result]);
            break;
        case 'get_student':
            $reg = $_GET['reg'] ?? '';
            $db = getDB();
            $stmt = $db->prepare("SELECT reg_number, full_name, course, graduation_year, form4_index_number FROM graduates WHERE reg_number = ?");
            $stmt->execute([$reg]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
            break;
        case 'list':
            $db = getDB();
            $stmt = $db->query("SELECT reg_number, full_name, course, graduation_year FROM graduates ORDER BY graduation_year DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
