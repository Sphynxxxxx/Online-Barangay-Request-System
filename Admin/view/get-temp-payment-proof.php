<?php

session_start();

header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'No payment proof found',
    'imagePath' => ''
];

// Function to find temporary payment proof files
function findTemporaryPaymentProof($userId, $requestId) {
    // Corrected path based on your directory structure
    $tempDir = '../../User/view/uploads/payment_proofs/temp/';
    
    // First check if directory exists
    if (!is_dir($tempDir)) {
        return null;
    }
    
    // First priority: Look for file with both user ID and request ID
    if ($userId && $requestId) {
        $files = scandir($tempDir);
        foreach ($files as $file) {
            if (strpos($file, 'temp_proof_') === 0 && 
                strpos($file, '_' . $userId . '_') !== false &&
                strpos($file, '_' . $requestId . '_') !== false) {
                return $tempDir . $file;
            }
        }
    }
    
    // Second priority: Look for request ID specific files
    if ($requestId) {
        $files = scandir($tempDir);
        foreach ($files as $file) {
            if (strpos($file, 'temp_proof_') === 0 && 
                strpos($file, '_' . $requestId . '_') !== false) {
                return $tempDir . $file;
            }
        }
    }
    
    // Third priority: Look for user ID specific files
    if ($userId) {
        $files = scandir($tempDir);
        foreach ($files as $file) {
            if (strpos($file, 'temp_proof_' . $userId . '_') === 0) {
                return $tempDir . $file;
            }
        }
    }
    
    // Fourth priority: Look for payment_proof_pending files
    $files = scandir($tempDir);
    foreach ($files as $file) {
        if (strpos($file, 'payment_proof_pending_') === 0) {
            return $tempDir . $file;
        }
    }
    
    // Fifth priority: Look for any payment_proof files
    $files = scandir($tempDir);
    foreach ($files as $file) {
        if (strpos($file, 'payment_proof_') === 0) {
            return $tempDir . $file;
        }
    }
    
    // Sixth priority: Look in session data
    if (isset($_SESSION['payment_proof']) && $_SESSION['payment_proof']['timestamp'] > (time() - 3600)) {
        $tempPath = $_SESSION['payment_proof']['file_path'];
        if (file_exists($tempPath)) {
            return $tempPath;
        }
    }
    
    return null;
}

// Check parameters
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

if ($userId || $requestId) {
    $imagePath = findTemporaryPaymentProof($userId, $requestId);
    
    if ($imagePath && file_exists($imagePath)) {
        $response['success'] = true;
        $response['message'] = 'Payment proof found';
        $response['imagePath'] = $imagePath;
    }
}

echo json_encode($response);
exit;
?>