<?php
require_once dirname(__DIR__, 2) . "/backend/connections/config.php";
require_once dirname(__DIR__, 2) . "/backend/connections/database.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];
$userName = isset($_SESSION['name']) ? $_SESSION['name'] : 'User';

if ($userType != 'resident') {
    header("Location: dashboard.php");
    exit();
}

// Initialize variables
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

$documentRequirements = [
    'barangay_clearance' => [
        'Valid ID',
        'Proof of residence (utility bill, etc.)',
        'Payment of processing fee'
    ],
    'certificate_residency' => [
        'Valid ID',
        'Proof of residence (utility bill, etc.)'
    ],
    'business_permit' => [
        'DTI/SEC Registration',
        'Valid ID of owner',
        'Proof of business location',
        'Payment of permit fee'
    ],
    'good_moral' => [
        'Valid ID',
        'School ID (if for educational purposes)',
        'Request letter (if applicable)'
    ],
    'indigency_certificate' => [
        'Valid ID',
        'Proof of residence',
        'Income statement or declaration'
    ],
    'cedula' => [
        'Valid ID',
        'Payment of cedula tax'
    ],
    'solo_parent' => [
        'Valid ID',
        'Birth certificate of child/children',
        'Affidavit of solo parenthood'
    ],
    'first_time_jobseeker' => [
        'Valid ID',
        'Barangay clearance',
        'Sworn declaration as first-time jobseeker'
    ]
];

$documentFees = [
    'barangay_clearance' => 50.00,
    'certificate_residency' => 30.00,
    'business_permit' => 200.00,
    'good_moral' => 50.00,
    'indigency_certificate' => 0.00, 
    'cedula' => 100.00,
    'solo_parent' => 50.00,
    'first_time_jobseeker' => 0.00 
];

$documentPurposes = [
    'employment' => 'For Employment',
    'education' => 'For Education',
    'government' => 'For Government Transaction',
    'loan' => 'For Loan Application',
    'travel' => 'For Travel',
    'business' => 'For Business',
    'personal' => 'For Personal Use',
    'legal' => 'For Legal Purposes',
    'others' => 'Others'
];

$paymentMethods = [
    'cash' => 'Cash on Pickup',
    'gcash' => 'GCash',
    'paymaya' => 'PayMaya',
    'bank_transfer' => 'Bank Transfer'
];

// Check if specific document type is requested from URL
$selectedDocType = '';
if (isset($_GET['type']) && array_key_exists($_GET['type'], $documentTypes)) {
    $selectedDocType = $_GET['type'];
}

