<?php
// Start session securely
session_start();

// Database connection parameters
$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "barangay_request_system"; 


// Initialize variables
$showAlert = false;
$alertType = "";
$alertMessage = "";
$pendingUsers = 0;
$activeUsers = 0;
$pendingRequests = 0;
$completedRequests = 0;
$recentUsers = [];
$recentRequests = [];

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $showAlert = true;
    $alertType = "danger";
    $alertMessage = "Database connection failed: " . $conn->connect_error;
} else {
    // Get user statistics
    $userStatsSql = "SELECT 
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
                    FROM users WHERE user_type = 'resident'";
    $userStatsResult = $conn->query($userStatsSql);
    
    if ($userStatsResult && $userStatsRow = $userStatsResult->fetch_assoc()) {
        $pendingUsers = $userStatsRow['pending'] ?? 0;
        $activeUsers = $userStatsRow['active'] ?? 0;
        $inactiveUsers = $userStatsRow['inactive'] ?? 0;
    }
    
    // Get document request statistics (if requests table exists)
    try {
        $requestStatsSql = "SELECT 
                          COUNT(*) as total_requests,
                          SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                          SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                          FROM requests";
        $requestStatsResult = $conn->query($requestStatsSql);
        
        if ($requestStatsResult && $requestStatsRow = $requestStatsResult->fetch_assoc()) {
            $totalRequests = $requestStatsRow['total_requests'] ?? 0;
            $pendingRequests = $requestStatsRow['pending'] ?? 0;
            $completedRequests = $requestStatsRow['completed'] ?? 0;
        }
    } catch (Exception $e) {
        // Requests table might not exist yet
        $totalRequests = 0;
        $pendingRequests = 0;
        $completedRequests = 0;
    }
    
    // Get recent users
    $recentUsersSql = "SELECT user_id, email, first_name, last_name, status, created_at
                      FROM users 
                      WHERE user_type = 'resident'
                      ORDER BY created_at DESC LIMIT 5";
    $recentUsersResult = $conn->query($recentUsersSql);
    
    if ($recentUsersResult) {
        while ($row = $recentUsersResult->fetch_assoc()) {
            $recentUsers[] = $row;
        }
    }
    
    // Get recent document requests (if requests table exists)
    try {
        $recentRequestsSql = "SELECT r.request_id, r.document_type, r.status, r.created_at, 
                             u.first_name, u.last_name
                             FROM requests r
                             JOIN users u ON r.user_id = u.user_id
                             ORDER BY r.created_at DESC LIMIT 5";
        $recentRequestsResult = $conn->query($recentRequestsSql);
        
        if ($recentRequestsResult) {
            while ($row = $recentRequestsResult->fetch_assoc()) {
                $recentRequests[] = $row;
            }
        }
    } catch (Exception $e) {
        // Requests table might not exist yet
    }
    
    // Handle approval/rejection actions
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['user_id'])) {
        $userId = intval($_POST['user_id']);
        $action = $_POST['action'];
        
        // Update user status based on action
        if ($action == 'approve') {
            $status = 'active';
            $actionText = 'approved';
        } else if ($action == 'reject') {
            $status = 'inactive';
            $actionText = 'rejected';
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "Invalid action.";
            goto SkipAction; // Skip further processing
        }
        
        // Prepare and execute update statement
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->bind_param("si", $status, $userId);
        
        if ($stmt->execute()) {
            $showAlert = true;
            $alertType = "success";
            $alertMessage = "User has been successfully $actionText.";
            
            // We're removing the admin logging functionality since it requires an account
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "Error updating user: " . $stmt->error;
        }
        
        // Close statement
        $stmt->close();
        
        // Refresh page data
        header("Location: admin.php");
        exit();
    }
    
    SkipAction:
    
    // Close connection
    $conn->close();
}

