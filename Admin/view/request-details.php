<?php
session_start();

$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "barangay_request_system"; 

$showAlert = false;
$alertType = "";
$alertMessage = "";
$requestDetails = null;
$paymentProof = null;

// Function to handle getting payment proof image path from different locations
function getPaymentProofPath($paymentProof) {
    // Define base paths
    $regularDir = '../../User/view/uploads/payment_proofs/';
    $tempDir = '../../User/view/uploads/payment_proofs/temp/';
    
    // If the path is already complete and file exists, return it
    if (isset($paymentProof['proof_image']) && file_exists($paymentProof['proof_image'])) {
        return $paymentProof['proof_image'];
    }
    
    // Check if it's a temporary proof with file_path set
    if (isset($paymentProof['file_path'])) {
        // Try the direct path first
        if (file_exists($paymentProof['file_path'])) {
            return $paymentProof['file_path'];
        }
        
        // Get just the filename
        $fileName = basename($paymentProof['file_path']);
        
        // Try temp directory with just the filename
        if (file_exists($tempDir . $fileName)) {
            return $tempDir . $fileName;
        }
        
        // Try regular directory with just the filename
        if (file_exists($regularDir . $fileName)) {
            return $regularDir . $fileName;
        }
    }
    
    // For request-details.php admin view - check in both locations
    $requestId = $paymentProof['request_id'] ?? 0;
    $userId = $paymentProof['user_id'] ?? 0;
    
    // Check in regular payment_proofs directory for files matching request ID
    if (is_dir($regularDir) && $requestId > 0) {
        $files = scandir($regularDir);
        foreach ($files as $file) {
            if (strpos($file, 'payment_proof_' . $requestId . '_') === 0) {
                return $regularDir . $file;
            }
        }
    }
    
    // Check in temp directory
    if (is_dir($tempDir)) {
        $files = scandir($tempDir);
        
        // First check for exact matches with request ID
        if ($requestId > 0) {
            foreach ($files as $file) {
                if (strpos($file, 'payment_proof_' . $requestId . '_') === 0 || 
                    strpos($file, 'temp_proof_') === 0 && strpos($file, '_' . $requestId . '_') !== false) {
                    return $tempDir . $file;
                }
            }
        }
        
        // Then check for files matching user ID
        if ($userId > 0) {
            foreach ($files as $file) {
                if (strpos($file, 'temp_proof_' . $userId . '_') === 0) {
                    return $tempDir . $file;
                }
            }
        }
        
        // Look for payment_proof_pending files
        foreach ($files as $file) {
            if (strpos($file, 'payment_proof_pending_') === 0) {
                return $tempDir . $file;
            }
        }
        
        // Look for any payment_proof files
        foreach ($files as $file) {
            if (strpos($file, 'payment_proof_') === 0) {
                return $tempDir . $file;
            }
        }
    }
    
    // If no file found, return a placeholder or default image
    return 'assets/img/no-image.jpg'; // Update this path to your placeholder image
}

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $showAlert = true;
    $alertType = "danger";
    $alertMessage = "Database connection failed: " . $conn->connect_error;
} else {
    // Check if request ID is provided
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $showAlert = true;
        $alertType = "danger";
        $alertMessage = "Invalid request ID.";
    } else {
        $requestId = intval($_GET['id']);

        // Fetch detailed request information
        $requestSql = "SELECT r.*, 
                        u.first_name, 
                        u.last_name, 
                        u.email,
                        u.contact_number,
                        DATE_FORMAT(r.created_at, '%M %d, %Y %h:%i %p') as formatted_created_at,
                        DATE_FORMAT(r.updated_at, '%M %d, %Y %h:%i %p') as formatted_updated_at
                      FROM requests r
                      JOIN users u ON r.user_id = u.user_id
                      WHERE r.request_id = ?";
        
        $stmt = $conn->prepare($requestSql);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $requestDetails = $result->fetch_assoc();
            
            // Also fetch payment proof if available
            $paymentSql = "SELECT * FROM payment_proofs 
                           WHERE request_id = ? 
                           ORDER BY created_at DESC 
                           LIMIT 1";
            
            $stmtPayment = $conn->prepare($paymentSql);
            $stmtPayment->bind_param("i", $requestId);
            $stmtPayment->execute();
            $paymentResult = $stmtPayment->get_result();
            
            if ($paymentResult->num_rows > 0) {
                $paymentProof = $paymentResult->fetch_assoc();
            }
            
            $stmtPayment->close();
            
            // Check for pending payment proofs that aren't in the database yet
            // After fetching the main request details, check if there's a payment proof in the session
            $pendingProofFromSession = false;
            if (!$paymentProof && isset($_SESSION['payment_proof']) && $_SESSION['payment_proof']['timestamp'] > (time() - 3600)) {
                // Create a temporary payment proof object from session data
                $pendingProofFromSession = true;
                $paymentProof = [
                    'proof_id' => 0,
                    'request_id' => $requestId,
                    'user_id' => $requestDetails['user_id'],
                    'payment_method' => $_SESSION['payment_proof']['payment_method'],
                    'payment_reference' => $_SESSION['payment_proof']['reference_number'],
                    'file_path' => $_SESSION['payment_proof']['file_path'], // This will be used by getPaymentProofPath()
                    'payment_notes' => $_SESSION['payment_proof']['payment_notes'],
                    'status' => 'submitted',
                    'created_at' => date('Y-m-d H:i:s', $_SESSION['payment_proof']['timestamp']),
                    'verified_at' => null,
                    'verified_by' => null,
                    'remarks' => null
                ];
            }

            // If no proof in database or session, check the temp folder for files matching this request or user
            if (!$paymentProof) {
                $tempDir = '../../User/view/uploads/payment_proofs/temp/';
                if (is_dir($tempDir)) {
                    $files = scandir($tempDir);
                    foreach ($files as $file) {
                        // Check for any payment proof files
                        if ((strpos($file, 'payment_proof_') === 0 || strpos($file, 'temp_proof_') === 0) && 
                            (strpos($file, $requestId . '_') !== false || strpos($file, '_' . $requestId . '_') !== false || 
                             strpos($file, $requestDetails['user_id'] . '_') !== false || strpos($file, '_' . $requestDetails['user_id'] . '_') !== false ||
                             strpos($file, 'pending') !== false)) {
                            
                            // Create a placeholder payment proof object
                            $paymentProof = [
                                'proof_id' => 0,
                                'request_id' => $requestId,
                                'user_id' => $requestDetails['user_id'],
                                'payment_method' => $requestDetails['payment_method'],
                                'payment_reference' => 'Pending Reference',
                                'file_path' => $tempDir . $file,
                                'payment_notes' => 'Temporary payment proof found',
                                'status' => 'submitted',
                                'created_at' => date('Y-m-d H:i:s', filemtime($tempDir . $file)),
                                'verified_at' => null,
                                'verified_by' => null,
                                'remarks' => null
                            ];
                            
                            $pendingProofFromSession = true; // Mark as pending so we adjust the UI
                            break;
                        }
                    }
                }
            }

            // Add a notification or badge to the UI if we found a pending proof
            if (isset($pendingProofFromSession) && $pendingProofFromSession && $paymentProof) {
                $showAlert = true;
                $alertType = "info";
                $alertMessage = "A pending payment proof has been found that hasn't been associated with this request yet. You can verify it now.";
            }
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "Request not found.";
        }
        $stmt->close();
    }

    // Handle status update
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
        $newStatus = $_POST['status'];
        $adminRemarks = isset($_POST['admin_remarks']) ? trim($_POST['admin_remarks']) : '';

        $updateSql = "UPDATE requests 
                      SET status = ?, 
                          admin_remarks = ?, 
                          updated_at = NOW() 
                      WHERE request_id = ?";
        
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ssi", $newStatus, $adminRemarks, $requestId);
        
        if ($stmt->execute()) {
            $showAlert = true;
            $alertType = "success";
            $alertMessage = "Request status updated successfully to " . ucfirst($newStatus) . ".";
            
            // Refresh request details
            $stmt = $conn->prepare($requestSql);
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            $requestDetails = $result->fetch_assoc();
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "Error updating request status: " . $stmt->error . " (Error code: " . $stmt->errno . ")";
        }
        $stmt->close();
    }
    
    // Handle payment status update
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_payment'])) {
        $paymentStatus = $_POST['payment_status'] ? 1 : 0;
        $paymentNotes = isset($_POST['payment_notes']) ? trim($_POST['payment_notes']) : '';
        
        // Update request payment status
        $updatePaymentSql = "UPDATE requests 
                            SET payment_status = ?, 
                                updated_at = NOW() 
                            WHERE request_id = ?";
        
        $stmt = $conn->prepare($updatePaymentSql);
        $stmt->bind_param("ii", $paymentStatus, $requestId);
        
        if ($stmt->execute()) {
            // If payment proof exists in database, update its status
            if (isset($_POST['proof_id']) && is_numeric($_POST['proof_id']) && $_POST['proof_id'] > 0) {
                $proofId = intval($_POST['proof_id']);
                $proofStatus = ($paymentStatus == 1) ? 'verified' : 'submitted';
                
                $updateProofSql = "UPDATE payment_proofs 
                                  SET status = ?, 
                                      remarks = ?,
                                      verified_at = NOW(),
                                      verified_by = ?
                                  WHERE proof_id = ?";
                
                $adminId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
                
                $stmtProof = $conn->prepare($updateProofSql);
                $stmtProof->bind_param("siii", $proofStatus, $paymentNotes, $adminId, $proofId);
                $stmtProof->execute();
                $stmtProof->close();
            } 
            // If it's a temporary proof, handle it differently
            elseif ($paymentProof && $paymentStatus == 1 && (!isset($_POST['proof_id']) || $_POST['proof_id'] == 0)) {
                // Get the file path from our helper function
                $sourceImagePath = getPaymentProofPath($paymentProof);
                
                if (file_exists($sourceImagePath)) {
                    // Create final destination for the image
                    $extension = pathinfo($sourceImagePath, PATHINFO_EXTENSION);
                    $finalFilename = 'payment_proof_' . $requestId . '_' . time() . '.' . $extension;
                    
                    // Use regular directory for verified proofs
                    $regularDir = '../../User/view/uploads/payment_proofs/';
                    $finalPath = $regularDir . $finalFilename;
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($regularDir)) {
                        mkdir($regularDir, 0755, true);
                    }
                    
                    // Copy the file to its final location
                    if (copy($sourceImagePath, $finalPath)) {
                        // Insert into payment_proofs table
                        $insertSql = "INSERT INTO payment_proofs (request_id, user_id, payment_method, payment_reference, 
                                      proof_image, payment_notes, status, created_at, verified_at, verified_by, remarks) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)";
                        
                        $adminId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
                        $status = 'verified';
                        
                        $stmtInsert = $conn->prepare($insertSql);
                        $stmtInsert->bind_param(
                            "iisssssisi", 
                            $requestId, 
                            $requestDetails['user_id'],
                            $paymentProof['payment_method'],
                            $paymentProof['payment_reference'],
                            $finalPath,
                            $paymentProof['payment_notes'],
                            $status,
                            $adminId,
                            $paymentNotes
                        );
                        $stmtInsert->execute();
                        $stmtInsert->close();
                        
                        // Try to delete the temporary file
                        if (strpos($sourceImagePath, 'temp') !== false) {
                            @unlink($sourceImagePath);
                        }
                    }
                }
            }
            
            $showAlert = true;
            $alertType = "success";
            $alertMessage = "Payment status updated successfully to " . ($paymentStatus ? "PAID" : "PENDING") . ".";
            
            // Refresh request details
            $stmt = $conn->prepare($requestSql);
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            $requestDetails = $result->fetch_assoc();
            
            // Refresh payment proof details
            $stmtPayment = $conn->prepare($paymentSql);
            $stmtPayment->bind_param("i", $requestId);
            $stmtPayment->execute();
            $paymentResult = $stmtPayment->get_result();
            
            if ($paymentResult->num_rows > 0) {
                $paymentProof = $paymentResult->fetch_assoc();
            }
            
            $stmtPayment->close();
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "Error updating payment status: " . $stmt->error;
        }
        $stmt->close();
    }

    // Close connection
    $conn->close();
}

