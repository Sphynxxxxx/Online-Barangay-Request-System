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
    
    $unreadNotifSql = "SELECT COUNT(*) as unread_count 
                        FROM notifications 
                        WHERE user_id = ? AND is_read = 0";
    $unreadNotifications = $db->fetchOne($unreadNotifSql, [$userId]);
    $unreadCount = $unreadNotifications['unread_count'] ?? 0;
    
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
                <p class="lead">Find answers to common questions about our Online Barangay Document Request System</p>
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
                                        <li>Select "Barangay Clearance or other Documents" from the document options</li>
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
                                        <li><strong>Bank transfer</strong> to the Barangay's official account</li>
                                    </ul>
                                    <p>Payment instructions will be provided when your request is approved. Always keep your payment receipt as proof of transaction.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFourteen">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFourteen" aria-expanded="false" aria-controls="collapseFourteen">
                                    Will I get a refund if my request is rejected?
                                </button>
                            </h2>
                            <div id="collapseFourteen" class="accordion-collapse collapse" aria-labelledby="headingFourteen" data-bs-parent="#accordionFeesPayments">
                                <div class="accordion-body">
                                    <p>Our payment policy regarding rejections:</p>
                                    <ul>
                                        <li>Since payment is typically made after approval or during pickup, you generally won't need a refund if your request is rejected.</li>
                                        <li>If you've made an advance payment for a request that is later rejected, you're eligible for a full refund.</li>
                                        <li>To claim a refund, visit the Barangay Hall with your payment receipt and request reference number.</li>
                                    </ul>
                                    <p>If you have questions about refunds, please contact the Barangay Treasury Office for assistance.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support & Help FAQ -->
            <div class="row mb-5" id="support-help">
                <div class="col-12">
                    <h3 class="mb-4"><i class="bi bi-headset me-2 text-primary"></i>Support & Help</h3>
                    <div class="accordion" id="accordionSupportHelp">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFifteen">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFifteen" aria-expanded="true" aria-controls="collapseFifteen">
                                    How do I contact the Barangay Office for help?
                                </button>
                            </h2>
                            <div id="collapseFifteen" class="accordion-collapse collapse show" aria-labelledby="headingFifteen" data-bs-parent="#accordionSupportHelp">
                                <div class="accordion-body">
                                    <p>You can contact the Barangay Office through the following channels:</p>
                                    <ul>
                                        <li><strong>Phone</strong>: (123) 456-7890</li>
                                        <li><strong>Email</strong>: support@barangayservices.gov.ph</li>
                                        <li><strong>Office Visit</strong>: Barangay Hall Lanit, Jaro, Iloilo City</li>
                                        <li><strong>Office Hours</strong>: Monday - Friday, 8:00 AM - 5:00 PM</li>
                                        <li><strong>Contact Form</strong>: Available on the "Contact" page of this website</li>
                                    </ul>
                                    <p>For urgent matters outside office hours, please contact the Barangay Emergency Hotline at (123) 456-7899.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingSixteen">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSixteen" aria-expanded="false" aria-controls="collapseSixteen">
                                    What if I encounter technical issues with the system?
                                </button>
                            </h2>
                            <div id="collapseSixteen" class="accordion-collapse collapse" aria-labelledby="headingSixteen" data-bs-parent="#accordionSupportHelp">
                                <div class="accordion-body">
                                    <p>If you encounter technical issues with the system:</p>
                                    <ol>
                                        <li>Try refreshing the page or clearing your browser cache</li>
                                        <li>Make sure you're using a supported browser (Chrome, Firefox, Safari, or Edge)</li>
                                        <li>If the issue persists, report the problem by:
                                            <ul>
                                                <li>Taking a screenshot of the error</li>
                                                <li>Noting what you were trying to do when the error occurred</li>
                                                <li>Emailing these details to tech.support@barangayservices.gov.ph</li>
                                            </ul>
                                        </li>
                                    </ol>
                                    <p>Our technical team will respond to your issue within 24-48 hours. For urgent technical issues that prevent you from submitting important requests, please call our support hotline.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingSeventeen">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeventeen" aria-expanded="false" aria-controls="collapseSeventeen">
                                    How can I provide feedback or suggestions?
                                </button>
                            </h2>
                            <div id="collapseSeventeen" class="accordion-collapse collapse" aria-labelledby="headingSeventeen" data-bs-parent="#accordionSupportHelp">
                                <div class="accordion-body">
                                    <p>We value your feedback and suggestions for improving our services. You can provide feedback through:</p>
                                    <ul>
                                        <li><strong>Feedback Form</strong>: Available on the "Contact" page</li>
                                        <li><strong>Email</strong>: feedback@barangayservices.gov.ph</li>
                                        <li><strong>Suggestion Box</strong>: Located at the Barangay Hall</li>
                                        <li><strong>User Surveys</strong>: Occasionally sent to system users</li>
                                    </ul>
                                    <p>All feedback is reviewed by our management team and considered for future system improvements. We appreciate your input in helping us serve the community better.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingEighteen">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEighteen" aria-expanded="false" aria-controls="collapseEighteen">
                                    Are there in-person assistance options available?
                                </button>
                            </h2>
                            <div id="collapseEighteen" class="accordion-collapse collapse" aria-labelledby="headingEighteen" data-bs-parent="#accordionSupportHelp">
                                <div class="accordion-body">
                                    <p>Yes, we offer in-person assistance for residents who need help using the system:</p>
                                    <ul>
                                        <li><strong>Service Desk</strong>: Located at the Barangay Hall during office hours</li>
                                        <li><strong>Digital Assistance Program</strong>: Staff members are available to help residents navigate the online system</li>
                                        <li><strong>Community Outreach</strong>: Periodic training sessions held at community centers</li>
                                    </ul>
                                    <p>Residents who have difficulty accessing or using the online system can still request documents in person at the Barangay Hall. Our staff will be happy to assist you with the process.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingNineteen">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNineteen" aria-expanded="false" aria-controls="collapseNineteen">
                                    What should I do if I don't receive notifications about my request?
                                </button>
                            </h2>
                            <div id="collapseNineteen" class="accordion-collapse collapse" aria-labelledby="headingNineteen" data-bs-parent="#accordionSupportHelp">
                                <div class="accordion-body">
                                    <p>If you're not receiving notifications about your request:</p>
                                    <ol>
                                        <li>Check your "My Requests" page for the current status</li>
                                        <li>Verify your email address and phone number in your profile settings</li>
                                        <li>Check your email spam/junk folder</li>
                                        <li>Ensure notifications are enabled in your account settings</li>
                                        <li>If you still don't receive notifications, contact the support team with your request reference number</li>
                                    </ol>
                                    <p>Even without notifications, you can always check the status of your requests by logging into your account and visiting the "My Requests" page.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional FAQ Section -->
            <div class="row mb-5" id="additional-faq">
                <div class="col-12">
                    <h3 class="mb-4"><i class="bi bi-info-circle me-2 text-primary"></i>Additional Information</h3>
                    <div class="accordion" id="accordionAdditionalInfo">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwenty">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwenty" aria-expanded="false" aria-controls="collapseTwenty">
                                    What's the difference between a Barangay Clearance and a Certificate of Residency?
                                </button>
                            </h2>
                            <div id="collapseTwenty" class="accordion-collapse collapse" aria-labelledby="headingTwenty" data-bs-parent="#accordionAdditionalInfo">
                                <div class="accordion-body">
                                    <p>Here are the key differences between these two documents:</p>
                                    
                                    <p><strong>Barangay Clearance:</strong></p>
                                    <ul>
                                        <li>Certifies that you have no derogatory record in the barangay</li>
                                        <li>Often required for employment, scholarship applications, bank transactions, etc.</li>
                                        <li>Includes verification of your good standing in the community</li>
                                        <li>Fee: ₱100.00</li>
                                    </ul>
                                    
                                    <p><strong>Certificate of Residency:</strong></p>
                                    <ul>
                                        <li>Certifies that you are a legitimate resident of the barangay</li>
                                        <li>Specifies how long you have been residing in the barangay</li>
                                        <li>Often required for school enrollment, voter registration, etc.</li>
                                        <li>Fee: ₱50.00</li>
                                    </ul>
                                    
                                    <p>Some applications may require both documents, while others may specifically ask for one or the other. If you're unsure which document you need, contact the Barangay Office for guidance.</p>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwentyOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwentyOne" aria-expanded="false" aria-controls="collapseTwentyOne">
                                    How do I request multiple documents at once?
                                </button>
                            </h2>
                            <div id="collapseTwentyOne" class="accordion-collapse collapse" aria-labelledby="headingTwentyOne" data-bs-parent="#accordionAdditionalInfo">
                                <div class="accordion-body">
                                    <p>Currently, you need to submit separate requests for each document you need. Here's the most efficient way to request multiple documents:</p>
                                    
                                    <ol>
                                        <li>Plan ahead and submit all requests at the same time</li>
                                        <li>Complete one request form fully before starting another</li>
                                        <li>Use consistent information across all your requests</li>
                                        <li>If the documents are for the same purpose, mention this in the "Purpose" field of each request</li>
                                    </ol>
                                    
                                    <p>This approach helps ensure that all your documents can be processed efficiently. You can track the status of all your requests on the "My Requests" page.</p>
                                    
                                    <p>Note: While you need to submit separate requests, you may be able to pick up all completed documents in a single visit to the Barangay Hall, provided they're all ready at the same time.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwentyTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwentyTwo" aria-expanded="false" aria-controls="collapseTwentyTwo">
                                    Can someone else pick up my document for me?
                                </button>
                            </h2>
                            <div id="collapseTwentyTwo" class="accordion-collapse collapse" aria-labelledby="headingTwentyTwo" data-bs-parent="#accordionAdditionalInfo">
                                <div class="accordion-body">
                                    <p>Yes, someone else can pick up your document on your behalf, but there are specific requirements:</p>
                                    
                                    <p><strong>Required for representative pickup:</strong></p>
                                    <ol>
                                        <li>An authorization letter signed by you</li>
                                        <li>A photocopy of your valid ID</li>
                                        <li>The original valid ID of your representative</li>
                                        <li>The request reference number</li>
                                    </ol>
                                    
                                    <p>The authorization letter should clearly state:</p>
                                    <ul>
                                        <li>Your full name and address</li>
                                        <li>Your representative's full name and relationship to you</li>
                                        <li>The specific document(s) to be picked up</li>
                                        <li>The date of authorization</li>
                                        <li>Your signature</li>
                                    </ul>
                                    
                                    <p>For security reasons, certain documents may have stricter requirements for third-party pickup. If you plan to send a representative, it's advisable to contact the Barangay Office in advance to confirm the requirements.</p>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwentyThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwentyThree" aria-expanded="false" aria-controls="collapseTwentyThree">
                                    What should I do if there's an error on my document?
                                </button>
                            </h2>
                            <div id="collapseTwentyThree" class="accordion-collapse collapse" aria-labelledby="headingTwentyThree" data-bs-parent="#accordionAdditionalInfo">
                                <div class="accordion-body">
                                    <p>If you notice an error on your issued document:</p>
                                    
                                    <ol>
                                        <li>Do not use or submit the document with errors</li>
                                        <li>Return to the Barangay Hall as soon as possible</li>
                                        <li>Bring the erroneous document with you</li>
                                        <li>Explain the error to the staff and request a correction</li>
                                        <li>Provide any supporting documents that verify the correct information</li>
                                    </ol>
                                    
                                    <p><strong>Correction policies:</strong></p>
                                    <ul>
                                        <li>If the error was made by the Barangay staff, the correction will be made free of charge</li>
                                        <li>If the error was due to incorrect information you provided, you may need to submit a new request with the correct information</li>
                                        <li>Minor typographical errors are usually corrected on the spot</li>
                                        <li>Major information changes might require verification and additional processing time</li>
                                    </ul>
                                    
                                    <p>To prevent errors, always carefully review your information before submitting requests and promptly update your profile if any of your personal details change.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Still Have Questions -->
            <div class="row mt-5 mb-5">
                <div class="col-md-8 mx-auto text-center">
                    <div class="card bg-primary text-white p-4">
                        <h4><i class="bi bi-chat-dots me-2"></i>Still Have Questions?</h4>
                        <p class="lead">We're here to help. Contact our support team or visit the Barangay Hall.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="contact.php" class="btn btn-light">
                                <i class="bi bi-envelope me-2"></i>Contact Us
                            </a>
                            <a href="tel:1234567890" class="btn btn-outline-light">
                                <i class="bi bi-telephone me-2"></i>Call Support
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
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // FAQ Search functionality
            const searchInput = document.getElementById('faqSearch');
            const searchBtn = document.getElementById('searchBtn');
            
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase();
                
                if (!searchTerm.trim()) return;
                
                // Get all accordion buttons
                const accordionButtons = document.querySelectorAll('.accordion-button');
                
                // Close all accordion items first
                const accordionCollapses = document.querySelectorAll('.accordion-collapse');
                accordionCollapses.forEach(collapse => {
                    collapse.classList.remove('show');
                });
                
                // Search through accordion items
                let foundMatch = false;
                
                accordionButtons.forEach(button => {
                    const text = button.textContent.toLowerCase();
                    const accordionId = button.getAttribute('data-bs-target');
                    const accordionContent = document.querySelector(accordionId);
                    const contentText = accordionContent ? accordionContent.textContent.toLowerCase() : '';
                    
                    // If the search term is found in the question or answer
                    if (text.includes(searchTerm) || contentText.includes(searchTerm)) {
                        // Expand this accordion item
                        button.classList.remove('collapsed');
                        if (accordionContent) {
                            accordionContent.classList.add('show');
                        }
                        
                        // Scroll to the first match
                        if (!foundMatch) {
                            button.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            foundMatch = true;
                        }
                    } else {
                        // Collapse this accordion item
                        button.classList.add('collapsed');
                    }
                });
                
                // If no matches found, show an alert
                if (!foundMatch) {
                    alert('No matches found for "' + searchTerm + '". Please try a different search term.');
                }
            }
            
            // Search on button click
            searchBtn.addEventListener('click', performSearch);
            
            // Search on Enter key press
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });
        });
    </script>
</body>
</html>