<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "../../../backend/connections/config.php";

$showAlert = false;
$alertType = "";
$alertMessage = "";
$requestDetails = null;
$clearanceDetails = null;
$officials = null;

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request ID.");
}

$requestId = intval($_GET['id']);

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
$officialsSql = "SELECT * FROM barangay_officials ORDER BY id";
$officialsResult = $conn->query($officialsSql);
$officials = $officialsResult->fetch_all(MYSQLI_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'] ?? $requestDetails['fullname'];
    $address = $_POST['address'] ?? $requestDetails['address'];
    $age = $_POST['age'] ?? $requestDetails['age'];
    $civilStatus = $_POST['civil_status'] ?? $requestDetails['civil_status'];
    $residencyStatus = $_POST['residency_status'] ?? $requestDetails['residency_status'];
    $purpose = $requestDetails['purpose'];
    $issuedDate = $_POST['issued_date'] ?? date('Y-m-d');
    
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
                size: A4;
            } 

            body {
                font-family: "Times New Roman", serif;
                color: black;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                line-height: 1.4;
            }

            .certificate-container {
                display: flex;
                border: 3px solid black;
                min-height: 100vh;
                padding: 0;
            }

            .certificate-content {
                width: 70%;
                padding: 30px;
                font-size: 14px;
                position: relative;
                background-image: url('../assets/6e958e5d-27fa-4beb-9010-b695c9af6470.jpg');
                background-repeat: no-repeat;
                background-position: center top 100px; 
                background-size: 250px 250px; 
                background-opacity: 0.5; 
                background-color: rgba(255, 255, 255, 0.95); 
                background-blend-mode: overlay;
            }

            .certificate-content::before {
                content: '';
                position: absolute;
                top: 80px; 
                left: 50%;
                transform: translateX(-50%);
                width: 250px;
                height: 250px;
                background-image: url('../assets/6e958e5d-27fa-4beb-9010-b695c9af6470.jpg');
                background-repeat: no-repeat;
                background-size: contain;
                opacity: 2;
                z-index: -1; 
                pointer-events: none; 
            }
            .print-only {
                display: block !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .officials-section {
                width: 30%;
                padding: 20px;
                border-right: 2px solid black;
                display: block !important;
            }

            .officials-section h6 {
                border-bottom: 2px solid black;
                padding-bottom: 8px;
                margin-bottom: 15px;
                font-weight: bold;
                text-align: center;
            }

            .officials-section ul {
                list-style-type: none;
                padding: 0;
                margin: 0;
            }

            .officials-section li {
                margin-bottom: 12px;
                font-size: 11px;
                text-align: center;
            }

            .header-section {
                text-align: center;
                margin-bottom: 30px;
            }

            .header-section h6 {
                margin: 2px 0;
                font-size: 12px;
                font-weight: normal;
            }

            .header-section h5 {
                margin: 2px 0;
                font-size: 14px;
                font-weight: bold;
            }

            .header-section h4 {
                margin: 15px 0 10px 0;
                font-size: 16px;
                font-weight: bold;
                letter-spacing: 2px;
            }

            .certification-content {
                text-align: justify;
                line-height: 1.6;
                margin: 20px 0;
            }

            .person-details {
                margin: 20px 0;
                text-align: center;
            }

            .person-details p {
                margin: 8px 0;
                font-size: 14px;
            }

            .signature-section {
                text-align: center;
                margin-top: 40px;
            }

            .signature-section p {
                margin-bottom: 40px;
            }

            .signature-line {
                border-bottom: 1px solid black;
                width: 200px;
                margin: 5px auto 5px auto;
                height: 40px;
            }

            .date-location {
                margin: 30px 0;
                text-align: left;
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
                        <form id="officialsForm" method="POST" action="update-officials.php">
                            <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                            
                            <div id="officialsContainer">
                                <div class="row g-2 official-row mb-2">
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm" name="official_names[]" 
                                            placeholder="Official Name">
                                    </div>
                                    <div class="col-4">
                                        <select class="form-select form-select-sm" name="official_positions[]">
                                            <option value="Punong Barangay" selected>Punong Barangay</option>
                                            <option value="Sangguniang Barangay Member">Sangguniang Barangay Member</option>
                                            <option value="SK Chairman">SK Chairman</option>
                                            <option value="Barangay Treasurer">Barangay Treasurer</option>
                                            <option value="Barangay Secretary">Barangay Secretary</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-1">
                                        <button type="button" class="btn btn-danger btn-sm w-100 remove-official" disabled>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" id="addOfficialBtn" class="btn btn-success btn-sm w-100 mt-2">
                                <i class="bi bi-plus me-1"></i>Add Official
                            </button>

                            <div class="row g-2 mt-2">
                                <div class="col-6">
                                    <button type="button" id="clearOfficialsBtn" class="btn btn-warning btn-sm w-100">
                                        <i class="bi bi-trash me-1"></i>Clear List
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                        <i class="bi bi-save me-1"></i>Save Officials
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Clearance Preview Container -->
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-4 mt-4 no-print">
                        <div class="card h-100">
                            <div class="card-header bg-secondary text-white">
                                <i class="bi bi-people me-2"></i>Barangay Officials
                            </div>
                            <div class="card-body" data-officials-list>
                            <?php if (!empty($officials)): ?>
                                <ul class="list-unstyled">
                                    <?php foreach ($officials as $official): ?>
                                    <li class="mb-2">
                                        <strong><?php echo htmlspecialchars($official['position']); ?>:</strong><br>
                                        <?php echo htmlspecialchars($official['name']); ?>
                                    </li>
                                    <?php endforeach; ?>
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
                                    <?php if (!empty($officials)): ?>
                                        <h6>BARANGAY OFFICIALS</h6>
                                        <ul>
                                            <?php foreach ($officials as $official): ?>
                                            <li>
                                                <strong><?php echo htmlspecialchars($official['position']); ?>:</strong><br>
                                                <?php echo htmlspecialchars($official['name']); ?>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>

                                <!-- Certificate Content -->
                                <div class="certificate-content">
                                    <div class="header-section">
                                        <h5>REPUBLIC OF THE PHILIPPINES</h5>
                                        <h6>City of Iloilo</h6>
                                        <h6>District of Jaro</h6>
                                        <h5>BARANGAY LANIT</h5>
                                        <h6 class="mt-3">OFFICE OF THE PUNONG BARANGAY</h6>
                                        <h4 class="mt-4">CERTIFICATION</h4>
                                    </div>
                                    
                                    <div class="certification-content">
                                        <p><strong>TO WHOM IT MAY CONCERN:</strong></p>
                                        
                                        <p>This is to certify that <strong><?php echo htmlspecialchars(strtoupper($requestDetails['fullname'])); ?></strong> 
                                        of legal age, <?php echo htmlspecialchars(strtolower($requestDetails['civil_status'])); ?>, is a 
                                        resident of <strong><?php echo htmlspecialchars(strtoupper($requestDetails['address'])); ?></strong>, 
                                        BARANGAY LANIT, JARO, ILOILO CITY.</p>
                                        
                                        <p>This certification is issued upon the request of the above-named person for the purpose of 
                                        <strong><?php echo htmlspecialchars(strtoupper($requestDetails['purpose'])); ?></strong>.</p>
                                    </div>
                                    
                                    <div class="date-location">
                                        <p>Issued this <strong><?php echo date('jS'); ?></strong> day of 
                                        <strong><?php echo date('F'); ?></strong>, <strong><?php echo date('Y'); ?></strong> 
                                        at Barangay Lanit, Jaro, Iloilo City, Philippines.</p>
                                    </div>
                                    
                                    <div class="signature-section">
                                        <p>Certified by:</p>
                                        <?php 
                                        // Get Punong Barangay name from officials list
                                        $punongBarangayName = '';
                                        if (!empty($officials)) {
                                            foreach ($officials as $official) {
                                                if (strtolower($official['position']) == 'punong barangay') {
                                                    $punongBarangayName = strtoupper($official['name']);
                                                    break;
                                                }
                                            }
                                        }
                                        ?>
                                        <?php if ($punongBarangayName): ?>
                                            <p style="text-decoration: underline; margin-bottom: 5px;"><strong><?php echo htmlspecialchars($punongBarangayName); ?></strong></p>
                                        <?php else: ?>
                                            <div class="signature-line"></div>
                                        <?php endif; ?>
                                        <p><strong>PUNONG BARANGAY</strong></p>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const officialsContainer = document.getElementById('officialsContainer');
            const addOfficialBtn = document.getElementById('addOfficialBtn');
            const officialsForm = document.getElementById('officialsForm');
            const clearOfficialsBtn = document.getElementById('clearOfficialsBtn');

            function createOfficialRow() {
                const row = document.createElement('div');
                row.className = 'row g-2 official-row mb-2';
                row.innerHTML = `
                    <div class="col-7">
                        <input type="text" class="form-control form-control-sm" name="official_names[]" 
                            placeholder="Official Name" required>
                    </div>
                    <div class="col-4">
                        <select class="form-select form-select-sm" name="official_positions[]" required>
                            <option value="">Select Position</option>
                            <option value="Punong Barangay">Punong Barangay</option>
                            <option value="Sangguniang Barangay Member">Sangguniang Barangay Member</option>
                            <option value="SK Chairman">SK Chairman</option>
                            <option value="Barangay Treasurer">Barangay Treasurer</option>
                            <option value="Barangay Secretary">Barangay Secretary</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-1">
                        <button type="button" class="btn btn-danger btn-sm w-100 remove-official">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                return row;
            }

            function updateOfficialsPanel() {
                const officialsPanelList = document.querySelector('[data-officials-list]');
                const listContainer = document.createElement('ul');
                
                listContainer.className = 'list-unstyled';

                const nameInputs = officialsContainer.querySelectorAll('input[name="official_names[]"]');
                const positionSelects = officialsContainer.querySelectorAll('select[name="official_positions[]"]');

                let hasOfficials = false;
                nameInputs.forEach((nameInput, index) => {
                    const name = nameInput.value.trim();
                    const position = positionSelects[index].value;

                    if (name && position) {
                        const li = document.createElement('li');
                        li.className = 'mb-2';
                        li.innerHTML = `
                            <strong>${position}:</strong><br>
                            ${name}
                        `;
                        listContainer.appendChild(li);
                        hasOfficials = true;
                    }
                });

                if (hasOfficials) {
                    officialsPanelList.innerHTML = '';
                    officialsPanelList.appendChild(listContainer);
                } else {
                    officialsPanelList.innerHTML = '<p class="text-muted text-center">No officials information available.</p>';
                }
            }

            function clearOfficialsList() {
                if (!confirm('Are you sure you want to clear all officials from the list?')) {
                    return;
                }

                const rows = officialsContainer.querySelectorAll('.official-row');
                
                for (let i = 1; i < rows.length; i++) {
                    rows[i].remove();
                }

                const officialsPanelList = document.querySelector('[data-officials-list]');
                officialsPanelList.innerHTML = '<p class="text-muted text-center">No officials information available.</p>';
            }

            addOfficialBtn.addEventListener('click', function() {
                const row = createOfficialRow();
                officialsContainer.appendChild(row);
                row.querySelector('input[name="official_names[]"]').focus();
            });

            officialsContainer.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.remove-official');
                if (removeBtn) {
                    const rows = officialsContainer.querySelectorAll('.official-row');
                    if (rows.length > 1) {
                        removeBtn.closest('.official-row').remove();
                        updateOfficialsPanel();
                    } else {
                        alert('At least one official must be maintained.');
                    }
                }
            });

            if (clearOfficialsBtn) {
                clearOfficialsBtn.addEventListener('click', clearOfficialsList);
            }

            officialsForm.addEventListener('submit', function(e) {
                const rows = officialsContainer.querySelectorAll('.official-row');
                let isValid = true;

                rows.forEach(row => {
                    const nameInput = row.querySelector('input[name="official_names[]"]');
                    const positionSelect = row.querySelector('select[name="official_positions[]"]');

                    if (!nameInput.value.trim()) {
                        nameInput.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        nameInput.classList.remove('is-invalid');
                    }

                    if (!positionSelect.value) {
                        positionSelect.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        positionSelect.classList.remove('is-invalid');
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    const firstInvalidInput = officialsForm.querySelector('.is-invalid');
                    if (firstInvalidInput) {
                        firstInvalidInput.focus();
                    }
                } else {
                    updateOfficialsPanel();
                }
            });

            officialsContainer.addEventListener('change', function(e) {
                if (e.target.name === 'official_positions[]') {
                    const positions = Array.from(officialsContainer.querySelectorAll('select[name="official_positions[]"]'))
                        .map(select => select.value);
                    
                    const selectElements = officialsContainer.querySelectorAll('select[name="official_positions[]"]');
                    
                    selectElements.forEach(select => {
                        Array.from(select.options).forEach(option => {
                            if (option.value !== 'Other') {
                                option.disabled = false;
                            }
                        });

                        positions.forEach(pos => {
                            if (pos && pos !== 'Other') {
                                const optionToDisable = select.querySelector(`option[value="${pos}"]`);
                                if (optionToDisable && optionToDisable !== select.querySelector('option:checked')) {
                                    optionToDisable.disabled = true;
                                }
                            }
                        });
                    });
                }
            });
        });
    </script>
</body>
</html>