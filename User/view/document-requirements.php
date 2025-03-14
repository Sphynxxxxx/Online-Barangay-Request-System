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

$unreadCount = 0;

try {
    $db = new Database();
    
    // Count unread notifications
    $unreadNotifSql = "SELECT COUNT(*) as unread_count 
                        FROM notifications 
                        WHERE user_id = ? AND is_read = 0";
    $unreadNotifications = $db->fetchOne($unreadNotifSql, [$userId]);
    $unreadCount = $unreadNotifications['unread_count'] ?? 0;
    
    // Get notifications for dropdown
    $notifSql = "SELECT notification_id, message, is_read, created_at 
                FROM notifications 
                WHERE user_id = ? " . ($userType != 'resident' ? " OR is_system = 1" : "") . "
                ORDER BY created_at DESC 
                LIMIT 5";
    $notifications = $db->fetchAll($notifSql, [$userId]);
    
    $db->closeConnection();
    
} catch (Exception $e) {
    error_log("Document Requirements Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Requirements - Barangay Clearance and Document Request System</title>
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
        
        .header-banner {
            background-color: #0d6efd;
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        
        .card-title {
            color: #0d6efd;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 10px;
            font-size: 1.5rem;
        }
        
        .list-group-item {
            border-left: none;
            border-right: none;
            padding: 1rem 1.25rem;
        }
        
        .list-group-item:first-child {
            border-top: none;
        }
        
        .list-group-item:last-child {
            border-bottom: none;
        }
        
        .badge-outline {
            background-color: transparent;
            border: 1px solid;
        }
        
        .badge-outline-primary {
            border-color: #0d6efd;
            color: #0d6efd;
        }
        
        .badge-outline-success {
            border-color: #198754;
            color: #198754;
        }
        
        .badge-outline-danger {
            border-color: #dc3545;
            color: #dc3545;
        }
        
        .badge-outline-warning {
            border-color: #ffc107;
            color: #ffc107;
        }
        
        .requirement-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        
        .requirement-item i {
            margin-right: 10px;
            color: #0d6efd;
            min-width: 20px;
            margin-top: 4px;
        }
        
        .requirement-item p {
            margin-bottom: 0;
        }
        
        .fee-badge {
            font-size: 1.1rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
        }
        
        .processing-time {
            display: flex;
            align-items: center;
            margin-top: 1rem;
            padding: 0.75rem;
            background-color: rgba(13, 110, 253, 0.05);
            border-radius: 8px;
        }
        
        .processing-time i {
            font-size: 1.25rem;
            margin-right: 0.75rem;
            color: #0d6efd;
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
        
        footer {
            margin-top: auto;
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
                            <a class="nav-link" href="request-document.php">Request Document</a>
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

        <!-- Header Banner -->
        <div class="header-banner">
            <div class="container text-center">
                <h1><i class="bi bi-list-check me-2"></i>Document Requirements</h1>
                <p class="lead">Learn about the requirements for various barangay documents</p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container py-4">
            <!-- Quick Navigation -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3">Jump to a document type:</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="#barangay-clearance" class="btn btn-outline-primary">Barangay Clearance</a>
                                <a href="#certificate-residency" class="btn btn-outline-primary">Certificate of Residency</a>
                                <a href="#business-permit" class="btn btn-outline-primary">Business Permit</a>
                                <a href="#good-moral" class="btn btn-outline-primary">Good Moral Certificate</a>
                                <a href="#certificate-indigency" class="btn btn-outline-primary">Certificate of Indigency</a>
                                <a href="#community-tax-certificate" class="btn btn-outline-primary">Community Tax Certificate</a>
                                <a href="#solo-parent-certificate" class="btn btn-outline-primary">Solo Parent Certificate</a>
                                <a href="#first-time-jobseeker" class="btn btn-outline-primary">First Time Jobseeker</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Introduction -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info" role="alert">
                        <h4 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Before You Begin</h4>
                        <p>Please make sure you have all the required documents ready before submitting your request. This will help ensure a faster processing time.</p>
                        <hr>
                        <p class="mb-0">For special cases or concerns, please contact the Barangay Office directly at <strong>(123) 456-7890</strong> or visit us during office hours.</p>
                    </div>
                </div>
            </div>

            <!-- Barangay Clearance -->
            <div class="row mb-5" id="barangay-clearance">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Barangay Clearance</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="card-title"><i class="bi bi-file-earmark-text"></i>Barangay Clearance</h5>
                                    <p>A Barangay Clearance is a document that certifies you have no derogatory record in the barangay and are in good standing with the community. It's commonly required for employment, scholarships, bank transactions, and other official purposes.</p>
                                    
                                    <h6 class="mt-4 mb-3">Required Documents:</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Valid government-issued ID (e.g., Driver's License, Passport, Voter's ID)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Proof of residency (any utility bill under your name)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>1 pc 2x2 recent ID picture (taken within the last 6 months)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Barangay ID (if available)</p>
                                    </div>
                                    
                                    <h6 class="mt-4 mb-3">Additional Requirements (if applicable):</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For business purposes: letter indicating the nature of business</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For employment purposes: letter from employer (if required)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For scholarship application: acceptance letter or proof of application</p>
                                    </div>
                                    
                                    <div class="processing-time">
                                        <i class="bi bi-clock"></i>
                                        <div>
                                            <strong>Processing Time:</strong> 1-2 business days
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card mt-md-0 mt-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Fee Information</h6>
                                        </div>
                                        <div class="card-body text-center">
                                            <span class="badge bg-primary fee-badge mb-3">₱100.00</span>
                                            <p class="mb-0">Standard processing fee</p>
                                            
                                            <hr>
                                            
                                            <h6 class="mb-3">Discounts Available For:</h6>
                                            <div class="d-flex flex-column gap-2">
                                                <span class="badge badge-outline badge-outline-primary">Senior Citizens (20%)</span>
                                                <span class="badge badge-outline badge-outline-primary">PWD (20%)</span>
                                                <span class="badge badge-outline badge-outline-primary">Indigent Residents</span>
                                            </div>
                                            
                                            <hr>
                                            
                                            <a href="request-document.php?type=barangay_clearance" class="btn btn-primary w-100">
                                                <i class="bi bi-file-earmark-plus me-2"></i>Request Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Certificate of Residency -->
            <div class="row mb-5" id="certificate-residency">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Certificate of Residency</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="card-title"><i class="bi bi-house-door"></i>Certificate of Residency</h5>
                                    <p>A Certificate of Residency confirms that you are a legitimate resident of the barangay. This document specifies how long you have been residing in the barangay and is often required for school enrollment, voter registration, and other government transactions.</p>
                                    
                                    <h6 class="mt-4 mb-3">Required Documents:</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Valid government-issued ID (e.g., Driver's License, Passport, Voter's ID)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Proof of residency (any utility bill under your name dated within the last 3 months)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Barangay ID (if available)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>1 pc 1x1 recent ID picture (taken within the last 6 months)</p>
                                    </div>
                                    
                                    <h6 class="mt-4 mb-3">Additional Requirements (if applicable):</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>If you've lived in the barangay for less than 6 months: previous address proof</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For tenants: copy of rental agreement or letter from property owner</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For specific purposes: letter stating the purpose (e.g., school enrollment)</p>
                                    </div>
                                    
                                    <div class="processing-time">
                                        <i class="bi bi-clock"></i>
                                        <div>
                                            <strong>Processing Time:</strong> 1-2 business days
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card mt-md-0 mt-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Fee Information</h6>
                                        </div>
                                        <div class="card-body text-center">
                                            <span class="badge bg-primary fee-badge mb-3">₱50.00</span>
                                            <p class="mb-0">Standard processing fee</p>
                                            
                                            <hr>
                                            
                                            <h6 class="mb-3">Discounts Available For:</h6>
                                            <div class="d-flex flex-column gap-2">
                                                <span class="badge badge-outline badge-outline-primary">Senior Citizens (20%)</span>
                                                <span class="badge badge-outline badge-outline-primary">PWD (20%)</span>
                                                <span class="badge badge-outline badge-outline-primary">Indigent Residents</span>
                                            </div>
                                            
                                            <hr>
                                            
                                            <a href="request-document.php?type=certificate_residency" class="btn btn-primary w-100">
                                                <i class="bi bi-file-earmark-plus me-2"></i>Request Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Business Permit -->
            <div class="row mb-5" id="business-permit">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Business Permit</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="card-title"><i class="bi bi-shop"></i>Barangay Business Permit</h5>
                                    <p>A Barangay Business Permit is a document required for operating a business within the barangay's jurisdiction. This permit is typically a prerequisite for obtaining a city/municipal business permit and must be renewed annually.</p>
                                    
                                    <h6 class="mt-4 mb-3">Required Documents:</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Valid government-issued ID of the business owner/manager</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>DTI Business Name Registration (for single proprietorship)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>SEC Registration (for corporations or partnerships)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Lease contract (if renting the business space)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Proof of ownership or authorization letter (if the business location is owned)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Business sketch location/vicinity map</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>2 pcs 2x2 ID picture of the owner/manager</p>
                                    </div>
                                    
                                    <h6 class="mt-4 mb-3">Additional Requirements (depending on business type):</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Community Tax Certificate (Cedula)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For food businesses: Sanitary Permit</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Environmental Clearance (for businesses that may impact the environment)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For renewal: Previous Barangay Business Permit</p>
                                    </div>
                                    
                                    <div class="processing-time">
                                        <i class="bi bi-clock"></i>
                                        <div>
                                            <strong>Processing Time:</strong> 3-5 business days
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card mt-md-0 mt-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Fee Information</h6>
                                        </div>
                                        <div class="card-body text-center">
                                            <span class="badge bg-primary fee-badge mb-3">₱500.00</span>
                                            <p class="mb-0">Starting fee (varies based on business type and size)</p>
                                            
                                            <hr>
                                            
                                            <h6 class="mb-3">Fee Categories:</h6>
                                            <div class="d-flex flex-column gap-2">
                                                <span class="badge badge-outline badge-outline-primary">Micro Business: ₱500.00</span>
                                                <span class="badge badge-outline badge-outline-primary">Small Business: ₱1,000.00</span>
                                                <span class="badge badge-outline badge-outline-primary">Medium Business: ₱1,500.00</span>
                                                <span class="badge badge-outline badge-outline-primary">Large Business: ₱2,000.00</span>
                                            </div>
                                            
                                            <hr>
                                            
                                            <a href="request-document.php?type=business_permit" class="btn btn-primary w-100">
                                                <i class="bi bi-file-earmark-plus me-2"></i>Request Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Good Moral Certificate -->
            <div class="row mb-5" id="good-moral">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Good Moral Certificate</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="card-title"><i class="bi bi-award"></i>Good Moral Certificate</h5>
                                    <p>A Good Moral Certificate certifies that you are of good moral character and standing in the community. This document is commonly required for school admissions, job applications, scholarship applications, and various other purposes that require character references.</p>
                                    
                                    <h6 class="mt-4 mb-3">Required Documents:</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Valid government-issued ID (e.g., Driver's License, Passport, Voter's ID)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Proof of residency (utility bill under your name)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>1 pc 2x2 recent ID picture (taken within the last 6 months)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Barangay ID (if available)</p>
                                    </div>
                                    
                                    <h6 class="mt-4 mb-3">Additional Requirements (if applicable):</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For educational purposes: school admission letter or application form</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For employment: letter from the employer (if required)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For scholarship applications: proof of application or acceptance letter</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For new residents (less than 1 year): character reference from previous barangay (if available)</p>
                                    </div>
                                    
                                    <div class="processing-time">
                                        <i class="bi bi-clock"></i>
                                        <div>
                                            <strong>Processing Time:</strong> 2-3 business days
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card mt-md-0 mt-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Fee Information</h6>
                                        </div>
                                        <div class="card-body text-center">
                                            <span class="badge bg-primary fee-badge mb-3">₱100.00</span>
                                            <p class="mb-0">Standard processing fee</p>
                                            
                                            <hr>
                                            
                                            <h6 class="mb-3">Discounts Available For:</h6>
                                            <div class="d-flex flex-column gap-2">
                                                <span class="badge badge-outline badge-outline-primary">Senior Citizens (20%)</span>
                                                <span class="badge badge-outline badge-outline-primary">PWD (20%)</span>
                                                <span class="badge badge-outline badge-outline-primary">Indigent Residents</span>
                                            </div>
                                            
                                            <hr>
                                            
                                            <a href="request-document.php?type=good_moral" class="btn btn-primary w-100">
                                                <i class="bi bi-file-earmark-plus me-2"></i>Request Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Certificate of Indigency -->
            <div class="row mb-5" id="certificate-indigency">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Certificate of Indigency</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="card-title"><i class="bi bi-file-earmark-text"></i>Certificate of Indigency</h5>
                                    <p>A Certificate of Indigency is a document that certifies that an individual or family belongs to the indigent sector or low-income bracket. This certificate is often required when seeking financial assistance, medical assistance, educational support, fee waivers, or other social services provided by government and non-government organizations.</p>
                                    
                                    <h6 class="mt-4 mb-3">Required Documents:</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Valid government-issued ID (e.g., Driver's License, Passport, Voter's ID)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Proof of residency (utility bill under your name)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Barangay ID (if available)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>1 pc 1x1 recent ID picture (taken within the last 6 months)</p>
                                    </div>
                                    
                                    <h6 class="mt-4 mb-3">Additional Requirements (if applicable):</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Income tax return or certificate of non-filing of income tax</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Certification of unemployment (if unemployed)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Letter stating the purpose of the certificate (e.g., for medical assistance, educational assistance)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Social worker assessment report (may be required in some cases)</p>
                                    </div>
                                    
                                    <div class="processing-time">
                                        <i class="bi bi-clock"></i>
                                        <div>
                                            <strong>Processing Time:</strong> 1-2 business days
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card mt-md-0 mt-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Fee Information</h6>
                                        </div>
                                        <div class="card-body text-center">
                                            <span class="badge bg-success fee-badge mb-3">FREE</span>
                                            <p class="mb-0">No processing fee required</p>
                                            
                                            <hr>
                                            
                                            <h6 class="mb-3">Important Note:</h6>
                                            <p class="small text-muted">This certificate is provided free of charge as per government regulations for indigent citizens. Misrepresentation of information may result in legal consequences.</p>
                                            
                                            <hr>
                                            <a href="request-document.php?type=indigency_certificate" class="btn btn-primary w-100">
                                                <i class="bi bi-file-earmark-plus me-2"></i>Request Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Community Tax Certificate (Cedula) -->
            <div class="row mb-5" id="community-tax-certificate">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Community Tax Certificate (Cedula)</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="card-title"><i class="bi bi-card-text"></i>Community Tax Certificate (Cedula)</h5>
                                    <p>A Community Tax Certificate (Cedula) is a basic document that serves as proof that you are a resident and taxpayer of the community. It is often required for official transactions with government offices, notarization of documents, applying for business permits, and other legal or official purposes.</p>
                                    
                                    <h6 class="mt-4 mb-3">Required Documents:</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Valid government-issued ID (e.g., Driver's License, Passport, Voter's ID)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Proof of residency (utility bill under your name)</p>
                                    </div>
                                    
                                    <h6 class="mt-4 mb-3">Additional Requirements (for professionals/business owners):</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For professionals: Professional Tax Receipt (PTR) if applicable</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For business owners: Previous year's income tax return</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>For corporations: SEC registration and financial statements</p>
                                    </div>
                                    
                                    <div class="processing-time">
                                        <i class="bi bi-clock"></i>
                                        <div>
                                            <strong>Processing Time:</strong> Same day (usually issued immediately)
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card mt-md-0 mt-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Fee Information</h6>
                                        </div>
                                        <div class="card-body text-center">
                                            <span class="badge bg-primary fee-badge mb-3">₱25.00 - ₱500.00</span>
                                            <p class="mb-0">Basic fee + percentage of income</p>
                                            
                                            <hr>
                                            
                                            <h6 class="mb-3">Fee Categories:</h6>
                                            <div class="d-flex flex-column gap-2">
                                                <span class="badge badge-outline badge-outline-primary">Basic Cedula (Individuals): ₱25.00</span>
                                                <span class="badge badge-outline badge-outline-primary">For Businesses: Starts at ₱100.00</span>
                                                <span class="badge badge-outline badge-outline-primary">Additional fee based on income/property</span>
                                            </div>
                                            
                                            <hr>
                                            
                                            <a href="request-document.php?type=cedula" class="btn btn-primary w-100">
                                                <i class="bi bi-file-earmark-plus me-2"></i>Request Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Solo Parent Certificate -->
            <div class="row mb-5" id="solo-parent-certificate">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Solo Parent Certificate</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="card-title"><i class="bi bi-people"></i>Solo Parent Certificate</h5>
                                    <p>A Solo Parent Certificate acknowledges your status as a solo parent under Republic Act 8972 (Solo Parents' Welfare Act). This certificate allows you to avail of benefits and privileges granted to solo parents, including parental leave, flexible work arrangements, educational and housing benefits, and other social service programs.</p>
                                    
                                    <h6 class="mt-4 mb-3">Required Documents:</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Valid government-issued ID (e.g., Driver's License, Passport, Voter's ID)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Proof of residency (utility bill under your name)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Birth certificate(s) of child/children</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>1 pc 2x2 recent ID picture (taken within the last 6 months)</p>
                                    </div>
                                    
                                    <h6 class="mt-4 mb-3">Additional Requirements (based on circumstances):</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Death certificate of spouse (if widowed)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Declaration of nullity of marriage/legal separation documents (if applicable)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Medical certificate (if spouse is physically/mentally incapacitated)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Police report or case filing (if abandoned by spouse)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Certification from DSWD or other proof of solo parenting status</p>
                                    </div>
                                    
                                    <div class="processing-time">
                                        <i class="bi bi-clock"></i>
                                        <div>
                                            <strong>Processing Time:</strong> 3-5 business days
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card mt-md-0 mt-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Fee Information</h6>
                                        </div>
                                        <div class="card-body text-center">
                                            <span class="badge bg-primary fee-badge mb-3">₱100.00</span>
                                            <p class="mb-0">Standard processing fee</p>
                                            
                                            <hr>
                                            
                                            <h6 class="mb-3">Validity Period:</h6>
                                            <p class="small text-muted">The Solo Parent ID/Certificate is valid for one (1) year from the date of issue and must be renewed annually with updated supporting documents.</p>
                                            
                                            <hr>
                                            
                                            <a href="request-document.php?type=solo_parent" class="btn btn-primary w-100">
                                                <i class="bi bi-file-earmark-plus me-2"></i>Request Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- First Time Jobseeker Certificate -->
            <div class="row mb-5" id="first-time-jobseeker">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">First Time Jobseeker Certificate</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="card-title"><i class="bi bi-briefcase"></i>First Time Jobseeker Certificate</h5>
                                    <p>A First Time Jobseeker Certificate is issued under Republic Act No. 11261 (First Time Jobseekers Assistance Act) to waive government fees and charges for documents required for employment. This certificate is valid for one-time use only for each government document or transaction.</p>
                                    
                                    <h6 class="mt-4 mb-3">Required Documents:</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Valid government-issued ID (e.g., Driver's License, Passport, Voter's ID)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Proof of residency (utility bill under your name)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>Barangay ID (if available)</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <p>1 pc 1x1 recent ID picture (taken within the last 6 months)</p>
                                    </div>
                                    
                                    <h6 class="mt-4 mb-3">Additional Requirements:</h6>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Diploma, TOR, or Certificate of Graduation</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>Sworn declaration/affidavit stating you are a first-time jobseeker</p>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-dash-circle-fill"></i>
                                        <p>List of government documents/transactions for which you plan to use the certificate</p>
                                    </div>
                                    
                                    <div class="processing-time">
                                        <i class="bi bi-clock"></i>
                                        <div>
                                            <strong>Processing Time:</strong> 1-2 business days
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card mt-md-0 mt-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Fee Information</h6>
                                        </div>
                                        <div class="card-body text-center">
                                            <span class="badge bg-success fee-badge mb-3">FREE</span>
                                            <p class="mb-0">No processing fee required</p>
                                            
                                            <hr>
                                            
                                            <h6 class="mb-3">Important Note:</h6>
                                            <p class="small text-muted">This certificate is valid for one (1) year from the date of issue and can only be issued ONCE to a first-time jobseeker. The waiver does not include fees related to civil registry documents from PSA or fees related to application for passport.</p>
                                            
                                            <hr>
                                            
                                            <a href="request-document.php?type=first_time_jobseeker" class="btn btn-primary w-100">
                                                <i class="bi bi-file-earmark-plus me-2"></i>Request Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="row mb-5">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h4 class="mb-0"><i class="bi bi-info-circle me-2"></i>Important Information</h4>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">General Guidelines for Document Requests</h5>
                            
                            <div class="requirement-item">
                                <i class="bi bi-info-circle-fill"></i>
                                <p><strong>Valid IDs:</strong> Acceptable government-issued IDs include Driver's License, Passport, SSS ID, PhilHealth ID, Voter's ID, Postal ID, PRC ID, or any government-issued ID with your photo, signature, and address.</p>
                            </div>
                            
                            <div class="requirement-item">
                                <i class="bi bi-info-circle-fill"></i>
                                <p><strong>Proof of Residency:</strong> Utility bills (electricity, water, internet) must be recent (within the last 3 months) and have your name and address clearly shown.</p>
                            </div>
                            
                            <div class="requirement-item">
                                <i class="bi bi-info-circle-fill"></i>
                                <p><strong>ID Photos:</strong> Must be with white background, proper attire, and taken within the last 6 months.</p>
                            </div>
                            
                            <div class="requirement-item">
                                <i class="bi bi-info-circle-fill"></i>
                                <p><strong>Discounts:</strong> To avail of discounts, present your Senior Citizen ID, PWD ID, or Indigent Certification during document pickup. For online requests, upload a copy of these IDs.</p>
                            </div>
                            
                            <div class="requirement-item">
                                <i class="bi bi-info-circle-fill"></i>
                                <p><strong>Document Pickup:</strong> Bring your original ID and request reference number when picking up documents. If someone else will pick up your document, they need an authorization letter, your ID photocopy, and their own valid ID.</p>
                            </div>
                            
                            <div class="requirement-item">
                                <i class="bi bi-info-circle-fill"></i>
                                <p><strong>Validity:</strong> Most barangay documents are valid for 3-6 months from the date of issuance, unless otherwise specified.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- FAQ and Help Section -->
            <div class="row mb-5">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="bi bi-question-circle me-2"></i>Frequently Asked Questions</h4>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="faqAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faqOne">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseOne" aria-expanded="false">
                                            Can I request multiple documents at once?
                                        </button>
                                    </h2>
                                    <div id="faqCollapseOne" class="accordion-collapse collapse" aria-labelledby="faqOne" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            Yes, you can request multiple documents, but you'll need to submit separate requests for each document type. You can track all your requests in the "My Requests" section.
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faqTwo">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseTwo" aria-expanded="false">
                                            What if I don't have the required documents?
                                        </button>
                                    </h2>
                                    <div id="faqCollapseTwo" class="accordion-collapse collapse" aria-labelledby="faqTwo" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            Contact the Barangay Office directly to discuss alternative documents that may be accepted in special circumstances. Each case is evaluated individually.
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faqThree">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseThree" aria-expanded="false">
                                            How do I follow up on my request?
                                        </button>
                                    </h2>
                                    <div id="faqCollapseThree" class="accordion-collapse collapse" aria-labelledby="faqThree" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            You can check the status of your request in the "My Requests" section. If your request is taking longer than the estimated processing time, you can contact the Barangay Office with your reference number.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <a href="faq.php" class="btn btn-outline-primary">View All FAQs</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0"><i class="bi bi-headset me-2"></i>Need Help?</h4>
                        </div>
                        <div class="card-body">
                            <p>If you need assistance with document requirements or have specific questions, our Barangay staff is ready to help.</p>
                            
                            <h6 class="mt-4 mb-3">Contact Information:</h6>
                            <div class="requirement-item">
                                <i class="bi bi-telephone-fill"></i>
                                <p><strong>Phone:</strong> (123) 456-7890</p>
                            </div>
                            <div class="requirement-item">
                                <i class="bi bi-envelope-fill"></i>
                                <p><strong>Email:</strong> support@barangayservices.gov.ph</p>
                            </div>
                            <div class="requirement-item">
                                <i class="bi bi-geo-alt-fill"></i>
                                <p><strong>Office:</strong> Barangay Hall Lanit, Jaro, Iloilo City</p>
                            </div>
                            <div class="requirement-item">
                                <i class="bi bi-clock-fill"></i>
                                <p><strong>Office Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM</p>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="contact.php" class="btn btn-success">
                                    <i class="bi bi-chat-dots me-2"></i>Contact Us
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Call to Action -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center py-4">
                            <h4><i class="bi bi-file-earmark-plus me-2"></i>Ready to Request a Document?</h4>
                            <p class="lead mb-4">Now that you know the requirements, you can proceed with your document request.</p>
                            <a href="request-document.php" class="btn btn-light btn-lg">
                                <i class="bi bi-arrow-right me-2"></i>Start Your Request
                            </a>
                        </div>
                    </div>
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
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    window.scrollTo({
                        top: target.offsetTop - 80, // Adjust for fixed navbar
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>