// Process form submission
$formErrors = [];
$formSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $docType = $_POST['document_type'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $purposeOther = $_POST['purpose_other'] ?? '';
    $urgentRequest = isset($_POST['urgent_request']) ? 1 : 0;
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    
    // Validate Barangay Clearance specific fields if selected
    if ($docType === 'barangay_clearance') {
        $fullname = $_POST['fullname'] ?? '';
        $age = $_POST['age'] ?? '';
        $address = $_POST['address'] ?? '';
        $civilStatus = $_POST['civil_status'] ?? '';
        $residencyStatus = $_POST['residency_status'] ?? '';
        
        if (empty($fullname)) {
            $formErrors[] = "Please enter your full name.";
        }
        
        if (empty($age) || !is_numeric($age) || $age < 1 || $age > 120) {
            $formErrors[] = "Please enter a valid age between 1 and 120.";
        }
        
        if (empty($address)) {
            $formErrors[] = "Please enter your complete address.";
        }
        
        if (empty($civilStatus) || !in_array($civilStatus, ['single', 'married', 'widowed', 'divorced', 'separated'])) {
            $formErrors[] = "Please select a valid civil status.";
        }
        
        if (empty($residencyStatus) || !in_array($residencyStatus, ['permanent', 'temporary', 'new'])) {
            $formErrors[] = "Please select a valid residency status.";
        }
    }
    
    // Basic validation
    if (!array_key_exists($docType, $documentTypes)) {
        $formErrors[] = "Please select a valid document type.";
    }
    
    if (!array_key_exists($purpose, $documentPurposes)) {
        $formErrors[] = "Please select a valid purpose.";
    }
    
    if ($purpose === 'others' && empty($purposeOther)) {
        $formErrors[] = "Please specify the purpose of your request.";
    }
    
    if (!array_key_exists($paymentMethod, $paymentMethods)) {
        $formErrors[] = "Please select a valid payment method.";
    }
    
    // If all validations pass, save to database
    if (empty($formErrors)) {
        try {
            $db = new Database();
            
            $purposeText = ($purpose === 'others') ? $purposeOther : $documentPurposes[$purpose];
            
            $processingFee = $documentFees[$docType];
            if ($urgentRequest) {
                $processingFee *= 1.5; // 50% additional fee for urgent requests
            }
            
            $sql = "INSERT INTO requests (user_id, document_type, purpose, urgent_request, processing_fee, payment_method, status, created_at) 
                   VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $params = [$userId, $docType, $purposeText, $urgentRequest, $processingFee, $paymentMethod];
            $result = $db->execute($sql, $params);
            
            if ($result) {
                $lastIdSql = "SELECT LAST_INSERT_ID() as id";
                $lastIdResult = $db->fetchOne($lastIdSql);
                $requestId = $lastIdResult['id'];
                
                // For Barangay Clearance, store additional details in a separate table
                if ($docType === 'barangay_clearance') {
                    $fullname = $_POST['fullname'] ?? '';
                    $age = $_POST['age'] ?? '';
                    $address = $_POST['address'] ?? '';
                    $civilStatus = $_POST['civil_status'] ?? '';
                    $residencyStatus = $_POST['residency_status'] ?? '';
                    
                    $detailsSql = "INSERT INTO request_details 
                                (request_id, fullname, age, address, civil_status, residency_status) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                    $detailsParams = [$requestId, $fullname, $age, $address, $civilStatus, $residencyStatus];
                    $db->execute($detailsSql, $detailsParams);
                }
                
                // --- Process payment proof if it exists in session ---
                if (isset($_SESSION['payment_proof']) && $_SESSION['payment_proof']['timestamp'] > (time() - 3600)) { // Only use if less than 1 hour old
                    $paymentProof = $_SESSION['payment_proof'];
                    
                    // Move temporary file to final destination
                    $tempPath = $paymentProof['file_path'];
                    $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
                    $finalFilename = 'payment_proof_' . $requestId . '_' . time() . '.' . $extension;
                    $finalPath = 'uploads/payment_proofs/' . $finalFilename;
                    
                    // Create directory if not exists
                    if (!is_dir('uploads/payment_proofs/')) {
                        mkdir('uploads/payment_proofs/', 0755, true);
                    }
                    
                    // Move file from temp to final location
                    if (file_exists($tempPath) && copy($tempPath, $finalPath)) {
                        // Insert into payment_proofs table
                        $paymentSql = "INSERT INTO payment_proofs (request_id, user_id, payment_method, payment_reference, 
                                       proof_image, payment_notes, status, created_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, 'submitted', NOW())";
                        
                        $paymentParams = [
                            $requestId, 
                            $userId, 
                            $paymentProof['payment_method'],
                            $paymentProof['reference_number'],
                            $finalPath,
                            $paymentProof['payment_notes']
                        ];
                        
                        $db->execute($paymentSql, $paymentParams);
                        
                        // Update the notification message to include payment confirmation
                        $notifMessage = "Your request for " . $documentTypes[$docType] . " has been submitted successfully. Payment proof has been received.";
                        $sysNotifMessage = "New document request: " . $documentTypes[$docType] . " (Request #$requestId) with payment proof";
                        
                        // Clean up temporary file
                        @unlink($tempPath);
                        
                        // Clear session payment data
                        unset($_SESSION['payment_proof']);
                    }
                } else {
                    // Create notification for the user (default if no payment proof)
                    $notifMessage = "Your request for " . $documentTypes[$docType] . " has been submitted successfully.";
                    
                    // Add payment method information to notification
                    if ($paymentMethod != 'cash') {
                        $notifMessage .= " Please complete your payment using " . $paymentMethods[$paymentMethod] . ".";
                    }
                    
                    // Create system notification for staff/admin
                    $sysNotifMessage = "New document request: " . $documentTypes[$docType] . " (Request #$requestId)";
                    
                    // Add payment method to admin notification
                    $sysNotifMessage .= " - Payment via " . $paymentMethods[$paymentMethod];
                }
                
                // Insert notifications
                $notifSql = "INSERT INTO notifications (user_id, message, is_read, is_system, created_at) 
                            VALUES (?, ?, 0, 0, NOW())";
                $db->execute($notifSql, [$userId, $notifMessage]);
                
                $sysNotifSql = "INSERT INTO notifications (message, is_read, is_system, created_at) 
                            VALUES (?, 0, 1, NOW())";
                $db->execute($sysNotifSql, [$sysNotifMessage]);
                
                // Set success message and redirect
                $_SESSION['success_msg'] = "Your request for " . $documentTypes[$docType] . " has been submitted successfully. Request ID: #$requestId";
                
                // Add payment instructions to session message for online payments
                if ($paymentMethod != 'cash') {
                    $_SESSION['payment_method'] = $paymentMethod;
                    $_SESSION['payment_amount'] = $processingFee;
                    $_SESSION['request_id'] = $requestId;
                }
                
                $db->closeConnection();
                
                // Redirect to view the request
                header("Location: view-request.php?id=$requestId");
                exit();
            } else {
                $formErrors[] = "Failed to submit your request. Please try again.";
            }
            
            $db->closeConnection();
            
        } catch (Exception $e) {
            error_log("Request Error: " . $e->getMessage());
            $formErrors[] = "An error occurred while processing your request. Please try again later.";
        }
    }
}

