<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clear verification data
unset($_SESSION['verification_code']);
unset($_SESSION['verification_email']);
unset($_SESSION['verification_expires']);

echo json_encode(['success' => true]);
?>