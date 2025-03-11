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

// Get user information
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];
$userName = isset($_SESSION['name']) ? $_SESSION['name'] : 'User';

// Redirect admin/staff to appropriate page
if ($userType != 'resident') {
    header("Location: dashboard.php");
    exit();
}

// Initialize variables
$requests = [];
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
    'pending' => 'bg-warning text-dark',
    'processing' => 'bg-info',
    'ready' => 'bg-success',
    'completed' => 'bg-success',
    'rejected' => 'bg-danger',
    'cancelled' => 'bg-secondary'
];

// Status filter
$statusFilter = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : '';
$statusCondition = '';
$statusParams = [];

if (!empty($statusFilter)) {
    $statusCondition = "AND r.status = ?";
    $statusParams[] = $statusFilter;
}

try {
    $db = new Database();
    
    $requestsSql = "SELECT r.*, 
                    DATE_FORMAT(r.created_at, '%M %d, %Y') as request_date,
                    DATE_FORMAT(r.updated_at, '%M %d, %Y %h:%i %p') as last_updated 
                  FROM requests r
                  WHERE r.user_id = ? $statusCondition
                  ORDER BY r.created_at DESC";
    
    $requestsParams = [$userId];
    if (!empty($statusParams)) {
        $requestsParams = array_merge($requestsParams, $statusParams);
    }
    
    $requests = $db->fetchAll($requestsSql, $requestsParams);
    $totalRequests = count($requests);
    
    $db->closeConnection();
    
} catch (Exception $e) {
    error_log("My Requests Error: " . $e->getMessage());
    $errorMsg = "An error occurred while retrieving your requests. Please try again later.";
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

// success message is set
$successMsg = '';
if (isset($_SESSION['success_msg'])) {
    $successMsg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

// error message is set
$errorMsg = '';
if (isset($_SESSION['error_msg'])) {
    $errorMsg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Add payment_status column if it doesn't exist
try {
    $db = new Database();
    
    $checkColumnSql = "SHOW COLUMNS FROM requests LIKE 'payment_status'";
    $columnExists = $db->fetchOne($checkColumnSql);
    
    if (!$columnExists) {
        // Add payment_status column
        $alterTableSql = "ALTER TABLE requests ADD COLUMN payment_status TINYINT(1) NOT NULL DEFAULT 0;";
        $db->execute($alterTableSql);
    }
    
    $db->closeConnection();
} catch (Exception $e) {
    error_log("Database Schema Check Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - Barangay Clearance and Document Request System</title>
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
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.5rem;
            border-radius: 0.25rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.025rem;
        }
        
        .request-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .request-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
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
                        <h1><i class="bi bi-list-check me-2"></i>My Requests</h1>
                        <p class="lead mb-0">View and track your document requests</p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <a href="request-document.php" class="btn btn-light">
                            <i class="bi bi-plus-circle me-2"></i>New Request
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content -->

        
        <div class="container py-4">
            <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $errorMsg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i> <?php echo $successMsg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="my-requests.php" method="get" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label for="status" class="form-label">Filter by Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Requests</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="ready" <?php echo $statusFilter === 'ready' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-2"></i>Apply Filter
                            </button>
                            <?php if (!empty($statusFilter)): ?>
                            <a href="my-requests.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-x-circle me-2"></i>Clear Filter
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            

            <!-- Request List -->
            <?php if (empty($requests)): ?>
            <div class="card">
                <div class="card-body empty-state">
                    <i class="bi bi-folder empty-state-icon d-block"></i>
                    <h4>No requests found</h4>
                    <?php if (!empty($statusFilter)): ?>
                    <p class="text-muted">No requests with the selected status. Try a different filter or clear filters.</p>
                    <?php else: ?>
                    <p class="text-muted">You haven't made any document requests yet.</p>
                    <?php endif; ?>
                    <a href="request-document.php" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-circle me-2"></i>Request a Document
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
                <?php foreach ($requests as $request): ?>
                <div class="col">
                    <div class="card h-100 request-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">Request #<?php echo $request['request_id']; ?></h5>
                                <small class="text-muted"><?php echo $request['request_date']; ?></small>
                            </div>
                            <span class="status-badge text-white <?php echo $statusColors[$request['status']] ?? 'bg-secondary'; ?>">
                                <?php echo ucfirst($request['status']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">
                                <?php echo $documentTypes[$request['document_type']] ?? $request['document_type']; ?>
                            </h5>
                            <p class="card-text">
                                <strong>Purpose:</strong> <?php echo htmlspecialchars($request['purpose']); ?><br>
                                <strong>Processing Fee:</strong> ₱<?php echo number_format($request['processing_fee'], 2); ?><br>
                                <strong>Urgent Request:</strong> <?php echo $request['urgent_request'] ? 'Yes' : 'No'; ?><br>
                                <strong>Payment Status:</strong> 
                                <?php if (isset($request['payment_status']) && $request['payment_status'] == 1): ?>
                                <span class="badge bg-success">Paid</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending Payment</span>
                                <?php endif; ?>
                            </p>
                            
                            <?php if ($request['status'] === 'ready'): ?>
                            <div class="alert alert-success py-2">
                                <i class="bi bi-check-circle me-2"></i>Ready for pickup!
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($request['admin_remarks']) && in_array($request['status'], ['rejected', 'processing'])): ?>
                            <div class="alert alert-info py-2">
                                <i class="bi bi-info-circle me-2"></i>Has remarks from staff
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                            <a href="view-request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>View Details
                            </a>

                            <div class="btn-group" role="group">
                                <?php if ($request['status'] === 'pending'): ?>
                                <a href="view-request.php?id=<?php echo $request['request_id']; ?>#cancel" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-x-circle me-1"></i>Cancel
                                </a>
                                <?php endif; ?>

                                <form action="delete-request.php" method="post" onsubmit="return confirm('Are you sure you want to delete this request? This action cannot be undone.');">
                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-trash me-1"></i>Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            
            
            <!-- Request Count Information -->
            <?php if ($totalRequests > 0): ?>
            <div class="mt-4 text-center">
                <p class="text-muted">
                    Showing all <?php echo $totalRequests; ?> request<?php echo $totalRequests != 1 ? 's' : ''; ?>
                    <?php echo !empty($statusFilter) ? ' with status "' . ucfirst($statusFilter) . '"' : ''; ?>
                </p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
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
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert-dismissible');
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