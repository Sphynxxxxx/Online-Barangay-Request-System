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

if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_msg'] = "Invalid request ID.";
    header("Location: my-requests.php");
    exit();
}

$requestId = intval($_GET['id']);
$request = null;
$requestDetails = null;
$formErrors = [];
$formSuccess = '';

try {
    $db = new Database();
    
    $requestSql = "SELECT r.*, 
                    DATE_FORMAT(r.created_at, '%M %d, %Y') as request_date,
                    DATE_FORMAT(r.updated_at, '%M %d, %Y %h:%i %p') as last_updated
                  FROM requests r
                  WHERE r.request_id = ? AND r.user_id = ?";
    $request = $db->fetchOne($requestSql, [$requestId, $userId]);
    
    if (!$request) {
        $_SESSION['error_msg'] = "Request not found or you don't have permission to view it.";
        $db->closeConnection();
        header("Location: my-requests.php");
        exit();
    }
    
    if ($request['document_type'] === 'barangay_clearance') {
        $detailsSql = "SELECT * FROM request_details WHERE request_id = ?";
        $requestDetails = $db->fetchOne($detailsSql, [$requestId]);
    }
    
    $db->closeConnection();
} catch (Exception $e) {
    error_log("Request View Error: " . $e->getMessage());
    $_SESSION['error_msg'] = "An error occurred while retrieving your request. Please try again later.";
    header("Location: my-requests.php");
    exit();
}

// Initialize document type information
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

// Status badge colors
$statusColors = [
    'pending' => 'bg-warning',
    'processing' => 'bg-info',
    'ready' => 'bg-success',
    'completed' => 'bg-success',
    'rejected' => 'bg-danger',
    'cancelled' => 'bg-secondary'
];

// Handle cancel request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    try {
        $db = new Database();
        
        // Check if request can be cancelled (only pending requests can be cancelled)
        if ($request['status'] !== 'pending') {
            $formErrors[] = "Only pending requests can be cancelled.";
        } else {
            // Update request status
            $cancelSql = "UPDATE requests SET status = 'cancelled', updated_at = NOW() WHERE request_id = ? AND user_id = ?";
            $result = $db->execute($cancelSql, [$requestId, $userId]);
            
            if ($result) {
                // Create notification for the user
                $notifMessage = "Your request for " . $documentTypes[$request['document_type']] . " (Request #$requestId) has been cancelled.";
                $notifSql = "INSERT INTO notifications (user_id, message, is_read, is_system, created_at) 
                            VALUES (?, ?, 0, 0, NOW())";
                $db->execute($notifSql, [$userId, $notifMessage]);
                
                // Create system notification for staff/admin
                $sysNotifMessage = "Request #$requestId for " . $documentTypes[$request['document_type']] . " has been cancelled by the user.";
                $sysNotifSql = "INSERT INTO notifications (message, is_read, is_system, created_at) 
                               VALUES (?, 0, 1, NOW())";
                $db->execute($sysNotifSql, [$sysNotifMessage]);
                
                $formSuccess = "Your request has been cancelled successfully.";
                $request['status'] = 'cancelled'; // Update status in the current page
            } else {
                $formErrors[] = "Failed to cancel your request. Please try again.";
            }
        }
        
        $db->closeConnection();
    } catch (Exception $e) {
        error_log("Request Cancel Error: " . $e->getMessage());
        $formErrors[] = "An error occurred while cancelling your request. Please try again later.";
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
    
    $db->closeConnection();
} catch (Exception $e) {
    error_log("Notification Error: " . $e->getMessage());
}

