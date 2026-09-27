<?php
ob_start();
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

// Pricing Fetch
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'rc_to_mobile'");
$service_price = ($price_check && $price_check->num_rows > 0) ? floatval($price_check->fetch_assoc()['price']) : 2.00;

// API Config Fetch
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = $api_settings['titanurl'] ?? '';
$api_key = $api_settings['titan_api_key'] ?? '';

if (empty($TitanApi_Url) || empty($api_key)) {
    echo "<script>alert('System Error: API Configuration missing.'); window.location.href='titanhome.php';</script>";
    exit();
}

// Handle Form Logic
if (isset($_POST['get_rc_mobile_btn'])) {
    // Spaces hata dena aur Uppercase kar dena
    $input_vehicle = mysqli_real_escape_string($conn, strtoupper(str_replace(' ', '', trim($_POST['vehicle_number']))));
    
    // Wallet Check
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    
    if (empty($input_vehicle)) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a valid Vehicle Number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        // Build Endpoint
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/RcToMobile.php?api_key=" . urlencode($api_key) . "&Vehicle=" . urlencode($input_vehicle);
        
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
            
            // Check Success
            if (isset($data['status']) && strtolower($data['status']) === 'success') {
                
                // Calculate new balance
                $new_wallet = $user_wallet - $service_price;
                
                // Deduct Wallet
                $conn->query("UPDATE users SET wallet = '$new_wallet' WHERE id = '$uid'");
                
                // History log update
                $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$service_price', '$new_wallet', 'RC To Mobile ($input_vehicle)', '1', 'Debit')");
                
                // Extract Data
                $mobile_no = mysqli_real_escape_string($conn, $data['data']['MobileNumber'] ?? 'N/A');
                
                // Save to History
                $insert = "INSERT INTO rc_to_mobile_history (user_id, vehicle_no, mobile_no) 
                           VALUES ('$uid', '$input_vehicle', '$mobile_no')";
                
                if ($conn->query($insert)) {
                    $swal_msg = "Swal.fire('Success!', 'Mobile Number Found Successfully!', 'success').then(() => { window.location.href='rc_to_mobile_list.php'; });";
                } else {
                    $swal_msg = "Swal.fire('Database Error!', 'Generated but failed to save history.', 'error');";
                }
                
            } else {
                $err = $data['message'] ?? 'Mobile number not found for this vehicle.';
                $swal_msg = "Swal.fire('Failed!', 'Reason: " . addslashes($err) . "', 'error');";
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
                    <div class="icon-item bg-danger-subtle shadow-none me-3">
                        <span class="fas fa-car text-danger fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-danger fs-10 mb-0">RTO Services</h6>
                        <h4 class="text-primary fw-bold mb-0">RC To <span class="text-danger fw-medium">Mobile Number</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="rc_to_mobile_list.php" class="btn btn-sm btn-outline-danger shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-danger">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-mobile-alt me-2 text-danger"></i>Find Mobile By RC</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-danger border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-danger"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successful fetch.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="rcForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Vehicle Number (RC) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-car-side"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase" name="vehicle_number" id="vehicle_number" placeholder="e.g. MH34AA7482" required>
                        </div>
                    </div>
                    
                    <button class="btn btn-danger w-100 fw-bold shadow-none py-2" type="button" onclick="confirmFetch()">
                        <i class="fas fa-search me-2"></i> Find Mobile Number
                    </button>
                    <button type="submit" name="get_rc_mobile_btn" id="realSubmit" class="d-none"></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_msg; ?>

    function confirmFetch() {
        let rcNo = document.getElementById('vehicle_number').value.trim();
        if(rcNo === '') {
            Swal.fire('Warning', 'Please enter a Vehicle Number.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Searching Database...',
            html: 'Please wait while we connect to RTO server.',
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