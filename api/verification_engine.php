<?php
/**
 * NECTA & Employment Verification Engine
 * Simulates verification against NECTA database and employer records
 */

// Mock NECTA database - Form IV Index Numbers that are "verified"
$necta_verified_indexes = [
    'S0101/0001/2016', 'S0102/0002/2016', 'S0103/0003/2017',
    'S0104/0004/2017', 'S0105/0005/2018', 'S0106/0006/2018',
    'S0107/0007/2019', 'S0108/0008/2019', 'S0109/0009/2019',
    'S0110/0010/2020', 'S0111/0011/2020', 'S0112/0012/2020',
];

// Mock employer database for verification
$verified_employers = [
    'ministry of education', 'crdb bank', 'nmb bank',
    'tanzania communications authority', 'tcra', 'kcmc hospital',
    'ministry of health', 'nita', 'mobilepay',
    'university of dar es salaam', 'st. mary secondary school',
    'mobilepay tanzania',
];

function runVerification($db, $graduate_id, $employment_status) {
    global $necta_verified_indexes, $verified_employers;
    
    // Get graduate details
    $stmt = $db->prepare("SELECT g.*, ed.company_name FROM graduates g LEFT JOIN employment_details ed ON g.id = ed.graduate_id WHERE g.id = ?");
    $stmt->execute([$graduate_id]);
    $graduate = $stmt->fetch();
    
    $form4_index = $graduate['form4_index_number'];
    $company = strtolower($graduate['company_name'] ?? '');
    
    // Step 1: NECTA Verification (Form IV Index Number)
    $necta_status = 'not_verified';
    $necta_notes = '';
    
    if (in_array($form4_index, $necta_verified_indexes)) {
        $necta_status = 'verified';
        $necta_notes = 'Form IV index number verified through NECTA database.';
    } else {
        // Simulate random verification for unknown indexes
        if (rand(1, 10) > 3) {
            $necta_status = 'verified';
            $necta_notes = 'Form IV index number verified through NECTA database.';
        } else {
            $necta_notes = 'Form IV index number not found in NECTA database.';
        }
    }
    
    // Step 2: Employer Verification (Simulated)
    $employer_match = 0;
    $employer_notes = '';
    
    if (!empty($company)) {
        foreach ($verified_employers as $employer) {
            if (stripos($company, $employer) !== false) {
                $employer_match = 1;
                $employer_notes = "Employment record matched with {$employer} database.";
                break;
            }
        }
        
        if ($employer_match === 0 && in_array($employment_status, ['employed', 'self_employed'])) {
            // Random match for demo purposes
            if (rand(1, 10) > 5) {
                $employer_match = 1;
                $employer_notes = "Employment record found in simulated employer database.";
            } else {
                $employer_notes = "No matching employer record found. Manual review may be required.";
            }
        }
    }
    
    // Step 3: Determine overall verification status
    $verification_status = 'pending';
    $notes = [];
    
    $notes[] = "NECTA: " . $necta_notes;
    if (!empty($company)) {
        $notes[] = "Employer: " . $employer_notes;
    }
    
    if ($necta_status === 'verified' && $employer_match === 1) {
        $verification_status = 'verified';
    } elseif ($necta_status === 'verified' && empty($company)) {
        $verification_status = 'verified';
    } elseif ($necta_status === 'verified' && $employer_match === 0) {
        $verification_status = 'pending';
    } elseif ($necta_status === 'not_verified') {
        $verification_status = 'not_verified';
    }
    
    // Store verification result
    $stmt = $db->prepare("
        INSERT INTO verification_logs (graduate_id, verification_status, verification_source, necta_status, employer_match, notes, date_checked)
        VALUES (?, ?, 'necta', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $graduate_id,
        $verification_status,
        $necta_status,
        $employer_match,
        implode(' ', $notes)
    ]);
    
    return [
        'status' => $verification_status,
        'necta' => $necta_status,
        'employer_match' => $employer_match,
        'notes' => implode(' ', $notes),
    ];
}

// Manual verification trigger for admin
function manualVerify($db, $graduate_id, $status, $notes = '') {
    $stmt = $db->prepare("
        INSERT INTO verification_logs (graduate_id, verification_status, verification_source, necta_status, notes, date_checked, checked_by)
        VALUES (?, ?, 'manual_review', ?, ?, NOW(), 'admin')
    ");
    $stmt->execute([
        $graduate_id,
        $status,
        $status,
        $notes ?: 'Manually verified by administrator.'
    ]);
    
    return true;
}
