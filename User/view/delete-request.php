<?php
require_once dirname(__DIR__, 2) . "/backend/connections/config.php";
require_once dirname(__DIR__, 2) . "/backend/connections/database.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'resident') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['request_id'])) {
    $requestId = intval($_POST['request_id']);
    $userId = $_SESSION['user_id'];

    try {
        $db = new Database();

        // Ensure the request belongs to the logged-in user
        $checkSql = "SELECT request_id FROM requests WHERE request_id = ? AND user_id = ?";
        $requestExists = $db->fetchOne($checkSql, [$requestId, $userId]);

        if (!$requestExists) {
            $_SESSION['error_msg'] = "Request not found or does not belong to you.";
            header("Location: my-requests.php");
            exit();
        }

        // Delete the request
        $deleteSql = "DELETE FROM requests WHERE request_id = ?";
        $db->execute($deleteSql, [$requestId]);

        $_SESSION['success_msg'] = "Request deleted successfully.";
        $db->closeConnection();

    } catch (Exception $e) {
        error_log("Delete Request Error: " . $e->getMessage());
        $_SESSION['error_msg'] = "An error occurred while deleting the request. Please try again.";
    }

    header("Location: my-requests.php");
    exit();
}

header("Location: my-requests.php");
exit();