// Check if success message is set
if (isset($_SESSION['success_msg'])) {
    $formSuccess = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

// Check if error message is set
if (isset($_SESSION['error_msg'])) {
    $formErrors[] = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request - Barangay Clearance and Document Request System</title>
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
        
        .timeline {
            position: relative;
            padding-left: 3rem;
            margin-bottom: 3rem;
        }
        
        .timeline:before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            height: 100%;
            width: 4px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-marker {
            position: absolute;
            left: -2rem;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background: #dee2e6;
            border: 3px solid #fff;
        }
        
        .timeline-marker.active {
            background: #0d6efd;
        }
        
        .timeline-marker.completed {
            background: #198754;
        }
        
        .timeline-marker.rejected {
            background: #dc3545;
        }
        
        .timeline-content {
            padding: 1.5rem;
            border-radius: 0.375rem;
            background: #fff;
            border: 1px solid #dee2e6;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .status-badge {
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.025rem;
        }
        
        .request-details dt {
            font-weight: 600;
            color: #495057;
        }
        
        .request-details dd {
            margin-bottom: 1rem;
        }
        
        .info-card {
            border-left: 4px solid #0d6efd;
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
            
            .timeline {
                padding-left: 2rem;
            }
            
            .timeline:before {
                left: 0.5rem;
            }
            
            .timeline-marker {
                left: -1.5rem;
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
                            <a class="nav-link" href="request-document.php">Request Document</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="my-requests.php">My Requests</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">My Profile</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell"></i>
                                <?php if (count($notifications) > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                    <?php echo count($notifications); ?>
                                </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php if (empty($notifications)): ?>
                                <li><span class="dropdown-item text-muted">No notifications</span></li>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" href="notifications.php?id=<?php echo $notification['notification_id']; ?>">
                                            <div class="d-flex w-100 justify-content-between">
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($notification['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo $notification['message']; ?></p>
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php endforeach; ?>
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
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1><i class="bi bi-file-earmark-text me-2"></i>Request Details</h1>
                        <p class="lead mb-0">View and track your document request</p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <span class="status-badge text-white <?php echo $statusColors[$request['status']] ?? 'bg-secondary'; ?>">
                            <i class="bi bi-circle-fill me-1 small"></i>
                            <?php echo ucfirst($request['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <div class="container py-4">
            <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h5><i class="bi bi-exclamation-triangle me-2"></i>Error:</h5>
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
                <i class="bi bi-check-circle me-2"></i> <?php echo $formSuccess; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Request Summary -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h4 class="mb-0"><i class="bi bi-info-circle me-2"></i>Request Summary</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="request-details">
                                        <dt>Request ID</dt>
                                        <dd>#<?php echo $requestId; ?></dd>
                                        
                                        <dt>Document Type</dt>
                                        <dd><?php echo $documentTypes[$request['document_type']] ?? $request['document_type']; ?></dd>
                                        
                                        <dt>Purpose</dt>
                                        <dd><?php echo htmlspecialchars($request['purpose']); ?></dd>
                                        
                                        <dt>Urgent Request</dt>
                                        <dd><?php echo $request['urgent_request'] ? 'Yes' : 'No'; ?></dd>
                                    </dl>
                                </div>
                                
                                <div class="col-md-6">
                                    <dl class="request-details">
                                        <dt>Date Requested</dt>
                                        <dd><?php echo $request['request_date']; ?></dd>
                                        
                                        <dt>Processing Fee</dt>
                                        <dd>₱<?php echo number_format($request['processing_fee'], 2); ?></dd>
                                        
                                        <dt>Payment Status</dt>
                                        <dd>
                                            <?php if ($request['payment_status'] == 1): ?>
                                            <span class="badge bg-success">Paid</span>
                                            <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pending Payment</span>
                                            <?php endif; ?>
                                        </dd>
                                        
                                        <dt>Last Updated</dt>
                                        <dd><?php echo $request['last_updated'] ?? 'Not available'; ?></dd>
                                    </dl>
                                </div>
                            </div>
                            
                            <?php if ($request['document_type'] === 'barangay_clearance' && $requestDetails): ?>
                            <div class="mt-2">
                                <h5 class="border-bottom pb-2">Personal Information</h5>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <dl class="request-details">
                                            <dt>Full Name</dt>
                                            <dd><?php echo htmlspecialchars($requestDetails['fullname']); ?></dd>
                                            
                                            <dt>Age</dt>
                                            <dd><?php echo $requestDetails['age']; ?></dd>
                                        </dl>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <dl class="request-details">
                                            <dt>Civil Status</dt>
                                            <dd><?php echo ucfirst($requestDetails['civil_status']); ?></dd>
                                            
                                            <dt>Residency Status</dt>
                                            <dd>
                                                <?php 
                                                switch($requestDetails['residency_status']) {
                                                    case 'permanent':
                                                        echo 'Permanent Resident';
                                                        break;
                                                    case 'temporary':
                                                        echo 'Temporary Resident';
                                                        break;
                                                    case 'new':
                                                        echo 'New Resident (< 6 months)';
                                                        break;
                                                    default:
                                                        echo ucfirst($requestDetails['residency_status']);
                                                }
                                                ?>
                                            </dd>
                                        </dl>
                                    </div>
                                    
                                    <div class="col-12">
                                        <dl class="request-details">
                                            <dt>Address</dt>
                                            <dd><?php echo htmlspecialchars($requestDetails['address']); ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($request['admin_remarks'])): ?>
                            <div class="alert alert-info info-card mt-3">
                                <h5><i class="bi bi-chat-square-text me-2"></i>Remarks from Barangay Staff</h5>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($request['admin_remarks'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <?php if ($request['status'] === 'pending'): ?>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                    <i class="bi bi-x-circle me-2"></i>Cancel Request
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($request['status'] === 'ready'): ?>
                                <div class="alert alert-success">
                                    <h5><i class="bi bi-check-circle me-2"></i>Your document is ready for pickup!</h5>
                                    <p>Please visit the barangay office during office hours and bring the following:</p>
                                    <ul>
                                        <li>Payment of ₱<?php echo number_format($request['processing_fee'], 2); ?></li>
                                        <li>Valid ID</li>
                                        <li>Request reference number: #<?php echo $requestId; ?></li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                
                                <a href="my-requests.php" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-left me-2"></i>Back to My Requests
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Request Timeline -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h4 class="mb-0"><i class="bi bi-clock-history me-2"></i>Request Status</h4>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-marker active"></div>
                                    <div class="timeline-content">
                                        <h5 class="mb-1">Request Submitted</h5>
                                        <p class="small text-muted mb-0"><?php echo $request['request_date']; ?></p>
                                    </div>
                                </div>
                                
                                <div class="timeline-item">
                                    <div class="timeline-marker <?php echo in_array($request['status'], ['processing', 'ready', 'completed']) ? 'active' : ''; ?>"></div>
                                    <div class="timeline-content">
                                        <h5 class="mb-1">Processing</h5>
                                        <p class="small text-muted mb-0">
                                            <?php 
                                            if ($request['status'] === 'processing') {
                                                echo "Your request is being processed by barangay staff.";
                                            } elseif (in_array($request['status'], ['ready', 'completed'])) {
                                                echo "Processed successfully.";
                                            } else {
                                                echo "Waiting for processing.";
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="timeline-item">
                                    <div class="timeline-marker <?php echo in_array($request['status'], ['ready', 'completed']) ? 'active' : ''; ?>"></div>
                                    <div class="timeline-content">
                                        <h5 class="mb-1">Ready for Pickup</h5>
                                        <p class="small text-muted mb-0">
                                            <?php 
                                            if ($request['status'] === 'ready') {
                                                echo "Your document is ready for pickup at the barangay office.";
                                            } elseif ($request['status'] === 'completed') {
                                                echo "Document picked up successfully.";
                                            } else {
                                                echo "Not yet ready.";
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="timeline-item">
                                    <div class="timeline-marker <?php echo $request['status'] === 'completed' ? 'completed' : ''; ?>"></div>
                                    <div class="timeline-content">
                                        <h5 class="mb-1">Completed</h5>
                                        <p class="small text-muted mb-0">
                                            <?php 
                                            if ($request['status'] === 'completed') {
                                                echo "Document has been issued and picked up.";
                                            } else {
                                                echo "Not yet completed.";
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if ($request['status'] === 'rejected'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker rejected"></div>
                                    <div class="timeline-content">
                                        <h5 class="mb-1">Request Rejected</h5>
                                        <p class="small text-muted mb-0">Your request has been rejected. Please check the remarks for details.</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($request['status'] === 'cancelled'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker rejected"></div>
                                    <div class="timeline-content">
                                        <h5 class="mb-1">Request Cancelled</h5>
                                        <p class="small text-muted mb-0">You have cancelled this request.</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Office Hours:</strong> Monday to Friday, 8:00 AM to 5:00 PM
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cancel Request Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="view-request.php?id=<?php echo $requestId; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelModalLabel">Confirm Cancellation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Are you sure you want to cancel this request? This action cannot be undone.
                        </div>
                        <p>Request ID: #<?php echo $requestId; ?></p>
                        <p>Document: <?php echo $documentTypes[$request['document_type']] ?? $request['document_type']; ?></p>
                        <input type="hidden" name="action" value="cancel">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Close
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>Cancel Request
                        </button>
                    </div>
                </form>
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
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert:not(.alert-info):not(.alert-warning)');
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