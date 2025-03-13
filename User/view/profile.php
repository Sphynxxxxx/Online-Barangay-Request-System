<?php
require_once dirname(__DIR__, 2) . "/backend/connections/config.php";
require_once dirname(__DIR__, 2) . "/backend/connections/database.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];
$userName = isset($_SESSION['name']) ? $_SESSION['name'] : 'User';

$userProfile = null;
$errors = [];
$successMessage = '';

try {
    $db = new Database();
    
    $profileSql = "SELECT * FROM users WHERE user_id = ?";
    $userProfile = $db->fetchOne($profileSql, [$userId]);
    
    // Format the birthdate for display
    if (isset($userProfile['birthday']) && $userProfile['birthday'] != '0000-00-00') {
        $userProfile['formatted_birthday'] = date('F d, Y', strtotime($userProfile['birthday']));
    } else {
        $userProfile['formatted_birthday'] = 'Not specified';
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

        $firstName = trim($_POST['first_name']);
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name']);
        $contactNumber = trim($_POST['contact_number']);
        $zone = trim($_POST['zone']);
        $houseNumber = trim($_POST['house_number']);
        
        // Validate required fields
        if (empty($firstName)) {
            $errors['first_name'] = 'First name is required';
        }
        
        if (empty($lastName)) {
            $errors['last_name'] = 'Last name is required';
        }
        
        if (empty($contactNumber)) {
            $errors['contact_number'] = 'Contact number is required';
        } elseif (!preg_match("/^[0-9]{10,15}$/", $contactNumber)) {
            $errors['contact_number'] = 'Invalid contact number format';
        }
        
        if (empty($zone)) {
            $errors['zone'] = 'Zone is required';
        }
        
        if (empty($houseNumber)) {
            $errors['house_number'] = 'House number is required';
        }
        
        // Handle profile picture upload if provided
        $profilePicPath = $userProfile['profile_pic'] ?? null;
        
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['size'] > 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['profile_pic']['type'], $allowedTypes)) {
                $errors['profile_pic'] = 'Only JPG, PNG, and GIF files are allowed';
            } elseif ($_FILES['profile_pic']['size'] > $maxFileSize) {
                $errors['profile_pic'] = 'File size should not exceed 5MB';
            } else {
                // Create upload directory if it doesn't exist
                $uploadDir = "uploads/profile_pics/";
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate unique filename
                $fileExtension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                $newFileName = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
                $targetFile = $uploadDir . $newFileName;
                
                // Move the uploaded file
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetFile)) {
                    $profilePicPath = $targetFile;
                } else {
                    $errors['profile_pic'] = 'Failed to upload profile picture';
                }
            }
        }
        
        // If no errors, update the profile
        if (empty($errors)) {
            $updateSql = "UPDATE users SET 
                            first_name = ?, 
                            middle_name = ?, 
                            last_name = ?, 
                            contact_number = ?, 
                            zone = ?, 
                            house_number = ?";
            
            $params = [
                $firstName,
                $middleName,
                $lastName,
                $contactNumber,
                $zone,
                $houseNumber
            ];
            
            // Add profile pic to update if it was uploaded
            if ($profilePicPath !== $userProfile['profile_pic']) {
                $updateSql .= ", profile_pic = ?";
                $params[] = $profilePicPath;
            }
            
            $updateSql .= " WHERE user_id = ?";
            $params[] = $userId;
            
            $result = $db->execute($updateSql, $params);
            
            if ($result) {
                // Update session name
                $_SESSION['name'] = $firstName . ' ' . $lastName;
                $userName = $_SESSION['name'];
                
                $successMessage = 'Profile updated successfully!';
                
                // Refresh user profile data
                $userProfile = $db->fetchOne($profileSql, [$userId]);
                if (isset($userProfile['birthday']) && $userProfile['birthday'] != '0000-00-00') {
                    $userProfile['formatted_birthday'] = date('F d, Y', strtotime($userProfile['birthday']));
                } else {
                    $userProfile['formatted_birthday'] = 'Not specified';
                }
            } else {
                $errors['general'] = 'Failed to update profile. Please try again.';
            }
        }
    }
    
    // Get notifications for the navigation bar
    // Get notifications
    $notifSql = "SELECT notification_id, message, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5";
    $notifications = $db->fetchAll($notifSql, [$userId]);

    // Count unread notifications
    $unreadNotifSql = "SELECT COUNT(*) as unread_count 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0";
    $unreadNotifications = $db->fetchOne($unreadNotifSql, [$userId]);
    $unreadCount = $unreadNotifications['unread_count'] ?? 0;
    
    $db->closeConnection();
    
} catch (Exception $e) {
    $errors['general'] = 'An error occurred: ' . $e->getMessage();
    error_log("Profile Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Barangay Clearance and Document Request System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="User/css/index.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .content-wrapper {
            flex: 1 0 auto;
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .profile-header {
            background-color: #0d6efd;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }
        
        .profile-pic-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: #adb5bd;
            border: 5px solid white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }
        
        .profile-info {
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            background-color: white;
            margin-bottom: 1.5rem;
        }
        
        .profile-info h5 {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .form-label.required::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(25%, -25%);
        }
        
        .dropdown-menu-end {
            right: 0;
            left: auto;
        }
        
        .dropdown-item.unread {
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        footer {
            height: 100px;
            display: flex;
            align-items: center;
            margin-top: auto;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                text-align: center;
            }
            
            .profile-pic, .profile-pic-placeholder {
                margin: 0 auto 1.5rem auto;
            }
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
            <div class="container">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Barangay Services
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="request-document.php">Request Document</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-requests.php">My Requests</a>
                        </li>
                        <?php if ($userType != 'resident'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage-requests.php">Manage Requests</a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="profile.php">My Profile</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell"></i>
                                <?php if ($unreadCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                    <?php echo $unreadCount; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php if (empty($notifications)): ?>
                                <li><span class="dropdown-item text-muted">No notifications</span></li>
                                <?php else: ?>
                                    <?php 
                                    $count = 0;
                                    foreach ($notifications as $notification): 
                                        if ($count < 5):
                                    ?>
                                    <li>
                                        <a class="dropdown-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" href="notifications.php?id=<?php echo $notification['notification_id']; ?>">
                                            <div class="d-flex w-100 justify-content-between">
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($notification['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo $notification['message']; ?></p>
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php 
                                        endif;
                                        $count++;
                                    endforeach; 
                                    ?>
                                <li><a class="dropdown-item text-primary" href="notifications.php">View all notifications</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($userName); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="change-password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>


        <!-- Profile Header -->
        <section class="profile-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-auto text-center text-md-start mb-3 mb-md-0">
                        <?php if (!empty($userProfile['profile_pic'])): ?>
                            <img src="<?php echo htmlspecialchars($userProfile['profile_pic']); ?>" alt="Profile Picture" class="profile-pic">
                        <?php else: ?>
                            <div class="profile-pic-placeholder">
                                <i class="bi bi-person-fill"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col">
                    <h1>
                        <?php 
                        $fullName = htmlspecialchars($userProfile['first_name']);
                        if (!empty($userProfile['middle_name'])) {
                            $fullName .= ' ' . htmlspecialchars($userProfile['middle_name']) . '. ';
                        } else {
                            $fullName .= ' ';
                        }
                        $fullName .= htmlspecialchars($userProfile['last_name']);
                        echo $fullName;
                    ?>
                    </h1>
                        <p class="lead mb-0"><?php echo htmlspecialchars($userProfile['email']); ?></p>
                        <p class="text-light">
                            <span class="badge bg-light text-dark">
                                <?php echo ucfirst($userProfile['user_type']); ?>
                            </span>
                            <span class="ms-2">
                                <i class="bi bi-geo-alt-fill"></i>
                                <?php echo htmlspecialchars($userProfile['zone'] . ', House No. ' . $userProfile['house_number']); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <div class="container py-4">
            <!-- Alert Messages -->
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $errors['general']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Profile Information Form -->
                <div class="col-lg-8">
                    <div class="profile-info">
                        <h5><i class="bi bi-person-lines-fill me-2"></i>Personal Information</h5>
                        
                        <form action="profile.php" method="post" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="first_name" class="form-label required">First Name</label>
                                    <input type="text" class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" id="first_name" name="first_name" value="<?php echo htmlspecialchars($userProfile['first_name']); ?>" required>
                                    <?php if (isset($errors['first_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($userProfile['middle_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="last_name" class="form-label required">Last Name</label>
                                    <input type="text" class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" id="last_name" name="last_name" value="<?php echo htmlspecialchars($userProfile['last_name']); ?>" required>
                                    <?php if (isset($errors['last_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($userProfile['email']); ?>" readonly>
                                    <div class="form-text">Email cannot be changed</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="contact_number" class="form-label required">Contact Number</label>
                                    <input type="tel" class="form-control <?php echo isset($errors['contact_number']) ? 'is-invalid' : ''; ?>" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($userProfile['contact_number'] ?? ''); ?>" required>
                                    <?php if (isset($errors['contact_number'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['contact_number']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="birthday" class="form-label">Birthday</label>
                                    <input type="text" class="form-control" id="birthday" value="<?php echo htmlspecialchars($userProfile['formatted_birthday']); ?>" readonly>
                                    <div class="form-text">Contact support to update your birthday</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender</label>
                                    <input type="text" class="form-control" id="gender" value="<?php echo ucfirst($userProfile['gender'] ?? 'Not specified'); ?>" readonly>
                                    <div class="form-text">Contact support to update your gender</div>
                                </div>
                            </div>
                            
                            <h5 class="mt-4 border-bottom pb-2"><i class="bi bi-house-door-fill me-2"></i>Address Information</h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="zone" class="form-label required">Zone</label>
                                    <select class="form-select <?php echo isset($errors['zone']) ? 'is-invalid' : ''; ?>" id="zone" name="zone" required>
                                        <option value="" disabled>Select zone</option>
                                        <option value="zone1" <?php echo ($userProfile['zone'] ?? '') === 'zone1' ? 'selected' : ''; ?>>Zone 1</option>
                                        <option value="zone2" <?php echo ($userProfile['zone'] ?? '') === 'zone2' ? 'selected' : ''; ?>>Zone 2</option>
                                        <option value="zone3" <?php echo ($userProfile['zone'] ?? '') === 'zone3' ? 'selected' : ''; ?>>Zone 3</option>
                                        <option value="zone4" <?php echo ($userProfile['zone'] ?? '') === 'zone4' ? 'selected' : ''; ?>>Zone 4</option>
                                        <option value="zone5" <?php echo ($userProfile['zone'] ?? '') === 'zone5' ? 'selected' : ''; ?>>Zone 5</option>
                                        <option value="zone5" <?php echo ($userProfile['zone'] ?? '') === 'zone5' ? 'selected' : ''; ?>>Zone 6</option>
                                        <option value="zone5" <?php echo ($userProfile['zone'] ?? '') === 'zone5' ? 'selected' : ''; ?>>Zone 7</option>
                                    </select>
                                    <?php if (isset($errors['zone'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['zone']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="house_number" class="form-label required">House Number</label>
                                    <input type="text" class="form-control <?php echo isset($errors['house_number']) ? 'is-invalid' : ''; ?>" id="house_number" name="house_number" value="<?php echo htmlspecialchars($userProfile['house_number'] ?? ''); ?>" required>
                                    <?php if (isset($errors['house_number'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['house_number']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <h5 class="mt-4 border-bottom pb-2"><i class="bi bi-image-fill me-2"></i>Profile Picture</h5>
                            
                            <div class="mb-3">
                                <label for="profile_pic" class="form-label">Upload New Profile Picture</label>
                                <input type="file" class="form-control <?php echo isset($errors['profile_pic']) ? 'is-invalid' : ''; ?>" id="profile_pic" name="profile_pic">
                                <div class="form-text">Accepted formats: JPG, PNG, GIF (Max size: 5MB)</div>
                                <?php if (isset($errors['profile_pic'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['profile_pic']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="reset" class="btn btn-outline-secondary">Cancel</button>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Account Security -->
                    <div class="profile-info">
                        <h5><i class="bi bi-shield-lock-fill me-2"></i>Account Security</h5>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" value="••••••••" disabled>
                                <a href="change-password.php" class="btn btn-outline-primary">Change</a>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Account Status</label>
                            <input type="text" class="form-control" value="<?php echo ucfirst($userProfile['status'] ?? 'Active'); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Last Login</label>
                            <input type="text" class="form-control" value="<?php echo isset($userProfile['last_login']) && $userProfile['last_login'] != '0000-00-00 00:00:00' ? date('M d, Y g:i A', strtotime($userProfile['last_login'])) : 'Not available'; ?>" readonly>
                        </div>
                    </div>
                    
                    <!-- ID Verification -->
                    <div class="profile-info">
                        <h5><i class="bi bi-card-checklist me-2"></i>ID Verification</h5>
                        
                        <div class="alert alert-<?php echo !empty($userProfile['id_path']) ? 'success' : 'warning'; ?> mb-3">
                            <i class="bi bi-<?php echo !empty($userProfile['id_path']) ? 'check-circle' : 'exclamation-triangle'; ?>-fill me-2"></i>
                            <?php echo !empty($userProfile['id_path']) ? 'Your ID has been verified' : 'Your ID is pending verification'; ?>
                        </div>
                        
                        <?php if (!empty($userProfile['id_path'])): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Submitted ID</h6>
                                    <p class="card-text small">Your ID has been submitted and verified by our staff.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">ID Verification Required</h6>
                                    <p class="card-text small">Please submit a valid ID for verification to access all services.</p>
                                    <a href="upload-id.php" class="btn btn-sm btn-outline-primary">Upload ID</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="profile-info">
                        <h5><i class="bi bi-link-45deg me-2"></i>Quick Links</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <a href="my-requests.php" class="text-decoration-none">
                                    <i class="bi bi-file-earmark-text me-2"></i>My Document Requests
                                </a>
                            </li>
                            <li class="list-group-item">
                                <a href="change-password.php" class="text-decoration-none">
                                    <i class="bi bi-key me-2"></i>Change Password
                                </a>
                            </li>
                            <li class="list-group-item">
                                <a href="request-document.php" class="text-decoration-none">
                                    <i class="bi bi-file-earmark-plus me-2"></i>Request New Document
                                </a>
                            </li>
                            <li class="list-group-item">
                                <a href="contact.php" class="text-decoration-none">
                                    <i class="bi bi-question-circle me-2"></i>Get Help
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2025 Barangay Clearance and Document Request System</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-white me-3"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-white me-3"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="text-white"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // Preview uploaded profile picture
            const profilePicInput = document.getElementById('profile_pic');
                        if (profilePicInput) {
                            profilePicInput.addEventListener('change', function() {
                                if (this.files && this.files[0]) {
                                    const reader = new FileReader();
                                    
                                    reader.onload = function(e) {
                                        // Get profile pic element
                                        const profilePicEl = document.querySelector('.profile-pic');
                                        const profilePicPlaceholder = document.querySelector('.profile-pic-placeholder');
                                        
                                        // If profile pic element exists, update its src
                                        if (profilePicEl) {
                                            profilePicEl.src = e.target.result;
                                        } 
                                        // If placeholder exists, replace it with an image
                                        else if (profilePicPlaceholder) {
                                            const img = document.createElement('img');
                                            img.src = e.target.result;
                                            img.className = 'profile-pic';
                                            img.alt = 'Profile Picture';
                                            
                                            profilePicPlaceholder.parentNode.replaceChild(img, profilePicPlaceholder);
                                        }
                                    }
                                    
                                    reader.readAsDataURL(this.files[0]);
                                }
                            });
                        }
                        
                        // Form validation
                        const form = document.querySelector('form');
                        form.addEventListener('submit', function(event) {
                            let isValid = true;
                            
                            // Check required fields
                            const requiredFields = form.querySelectorAll('[required]');
                            requiredFields.forEach(function(field) {
                                if (!field.value.trim()) {
                                    field.classList.add('is-invalid');
                                    isValid = false;
                                } else {
                                    field.classList.remove('is-invalid');
                                }
                            });
                            
                            // Validate contact number
                            const contactField = document.getElementById('contact_number');
                            if (contactField.value.trim() && !/^[0-9]{10,15}$/.test(contactField.value.trim())) {
                                contactField.classList.add('is-invalid');
                                if (!contactField.nextElementSibling || !contactField.nextElementSibling.classList.contains('invalid-feedback')) {
                                    const feedback = document.createElement('div');
                                    feedback.className = 'invalid-feedback';
                                    feedback.textContent = 'Please enter a valid contact number';
                                    contactField.parentNode.appendChild(feedback);
                                }
                                isValid = false;
                            }
                            
                            if (!isValid) {
                                event.preventDefault();
                            }
                        });
                    });
    </script>
</body>
</html>