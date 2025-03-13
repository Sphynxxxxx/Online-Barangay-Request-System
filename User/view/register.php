<?php
require_once "../../backend/connections/config.php"; 
require_once "../../backend/connections/database.php"; 
require_once "../../vendor/autoload.php";


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = $firstName = $middleName = $lastName = "";
$birthday = $gender = $contactNumber = $zone = $houseNumber = $verificationCode = "";
$errors = [];

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST["email"])) {
        $errors["email"] = "Email is required";
    } else {
        $email = test_input($_POST["email"]);
        // Check if email is valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors["email"] = "Invalid email format";
        }
    }
    
    // Validate password
    if (empty($_POST["password"])) {
        $errors["password"] = "Password is required";
    } else {
        // Check password strength
        if (strlen($_POST["password"]) < 8) {
            $errors["password"] = "Password must be at least 8 characters";
        }
    }
    
    // Validate confirm password
    if (empty($_POST["confirmPassword"])) {
        $errors["confirmPassword"] = "Please confirm your password";
    } else {
        // Check if passwords match
        if ($_POST["password"] !== $_POST["confirmPassword"]) {
            $errors["confirmPassword"] = "Passwords do not match";
        }
    }
    
    // Validate first name
    if (empty($_POST["firstName"])) {
        $errors["firstName"] = "First name is required";
    } else {
        $firstName = test_input($_POST["firstName"]);
    }
    
    // Validate middle name (optional)
    if (!empty($_POST["middleName"])) {
        $middleName = test_input($_POST["middleName"]);
    }
    
    // Validate last name
    if (empty($_POST["lastName"])) {
        $errors["lastName"] = "Last name is required";
    } else {
        $lastName = test_input($_POST["lastName"]);
    }
    
    // Validate birthday
    if (empty($_POST["birthday"])) {
        $errors["birthday"] = "Birthday is required";
    } else {
        $birthday = test_input($_POST["birthday"]);
        // Check if date is valid and not in the future
        $birthdayDate = new DateTime($birthday);
        $today = new DateTime();
        if ($birthdayDate > $today) {
            $errors["birthday"] = "Birthday cannot be in the future";
        }
    }
    
    // Validate gender
    if (empty($_POST["gender"])) {
        $errors["gender"] = "Gender is required";
    } else {
        $gender = test_input($_POST["gender"]);
    }
    
    // Validate contact number
    if (empty($_POST["contactNumber"])) {
        $errors["contactNumber"] = "Contact number is required";
    } else {
        $contactNumber = test_input($_POST["contactNumber"]);
        // Simple validation for phone number
        if (!preg_match("/^[0-9]{10,15}$/", $contactNumber)) {
            $errors["contactNumber"] = "Invalid contact number format";
        }
    }
    
    // Validate zone
    if (empty($_POST["zone"])) {
        $errors["zone"] = "Zone is required";
    } else {
        $zone = test_input($_POST["zone"]);
    }
    
    // Validate house number
    if (empty($_POST["houseNumber"])) {
        $errors["houseNumber"] = "House number is required";
    } else {
        $houseNumber = test_input($_POST["houseNumber"]);
    }
    
    // Validate ID upload
    if (empty($_FILES["idUpload"]["name"])) {
        $errors["idUpload"] = "ID upload is required";
    } else {
        // Check file type and size
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES["idUpload"]["type"], $allowedTypes)) {
            $errors["idUpload"] = "Only JPG, PNG, and PDF files are allowed";
        }
        
        if ($_FILES["idUpload"]["size"] > $maxFileSize) {
            $errors["idUpload"] = "File size should not exceed 5MB";
        }
    }
    
    // Validate verification code 
    if (empty($_POST["verificationCode"])) {
        $errors["verificationCode"] = "Verification code is required";
    } else {
        $verificationCode = test_input($_POST["verificationCode"]);
        
        // Check for client-side verification first 
        $clientSideVerified = true; 
        
        // Now check server-side for security
        if (!isset($_SESSION['verification_code']) || 
            !isset($_SESSION['verification_email']) || 
            !isset($_SESSION['verification_expires'])) {
            $errors["verificationCode"] = "No verification code found. Please request a new code.";
            $clientSideVerified = false;
        } else if ($_SESSION['verification_code'] != $verificationCode) {
            $errors["verificationCode"] = "Invalid verification code.";
            $clientSideVerified = false;
        } else if ($_SESSION['verification_email'] != $email) {
            $errors["verificationCode"] = "Email address does not match the one used for verification.";
            $clientSideVerified = false;
        } else if (time() > $_SESSION['verification_expires']) {
            $errors["verificationCode"] = "Verification code has expired. Please request a new one.";
            $clientSideVerified = false;
        }
        
        if (!$clientSideVerified) {
            // Clear expired or invalid verification data
            unset($_SESSION['verification_code']);
            unset($_SESSION['verification_email']);
            unset($_SESSION['verification_expires']);
        }
    }
    
    // Validate terms checkbox
    if (!isset($_POST["termsCheck"])) {
        $errors["termsCheck"] = "You must agree to the terms and conditions";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        try {

            $db = new Database();
            
            $checkEmailSql = "SELECT user_id FROM users WHERE email = ?";
            $result = $db->fetchOne($checkEmailSql, [$email]);
            
            if ($result) {
                $errors["email"] = "Email already registered";
            } else {
                $db->beginTransaction();
                
                $hashedPassword = password_hash($_POST["password"], PASSWORD_DEFAULT);
                
                // Handle file upload
                $targetDir = "uploads/";
                
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                
                // Generate unique filename
                $fileExtension = pathinfo($_FILES["idUpload"]["name"], PATHINFO_EXTENSION);
                $newFileName = uniqid('ID_') . "." . $fileExtension;
                $targetFile = $targetDir . $newFileName;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES["idUpload"]["tmp_name"], $targetFile)) {
                    $insertSql = "INSERT INTO users (email, password, first_name, middle_name, last_name, 
                                 birthday, gender, contact_number, zone, house_number, id_path) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $params = [
                        $email,
                        $hashedPassword,
                        $firstName,
                        $middleName,
                        $lastName,
                        $birthday,
                        $gender,
                        $contactNumber,
                        $zone,
                        $houseNumber,
                        $targetFile
                    ];
                    
                    $userId = $db->insert($insertSql, $params);
                    
                    if ($userId) {
                        // Clear verification code from session
                        unset($_SESSION['verification_code']);
                        unset($_SESSION['verification_email']);
                        unset($_SESSION['verification_expires']);
                        
                        // Commit transaction
                        $db->commit();
                        
                        
                        // Set a success message in session
                        $_SESSION['success_msg'] = "Registration successful! Welcome to the Barangay Document System.";
                        
                        // Redirect to dashboard
                        header("Location: login.php");
                        exit();
                    } else {
                        $db->rollback();
                        $errors["general"] = "Registration failed. Please try again.";
                    }
                } else {
                    $db->rollback();
                    $errors["idUpload"] = "Error uploading ID. Please try again.";
                }
            }
            
            $db->closeConnection();
            
        } catch (Exception $e) {
            // Rollback transaction if active
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            
            $errors["general"] = "An error occurred during registration. Please try again later.";
            error_log("Registration Error: " . $e->getMessage());
        }
    }
}

