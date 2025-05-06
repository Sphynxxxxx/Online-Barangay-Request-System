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
                      rd.fullname
                    FROM requests r
                    JOIN users u ON r.user_id = u.user_id
                    LEFT JOIN request_details rd ON r.request_id = rd.request_id
                    WHERE r.request_id = ? AND r.document_type = 'first_time_jobseeker'";
        
        $stmt = $conn->prepare($requestSql);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $requestDetails = $result->fetch_assoc();
            
            // Check if certificate details already exist in the database
            $certSql = "SELECT * FROM jobseeker_certificates WHERE request_id = ?";
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
            $alertMessage = "Request not found or not a First Time Jobseeker Certificate.";
        }
        $stmt->close();
    }

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_certificate'])) {
        $requestId = intval($_POST['request_id']);
        $certificateNumber = trim($_POST['certificate_number']);
        $fullName = trim($_POST['full_name']);
        $address = trim($_POST['address']);
        $yearsResidency = intval($_POST['years_residency']);
        $certDay = trim($_POST['cert_day']);
        $certMonth = trim($_POST['cert_month']);
        $certYear = trim($_POST['cert_year']);
        $cityMunicipality = trim($_POST['city_municipality']);
        $validUntil = trim($_POST['valid_until']);
        $punongBarangay = trim($_POST['punong_barangay']);
        $pbDate = trim($_POST['pb_date']);
        $kagawad = trim($_POST['kagawad']);
        $kagawadDate = trim($_POST['kagawad_date']);
        
        // Check if certificate already exists
        $checkSql = "SELECT certificate_id FROM jobseeker_certificates WHERE request_id = ?";
        $stmtCheck = $conn->prepare($checkSql);
        $stmtCheck->bind_param("i", $requestId);
        $stmtCheck->execute();
        $checkResult = $stmtCheck->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing certificate
            $certificateId = $checkResult->fetch_assoc()['certificate_id'];
            
            $updateSql = "UPDATE jobseeker_certificates 
                         SET certificate_number = ?, 
                             full_name = ?, 
                             address = ?, 
                             years_residency = ?, 
                             cert_day = ?, 
                             cert_month = ?, 
                             cert_year = ?, 
                             city_municipality = ?, 
                             valid_until = ?, 
                             punong_barangay = ?, 
                             pb_date = ?, 
                             kagawad = ?, 
                             kagawad_date = ?, 
                             updated_at = NOW() 
                         WHERE certificate_id = ?";
            
            $stmtUpdate = $conn->prepare($updateSql);
            $stmtUpdate->bind_param("sssisississssi", 
                $certificateNumber, 
                $fullName, 
                $address, 
                $yearsResidency, 
                $certDay, 
                $certMonth, 
                $certYear,
                $cityMunicipality,
                $validUntil,
                $punongBarangay,
                $pbDate,
                $kagawad,
                $kagawadDate,
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
            $insertSql = "INSERT INTO jobseeker_certificates 
                         (request_id, certificate_number, full_name, address, years_residency, 
                          cert_day, cert_month, cert_year, city_municipality, valid_until,
                          punong_barangay, pb_date, kagawad, kagawad_date, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmtInsert = $conn->prepare($insertSql);
            $stmtInsert->bind_param("isssississssss", 
                $requestId, 
                $certificateNumber, 
                $fullName, 
                $address, 
                $yearsResidency, 
                $certDay, 
                $certMonth, 
                $certYear,
                $cityMunicipality,
                $validUntil,
                $punongBarangay,
                $pbDate,
                $kagawad,
                $kagawadDate
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

// Default value for valid until (1 year from now)
$validUntilDefault = date("F j, Y", strtotime("+1 year"));

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

// Pre-populate form data
$formData = [
    'certificate_number' => $certificateDetails['certificate_number'] ?? '',
    'full_name' => $certificateDetails['full_name'] ?? ($requestDetails['fullname'] ?? ($requestDetails['first_name'] . ' ' . $requestDetails['last_name'])),
    'address' => $certificateDetails['address'] ?? $userAddress,
    'years_residency' => $certificateDetails['years_residency'] ?? '5',
    'cert_day' => $certificateDetails['cert_day'] ?? $currentDay,
    'cert_month' => $certificateDetails['cert_month'] ?? $currentMonth,
    'cert_year' => $certificateDetails['cert_year'] ?? $currentYear,
    'city_municipality' => $certificateDetails['city_municipality'] ?? 'Your City/Municipality',
    'valid_until' => $certificateDetails['valid_until'] ?? $validUntilDefault,
    'punong_barangay' => $certificateDetails['punong_barangay'] ?? 'BARANGAY CAPTAIN NAME',
    'pb_date' => $certificateDetails['pb_date'] ?? date("F j, Y"),
    'kagawad' => $certificateDetails['kagawad'] ?? 'KAGAWAD NAME',
    'kagawad_date' => $certificateDetails['kagawad_date'] ?? date("F j, Y")
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit First Time Jobseeker Certificate - Barangay Document System</title>
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
        
        .certificate-title {
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .certificate-subtitle {
            text-align: center;
            font-size: 14pt;
            margin-bottom: 15px;
        }
        
        .certificate-content {
            font-size: 12pt;
            line-height: 1.4;
            text-align: justify;
        }
        
        .certificate-number {
            text-align: right;
            margin-bottom: 10px;
        }
        
        .certificate-number-line {
            width: 200px;
            border-bottom: 1px solid #000;
            display: inline-block;
            text-align: center;
        }
        
        .signature-line {
            width: 250px;
            border-bottom: 1px solid #000;
            margin: 25px 0 2px;
            display: inline-block;
            text-align: center;
        }
        
        .signature-name {
            font-weight: bold;
            text-align: center;
            width: 250px;
        }
        
        .signature-position {
            text-align: center;
            width: 250px;
            margin-top: 0;
        }
        
        .certificate-footer {
            position: absolute;
            bottom: 0.5in;
            left: 0.7in;
        }
        
        .right-aligned {
            text-align: right;
        }
        
        .fit-content p {
            margin: 6px 0;
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
                        <i class="bi bi-file-earmark-text me-2"></i>Edit First Time Jobseeker Certificate
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
                                        <label for="certificate_number" class="form-label">Certificate Number</label>
                                        <input type="text" class="form-control" id="certificate_number" name="certificate_number" 
                                               value="<?php echo $formData['certificate_number']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo $formData['full_name']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Complete Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2" 
                                                  required><?php echo $formData['address']; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="years_residency" class="form-label">Years of Residency</label>
                                        <input type="number" class="form-control" id="years_residency" name="years_residency" 
                                               value="<?php echo $formData['years_residency']; ?>" min="1" required>
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
                                        <label for="city_municipality" class="form-label">City/Municipality</label>
                                        <input type="text" class="form-control" id="city_municipality" name="city_municipality" 
                                               value="<?php echo $formData['city_municipality']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="valid_until" class="form-label">Valid Until</label>
                                        <input type="text" class="form-control" id="valid_until" name="valid_until" 
                                               value="<?php echo $formData['valid_until']; ?>" required>
                                        <div class="form-text">Format: Month Day, Year (e.g., January 31, 2026)</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="punong_barangay" class="form-label">Punong Barangay</label>
                                        <input type="text" class="form-control" id="punong_barangay" name="punong_barangay" 
                                               value="<?php echo $formData['punong_barangay']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="pb_date" class="form-label">Punong Barangay Date</label>
                                        <input type="text" class="form-control" id="pb_date" name="pb_date" 
                                               value="<?php echo $formData['pb_date']; ?>" required>
                                        <div class="form-text">Format: Month Day, Year (e.g., May 7, 2025)</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="kagawad" class="form-label">Kagawad Name</label>
                                        <input type="text" class="form-control" id="kagawad" name="kagawad" 
                                               value="<?php echo $formData['kagawad']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="kagawad_date" class="form-label">Kagawad Date</label>
                                        <input type="text" class="form-control" id="kagawad_date" name="kagawad_date" 
                                               value="<?php echo $formData['kagawad_date']; ?>" required>
                                        <div class="form-text">Format: Month Day, Year (e.g., May 7, 2025)</div>
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
                                <div class="certificate-number text-end mb-3">
                                    <p class="mb-0">Barangay Certificate Number:</p>
                                    <div class="certificate-number-line" id="certificate-number-display"></div>
                                </div>
                                
                                <div class="certificate-title">BARANGAY CERTIFICATION</div>
                                <div class="certificate-subtitle">(First Time Jobseekers Assistance Act – RA 11261)</div>
                                
                                <div class="certificate-content fit-content">
                                    <p>This is to certify that Mr./Ms. <strong><u><?php echo $formData['full_name']; ?></u></strong>, a resident of <strong><u><?php echo $formData['address']; ?></u></strong> (complete address) for <strong><u><?php echo $formData['years_residency']; ?></u></strong> years, is a qualified availlee of <strong>RA 11261</strong> or the <strong>First Time Jobseekers Act of 2019</strong>.</p>
                                    
                                    <p>I further certify that the holder/bearer was informed of his/her rights, including the duties and responsibilities accorded by RA 11261 through the <strong>Oath of Undertaking</strong> he/she has signed and executed in the presence of our Barangay Official/s.</p>
                                    
                                    <p>Signed this <strong><u><?php echo $formData['cert_day']; ?></u></strong>day of <strong><u><?php echo $formData['cert_month']; ?></u></strong>, 20<strong><u><?php echo substr($formData['cert_year'], -2); ?></u></strong>, in the City/Municipality of <strong><u><?php echo $formData['city_municipality']; ?></u></strong>.</p>
                                    
                                    <p>This certification is valid only until <strong><u><?php echo $formData['valid_until']; ?></u></strong>, one (1) year from the issuance.</p>
                                </div>
                                
                                <div class="right-aligned mt-3">
                                    <p class="mb-0">(Signature over printed name w/ dry seal– don't type this)</p>
                                    <div class="signature-line" id="punongBarangayLine"></div>
                                    <p class="mt-0 mb-0">Punong Barangay</p>
                                </div>
                                
                                <div class="right-aligned mt-2">
                                    <div class="signature-line" id="pbDateLine"></div>
                                    <p class="mt-0 mb-0">Date</p>
                                </div>
                                
                                <div class="right-aligned mt-3">
                                    <p class="mb-1">Witnessed by:</p>
                                    
                                    <p class="mb-0">(Signature over printed name – don't type this)</p>
                                    <div class="signature-line" id="kagawadLine"></div>
                                    <p class="mt-0 mb-0">Kagawad</p>
                                </div>
                                
                                <div class="right-aligned mt-2">
                                    <div class="signature-line" id="kagawadDateLine"></div>
                                    <p class="mt-0 mb-0">Date</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No valid request was found. Please select a valid First Time Jobseeker Certificate request.
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
        // Function to update certificate preview in real-time
        document.addEventListener('DOMContentLoaded', function() {
            const formInputs = document.querySelectorAll('#certificateForm input, #certificateForm textarea, #certificateForm select');
            
            formInputs.forEach(input => {
                input.addEventListener('input', updatePreview);
            });
            
            function updatePreview() {
                // Get all form values
                const fullName = document.getElementById('full_name').value;
                const address = document.getElementById('address').value;
                const yearsResidency = document.getElementById('years_residency').value;
                const certDay = document.getElementById('cert_day').value;
                const certMonth = document.getElementById('cert_month').value;
                const certYear = document.getElementById('cert_year').value;
                const certYearShort = certYear.toString().substr(-2);
                const cityMunicipality = document.getElementById('city_municipality').value;
                const validUntil = document.getElementById('valid_until').value;
                const certificateNumber = document.getElementById('certificate_number').value;
                
                // Get signature information
                const punongBarangay = document.getElementById('punong_barangay').value;
                const pbDate = document.getElementById('pb_date').value;
                const kagawad = document.getElementById('kagawad').value;
                const kagawadDate = document.getElementById('kagawad_date').value;
                
                // Update certificate number
                const certNumberElement = document.getElementById('certificate-number-display');
                if (certNumberElement) {
                    certNumberElement.textContent = certificateNumber;
                }
                
                // Update the preview content
                const preview = document.getElementById('certificatePreview');
                
                // Update name, address and years in the first paragraph
                const paragraphs = preview.querySelectorAll('.certificate-content p');
                if (paragraphs.length >= 1) {
                    paragraphs[0].innerHTML = `This is to certify that Mr./Ms. <strong><u>${fullName}</u></strong>, a resident of <strong><u>${address}</u></strong> (complete address) for <strong><u>${yearsResidency}</u></strong> years, is a qualified availlee of <strong>RA 11261</strong> or the <strong>First Time Jobseekers Act of 2019</strong>.`;
                }
                
                // Update date info in the third paragraph
                if (paragraphs.length >= 3) {
                    paragraphs[2].innerHTML = `Signed this <strong><u>${certDay}</u></strong>day of <strong><u>${certMonth}</u></strong>, 20<strong><u>${certYearShort}</u></strong>, in the City/Municipality of <strong><u>${cityMunicipality}</u></strong>.`;
                }
                
                // Update valid until date in the fourth paragraph
                if (paragraphs.length >= 4) {
                    paragraphs[3].innerHTML = `This certification is valid only until <strong><u>${validUntil}</u></strong>, one (1) year from the issuance.`;
                }
                
                // Update Punong Barangay, Date, Kagawad, and Date
                document.getElementById('punongBarangayLine').textContent = punongBarangay;
                document.getElementById('pbDateLine').textContent = pbDate;
                document.getElementById('kagawadLine').textContent = kagawad;
                document.getElementById('kagawadDateLine').textContent = kagawadDate;
            }
            
            // Initial update on page load
            updatePreview();
        });
    </script>
</body>
</html>