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

// Get unread notifications count
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
    error_log("FAQ Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Barangay Clearance and Document Request System</title>
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
        
        .faq-header {
            background-color: #0d6efd;
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            box-shadow: none;
        }
        
        .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(13, 110, 253, 0.25);
        }
        
        .accordion-item {
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        
        .accordion-body {
            padding: 1.5rem;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .category-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }
        
        footer {
            margin-top: auto;
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

        <!-- FAQ Header -->
        <div class="faq-header">
            <div class="container text-center">
                <h1><i class="bi bi-question-circle me-2"></i>Frequently Asked Questions</h1>
                <p class="lead">Find answers to common questions about our Barangay Clearance and Document Request System</p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container py-4">
            <!-- FAQ Categories -->
            <div class="row mb-5">
                <div class="col-12 text-center mb-4">
                    <h2>FAQ Categories</h2>
                    <p class="text-muted">Choose a category to find answers quickly</p>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-center p-4">
                        <div class="category-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h5>Document Requests</h5>
                        <p class="text-muted">Questions about requesting documents</p>
                        <a href="#document-requests" class="stretched-link"></a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-center p-4">
                        <div class="category-icon">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <h5>Account & Profile</h5>
                        <p class="text-muted">Questions about your account</p>
                        <a href="#account-profile" class="stretched-link"></a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-center p-4">
                        <div class="category-icon">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <h5>Fees & Payments</h5>
                        <p class="text-muted">Questions about document fees</p>
                        <a href="#fees-payments" class="stretched-link"></a>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-center p-4">
                        <div class="category-icon">
                            <i class="bi bi-headset"></i>
                        </div>
                        <h5>Support & Help</h5>
                        <p class="text-muted">Questions about getting support</p>
                        <a href="#support-help" class="stretched-link"></a>
                    </div>
                </div>
            </div>
            
            <!-- Search Box -->
            <div class="row mb-5">
                <div class="col-md-8 mx-auto">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Search FAQs</h5>
                            <div class="input-group">
                                <input type="text" id="faqSearch" class="form-control" placeholder="Type your question here...">
                                <button class="btn btn-primary" type="button" id="searchBtn">
                                    <i class="bi bi-search me-1"></i> Search
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Document Requests FAQ -->
            <div class="row mb-5" id="document-requests">
                <div class="col-12">
                    <h3 class="mb-4"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Document Requests</h3>
                    <div class="accordion" id="accordionDocumentRequests">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    How do I request a Barangay Clearance?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#accordionDocumentRequests">
                                <div class="accordion-body">
                                    <p>To request a Barangay Clearance, follow these steps:</p>
                                    <ol>
                                        <li>Log in to your account</li>
                                        <li>Go to the "Request Document" page from the navigation menu</li>
                                        <li>Select "Barangay Clearance" from the document options</li>
                                        <li>Fill out the required information in the form</li>
                                        <li>Upload any required supporting documents</li>
                                        <li>Submit your request</li>
                                    </ol>
                                    <p>Once submitted, you'll receive a confirmation notification and can track the status of your request through the "My Requests" page.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    What documents are available for request in the system?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionDocumentRequests">
                                <div class="accordion-body">
                                    <p>The following documents are currently available for request through our system:</p>
                                    <ul>
                                        <li><strong>Barangay Clearance</strong> - A general clearance document</li>
                                        <li><strong>Certificate of Residency</strong> - Certifies that you are a resident of the barangay</li>
                                        <li><strong>Business Permit</strong> - Required for operating a business within the barangay</li>
                                        <li><strong>Good Moral Certificate</strong> - Certifies your good standing in the community</li>
                                    </ul>
                                    <p>Each document has specific requirements and processing times. You can find more details on the "Document Requirements" page.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    How long does it take to process my document request?
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionDocumentRequests">
                                <div class="accordion-body">
                                    <p>Processing times vary by document type:</p>
                                    <ul>
                                        <li><strong>Barangay Clearance</strong>: 1-2 business days</li>
                                        <li><strong>Certificate of Residency</strong>: 1-2 business days</li>
                                        <li><strong>Good Moral Certificate</strong>: 2-3 business days</li>
                                        <li><strong>Business Permit</strong>: 3-5 business days</li>
                                    </ul>
                                    <p>These are standard processing times. During peak periods or if additional verification is needed, processing may take longer. You'll receive notifications about any changes to your request status.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFour">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    What should I do if my document request is rejected?
                                </button>
                            </h2>
                            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#accordionDocumentRequests">
                                <div class="accordion-body">
                                    <p>If your document request is rejected:</p>
                                    <ol>
                                        <li>Check the notification or go to "My Requests" to see the reason for rejection</li>
                                        <li>Address the issues mentioned in the rejection reason</li>
                                        <li>Submit a new request with the corrected information or additional documents</li>
                                    </ol>
                                    <p>Common reasons for rejection include incomplete information, unclear supporting documents, or verification issues. If you're unsure how to resolve the issue, contact the Barangay Office for assistance.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFive">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                    How will I receive my document once it's approved?
                                </button>
                            </h2>
                            <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#accordionDocumentRequests">
                                <div class="accordion-body">
                                    <p>Once your document request is approved and processed, you have two options:</p>
                                    <ol>
                                        <li><strong>Pick up at the Barangay Hall</strong>: You'll receive a notification when your document is ready for pickup. Bring your ID and the reference number.</li>
                                        <li><strong>Delivery (if available)</strong>: For certain documents, a delivery option may be available for an additional fee.</li>
                                    </ol>
                                    <p>The system will notify you when your document is ready. Check the "My Requests" page for the latest status and specific pickup instructions.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account & Profile FAQ -->
            <div class="row mb-5" id="account-profile">
                <div class="col-12">
                    <h3 class="mb-4"><i class="bi bi-person-badge me-2 text-primary"></i>Account & Profile</h3>
                    <div class="accordion" id="accordionAccountProfile">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingSix">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="true" aria-controls="collapseSix">
                                    How do I update my personal information?
                                </button>
                            </h2>
                            <div id="collapseSix" class="accordion-collapse collapse show" aria-labelledby="headingSix" data-bs-parent="#accordionAccountProfile">
                                <div class="accordion-body">
                                    <p>To update your personal information:</p>
                                    <ol>
                                        <li>Click on your name at the top-right corner of the page</li>
                                        <li>Select "My Profile" from the dropdown menu</li>
                                        <li>Click the "Edit Profile" button</li>
                                        <li>Update your information in the form</li>
                                        <li>Click "Save Changes" to update your profile</li>
                                    </ol>
                                    <p>It's important to keep your information up-to-date, especially your contact details, to ensure you receive notifications about your requests.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingSeven">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven" aria-expanded="false" aria-controls="collapseSeven">
                                    How do I change my password?
                                </button>
                            </h2>
                            <div id="collapseSeven" class="accordion-collapse collapse" aria-labelledby="headingSeven" data-bs-parent="#accordionAccountProfile">
                                <div class="accordion-body">
                                    <p>To change your password:</p>
                                    <ol>
                                        <li>Click on your name at the top-right corner</li>
                                        <li>Select "Change Password" from the dropdown menu</li>
                                        <li>Enter your current password</li>
                                        <li>Enter your new password and confirm it</li>
                                        <li>Click "Update Password"</li>
                                    </ol>
                                    <p>For security reasons, choose a strong password that includes a mix of letters, numbers, and special characters. We recommend changing your password periodically.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingEight">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEight" aria-expanded="false" aria-controls="collapseEight">
                                    What should I do if I forgot my password?
                                </button>
                            </h2>
                            <div id="collapseEight" class="accordion-collapse collapse" aria-labelledby="headingEight" data-bs-parent="#accordionAccountProfile">
                                <div class="accordion-body">
                                    <p>If you forgot your password:</p>
                                    <ol>
                                        <li>Go to the login page</li>
                                        <li>Click on the "Forgot Password" link</li>
                                        <li>Enter the email address associated with your account</li>
                                        <li>Check your email for a password reset link</li>
                                        <li>Follow the instructions in the email to reset your password</li>
                                    </ol>
                                    <p>If you don't receive the password reset email within a few minutes, check your spam folder. If you still don't see it, contact the Barangay Office for assistance.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingNine">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNine" aria-expanded="false" aria-controls="collapseNine">
                                    How do I manage my notification settings?
                                </button>
                            </h2>
                            <div id="collapseNine" class="accordion-collapse collapse" aria-labelledby="headingNine" data-bs-parent="#accordionAccountProfile">
                                <div class="accordion-body">
                                    <p>To manage your notification settings:</p>
                                    <ol>
                                        <li>Click on your name at the top-right corner</li>
                                        <li>Select "My Profile" from the dropdown menu</li>
                                        <li>Navigate to the "Notification Settings" tab</li>
                                        <li>Select which notifications you want to receive</li>
                                        <li>Choose your preferred notification methods (email, SMS, etc.)</li>
                                        <li>Click "Save Changes"</li>
                                    </ol>
                                    <p>We recommend keeping notifications enabled for document request updates to stay informed about their status.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Fees & Payments FAQ -->
            <div class="row mb-5" id="fees-payments">
                <div class="col-12">
                    <h3 class="mb-4"><i class="bi bi-cash-coin me-2 text-primary"></i>Fees & Payments</h3>
                    <div class="accordion" id="accordionFeesPayments">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTen">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTen" aria-expanded="true" aria-controls="collapseTen">
                                    What are the fees for document requests?
                                </button>
                            </h2>
                            <div id="collapseTen" class="accordion-collapse collapse show" aria-labelledby="headingTen" data-bs-parent="#accordionFeesPayments">
                                <div class="accordion-body">
                                    <p>The current document processing fees are:</p>
                                    <ul>
                                        <li><strong>Barangay Clearance</strong>: ₱100.00</li>
                                        <li><strong>Certificate of Residency</strong>: ₱50.00</li>
                                        <li><strong>Good Moral Certificate</strong>: ₱100.00</li>
                                        <li><strong>Business Permit</strong>: Starting at ₱500.00 (varies based on business type and size)</li>
                                    </ul>
                                    <p>Note: Fees are subject to change. Senior citizens, PWDs, and indigent residents may qualify for discounted rates as per local ordinances.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingEleven">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEleven" aria-expanded="false" aria-controls="collapseEleven">
                                    How and when do I pay for document requests?
                                </button>
                            </h2>
                            <div id="collapseEleven" class="accordion-collapse collapse" aria-labelledby="headingEleven" data-bs-parent="#accordionFeesPayments">
                                <div class="accordion-body">
                                    <p>Payment can be made in two ways:</p>
                                    <ol>
                                        <li><strong>Pay upon pickup</strong>: You can pay the fee at the Barangay Hall when you collect your document</li>
                                        <li><strong>Online payment (if available)</strong>: Some barangays offer online payment options through GCash, PayMaya, or bank transfer</li>
                                    </ol>
                                    <p>The system will notify you about payment details once your request is approved. Make sure to keep your receipt as proof of payment.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwelve">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwelve" aria-expanded="false" aria-controls="collapseTwelve">
                                    Are there any discounts available for certain groups?
                                </button>
                            </h2>
                            <div id="collapseTwelve" class="accordion-collapse collapse" aria-labelledby="headingTwelve" data-bs-parent="#accordionFeesPayments">
                                <div class="accordion-body">
                                    <p>Yes, the following groups may qualify for discounts:</p>
                                    <ul>
                                        <li><strong>Senior Citizens</strong>: 20% discount with valid Senior Citizen ID</li>
                                        <li><strong>Persons with Disabilities (PWDs)</strong>: 20% discount with valid PWD ID</li>
                                        <li><strong>Indigent Residents</strong>: May qualify for reduced fees or fee waivers (subject to verification)</li>
                                    </ul>
                                    <p>To avail of these discounts, present your valid ID during document pickup or upload a copy of your ID when submitting your request. For indigent certification, please contact the Barangay Social Welfare office.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThirteen">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThirteen" aria-expanded="false" aria-controls="collapseThirteen">
                                    What payment methods are accepted?
                                </button>
                            </h2>
                            <div id="collapseThirteen" class="accordion-collapse collapse" aria-labelledby="headingThirteen" data-bs-parent="#accordionFeesPayments">
                                <div class="accordion-body">
                                    <p>The following payment methods are accepted:</p>
                                    <ul>
                                        <li><strong>Cash payment</strong> at the Barangay Hall</li>
                                        <li><strong>GCash</strong> (mobile payment method)</li>
                                        <li><strong>PayMaya</strong> (mobile payment method)</li>