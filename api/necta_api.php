<?php
/**
 * NECTA Verification API
 * Simulates NECTA verification using Form IV Index Number
 * In production, replace with actual NECTA API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Mock NECTA database
$necta_database = [
    'S0101/0001/2016' => ['name' => 'John Mwangema', 'year' => '2016', 'verified' => true],
    'S0102/0002/2016' => ['name' => 'Mary Josephat', 'year' => '2016', 'verified' => true],
    'S0103/0003/2017' => ['name' => 'Emmanuel Mushi', 'year' => '2017', 'verified' => true],
    'S0104/0004/2017' => ['name' => 'Grace Mlay', 'year' => '2017', 'verified' => true],
    'S0105/0005/2018' => ['name' => 'David Mrema', 'year' => '2018', 'verified' => true],
    'S0106/0006/2018' => ['name' => 'Sarah Kimaro', 'year' => '2018', 'verified' => true],
    'S0107/0007/2019' => ['name' => 'Michael Massawe', 'year' => '2019', 'verified' => true],
    'S0108/0008/2019' => ['name' => 'Rebecca Mtegha', 'year' => '2019', 'verified' => true],
    'S0109/0009/2019' => ['name' => 'Joseph Mmbaga', 'year' => '2019', 'verified' => true],
    'S0110/0010/2020' => ['name' => 'Elizabeth Swai', 'year' => '2020', 'verified' => true],
    'S0111/0011/2020' => ['name' => 'Peter Mgaya', 'year' => '2020', 'verified' => true],
    'S0112/0012/2020' => ['name' => 'Anna Lema', 'year' => '2020', 'verified' => true],
];

// Verify Form IV Index Number
function verifyNECTA($index_number) {
    global $necta_database;
    
    $index_number = strtoupper(trim($index_number));
    
    if (isset($necta_database[$index_number])) {
        return [
            'status' => 'verified',
            'message' => 'Index number verified in NECTA database',
            'details' => $necta_database[$index_number]
        ];
    }
    
    // Simulate random verification for unknown indexes
    if (rand(1, 10) > 3) {
        return [
            'status' => 'verified',
            'message' => 'Index number verified in NECTA database',
            'details' => ['name' => 'Unknown', 'year' => 'Unknown', 'verified' => true]
        ];
    }
    
    return [
        'status' => 'not_verified',
        'message' => 'Index number not found in NECTA database',
        'details' => null
    ];
}

// API endpoint
if (basename($_SERVER['PHP_SELF']) === 'necta_api.php') {
    header('Content-Type: application/json');
    
    $index_number = $_GET['index'] ?? $_POST['index'] ?? '';
    
    if (empty($index_number)) {
        echo json_encode(['success' => false, 'message' => 'Index number required']);
        exit;
    }
    
    $result = verifyNECTA($index_number);
    echo json_encode(['success' => true, 'data' => $result]);
}