// Get notifications for the navigation bar
$notifications = [];
try {
    $db = new Database();
    
    // Get notifications
    $notifSql = "SELECT notification_id, message, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5";
    $notifications = $db->fetchAll($notifSql, [$userId]);

    // Count unread notifications
    $unreadNotifSql = "SELECT COUNT(*) as unread_count 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0";
    $unreadNotifications = $db->fetchOne($unreadNotifSql, [$userId]);
    $unreadCount = $unreadNotifications['unread_count'] ?? 0;
    
    $db->closeConnection();
    
} catch (Exception $e) {
    error_log("Notification Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Document - Barangay Clearance and Document Request System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="User/css/index.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .content-wrapper {
            flex: 1 0 auto;
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .service-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }
        
        .service-card {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .service-card.selected {
            border-color: #0d6efd;
            border-width: 2px;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .requirements-list {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
        }
        
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(25%, -25%);
        }
        
        .dropdown-menu-end {
            right: 0;
            left: auto;
        }
        
        .dropdown-item.unread {
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        .page-title {
            background-color: #0d6efd;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .requirement-item {
            margin-bottom: 0.75rem;
        }
        
        .urgent-request-badge {
            background-color: #dc3545;
            color: white;
            padding: 0.5rem;
            border-radius: 5px;
            margin-left: 1rem;
        }
        
        footer {
            height: 100px;
            display: flex;
            align-items: center;
            margin-top: auto;
        }
        
        @media (max-width: 768px) {
            .page-title {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
            <div class="container">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Barangay Services
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="request-document.php">Request Document</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-requests.php">My Requests</a>
                        </li>
                        <?php if ($userType != 'resident'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage-requests.php">Manage Requests</a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">My Profile</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell"></i>
                                <?php if ($unreadCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                    <?php echo $unreadCount; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php if (empty($notifications)): ?>
                                <li><span class="dropdown-item text-muted">No notifications</span></li>
                                <?php else: ?>
                                    <?php 
                                    $count = 0;
                                    foreach ($notifications as $notification): 
                                        if ($count < 5):
                                    ?>
                                    <li>
                                        <a class="dropdown-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" href="notifications.php?id=<?php echo $notification['notification_id']; ?>">
                                            <div class="d-flex w-100 justify-content-between">
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($notification['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo $notification['message']; ?></p>
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php 
                                        endif;
                                        $count++;
                                    endforeach; 
                                    ?>
                                <li><a class="dropdown-item text-primary" href="notifications.php">View all notifications</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($userName); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="change-password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page Title -->
        <section class="page-title">
            <div class="container">
                <h1><i class="bi bi-file-earmark-plus me-2"></i>Request a Document</h1>
                <p class="lead mb-0">Apply for various barangay documents and certificates.</p>
            </div>
        </section>

        <!-- Main Content -->
        <div class="container py-4">
            <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h5><i class="bi bi-exclamation-triangle me-2"></i>Please fix the following errors:</h5>
                <ul class="mb-0">
                    <?php foreach ($formErrors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($formSuccess)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $formSuccess; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <form action="request-document.php" method="post" id="requestForm">

                <!-- Document Type Selection -->
                <h4 class="mb-4"><i class="bi bi-file-earmark me-2"></i>Step 1: Select Document Type</h4>
                <div class="row mb-5">
                    <?php foreach ($documentTypes as $type => $name): ?>
                    <div class="col-md-4 col-lg-3 mb-3">
                        <div class="card service-card <?php echo ($selectedDocType === $type || (isset($_POST['document_type']) && $_POST['document_type'] === $type)) ? 'selected' : ''; ?>" data-document-type="<?php echo $type; ?>">
                            <div class="card-body text-center">
                                <i class="bi 
                                    <?php 
                                    switch ($type) {
                                        case 'barangay_clearance':
                                            echo 'bi-file-earmark-text';
                                            break;
                                        case 'certificate_residency':
                                            echo 'bi-house-door';
                                            break;
                                        case 'business_permit':
                                            echo 'bi-shop';
                                            break;
                                        case 'good_moral':
                                            echo 'bi-award';
                                            break;
                                        case 'indigency_certificate':
                                            echo 'bi-file-earmark-person';
                                            break;
                                        case 'cedula':
                                            echo 'bi-card-heading';
                                            break;
                                        case 'solo_parent':
                                            echo 'bi-people';
                                            break;
                                        case 'first_time_jobseeker':
                                            echo 'bi-briefcase';
                                            break;
                                        default:
                                            echo 'bi-file-earmark';
                                    }
                                    ?> service-icon"></i>
                                <h5><?php echo $name; ?></h5>
                                <div class="mt-2 text-primary">
                                    Fee: ₱<?php echo number_format($documentFees[$type], 2); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="document_type" id="document_type" value="<?php echo $selectedDocType; ?>">
                
                <!-- Document Requirements -->
                <div id="requirementsSection" class="mb-5 <?php echo empty($selectedDocType) ? 'd-none' : ''; ?>">
                    <h4 class="mb-4"><i class="bi bi-list-check me-2"></i>Step 2: Document Requirements</h4>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Requirements for <span id="documentTypeName">
                                <?php echo isset($documentTypes[$selectedDocType]) ? $documentTypes[$selectedDocType] : ''; ?>
                            </span></h5>
                            
                            <div class="requirements-list">
                                <p class="text-muted mb-3">Please prepare the following requirements before submitting your request:</p>
                                <ul id="requirementsList">
                                    <?php 
                                    if (!empty($selectedDocType) && isset($documentRequirements[$selectedDocType])) {
                                        foreach ($documentRequirements[$selectedDocType] as $req) {
                                            echo '<li class="requirement-item">' . $req . '</li>';
                                        }
                                    }
                                    ?>
                                </ul>
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Note:</strong> You'll need to bring these requirements when you collect your document.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Request Details -->
                <div id="detailsSection" class="mb-5 <?php echo empty($selectedDocType) ? 'd-none' : ''; ?>">
                    <h4 class="mb-4"><i class="bi bi-info-circle me-2"></i>Step 3: Request Details</h4>
                    <div class="card">
                        <div class="card-body">

                            <!-- Additional fields for Barangay Clearance only -->
                            <div id="barangayClearanceFields" class="<?php echo ($selectedDocType !== 'barangay_clearance') ? 'd-none' : ''; ?>">
                                <h5 class="mb-3">Personal Information</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="fullname" class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="fullname" name="fullname" 
                                            value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" 
                                            required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="age" class="form-label">Age <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="age" name="age" min="1" max="120"
                                            value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>" 
                                            required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="address" class="form-label">Complete Address <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="address" name="address" rows="2" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="civil_status" class="form-label">Civil Status <span class="text-danger">*</span></label>
                                        <select class="form-select" id="civil_status" name="civil_status" required>
                                            <option value="" selected disabled>Select civil status...</option>
                                            <option value="single" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'single') ? 'selected' : ''; ?>>Single</option>
                                            <option value="married" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'married') ? 'selected' : ''; ?>>Married</option>
                                            <option value="widowed" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'widowed') ? 'selected' : ''; ?>>Widowed</option>
                                            <option value="divorced" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'divorced') ? 'selected' : ''; ?>>Divorced</option>
                                            <option value="separated" <?php echo (isset($_POST['civil_status']) && $_POST['civil_status'] === 'separated') ? 'selected' : ''; ?>>Separated</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="residency_status" class="form-label">Residency Status <span class="text-danger">*</span></label>
                                        <select class="form-select" id="residency_status" name="residency_status" required>
                                            <option value="" selected disabled>Select residency status...</option>
                                            <option value="permanent" <?php echo (isset($_POST['residency_status']) && $_POST['residency_status'] === 'permanent') ? 'selected' : ''; ?>>Permanent Resident</option>
                                            <option value="temporary" <?php echo (isset($_POST['residency_status']) && $_POST['residency_status'] === 'temporary') ? 'selected' : ''; ?>>Temporary Resident</option>
                                            <option value="new" <?php echo (isset($_POST['residency_status']) && $_POST['residency_status'] === 'new') ? 'selected' : ''; ?>>New Resident (< 6 months)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="alert alert-info mb-4">
                                    <i class="bi bi-info-circle me-2"></i>
                                    The personal information above will be used in the Barangay Clearance document.
                                </div>
                                <hr class="my-4">
                            </div>

                            <div class="mb-3">
                                <label for="purpose" class="form-label">Purpose of Request <span class="text-danger">*</span></label>
                                <select class="form-select" id="purpose" name="purpose" required>
                                    <option value="" selected disabled>Select purpose...</option>
                                    <?php foreach ($documentPurposes as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo (isset($_POST['purpose']) && $_POST['purpose'] === $key) ? 'selected' : ''; ?>>
                                        <?php echo $value; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3 <?php echo (!isset($_POST['purpose']) || $_POST['purpose'] !== 'others') ? 'd-none' : ''; ?>" id="otherPurposeDiv">
                                <label for="purpose_other" class="form-label">Specify Purpose <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="purpose_other" name="purpose_other" 
                                    value="<?php echo isset($_POST['purpose_other']) ? htmlspecialchars($_POST['purpose_other']) : ''; ?>"
                                    <?php echo (isset($_POST['purpose']) && $_POST['purpose'] === 'others') ? 'required' : ''; ?>>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="urgent_request" name="urgent_request" 
                                    <?php echo (isset($_POST['urgent_request'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="urgent_request">
                                    Urgent Request 
                                    <span class="urgent-request-badge">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Additional Fee: 50%
                                    </span>
                                </label>
                                <div class="form-text">Select this option if you need the document urgently (within 1-2 business days).</div>
                            </div>
                            
                            <div class="alert alert-primary" role="alert">
                                <h5><i class="bi bi-currency-exchange me-2"></i>Processing Fee Summary</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Base Fee:</strong> ₱<span id="baseFee">
                                            <?php echo !empty($selectedDocType) ? number_format($documentFees[$selectedDocType], 2) : '0.00'; ?>
                                        </span></p>
                                        <p class="mb-1"><strong>Urgent Fee (50%):</strong> ₱<span id="urgentFee">0.00</span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Total Fee:</strong> ₱<span id="totalFee">
                                            <?php echo !empty($selectedDocType) ? number_format($documentFees[$selectedDocType], 2) : '0.00'; ?>
                                        </span></p>
                                        <p class="mb-1"><strong>Payment Method:</strong> <span id="displayPaymentMethod">Cash on pickup</span></p>
                                    </div>
                                </div>

                                <div class="mb-3 mt-3">
                                    <label for="payment_method" class="form-label"><strong>Payment Method</strong> <span class="text-danger">*</span></label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <?php foreach ($paymentMethods as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === $key) ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div id="onlinePaymentInfo" class="mt-3 d-none">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Online Payment Instructions:</strong>
                                    <p class="mb-1 mt-2">Please scan the QR code below to make your payment. Make sure to include your Request ID in the payment reference.</p>
                                    <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
                                        <i class="bi bi-qr-code me-2"></i>View QR Code
                                    </button>
                                </div>
                            </div>
                                                        
                            <div class="alert alert-warning" role="alert">
                                <i class="bi bi-clock me-2"></i>
                                <strong>Processing Time:</strong> Regular requests are processed within 3-5 business days.
                                Urgent requests are processed within 1-2 business days.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Section -->
                <div id="submitSection" class="mb-5 text-center <?php echo empty($selectedDocType) ? 'd-none' : ''; ?>">
                    <h4 class="mb-4"><i class="bi bi-check-circle me-2"></i>Step 4: Submit Request</h4>
                    <div class="alert alert-info mb-4" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        By submitting this request, you confirm that all the information provided is true and correct.
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-send me-2"></i>Submit Document Request
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-lg ms-2">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrCodeModalLabel">Scan QR Code to Pay</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="gcashQR" class="payment-qr">
                        <h5 class="text-primary mb-3"><i class="bi bi-phone me-2"></i>GCash Payment</h5>
                        <img src="assets/qr.png" alt="GCash QR Code" class="img-fluid mb-3" width="300" height="300" style="border: 1px solid #ddd; padding: 10px;">
                        <p>Barangay Services GCash Account</p>
                    </div>
                    
                    <div id="paymayaQR" class="payment-qr d-none">
                        <h5 class="text-primary mb-3"><i class="bi bi-phone me-2"></i>PayMaya Payment</h5>
                        <img src="/api/placeholder/300/300" alt="PayMaya QR Code" class="img-fluid mb-3" style="border: 1px solid #ddd; padding: 10px;">
                        <p>Barangay Services PayMaya Account</p>
                    </div>
                    
                    <div id="bankTransferQR" class="payment-qr d-none">
                        <h5 class="text-primary mb-3"><i class="bi bi-bank me-2"></i>Bank Transfer Details</h5>
                        <div class="text-start p-3" style="border: 1px solid #ddd;">
                            <p><strong>Bank:</strong> Sample Bank</p>
                            <p><strong>Account Name:</strong> Barangay Services</p>
                            <p><strong>Account Number:</strong> 1234-5678-9012</p>
                            <p><strong>Branch:</strong> Main Branch</p>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Please include your Request ID in the payment reference. And present the receipt to the Barangay Services staff for verification.
                    </div>
                    
                    <!-- Payment Proof Upload Section -->
                    <div class="mt-4 p-3 border rounded bg-light">
                        <h5 class="mb-3"><i class="bi bi-upload me-2"></i>Upload Payment Screenshot</h5>
                        <p class="text-muted mb-3">Please take a screenshot of your payment confirmation and upload it below.</p>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Note:</strong> Make sure your screenshot clearly shows:
                            <ul class="mb-0 mt-2 text-start">
                                <li>Reference/Transaction Number</li>
                                <li>Amount Paid</li>
                                <li>Date and Time</li>
                            </ul>
                        </div>
                        
                        <div id="paymentProofForm">
                            <input type="hidden" id="proof_request_id" name="request_id" value="">
                            <input type="hidden" id="proof_payment_method" name="payment_method" value="">
                            
                            <div class="mb-3">
                                <label for="proofImage" class="form-label">Upload Screenshot</label>
                                <input type="file" class="form-control" id="proofImage" name="proof_image" accept="image/*">
                                <!-- Preview container -->
                                <div id="imagePreview" class="text-center mt-3"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="referenceNumber" class="form-label">Reference/Transaction Number</label>
                                <input type="text" class="form-control" id="referenceNumber" name="reference_number" placeholder="Enter reference number">
                            </div>
                            
                            <div class="mb-3">
                                <label for="paymentNotes" class="form-label">Additional Notes (Optional)</label>
                                <textarea class="form-control" id="paymentNotes" name="payment_notes" rows="2" placeholder="Any additional information about your payment"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="paymentComplete">
                        <i class="bi bi-check-circle me-2"></i>Save Payment Proof
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2025 Barangay Clearance and Document Request System</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-white me-3"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-white me-3"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="text-white"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select document type cards
            const documentCards = document.querySelectorAll('.service-card');
            const documentTypeInput = document.getElementById('document_type');
            const documentTypeName = document.getElementById('documentTypeName');
            const requirementsList = document.getElementById('requirementsList');
            const requirementsSection = document.getElementById('requirementsSection');
            const detailsSection = document.getElementById('detailsSection');
            const submitSection = document.getElementById('submitSection');
            const purposeSelect = document.getElementById('purpose');
            const otherPurposeDiv = document.getElementById('otherPurposeDiv');
            const purposeOtherInput = document.getElementById('purpose_other');
            const urgentCheckbox = document.getElementById('urgent_request');
            const baseFeeElement = document.getElementById('baseFee');
            const urgentFeeElement = document.getElementById('urgentFee');
            const totalFeeElement = document.getElementById('totalFee');
            const barangayClearanceFields = document.getElementById('barangayClearanceFields');
            const paymentMethodSelect = document.getElementById('payment_method');
            const onlinePaymentInfo = document.getElementById('onlinePaymentInfo');
            const paymentQRs = document.querySelectorAll('.payment-qr');
            
            // Document requirements data
            const documentRequirements = <?php echo json_encode($documentRequirements); ?>;
            const documentFees = <?php echo json_encode($documentFees); ?>;
            const documentTypes = <?php echo json_encode($documentTypes); ?>;
            
            // Function to update requirements
            function updateRequirements(documentType) {
                if (documentType && documentRequirements[documentType]) {
                    requirementsList.innerHTML = '';
                    documentRequirements[documentType].forEach(function(req) {
                        const li = document.createElement('li');
                        li.classList.add('requirement-item');
                        li.textContent = req;
                        requirementsList.appendChild(li);
                    });
                    
                    documentTypeName.textContent = documentTypes[documentType];
                    
                    // Show sections
                    requirementsSection.classList.remove('d-none');
                    detailsSection.classList.remove('d-none');
                    submitSection.classList.remove('d-none');
                    
                    // Update fee
                    updateFees(documentType);
                    
                    // Show/hide Barangay Clearance specific fields
                    if (documentType === 'barangay_clearance') {
                        barangayClearanceFields.classList.remove('d-none');
                        
                        // Make the fields required
                        document.getElementById('fullname').setAttribute('required', true);
                        document.getElementById('age').setAttribute('required', true);
                        document.getElementById('address').setAttribute('required', true);
                        document.getElementById('civil_status').setAttribute('required', true);
                        document.getElementById('residency_status').setAttribute('required', true);
                    } else {
                        barangayClearanceFields.classList.add('d-none');
                        
                        // Remove required attribute
                        document.getElementById('fullname').removeAttribute('required');
                        document.getElementById('age').removeAttribute('required');
                        document.getElementById('address').removeAttribute('required');
                        document.getElementById('civil_status').removeAttribute('required');
                        document.getElementById('residency_status').removeAttribute('required');
                    }
                }
            }
            
            // Function to update fees
            function updateFees(documentType) {
                if (documentType && documentFees[documentType]) {
                    const baseFee = documentFees[documentType];
                    baseFeeElement.textContent = baseFee.toFixed(2);
                    
                    // Calculate urgent fee if checked
                    const urgentFee = urgentCheckbox.checked ? baseFee * 0.5 : 0;
                    urgentFeeElement.textContent = urgentFee.toFixed(2);
                    
                    // Calculate total
                    const totalFee = baseFee + urgentFee;
                    totalFeeElement.textContent = totalFee.toFixed(2);
                }
            }
            
            // Add click event to document type cards
            documentCards.forEach(function(card) {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    documentCards.forEach(function(c) {
                        c.classList.remove('selected');
                    });
                    
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    
                    // Set hidden input value
                    const documentType = this.getAttribute('data-document-type');
                    documentTypeInput.value = documentType;
                    
                    // Update requirements
                    updateRequirements(documentType);
                });
            });
            
            // Handle purpose select change
            purposeSelect.addEventListener('change', function() {
                if (this.value === 'others') {
                    otherPurposeDiv.classList.remove('d-none');
                    purposeOtherInput.setAttribute('required', true);
                } else {
                    otherPurposeDiv.classList.add('d-none');
                    purposeOtherInput.removeAttribute('required');
                }
            });
            
            // Handle urgent checkbox change
            urgentCheckbox.addEventListener('change', function() {
                const documentType = documentTypeInput.value;
                updateFees(documentType);
            });
            
            // Initialize if document type is already selected
            if (documentTypeInput.value) {
                updateRequirements(documentTypeInput.value);
            }
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert:not(.alert-info):not(.alert-warning):not(.alert-primary)');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });

            // Handle payment method change
            paymentMethodSelect.addEventListener('change', function() {
                // Show/hide online payment info
                if (this.value !== 'cash') {
                    onlinePaymentInfo.classList.remove('d-none');
                } else {
                    onlinePaymentInfo.classList.add('d-none');
                }
                
                // Update modal content based on selected payment method
                document.getElementById('qrCodeModalLabel').textContent = 
                    'Scan QR Code to Pay via ' + this.options[this.selectedIndex].text;
                
                // Update the displayed payment method in the fee summary
                document.getElementById('displayPaymentMethod').textContent = this.options[this.selectedIndex].text;
                
                // Update the payment method in the form
                if (document.getElementById('proof_payment_method')) {
                    document.getElementById('proof_payment_method').value = this.value;
                }
            });
            
            // Show the correct QR code in the modal based on selected payment method
            const qrCodeModal = document.getElementById('qrCodeModal');
            qrCodeModal.addEventListener('show.bs.modal', function () {
                // Hide all payment QRs first
                paymentQRs.forEach(qr => qr.classList.add('d-none'));
                
                // Show the selected payment method QR
                const selectedMethod = paymentMethodSelect.value;
                if (selectedMethod === 'gcash') {
                    document.getElementById('gcashQR').classList.remove('d-none');
                } else if (selectedMethod === 'paymaya') {
                    document.getElementById('paymayaQR').classList.remove('d-none');
                } else if (selectedMethod === 'bank_transfer') {
                    document.getElementById('bankTransferQR').classList.remove('d-none');
                }
            });
            
            // File validation and preview for image upload
            const proofImage = document.getElementById('proofImage');
            const imagePreview = document.getElementById('imagePreview');
            
            if (proofImage) {
                proofImage.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        // Check file size
                        const fileSize = this.files[0].size / 1024 / 1024; // in MB
                        if (fileSize > 5) {
                            alert('File size exceeds 5MB. Please select a smaller file.');
                            this.value = ''; // Clear the input
                            if (imagePreview) {
                                imagePreview.innerHTML = ''; // Clear preview
                            }
                            return;
                        }
                        
                        // Check file type
                        const fileType = this.files[0].type;
                        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        if (!validTypes.includes(fileType)) {
                            alert('Invalid file type. Only JPG, PNG, and GIF images are allowed.');
                            this.value = ''; // Clear the input
                            if (imagePreview) {
                                imagePreview.innerHTML = ''; // Clear preview
                            }
                            return;
                        }
                        
                        // Create image preview if container exists
                        if (imagePreview) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                imagePreview.innerHTML = `
                                    <img src="${e.target.result}" class="img-fluid rounded mt-2 mb-2" style="max-height: 200px;">
                                `;
                            };
                            reader.readAsDataURL(this.files[0]);
                        }
                    }
                });
            }
            
            // Payment completion button (modified to use save-payment-proof.php)
            const paymentCompleteBtn = document.getElementById('paymentComplete');
            if (paymentCompleteBtn) {
                paymentCompleteBtn.addEventListener('click', function() {
                    // Check if file is uploaded
                    const proofImage = document.getElementById('proofImage');
                    if (!proofImage || !proofImage.files || !proofImage.files[0]) {
                        alert('Please upload a screenshot of your payment proof.');
                        return;
                    }
                    
                    // Check reference number
                    const referenceNumber = document.getElementById('referenceNumber');
                    if (!referenceNumber.value.trim()) {
                        alert('Please enter the reference/transaction number.');
                        referenceNumber.focus();
                        return;
                    }
                    
                    // Set the request ID (or "pending" if not yet created)
                    const requestIdInput = document.getElementById('proof_request_id');
                    if (requestIdInput) {
                        requestIdInput.value = 'pending';
                    }
                    
                    // Set the payment method
                    const paymentMethodInput = document.getElementById('proof_payment_method');
                    if (paymentMethodInput) {
                        paymentMethodInput.value = paymentMethodSelect.value;
                    }
                    
                    // Create FormData
                    const formData = new FormData();
                    formData.append('request_id', requestIdInput.value);
                    formData.append('payment_method', paymentMethodSelect.value);
                    formData.append('reference_number', referenceNumber.value.trim());
                    formData.append('payment_notes', document.getElementById('paymentNotes').value.trim());
                    formData.append('proof_image', proofImage.files[0]);
                    
                    // Show loading state
                    paymentCompleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                    paymentCompleteBtn.disabled = true;
                    
                    // Submit via fetch API
                    fetch('save-payment-proof.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Close modal
                            const modalInstance = bootstrap.Modal.getInstance(qrCodeModal);
                            if (modalInstance) {
                                modalInstance.hide();
                            }
                            
                            // Create preview display if data.preview is true
                            if (data.preview) {
                                // Create preview element
                                const previewDisplay = document.createElement('div');
                                previewDisplay.id = 'paymentPreviewDisplay';
                                previewDisplay.className = 'mt-3 border rounded p-3 bg-light';
                                
                                // Read the current file with FileReader to get a data URL for the preview
                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    previewDisplay.innerHTML = `
                                        <h5 class="mb-3"><i class="bi bi-file-earmark-image me-2"></i>Payment Screenshot</h5>
                                        <div class="row">
                                            <div class="col-md-6 text-center mb-3">
                                                <img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px; border: 1px solid #ddd;">
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-1"><strong>Reference Number:</strong> ${referenceNumber.value.trim()}</p>
                                                <p class="mb-1"><strong>Payment Method:</strong> ${paymentMethodSelect.options[paymentMethodSelect.selectedIndex].text}</p>
                                                <p class="mb-1"><strong>Status:</strong> <span class="badge bg-info">Saved</span></p>
                                                ${document.getElementById('paymentNotes').value.trim() ? 
                                                    `<p class="mb-1"><strong>Notes:</strong> ${document.getElementById('paymentNotes').value.trim()}</p>` : ''}
                                            </div>
                                        </div>
                                    `;
                                    
                                    // Add the preview display to the page
                                    if (onlinePaymentInfo) {
                                        // Remove any existing preview
                                        const existingPreview = document.getElementById('paymentPreviewDisplay');
                                        if (existingPreview) {
                                            existingPreview.remove();
                                        }
                                        
                                        onlinePaymentInfo.after(previewDisplay);
                                    }
                                };
                                reader.readAsDataURL(proofImage.files[0]);
                            }
                            
                            // Show success message
                            const successAlert = document.createElement('div');
                            successAlert.className = 'alert alert-success alert-dismissible fade show mt-3';
                            successAlert.innerHTML = `
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Payment proof saved!</strong> ${data.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            `;
                            
                            // Add the success alert to the page
                            if (onlinePaymentInfo) {
                                onlinePaymentInfo.after(successAlert);
                            } else {
                                const alertContainer = document.querySelector('.container.py-4');
                                if (alertContainer) {
                                    alertContainer.prepend(successAlert);
                                }
                            }
                            
                            // Reset form and preview
                            document.getElementById('referenceNumber').value = '';
                            document.getElementById('paymentNotes').value = '';
                            document.getElementById('proofImage').value = '';
                            if (imagePreview) {
                                imagePreview.innerHTML = '';
                            }
                            
                            // Auto-dismiss alert after 5 seconds
                            setTimeout(function() {
                                successAlert.remove();
                            }, 5000);
                            
                            // If there's a redirect URL, navigate after a delay
                            if (data.redirect) {
                                setTimeout(function() {
                                    window.location.href = data.redirect;
                                }, 1500);
                            }
                        } else {
                            // Show error message
                            alert(data.message || 'An error occurred while saving your payment proof.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while processing your request. Please try again.');
                    })
                    .finally(() => {
                        // Reset button state
                        paymentCompleteBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Save Payment Proof';
                        paymentCompleteBtn.disabled = false;
                    });
                });
            }
            
            // Trigger change event to initialize UI
            if (paymentMethodSelect) {
                paymentMethodSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>