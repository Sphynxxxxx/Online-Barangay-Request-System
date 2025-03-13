<?php
session_start();

$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "barangay_request_system"; 

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    $_SESSION['error_msg'] = "Connection failed: " . $conn->connect_error;
    header("Location: edit-brgyclearance.php?id=" . $_POST['request_id']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $official_names = isset($_POST['official_names']) ? $_POST['official_names'] : [];
    $official_positions = isset($_POST['official_positions']) ? $_POST['official_positions'] : [];
    $request_id = intval($_POST['request_id']);

    $conn->begin_transaction();

    try {
        $clearSql = "DELETE FROM barangay_officials";
        if (!$conn->query($clearSql)) {
            throw new Exception("Failed to clear existing officials: " . $conn->error);
        }

        $insertSql = "INSERT INTO barangay_officials (name, position) VALUES (?, ?)";
        $stmt = $conn->prepare($insertSql);

        if (!$stmt) {
            throw new Exception("Failed to prepare insert statement: " . $conn->error);
        }

        // Insert new officials
        $insertedOfficials = 0;
        foreach ($official_names as $index => $name) {
            // Trim and sanitize input
            $sanitizedName = trim($name);
            $sanitizedPosition = trim($official_positions[$index]);

            // Only insert if both name and position are not empty
            if (!empty($sanitizedName) && !empty($sanitizedPosition)) {
                $stmt->bind_param("ss", $sanitizedName, $sanitizedPosition);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert official: " . $stmt->error);
                }
                
                $insertedOfficials++;
            }
        }

        // Commit transaction
        if (!$conn->commit()) {
            throw new Exception("Failed to commit transaction: " . $conn->error);
        }

        // Set success message
        if ($insertedOfficials > 0) {
            $_SESSION['success_msg'] = "$insertedOfficials Barangay Official(s) updated successfully.";
        } else {
            $_SESSION['error_msg'] = "No officials were added. Please check your input.";
        }
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        $_SESSION['error_msg'] = "Error updating Barangay Officials: " . $e->getMessage();
    }

    $stmt->close();
    $conn->close();

    header("Location: edit-brgyclearance.php?id=" . $request_id);
    exit();
} else {
    $_SESSION['error_msg'] = "Invalid request method.";
    header("Location: edit-brgyclearance.php");
    exit();
}