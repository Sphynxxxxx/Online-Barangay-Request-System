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
$requests = [];
$requestStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$totalRequests = 0;

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $showAlert = true;
    $alertType = "danger";
    $alertMessage = "Database connection failed: " . $conn->connect_error;
} else {
    // Handle request status updates
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['request_id'])) {
        $requestId = intval($_POST['request_id']);
        $action = $_POST['action'];
        $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
        $adminNotes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';
        
        // Update request status based on action
        if ($action == 'approve') {
            $status = 'processing';
            $actionText = 'approved for processing';
        } else if ($action == 'ready') {
            $status = 'ready';
            $actionText = 'marked as ready for pickup';
        } else if ($action == 'complete') {
            $status = 'completed';
            $actionText = 'marked as completed';
        } else if ($action == 'reject') {
            $status = 'rejected';
            $actionText = 'rejected';
        } else if ($action == 'cancel') {
            $status = 'cancelled';
            $actionText = 'cancelled';
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "Invalid action.";
            goto SkipAction; // Skip further processing
        }
        
        // Prepare and execute update statement
        $stmt = $conn->prepare("UPDATE requests SET status = ?, admin_notes = ?, updated_at = NOW() WHERE request_id = ?");
        $stmt->bind_param("ssi", $status, $notes, $requestId);
        
        if ($stmt->execute()) {
            $showAlert = true;
            $alertType = "success";
            $alertMessage = "Request #$requestId has been successfully $actionText.";
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "Error updating request: " . $stmt->error;
        }
        
        // Close statement
        $stmt->close();
    }
    
    SkipAction:
    
    // Build the query based on filter and search
    $baseQuery = "SELECT r.*, u.first_name, u.last_name, u.email 
                  FROM requests r
                  JOIN users u ON r.user_id = u.user_id";
    
    $whereConditions = [];
    $queryParams = [];
    $paramTypes = "";
    
    // Filter by status
    if ($requestStatus != 'all') {
        $whereConditions[] = "r.status = ?";
        $queryParams[] = $requestStatus;
        $paramTypes .= "s";
    }
    
    // Search functionality
    if (!empty($searchTerm)) {
        $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR r.document_type LIKE ? OR r.request_id LIKE ?)";
        $searchPattern = "%$searchTerm%";
        $queryParams[] = $searchPattern;
        $queryParams[] = $searchPattern;
        $queryParams[] = $searchPattern;
        $queryParams[] = $searchPattern;
        $paramTypes .= "ssss";
    }
    
    // Combine where conditions
    $whereClause = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";
    
    // Count total matching records for pagination
    $countQuery = "SELECT COUNT(*) as total FROM requests r JOIN users u ON r.user_id = u.user_id" . $whereClause;
    
    if (!empty($queryParams)) {
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param($paramTypes, ...$queryParams);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $row = $countResult->fetch_assoc();
        $totalRequests = $row['total'];
        $countStmt->close();
    } else {
        $countResult = $conn->query($countQuery);
        $row = $countResult->fetch_assoc();
        $totalRequests = $row['total'];
    }
    
    // Calculate pagination
    $totalPages = ceil($totalRequests / $itemsPerPage);
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Get request data with pagination
    $query = $baseQuery . $whereClause . " ORDER BY r.created_at DESC LIMIT ?, ?";
    $queryParams[] = $offset;
    $queryParams[] = $itemsPerPage;
    $paramTypes .= "ii";
    
    $stmt = $conn->prepare($query);
    if (!empty($queryParams)) {
        $stmt->bind_param($paramTypes, ...$queryParams);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    $stmt->close();
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

// Helper function to format document types for display
function formatDocumentType($type) {
    return ucwords(str_replace('_', ' ', $type));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Requests - Barangay Clearance and Document Request System</title>
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
            <a href="document-requests.php" class="d-block text-decoration-none text-white sidebar-item active">
                <i class="bi bi-file-earmark-text me-2"></i> Document Requests
            </a>
            <a href="../../manage-users.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-people me-2"></i> Manage Users
            </a>
            <a href="../../reports.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-graph-up me-2"></i> Reports
            </a>
            <a href="../../system-logs.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-journal-text me-2"></i> System Logs
            </a>
            <a href="../../settings.php" class="d-block text-decoration-none text-white sidebar-item">
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
                            <li class="breadcrumb-item"><a href="../../admin.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Document Requests</li>
                        </ol>
                    </nav>
                    <h1 class="h2 mb-2">Document Requests</h1>
                    <p class="lead">Manage and process document requests from residents.</p>
                    
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
                            <div class="row g-3">
                                <!-- Status Filter -->
                                <div class="col-md-4">
                                    <label class="form-label">Filter by Status</label>
                                    <select id="statusFilter" class="form-select" onchange="applyFilters()">
                                        <option value="all" <?php echo $requestStatus == 'all' ? 'selected' : ''; ?>>All Requests</option>
                                        <option value="pending" <?php echo $requestStatus == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $requestStatus == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="ready" <?php echo $requestStatus == 'ready' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                        <option value="completed" <?php echo $requestStatus == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="rejected" <?php echo $requestStatus == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        <option value="cancelled" <?php echo $requestStatus == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                
                                <!-- Search -->
                                <div class="col-md-6">
                                    <label class="form-label">Search</label>
                                    <div class="input-group">
                                        <input type="text" id="searchInput" class="form-control" placeholder="Search by name, document type, or request ID" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                        <button class="btn btn-primary" type="button" onclick="applyFilters()">
                                            <i class="bi bi-search"></i> Search
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Reset Button -->
                                <div class="col-md-2 d-flex align-items-end">
                                    <button class="btn btn-outline-secondary w-100" type="button" onclick="resetFilters()">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Request List -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    <?php 
                                    if ($requestStatus == 'pending') echo 'Pending';
                                    elseif ($requestStatus == 'processing') echo 'Processing';
                                    elseif ($requestStatus == 'ready') echo 'Ready for Pickup';
                                    elseif ($requestStatus == 'completed') echo 'Completed';
                                    elseif ($requestStatus == 'rejected') echo 'Rejected';
                                    elseif ($requestStatus == 'cancelled') echo 'Cancelled';
                                    else echo 'All';
                                    ?> Document Requests
                                    <span class="badge bg-secondary ms-2"><?php echo $totalRequests; ?></span>
                                </h5>
                                <div>
                                    <?php if ($totalRequests > 0): ?>
                                    <button class="btn btn-sm btn-outline-success me-2" onclick="exportToCSV()">
                                        <i class="bi bi-file-earmark-excel"></i> Export to CSV
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="printTable()">
                                        <i class="bi bi-printer"></i> Print
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($requests)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-file-earmark-text text-secondary" style="font-size: 3rem;"></i>
                                    <p class="mt-3 text-muted">No document requests found with the selected filters.</p>
                                    <?php if (!empty($searchTerm) || $requestStatus != 'all'): ?>
                                        <button class="btn btn-outline-primary" onclick="resetFilters()">Clear Filters</button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="requestsTable">
                                        <thead>
                                            <tr>
                                                <th>Request ID</th>
                                                <th>Document Type</th>
                                                <th>Requestor</th>
                                                <th>Contact</th>
                                                <th>Date Requested</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requests as $request): ?>
                                                <tr>
                                                    <td>#<?php echo $request['request_id']; ?></td>
                                                    <td><?php echo formatDocumentType($request['document_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($request['email']); ?></td>
                                                    <td><?php echo date('M d, Y g:i A', strtotime($request['created_at'])); ?></td>
                                                    <td>
                                                        <?php if ($request['status'] == 'pending'): ?>
                                                            <span class="badge bg-warning text-dark status-badge">Pending</span>
                                                        <?php elseif ($request['status'] == 'processing'): ?>
                                                            <span class="badge bg-info text-white status-badge">Processing</span>
                                                        <?php elseif ($request['status'] == 'ready'): ?>
                                                            <span class="badge bg-primary status-badge">Ready for Pickup</span>
                                                        <?php elseif ($request['status'] == 'completed'): ?>
                                                            <span class="badge bg-success status-badge">Completed</span>
                                                        <?php elseif ($request['status'] == 'rejected'): ?>
                                                            <span class="badge bg-danger status-badge">Rejected</span>
                                                        <?php elseif ($request['status'] == 'cancelled'): ?>
                                                            <span class="badge bg-secondary status-badge">Cancelled</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="request-details.php?id=<?php echo $request['request_id']; ?>" class="btn btn-outline-primary">
                                                                <i class="bi bi-eye"></i> View
                                                            </a>
                                                            
                                                            <?php if ($request['status'] == 'pending'): ?>
                                                                <button type="button" class="btn btn-outline-success" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#actionModal" 
                                                                        data-request-id="<?php echo $request['request_id']; ?>"
                                                                        data-action="approve"
                                                                        data-title="Approve Request">
                                                                    <i class="bi bi-check-lg"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#actionModal" 
                                                                        data-request-id="<?php echo $request['request_id']; ?>"
                                                                        data-action="reject"
                                                                        data-title="Reject Request">
                                                                    <i class="bi bi-x-lg"></i>
                                                                </button>
                                                            <?php elseif ($request['status'] == 'processing'): ?>
                                                                <button type="button" class="btn btn-outline-primary" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#actionModal" 
                                                                        data-request-id="<?php echo $request['request_id']; ?>"
                                                                        data-action="ready"
                                                                        data-title="Mark as Ready for Pickup">
                                                                    <i class="bi bi-box-seam"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-success" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#actionModal" 
                                                                        data-request-id="<?php echo $request['request_id']; ?>"
                                                                        data-action="complete"
                                                                        data-title="Mark as Completed">
                                                                    <i class="bi bi-check-all"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-secondary" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#actionModal" 
                                                                        data-request-id="<?php echo $request['request_id']; ?>"
                                                                        data-action="cancel"
                                                                        data-title="Cancel Request">
                                                                    <i class="bi bi-x-circle"></i>
                                                                </button>
                                                            <?php elseif ($request['status'] == 'ready'): ?>
                                                                <button type="button" class="btn btn-outline-success" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#actionModal" 
                                                                        data-request-id="<?php echo $request['request_id']; ?>"
                                                                        data-action="complete"
                                                                        data-title="Mark as Completed">
                                                                    <i class="bi bi-check-all"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center mt-4">
                                            <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="javascript:void(0);" onclick="changePage(<?php echo $currentPage - 1; ?>)">Previous</a>
                                            </li>
                                            
                                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                                    <a class="page-link" href="javascript:void(0);" onclick="changePage(<?php echo $i; ?>)"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="javascript:void(0);" onclick="changePage(<?php echo $currentPage + 1; ?>)">Next</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalLabel">Process Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="actionForm" method="post">
                    <div class="modal-body">
                        <input type="hidden" id="requestIdInput" name="request_id">
                        <input type="hidden" id="actionInput" name="action">
                        
                        <div class="mb-3">
                            <label for="notesInput" class="form-label">Notes</label>
                            <textarea class="form-control" id="notesInput" name="notes" rows="3" placeholder="Add any notes or comments about this action"></textarea>
                        </div>
                        
                        <div id="approveAlert" class="alert alert-info d-none">
                            <i class="bi bi-info-circle me-2"></i> 
                            This will move the request to the processing stage. You can mark it as ready for pickup or completed later.
                        </div>
                        
                        <div id="readyAlert" class="alert alert-primary d-none">
                            <i class="bi bi-box-seam me-2"></i> 
                            This will mark the document as ready for pickup by the resident.
                        </div>
                        
                        <div id="rejectAlert" class="alert alert-warning d-none">
                            <i class="bi bi-exclamation-triangle me-2"></i> 
                            This will reject the request. Please provide a reason in the notes.
                        </div>
                        
                        <div id="completeAlert" class="alert alert-success d-none">
                            <i class="bi bi-check-circle me-2"></i> 
                            This will mark the request as completed.
                        </div>
                        
                        <div id="cancelAlert" class="alert alert-secondary d-none">
                            <i class="bi bi-x-circle me-2"></i> 
                            This will cancel the request. Please provide a reason in the notes.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="confirmActionBtn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle modal data
        var actionModal = document.getElementById('actionModal');
        if (actionModal) {
            actionModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var requestId = button.getAttribute('data-request-id');
                var action = button.getAttribute('data-action');
                var title = button.getAttribute('data-title');
                
                var modalTitle = this.querySelector('.modal-title');
                var requestIdInput = document.getElementById('requestIdInput');
                var actionInput = document.getElementById('actionInput');
                var confirmBtn = document.getElementById('confirmActionBtn');
                
                // Reset all alerts
                document.getElementById('approveAlert').classList.add('d-none');
                document.getElementById('readyAlert').classList.add('d-none');
                document.getElementById('rejectAlert').classList.add('d-none');
                document.getElementById('completeAlert').classList.add('d-none');
                document.getElementById('cancelAlert').classList.add('d-none');
                
                modalTitle.textContent = title;
                requestIdInput.value = requestId;
                actionInput.value = action;
                
                // Show the appropriate alert
                if (action === 'approve') {
                    document.getElementById('approveAlert').classList.remove('d-none');
                    confirmBtn.classList.remove('btn-danger', 'btn-success', 'btn-secondary');
                    confirmBtn.classList.add('btn-primary');
                    confirmBtn.textContent = 'Approve';
                } else if (action === 'ready') {
                    document.getElementById('readyAlert').classList.remove('d-none');
                    confirmBtn.classList.remove('btn-danger', 'btn-success', 'btn-secondary');
                    confirmBtn.classList.add('btn-primary');
                    confirmBtn.textContent = 'Mark as Ready';
                } else if (action === 'reject') {
                    document.getElementById('rejectAlert').classList.remove('d-none');
                    confirmBtn.classList.remove('btn-primary', 'btn-success', 'btn-secondary');
                    confirmBtn.classList.add('btn-danger');
                    confirmBtn.textContent = 'Reject';
                } else if (action === 'complete') {
                    document.getElementById('completeAlert').classList.remove('d-none');
                    confirmBtn.classList.remove('btn-primary', 'btn-danger', 'btn-secondary');
                    confirmBtn.classList.add('btn-success');
                    confirmBtn.textContent = 'Complete';
                } else if (action === 'cancel') {
                    document.getElementById('cancelAlert').classList.remove('d-none');
                    confirmBtn.classList.remove('btn-primary', 'btn-danger', 'btn-success');
                    confirmBtn.classList.add('btn-secondary');
                    confirmBtn.textContent = 'Cancel Request';
                }
            });
        }
        
        // Apply filters
        function applyFilters() {
            var status = document.getElementById('statusFilter').value;
            var search = document.getElementById('searchInput').value.trim();
            
            var url = 'document-requests.php?';
            if (status !== 'all') {
                url += 'status=' + status + '&';
            }
            if (search !== '') {
                url += 'search=' + encodeURIComponent(search) + '&';
            }
            
            // Remove trailing & if exists
            if (url.endsWith('&')) {
                url = url.slice(0, -1);
            }
            
            window.location.href = url;
        }
        
        // Reset filters
        function resetFilters() {
            window.location.href = 'document-requests.php';
        }
        
        // Change page
        function changePage(page) {
            var status = '<?php echo $requestStatus; ?>';
            var search = '<?php echo htmlspecialchars($searchTerm); ?>';
            
            var url = 'document-requests.php?page=' + page;
            if (status !== 'all') {
                url += '&status=' + status;
            }
            if (search !== '') {
                url += '&search=' + encodeURIComponent(search);
            }
            
            window.location.href = url;
        }
        
        // Enter key triggers search
        document.getElementById('searchInput').addEventListener('keyup', function(event) {
            if (event.key === 'Enter') {
                applyFilters();
            }
        });
        
        // Export to CSV
        function exportToCSV() {
            // Get the table
            var table = document.getElementById('requestsTable');
            if (!table) return;
            
            // Create CSV content
            var csv = [];
            var rows = table.querySelectorAll('tr');
            
            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (var j = 0; j < cols.length - 1; j++) { // Skip the Actions column
                    // Get the text content and clean it
                    var text = cols[j].textContent.trim();
                    // Replace any commas in the text with spaces to avoid CSV issues
                    text = text.replace(/,/g, ' ');
                    // Add quotes around the text
                    row.push('"' + text + '"');
                }
                csv.push(row.join(','));
            }
            
            // Create and download the CSV file
            var csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
            var encodedUri = encodeURI(csvContent);
            var link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'document_requests_<?php echo date('Y-m-d'); ?>.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Print table
        function printTable() {
            var printContents = document.querySelector('.card').innerHTML;
            var originalContents = document.body.innerHTML;
            
            document.body.innerHTML = `
                <div style="padding: 20px;">
                    <h1 style="text-align: center; margin-bottom: 20px;">Document Requests Report</h1>
                    <p style="text-align: center; margin-bottom: 30px;">Generated on ${new Date().toLocaleDateString()}</p>
                    ${printContents}
                </div>
            `;
            
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        }
    </script>
</body>
</html>