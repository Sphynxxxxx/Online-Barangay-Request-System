<?php
require_once dirname(__DIR__, 2) . "/backend/connections/config.php";
require_once dirname(__DIR__, 2) . "/backend/connections/database.php";

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    // Not logged in, redirect to login page
    header("Location: login.php");
    exit();
}

// Get user information
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];
$userName = isset($_SESSION['name']) ? $_SESSION['name'] : 'User';

// Initialize variables
$pendingRequests = 0;
$completedRequests = 0;
$rejectedRequests = 0;
$notifications = [];
$recentRequests = [];

try {
    // Create database connection
    $db = new Database();
    
    // Get request statistics
    if ($userType == 'resident') {
        // For residents, get only their requests
        $statsSql = "SELECT 
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                      FROM requests 
                      WHERE user_id = ?";
        $stats = $db->fetchOne($statsSql, [$userId]);
        
        // Get recent requests
        $recentSql = "SELECT request_id, document_type, status, created_at 
                      FROM requests 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC 
                      LIMIT 5";
        $recentRequests = $db->fetchAll($recentSql, [$userId]);
        
        // Get notifications
        $notifSql = "SELECT notification_id, message, is_read, created_at 
                    FROM notifications 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 5";
        $notifications = $db->fetchAll($notifSql, [$userId]);
    } else {
        // For admin/staff, get all requests
        $statsSql = "SELECT 
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                     FROM requests";
        $stats = $db->fetchOne($statsSql);
        
        // Get recent requests
        $recentSql = "SELECT r.request_id, r.document_type, r.status, r.created_at, 
                             CONCAT(u.first_name, ' ', u.last_name) as resident_name
                      FROM requests r
                      JOIN users u ON r.user_id = u.user_id
                      ORDER BY r.created_at DESC 
                      LIMIT 10";
        $recentRequests = $db->fetchAll($recentSql);
        
        // Get system notifications
        $notifSql = "SELECT notification_id, message, is_read, created_at 
                    FROM notifications 
                    WHERE user_id = ? OR is_system = 1
                    ORDER BY created_at DESC 
                    LIMIT 5";
        $notifications = $db->fetchAll($notifSql, [$userId]);
    }
    
    // Set statistics
    if ($stats) {
        $pendingRequests = $stats['pending'] ?? 0;
        $completedRequests = $stats['completed'] ?? 0;
        $rejectedRequests = $stats['rejected'] ?? 0;
    }
    
    // Close the database connection
    $db->closeConnection();
    
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}

