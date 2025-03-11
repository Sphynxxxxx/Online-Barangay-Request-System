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
$requestDetails = null;

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $showAlert = true;
    $alertType = "danger";
    $alertMessage = "Database connection failed: " . $conn->connect_error;
} else {
    // Check if request ID is provided
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $showAlert = true;
        $alertType = "danger";
        $alertMessage = "Invalid request ID.";
    } else {
        $requestId = intval($_GET['id']);

        // Fetch detailed request information
        $requestSql = "SELECT r.*, 
                        u.first_name, 
                        u.last_name, 
                        u.email,
                        u.contact_number,
                        DATE_FORMAT(r.created_at, '%M %d, %Y %h:%i %p') as formatted_created_at,
                        DATE_FORMAT(r.updated_at, '%M %d, %Y %h:%i %p') as formatted_updated_at
                      FROM requests r
                      JOIN users u ON r.user_id = u.user_id
                      WHERE r.request_id = ?";
        
        $stmt = $conn->prepare($requestSql);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $requestDetails = $result->fetch_assoc();
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "Request not found.";
        }
        $stmt->close();
    }

    // Handle status update
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
        $newStatus = $_POST['status'];
        $adminRemarks = isset($_POST['admin_remarks']) ? trim($_POST['admin_remarks']) : '';

        // Debug information (uncomment if needed)
        // echo "<pre>";
        // echo "Status: " . $newStatus . "\n";
        // echo "Remarks: " . $adminRemarks . "\n";
        // echo "Request ID: " . $requestId . "\n";
        // echo "</pre>";

        $updateSql = "UPDATE requests 
                      SET status = ?, 
                          admin_remarks = ?, 
                          updated_at = NOW() 
                      WHERE request_id = ?";
        
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ssi", $newStatus, $adminRemarks, $requestId);
        
        if ($stmt->execute()) {
            $showAlert = true;
            $alertType = "success";
            $alertMessage = "Request status updated successfully to " . ucfirst($newStatus) . ".";
            
            // Refresh request details
            $stmt = $conn->prepare($requestSql);
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            $requestDetails = $result->fetch_assoc();
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "Error updating request status: " . $stmt->error . " (Error code: " . $stmt->errno . ")";
        }
        $stmt->close();
    }

    // Close connection
    $conn->close();
}

// Document type mapping
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

// Status colors
$statusColors = [
    'pending' => 'bg-warning text-dark',
    'processing' => 'bg-info text-white',
    'ready' => 'bg-primary',
    'completed' => 'bg-success',
    'rejected' => 'bg-danger',
    'cancelled' => 'bg-secondary'
];

