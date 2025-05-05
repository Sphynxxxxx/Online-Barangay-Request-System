<?php
require_once dirname(__DIR__, 2) . "/backend/connections/config.php";
require_once dirname(__DIR__, 2) . "/backend/connections/database.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $response = [
        'success' => false,
        'message' => 'You must be logged in to save payment proof'
    ];
    echo json_encode($response);
    exit;
}

$userId = $_SESSION['user_id'];

// Create a temporary storage for payment proofs
function storeTemporaryPaymentProof($file, $userId) {
    $uploadDir = 'uploads/payment_proofs/temp/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'temp_proof_' . $userId . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath;
    }
    
    return false;
}

// Store payment information in session for later use
function storePaymentInfoInSession($filePath, $paymentMethod, $referenceNumber, $paymentNotes) {
    // Debug log to check reference number
    error_log('Storing reference number in session: ' . $referenceNumber);
    
    $_SESSION['payment_proof'] = [
        'file_path' => $filePath,
        'payment_method' => $paymentMethod,
        'reference_number' => $referenceNumber, // Using consistent key name
        'payment_notes' => $paymentNotes,
        'timestamp' => time()
    ];
    
    // Verify data was stored in session
    error_log('Session payment_proof data: ' . print_r($_SESSION['payment_proof'], true));
}

// Handle the AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the request data
    $paymentMethod = $_POST['payment_method'] ?? '';
    $referenceNumber = $_POST['reference_number'] ?? ''; // Match the name in the JS formData
    $paymentNotes = $_POST['payment_notes'] ?? '';
    
    // Debug logs
    error_log('Payment method received: ' . $paymentMethod);
    error_log('Reference number received: ' . $referenceNumber);
    
    // Validate inputs
    if (empty($paymentMethod)) {
        $response = [
            'success' => false,
            'message' => 'Payment method is required'
        ];
        echo json_encode($response);
        exit;
    }
    
    if (empty($referenceNumber)) {
        $response = [
            'success' => false,
            'message' => 'Reference number is required'
        ];
        echo json_encode($response);
        exit;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
        $response = [
            'success' => false,
            'message' => 'Payment proof image is required'
        ];
        echo json_encode($response);
        exit;
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($_FILES['proof_image']['type'], $allowedTypes)) {
        $response = [
            'success' => false,
            'message' => 'Invalid file type. Only JPG, PNG, and GIF images are allowed.'
        ];
        echo json_encode($response);
        exit;
    }
    
    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if ($_FILES['proof_image']['size'] > $maxSize) {
        $response = [
            'success' => false,
            'message' => 'File size exceeds the limit of 5MB'
        ];
        echo json_encode($response);
        exit;
    }
    
    try {
        // Store the file temporarily
        $filePath = storeTemporaryPaymentProof($_FILES['proof_image'], $userId);
        
        if (!$filePath) {
            throw new Exception('Failed to upload the payment proof image');
        }
        
        // Store payment info in session for later use when submitting the request
        storePaymentInfoInSession($filePath, $paymentMethod, $referenceNumber, $paymentNotes);
        
        // Success response
        $response = [
            'success' => true,
            'message' => 'Payment proof has been saved and will be associated with your request when submitted',
            'filePath' => $filePath,
            'preview' => true
        ];
        
    } catch (Exception $e) {
        // Error response
        $response = [
            'success' => false,
            'message' => 'An error occurred: ' . $e->getMessage()
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

header('Location: request-document.php');
exit;
?>