<?php
session_start();

$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "barangay_request_system"; 

$showAlert = false;
$alertType = "";
$alertMessage = "";
$users = [];

// Handle approval/rejection actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);
    $action = $_POST['action'];
    
    // Create database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        $showAlert = true;
        $alertType = "danger";
        $alertMessage = "Database connection failed: " . $conn->connect_error;
    } else {
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
            $conn->close();
            goto SkipAction; // Skip further processing
        }
        
        // Prepare and execute update statement
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->bind_param("si", $status, $userId);
        
        if ($stmt->execute()) {
            $showAlert = true;
            $alertType = "success";
            $alertMessage = "User has been successfully $actionText.";
            
            // We've removed admin logs functionality as it's not needed
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "Error updating user: " . $stmt->error;
        }
        
        // Close statement and connection
        $stmt->close();
        $conn->close();
    }
}

SkipAction:

// Fetch users for display
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    $showAlert = true;
    $alertType = "danger";
    $alertMessage = "Database connection failed: " . $conn->connect_error;
} else {
    // Prepare status filter
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : 'pending';
    $validStatuses = ['pending', 'active', 'inactive', 'all'];
    
    if (!in_array($statusFilter, $validStatuses)) {
        $statusFilter = 'pending';
    }
    
    // Prepare and execute query
    if ($statusFilter == 'all') {
        $stmt = $conn->prepare("SELECT user_id, email, first_name, middle_name, last_name, 
                              contact_number, zone, house_number, id_path, status, created_at 
                              FROM users WHERE user_type = 'resident' ORDER BY created_at DESC");
    } else {
        $stmt = $conn->prepare("SELECT user_id, email, first_name, middle_name, last_name, 
                              contact_number, zone, house_number, id_path, status, created_at 
                              FROM users WHERE user_type = 'resident' AND status = ? ORDER BY created_at DESC");
        $stmt->bind_param("s", $statusFilter);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Users - Admin Dashboard</title>
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
        
        .id-image {
            max-width: 100%;
            max-height: 200px;
            cursor: pointer;
        }
        
        .modal-body img {
            max-width: 100%;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-building-fill-gear me-2"></i>
                Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#">
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
                <h5 class="mb-0">Admin Dashboard</h5>
            </div>
            <a href="../../admin.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="verify-users.php" class="d-block text-decoration-none text-white sidebar-item active">
                <i class="bi bi-person-check me-2"></i> Verify Users
            </a>
            <a href="document-requests.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-file-earmark-text me-2"></i> Document Requests
            </a>
            <a href="manage-users.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-people me-2"></i> Manage Users
            </a>
            <!--<a href="announcements.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-megaphone me-2"></i> Announcements
            </a>
            <a href="reports.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-graph-up me-2"></i> Reports
            </a>
            <a href="system-logs.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-journal-text me-2"></i> System Logs
            </a>
            <a href="settings.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-gear me-2"></i> Settings
            </a>-->
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4"><i class="bi bi-person-check me-2"></i>Verify User Accounts</h2>
            
            <?php if ($showAlert): ?>
                <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $alertMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Status Filter Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo (!isset($_GET['status']) || $_GET['status'] == 'pending') ? 'active' : ''; ?>" href="?status=pending">
                        <span class="badge bg-warning rounded-pill me-1"><?php echo count(array_filter($users, function($u) { return $u['status'] == 'pending'; })); ?></span>
                        Pending
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'active' : ''; ?>" href="?status=active">
                        <span class="badge bg-success rounded-pill me-1"><?php echo count(array_filter($users, function($u) { return $u['status'] == 'active'; })); ?></span>
                        Approved
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'active' : ''; ?>" href="?status=inactive">
                        <span class="badge bg-danger rounded-pill me-1"><?php echo count(array_filter($users, function($u) { return $u['status'] == 'inactive'; })); ?></span>
                        Rejected
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (isset($_GET['status']) && $_GET['status'] == 'all') ? 'active' : ''; ?>" href="?status=all">
                        All Users
                    </a>
                </li>
            </ul>
            
            <!-- Users Table -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox-fill text-secondary" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No users found</h5>
                            <p class="text-muted">There are no users with the selected status</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Address</th>
                                        <th>Registration Date</th>
                                        <th>Status</th>
                                        <th>ID Card</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>#<?php echo $user['user_id']; ?></td>
                                        <td>
                                            <?php 
                                                echo htmlspecialchars($user['first_name'] . ' ');
                                                if (!empty($user['middle_name'])) {
                                                    echo htmlspecialchars($user['middle_name'][0] . '. ');
                                                }
                                                echo htmlspecialchars($user['last_name']);
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['contact_number']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($user['house_number'] . ', Zone ' . $user['zone']); ?>
                                        </td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($user['created_at'])); ?></td>
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
                                            <?php if (file_exists('../../' . $user['id_path'])): ?>
                                                <img src="../../<?php echo $user['id_path']; ?>" alt="ID Card" class="id-image img-thumbnail" 
                                                     data-bs-toggle="modal" data-bs-target="#idModal<?php echo $user['user_id']; ?>">
                                            <?php else: ?>
                                                <span class="badge bg-secondary">File Not Found</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['status'] == 'pending'): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <form method="post" class="me-1">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-success btn-sm" 
                                                                onclick="return confirm('Are you sure you want to approve this user?')">
                                                            <i class="bi bi-check-lg"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="post">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Are you sure you want to reject this user?')">
                                                            <i class="bi bi-x-lg"></i> Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php elseif ($user['status'] == 'inactive'): ?>
                                                <form method="post">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-success btn-sm" 
                                                            onclick="return confirm('Are you sure you want to approve this user?')">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </button>
                                                </form>
                                            <?php elseif ($user['status'] == 'active'): ?>
                                                <form method="post">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to deactivate this user account?')">
                                                        <i class="bi bi-x-lg"></i> Deactivate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <!-- ID Image Modal -->
                                    <div class="modal fade" id="idModal<?php echo $user['user_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">ID Card - <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body text-center">
                                                    <img src="../../<?php echo $user['id_path']; ?>" alt="ID Card" class="img-fluid">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>