// Status descriptions
$statusDescriptions = [
    'pending' => 'Request is awaiting initial review',
    'processing' => 'Request is currently being processed',
    'ready' => 'Document is ready for pickup',
    'completed' => 'Request has been completed and delivered',
    'rejected' => 'Request has been rejected',
    'cancelled' => 'Request has been cancelled'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Details - Barangay Clearance and Document Request System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f4f6f9;
        }
        
        .card-detail {
            transition: transform 0.2s;
        }
        
        .card-detail:hover {
            transform: translateY(-5px);
        }
        
        .status-badge {
            font-size: 0.9rem;
            padding: 0.4rem 0.75rem;
        }

        .status-timeline {
            position: relative;
            padding-left: 45px;
        }

        .status-timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 22px;
            width: 2px;
            background-color: #dee2e6;
        }

        .status-item {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .status-marker {
            position: absolute;
            left: -45px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: #fff;
            border: 2px solid #dee2e6;
            top: 5px;
        }

        .status-active .status-marker {
            width: 20px;
            height: 20px;
            left: -47px;
            top: 3px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>Request Details
                    </h1>
                    <a href="document-requests.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Requests
                    </a>
                </div>

                <?php if ($showAlert): ?>
                    <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $alertMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($requestDetails): ?>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card card-detail mb-4">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    Request #<?php echo $requestDetails['request_id']; ?> 
                                    - <?php echo $documentTypes[$requestDetails['document_type']] ?? $requestDetails['document_type']; ?>
                                </h5>
                                <span class="badge <?php echo $statusColors[$requestDetails['status']] ?? 'bg-secondary'; ?> status-badge">
                                    <?php echo ucfirst($requestDetails['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <strong>Purpose:</strong>
                                        <p><?php echo htmlspecialchars($requestDetails['purpose']); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Processing Fee:</strong>
                                        <p>₱<?php echo number_format($requestDetails['processing_fee'], 2); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Urgent Request:</strong>
                                        <p><?php echo $requestDetails['urgent_request'] ? 'Yes' : 'No'; ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <strong>Payment Status:</strong>
                                        <p>
                                            <?php 
                                            if (isset($requestDetails['payment_status']) && $requestDetails['payment_status'] == 1): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending Payment</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Status Timeline -->
                                <div class="mt-4">
                                    <h6 class="mb-3">Request Status Timeline</h6>
                                    <div class="status-timeline">
                                        <?php
                                        $statusOrder = ['pending', 'processing', 'ready', 'completed'];
                                        $currentStatusIndex = array_search($requestDetails['status'], $statusOrder);
                                        
                                        // Show different flow for rejected or cancelled
                                        if ($requestDetails['status'] == 'rejected' || $requestDetails['status'] == 'cancelled'):
                                        ?>
                                            <div class="status-item status-active">
                                                <div class="status-marker bg-warning"></div>
                                                <h6>Pending</h6>
                                                <p class="text-muted small">Request submitted and awaiting review</p>
                                            </div>
                                            <div class="status-item status-active">
                                                <div class="status-marker bg-<?php echo $requestDetails['status'] == 'rejected' ? 'danger' : 'secondary'; ?>"></div>
                                                <h6><?php echo ucfirst($requestDetails['status']); ?></h6>
                                                <p class="text-muted small">
                                                    <?php echo $statusDescriptions[$requestDetails['status']]; ?>
                                                    <br>
                                                    <span class="fst-italic">
                                                        <?php echo date('M d, Y g:i A', strtotime($requestDetails['updated_at'])); ?>
                                                    </span>
                                                </p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($statusOrder as $index => $status): ?>
                                                <div class="status-item <?php echo ($index <= $currentStatusIndex) ? 'status-active' : ''; ?>">
                                                    <div class="status-marker bg-<?php 
                                                        if ($index < $currentStatusIndex) {
                                                            echo 'success';
                                                        } elseif ($index == $currentStatusIndex) {
                                                            echo str_replace(['text-dark', 'text-white'], '', $statusColors[$status]);
                                                        } else {
                                                            echo 'light';
                                                        }
                                                    ?>"></div>
                                                    <h6><?php echo ucfirst($status); ?></h6>
                                                    <p class="text-muted small">
                                                        <?php echo $statusDescriptions[$status]; ?>
                                                        <?php if ($index == $currentStatusIndex): ?>
                                                            <br>
                                                            <span class="fst-italic">
                                                                <?php echo date('M d, Y g:i A', strtotime($requestDetails['updated_at'])); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Created At:</strong>
                                        <p><?php echo $requestDetails['formatted_created_at']; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Last Updated:</strong>
                                        <p><?php echo $requestDetails['formatted_updated_at']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($requestDetails['admin_remarks'])): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <i class="bi bi-info-circle me-2"></i>Admin Remarks
                            </div>
                            <div class="card-body">
                                <p><?php echo nl2br(htmlspecialchars($requestDetails['admin_remarks'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">
                                <i class="bi bi-person-circle me-2"></i>Requestor Information
                            </div>
                            <div class="card-body">
                                <h5><?php echo htmlspecialchars($requestDetails['first_name'] . ' ' . $requestDetails['last_name']); ?></h5>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($requestDetails['email']); ?></p>
                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($requestDetails['contact_number'] ?? 'N/A'); ?></p>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <i class="bi bi-pencil-square me-2"></i>Update Request Status
                            </div>
                            <div class="card-body">
                                <form method="POST" id="statusUpdateForm">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select name="status" id="status" class="form-select" required>
                                            <option value="pending" <?php echo $requestDetails['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="processing" <?php echo $requestDetails['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="completed" <?php echo $requestDetails['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="rejected" <?php echo $requestDetails['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="admin_remarks" class="form-label">Admin Remarks</label>
                                        <textarea name="admin_remarks" id="admin_remarks" class="form-control" rows="3" placeholder="Add notes about this status change"><?php echo htmlspecialchars($requestDetails['admin_remarks'] ?? ''); ?></textarea>
                                    </div>
                                    <input type="hidden" name="update_status" value="1">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary" id="updateStatusBtn">
                                            <i class="bi bi-save me-2"></i>Update Status
                                        </button>
                                        <?php if ($requestDetails['document_type'] === 'barangay_clearance'): ?>
                                        <a href="documents-edit/edit-brgyclearance.php?id=<?php echo $requestDetails['request_id']; ?>" class="btn btn-success">
                                            <i class="bi bi-file-earmark-text me-2"></i>Edit Clearance Details
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header bg-primary text-white">
                                <i class="bi bi-info-circle me-2"></i>Quick Actions
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if ($requestDetails['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-outline-info" onclick="updateStatus('processing')">
                                        <i class="bi bi-arrow-right-circle me-2"></i>Move to Processing
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="updateStatus('rejected')">
                                        <i class="bi bi-x-circle me-2"></i>Reject Request
                                    </button>
                                    <?php elseif ($requestDetails['status'] === 'processing'): ?>
                                    <button type="button" class="btn btn-outline-primary" onclick="updateStatus('ready')">
                                        <i class="bi bi-box-seam me-2"></i>Mark as Ready for Pickup
                                    </button>
                                    <button type="button" class="btn btn-outline-success" onclick="updateStatus('completed')">
                                        <i class="bi bi-check-circle me-2"></i>Mark as Completed
                                    </button>
                                    <?php elseif ($requestDetails['status'] === 'ready'): ?>
                                    <button type="button" class="btn btn-outline-success" onclick="updateStatus('completed')">
                                        <i class="bi bi-check-circle me-2"></i>Mark as Completed
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($requestDetails['status'] !== 'completed' && $requestDetails['status'] !== 'rejected' && $requestDetails['status'] !== 'cancelled'): ?>
                                    <button type="button" class="btn btn-outline-secondary" onclick="updateStatus('cancelled')">
                                        <i class="bi bi-x-circle me-2"></i>Cancel Request
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to update status with quick action buttons
        function updateStatus(status) {
            const statusSelect = document.getElementById('status');
            statusSelect.value = status;
            
            // Prompt for remarks based on status
            let defaultRemarks = '';
            if (status === 'rejected') {
                defaultRemarks = document.getElementById('admin_remarks').value;
                const reason = prompt('Please provide a reason for rejecting this request:', defaultRemarks);
                if (reason !== null) {
                    document.getElementById('admin_remarks').value = reason;
                } else {
                    return; // User cancelled
                }
            } else if (status === 'cancelled') {
                defaultRemarks = document.getElementById('admin_remarks').value;
                const reason = prompt('Please provide a reason for cancelling this request:', defaultRemarks);
                if (reason !== null) {
                    document.getElementById('admin_remarks').value = reason;
                } else {
                    return; // User cancelled
                }
            } else if (status === 'ready') {
                document.getElementById('admin_remarks').value += 
                    (document.getElementById('admin_remarks').value ? '\n\n' : '') + 
                    'Document is ready for pickup. Please bring valid ID.';
            } else if (status === 'completed') {
                document.getElementById('admin_remarks').value += 
                    (document.getElementById('admin_remarks').value ? '\n\n' : '') + 
                    'Request has been completed.';
            }
            
            // Submit the form
            document.getElementById('statusUpdateForm').submit();
        }
        
        // Confirm before status update if changing to rejected or cancelled
        document.getElementById('statusUpdateForm').addEventListener('submit', function(e) {
            const status = document.getElementById('status').value;
            const currentStatus = '<?php echo $requestDetails['status']; ?>';
            
            if ((status === 'rejected' || status === 'cancelled') && 
                (currentStatus !== 'rejected' && currentStatus !== 'cancelled')) {
                
                const remarks = document.getElementById('admin_remarks').value.trim();
                
                if (remarks === '') {
                    e.preventDefault();
                    alert('Please provide remarks explaining why the request is being ' + 
                          (status === 'rejected' ? 'rejected' : 'cancelled'));
                    return false;
                }
                
                if (!confirm('Are you sure you want to ' + 
                             (status === 'rejected' ? 'reject' : 'cancel') + 
                             ' this request? This action cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html>