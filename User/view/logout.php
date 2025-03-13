<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    // Optional: Log the logout to database
    try {
        require_once dirname(__DIR__, 2) . "/backend/connections/config.php";
        require_once dirname(__DIR__, 2) . "/backend/connections/database.php";
        
        $db = new Database();
        $logSql = "INSERT INTO user_logs (user_id, action, ip_address) VALUES (?, ?, ?)";
        $db->insert($logSql, [
            $_SESSION['user_id'],
            'logout',
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $db->closeConnection();
    } catch (Exception $e) {
        // Just log the error but continue with logout
        error_log("Logout Error: " . $e->getMessage());
    }
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

session_start();
$_SESSION['success_msg'] = "You have been successfully logged out.";
header("Location: login.php");
exit();
?>