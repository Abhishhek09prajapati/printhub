<?php
ob_start();
// 1. Session & Auth Control
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');

// Fix Database Encoding
mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");
mysqli_query($conn, "SET CHARACTER SET 'utf8mb4'");

require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$u_name = $_SESSION['user_name'] ?? 'User';

$swal_msg = "";

// 2. Pricing Fetch (Assumed service name: aadhar_to_details)
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'aadhar_to_details'");
$service_price = ($price_check && $price_check->num_rows > 0) ? $price_check->fetch_assoc()['price'] : 5.00;

// 3. API Config Fetch from TitanPayment
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = $api_settings['titanurl'] ?? '';
$api_key = $api_settings['titan_api_key'] ?? '';

if (empty($TitanApi_Url) || empty($api_key)) {
    echo "<script>alert('System Error: TitanPayment API URL or Key is not configured. Contact Admin.'); window.location.href='titanhome.php';</script>";
    exit();
}

// 4. Handle Verification Logic
if (isset($_POST['find_details_btn'])) {
    $input_aadhar = mysqli_real_escape_string($conn, trim($_POST['aadhar_number']));
    
    // Wallet Check
    $user_wallet = $conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet'];
    
    if (strlen($input_aadhar) !== 12 || !is_numeric($input_aadhar)) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a valid 12-digit Aadhaar number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet to use this service.', 'warning');";
    } else {
        // Build API URL using &aadhar= instead of aadhaar_no
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/AadharToDetails.php?api_key=" . urlencode($api_key) . "&aadhar=" . urlencode($input_aadhar);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $api_response = curl_exec($ch);
        curl_close($ch);
        
        if ($api_response) {
            $data = json_decode($api_response, true);
            
            // Check based on the new JSON structure ("status": "success")
            if (isset($data['status']) && strtolower($data['status']) === 'success' && !empty($data['data'])) {
                
                // Deduct Wallet
                $conn->query("UPDATE users SET wallet = wallet - $service_price WHERE id = '$uid'");
                
                $provider = mysqli_real_escape_string($conn, $data['provider'] ?? 'TitanApi');
                $txn_id = mysqli_real_escape_string($conn, $data['transaction']['transaction_id'] ?? '');
                
                // ★ NEW: Convert data array to JSON for database storage ★
                $full_data = mysqli_real_escape_string($conn, json_encode($data['data'], JSON_UNESCAPED_UNICODE));
                $date_time = date('Y-m-d H:i:s');
                
                // Save to Database History Table (Make sure full_data column exists as provided earlier)
                $insert = "INSERT INTO aadhar_details_history (user_id, aadhar_no, txn_id, provider, full_data, created_at) 
                           VALUES ('$uid', '$input_aadhar', '$txn_id', '$provider', '$full_data', '$date_time')";
                
                if ($conn->query($insert)) {
                    // ★ Redirect to history page upon success ★
                    $swal_msg = "Swal.fire('Success!', 'Details Fetched Successfully! Redirecting...', 'success').then(() => { window.location.href='aadhaar_details_history.php'; });";
                } else {
                    $swal_msg = "Swal.fire('Database Error!', 'Details fetched but failed to save history.', 'warning');";
                }
                
            } else {
                $err = $data['message'] ?? 'No details found or invalid API response.';
                $swal_msg = "Swal.fire('Failed!', '" . addslashes($err) . "', 'error');";
            }
        } else {
            $swal_msg = "Swal.fire('Server Error!', 'Could not connect to the Master API Server.', 'error');";
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-info-subtle shadow-none me-3">
                        <span class="fas fa-search text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-info fs-10 mb-0">Identity Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar <span class="text-info fw-medium">To Details</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <a href="aadhaar_details_history.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History List</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <!-- Input Form Section (Centered) -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100 border shadow-none">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-fingerprint me-2"></i>Find Linked Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-info"></i>
                    <p class="mb-0 fs-11">Search charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted from your wallet upon success. Results will be saved in your history.</p>
                </div>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control shadow-none" name="aadhar_number" placeholder="Enter 12-digit number" maxlength="12" pattern="\d{12}" required>
                        </div>
                    </div>
                    
                    <button class="btn btn-info w-100 fw-bold shadow-none py-2" type="submit" name="find_details_btn">
                        <i class="fas fa-search me-2"></i> Find Details & View
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_msg; ?>
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>