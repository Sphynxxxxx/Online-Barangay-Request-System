<?php
/**
 * This file processes payment proof uploads that were submitted before a request was created
 * It should be included in view-request.php after getting the request ID
 */

// Check if there's a pending payment proof in the session
if (isset($_SESSION['payment_proof']) && isset($_SESSION['request_id'])) {
    $pendingProof = $_SESSION['payment_proof'];
    $requestId = $_SESSION['request_id'];
    
    try {
        $db = new Database();
        
        // Create payment_proofs directory if it doesn't exist
        if (!is_dir("payment_proofs")) {
            mkdir("payment_proofs", 0755, true);
        }
        
        // Generate a unique filename
        $extension = 'jpg'; // Default to jpg 
        if (isset($pendingProof['file_type'])) {
            // Get extension from mime type
            $fileTypeParts = explode('/', $pendingProof['file_type']);
            if (count($fileTypeParts) > 1 && !empty($fileTypeParts[1])) {
                $extension = $fileTypeParts[1];
                if ($extension == 'jpeg') $extension = 'jpg';
            }
        }
        
        $fileName = 'payment_' . $requestId . '_' . time() . '.' . $extension;
        $targetFilePath = 'uploads/payment_proofs/' . $fileName;
        
        $fileDecoded = false;
        
        // Check if we have a base64 encoded file
        if (isset($pendingProof['file_data'])) {
            $fileData = base64_decode($pendingProof['file_data']);
            if ($fileData) {
                if (file_put_contents($targetFilePath, $fileData)) {
                    $fileDecoded = true;
                } else {
                    error_log("Failed to write decoded file to: " . $targetFilePath);
                }
            } else {
                error_log("Failed to decode base64 file data");
            }
        }
        
        if ($fileDecoded) {
            // Fix: Remove userId from UPDATE parameters and fix WHERE clause
            $updateSql = "UPDATE requests SET 
                payment_reference = ?, 
                payment_proof = ?, 
                payment_notes = ?,
                payment_status = 1,
                payment_date = NOW() 
                WHERE request_id = ?";
            
            $updateParams = [
                $pendingProof['reference_number'],
                $fileName,
                $pendingProof['payment_notes'] ?? null,
                $requestId
            ];
            
            $result = $db->execute($updateSql, $updateParams);
            
            if ($result) {
                // Insert into payment_proofs table
                $proofSql = "INSERT INTO payment_proofs (
                    request_id, 
                    user_id, 
                    payment_method, 
                    payment_reference, 
                    proof_image, 
                    payment_notes, 
                    status, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'submitted', NOW())";

                $proofParams = [
                    $requestId, 
                    $userId, 
                    $pendingProof['payment_method'], 
                    $pendingProof['reference_number'], 
                    $fileName, 
                    $pendingProof['payment_notes'] ?? null
                ];

                $db->execute($proofSql, $proofParams);
                
                // Create notification for the user
                $notifMessage = "Your payment proof for Request #$requestId has been submitted successfully.";
                $notifSql = "INSERT INTO notifications (user_id, message, is_read, is_system, created_at) 
                            VALUES (?, ?, 0, 0, NOW())";
                $db->execute($notifSql, [$userId, $notifMessage]);
                
                // Create system notification for staff/admin
                $sysNotifMessage = "Payment proof submitted for Request #$requestId.";
                $sysNotifSql = "INSERT INTO notifications (message, is_read, is_system, created_at) 
                            VALUES (?, 0, 1, NOW())";
                $db->execute($sysNotifSql, [$sysNotifMessage]);
                
                // Add success message
                $_SESSION['success_msg'] = "Your payment proof has been processed successfully.";
            } else {
                error_log("Failed to update request with payment information");
                // Delete the file if database update fails
                if (file_exists($targetFilePath)) {
                    unlink($targetFilePath);
                }
            }
        }
        
        $db->closeConnection();
    }
    catch (Exception $e) {
        error_log("Process Pending Payment Error: " . $e->getMessage());
        $_SESSION['error_msg'] = "An error occurred while processing your payment proof. Please try again.";
    }
    
    // Clear the session variables
    unset($_SESSION['payment_proof']);
    unset($_SESSION['request_id']);
}
?>