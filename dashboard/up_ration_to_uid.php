<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');

// Fix Database Encoding
mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");

require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$u_name = $_SESSION['user_name'] ?? 'User';
$swal_msg = "";

// Pricing Fetch
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'up_ration_to_uid'");
$service_price = ($price_check && $price_check->num_rows > 0) ? floatval($price_check->fetch_assoc()['price']) : 20.00;

// API Config Fetch
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = $api_settings['titanurl'] ?? '';
$api_key = $api_settings['titan_api_key'] ?? '';

if (empty($TitanApi_Url) || empty($api_key)) {
    echo "<script>alert('System Error: API Configuration missing.'); window.location.href='titanhome.php';</script>";
    exit();
}

// Handle Form Logic
if (isset($_POST['get_ration_uid_btn'])) {
    $input_ration = mysqli_real_escape_string($conn, trim($_POST['ration_number']));
    
    // Wallet Check
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    
    if (empty($input_ration)) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a valid Ration Number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        // Build Endpoint
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/Up_Ration_To_Uid.php?api_key=" . urlencode($api_key) . "&ration_no=" . urlencode($input_ration);
        
        // cURL Request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $api_response = curl_exec($ch);
        curl_close($ch);
        
        if ($api_response) {
            $data = json_decode($api_response, true);
            
            // Flexible Status Check
            $api_status = $data['result']['status'] ?? $data['status'] ?? 'failed';
            $api_msg = $data['result']['message'] ?? $data['message'] ?? 'Failed to fetch Details.';
            
            // Check Success
            if (strtolower($api_status) === 'success') {
                
                if(isset($data['result']['data']) && is_array($data['result']['data']) && count($data['result']['data']) > 0) {
                    
                    // Calculate new balance
                    $new_wallet = $user_wallet - $service_price;
                    
                    // Deduct Wallet
                    $conn->query("UPDATE users SET wallet = '$new_wallet' WHERE id = '$uid'");
                    
                    // History log update
                    $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$service_price', '$new_wallet', 'UP Ration To UID ($input_ration)', '1', 'Debit')");
                    
                    // ★ THE HINDI FIX: Bina JSON_UNESCAPED_UNICODE ke encode kar rahe hain. 
                    // Isse Hindi \u0930 format me save hogi jo DB me kabhi ????? nahi banegi.
                    $member_data = mysqli_real_escape_string($conn, json_encode($data['result']['data']));
                    
                    // Save to History
                    $insert = "INSERT INTO up_ration_to_uid_history (user_id, ration_no, member_data) 
                               VALUES ('$uid', '$input_ration', '$member_data')";
                    
                    if ($conn->query($insert)) {
                        $swal_msg = "Swal.fire('Success!', 'Family Details Fetched Successfully!', 'success').then(() => { window.location.href='up_ration_to_uid_list.php'; });";
                    } else {
                        $swal_msg = "Swal.fire('Database Error!', 'Generated but failed to save history.', 'error');";
                    }
                } else {
                    $swal_msg = "Swal.fire('No Records!', 'No family members found for this ration number.', 'info');";
                }
                
            } else {
                $swal_msg = "Swal.fire('Failed!', 'Reason: " . addslashes($api_msg) . "', 'error');";
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
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-users text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Identity & Family Services</h6>
                        <h4 class="text-primary fw-bold mb-0">UP Ration <span class="fw-medium">To UID</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="up_ration_to_uid_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View Family History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-body-tertiary border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Find UID by UP Ration</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successful fetch.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="upRationForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Ration Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                            <input type="text" class="form-control shadow-none" name="ration_number" id="ration_number" placeholder="Enter UP Ration Number" required>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary w-100 fw-bold shadow-none py-2" type="button" onclick="confirmFetch()">
                        <i class="fas fa-users me-2"></i> Fetch Family Details
                    </button>
                    <button type="submit" name="get_ration_uid_btn" id="realSubmit" class="d-none"></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_msg; ?>

    function confirmFetch() {
        let rationNo = document.getElementById('ration_number').value.trim();
        if(rationNo === '') {
            Swal.fire('Warning', 'Please enter a Ration Number.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Fetching Family Data...',
            html: 'Please wait while we connect to server.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        document.getElementById('realSubmit').click();
    }
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>