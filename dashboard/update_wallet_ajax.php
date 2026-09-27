<?php
session_start();
require_once('../titancore/titanconfig.php');

// Output format JSON set kar diya
header('Content-Type: application/json');

// Security Check (Direct access rokne ke liye)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order_id']) && isset($_POST['amount'])) {
    
    // Safety ke liye variables ko escape kar rahe hain
    $order_id = $conn->real_escape_string(trim($_POST['order_id']));
    
    // Check if the order exists and is in 'Process' status
    $stmt = $conn->query("SELECT * FROM wallet_requests WHERE utr_number='$order_id' AND status='Process' LIMIT 1");
    
    if ($stmt && $stmt->num_rows > 0) {
        $row = $stmt->fetch_assoc();
        
        $phone_number = $conn->real_escape_string($row['userid']); // User ka phone number jo requests table mein save hai
        $db_amount = floatval($row['amount']); // DB se amount uthaya
        
        // 1. Transaction ko success mark karo (Taki dobara double add na ho)
        $update_req = $conn->query("UPDATE wallet_requests SET status='SUCCESS' WHERE utr_number='$order_id'");
        
        // 2. Main users table mein wallet update karo
        if ($update_req) {
            $update_wallet = $conn->query("UPDATE users SET wallet = wallet + $db_amount WHERE phone='$phone_number'");
            
            if ($update_wallet) {
                echo json_encode(['success' => true]);
                exit;
            }
        }
    } else {
        // Agar pehle hi SUCCESS ho chuka hai (double hit lagne par), toh bhi frontend ko success bolo taki UI aage badh jaye
        $check_success = $conn->query("SELECT * FROM wallet_requests WHERE utr_number='$order_id' AND status='SUCCESS'");
        if ($check_success && $check_success->num_rows > 0) {
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

// Default fail response
echo json_encode(['success' => false]);
?>