// Get success message if exists
$successMsg = '';
if (isset($_SESSION['success_msg'])) {
    $successMsg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Barangay Clearance and Document Request System</title>
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
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: 100%;
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }
        
        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-card.pending i {
            color: #ffc107;
        }
        
        .stat-card.completed i {
            color: #198754;
        }
        
        .stat-card.rejected i {
            color: #dc3545;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .service-card {
            text-align: center;
            padding: 1.5rem;
        }
        
        .service-card i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
        }
        
        .welcome-section {
            background-color: #0d6efd;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        footer {
            height: 100px;
            display: flex;
            align-items: center;
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
        
        @media (max-width: 768px) {
            .welcome-section {
                text-align: center;
            }
            
            .welcome-section .btn {
                margin-top: 1rem;
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
                            <a class="nav-link active" href="dashboard.php">Dashboard</a>
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

        <!-- Welcome Section -->
        <section class="welcome-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1>Welcome, <?php echo htmlspecialchars($userName); ?>!</h1>
                        <p class="lead mb-0">Manage your document requests and barangay services in one place.</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="request-document.php" class="btn btn-light btn-lg">
                            <i class="bi bi-file-earmark-plus me-2"></i>Request Document
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <div class="container py-4">
            <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $successMsg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <h4 class="mb-4"><i class="bi bi-clipboard-data me-2"></i>Your Request Summary</h4>
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card stat-card pending">
                        <i class="bi bi-hourglass-split"></i>
                        <div class="stat-value"><?php echo $pendingRequests; ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stat-card completed">
                        <i class="bi bi-check-circle"></i>
                        <div class="stat-value"><?php echo $completedRequests; ?></div>
                        <div class="stat-label">Completed Requests</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stat-card rejected">
                        <i class="bi bi-x-circle"></i>
                        <div class="stat-value"><?php echo $rejectedRequests; ?></div>
                        <div class="stat-label">Rejected Requests</div>
                    </div>
                </div>
            </div>
            
            <!-- Available Services -->
            <h4 class="mb-4 mt-5"><i class="bi bi-grid me-2"></i>Available Services</h4>
            <div class="row mb-5">
                <div class="col-md-4 col-lg-3 mb-3">
                    <a href="request-document.php?type=barangay_clearance" class="text-decoration-none">
                        <div class="card service-card">
                            <i class="bi bi-file-earmark-text"></i>
                            <h5>Barangay Clearance</h5>
                            <p class="text-muted mb-0">Apply for a barangay clearance document</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-4 col-lg-3 mb-3">
                    <a href="request-document.php?type=certificate_residency" class="text-decoration-none">
                        <div class="card service-card">
                            <i class="bi bi-house-door"></i>
                            <h5>Certificate of Residency</h5>
                            <p class="text-muted mb-0">Certify your residency in the barangay</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-4 col-lg-3 mb-3">
                    <a href="request-document.php?type=business_permit" class="text-decoration-none">
                        <div class="card service-card">
                            <i class="bi bi-shop"></i>
                            <h5>Business Permit</h5>
                            <p class="text-muted mb-0">Apply for a barangay business permit</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-4 col-lg-3 mb-3">
                    <a href="request-document.php?type=good_moral" class="text-decoration-none">
                        <div class="card service-card">
                            <i class="bi bi-award"></i>
                            <h5>Good Moral Certificate</h5>
                            <p class="text-muted mb-0">Obtain a certificate of good moral character</p>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Recent Requests -->
            <h4 class="mb-4 mt-5"><i class="bi bi-clock-history me-2"></i>Recent Requests</h4>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Document Type</th>
                                    <?php if ($userType != 'resident'): ?>
                                    <th>Resident</th>
                                    <?php endif; ?>
                                    <th>Date Requested</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentRequests)): ?>
                                <tr>
                                    <td colspan="<?php echo $userType != 'resident' ? '6' : '5'; ?>" class="text-center">No requests found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recentRequests as $request): ?>
                                    <tr>
                                        <td>#<?php echo $request['request_id']; ?></td>
                                        <td>
                                            <?php 
                                                $docType = ucwords(str_replace('_', ' ', $request['document_type']));
                                                echo $docType;
                                            ?>
                                        </td>
                                        <?php if ($userType != 'resident'): ?>
                                        <td><?php echo isset($request['resident_name']) ? htmlspecialchars($request['resident_name']) : 'N/A'; ?></td>
                                        <?php endif; ?>
                                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td>
                                            <?php if ($request['status'] == 'pending'): ?>
                                            <span class="badge bg-warning status-badge">Pending</span>
                                            <?php elseif ($request['status'] == 'processing'): ?>
                                            <span class="badge bg-info status-badge">Processing</span>
                                            <?php elseif ($request['status'] == 'completed'): ?>
                                            <span class="badge bg-success status-badge">Completed</span>
                                            <?php elseif ($request['status'] == 'rejected'): ?>
                                            <span class="badge bg-danger status-badge">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view-request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-3">
                        <a href="my-requests.php" class="btn btn-outline-primary">View All Requests</a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Links and Help -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Quick Links</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <a href="profile.php" class="text-decoration-none">
                                        <i class="bi bi-person me-2"></i>Update Your Profile
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="document-requirements.php" class="text-decoration-none">
                                        <i class="bi bi-list-check me-2"></i>Document Requirements
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="announcements.php" class="text-decoration-none">
                                        <i class="bi bi-megaphone me-2"></i>Barangay Announcements
                                    </a>
                                </li>
                                <li class="list-group-item">
                                    <a href="contact.php" class="text-decoration-none">
                                        <i class="bi bi-telephone me-2"></i>Contact Barangay Officials
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-question-circle me-2"></i>Help & Support</h5>
                        </div>
                        <div class="card-body">
                            <p>Need assistance with your document requests or other barangay services?</p>
                            <div class="mb-3">
                                <i class="bi bi-telephone-fill me-2 text-info"></i>
                                <strong>Hotline:</strong> (123) 456-7890
                            </div>
                            <div class="mb-3">
                                <i class="bi bi-envelope-fill me-2 text-info"></i>
                                <strong>Email:</strong> support@barangayservices.gov.ph
                            </div>
                            <div class="mb-3">
                                <i class="bi bi-geo-alt-fill me-2 text-info"></i>
                                <strong>Office:</strong> Barangay Hall, Main Street, Barangay Name
                            </div>
                            <div class="mb-3">
                                <i class="bi bi-clock-fill me-2 text-info"></i>
                                <strong>Office Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM
                            </div>
                            <a href="faq.php" class="btn btn-info text-white">
                                <i class="bi bi-question-circle me-2"></i>View FAQs
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

    <!-- Bootstrap JS Bundle with Popper -->
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
        });
    </script>
</body>
</html>