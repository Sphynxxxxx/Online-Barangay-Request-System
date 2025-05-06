<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "../../../backend/connections/config.php";

// Initialize variables
$showAlert = false;
$alertType = "";
$alertMessage = "";
$requestDetails = null;
$certificateDetails = null;
$isDataSaved = false;

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

        // Fetch request details
        $requestSql = "SELECT r.*, 
                      u.first_name, 
                      u.last_name, 
                      u.email,
                      u.contact_number,
                      u.zone,
                      u.house_number,
                      rd.address,
                      rd.fullname,
                      rd.civil_status
                    FROM requests r
                    JOIN users u ON r.user_id = u.user_id
                    LEFT JOIN request_details rd ON r.request_id = rd.request_id
                    WHERE r.request_id = ? AND r.document_type = 'certificate_residency'";
        
        $stmt = $conn->prepare($requestSql);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $requestDetails = $result->fetch_assoc();
            
            // Check if certificate details already exist in the database
            // Create the residency_certificates table if not exists
            $createTableSql = "CREATE TABLE IF NOT EXISTS `residency_certificates` (
                `certificate_id` int(11) NOT NULL AUTO_INCREMENT,
                `request_id` int(11) NOT NULL,
                `certificate_number` varchar(50) DEFAULT NULL,
                `full_name` varchar(255) NOT NULL,
                `civil_status` varchar(50) DEFAULT NULL,
                `barangay_address` text NOT NULL,
                `municipality` varchar(100) DEFAULT 'Iguig',
                `province` varchar(100) DEFAULT 'Cagayan',
                `cert_day` varchar(10) NOT NULL,
                `cert_month` varchar(20) NOT NULL,
                `cert_year` varchar(10) NOT NULL,
                `punong_barangay` varchar(100) NOT NULL,
                `specimen_signature` tinyint(1) DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`certificate_id`),
                KEY `request_id` (`request_id`),
                CONSTRAINT `residency_certificates_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
            
            $conn->query($createTableSql);
            
            $certSql = "SELECT * FROM residency_certificates WHERE request_id = ?";
            $stmtCert = $conn->prepare($certSql);
            $stmtCert->bind_param("i", $requestId);
            $stmtCert->execute();
            $certResult = $stmtCert->get_result();
            
            if ($certResult->num_rows > 0) {
                $certificateDetails = $certResult->fetch_assoc();
                $isDataSaved = true;
            }
            
            $stmtCert->close();
        } else {
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "Request not found or not a Certificate of Residency.";
        }
        $stmt->close();
    }

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_certificate'])) {
        $requestId = intval($_POST['request_id']);
        $fullName = trim($_POST['full_name']);
        $civilStatus = trim($_POST['civil_status']);
        $barangayAddress = trim($_POST['barangay_address']);
        $certDay = trim($_POST['cert_day']);
        $certMonth = trim($_POST['cert_month']);
        $certYear = trim($_POST['cert_year']);
        $punongBarangay = trim($_POST['punong_barangay']);
        $specimenSignature = isset($_POST['specimen_signature']) ? 1 : 0;
        
        // Check if certificate already exists
        $checkSql = "SELECT certificate_id FROM residency_certificates WHERE request_id = ?";
        $stmtCheck = $conn->prepare($checkSql);
        $stmtCheck->bind_param("i", $requestId);
        $stmtCheck->execute();
        $checkResult = $stmtCheck->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing certificate
            $certificateId = $checkResult->fetch_assoc()['certificate_id'];
            
            $updateSql = "UPDATE residency_certificates 
                         SET certificate_number = ?, 
                             full_name = ?, 
                             civil_status = ?, 
                             barangay_address = ?, 
                             municipality = ?, 
                             province = ?, 
                             cert_day = ?, 
                             cert_month = ?, 
                             cert_year = ?, 
                             punong_barangay = ?, 
                             specimen_signature = ?,
                             updated_at = NOW() 
                         WHERE certificate_id = ?";
            
            $stmtUpdate = $conn->prepare($updateSql);
            $stmtUpdate->bind_param("ssssssssssii", 
                $certificateNumber, 
                $fullName, 
                $civilStatus, 
                $barangayAddress, 
                $municipality, 
                $province,
                $certDay,
                $certMonth,
                $certYear,
                $punongBarangay,
                $specimenSignature,
                $certificateId
            );
            
            if ($stmtUpdate->execute()) {
                $showAlert = true;
                $alertType = "success";
                $alertMessage = "Certificate details updated successfully.";
                $isDataSaved = true;
                
                // Refresh certificate details
                $stmtCert = $conn->prepare($certSql);
                $stmtCert->bind_param("i", $requestId);
                $stmtCert->execute();
                $certResult = $stmtCert->get_result();
                $certificateDetails = $certResult->fetch_assoc();
                $stmtCert->close();
            } else {
                $showAlert = true;
                $alertType = "danger";
                $alertMessage = "Error updating certificate details: " . $stmtUpdate->error;
            }
            
            $stmtUpdate->close();
        } else {
            // Insert new certificate
            $insertSql = "INSERT INTO residency_certificates 
                         (request_id, certificate_number, full_name, civil_status, barangay_address, 
                          municipality, province, cert_day, cert_month, cert_year,
                          punong_barangay, specimen_signature, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmtInsert = $conn->prepare($insertSql);
            $stmtInsert->bind_param("issssssssssi", 
                $requestId, 
                $certificateNumber, 
                $fullName, 
                $civilStatus, 
                $barangayAddress, 
                $municipality,
                $province,
                $certDay,
                $certMonth,
                $certYear,
                $punongBarangay,
                $specimenSignature
            );
            
            if ($stmtInsert->execute()) {
                $showAlert = true;
                $alertType = "success";
                $alertMessage = "Certificate details saved successfully.";
                $isDataSaved = true;
                
                // Get the newly inserted certificate details
                $stmtCert = $conn->prepare($certSql);
                $stmtCert->bind_param("i", $requestId);
                $stmtCert->execute();
                $certResult = $stmtCert->get_result();
                $certificateDetails = $certResult->fetch_assoc();
                $stmtCert->close();
            } else {
                $showAlert = true;
                $alertType = "danger";
                $alertMessage = "Error saving certificate details: " . $stmtInsert->error;
            }
            
            $stmtInsert->close();
        }
        
        $stmtCheck->close();
    }

    // Close connection
    $conn->close();
}

// Current date components for default values
$currentDay = date("j");
$currentMonth = date("F");
$currentYear = date("Y");

// Get address from user data
$userAddress = "";
if (isset($requestDetails)) {
    if (!empty($requestDetails['address'])) {
        // Use address from request_details if available
        $userAddress = $requestDetails['address'];
    } else {
        // Construct address from house_number and zone
        $userAddress = $requestDetails['house_number'] . " " . $requestDetails['zone'];
    }
}

// Set a default barangay address using the barangay name from the image
$barangayAddress = "Barangay Minanga Norte, Iguig, Cagayan";

// Pre-populate form data
$formData = [
    'certificate_number' => $certificateDetails['certificate_number'] ?? '',
    'full_name' => $certificateDetails['full_name'] ?? ($requestDetails['fullname'] ?? strtoupper($requestDetails['first_name'] . ' ' . $requestDetails['last_name'])),
    'civil_status' => $certificateDetails['civil_status'] ?? ($requestDetails['civil_status'] ?? 'married'),
    'barangay_address' => $certificateDetails['barangay_address'] ?? $barangayAddress,
    'municipality' => $certificateDetails['municipality'] ?? 'Iguig',
    'province' => $certificateDetails['province'] ?? 'Cagayan',
    'cert_day' => $certificateDetails['cert_day'] ?? $currentDay,
    'cert_month' => $certificateDetails['cert_month'] ?? $currentMonth,
    'cert_year' => $certificateDetails['cert_year'] ?? $currentYear,
    'punong_barangay' => $certificateDetails['punong_barangay'] ?? 'BARANGAY CAPTAIN NAME',
    'specimen_signature' => $certificateDetails['specimen_signature'] ?? 1
];

// Fetch Barangay Official Names from database (if needed)
// You could add code here to get the actual Barangay Captain name from the barangay_officials table
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Certificate of Residency - Barangay Document System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f4f6f9;
        }
        
        .preview-container {
            background-color: white;
            padding: 10px;

        }
        
        .certificate-preview {
            width: 8.5in;
            height: 11in;
            padding: 0.7in;
            background-color: white;
            margin: 0 auto;
            font-family: 'Times New Roman', Times, serif;
            position: relative;
            font-size: 12pt;
        }
        
        .certificate-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .certificate-title {
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
        }
        
        .certificate-content {
            font-size: 12pt;
            line-height: 1.6;
            text-align: justify;
            margin-top: 20px;
        }
        
        .text-indent {
            text-indent: 50px;
        }
        
        .uppercase {
            text-transform: uppercase;
        }
        
        .signature-line {
            width: 250px;
            border-bottom: 1px solid #000;
            margin: 5px 0;
            display: inline-block;
            position: relative;
        }
        
        .signature-line > div {
            position: absolute;
            width: 100%;
            bottom: -5px;
            left: 0;
            text-align: center;
        }
        
        .text-center {
            text-align: center;
            width: 250px;
            display: inline-block;
        }
        
        .signature-name {
            font-weight: bold;
            text-align: center;
            margin-top: 5px;
        }
        
        .signature-position {
            text-align: center;
            margin-top: 0;
        }
        
        .text-center-align {
            text-align: center;
        }
        
        .text-right-align {
            text-align: right;
        }
        
        .certificate-footer {
            position: absolute;
            bottom: 1in;
            left: 0.7in;
            font-size: 11pt;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: white;
                margin: 0;
                padding: 0;
            }
            .container-fluid, .row, .col-md-12 {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .certificate-preview {
                width: 100%;
                height: auto;
                padding: 0.5in;
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h1 class="h3 mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>Edit Certificate of Residency
                    </h1>
                </div>

                <?php if ($showAlert): ?>
                <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show no-print" role="alert">
                    <?php echo $alertMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($requestDetails): ?>
                <div class="row">
                    <div class="col-md-4 no-print">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <i class="bi bi-pencil-square me-2"></i>Certificate Details
                            </div>
                            <div class="card-body">
                                <form method="POST" id="certificateForm">
                                    <input type="hidden" name="request_id" value="<?php echo $requestDetails['request_id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo $formData['full_name']; ?>" required>
                                        <div class="form-text">Name will appear in uppercase</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="civil_status" class="form-label">Civil Status</label>
                                        <select class="form-select" id="civil_status" name="civil_status" required>
                                            <option value="single" <?php echo $formData['civil_status'] == 'single' ? 'selected' : ''; ?>>Single</option>
                                            <option value="married" <?php echo $formData['civil_status'] == 'married' ? 'selected' : ''; ?>>Married</option>
                                            <option value="widowed" <?php echo $formData['civil_status'] == 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                                            <option value="divorced" <?php echo $formData['civil_status'] == 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                            <option value="separated" <?php echo $formData['civil_status'] == 'separated' ? 'selected' : ''; ?>>Separated</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="barangay_address" class="form-label">Barangay Address</label>
                                        <input type="text" class="form-control" id="barangay_address" name="barangay_address" 
                                               value="<?php echo $formData['barangay_address']; ?>" required>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <!--<div class="col-md-6">
                                            <label for="municipality" class="form-label">Municipality</label>
                                            <input type="text" class="form-control" id="municipality" name="municipality" 
                                                   value="<?php echo $formData['municipality']; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="province" class="form-label">Province</label>
                                            <input type="text" class="form-control" id="province" name="province" 
                                                   value="<?php echo $formData['province']; ?>" required>
                                        </div>-->
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Certificate Date</label>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <input type="number" class="form-control" id="cert_day" name="cert_day" 
                                                       value="<?php echo $formData['cert_day']; ?>" min="1" max="31" required 
                                                       placeholder="Day">
                                            </div>
                                            <div class="col-md-5">
                                                <select class="form-select" id="cert_month" name="cert_month" required>
                                                    <?php
                                                    $months = [
                                                        'January', 'February', 'March', 'April', 'May', 'June', 
                                                        'July', 'August', 'September', 'October', 'November', 'December'
                                                    ];
                                                    foreach ($months as $month) {
                                                        $selected = ($month == $formData['cert_month']) ? 'selected' : '';
                                                        echo "<option value=\"$month\" $selected>$month</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="number" class="form-control" id="cert_year" name="cert_year" 
                                                       value="<?php echo $formData['cert_year']; ?>" min="2000" max="2100" required
                                                       placeholder="Year">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="punong_barangay" class="form-label">Punong Barangay</label>
                                        <input type="text" class="form-control" id="punong_barangay" name="punong_barangay" 
                                               value="<?php echo $formData['punong_barangay']; ?>" required>
                                        <div class="form-text">Name will appear in uppercase</div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="specimen_signature" name="specimen_signature"
                                              <?php echo $formData['specimen_signature'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="specimen_signature">Include Specimen Signature field</label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="save_certificate" class="btn btn-success">
                                            <i class="bi bi-save me-2"></i>Save Certificate Details
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="preview-container">
                            <div class="certificate-preview" id="certificatePreview">
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <p style="margin-bottom: 0; font-weight: bold;">Republic of the Philippines</p>
                                    <p style="margin-bottom: 0;">City of Iloilo</p>
                                    <p style="margin-bottom: 0;">District of Jaro</p>
                                    <p style="margin-bottom: 5px; font-weight: bold;">BARANGAY LANIT</p>
                                    <p style="margin-bottom: 0; font-weight: bold;">OFFICE OF THE PUNONG BARANGAY</p>
                                </div>
                                
                                <div class="certificate-title">CERTIFICATE OF RESIDENCY</div>
                                
                                <div class="certificate-content">
                                    <p class="mb-4">TO WHOM IT MAY CONCERN:</p>
                                    
                                    <p class="text-indent mb-4">
                                        This is to certify that <span class="uppercase fw-bold" id="preview-fullname"><?php echo $formData['full_name']; ?></span>, of legal age, <span id="preview-civil-status"><?php echo $formData['civil_status']; ?></span>, Filipino citizen, whose specimen signature appears below, is a <span class="uppercase fw-bold">PERMANENT RESIDENT</span> of <span id="preview-address"><?php echo $formData['barangay_address']; ?></span>.
                                    </p>
                                    
                                    <p class="text-indent mb-4">
                                        Based on records of this office, she has been residing at <span id="preview-barangay-address-2"><?php echo $formData['barangay_address']; ?></span>.
                                    </p>
                                    
                                    <p class="text-indent mb-4">
                                        This <span class="uppercase fw-bold">CERTIFICATION</span> is being issued upon the request of the above-named person for whatever legal purpose it may serve.
                                    </p>
                                    
                                    <p class="text-indent mb-5">
                                        Issued this <span id="preview-day" class="fw-bold"><?php echo $formData['cert_day']; ?></span><sup id="preview-day-suffix"><?php echo getOrdinalSuffix($formData['cert_day']); ?></sup> day of <span id="preview-month" class="fw-bold"><?php echo $formData['cert_month']; ?></span>, <span id="preview-year" class="fw-bold"><?php echo $formData['cert_year']; ?></span> at <span id="preview-barangay-address-3"><?php echo $formData['barangay_address']; ?></span>.
                                    </p>
                                    
                                    <?php if ($formData['specimen_signature']): ?>
                                    <div class="mb-5">
                                        <p>Specimen Signature:</p>
                                        <div class="signature-line"></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-right-align" style="margin-top: 30px;">
                                        <div class="text-center" style="width: 250px;">
                                            <div class="signature-line" id="punong-barangay-line">
                                                <div class="text-center uppercase fw-bold" id="preview-punong-barangay">
                                                    <?php echo $formData['punong_barangay']; ?>
                                                </div>
                                            </div>
                                            <p class="text-center mt-1 mb-0">Punong Barangay</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="certificate-footer">
                                    <p class="mb-0">Note:</p>
                                    <p class="fst-italic mb-0">"Not valid without official seal"</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No valid request was found. Please select a valid Certificate of Residency request.
                </div>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <h1 class="h3 mb-0">
                </h1>
                <div>
                    <a href="../request-details.php?id=<?php echo $requestDetails['request_id']; ?>" class="btn btn-danger me-2">
                        <i class="bi bi-x-circle me-2"></i>Close
                    </a>
                    <button onclick="window.print();" class="btn btn-primary">
                        <i class="bi bi-printer me-2"></i>Print Certificate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Helper function to get ordinal suffix for a number (1st, 2nd, 3rd, etc.)
        function getOrdinalSuffix(day) {
            if (day >= 11 && day <= 13) {
                return 'th';
            }
            
            switch (day % 10) {
                case 1: return 'st';
                case 2: return 'nd';
                case 3: return 'rd';
                default: return 'th';
            }
        }
        
        // Function to update certificate preview in real-time
        document.addEventListener('DOMContentLoaded', function() {
            const formInputs = document.querySelectorAll('#certificateForm input, #certificateForm textarea, #certificateForm select');
            
            formInputs.forEach(input => {
                input.addEventListener('input', updatePreview);
            });
            
            function updatePreview() {
                // Get all form values
                const fullName = document.getElementById('full_name').value;
                const civilStatus = document.getElementById('civil_status').value;
                const barangayAddress = document.getElementById('barangay_address').value;
                const municipality = document.getElementById('municipality').value;
                const province = document.getElementById('province').value;
                const certDay = document.getElementById('cert_day').value;
                const certMonth = document.getElementById('cert_month').value;
                const certYear = document.getElementById('cert_year').value;
                const punongBarangay = document.getElementById('punong_barangay').value;
                const specimenSignature = document.getElementById('specimen_signature').checked;
                
                // Update the preview content
                document.getElementById('preview-fullname').textContent = fullName.toUpperCase();
                document.getElementById('preview-civil-status').textContent = civilStatus;
                document.getElementById('preview-address').textContent = barangayAddress;
                document.getElementById('preview-barangay-address-2').textContent = barangayAddress;
                document.getElementById('preview-barangay-address-3').textContent = barangayAddress;
                document.getElementById('preview-province').textContent = province;
                document.getElementById('preview-municipality').textContent = municipality;
                document.getElementById('preview-day').textContent = certDay;
                document.getElementById('preview-day-suffix').textContent = getOrdinalSuffix(certDay);
                document.getElementById('preview-month').textContent = certMonth;
                document.getElementById('preview-year').textContent = certYear;
                // Update Punong Barangay name on the signature line
                const punongBarangayElem = document.getElementById('preview-punong-barangay');
                if (punongBarangayElem) {
                    punongBarangayElem.textContent = punongBarangay.toUpperCase();
                }
                
                // Show/hide specimen signature section
                const specimenSection = document.querySelector('.certificate-content .mb-5');
                if (specimenSection) {
                    specimenSection.style.display = specimenSignature ? 'block' : 'none';
                }
            }
            
            // Initial update on page load
            updatePreview();
        });
    </script>
    
    <?php
    // Helper function for ordinal suffix
    function getOrdinalSuffix($day) {
        if ($day >= 11 && $day <= 13) {
            return 'th';
        }
        
        switch ($day % 10) {
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
            default: return 'th';
        }
    }
    ?>
</body>
</html>