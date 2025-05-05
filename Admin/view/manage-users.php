<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "../../backend/connections/config.php";

// Initialize variables
$showAlert = false;
$alertType = "";
$alertMessage = "";
$users = [];
$totalUsers = 0;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$userType = isset($_GET['user_type']) ? $_GET['user_type'] : 'all';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $showAlert = true;
    $alertType = "danger";
    $alertMessage = "Database connection failed: " . $conn->connect_error;
} else {
    
    // Build query based on filters
    $baseQuery = "SELECT u.*, 
                 DATE_FORMAT(u.created_at, '%M %d, %Y') as registered_date
                 FROM users u";
    
    $countQuery = "SELECT COUNT(*) as total FROM users u";
    
    $whereConditions = [];
    $queryParams = [];
    $paramTypes = "";
    
    // Filter by user type
    /*if ($userType !== 'all') {
        $whereConditions[] = "u.user_type = ?";
        $queryParams[] = $userType;
        $paramTypes .= "s";
    }*/
    
    // Filter by status
    if ($status !== 'all') {
        $whereConditions[] = "u.status = ?";
        $queryParams[] = $status;
        $paramTypes .= "s";
    }
    
    // Search functionality
    if (!empty($searchTerm)) {
        $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $searchPattern = "%$searchTerm%";
        $queryParams[] = $searchPattern;
        $queryParams[] = $searchPattern;
        $queryParams[] = $searchPattern;
        $paramTypes .= "sss";
    }
    
    // Combine where conditions
    $whereClause = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";
    
    // Add where clause to queries
    $baseQuery .= $whereClause;
    $countQuery .= $whereClause;
    
    // Count total matching records for pagination
    if (!empty($queryParams)) {
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param($paramTypes, ...$queryParams);
        $stmt->execute();
        $countResult = $stmt->get_result();
        $row = $countResult->fetch_assoc();
        $totalUsers = $row['total'];
        $stmt->close();
    } else {
        $countResult = $conn->query($countQuery);
        $row = $countResult->fetch_assoc();
        $totalUsers = $row['total'];
    }
    
    // Calculate pagination
    $totalPages = ceil($totalUsers / $itemsPerPage);
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Get users with pagination
    $baseQuery .= " ORDER BY u.created_at DESC LIMIT ?, ?";
    $queryParams[] = $offset;
    $queryParams[] = $itemsPerPage;
    $paramTypes .= "ii";
    
    $stmt = $conn->prepare($baseQuery);
    if (!empty($queryParams)) {
        $stmt->bind_param($paramTypes, ...$queryParams);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    $stmt->close();
    
    // Bulk action - change status
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_action']) && isset($_POST['selected_users'])) {
        $action = $_POST['bulk_action'];
        $selectedUsers = $_POST['selected_users'];
        
        if (!empty($selectedUsers) && ($action === 'activate' || $action === 'deactivate' || $action === 'delete')) {
            // Prepare IDs for SQL
            $userIds = array_map('intval', $selectedUsers);
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            
            if ($action === 'activate' || $action === 'deactivate') {
                $newStatus = ($action === 'activate') ? 'active' : 'inactive';
                $actionText = ($action === 'activate') ? 'activated' : 'deactivated';
                
                // Update status
                $updateSql = "UPDATE users SET status = ?, updated_at = NOW() WHERE user_id IN ($placeholders)";
                $stmt = $conn->prepare($updateSql);
                
                $types = 's' . str_repeat('i', count($userIds));
                $params = array_merge([$newStatus], $userIds);
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $showAlert = true;
                    $alertType = "success";
                    $alertMessage = count($userIds) . " user(s) have been successfully $actionText.";
                } else {
                    $showAlert = true;
                    $alertType = "danger";
                    $alertMessage = "Error updating users: " . $stmt->error;
                }
                $stmt->close();
            } elseif ($action === 'delete') {
                // First check if any selected users have pending requests
                $pendingCheckSql = "SELECT COUNT(*) as pending_count FROM requests 
                                   WHERE user_id IN ($placeholders) 
                                   AND status IN ('pending', 'processing', 'ready')";
                $stmt = $conn->prepare($pendingCheckSql);
                $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
                $stmt->execute();
                $pendingResult = $stmt->get_result();
                $pendingCount = $pendingResult->fetch_assoc()['pending_count'];
                $stmt->close();
                
                if ($pendingCount > 0) {
                    $showAlert = true;
                    $alertType = "danger";
                    $alertMessage = "Cannot delete users with pending requests. Please process or cancel all pending requests first.";
                } else {
                    // Delete users
                    $deleteSql = "DELETE FROM users WHERE user_id IN ($placeholders)";
                    $stmt = $conn->prepare($deleteSql);
                    $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
                    
                    if ($stmt->execute()) {
                        $showAlert = true;
                        $alertType = "success";
                        $alertMessage = count($userIds) . " user(s) have been successfully deleted.";
                    } else {
                        $showAlert = true;
                        $alertType = "danger";
                        $alertMessage = "Error deleting users: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
            
            // Refresh the page to show updated data
            if ($alertType === "success") {
                // Store alert in session
                $_SESSION['alert_type'] = $alertType;
                $_SESSION['alert_message'] = $alertMessage;
                
                // Redirect to same page with same filters
                $redirectUrl = "manage-users.php?";
                if ($userType !== 'all') {
                    $redirectUrl .= "user_type=$userType&";
                }
                if ($status !== 'all') {
                    $redirectUrl .= "status=$status&";
                }
                if (!empty($searchTerm)) {
                    $redirectUrl .= "search=" . urlencode($searchTerm) . "&";
                }
                if ($currentPage > 1) {
                    $redirectUrl .= "page=$currentPage&";
                }
                
                header("Location: " . rtrim($redirectUrl, "&?"));
                exit();
            }
        } else {
            $showAlert = true;
            $alertType = "warning";
            $alertMessage = "Please select users and an action to perform.";
        }
    }
    
    // Close connection
    $conn->close();
}

// Check for error message from other pages or redirects
if (isset($_SESSION['alert_type']) && isset($_SESSION['alert_message'])) {
    $showAlert = true;
    $alertType = $_SESSION['alert_type'];
    $alertMessage = $_SESSION['alert_message'];
    unset($_SESSION['alert_type']);
    unset($_SESSION['alert_message']);
}

// Check for success message from other pages
if (isset($_SESSION['success_msg'])) {
    $showAlert = true;
    $alertType = "success";
    $alertMessage = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

/*$userTypes = [
    'admin' => 'Administrator',
    'staff' => 'Staff',
    'resident' => 'Resident'
];*/

$statusColors = [
    'pending' => 'bg-warning text-dark',
    'active' => 'bg-success',
    'inactive' => 'bg-danger'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Barangay Clearance and Document Request System</title>
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
        
        .table th, .table td {
            vertical-align: middle;
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
            <a href="../../admin.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="verify-users.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-person-check me-2"></i> Verify Users
            </a>
            <a href="document-requests.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-file-earmark-text me-2"></i> Document Requests
            </a>
            <a href="manage-users.php" class="d-block text-decoration-none text-white sidebar-item active">
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
            <div class="row mb-4">
                <div class="col">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Manage Users</li>
                        </ol>
                    </nav>
                    <h1 class="h2 mb-2">Manage Users</h1>
                    <p class="lead">View, edit, and manage user accounts in the system.</p>
                    
                    <?php if ($showAlert): ?>
                        <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $alertMessage; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filters and Search -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form action="manage-users.php" method="get" id="filterForm">
                                <div class="row g-3">
                                    <!-- User Type Filter -->
                                    <!--<div class="col-md-3">
                                        <label class="form-label">User Type</label>
                                        <select id="userTypeFilter" name="user_type" class="form-select" onchange="this.form.submit()">
                                            <option value="all" <?php echo $userType == 'all' ? 'selected' : ''; ?>>All Types</option>
                                            <option value="resident" <?php echo $userType == 'resident' ? 'selected' : ''; ?>>Residents</option>
                                            <option value="staff" <?php echo $userType == 'staff' ? 'selected' : ''; ?>>Staff</option>
                                            <option value="admin" <?php echo $userType == 'admin' ? 'selected' : ''; ?>>Administrators</option>
                                        </select>
                                    </div>-->
                                    
                                    <!-- Status Filter -->
                                    <div class="col-md-3">
                                        <label class="form-label">Status</label>
                                        <select id="statusFilter" name="status" class="form-select" onchange="this.form.submit()">
                                            <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Status</option>
                                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Search -->
                                    <div class="col-md-4">
                                        <label class="form-label">Search</label>
                                        <div class="input-group">
                                            <input type="text" id="searchInput" name="search" class="form-control" placeholder="Search by name or email" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="bi bi-search"></i> Search
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Reset Button -->
                                    <div class="col-md-2 d-flex align-items-end">
                                        <a href="manage-users.php" class="btn btn-outline-secondary w-100">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset Filters
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User List -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-people me-2"></i>
                                    User List
                                    <span class="badge bg-secondary ms-2"><?php echo $totalUsers; ?> users</span>
                                </h5>
                                <div>
                                    <?php if ($totalUsers > 0): ?>
                                    <button class="btn btn-sm btn-outline-success me-2" id="exportCSVBtn">
                                        <i class="bi bi-file-earmark-excel"></i> Export to CSV
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" id="printBtn">
                                        <i class="bi bi-printer"></i> Print
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($users)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-people text-secondary" style="font-size: 3rem;"></i>
                                    <p class="mt-3 text-muted">No users found with the selected filters.</p>
                                    <?php if (!empty($searchTerm) || $userType != 'all' || $status != 'all'): ?>
                                        <a href="manage-users.php" class="btn btn-outline-primary">Clear Filters</a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <form id="bulkActionForm" method="post">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="usersTable">
                                            <thead>
                                                <tr>
                                                    <th>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="selectAll">
                                                        </div>
                                                    </th>
                                                    <th>ID</th>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>User Type</th>
                                                    <th>Registered</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="form-check">
                                                            <input class="form-check-input user-checkbox" type="checkbox" name="selected_users[]" value="<?php echo $user['user_id']; ?>">
                                                        </div>
                                                    </td>
                                                    <td><?php echo $user['user_id']; ?></td>
                                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td><?php echo $userTypes[$user['user_type']] ?? ucfirst($user['user_type']); ?></td>
                                                    <td><?php echo $user['registered_date']; ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $statusColors[$user['status']] ?? 'bg-secondary'; ?> status-badge">
                                                            <?php echo ucfirst($user['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="documents-edit/user-details.php?id=<?php echo $user['user_id']; ?>" class="btn btn-outline-primary">
                                                                <i class="bi bi-eye"></i> View
                                                            </a>
                                                            <?php if ($user['status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-outline-success user-approve-btn" data-user-id="<?php echo $user['user_id']; ?>">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger user-reject-btn" data-user-id="<?php echo $user['user_id']; ?>">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Bulk Actions -->
                                    <div class="row mt-3">
                                        <div class="col-md-4">
                                            <div class="input-group">
                                                <select name="bulk_action" class="form-select" id="bulkActionSelect">
                                                    <option value="">Status Action</option>
                                                    <option value="activate">Activate Selected</option>
                                                    <option value="deactivate">Deactivate Selected</option>
                                                    <option value="delete">Delete Selected</option>
                                                </select>
                                                <button type="submit" class="btn btn-outline-primary" id="applyBulkAction">
                                                    Apply
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <!-- Pagination -->
                                            <?php if ($totalPages > 1): ?>
                                                <nav aria-label="Page navigation" class="float-end">
                                                    <ul class="pagination mb-0">
                                                        <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="<?php echo $currentPage > 1 ? '?page=' . ($currentPage - 1) . ($userType != 'all' ? '&user_type=' . $userType : '') . ($status != 'all' ? '&status=' . $status : '') . (!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '') : '#'; ?>">Previous</a>
                                                        </li>
                                                        
                                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                            <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $userType != 'all' ? '&user_type=' . $userType : ''; ?><?php echo $status != 'all' ? '&status=' . $status : ''; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>"><?php echo $i; ?></a>
                                                            </li>
                                                        <?php endfor; ?>
                                                        
                                                        <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="<?php echo $currentPage < $totalPages ? '?page=' . ($currentPage + 1) . ($userType != 'all' ? '&user_type=' . $userType : '') . ($status != 'all' ? '&status=' . $status : '') . (!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '') : '#'; ?>">Next</a>
                                                        </li>
                                                    </ul>
                                                </nav>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve User Modal -->
    <div class="modal fade" id="approveUserModal" tabindex="-1" aria-labelledby="approveUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="approveUserForm">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="approveUserModalLabel">Approve User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to approve this user? This will grant them access to the system.</p>
                        <input type="hidden" name="selected_users[]" id="approveUserId">
                        <input type="hidden" name="bulk_action" value="activate">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-2"></i>Approve User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject User Modal -->
    <div class="modal fade" id="rejectUserModal" tabindex="-1" aria-labelledby="rejectUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="rejectUserForm">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="rejectUserModalLabel">Reject User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to reject this user? They will not be able to access the system.</p>
                        <input type="hidden" name="selected_users[]" id="rejectUserId">
                        <input type="hidden" name="bulk_action" value="deactivate">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-lg me-2"></i>Reject User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        if (bulkActionForm && applyBulkAction) {
                    applyBulkAction.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Check if any users are selected
                        const selectedUsers = document.querySelectorAll('.user-checkbox:checked');
                        if (selectedUsers.length === 0) {
                            alert('Please select at least one user.');
                            return;
                        }
                        
                        // Check if an action is selected
                        const action = bulkActionSelect.value;
                        if (!action) {
                            alert('Please select an action to perform.');
                            return;
                        }
                        
                        // Confirm action
                        let confirmMessage = '';
                        if (action === 'activate') {
                            confirmMessage = 'Are you sure you want to activate ' + selectedUsers.length + ' user(s)?';
                        } else if (action === 'deactivate') {
                            confirmMessage = 'Are you sure you want to deactivate ' + selectedUsers.length + ' user(s)?';
                        } else if (action === 'delete') {
                            confirmMessage = 'Are you sure you want to delete ' + selectedUsers.length + ' user(s)? This action cannot be undone!';
                        }
                        
                        if (confirm(confirmMessage)) {
                            bulkActionForm.submit();
                        }
                    });
                }
                
                // Approve/Reject User buttons
                const approveButtons = document.querySelectorAll('.user-approve-btn');
                const rejectButtons = document.querySelectorAll('.user-reject-btn');
                const approveUserModal = new bootstrap.Modal(document.getElementById('approveUserModal'));
                const rejectUserModal = new bootstrap.Modal(document.getElementById('rejectUserModal'));
                const approveUserId = document.getElementById('approveUserId');
                const rejectUserId = document.getElementById('rejectUserId');
                
                approveButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const userId = this.getAttribute('data-user-id');
                        approveUserId.value = userId;
                        approveUserModal.show();
                    });
                });
                
                rejectButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const userId = this.getAttribute('data-user-id');
                        rejectUserId.value = userId;
                        rejectUserModal.show();
                    });
                });
                
                // Export to CSV
                const exportCSVBtn = document.getElementById('exportCSVBtn');
                if (exportCSVBtn) {
                    exportCSVBtn.addEventListener('click', function() {
                        exportTableToCSV('users_<?php echo date('Y-m-d'); ?>.csv');
                    });
                }
                
                // Print table
                const printBtn = document.getElementById('printBtn');
                if (printBtn) {
                    printBtn.addEventListener('click', function() {
                        printTable();
                    });
                }
                
                // Function to export table to CSV
                function exportTableToCSV(filename) {
                    const table = document.getElementById('usersTable');
                    if (!table) return;
                    
                    const rows = table.querySelectorAll('tr');
                    const csv = [];
                    
                    for (let i = 0; i < rows.length; i++) {
                        const row = [], cols = rows[i].querySelectorAll('td, th');
                        
                        for (let j = 1; j < cols.length - 1; j++) {
                            // Skip checkbox column and actions column
                            let text = cols[j].textContent.trim();
                            
                            // Handle badges - extract text only
                            if (cols[j].querySelector('.badge')) {
                                text = cols[j].querySelector('.badge').textContent.trim();
                            }
                            
                            // Replace commas to avoid CSV issues
                            text = text.replace(/,/g, ' ');
                            
                            // Wrap in quotes
                            row.push('"' + text + '"');
                        }
                        
                        csv.push(row.join(','));
                    }
                    
                    // Download CSV file
                    const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
                    const encodedUri = encodeURI(csvContent);
                    const link = document.createElement('a');
                    link.setAttribute('href', encodedUri);
                    link.setAttribute('download', filename);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
                
                // Function to print table
                function printTable() {
                    const tableContainer = document.querySelector('.card');
                    const table = document.getElementById('usersTable');
                    if (!table) return;
                    
                    // Create a printable version
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <html>
                        <head>
                            <title>User List - Barangay Clearance and Document Request System</title>
                            <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
                            <style>
                                @media print {
                                    body {
                                        padding: 20px;
                                    }
                                    .no-print {
                                        display: none;
                                    }
                                    table {
                                        width: 100%;
                                        border-collapse: collapse;
                                    }
                                    th, td {
                                        padding: 8px;
                                        border: 1px solid #ddd;
                                    }
                                    .badge {
                                        font-weight: bold;
                                    }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="container mb-4">
                                <h1 class="text-center mb-4">User List</h1>
                                <p class="text-center text-muted">Generated on ${new Date().toLocaleDateString()}</p>
                                <hr>
                                <div class="table-responsive">
                                    ${table.outerHTML.replace(/<th>.*?<\/th>/, '<th class="no-print">Actions</th>')}
                                </div>
                            </div>
                            <script>
                                window.onload = function() {
                                    // Remove checkbox column and actions column
                                    const table = document.querySelector('table');
                                    const rows = table.querySelectorAll('tr');
                                    rows.forEach(row => {
                                        const cells = row.querySelectorAll('td, th');
                                        if (cells.length) {
                                            cells[0].style.display = 'none'; // Hide checkbox column
                                            if (cells[cells.length - 1].classList.contains('no-print')) {
                                                cells[cells.length - 1].style.display = 'none'; // Hide actions column
                                            }
                                        }
                                    });
                                    window.print();
                                    // Close after printing
                                    window.addEventListener('afterprint', function() {
                                        window.close();
                                    });
                                };
                            </script>
                        </body>
                        </html>
                    `);
                    
                    printWindow.document.close();
                }
    </script>
</body>
</html>
    