
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "../../backend/connections/config.php";

// Initialize variables
$showAlert = false;
$alertType = "";
$alertMessage = "";
$payments = [];
$totalPayments = 0;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$paymentMethod = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'all';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $showAlert = true;
    $alertType = "danger";
    $alertMessage = "Database connection failed: " . $conn->connect_error;
} else {
    
    // Build query based on filters - FIXED JOIN with LEFT JOIN
    $baseQuery = "SELECT p.*, 
                 r.request_id, 
                 r.document_type,
                 r.processing_fee,
                 u.first_name, 
                 u.last_name, 
                 u.email,
                 DATE_FORMAT(p.created_at, '%M %d, %Y %h:%i %p') as payment_date
                 FROM payment_proofs p
                 JOIN requests r ON p.request_id = r.request_id
                 LEFT JOIN users u ON p.user_id = u.user_id";
    
    $countQuery = "SELECT COUNT(*) as total 
                  FROM payment_proofs p
                  JOIN requests r ON p.request_id = r.request_id
                  LEFT JOIN users u ON p.user_id = u.user_id";
    
    $whereConditions = [];
    $queryParams = [];
    $paramTypes = "";
    
    // Filter by payment method
    if ($paymentMethod !== 'all') {
        $whereConditions[] = "p.payment_method = ?";
        $queryParams[] = $paymentMethod;
        $paramTypes .= "s";
    }
    
    // Filter by status
    if ($status !== 'all') {
        $whereConditions[] = "p.status = ?";
        $queryParams[] = $status;
        $paramTypes .= "s";
    }
    
    // Filter by date range - FIXED date formatting
    if (!empty($dateFrom)) {
        $whereConditions[] = "DATE(p.created_at) >= ?";
        $queryParams[] = $dateFrom;
        $paramTypes .= "s";
    }
    
    if (!empty($dateTo)) {
        $whereConditions[] = "DATE(p.created_at) <= ?";
        $queryParams[] = $dateTo;
        $paramTypes .= "s";
    }
    
    // Search functionality - FIXED to include fallback options
    if (!empty($searchTerm)) {
        // Modified to add COALESCE to handle NULL values in user names
        $whereConditions[] = "(COALESCE(u.first_name, '') LIKE ? OR COALESCE(u.last_name, '') LIKE ? OR p.payment_reference LIKE ? OR CAST(r.request_id AS CHAR) LIKE ?)";
        $searchPattern = "%$searchTerm%";
        $queryParams[] = $searchPattern;
        $queryParams[] = $searchPattern;
        $queryParams[] = $searchPattern;
        $queryParams[] = $searchPattern;
        $paramTypes .= "ssss";
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
        $totalPayments = $row['total'];
        $stmt->close();
    } else {
        $countResult = $conn->query($countQuery);
        $row = $countResult->fetch_assoc();
        $totalPayments = $row['total'];
    }
    
    // Calculate pagination
    $totalPages = ceil($totalPayments / $itemsPerPage);
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Get payments with pagination
    $baseQuery .= " ORDER BY p.created_at DESC LIMIT ?, ?";
    
    // Copy the original parameters and types before adding pagination
    $paginatedParams = $queryParams;
    $paginatedTypes = $paramTypes;
    
    // Add pagination parameters
    $paginatedParams[] = $offset;
    $paginatedParams[] = $itemsPerPage;
    
    // Make sure paginatedTypes isn't empty before binding
    if (empty($paginatedTypes)) {
        $paginatedTypes = "ii";  
    } else {
        $paginatedTypes .= "ii"; 
    }
    
    $stmt = $conn->prepare($baseQuery);
    $stmt->bind_param($paginatedTypes, ...$paginatedParams);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    $stmt->close();
    
    // Calculate summary statistics - FIXED to match the JOIN and WHERE clauses
    $totalAmount = 0;
    $verifiedAmount = 0;
    $pendingAmount = 0;
    
    $statsSql = "SELECT 
                SUM(r.processing_fee) as total_amount,
                SUM(CASE WHEN p.status = 'verified' THEN r.processing_fee ELSE 0 END) as verified_amount,
                SUM(CASE WHEN p.status = 'submitted' THEN r.processing_fee ELSE 0 END) as pending_amount
                FROM payment_proofs p
                JOIN requests r ON p.request_id = r.request_id
                LEFT JOIN users u ON p.user_id = u.user_id";
    
    // Add same where clause for stats
    $statsSql .= $whereClause;
    
    if (!empty($queryParams) && !empty($paramTypes)) {
        $statsStmt = $conn->prepare($statsSql);
        $statsStmt->bind_param($paramTypes, ...$queryParams);
        $statsStmt->execute();
        $statsResult = $statsStmt->get_result();
        $statsRow = $statsResult->fetch_assoc();
        
        $totalAmount = $statsRow['total_amount'] ?? 0;
        $verifiedAmount = $statsRow['verified_amount'] ?? 0;
        $pendingAmount = $statsRow['pending_amount'] ?? 0;
        
        $statsStmt->close();
    } else {
        $statsResult = $conn->query($statsSql);
        $statsRow = $statsResult->fetch_assoc();
        
        $totalAmount = $statsRow['total_amount'] ?? 0;
        $verifiedAmount = $statsRow['verified_amount'] ?? 0;
        $pendingAmount = $statsRow['pending_amount'] ?? 0;
    }
    
    
    // Bulk action - verify payments
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_action']) && isset($_POST['selected_payments'])) {
        $action = $_POST['bulk_action'];
        $selectedPayments = $_POST['selected_payments'];
        
        if (!empty($selectedPayments) && ($action === 'verify' || $action === 'reject')) {
            // Prepare IDs for SQL
            $proofIds = array_map('intval', $selectedPayments);
            $placeholders = str_repeat('?,', count($proofIds) - 1) . '?';
            
            // Get admin ID for verification
            $adminId = $_SESSION['user_id'] ?? 1; // Default to 1 if not set
            
            if ($action === 'verify') {
                // Update payment status to verified
                $updateSql = "UPDATE payment_proofs 
                             SET status = 'verified', 
                                 verified_at = NOW(),
                                 verified_by = ?,
                                 remarks = 'Verified by admin'
                             WHERE proof_id IN ($placeholders)";
                
                $stmt = $conn->prepare($updateSql);
                
                $types = 'i' . str_repeat('i', count($proofIds));
                $params = array_merge([$adminId], $proofIds);
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    // Also update the payment_status in requests table
                    $updateRequestsSql = "UPDATE requests 
                                         SET payment_status = 1, 
                                             updated_at = NOW() 
                                         WHERE request_id IN (
                                             SELECT request_id FROM payment_proofs WHERE proof_id IN ($placeholders)
                                         )";
                    
                    $stmtRequests = $conn->prepare($updateRequestsSql);
                    $stmtRequests->bind_param(str_repeat('i', count($proofIds)), ...$proofIds);
                    $stmtRequests->execute();
                    $stmtRequests->close();
                    
                    $showAlert = true;
                    $alertType = "success";
                    $alertMessage = count($proofIds) . " payment(s) have been successfully verified.";
                } else {
                    $showAlert = true;
                    $alertType = "danger";
                    $alertMessage = "Error verifying payments: " . $stmt->error;
                }
                $stmt->close();
            } elseif ($action === 'reject') {
                // Update payment status to rejected
                $updateSql = "UPDATE payment_proofs 
                             SET status = 'rejected', 
                                 verified_at = NOW(),
                                 verified_by = ?,
                                 remarks = 'Rejected by admin'
                             WHERE proof_id IN ($placeholders)";
                
                $stmt = $conn->prepare($updateSql);
                
                $types = 'i' . str_repeat('i', count($proofIds));
                $params = array_merge([$adminId], $proofIds);
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    // Update the payment_status in requests table to 0 (not paid)
                    $updateRequestsSql = "UPDATE requests 
                                         SET payment_status = 0, 
                                             updated_at = NOW() 
                                         WHERE request_id IN (
                                             SELECT request_id FROM payment_proofs WHERE proof_id IN ($placeholders)
                                         )";
                    
                    $stmtRequests = $conn->prepare($updateRequestsSql);
                    $stmtRequests->bind_param(str_repeat('i', count($proofIds)), ...$proofIds);
                    $stmtRequests->execute();
                    $stmtRequests->close();
                    
                    $showAlert = true;
                    $alertType = "success";
                    $alertMessage = count($proofIds) . " payment(s) have been rejected.";
                } else {
                    $showAlert = true;
                    $alertType = "danger";
                    $alertMessage = "Error rejecting payments: " . $stmt->error;
                }
                $stmt->close();
            }
            
            // Refresh the page to show updated data
            if ($alertType === "success") {
                // Store alert in session
                $_SESSION['alert_type'] = $alertType;
                $_SESSION['alert_message'] = $alertMessage;
                
                // Redirect to same page with same filters
                $redirectUrl = "manage-payments.php?";
                if ($paymentMethod !== 'all') {
                    $redirectUrl .= "payment_method=$paymentMethod&";
                }
                if ($status !== 'all') {
                    $redirectUrl .= "status=$status&";
                }
                if (!empty($searchTerm)) {
                    $redirectUrl .= "search=" . urlencode($searchTerm) . "&";
                }
                if (!empty($dateFrom)) {
                    $redirectUrl .= "date_from=$dateFrom&";
                }
                if (!empty($dateTo)) {
                    $redirectUrl .= "date_to=$dateTo&";
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
            $alertMessage = "Please select payments and an action to perform.";
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

$paymentMethods = [
    'gcash' => 'GCash',
    'paymaya' => 'PayMaya',
    'bank_transfer' => 'Bank Transfer',
    'cash' => 'Cash on Pickup'
];

$statusColors = [
    'submitted' => 'bg-warning text-dark',
    'verified' => 'bg-success',
    'rejected' => 'bg-danger'
];

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
    <title>Manage Payments - Barangay Clearance and Document Request System</title>
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
        
        .summary-card {
            transition: all 0.3s;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .proof-image-thumbnail {
            max-width: 50px;
            max-height: 50px;
            cursor: pointer;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: transform 0.2s;
        }
        
        .proof-image-thumbnail:hover {
            transform: scale(1.1);
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
            <a href="manage-users.php" class="d-block text-decoration-none text-white sidebar-item">
                <i class="bi bi-people me-2"></i> Manage Users
            </a>
            <a href="manage-payments.php" class="d-block text-decoration-none text-white sidebar-item active">
                <i class="bi bi-cash-coin me-2"></i> Manage Payments
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
                            <li class="breadcrumb-item active" aria-current="page">Manage Payments</li>
                        </ol>
                    </nav>
                    <h1 class="h2 mb-2">Manage Payments</h1>
                    <p class="lead">View, verify, and track all payments in the system.</p>
                    
                    <?php if ($showAlert): ?>
                        <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $alertMessage; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card summary-card bg-primary text-white shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Total Payments</h6>
                                    <h3 class="mb-0">₱<?php echo number_format($totalAmount, 2); ?></h3>
                                    <p class="card-text mb-0"><?php echo $totalPayments; ?> transaction(s)</p>
                                </div>
                                <div>
                                    <i class="bi bi-cash-coin" style="font-size: 3rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card summary-card bg-success text-white shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Verified Payments</h6>
                                    <h3 class="mb-0">₱<?php echo number_format($verifiedAmount, 2); ?></h3>
                                    <p class="card-text mb-0">
                                        <?php 
                                        echo ($totalAmount > 0) 
                                            ? round(($verifiedAmount / $totalAmount) * 100) . '% of total' 
                                            : '0% of total'; 
                                        ?>
                                    </p>
                                </div>
                                <div>
                                    <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card summary-card bg-warning text-dark shadow">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Pending Verification</h6>
                                    <h3 class="mb-0">₱<?php echo number_format($pendingAmount, 2); ?></h3>
                                    <p class="card-text mb-0">
                                        <?php 
                                        echo ($totalAmount > 0) 
                                            ? round(($pendingAmount / $totalAmount) * 100) . '% of total' 
                                            : '0% of total'; 
                                        ?>
                                    </p>
                                </div>
                                <div>
                                    <i class="bi bi-hourglass-split" style="font-size: 3rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters and Search -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form action="manage-payments.php" method="get" id="filterForm">
                                <div class="row g-3">
                                    <!-- Payment Method Filter -->
                                    <div class="col-md-3">
                                        <label class="form-label">Payment Method</label>
                                        <select id="paymentMethodFilter" name="payment_method" class="form-select" onchange="this.form.submit()">
                                            <option value="all" <?php echo $paymentMethod == 'all' ? 'selected' : ''; ?>>All Methods</option>
                                            <?php foreach ($paymentMethods as $key => $value): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $paymentMethod == $key ? 'selected' : ''; ?>><?php echo $value; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Status Filter -->
                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select id="statusFilter" name="status" class="form-select" onchange="this.form.submit()">
                                            <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Status</option>
                                            <option value="submitted" <?php echo $status == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                            <option value="verified" <?php echo $status == 'verified' ? 'selected' : ''; ?>>Verified</option>
                                            <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Date Range Filter -->
                                    <div class="col-md-4">
                                        <label class="form-label">Date Range</label>
                                        <div class="input-group">
                                            <input type="date" id="dateFrom" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                                            <span class="input-group-text">to</span>
                                            <input type="date" id="dateTo" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                                        </div>
                                    </div>
                                    
                                    <!-- Search -->
                                    <div class="col-md-3">
                                        <label class="form-label">Search</label>
                                        <div class="input-group">
                                            <input type="text" id="searchInput" name="search" class="form-control" placeholder="Ref # or Name" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Reset Button -->
                                    <div class="col-md-12 d-flex justify-content-end">
                                        <a href="manage-payments.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset Filters
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment List -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-cash-coin me-2"></i>
                                    Payment Transactions
                                    <span class="badge bg-secondary ms-2"><?php echo $totalPayments; ?> records</span>
                                </h5>
                                <div>
                                    <?php if ($totalPayments > 0): ?>
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
                            <?php if (empty($payments)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-cash-coin text-secondary" style="font-size: 3rem;"></i>
                                    <p class="mt-3 text-muted">No payment records found with the selected filters.</p>
                                    <?php if (!empty($searchTerm) || $paymentMethod != 'all' || $status != 'all' || !empty($dateFrom) || !empty($dateTo)): ?>
                                        <a href="manage-payments.php" class="btn btn-outline-primary">Clear Filters</a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <form id="bulkActionForm" method="post">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="paymentsTable">
                                            <thead>
                                                <tr>
                                                    <th>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="selectAll">
                                                        </div>
                                                    </th>
                                                    <th>ID</th>
                                                    <th>Date</th>
                                                    <th>Name</th>
                                                    <th>Document</th>
                                                    <th>Method</th>
                                                    <th>Reference #</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Proof</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($payments as $payment): ?>
                                                <tr>
                                                    <td>
                                                        <div class="form-check">
                                                            <input class="form-check-input payment-checkbox" type="checkbox" name="selected_payments[]" value="<?php echo $payment['proof_id']; ?>">
                                                        </div>
                                                    </td>
                                                    <td><?php echo $payment['proof_id']; ?></td>
                                                    <td><?php echo $payment['payment_date']; ?></td>
                                                    <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                                    <td><?php echo $documentTypes[$payment['document_type']] ?? ucfirst(str_replace('_', ' ', $payment['document_type'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo ucfirst($payment['payment_method']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($payment['payment_reference']); ?></td>
                                                    <td>₱<?php echo number_format($payment['processing_fee'], 2); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $statusColors[$payment['status']] ?? 'bg-secondary'; ?> status-badge">
                                                            <?php echo ucfirst($payment['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($payment['proof_image']) && $payment['payment_method'] != 'cash'): ?>
                                                        <img src="../../User/view/<?php echo $payment['proof_image']; ?>" class="proof-image-thumbnail" 
                                                             data-bs-toggle="modal" data-bs-target="#proofImageModal"
                                                             data-proof-image="../../User/view/<?php echo $payment['proof_image']; ?>"
                                                             data-reference="<?php echo htmlspecialchars($payment['payment_reference']); ?>"
                                                             alt="Payment Proof">
                                                        <?php else: ?>
                                                        <span class="badge bg-secondary">No Image</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="request-details.php?id=<?php echo $payment['request_id']; ?>" class="btn btn-outline-primary">
                                                                <i class="bi bi-eye"></i> View
                                                            </a>
                                                            <?php if ($payment['status'] === 'submitted'): ?>
                                                            <button type="button" class="btn btn-outline-success payment-verify-btn" data-payment-id="<?php echo $payment['proof_id']; ?>">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger payment-reject-btn" data-payment-id="<?php echo $payment['proof_id']; ?>">
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
                                                    <option value="">Bulk Action</option>
                                                    <option value="verify">Verify Selected</option>
                                                    <option value="reject">Reject Selected</option>
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
                                                            <a class="page-link" href="<?php echo $currentPage > 1 ? '?page=' . ($currentPage - 1) . ($paymentMethod != 'all' ? '&payment_method=' . $paymentMethod : '') . ($status != 'all' ? '&status=' . $status : '') . (!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '') . (!empty($dateFrom) ? '&date_from=' . $dateFrom : '') . (!empty($dateTo) ? '&date_to=' . $dateTo : '') : '#'; ?>">Previous</a>
                                                        </li>
                                                        
                                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                            <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $paymentMethod != 'all' ? '&payment_method=' . $paymentMethod : ''; ?><?php echo $status != 'all' ? '&status=' . $status : ''; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . $dateFrom : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . $dateTo : ''; ?>"><?php echo $i; ?></a>
                                                            </li>
                                                        <?php endfor; ?>
                                                        
                                                        <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="<?php echo $currentPage < $totalPages ? '?page=' . ($currentPage + 1) . ($paymentMethod != 'all' ? '&payment_method=' . $paymentMethod : '') . ($status != 'all' ? '&status=' . $status : '') . (!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '') . (!empty($dateFrom) ? '&date_from=' . $dateFrom : '') . (!empty($dateTo) ? '&date_to=' . $dateTo : '') : '#'; ?>">Next</a>
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

    <!-- Payment Proof Image Modal -->
    <div class="modal fade" id="proofImageModal" tabindex="-1" aria-labelledby="proofImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="proofImageModalLabel">
                        <i class="bi bi-image me-2"></i>Payment Proof
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <span class="badge bg-primary" id="modalPaymentReference"></span>
                    </div>
                    <img src="" class="img-fluid" id="modalProofImage" style="max-height: 500px;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary" id="downloadProofBtn" download>
                        <i class="bi bi-download me-2"></i>Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Verify Payment Modal -->
    <div class="modal fade" id="verifyPaymentModal" tabindex="-1" aria-labelledby="verifyPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="verifyPaymentForm">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="verifyPaymentModalLabel">Verify Payment</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to verify this payment? This will mark the payment as confirmed and update the request as paid.</p>
                        <input type="hidden" name="selected_payments[]" id="verifyPaymentId">
                        <input type="hidden" name="bulk_action" value="verify">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-2"></i>Verify Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Payment Modal -->
    <div class="modal fade" id="rejectPaymentModal" tabindex="-1" aria-labelledby="rejectPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="rejectPaymentForm">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="rejectPaymentModalLabel">Reject Payment</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to reject this payment? This will mark the payment as rejected and the request will remain unpaid.</p>
                        <input type="hidden" name="selected_payments[]" id="rejectPaymentId">
                        <input type="hidden" name="bulk_action" value="reject">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-lg me-2"></i>Reject Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select All Checkbox
            const selectAllCheckbox = document.getElementById('selectAll');
            const paymentCheckboxes = document.querySelectorAll('.payment-checkbox');
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    paymentCheckboxes.forEach(checkbox => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                });
            }
            
            // Bulk Action Form
            const bulkActionForm = document.getElementById('bulkActionForm');
            const bulkActionSelect = document.getElementById('bulkActionSelect');
            const applyBulkAction = document.getElementById('applyBulkAction');
            
            if (bulkActionForm && applyBulkAction) {
                applyBulkAction.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Check if any payments are selected
                    const selectedPayments = document.querySelectorAll('.payment-checkbox:checked');
                    if (selectedPayments.length === 0) {
                        alert('Please select at least one payment.');
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
                    if (action === 'verify') {
                        confirmMessage = 'Are you sure you want to verify ' + selectedPayments.length + ' payment(s)?';
                    } else if (action === 'reject') {
                        confirmMessage = 'Are you sure you want to reject ' + selectedPayments.length + ' payment(s)?';
                    }
                    
                    if (confirm(confirmMessage)) {
                        bulkActionForm.submit();
                    }
                });
            }
            
            // Payment Image Modal
            const proofImageModal = document.getElementById('proofImageModal');
            if (proofImageModal) {
                proofImageModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const proofImage = button.getAttribute('data-proof-image');
                    const reference = button.getAttribute('data-reference');
                    
                    const modalImage = document.getElementById('modalProofImage');
                    const modalReference = document.getElementById('modalPaymentReference');
                    const downloadBtn = document.getElementById('downloadProofBtn');
                    
                    if (modalImage) modalImage.src = proofImage;
                    if (modalReference) modalReference.textContent = 'Reference #: ' + reference;
                    if (downloadBtn) downloadBtn.href = proofImage;
                });
            }
            
            // Verify/Reject Payment buttons
            const verifyButtons = document.querySelectorAll('.payment-verify-btn');
            const rejectButtons = document.querySelectorAll('.payment-reject-btn');
            const verifyPaymentModal = new bootstrap.Modal(document.getElementById('verifyPaymentModal'));
            const rejectPaymentModal = new bootstrap.Modal(document.getElementById('rejectPaymentModal'));
            const verifyPaymentId = document.getElementById('verifyPaymentId');
            const rejectPaymentId = document.getElementById('rejectPaymentId');
            
            verifyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const paymentId = this.getAttribute('data-payment-id');
                    verifyPaymentId.value = paymentId;
                    verifyPaymentModal.show();
                });
            });
            
            rejectButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const paymentId = this.getAttribute('data-payment-id');
                    rejectPaymentId.value = paymentId;
                    rejectPaymentModal.show();
                });
            });
            
            // Date filter validation
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            
            if (dateFrom && dateTo) {
                dateFrom.addEventListener('change', function() {
                    if (dateTo.value && dateFrom.value > dateTo.value) {
                        alert('Start date cannot be after end date');
                        dateFrom.value = dateTo.value;
                    }
                });
                
                dateTo.addEventListener('change', function() {
                    if (dateFrom.value && dateTo.value < dateFrom.value) {
                        alert('End date cannot be before start date');
                        dateTo.value = dateFrom.value;
                    }
                });
            }
            
            // Export to CSV
            const exportCSVBtn = document.getElementById('exportCSVBtn');
            if (exportCSVBtn) {
                exportCSVBtn.addEventListener('click', function() {
                    exportTableToCSV('payments_<?php echo date('Y-m-d'); ?>.csv');
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
                const table = document.getElementById('paymentsTable');
                if (!table) return;
                
                const rows = table.querySelectorAll('tr');
                const csv = [];
                
                for (let i = 0; i < rows.length; i++) {
                    const row = [], cols = rows[i].querySelectorAll('td, th');
                    
                    for (let j = 1; j < cols.length - 2; j++) {
                        // Skip checkbox column, proof image column and actions column
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
        });
    </script>
</body>
</html>