<?php
// Start session securely
session_start();

// Database connection parameters
$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "barangay_request_system"; 

// Initialize variables
$email = "";
$errors = [];
$showAlert = false;
$alertType = "";
$alertMessage = "";

// Check if user is logged in but account might have been deleted
if (isset($_SESSION['user_id'])) {
    // Create database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
    } else {
        $userId = $_SESSION['user_id'];
        
        // Check if user still exists in database
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows == 0) {
            // User doesn't exist anymore, log them out
            session_unset();
            session_destroy();
            
            // Start a new session to display message
            session_start();
            $_SESSION['success_msg'] = "Your account has been deleted. Please contact support if this was not intended.";
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Redirect logged-in users to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Display success message from registration
if (isset($_SESSION['success_msg'])) {
    $showAlert = true;
    $alertType = "success";
    $alertMessage = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate email
    if (empty($_POST["email"])) {
        $errors["email"] = "Email is required";
    } else {
        $email = sanitize_input($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors["email"] = "Invalid email format";
        }
    }
    
    // Validate password
    if (empty($_POST["password"])) {
        $errors["password"] = "Password is required";
    }

    // Proceed if no validation errors
    if (empty($errors)) {
        // Create database connection
        $conn = new mysqli($servername, $username, $password, $dbname);
        
        // Check connection
        if ($conn->connect_error) {
            error_log("Connection failed: " . $conn->connect_error);
            $showAlert = true;
            $alertType = "danger";
            $alertMessage = "An unexpected error occurred. Please try again later.";
        } else {
            // Prepare SQL statement to get user data including status
            $stmt = $conn->prepare("SELECT user_id, email, password, first_name, last_name, user_type, status FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Check if account is pending
                if ($user['status'] == 'pending') {
                    $showAlert = true;
                    $alertType = "warning";
                    $alertMessage = "Your account is still pending approval. Please check back later.";
                }
                // Check if account is inactive
                else if ($user['status'] == 'inactive') {
                    $showAlert = true;
                    $alertType = "danger";
                    $alertMessage = "Your account has been deactivated. Please contact support.";
                }
                // Verify password for active accounts
                else if ($user['status'] == 'active' && password_verify($_POST["password"], $user['password'])) {
                    // Password is correct, regenerate session ID for security
                    session_regenerate_id(true);

                    // Store user details in session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_type'] = $user['user_type'];

                    // Log user login
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $user_id = $user['user_id'];
                    $action = 'login';
                    
                    // Create user_logs table if it doesn't exist
                    try {
                        $log_stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                        $log_stmt->bind_param("iss", $user_id, $action, $ip_address);
                        $log_stmt->execute();
                        $log_stmt->close();
                    } catch (Exception $e) {
                        error_log("Warning: Could not log login activity: " . $e->getMessage());
                    }

                    // Update last login timestamp
                    try {
                        $update_stmt = $conn->prepare("UPDATE users SET updated_at = NOW() WHERE user_id = ?");
                        $update_stmt->bind_param("i", $user_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    } catch (Exception $e) {
                        error_log("Warning: Could not update last login time: " . $e->getMessage());
                    }

                    // Close the connection
                    $stmt->close();
                    $conn->close();

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    // Incorrect password
                    $showAlert = true;
                    $alertType = "danger";
                    $alertMessage = "Invalid email or password";
                    error_log("Login failed for email: $email (incorrect password)");
                    sleep(1); // Delay to prevent brute force
                }
            } else {
                // User not found
                $showAlert = true;
                $alertType = "danger";
                $alertMessage = "Invalid email or password";
                error_log("Login failed for email: $email (user not found)");
                sleep(1); // Delay to prevent brute force
            }
            
            // Close the connection
            $stmt->close();
            $conn->close();
        }
    }
}

// Helper function to sanitize input
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Barangay Clearance and Document Request System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="content-wrapper">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="../../index.php">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Barangay Services
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="../../index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../../index.php#services">Services</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Contact</a>
                        </li>
                    </ul>
                    <div class="d-flex">
                        <a href="register.php" class="btn btn-outline-light me-3">Register</a>
                        <a href="../../index.php" class="btn btn-outline-light">Back</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Login Section -->
        <section class="py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-6 col-lg-5">
                        <div class="card shadow-lg">
                            <div class="card-body p-5">
                                <div class="text-center mb-4">
                                    <h2 class="mb-3">Login to Your Account</h2>
                                    <p class="text-muted">Enter your credentials to access your account</p>
                                </div>
                                
                                <?php if ($showAlert): ?>
                                    <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                                        <?php echo $alertMessage; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo $email; ?>" placeholder="Enter your email" required>
                                            <?php if (isset($errors['email'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <label for="password" class="form-label">Password</label>
                                            <a href="forgot-password.php" class="small text-decoration-none">Forgot password?</a>
                                        </div>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                            <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="Enter your password" required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if (isset($errors['password'])): ?>
                                                <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4 form-check">
                                        <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe">
                                        <label class="form-check-label" for="rememberMe">Remember me</label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                                    </div>
                                </form>
                                
                                <div class="text-center mt-4">
                                    <p class="mb-0">Don't have an account? <a href="register.php" class="text-decoration-none">Register now</a></p>
                                </div>
                            </div>
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
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>
</body>
</html>