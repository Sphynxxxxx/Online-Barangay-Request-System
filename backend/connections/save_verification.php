<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get the data from the request
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['email']) && isset($data['code'])) {
    // Store the verification code and email in the session
    $_SESSION['verification_code'] = $data['code'];
    $_SESSION['verification_email'] = $data['email'];
    
    // Calculate expiry time (30 minutes from now)
    $_SESSION['verification_expires'] = time() + (30 * 60);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Missing email or code']);
}
?>