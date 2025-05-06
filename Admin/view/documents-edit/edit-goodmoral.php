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
                    WHERE r.request_id = ? AND r.document_type = 'good_moral'";
        
        $stmt = $conn->prepare($requestSql);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $requestDetails = $result->fetch_assoc();
            
            // Check if certificate details already exist in the database
            // Create the goodmoral_certificates table if not exists
            $createTableSql = "CREATE TABLE IF NOT EXISTS `goodmoral_certificates` (
                `certificate_id` int(11) NOT NULL AUTO_INCREMENT,
                `request_id` int(11) NOT NULL,
                `full_name` varchar(255) NOT NULL,
                `civil_status` varchar(50) DEFAULT 'single',
                `barangay_address` text NOT NULL,
                `municipality` varchar(100) DEFAULT 'Infanta',
                `province` varchar(100) DEFAULT 'Pangasinan',
                `cert_day` varchar(10) NOT NULL,
                `cert_month` varchar(20) NOT NULL,
                `cert_year` varchar(10) NOT NULL,
                `punong_barangay` varchar(100) NOT NULL,
                `or_number` varchar(50) DEFAULT NULL,
                `validity_months` int(11) DEFAULT 5,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`certificate_id`),
                KEY `request_id` (`request_id`),
                CONSTRAINT `goodmoral_certificates_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
            
            $conn->query($createTableSql);
            
            $certSql = "SELECT * FROM goodmoral_certificates WHERE request_id = ?";
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
            $alertMessage = "Request not found or not a Good Moral Certificate.";
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
        $orNumber = trim($_POST['or_number']);
        $validityMonths = intval($_POST['validity_months']);
        
        // Check if certificate already exists
        $checkSql = "SELECT certificate_id FROM goodmoral_certificates WHERE request_id = ?";
        $stmtCheck = $conn->prepare($checkSql);
        $stmtCheck->bind_param("i", $requestId);
        $stmtCheck->execute();
        $checkResult = $stmtCheck->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing certificate
            $certificateId = $checkResult->fetch_assoc()['certificate_id'];
            
            $updateSql = "UPDATE goodmoral_certificates 
                         SET full_name = ?, 
                             civil_status = ?, 
                             barangay_address = ?, 
                             municipality = ?, 
                             province = ?, 
                             cert_day = ?, 
                             cert_month = ?, 
                             cert_year = ?, 
                             punong_barangay = ?, 
                             or_number = ?,
                             validity_months = ?,
                             updated_at = NOW() 
                         WHERE certificate_id = ?";
            
            $stmtUpdate = $conn->prepare($updateSql);
            $stmtUpdate->bind_param("ssssssssssii", 
                $fullName, 
                $civilStatus, 
                $barangayAddress, 
                $municipality, 
                $province,
                $certDay,
                $certMonth,
                $certYear,
                $punongBarangay,
                $orNumber,
                $validityMonths,
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
            $insertSql = "INSERT INTO goodmoral_certificates 
                         (request_id, full_name, civil_status, barangay_address, 
                          municipality, province, cert_day, cert_month, cert_year,
                          punong_barangay, or_number, validity_months, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmtInsert = $conn->prepare($insertSql);
            $stmtInsert->bind_param("issssssssssi", 
                $requestId, 
                $fullName, 
                $civilStatus, 
                $barangayAddress, 
                $municipality,
                $province,
                $certDay,
                $certMonth,
                $certYear,
                $punongBarangay,
                $orNumber,
                $validityMonths
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
        $userAddress = "Lanit Jaro, Iloilo City";
    }
}


// Pre-populate form data
$formData = [
    'full_name' => $certificateDetails['full_name'] ?? ($requestDetails['fullname'] ?? strtoupper($requestDetails['first_name'] . ' ' . $requestDetails['last_name'])),
    'civil_status' => $certificateDetails['civil_status'] ?? ($requestDetails['civil_status'] ?? 'married'),
    'barangay_address' => $certificateDetails['barangay_address'] ?? $userAddress,
    'cert_day' => $certificateDetails['cert_day'] ?? $currentDay,
    'cert_month' => $certificateDetails['cert_month'] ?? $currentMonth,
    'cert_year' => $certificateDetails['cert_year'] ?? $currentYear,
    'punong_barangay' => $certificateDetails['punong_barangay'] ?? 'RAMON M. ABELLA',
    'or_number' => $certificateDetails['or_number'] ?? '5309160',
    'validity_months' => $certificateDetails['validity_months'] ?? 5
];

// Fetch Barangay Official Names from database (if needed)
// You could add code here to get the actual Barangay Captain name from the barangay_officials table
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Good Moral Certificate - Barangay Document System</title>
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
            padding: 0.6in;
            background-color: white;
            margin: 0 auto;
            font-family: 'Times New Roman', Times, serif;
            position: relative;
            font-size: 11pt;
        }
        
        .header-text {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .certificate-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin: 10px 0;
            text-transform: uppercase;
        }
        
        .certificate-content {
            font-size: 11pt;
            line-height: 1.4;
            text-align: justify;
            margin-top: 10px;
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
            text-align: center;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-bold {
            font-weight: bold;
        }
        
        .footer-note {
            font-size: 10pt;
            margin-top: 20px;
        }
        
        .note-small {
            font-size: 10pt;
            font-style: italic;
        }
        
        .payment-info {
            font-size: 10pt;
            margin-top: 15px;
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
                padding: 0.4in;
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
                        <i class="bi bi-file-earmark-text me-2"></i>Edit Good Moral Certificate
                    </h1>
                    <div>
                        <a href="../request-details.php?id=<?php echo $requestDetails['request_id']; ?>" class="btn btn-secondary me-2">
                            <i class="bi bi-arrow-left me-2"></i>Back to Request
                        </a>
                        <button onclick="window.print();" class="btn btn-primary">
                            <i class="bi bi-printer me-2"></i>Print Certificate
                        </button>
                    </div>
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
                                    
                                    <!--<div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="municipality" class="form-label">Municipality</label>
                                            <input type="text" class="form-control" id="municipality" name="municipality" 
                                                   value="<?php echo $formData['municipality']; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="province" class="form-label">Province</label>
                                            <input type="text" class="form-control" id="province" name="province" 
                                                   value="<?php echo $formData['province']; ?>" required>
                                        </div>
                                    </div>-->
                                    
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
                                    
                                    <div class="mb-3">
                                        <label for="or_number" class="form-label">OR Number</label>
                                        <input type="text" class="form-control" id="or_number" name="or_number" 
                                               value="<?php echo $formData['or_number']; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="validity_months" class="form-label">Validity (Months)</label>
                                        <input type="number" class="form-control" id="validity_months" name="validity_months" 
                                               value="<?php echo $formData['validity_months']; ?>" min="1" max="12">
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
                                <div class="header-text">
                                    <p style="margin-bottom: 0; font-weight: bold">REPUBLIC OF THE PHILIPPINES</p>
                                    <p style="margin-bottom: 0;">City of Iloilo</p>
                                    <p style="margin-bottom: 0;">District of Jaro</p>
                                    <p style="margin-bottom: 0; font-weight: bold">BARANGAY LANIT</p>
                                </div>
                                
                                <div class="text-center" style="margin: 15px 0;">
                                    <p style="margin-bottom: 0; font-weight: bold;">OFFICE OF THE PUNONG BARANGAY</p>
                                </div>
                                
                                <div class="certificate-title">CERTIFICATE OF GOOD MORAL CHARACTER</div>
                                
                                <div class="certificate-content">
                                    <p>TO WHOM IT MAY CONCERN:</p>
                                    
                                    <p style="text-indent: 40px; margin-top: 15px;">
                                        THIS IS TO CERTIFY that <span class="text-bold uppercase" id="preview-fullname"><?php echo $formData['full_name']; ?></span>, of legal age, <span id="preview-civil-status"><?php echo $formData['civil_status']; ?></span>, and a resident of <span id="preview-address"><?php echo $formData['barangay_address']; ?></span> is personally known to me to be of good moral character and reputation in the community. He is peaceful and law-abiding citizen.
                                    </p>
                                    
                                    <p style="text-indent: 40px; margin-top: 15px;">
                                        As per records available in the files of this office, said subject has never been convicted nor accused of any crime whatsoever nor is he a member of any subversive organization.
                                    </p>
                                    
                                    <p style="text-indent: 40px; margin-top: 15px;">
                                        This certification is issued upon request of the above mentioned name for whatever legal purpose it may serve.
                                    </p>
                                    
                                    <p style="text-indent: 40px; margin-top: 15px;">
                                        Issued this <span id="preview-day"><?php echo $formData['cert_day']; ?></span><sup>th</sup> day of <span id="preview-month"><?php echo $formData['cert_month']; ?></span>, <span id="preview-year"><?php echo $formData['cert_year']; ?></span> at <span id="preview-barangay-location">Lanit Jaro, Iloilo City</span> for whatever legal purpose it may serve.
                                    </p>
                                    
                                    <div style="margin-top: 50px; display: flex; justify-content: space-between;">
                                        <div>
                                            <div style="margin-bottom: 40px;">
                                                <div class="signature-line" style="width: 200px; margin-top: 20px;"></div>
                                                <p style="margin-top: 0;">Signature of Applicant</p>
                                            </div>
                                            
                                            <p style="margin-top: 10px;">Attested by:</p>
                                        </div>
                                        
                                        <div style="text-align: right;">
                                            <div style="margin-bottom: 40px;">
                                                <div class="signature-line" style="width: 200px; margin-top: 20px;">
                                                    <div class="text-center text-bold" id="preview-punong-barangay">
                                                        <?php echo strtoupper($formData['punong_barangay']); ?>
                                                    </div>
                                                </div>
                                                <p style="margin-top: 0; text-align: center;">Punong Barangay</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="footer-note">
                                        <p class="note-small">(Note: Not Valid without Barangay Dry Seal)</p>
                                        <p class="note-small">This clearance is valid only for <span id="preview-validity"><?php echo $formData['validity_months']; ?></span> months upon issue</p>
                                    </div>
                                    <div class="payment-info">
                                        <p style="margin-bottom: 0;">Paid under OR# <span id="preview-or-number"><?php echo $formData['or_number']; ?></span></p>
                                        <p style="margin-bottom: 0;">On <?php echo $formData['cert_month']; ?> <?php echo $formData['cert_day']; ?>, <?php echo $formData['cert_year']; ?></p>
                                        <p style="margin-bottom: 0;">At Lanit Jaro, Iloilo City</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No valid request was found. Please select a valid Good Moral Certificate request.
                </div>
                <?php endif; ?>

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
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
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
                const orNumber = document.getElementById('or_number').value;
                const validityMonths = document.getElementById('validity_months').value;
                
                // Update the preview content
                document.getElementById('preview-fullname').textContent = fullName.toUpperCase();
                document.getElementById('preview-civil-status').textContent = civilStatus;
                document.getElementById('preview-address').textContent = barangayAddress;
                document.getElementById('preview-day').textContent = certDay;
                document.getElementById('preview-month').textContent = certMonth;
                document.getElementById('preview-year').textContent = certYear;
                document.getElementById('preview-barangay-location').textContent = barangayAddress;
                document.getElementById('preview-punong-barangay').textContent = punongBarangay.toUpperCase();
                document.getElementById('preview-validity').textContent = validityMonths;
                document.getElementById('preview-or-number').textContent = orNumber;
                
                // Update ordinal suffix for the day
                if (certDay) {
                    let suffix = 'th';
                    if (certDay % 10 === 1 && certDay !== 11) suffix = 'st';
                    else if (certDay % 10 === 2 && certDay !== 12) suffix = 'nd';
                    else if (certDay % 10 === 3 && certDay !== 13) suffix = 'rd';
                }
                
                document.querySelector('#preview-day').nextElementSibling.textContent = suffix;
                // Update payment info section
                const paymentDateElement = document.querySelector('.payment-info p:nth-child(2)');
                if (paymentDateElement) {
                    paymentDateElement.textContent = `On ${certMonth} ${certDay}, ${certYear}`;
                }
            }
            
            // Initial update on page load
            updatePreview();
        });
    </script>
</body>
</html>