// Check for error message from other pages
if (isset($_SESSION['error_msg'])) {
    $showAlert = true;
    $alertType = "danger";
    $alertMessage = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Check for success message from other pages
if (isset($_SESSION['success_msg'])) {
    $showAlert = true;
    $alertType = "success";
    $alertMessage = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Barangay Clearance and Document Request System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            padding-top: 56px;
            background-color: #343a40;
            color: white;
            z-index: 1;
        }
        
        .sidebar-item {
            padding: 10px 15px;
            border-left: 3px solid transparent;
        }
        
        .sidebar-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: #0d6efd;
        }
        
        .sidebar-item.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: #0d6efd;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            padding-top: 76px;
        }
        
        .stat-card {
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding-top: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-building-fill-gear me-2"></i>
                Admin Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="navbarDropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            Administrator
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="pt-2 pb-4">
            <div class="px-4 py-3">
                <h5 class="mb-0">Admin Panel</h5>
            </div>
            <a href="admin.php" class="d-block text-decoration-none text-white sidebar-item active">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="Admin/view/verify-users.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-person-check me-2"></i> Verify Users
                <?php if ($pendingUsers > 0): ?>
                <span class="badge bg-warning rounded-pill float-end"><?php echo $pendingUsers; ?></span>
                <?php endif; ?>
            </a>
            <a href="Admin/view/document-requests.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-file-earmark-text me-2"></i> Document Requests
                <?php if ($pendingRequests > 0): ?>
                <span class="badge bg-warning rounded-pill float-end"><?php echo $pendingRequests; ?></span>
                <?php endif; ?>
            </a>
            <a href="manage-users.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-people me-2"></i> Manage Users
            </a>
            <a href="reports.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-graph-up me-2"></i> Reports
            </a>
            <a href="system-logs.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-journal-text me-2"></i> System Logs
            </a>
            <a href="settings.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-gear me-2"></i> Settings
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col">
                    <h1 class="display-6 mb-3">Welcome, Admin!</h1>
                    <p class="lead">Manage the Barangay Clearance and Document Request System from this dashboard.</p>
                    
                    <?php if ($showAlert): ?>
                        <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $alertMessage; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- System Overview Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stat-card h-100 bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Pending Users</h6>
                                    <h3 class="mb-0"><?php echo $pendingUsers; ?></h3>
                                </div>
                                <i class="bi bi-person-plus fs-1"></i>
                            </div>
                            <div class="mt-3">
                                <a href="Admin/view/verify-users.php?status=pending" class="btn btn-light btn-sm text-primary">
                                    <i class="bi bi-arrow-right-circle"></i> Verify Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stat-card h-100 bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Active Users</h6>
                                    <h3 class="mb-0"><?php echo $activeUsers; ?></h3>
                                </div>
                                <i class="bi bi-people fs-1"></i>
                            </div>
                            <div class="mt-3">
                                <a href="Admin/view/verify-users.php?status=active" class="btn btn-light btn-sm text-success">
                                    <i class="bi bi-arrow-right-circle"></i> View All
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stat-card h-100 bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Pending Requests</h6>
                                    <h3 class="mb-0"><?php echo $pendingRequests; ?></h3>
                                </div>
                                <i class="bi bi-file-earmark-text fs-1"></i>
                            </div>
                            <div class="mt-3">
                                <a href="Admin/view/document-requests.php?status=pending" class="btn btn-light btn-sm text-warning">
                                    <i class="bi bi-arrow-right-circle"></i> Process Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stat-card h-100 bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Completed Requests</h6>
                                    <h3 class="mb-0"><?php echo $completedRequests; ?></h3>
                                </div>
                                <i class="bi bi-check-square fs-1"></i>
                            </div>
                            <div class="mt-3">
                                <a href="Admin/view/document-requests.php?status=completed" class="btn btn-light btn-sm text-info">
                                    <i class="bi bi-arrow-right-circle"></i> View All
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="row">
                <!-- Recent Users -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Recent Registrations</h5>
                            <a href="verify-users.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentUsers)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-people text-secondary" style="font-size: 2.5rem;"></i>
                                    <p class="mt-3 text-muted">No recent user registrations</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentUsers as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($user['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning text-dark status-badge">Pending</span>
                                                    <?php elseif ($user['status'] == 'active'): ?>
                                                        <span class="badge bg-success status-badge">Approved</span>
                                                    <?php elseif ($user['status'] == 'inactive'): ?>
                                                        <span class="badge bg-danger status-badge">Rejected</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['status'] == 'pending'): ?>
                                                        <div class="btn-group btn-group-sm">
                                                            <form method="post" class="me-1">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <button type="submit" class="btn btn-success btn-sm" title="Approve" onclick="return confirm('Are you sure?')">
                                                                    <i class="bi bi-check-lg"></i>
                                                                </button>
                                                            </form>
                                                            <form method="post">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <button type="submit" class="btn btn-danger btn-sm" title="Reject" onclick="return confirm('Are you sure?')">
                                                                    <i class="bi bi-x-lg"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    <?php else: ?>
                                                        <a href="user-details.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Document Requests -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Recent Document Requests</h5>
                            <a href="Admin/view/document-requests.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentRequests)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-file-earmark text-secondary" style="font-size: 2.5rem;"></i>
                                    <p class="mt-3 text-muted">No recent document requests</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Document Type</th>
                                                <th>Requestor</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentRequests as $request): ?>
                                            <tr>
                                                <td>#<?php echo $request['request_id']; ?></td>
                                                <td>
                                                    <?php 
                                                        $docType = str_replace('_', ' ', $request['document_type']);
                                                        echo ucwords($docType);
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($request['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning text-dark status-badge">Pending</span>
                                                    <?php elseif ($request['status'] == 'processing'): ?>
                                                        <span class="badge bg-info text-white status-badge">Processing</span>
                                                    <?php elseif ($request['status'] == 'completed'): ?>
                                                        <span class="badge bg-success status-badge">Completed</span>
                                                    <?php elseif ($request['status'] == 'rejected'): ?>
                                                        <span class="badge bg-danger status-badge">Rejected</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="Admin/view/request-details.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="Admin/view/verify-users.php?status=pending" class="btn btn-lg btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                                        <i class="bi bi-person-check fs-2 mb-2"></i>
                                        <span>Verify Users</span>
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="Admin/view/document-requests.php?status=pending" class="btn btn-lg btn-outline-warning w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                                        <i class="bi bi-file-earmark-check fs-2 mb-2"></i>
                                        <span>Process Requests</span>
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="reports.php" class="btn btn-lg btn-outline-success w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                                        <i class="bi bi-file-earmark-pdf fs-2 mb-2"></i>
                                        <span>Generate Reports</span>
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3">
                                    <a href="settings.php" class="btn btn-lg btn-outline-secondary w-100 h-100 d-flex flex-column justify-content-center align-items-center py-3">
                                        <i class="bi bi-gear fs-2 mb-2"></i>
                                        <span>System Settings</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>