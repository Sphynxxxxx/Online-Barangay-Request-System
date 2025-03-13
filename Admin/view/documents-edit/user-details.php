<?php
session_start();

$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "barangay_request_system"; 

$showAlert = false;
$alertType = "";
$alertMessage = "";
$userDetails = null;
$userRequests = [];

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    $showAlert = true;
    $alertType = "danger";
    $alertMessage = "Database connection failed: " . $conn->connect_error;
} else {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $showAlert = true;
        $alertType = "danger";
        $alertMessage = "Invalid user ID.";
    } else {
        $userId = intval($_GET['id']);
        
        // Fetch user details
        $userSql = "SELECT u.*, 
                    DATE_FORMAT(u.created_at, '%M %d, %Y') as registered_date,
                    DATE_FORMAT(u.updated_at, '%M %d, %Y %h:%i %p') as last_updated
                   FROM users u 
                   WHERE u.user_id = ?";
        
        $stmt = $conn->prepare($userSql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $userDetails = $result->fetch_assoc();
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "User not found.";
        }
        $stmt->close();
        
        // Fetch user requests if user exists
        if ($userDetails) {
            $requestsSql = "SELECT r.*, 
                          DATE_FORMAT(r.created_at, '%M %d, %Y') as request_date
                          FROM requests r 
                          WHERE r.user_id = ? 
                          ORDER BY r.created_at DESC";
            
            $stmt = $conn->prepare($requestsSql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $requestsResult = $stmt->get_result();
            
            while ($row = $requestsResult->fetch_assoc()) {
                $userRequests[] = $row;
            }
            $stmt->close();
        }
        
        // Handle status update
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
            $action = $_POST['action'];
            $newStatus = '';
            $actionText = '';
            
            if ($action === 'activate') {
                $newStatus = 'active';
                $actionText = 'activated';
            } elseif ($action === 'deactivate') {
                $newStatus = 'inactive';
                $actionText = 'deactivated';
            } else {
                $showAlert = true;
                $alertType = "danger";
                $alertMessage = "Invalid action.";
                goto SkipUpdate;
            }
            
            $updateSql = "UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("si", $newStatus, $userId);
            
            if ($stmt->execute()) {
                $showAlert = true;
                $alertType = "success";
                $alertMessage = "User account has been $actionText successfully.";
                
                // Refresh user details
                $stmt = $conn->prepare($userSql);
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $userDetails = $result->fetch_assoc();
            } else {
                $showAlert = true;
                $alertType = "danger";
                $alertMessage = "Error updating user: " . $stmt->error;
            }
            $stmt->close();
        }
        
        SkipUpdate:
        
        // Handle delete user
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
            // Check if there are pending requests
            $pendingCheck = "SELECT COUNT(*) as pending_count 
                           FROM requests 
                           WHERE user_id = ? 
                           AND status IN ('pending', 'processing', 'ready')";
            
            $stmt = $conn->prepare($pendingCheck);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $pendingResult = $stmt->get_result();
            $pendingCount = $pendingResult->fetch_assoc()['pending_count'];
            $stmt->close();
            
            if ($pendingCount > 0) {
                $showAlert = true;
                $alertType = "danger";
                $alertMessage = "Cannot delete user with pending requests. Please process or cancel all requests first.";
            } else {
                // Delete user
                $deleteSql = "DELETE FROM users WHERE user_id = ?";
                $stmt = $conn->prepare($deleteSql);
                $stmt->bind_param("i", $userId);
                
                if ($stmt->execute()) {
                    // Redirect to users list with success message
                    $_SESSION['success_msg'] = "User has been deleted successfully.";
                    $stmt->close();
                    $conn->close();
                    header("Location: user-details.php");
                    exit();
                } else {
                    $showAlert = true;
                    $alertType = "danger";
                    $alertMessage = "Error deleting user: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    $conn->close();
}

// Status badge colors
$statusColors = [
    'pending' => 'bg-warning text-dark',
    'processing' => 'bg-info text-white',
    'ready' => 'bg-primary',
    'completed' => 'bg-success',
    'rejected' => 'bg-danger',
    'cancelled' => 'bg-secondary'
];

// User status badge colors
$userStatusColors = [
    'pending' => 'bg-warning text-dark',
    'active' => 'bg-success',
    'inactive' => 'bg-danger'
];

// Document types for display
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - Barangay Clearance and Document Request System</title>
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
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
        }
        
        .user-avatar {
            width: 120px;
            height: 120px;
            font-size: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .badge-ready {
            background-color: #2563eb !important; /* Brighter blue */
            color: white !important;
            padding: 0.4rem 0.7rem !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            animation: pulse 2s infinite;
        }
        
        .badge-ready i {
            margin-right: 0.25rem;
        }
        
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
            <a href="../../../admin.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="../verify-users.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-person-check me-2"></i> Verify Users
            </a>
            <a href="../document-requests.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-file-earmark-text me-2"></i> Document Requests
            </a>
            <a href="../manage-users.php" class="d-block text-decoration-none text-white sidebar-item active">
                <i class="bi bi-people me-2"></i> Manage Users
            </a>
            <a href="../reports.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-graph-up me-2"></i> Reports
            </a>
            <a href="../system-logs.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-journal-text me-2"></i> System Logs
            </a>
            <a href="../settings.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-gear me-2"></i> Settings
            </a>
        </div>
    </div>
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="manage-users.php">Manage Users</a></li>
                            <li class="breadcrumb-item active" aria-current="page">User Details</li>
                        </ol>
                    </nav>
                    
                    <?php if ($showAlert): ?>
                        <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $alertMessage; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($userDetails): ?>
            <div class="row">
                <!-- User Profile Card -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>User Profile</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-3 mx-auto bg-light rounded-circle user-avatar">
                                <i class="bi bi-person text-secondary"></i>
                            </div>
                            <h4><?php echo htmlspecialchars($userDetails['first_name'] . ' ' . $userDetails['last_name']); ?></h4>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($userDetails['email']); ?></p>
                            <div class="mb-3">
                                <span class="badge <?php echo $userStatusColors[$userDetails['status']] ?? 'bg-secondary'; ?> px-3 py-2">
                                    <?php 
                                    if ($userDetails['status'] === 'active') echo '<i class="bi bi-check-circle me-1"></i>';
                                    elseif ($userDetails['status'] === 'pending') echo '<i class="bi bi-clock me-1"></i>';
                                    elseif ($userDetails['status'] === 'inactive') echo '<i class="bi bi-x-circle me-1"></i>';
                                    echo ucfirst($userDetails['status']); 
                                    ?>
                                </span>
                            </div>
                            
                            <div class="d-grid gap-2 mt-4">
                                <?php if ($userDetails['status'] === 'active'): ?>
                                <form method="post" onsubmit="return confirm('Are you sure you want to deactivate this user?');">
                                    <input type="hidden" name="action" value="deactivate">
                                    <button type="submit" class="btn btn-danger btn-block">
                                        <i class="bi bi-person-x me-2"></i>Deactivate Account
                                    </button>
                                </form>
                                <?php elseif ($userDetails['status'] === 'inactive'): ?>
                                <form method="post" onsubmit="return confirm('Are you sure you want to activate this user?');">
                                    <input type="hidden" name="action" value="activate">
                                    <button type="submit" class="btn btn-success btn-block">
                                        <i class="bi bi-person-check me-2"></i>Activate Account
                                    </button>
                                </form>
                                <?php elseif ($userDetails['status'] === 'pending'): ?>
                                <div class="row">
                                    <div class="col-6">
                                        <form method="post" onsubmit="return confirm('Approve this user?');">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="btn btn-success btn-block w-100">
                                                <i class="bi bi-check-lg me-2"></i>Approve
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-6">
                                        <form method="post" onsubmit="return confirm('Reject this user?');">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="btn btn-danger btn-block w-100">
                                                <i class="bi bi-x-lg me-2"></i>Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Delete User Button (with modal trigger) -->
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                                    <i class="bi bi-trash me-2"></i>Delete User
                                </button>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <div class="small text-muted">
                                <div class="mb-1"><strong>Registered:</strong> <?php echo $userDetails['registered_date']; ?></div>
                                <div><strong>Last Updated:</strong> <?php echo $userDetails['last_updated']; ?></div>
                            </div>
                        </div>
                    </div>
                    <!-- Contact Information -->
                    <div class="card mt-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Contact Information</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <span><i class="bi bi-envelope me-2"></i>Email</span>
                                        <span class="text-truncate"><?php echo htmlspecialchars($userDetails['email']); ?></span>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <span><i class="bi bi-telephone me-2"></i>Phone</span>
                                        <span><?php echo htmlspecialchars($userDetails['contact_number'] ?? 'Not provided'); ?></span>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <span><i class="bi bi-geo-alt me-2"></i>Address</span>
                                        <span class="text-end"><?php echo htmlspecialchars($userDetails['address'] ?? 'Not provided'); ?></span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- User Requests -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Document Requests</h5>
                            <span class="badge bg-light text-primary"><?php echo count($userRequests); ?> Requests</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($userRequests)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                                    <p class="mt-3">No document requests found for this user.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Request ID</th>
                                                <th>Document Type</th>
                                                <th>Purpose</th>
                                                <th>Date Requested</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($userRequests as $request): ?>
                                                <tr>
                                                    <td>#<?php echo $request['request_id']; ?></td>
                                                    <td><?php echo $documentTypes[$request['document_type']] ?? ucwords(str_replace('_', ' ', $request['document_type'])); ?></td>
                                                    <td><?php echo htmlspecialchars(substr($request['purpose'], 0, 30)) . (strlen($request['purpose']) > 30 ? '...' : ''); ?></td>
                                                    <td><?php echo $request['request_date']; ?></td>
                                                    <td>
                                                        <?php if ($request['status'] == 'pending'): ?>
                                                            <span class="badge bg-warning text-dark status-badge">Pending</span>
                                                        <?php elseif ($request['status'] == 'processing'): ?>
                                                            <span class="badge bg-info text-white status-badge">Processing</span>
                                                        <?php elseif ($request['status'] == 'ready'): ?>
                                                            <span class="badge badge-ready status-badge">
                                                                <i class="bi bi-box-seam"></i> Ready for Pickup
                                                            </span>
                                                        <?php elseif ($request['status'] == 'completed'): ?>
                                                            <span class="badge bg-success status-badge">Completed</span>
                                                        <?php elseif ($request['status'] == 'rejected'): ?>
                                                            <span class="badge bg-danger status-badge">Rejected</span>
                                                        <?php elseif ($request['status'] == 'cancelled'): ?>
                                                            <span class="badge bg-secondary status-badge">Cancelled</span>
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
                    <!-- User Details -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>User Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                                    <dl class="row">
                                        <dt class="col-sm-4">Full Name</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($userDetails['first_name'] . ' ' . $userDetails['last_name']); ?></dd>
                                        
                                        <dt class="col-sm-4">Gender</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($userDetails['gender'] ?? 'Not specified'); ?></dd>
                                        
                                        <dt class="col-sm-4">Birthdate</dt>
                                        <dd class="col-sm-8">
                                            <?php 
                                            echo isset($userDetails['birthdate']) && $userDetails['birthdate'] != '0000-00-00' 
                                                ? date('F d, Y', strtotime($userDetails['birthdate'])) 
                                                : 'Not specified'; 
                                            ?>
                                        </dd>
                                    </dl>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="border-bottom pb-2 mb-3">Account Information</h6>
                                    <dl class="row">
                                        <dt class="col-sm-4">User Type</dt>
                                        <dd class="col-sm-8"><?php echo ucfirst($userDetails['user_type']); ?></dd>
                                        
                                        <dt class="col-sm-4">Status</dt>
                                        <dd class="col-sm-8">
                                            <span class="badge <?php echo $userStatusColors[$userDetails['status']] ?? 'bg-secondary'; ?>">
                                                <?php echo ucfirst($userDetails['status']); ?>
                                            </span>
                                        </dd>
                                        
                                        <dt class="col-sm-4">Last Login</dt>
                                        <dd class="col-sm-8">
                                            <?php 
                                            echo isset($userDetails['last_login']) && $userDetails['last_login'] != '0000-00-00 00:00:00' 
                                                ? date('M d, Y g:i A', strtotime($userDetails['last_login'])) 
                                                : 'Never'; 
                                            ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                            
                            <?php if (!empty($userDetails['notes'])): ?>
                            <div class="mt-4">
                                <h6 class="border-bottom pb-2 mb-3">Notes</h6>
                                <p><?php echo nl2br(htmlspecialchars($userDetails['notes'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <a href="../manage-users.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Back to User List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-person-x text-muted" style="font-size: 4rem;"></i>
                                <h3 class="mt-4">User Not Found</h3>
                                <p class="text-muted">The user you are looking for does not exist or has been removed.</p>
                                <a href="../manage-users.php" class="btn btn-primary mt-3">
                                    <i class="bi bi-arrow-left me-2"></i>Back to User List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteUserModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. All data associated with this user will be permanently deleted.
                    </div>
                    <p>Are you sure you want to delete the following user?</p>
                    <p><strong>Name:</strong> <?php echo isset($userDetails) ? htmlspecialchars($userDetails['first_name'] . ' ' . $userDetails['last_name']) : ''; ?></p>
                    <p><strong>Email:</strong> <?php echo isset($userDetails) ? htmlspecialchars($userDetails['email']) : ''; ?></p>
                    
                    <?php if (!empty($userRequests)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        This user has <?php echo count($userRequests); ?> document request(s). Deleting this user will also delete all associated requests.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" onsubmit="return confirm('Are you absolutely sure? This action CANNOT be undone!');">
                        <input type="hidden" name="delete_user" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>Delete Permanently
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Highlight ready for pickup status with animation
        document.addEventListener('DOMContentLoaded', function() {
            // Add special animation to Ready for Pickup badges
            const readyBadges = document.querySelectorAll('.badge-ready');
            readyBadges.forEach(badge => {
                // Add subtle pulse animation
                badge.style.animation = 'pulse 2s infinite';
            });
        });
    </script>
</body>
</html>