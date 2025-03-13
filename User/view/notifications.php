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

$notifications = [];
$totalNotifications = 0;
$showOnlyUnread = isset($_GET['filter']) && $_GET['filter'] === 'unread';
$notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$markAllRead = isset($_GET['mark_all_read']) && $_GET['mark_all_read'] === '1';
$unreadCount = 0;

try {
    $db = new Database();
    
    // If a specific notification ID is provided, mark it as read
    if ($notificationId > 0) {
        $markReadSql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND (user_id = ? OR is_system = 1)";
        $db->execute($markReadSql, [$notificationId, $userId]);
    }
    
    // If mark all as read is requested
    if ($markAllRead) {
        $markAllReadSql = "UPDATE notifications SET is_read = 1 WHERE (user_id = ? OR is_system = 1) AND is_read = 0";
        $db->execute($markAllReadSql, [$userId]);
        
        header("Location: notifications.php");
        exit();
    }
    
    $baseQuery = "SELECT n.*, DATE_FORMAT(n.created_at, '%M %d, %Y %h:%i %p') as formatted_date 
                 FROM notifications n 
                 WHERE ";
    
    if ($userType === 'resident') {
        $baseQuery .= "n.user_id = ?";
        $params = [$userId];
    } else {

        // Admins and staff can see system notifications and their own
        $baseQuery .= "(n.user_id = ? OR n.is_system = 1)";
        $params = [$userId];
    }
    
    // Add unread filter if selected
    if ($showOnlyUnread) {
        $baseQuery .= " AND n.is_read = 0";
    }
    
    // Count total matching records
    $countQuery = str_replace("n.*, DATE_FORMAT(n.created_at, '%M %d, %Y %h:%i %p') as formatted_date", "COUNT(*) as total", $baseQuery);
    $totalNotifications = $db->fetchOne($countQuery, $params)['total'] ?? 0;
    
    $query = $baseQuery . " ORDER BY n.created_at DESC";
    
    $notifications = $db->fetchAll($query, $params);
    
    // Count unread notifications for the badge
    $unreadQuery = str_replace("n.*, DATE_FORMAT(n.created_at, '%M %d, %Y %h:%i %p') as formatted_date", "COUNT(*) as unread", $baseQuery . " AND n.is_read = 0");
    $unreadCount = $db->fetchOne($unreadQuery, $params)['unread'] ?? 0;
    
    $db->closeConnection();
    
} catch (Exception $e) {
    $error = "Error retrieving notifications: " . $e->getMessage();
    error_log("Notifications Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Barangay Clearance and Document Request System</title>
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
        
        .page-header {
            background-color: #0d6efd;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .notification-card {
            border-left: 4px solid transparent;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .notification-card.unread {
            border-left-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        .notification-card.system {
            border-left-color: #6c757d;
        }
        
        .notification-time {
            font-size: 0.85rem;
            color: #6c757d;
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
        
        .empty-state {
            text-align: center;
            padding: 3rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .page-header {
                text-align: center;
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
                            <a class="nav-link" href="profile.php">My Profile</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle position-relative active" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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

        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1><i class="bi bi-bell me-2"></i>Notifications</h1>
                        <p class="lead mb-0">View all your notifications and updates</p>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <?php if ($unreadCount > 0): ?>
                        <a href="notifications.php?mark_all_read=1" class="btn btn-outline-light">
                            <i class="bi bi-check-all me-2"></i>Mark All as Read
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <div class="container py-4">
            <!-- Filter Options -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="btn-group" role="group">
                        <a href="notifications.php" class="btn btn-outline-primary <?php echo !$showOnlyUnread ? 'active' : ''; ?>">
                            All Notifications
                        </a>
                        <a href="notifications.php?filter=unread" class="btn btn-outline-primary <?php echo $showOnlyUnread ? 'active' : ''; ?>">
                            Unread Only
                        </a>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 mt-2 mt-md-0">
                        Showing <?php echo count($notifications); ?> of <?php echo $totalNotifications; ?> notifications
                    </p>
                </div>
            </div>
            
            <!-- Notifications List -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="bi bi-bell-slash"></i>
                            <h4>No notifications found</h4>
                            <p class="text-muted">
                                <?php echo $showOnlyUnread ? 'You have no unread notifications.' : 'You don\'t have any notifications yet.'; ?>
                            </p>
                            <?php if ($showOnlyUnread && $totalNotifications > 0): ?>
                                <a href="notifications.php" class="btn btn-outline-primary mt-3">View All Notifications</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="card notification-card <?php echo !$notification['is_read'] ? 'unread' : ''; ?> <?php echo $notification['is_system'] ? 'system' : ''; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h5 class="card-title">
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="badge bg-primary me-2">New</span>
                                            <?php endif; ?>
                                            <?php if ($notification['is_system']): ?>
                                                <i class="bi bi-megaphone me-2"></i>System Notification
                                            <?php else: ?>
                                                <i class="bi bi-bell me-2"></i>Document Request Update
                                            <?php endif; ?>
                                        </h5>
                                        <span class="notification-time">
                                            <?php echo $notification['formatted_date']; ?>
                                        </span>
                                    </div>
                                    <p class="card-text"><?php echo $notification['message']; ?></p>
                                    <?php if (!$notification['is_read']): ?>
                                        <a href="notifications.php?id=<?php echo $notification['notification_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-check me-1"></i>Mark as Read
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Extract request ID from notification message if available
                                    if (preg_match('/Request #(\d+)/', $notification['message'], $matches)) {
                                        $requestId = $matches[1];
                                        echo '<a href="view-request.php?id=' . $requestId . '" class="btn btn-sm btn-outline-info ms-2">
                                                <i class="bi bi-eye me-1"></i>View Request
                                            </a>';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
</body>
</html>