// Helper function to sanitize form inputs
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Barangay Clearance and Document Request System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/register.css">
    
</head>
<body>
    <div class="content-wrapper">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Barangay Services
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php#services">Services</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Contact</a>
                        </li>
                    </ul>
                    <div class="d-flex">
                        <a href="login.php" class="btn btn-light me-2">Login</a>
                        <a href="../../index.php" class="btn btn-outline-light active">Back</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Registration Form Section -->
        <section class="py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card p-4 p-md-5">
                            <h2 class="text-center mb-4">New Applicant Registration</h2>
                            <p class="text-center text-muted mb-4">Please fill out the form below to create your account</p>
                            
                            <?php if (!empty($errors) && isset($errors["general"])): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo $errors["general"]; ?>
                                </div>
                            <?php elseif (!empty($errors)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <strong>There were errors with your submission. Please check the form and try again.</strong>
                                    <ul>
                                        <?php foreach($errors as $key => $error): ?>
                                            <li><?php echo "$key: $error"; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <div id="emailAlert" class="alert alert-info" style="display: none;"></div>
                            
                            <form id="registrationForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                <!-- Account Information -->
                                <div class="mb-4">
                                    <h5 class="border-bottom pb-2">Account Information</h5>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label required">Email Address</label>
                                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo $email; ?>" required>
                                        <div class="form-text">This will be used as your username</div>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label required">Password</label>
                                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                                        <div class="form-text">Must be at least 8 characters with letters, numbers and special characters</div>
                                        <?php if (isset($errors['password'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirmPassword" class="form-label required">Confirm Password</label>
                                        <input type="password" class="form-control <?php echo isset($errors['confirmPassword']) ? 'is-invalid' : ''; ?>" id="confirmPassword" name="confirmPassword" required>
                                        <?php if (isset($errors['confirmPassword'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['confirmPassword']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Personal Information -->
                                <div class="mb-4">
                                    <h5 class="border-bottom pb-2">Personal Information</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="firstName" class="form-label required">First Name</label>
                                            <input type="text" class="form-control <?php echo isset($errors['firstName']) ? 'is-invalid' : ''; ?>" id="firstName" name="firstName" value="<?php echo $firstName; ?>" required>
                                            <?php if (isset($errors['firstName'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['firstName']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <label for="middleName" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control" id="middleName" name="middleName" value="<?php echo $middleName; ?>">
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <label for="lastName" class="form-label required">Last Name</label>
                                            <input type="text" class="form-control <?php echo isset($errors['lastName']) ? 'is-invalid' : ''; ?>" id="lastName" name="lastName" value="<?php echo $lastName; ?>" required>
                                            <?php if (isset($errors['lastName'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['lastName']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="birthday" class="form-label required">Birthday</label>
                                            <input type="date" class="form-control <?php echo isset($errors['birthday']) ? 'is-invalid' : ''; ?>" id="birthday" name="birthday" value="<?php echo $birthday; ?>" required>
                                            <?php if (isset($errors['birthday'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['birthday']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="gender" class="form-label required">Gender</label>
                                            <select class="form-select <?php echo isset($errors['gender']) ? 'is-invalid' : ''; ?>" id="gender" name="gender" required>
                                                <option value="" <?php echo $gender === '' ? 'selected' : ''; ?> disabled>Select gender</option>
                                                <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Female</option>
                                                <option value="other" <?php echo $gender === 'other' ? 'selected' : ''; ?>>Prefer not to say</option>
                                            </select>
                                            <?php if (isset($errors['gender'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['gender']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Contact Information -->
                                <div class="mb-4">
                                    <h5 class="border-bottom pb-2">Contact Information</h5>
                                    
                                    <div class="mb-3">
                                        <label for="contactNumber" class="form-label required">Contact Number</label>
                                        <input type="tel" class="form-control <?php echo isset($errors['contactNumber']) ? 'is-invalid' : ''; ?>" id="contactNumber" name="contactNumber" value="<?php echo $contactNumber; ?>" required>
                                        <?php if (isset($errors['contactNumber'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['contactNumber']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="zone" class="form-label required">Zone</label>
                                            <select class="form-select <?php echo isset($errors['zone']) ? 'is-invalid' : ''; ?>" id="zone" name="zone" required>
                                                <option value="" <?php echo $zone === '' ? 'selected' : ''; ?> disabled>Select zone</option>
                                                <option value="zone1" <?php echo $zone === 'zone1' ? 'selected' : ''; ?>>Zone 1</option>
                                                <option value="zone2" <?php echo $zone === 'zone2' ? 'selected' : ''; ?>>Zone 2</option>
                                                <option value="zone3" <?php echo $zone === 'zone3' ? 'selected' : ''; ?>>Zone 3</option>
                                                <option value="zone4" <?php echo $zone === 'zone4' ? 'selected' : ''; ?>>Zone 4</option>
                                                <option value="zone5" <?php echo $zone === 'zone5' ? 'selected' : ''; ?>>Zone 5</option>
                                            </select>
                                            <?php if (isset($errors['zone'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['zone']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="col-md-8 mb-3">
                                            <label for="houseNumber" class="form-label required">House Number</label>
                                            <input type="text" class="form-control <?php echo isset($errors['houseNumber']) ? 'is-invalid' : ''; ?>" id="houseNumber" name="houseNumber" value="<?php echo $houseNumber; ?>" required>
                                            <?php if (isset($errors['houseNumber'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['houseNumber']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Verification -->
                                <div class="mb-4">
                                    <h5 class="border-bottom pb-2">Verification</h5>
                                    
                                    <div class="mb-3">
                                        <label for="idUpload" class="form-label required">Upload ID</label>
                                        <input type="file" class="form-control <?php echo isset($errors['idUpload']) ? 'is-invalid' : ''; ?>" id="idUpload" name="idUpload" required>
                                        <div class="form-text">Accepted formats: JPG, PNG, PDF (Max size: 5MB)</div>
                                        <?php if (isset($errors['idUpload'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['idUpload']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="verificationCode" class="form-label required">Verification Code</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control <?php echo isset($errors['verificationCode']) ? 'is-invalid' : ''; ?>" id="verificationCode" name="verificationCode" value="<?php echo $verificationCode; ?>" required>
                                                <?php if (isset($errors['verificationCode'])): ?>
                                                    <div class="invalid-feedback"><?php echo $errors['verificationCode']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-grid">
                                                    <button type="button" class="btn btn-secondary" id="sendCodeBtn">
                                                        <span id="loading"></span>
                                                        <span id="btnText">Send Code</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input <?php echo isset($errors['termsCheck']) ? 'is-invalid' : ''; ?>" id="termsCheck" name="termsCheck">
                                        <label class="form-check-label" for="termsCheck">
                                            I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                                        </label>
                                        <?php if (isset($errors['termsCheck'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['termsCheck']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">Register</button>
                                    <p class="text-center mt-3">
                                        Already have an account? <a href="login.php">Login here</a>
                                    </p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white">
        <div class="container">
            <div class="row">
                <p>&copy; 2025 Barangay Clearance and Document Request System</p>
                <div class="col-md-6 text-center text-md-end">
                    <a href="#" class="text-muted me-3"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-muted me-3"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="text-muted"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sendCodeBtn').addEventListener('click', function() {
            const email = document.getElementById('email').value;
            const loading = document.getElementById('loading');
            const btnText = document.getElementById('btnText');
            const alertBox = document.getElementById('emailAlert');
            
            if (email === '') {
                alert('Please enter your email address first');
                return;
            }
            
            // Email validation using regex
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address');
                return;
            }
            
            // Show loading state
            if (loading) loading.style.display = 'inline-block';
            if (btnText) btnText.textContent = 'Sending...';
            this.disabled = true;
            
            // Generate a random 6-digit code
            const code = Math.floor(100000 + Math.random() * 900000);
            
            // Send email using fetch API
            fetch('../../backend/connections/send_email_verification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    code: code
                })
            })
            .then(response => response.json())
            .then(data => {
                if (loading) loading.style.display = 'none';
                
                if (data.success) {
                    // Store the code in sessionStorage for verification with expiration time (30 minutes)
                    const expirationTime = Date.now() + (30 * 60 * 1000);
                    sessionStorage.setItem('verificationCode', code);
                    sessionStorage.setItem('verificationEmail', email);
                    sessionStorage.setItem('verificationExpires', expirationTime.toString());
                    
                    saveCodeToSession(email, code);
                    
                    // Start the countdown timer
                    startVerificationCountdown();
                    
                    alertBox.innerHTML = 'Verification code sent to your email. Please check your inbox.';
                    alertBox.classList.remove('alert-danger');
                    alertBox.classList.add('alert-success');
                    alertBox.style.display = 'block';
                    
                    if (btnText) btnText.textContent = 'Resend Code';
                } else {
                    alertBox.innerHTML = 'Error: ' + (data.error || 'Failed to send code');
                    alertBox.classList.remove('alert-success');
                    alertBox.classList.add('alert-danger');
                    alertBox.style.display = 'block';
                    
                    if (btnText) btnText.textContent = 'Try Again';
                }
                this.disabled = false;
            })
            .catch(error => {
                if (loading) loading.style.display = 'none';
                
                alertBox.innerHTML = 'Error connecting to server. Please try again.';
                alertBox.classList.remove('alert-success');
                alertBox.classList.add('alert-danger');
                alertBox.style.display = 'block';
                
                if (btnText) btnText.textContent = 'Try Again';
                this.disabled = false;
                console.error('Error:', error);
            });
        });

        // Function to save verification code to PHP session
        function saveCodeToSession(email, code) {
            fetch('../../backend/connections/save_verification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    code: code
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Code saved to session:', data.success);
            })
            .catch(error => {
                console.error('Error saving code to session:', error);
            });
        }

        // Function to start countdown timer for verification code
        function startVerificationCountdown() {
            // Clear any existing countdown element
            const existingCountdown = document.getElementById('verification-countdown');
            if (existingCountdown) {
                existingCountdown.remove();
            }
            
            // Clear any existing interval
            if (window.countdownInterval) {
                clearInterval(window.countdownInterval);
            }
            
            // Create countdown element
            const countdownElement = document.createElement('div');
            countdownElement.id = 'verification-countdown';
            countdownElement.className = 'mt-2 small text-muted';
            
            // Append it after the verification code input
            const verificationInputParent = document.getElementById('verificationCode').closest('.col-md-6');
            verificationInputParent.appendChild(countdownElement);
            
            // Calculate end time
            const expirationTime = parseInt(sessionStorage.getItem('verificationExpires'));
            
            // Start the countdown
            window.countdownInterval = setInterval(function() {
                const currentTime = Date.now();
                const timeLeft = Math.round((expirationTime - currentTime) / 1000);
                
                if (timeLeft <= 0) {
                    // Code has expired
                    clearInterval(window.countdownInterval);
                    countdownElement.textContent = 'Verification code has expired. Please request a new one.';
                    countdownElement.className = 'mt-2 small text-danger';
                    
                    // Clear the verification data
                    sessionStorage.removeItem('verificationCode');
                    sessionStorage.removeItem('verificationEmail');
                    sessionStorage.removeItem('verificationExpires');
                    
                    // Clear the server-side session data too
                    fetch('clear_verification.php', {
                        method: 'POST'
                    });
                } else {
                    // Format and display the time left
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    countdownElement.textContent = `Code expires in: ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
                }
            }, 1000);
        }

        // Check if we need to restore the countdown timer when page loads
        window.addEventListener('DOMContentLoaded', function() {
            const expirationTime = sessionStorage.getItem('verificationExpires');
            if (expirationTime) {
                const currentTime = Date.now();
                const timeLeft = Math.round((parseInt(expirationTime) - currentTime) / 1000);
                
                if (timeLeft > 0) {
                    // There's still time left, start the countdown
                    startVerificationCountdown();
                } else {
                    // Code has expired, clear the data
                    sessionStorage.removeItem('verificationCode');
                    sessionStorage.removeItem('verificationEmail');
                    sessionStorage.removeItem('verificationExpires');
                    
                    // Clear server-side session too
                    fetch('clear_verification.php', {
                        method: 'POST'
                    });
                }
            }
        });

        // Password validation
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const hasMinLength = password.length >= 8;
            const hasLetter = /[a-zA-Z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            
            // Update password strength indicator
            if (hasMinLength && hasLetter && hasNumber && hasSpecial) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
            }
        });

        // Confirm password validation
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password === confirmPassword) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });

        // Check verification code from sessionStorage on form submission
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const verificationCode = document.getElementById('verificationCode').value;
            const email = document.getElementById('email').value;
            
            // Get stored verification data
            const storedCode = sessionStorage.getItem('verificationCode');
            const storedEmail = sessionStorage.getItem('verificationEmail');
            const expirationTime = sessionStorage.getItem('verificationExpires');
            
            if (!storedCode || !storedEmail || !expirationTime) {
                e.preventDefault();
                alert('No valid verification code found. Please request a verification code.');
                return false;
            }
            
            // Check if the code matches
            if (storedCode !== verificationCode || storedEmail !== email) {
                e.preventDefault();
                alert('Invalid verification code or email does not match the one used for verification.');
                return false;
            }
            
            // Check if the code has expired
            const currentTime = Date.now();
            if (currentTime > parseInt(expirationTime)) {
                e.preventDefault();
                alert('Verification code has expired. Please request a new code.');
                
                // Clear the expired verification data
                sessionStorage.removeItem('verificationCode');
                sessionStorage.removeItem('verificationEmail');
                sessionStorage.removeItem('verificationExpires');
                
                return false;
            }
        });
    </script>
    
</body>
</html>