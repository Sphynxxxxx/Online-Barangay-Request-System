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
    $punong_barangay = trim($conn->real_escape_string($_POST['punong_barangay']));
    $sk_chairperson = trim($conn->real_escape_string($_POST['sk_chairperson']));
    $barangay_secretary = trim($conn->real_escape_string($_POST['barangay_secretary']));
    $barangay_treasurer = trim($conn->real_escape_string($_POST['barangay_treasurer']));
    $other_official = trim($conn->real_escape_string($_POST['other_official']));
    $request_id = intval($_POST['request_id']);

    // Check if a record already exists
    $checkSql = "SELECT COUNT(*) as count FROM barangay_officials";
    $result = $conn->query($checkSql);
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        // Update existing record
        $sql = "UPDATE barangay_officials 
                SET punong_barangay = ?, 
                    sk_chairperson = ?, 
                    barangay_secretary = ?, 
                    barangay_treasurer = ?, 
                    other_official = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssss", 
            $punong_barangay, 
            $sk_chairperson, 
            $barangay_secretary, 
            $barangay_treasurer, 
            $other_official
        );
    } else {
        // Insert new record
        $sql = "INSERT INTO barangay_officials 
                (punong_barangay, sk_chairperson, barangay_secretary, barangay_treasurer, other_official) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssss", 
            $punong_barangay, 
            $sk_chairperson, 
            $barangay_secretary, 
            $barangay_treasurer, 
            $other_official
        );
    }

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Barangay Officials updated successfully.";
    } else {
        $_SESSION['error_msg'] = "Error updating Barangay Officials: " . $stmt->error;
    }

    $stmt->close();
} else {
    $_SESSION['error_msg'] = "Invalid request method.";
}

$conn->close();

header("Location: edit-brgyclearance.php?id=" . $request_id);
exit();