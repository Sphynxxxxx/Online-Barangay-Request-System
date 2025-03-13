<?php
session_start();

$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "barangay_request_system"; 

$showAlert = false;
$alertType = "";
$alertMessage = "";
$requestDetails = null;
$clearanceDetails = null;
$officials = null;

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if request ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request ID.");
}

$requestId = intval($_GET['id']);

// Fetch request details with request_details
$requestSql = "SELECT r.*, rd.*, r.document_type, r.purpose
               FROM requests r
               JOIN request_details rd ON r.request_id = rd.request_id
               WHERE r.request_id = ?";

$stmt = $conn->prepare($requestSql);
$stmt->bind_param("i", $requestId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $requestDetails = $result->fetch_assoc();
} else {
    die("Request not found.");
}

// Fetch Barangay Officials
$officialsSql = "SELECT * FROM barangay_officials LIMIT 1";
$officialsResult = $conn->query($officialsSql);
$officials = $officialsResult ? $officialsResult->fetch_assoc() : null;

// Check if clearance details exist or submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process form submission
    $name = $_POST['name'] ?? $requestDetails['fullname'];
    $address = $_POST['address'] ?? $requestDetails['address'];
    $age = $_POST['age'] ?? $requestDetails['age'];
    $civilStatus = $_POST['civil_status'] ?? $requestDetails['civil_status'];
    $residencyStatus = $_POST['residency_status'] ?? $requestDetails['residency_status'];
    $purpose = $requestDetails['purpose'];
    $issuedDate = $_POST['issued_date'] ?? date('Y-m-d');
    
    // Prepare SQL to update request_details
    $updateSql = "UPDATE request_details 
                  SET fullname = ?, 
                      age = ?, 
                      address = ?, 
                      civil_status = ?, 
                      residency_status = ?
                  WHERE request_id = ?";
    
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param(
        "sisssi", 
        $name, $age, $address, $civilStatus, $residencyStatus, $requestId
    );
    
    if ($stmt->execute()) {
        $showAlert = true;
        $alertType = "success";
        $alertMessage = "Clearance details updated successfully.";
        
        // Refresh details
        $stmt = $conn->prepare($requestSql);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        $requestDetails = $result->fetch_assoc();
    } else {
        $showAlert = true;
        $alertType = "danger";
        $alertMessage = "Error updating clearance details: " . $stmt->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Barangay Clearance</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <style>
        .clearance-form {
            max-height: 100vh;
            overflow-y: auto;
            padding: 15px;
        }
        .card {
            margin-bottom: 15px;
        }
        .card-body {
            padding: 15px;
        }
        .form-label {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
        .form-control {
            font-size: 0.90rem;
            padding: 0.375rem 0.75rem;
            height: calc(1.5em + 0.75rem + 2px);
        }
        .officials-section {
            display: none; 
        }
        @media (max-width: 768px) {
            .clearance-form {
                max-height: none;
                overflow-y: visible;
            }
        }
        @media print {
            @page {
                margin: 0.5cm;
            } 

            @page :left {
                content: "";
            }
            
            @page :right {
                content: "";
            }
            body {
                font-family: "Times New Roman", serif;
                color: black;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            .certificate-container {
                display: flex;
                border: 5px double black;
                min-height: 100%;
            }

            .certificate-content {
                width: 70%;
                padding: 20px;
            }

            .print-only {
                display: block !important;
            }
            
            .print-only {
                display: none;
            }
            .officials-section {
                width: 30%;
                padding: 20px;
                border-right: 1px solid #000;
            }

            .certificate-section {
                width: 70%;
                padding: 20px;
            }

            .officials-section h5 {
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
                margin-bottom: 15px;
            }

            .officials-section ul {
                list-style-type: none;
                padding: 0;
            }

            .officials-section li {
                margin-bottom: 15px;
            }

            .no-print, 
            .no-print * {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Clearance Edit Form -->
            <div class="col-md-4 clearance-form p-2 bg-light no-print">
                <div class="card mb-2">
                    <div class="card-header bg-primary text-white py-2">
                        <i class="bi bi-pencil-square me-2"></i>Edit Clearance Details
                    </div>
                    <div class="card-body py-2">
                        <?php if ($showAlert): ?>
                            <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show mb-2" role="alert">
                                <?php echo $alertMessage; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row g-2">
                                <div class="col-12 mb-1">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control form-control-sm" name="name" 
                                        value="<?php echo htmlspecialchars($requestDetails['fullname']); ?>">
                                </div>
                                
                                <div class="col-12 mb-1">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control form-control-sm" name="address" 
                                        value="<?php echo htmlspecialchars($requestDetails['address']); ?>">
                                </div>
                                
                                <div class="col-4 mb-1">
                                    <label class="form-label">Age</label>
                                    <input type="number" class="form-control form-control-sm" name="age" 
                                        value="<?php echo htmlspecialchars($requestDetails['age']); ?>">
                                </div>
                                
                                <div class="col-8 mb-1">
                                    <label class="form-label">Civil Status</label>
                                    <input type="text" class="form-control form-control-sm" name="civil_status" 
                                        value="<?php echo htmlspecialchars($requestDetails['civil_status']); ?>">
                                </div>
                                
                                <div class="col-12 mb-1">
                                    <label class="form-label">Residency Status</label>
                                    <input type="text" class="form-control form-control-sm" name="residency_status" 
                                        value="<?php echo htmlspecialchars($requestDetails['residency_status']); ?>">
                                </div>
                                
                                <div class="col-12 mb-1">
                                    <label class="form-label">Issued Date</label>
                                    <input type="date" class="form-control form-control-sm" name="issued_date" 
                                        value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-sm w-100 mt-2">
                                <i class="bi bi-save me-1"></i>Save Details
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-info text-white py-2">
                        <i class="bi bi-people me-2"></i>Edit Barangay Officials
                    </div>
                    <div class="card-body py-2">
                        <form method="POST" action="update-officials.php">
                            <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                            
                            <div class="row g-2">
                                <div class="col-12 mb-1">
                                    <label class="form-label">Punong Barangay</label>
                                    <input type="text" class="form-control form-control-sm" name="punong_barangay" 
                                        value="<?php echo htmlspecialchars($officials['punong_barangay'] ?? 'REMEDIOS S. BEDIA'); ?>" 
                                        placeholder="Enter Punong Barangay Name">
                                </div>
                                
                                <div class="col-6 mb-1">
                                    <label class="form-label">SK Chairperson</label>
                                    <input type="text" class="form-control form-control-sm" name="sk_chairperson" 
                                        value="<?php echo htmlspecialchars($officials['sk_chairperson'] ?? ''); ?>" 
                                        placeholder="SK Chairperson">
                                </div>
                                
                                <div class="col-6 mb-1">
                                    <label class="form-label">Barangay Secretary</label>
                                    <input type="text" class="form-control form-control-sm" name="barangay_secretary" 
                                        value="<?php echo htmlspecialchars($officials['barangay_secretary'] ?? ''); ?>" 
                                        placeholder="Barangay Secretary">
                                </div>
                                
                                <div class="col-6 mb-1">
                                    <label class="form-label">Barangay Treasurer</label>
                                    <input type="text" class="form-control form-control-sm" name="barangay_treasurer" 
                                        value="<?php echo htmlspecialchars($officials['barangay_treasurer'] ?? ''); ?>" 
                                        placeholder="Barangay Treasurer">
                                </div>
                                
                                <div class="col-6 mb-1">
                                    <label class="form-label">Other Official</label>
                                    <input type="text" class="form-control form-control-sm" name="other_official" 
                                        value="<?php echo htmlspecialchars($officials['other_official'] ?? ''); ?>" 
                                        placeholder="Other Official">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-info btn-sm w-100 mt-2 text-white">
                                <i class="bi bi-save me-1"></i>Update Officials
                            </button>
                        </form>
                    </div>
                </div>
                
            </div>

            

            <!-- Clearance Preview Container -->
            <div class="col-md-8">
                <div class="row">
                    <!-- Barangay Officials Side Panel - Moved to the left -->
                    <div class="col-md-4 mt-4 no-print">
                        <div class="card h-100">
                            <div class="card-header bg-secondary text-white">
                                <i class="bi bi-people me-2"></i>Barangay Officials
                            </div>
                            <div class="card-body">
                                <?php if ($officials): ?>
                                    <ul class="list-unstyled">
                                        <?php 
                                        $officialRoles = [
                                            'punong_barangay' => 'Punong Barangay',
                                            'sk_chairperson' => 'SK Chairperson',
                                            'barangay_secretary' => 'Barangay Secretary',
                                            'barangay_treasurer' => 'Barangay Treasurer',
                                            'other_official' => 'Other Official'
                                        ];

                                        foreach ($officialRoles as $key => $label):
                                            if (!empty($officials[$key])):
                                        ?>
                                        <li class="mb-2">
                                            <strong><?php echo $label; ?>:</strong><br>
                                            <?php echo htmlspecialchars($officials[$key]); ?>
                                        </li>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted text-center">No officials information available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Clearance Preview -->
                    <div class="col-md-8 p-4">
                        <div class="certificate-preview">
                            <div class="certificate-container">
                                <!-- Officials Section for Print -->
                                <div class="officials-section print-only">
                                    <?php 
                                    $officialFields = [
                                        'punong_barangay' => 'Punong Barangay',
                                        'sk_chairperson' => 'SK Chairperson',
                                        'barangay_secretary' => 'Barangay Secretary',
                                        'barangay_treasurer' => 'Barangay Treasurer',
                                        'other_official' => 'Other Official'
                                    ];

                                    $hasOfficials = false;
                                    foreach ($officialFields as $field => $label) {
                                        if (!empty($officials[$field])) {
                                            $hasOfficials = true;
                                            break;
                                        }
                                    }

                                    if ($hasOfficials): 
                                    ?>
                                        <h5 class="text-center">Barangay Officials</h5>
                                        <ul class="list-unstyled">
                                            <?php foreach ($officialFields as $field => $label): ?>
                                                <?php if (!empty($officials[$field])): ?>
                                                <li class="mb-3">
                                                    <strong><?php echo $label; ?></strong><br>
                                                    <?php echo htmlspecialchars($officials[$field]); ?>
                                                </li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>

                                <!-- Certificate Content -->
                                <div class="certificate-content">
                                    <div class="text-center mb-4">
                                        <h5 class="text-uppercase">REPUBLIC OF THE PHILIPPINES</h5>
                                        <h6>City of Iloilo</h6>
                                        <h6>District of Jaro</h6>
                                        <h6>BARANGAY LANIT</h6>
                                        <h6 class="text-center mt-3">OFFICE OF THE PUNONG BARANGAY</h6>
                                        <h2 class="mt-3">BARANGAY CERTIFICATION</h2>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <p class="text-center"><strong>TO WHOM IT MAY CONCERN:</strong></p>
                                        
                                        <p>This is to certify that the name appears below is a resident of Barangay Lanit, Jaro, Iloilo City.</p>
                                        
                                        <div class="row mt-4">
                                            <div class="col-4"><strong>NAME:</strong></div>
                                            <div class="col-8"><?php echo htmlspecialchars($requestDetails['fullname']); ?></div>
                                        </div>
                                        
                                        <div class="row mt-2">
                                            <div class="col-4"><strong>ADDRESS:</strong></div>
                                            <div class="col-8"><?php echo htmlspecialchars($requestDetails['address']); ?></div>
                                        </div>
                                        
                                        <div class="row mt-2">
                                            <div class="col-4"><strong>AGE:</strong></div>
                                            <div class="col-8"><?php echo htmlspecialchars($requestDetails['age']); ?> YEARS OLD</div>
                                        </div>
                                        
                                        <div class="row mt-2">
                                            <div class="col-4"><strong>CIVIL STATUS:</strong></div>
                                            <div class="col-8"><?php echo htmlspecialchars(strtoupper($requestDetails['civil_status'])); ?></div>
                                        </div>
                                        
                                        <div class="row mt-2">
                                            <div class="col-4"><strong>RESIDENCY STATUS:</strong></div>
                                            <div class="col-8"><?php echo htmlspecialchars(strtoupper($requestDetails['residency_status'])); ?></div>
                                        </div>
                                        
                                        <p class="mt-4">This is being issued for <strong><?php echo htmlspecialchars($requestDetails['purpose']); ?></strong> purpose only.</p>
                                        
                                        <p class="mt-4">
                                            Issued this <?php echo date('jS'); ?> day of <?php echo date('F Y'); ?> 
                                            at Barangay Lanit, Jaro, Iloilo City, Philippines.
                                        </p>
                                        <div class="text-center mt-5">
                                            <p>____________________<br>
                                            PUNONG BARANGAY</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 text-center no-print">
                                    <button class="btn btn-success px-4 py-2" onclick="window.print()">
                                        <i class="bi bi-printer me-2"></i>Print Clearance
                                    </button>
                                    <button type="button" class="btn btn-danger px-4 py-2" 
                                        onclick="window.location.href='../request-details.php?id=<?php echo $requestId; ?>'">
                                        <i class="bi bi-x-circle me-1"></i>Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>



    

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>