// Document type mapping
$documentTypes = [
    'barangay_clearance' => 'Barangay Clearance',
    'certificate_residency' => 'Certificate of Residency',
    'business_permit' => 'Business Permit',
    'good_moral' => 'Good Moral Certificate',
    'indigency_certificate' => 'Certificate of Indigency',
    'cedula' => 'Community Tax Certificate (Cedula)',
    'solo_parent' => 'Solo Parent Certificate',
    'first_time_jobseeker' => 'First Time Jobseeker Certificate'
];

// Status colors
$statusColors = [
    'pending' => 'bg-warning text-dark',
    'processing' => 'bg-info text-white',
    'ready' => 'bg-primary',
    'completed' => 'bg-success',
    'rejected' => 'bg-danger',
    'cancelled' => 'bg-secondary'
];

// Status descriptions
$statusDescriptions = [
    'pending' => 'Request is awaiting initial review',
    'processing' => 'Request is currently being processed',
    'ready' => 'Document is ready for pickup',
    'completed' => 'Request has been completed and delivered',
    'rejected' => 'Request has been rejected',
    'cancelled' => 'Request has been cancelled'
];

// Payment proof status colors
$proofStatusColors = [
    'submitted' => 'bg-warning text-dark',
    'verified' => 'bg-success',
    'rejected' => 'bg-danger'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Details - Barangay Clearance and Document Request System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f4f6f9;
        }
        
        .card-detail {
            transition: transform 0.2s;
        }
        
        .card-detail:hover {
            transform: translateY(-5px);
        }
        
        .status-badge {
            font-size: 0.9rem;
            padding: 0.4rem 0.75rem;
        }

        .status-timeline {
            position: relative;
            padding-left: 45px;
        }

        .status-timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 22px;
            width: 2px;
            background-color: #dee2e6;
        }

        .status-item {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .status-marker {
            position: absolute;
            left: -45px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: #fff;
            border: 2px solid #dee2e6;
            top: 5px;
        }

        .status-active .status-marker {
            width: 20px;
            height: 20px;
            left: -47px;
            top: 3px;
        }
        
        /* Enhanced styles for Ready for Pickup status */
        .badge-ready {
            background-color: #2563eb !important; /* Brighter blue */
            color: white !important;
            padding: 0.4rem 0.7rem !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .badge-ready i {
            margin-right: 0.25rem;
        }
        
        /* Pulse animation for ready badges */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(37, 99, 235, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
            }
        }
        
        /* Payment proof styles */
        .proof-image {
            max-width: 100%;
            height: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .proof-image:hover {
            transform: scale(1.02);
        }
        
        #paymentProofModal .modal-img {
            max-width: 100%;
            max-height: 80vh;
        }
        
        .payment-card {
            border-left: 4px solid #0d6efd;
        }
        
        .payment-verified {
            border-left: 4px solid #198754;
        }
        
        .payment-rejected {
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>Request Details
                    </h1>
                    <a href="document-requests.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Requests
                    </a>
                </div>

                <?php if ($showAlert): ?>
                    <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $alertMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($requestDetails): ?>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card card-detail mb-4">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    Request #<?php echo $requestDetails['request_id']; ?> 
                                    - <?php echo $documentTypes[$requestDetails['document_type']] ?? $requestDetails['document_type']; ?>
                                </h5>
                                <?php if ($requestDetails['status'] == 'ready'): ?>
                                    <span class="badge badge-ready status-badge" style="animation: pulse 2s infinite;">
                                        <i class="bi bi-box-seam"></i> Ready for Pickup
                                    </span>
                                <?php else: ?>
                                    <span class="badge <?php echo $statusColors[$requestDetails['status']] ?? 'bg-secondary'; ?> status-badge">
                                        <?php echo ucfirst($requestDetails['status']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>Purpose:</strong>
                                        <p><?php echo htmlspecialchars($requestDetails['purpose']); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Processing Fee:</strong>
                                        <p>₱<?php echo number_format($requestDetails['processing_fee'], 2); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Payment Method:</strong>
                                        <p>
                                            <?php 
                                            $paymentMethods = [
                                                'cash' => 'Cash on Pickup',
                                                'gcash' => 'GCash',
                                                'paymaya' => 'PayMaya',
                                                'bank_transfer' => 'Bank Transfer'
                                            ];
                                            
                                            $paymentMethod = isset($requestDetails['payment_method']) ? $requestDetails['payment_method'] : 'cash';
                                            echo htmlspecialchars($paymentMethods[$paymentMethod] ?? 'Cash on Pickup');
                                            ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Urgent Request:</strong>
                                        <p><?php echo $requestDetails['urgent_request'] ? 'Yes' : 'No'; ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Payment Status:</strong>
                                        <p>
                                            <?php 
                                            if (isset($requestDetails['payment_status']) && $requestDetails['payment_status'] == 1): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending Payment</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Status Timeline -->
                                <div class="mt-4">
                                    <h6 class="mb-3">Request Status Timeline</h6>
                                    <div class="status-timeline">
                                        <?php
                                        $statusOrder = ['pending', 'processing', 'ready', 'completed'];
                                        $currentStatusIndex = array_search($requestDetails['status'], $statusOrder);
                                        
                                        // Show different flow for rejected or cancelled
                                        if ($requestDetails['status'] == 'rejected' || $requestDetails['status'] == 'cancelled'):
                                        ?>
                                            <div class="status-item status-active">
                                                <div class="status-marker bg-warning"></div>
                                                <h6>Pending</h6>
                                                <p class="text-muted small">Request submitted and awaiting review</p>
                                            </div>
                                            <div class="status-item status-active">
                                                <div class="status-marker bg-<?php echo $requestDetails['status'] == 'rejected' ? 'danger' : 'secondary'; ?>"></div>
                                                <h6><?php echo ucfirst($requestDetails['status']); ?></h6>
                                                <p class="text-muted small">
                                                    <?php echo $statusDescriptions[$requestDetails['status']]; ?>
                                                    <br>
                                                    <span class="fst-italic">
                                                        <?php echo date('M d, Y g:i A', strtotime($requestDetails['updated_at'])); ?>
                                                    </span>
                                                </p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($statusOrder as $index => $status): ?>
                                                <div class="status-item <?php echo ($index <= $currentStatusIndex) ? 'status-active' : ''; ?>">
                                                    <div class="status-marker bg-<?php 
                                                        if ($index < $currentStatusIndex) {
                                                            echo 'success';
                                                        } elseif ($index == $currentStatusIndex) {
                                                            echo str_replace(['text-dark', 'text-white'], '', $statusColors[$status]);
                                                        } else {
                                                            echo 'light';
                                                        }
                                                    ?>"></div>
                                                    <h6><?php echo $status == 'ready' ? 'Ready for Pickup' : ucfirst($status); ?></h6>
                                                    <p class="text-muted small">
                                                        <?php echo $statusDescriptions[$status]; ?>
                                                        <?php if ($index == $currentStatusIndex): ?>
                                                            <br>
                                                            <span class="fst-italic">
                                                                <?php echo date('M d, Y g:i A', strtotime($requestDetails['updated_at'])); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Created At:</strong>
                                        <p><?php echo $requestDetails['formatted_created_at']; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Last Updated:</strong>
                                        <p><?php echo $requestDetails['formatted_updated_at']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Proof Section (New) -->
                        <?php if ($paymentProof): ?>
                        <div class="card mb-4 <?php 
                            if (isset($paymentProof['status'])) {
                                if ($paymentProof['status'] == 'verified') {
                                    echo 'payment-verified';
                                } elseif ($paymentProof['status'] == 'rejected') {
                                    echo 'payment-rejected';
                                } else {
                                    echo 'payment-card';
                                }
                            } else {
                                echo 'payment-card';
                            }
                        ?>">
                            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-credit-card me-2"></i>Payment Proof
                                </h5>
                                <span class="badge <?php 
                                    if (isset($paymentProof['status'])) {
                                        echo $proofStatusColors[$paymentProof['status']] ?? 'bg-secondary';
                                    } else {
                                        echo 'bg-warning text-dark';
                                    }
                                ?>">
                                    <?php echo isset($paymentProof['status']) ? ucfirst($paymentProof['status']) : 'Pending'; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <strong>Reference Number:</strong>
                                            <p><?php echo htmlspecialchars($paymentProof['payment_reference'] ?? 'Pending'); ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Payment Method:</strong>
                                            <p>
                                                <?php 
                                                $paymentMethod = isset($paymentProof['payment_method']) ? $paymentProof['payment_method'] : 
                                                                (isset($requestDetails['payment_method']) ? $requestDetails['payment_method'] : 'cash');
                                                echo htmlspecialchars($paymentMethods[$paymentMethod] ?? ucfirst($paymentMethod));
                                                ?>
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <strong>Submitted On:</strong>
                                            <p><?php echo isset($paymentProof['created_at']) ? date('M d, Y g:i A', strtotime($paymentProof['created_at'])) : 'Pending'; ?></p>
                                        </div>
                                        <?php if (!empty($paymentProof['payment_notes'])): ?>
                                        <div class="mb-3">
                                            <strong>User Notes:</strong>
                                            <p><?php echo nl2br(htmlspecialchars($paymentProof['payment_notes'])); ?></p>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (isset($paymentProof['status']) && $paymentProof['status'] == 'verified'): ?>
                                        <div class="alert alert-success">
                                            <i class="bi bi-check-circle me-2"></i>
                                            Verified on <?php echo date('M d, Y g:i A', strtotime($paymentProof['verified_at'])); ?>
                                        </div>
                                        <?php elseif (isset($pendingProofFromSession) && $pendingProofFromSession): ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-2"></i>
                                            This is a pending payment proof that hasn't been fully processed yet.
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center mb-3">
                                            <img src="<?php echo htmlspecialchars(getPaymentProofPath($paymentProof)); ?>" 
                                                class="proof-image img-thumbnail" alt="Payment Proof" 
                                                style="max-width: 150px; height: auto; cursor: pointer;"
                                                onerror="this.src='assets/img/no-image.jpg';this.classList.add('img-error');"
                                                onclick="openPaymentProofModal('<?php echo htmlspecialchars(getPaymentProofPath($paymentProof)); ?>')"
                                                title="Click to view larger image">
                                        </div>

                                        <div class="d-grid">
                                            <button type="button" class="btn btn-primary" 
                                                onclick="openPaymentProofModal('<?php echo htmlspecialchars(getPaymentProofPath($paymentProof)); ?>')">
                                                <i class="bi bi-zoom-in me-2"></i>View Full Image
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Payment Status Update Form -->
                                <div class="mt-4">
                                    <h6 class="border-bottom pb-2 mb-3">Update Payment Status</h6>
                                    <form method="POST" id="paymentUpdateForm">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" 
                                                        id="payment_status" name="payment_status" value="1"
                                                        <?php echo (isset($requestDetails['payment_status']) && $requestDetails['payment_status'] == 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="payment_status">
                                                        Mark as Paid
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <input type="hidden" name="proof_id" value="<?php echo $paymentProof['proof_id'] ?? 0; ?>">
                                            </div>
                                            <!--<div class="col-12 mb-3">
                                                <label for="payment_notes" class="form-label">Payment Notes/Remarks</label>
                                                <textarea class="form-control" id="payment_notes" name="payment_notes" rows="2"><?php 
                                                    echo htmlspecialchars(($paymentProof['remarks'] && $paymentProof['remarks'] !== '0') ? $paymentProof['remarks'] : ''); 
                                                ?></textarea>
                                            </div>-->
                                            <div class="col-12">
                                                <input type="hidden" name="update_payment" value="1">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="bi bi-check-circle me-2"></i>Update Payment Status
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($requestDetails['admin_remarks'])): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <i class="bi bi-info-circle me-2"></i>Admin Remarks
                            </div>
                            <div class="card-body">
                                <p><?php echo nl2br(htmlspecialchars($requestDetails['admin_remarks'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">
                                <i class="bi bi-person-circle me-2"></i>Requestor Information
                            </div>
                            <div class="card-body">
                                <h5><?php echo htmlspecialchars($requestDetails['first_name'] . ' ' . $requestDetails['last_name']); ?></h5>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($requestDetails['email']); ?></p>
                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($requestDetails['contact_number'] ?? 'N/A'); ?></p>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <i class="bi bi-pencil-square me-2"></i>Update Request Status
                            </div>
                            <div class="card-body">
                                <form method="POST" id="statusUpdateForm">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select name="status" id="status" class="form-select" required>
                                            <option value="pending" <?php echo $requestDetails['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="processing" <?php echo $requestDetails['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="ready" <?php echo $requestDetails['status'] === 'ready' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                            <option value="completed" <?php echo $requestDetails['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="rejected" <?php echo $requestDetails['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="cancelled" <?php echo $requestDetails['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="admin_remarks" class="form-label">Admin Remarks</label>
                                        <textarea name="admin_remarks" id="admin_remarks" class="form-control" rows="3" placeholder="Add notes about this status change"><?php echo htmlspecialchars($requestDetails['admin_remarks'] ?? ''); ?></textarea>
                                    </div>
                                    <input type="hidden" name="update_status" value="1">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary" id="updateStatusBtn">
                                            <i class="bi bi-save me-2"></i>Update Status
                                        </button>
                                        <?php if ($requestDetails['document_type'] === 'barangay_clearance'): ?>
                                        <a href="documents-edit/edit-brgyclearance.php?id=<?php echo $requestDetails['request_id']; ?>" class="btn btn-success">
                                            <i class="bi bi-file-earmark-text me-2"></i>Edit Clearance Details
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <i class="bi bi-info-circle me-2"></i>Quick Actions
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if ($requestDetails['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-outline-info" onclick="updateStatus('processing')">
                                        <i class="bi bi-arrow-right-circle me-2"></i>Move to Processing
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="updateStatus('rejected')">
                                        <i class="bi bi-x-circle me-2"></i>Reject Request
                                    </button>
                                    <?php elseif ($requestDetails['status'] === 'processing'): ?>
                                    <button type="button" class="btn btn-outline-primary" onclick="updateStatus('ready')">
                                        <i class="bi bi-box-seam me-2"></i>Mark as Ready for Pickup
                                    </button>
                                    <button type="button" class="btn btn-outline-success" onclick="updateStatus('completed')">
                                        <i class="bi bi-check-circle me-2"></i>Mark as Completed
                                    </button>
                                    <?php elseif ($requestDetails['status'] === 'ready'): ?>
                                    <button type="button" class="btn btn-outline-success" onclick="updateStatus('completed')">
                                        <i class="bi bi-check-circle me-2"></i>Mark as Completed
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($requestDetails['status'] !== 'completed' && $requestDetails['status'] !== 'rejected' && $requestDetails['status'] !== 'cancelled'): ?>
                                    <button type="button" class="btn btn-outline-secondary" onclick="updateStatus('cancelled')">
                                        <i class="bi bi-x-circle me-2"></i>Cancel Request
                                    </button>
                                    <?php endif; ?>
                                    
                                    <!-- Quick payment status buttons -->
                                    <?php if (isset($requestDetails['payment_status']) && $requestDetails['payment_status'] != 1 && $paymentProof): ?>
                                    <hr>
                                    <button type="button" class="btn btn-outline-success" onclick="markAsPaid()">
                                        <i class="bi bi-cash-coin me-2"></i>Mark as Paid
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="paymentProofModal" tabindex="-1" aria-labelledby="paymentProofModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentProofModalLabel">
                        <i class="bi bi-image me-2"></i>Payment Proof
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="position-relative">
                        <img src="" id="modalPaymentProofImage" class="modal-img img-fluid" 
                            alt="Payment Proof" style="max-height: 70vh;"
                            onerror="this.src='assets/img/no-image.jpg';this.classList.add('img-error');">
                        
                        <!-- Loading indicator -->
                        <div id="imageLoadingSpinner" class="position-absolute top-50 start-50 translate-middle">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment info box (shown below image) -->
                    <?php if ($paymentProof): ?>
                    <div class="mt-3 text-start p-3 bg-light rounded">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Reference:</strong> <?php echo htmlspecialchars($paymentProof['payment_reference'] ?? 'Pending'); ?></p>
                                <p class="mb-1"><strong>Method:</strong> <?php 
                                $paymentMethod = isset($paymentProof['payment_method']) ? $paymentProof['payment_method'] : 
                                                (isset($requestDetails['payment_method']) ? $requestDetails['payment_method'] : 'cash');
                                echo htmlspecialchars($paymentMethods[$paymentMethod] ?? ucfirst($paymentMethod)); 
                                ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Status:</strong> 
                                    <span class="badge <?php echo isset($paymentProof['status']) ? 
                                        ($proofStatusColors[$paymentProof['status']] ?? 'bg-secondary') : 'bg-warning text-dark'; ?>">
                                        <?php echo isset($paymentProof['status']) ? ucfirst($paymentProof['status']) : 'Pending'; ?>
                                    </span>
                                </p>
                                <p class="mb-1"><strong>Submitted:</strong> <?php echo isset($paymentProof['created_at']) ? 
                                    date('M d, Y g:i A', strtotime($paymentProof['created_at'])) : 'Pending'; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if ($paymentProof && isset($requestDetails['payment_status']) && $requestDetails['payment_status'] != 1): ?>
                    <button type="button" class="btn btn-success" onclick="markAsPaid()">
                        <i class="bi bi-check-circle me-2"></i>Verify Payment
                    </button>
                    <?php endif; ?>
                    
                    <!-- Add Download Button -->
                    <a href="#" id="downloadProofBtn" class="btn btn-primary" download target="_blank">
                        <i class="bi bi-download me-2"></i>Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to update status with quick action buttons
        function updateStatus(status) {
            const statusSelect = document.getElementById('status');
            statusSelect.value = status;
            
            // Prompt for remarks based on status
            let defaultRemarks = '';
            if (status === 'rejected') {
                defaultRemarks = document.getElementById('admin_remarks').value;
                const reason = prompt('Please provide a reason for rejecting this request:', defaultRemarks);
                if (reason !== null) {
                    document.getElementById('admin_remarks').value = reason;
                } else {
                    return; // User cancelled
                }
            } else if (status === 'cancelled') {
                defaultRemarks = document.getElementById('admin_remarks').value;
                const reason = prompt('Please provide a reason for cancelling this request:', defaultRemarks);
                if (reason !== null) {
                    document.getElementById('admin_remarks').value = reason;
                } else {
                    return; // User cancelled
                }
            } else if (status === 'ready') {
                document.getElementById('admin_remarks').value += 
                    (document.getElementById('admin_remarks').value ? '\n\n' : '') + 
                    'Document is ready for pickup. Please bring valid ID.';
            } else if (status === 'completed') {
                document.getElementById('admin_remarks').value += 
                    (document.getElementById('admin_remarks').value ? '\n\n' : '') + 
                    'Request has been completed.';
            }
            
            // Submit the form
            document.getElementById('statusUpdateForm').submit();
        }

        // Confirm before status update if changing to rejected or cancelled
        document.getElementById('statusUpdateForm').addEventListener('submit', function(e) {
            const status = document.getElementById('status').value;
            const currentStatus = '<?php echo $requestDetails['status']; ?>';
            
            if ((status === 'rejected' || status === 'cancelled') && 
                (currentStatus !== 'rejected' && currentStatus !== 'cancelled')) {
                
                const remarks = document.getElementById('admin_remarks').value.trim();
                
                if (remarks === '') {
                    e.preventDefault();
                    alert('Please provide remarks explaining why the request is being ' + 
                        (status === 'rejected' ? 'rejected' : 'cancelled'));
                    return false;
                }
                
                if (!confirm('Are you sure you want to ' + 
                            (status === 'rejected' ? 'reject' : 'cancel') + 
                            ' this request? This action cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Enhanced function to open payment proof modal
        function openPaymentProofModal(imageSrc) {
            // Show loading spinner if it exists
            const spinner = document.getElementById('imageLoadingSpinner');
            if (spinner) {
                spinner.style.display = 'block';
            }
            
            // Set the image source
            const modalImg = document.getElementById('modalPaymentProofImage');
            if (modalImg) {
                modalImg.src = imageSrc;
                
                // Update download button if it exists
                const downloadBtn = document.getElementById('downloadProofBtn');
                if (downloadBtn) {
                    downloadBtn.href = imageSrc;
                    downloadBtn.classList.remove('disabled');
                    downloadBtn.removeAttribute('aria-disabled');
                }
            }
            
            // Show the modal
            const modalElement = document.getElementById('paymentProofModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        }

        // Handle image loading events
        document.addEventListener('DOMContentLoaded', function() {
            const modalImg = document.getElementById('modalPaymentProofImage');
            if (modalImg) {
                // Handle successful image load
                modalImg.onload = function() {
                    // Hide spinner if it exists
                    const spinner = document.getElementById('imageLoadingSpinner');
                    if (spinner) {
                        spinner.style.display = 'none';
                    }
                };
                
                // Handle image loading errors
                modalImg.onerror = function() {
                    // Hide spinner if it exists
                    const spinner = document.getElementById('imageLoadingSpinner');
                    if (spinner) {
                        spinner.style.display = 'none';
                    }
                    
                    // Show placeholder image
                    this.src = 'assets/img/no-image.jpg';
                    this.classList.add('img-error');
                    console.warn('Failed to load payment proof image');
                    
                    // Disable download button
                    const downloadBtn = document.getElementById('downloadProofBtn');
                    if (downloadBtn) {
                        downloadBtn.classList.add('disabled');
                        downloadBtn.setAttribute('aria-disabled', 'true');
                    }
                };
            }
        });

        // Quick function to mark as paid
        function markAsPaid() {
            const paymentCheckbox = document.getElementById('payment_status');
            if (paymentCheckbox) {
                paymentCheckbox.checked = true;
                
                // Add standard verification note if empty
                const paymentNotes = document.getElementById('payment_notes');
                if (paymentNotes && paymentNotes.value.trim() === '') {
                    paymentNotes.value = 'Payment verified by admin.';
                }
                
                // Submit the payment form
                document.getElementById('paymentUpdateForm').submit();
            }
        }

        // Function to handle image viewing from temp directory
        function viewTemporaryPaymentProof(userId, requestId) {
            // Show a loading message
            const loadingToast = showToast('Loading payment proof...', 'info');
            
            // Try to find any images in the temp directory that match this user or request
            fetch('get-temp-payment-proof.php?user_id=' + userId + '&request_id=' + requestId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Hide the loading toast
                    if (loadingToast) {
                        loadingToast.hide();
                    }
                    
                    if (data.success && data.imagePath) {
                        openPaymentProofModal(data.imagePath);
                    } else {
                        showToast('No temporary payment proof found.', 'warning');
                    }
                })
                .catch(error => {
                    console.error('Error fetching temporary payment proof:', error);
                    showToast('Error fetching payment proof. Please try again.', 'danger');
                    
                    // Hide the loading toast
                    if (loadingToast) {
                        loadingToast.hide();
                    }
                });
        }

        // Function to display toast notifications
        function showToast(message, type = 'info') {
            // Check if Bootstrap 5 toast is available
            if (typeof bootstrap !== 'undefined' && typeof bootstrap.Toast !== 'undefined') {
                // Create toast container if it doesn't exist
                let toastContainer = document.querySelector('.toast-container');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                    document.body.appendChild(toastContainer);
                }
                
                // Create the toast element
                const toastEl = document.createElement('div');
                toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
                toastEl.setAttribute('role', 'alert');
                toastEl.setAttribute('aria-live', 'assertive');
                toastEl.setAttribute('aria-atomic', 'true');
                
                // Create toast content
                toastEl.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                
                // Add the toast to the container
                toastContainer.appendChild(toastEl);
                
                // Initialize and show the toast
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
                
                // Return the toast instance for further control
                return toast;
            } else {
                // Fallback to alert if Bootstrap is not available
                alert(message);
                return null;
            }
        }

        // Highlight ready for pickup status with animation
        document.addEventListener('DOMContentLoaded', function() {
            // Add special animation to Ready for Pickup badges
            const readyBadges = document.querySelectorAll('.badge-ready');
            readyBadges.forEach(badge => {
                // Add subtle pulse animation
                badge.style.animation = 'pulse 2s infinite';
            });
            
            // Add click handler to payment proof images to ensure they open the modal
            const proofImages = document.querySelectorAll('.proof-image');
            proofImages.forEach(image => {
                image.addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent default click behavior
                    openPaymentProofModal(this.src);
                });
                
                // Add error handler for images
                image.onerror = function() {
                    this.src = 'assets/img/no-image.jpg'; // Replace with your placeholder image
                    this.classList.add('img-error');
                };
            });
            
            // Set up download button functionality
            const downloadBtn = document.getElementById('downloadProofBtn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function(e) {
                    // Check if button is disabled
                    if (this.classList.contains('disabled')) {
                        e.preventDefault();
                        showToast('No image available to download.', 'warning');
                    }
                });
            }
            
            // Initialize any tooltips
            if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined') {
                const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltips.forEach(tooltip => {
                    new bootstrap.Tooltip(tooltip);
                });
            }
        });
    </script>
</body>
</html>