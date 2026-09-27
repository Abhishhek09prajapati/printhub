<?php
require_once('../titancore/titanconfig.php');

if (!isset($_GET['order'])) {
    echo json_encode(['success' => false, 'message' => 'Order missing']);
    exit;
}

$orderId = $_GET['order'];
$api_url = "https://pg.world365.in/payment/statuspopup?order=" . urlencode($orderId);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$resData = json_decode($response, true);

// Agar Payment Success Hai
if (isset($resData['status']) && strtoupper($resData['status']) === 'SUCCESS') {
    
    // Check if user data exists in GET (from register.php check loop)
    if (isset($_GET['phone'])) {
        $name = mysqli_real_escape_string($conn, $_GET['name']);
        $shop = mysqli_real_escape_string($conn, $_GET['shop']);
        $email = mysqli_real_escape_string($conn, $_GET['email']);
        $phone = mysqli_real_escape_string($conn, $_GET['phone']);
        $type = mysqli_real_escape_string($conn, $_GET['type']);
        $pass = mysqli_real_escape_string($conn, $_GET['pass']);

        // Final Insert (Ab bina payment ke insert nahi ho payega)
        $check_again = $conn->query("SELECT id FROM users WHERE phone='$phone'");
        if ($check_again->num_rows == 0) {
            $conn->query("INSERT INTO users (name, shop_name, email, phone, user_type, password, status) VALUES ('$name', '$shop', '$email', '$phone', '$type', '$pass', 'Active')");
        }
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>