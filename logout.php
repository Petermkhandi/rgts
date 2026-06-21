<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear user session data but preserve flash
$oldSessionId = session_id();
$_SESSION = [];
session_regenerate_id(true);
$_SESSION['flash'] = ['type' => 'success', 'message' => 'You have been logged out successfully.'];
redirect('/index.php');
