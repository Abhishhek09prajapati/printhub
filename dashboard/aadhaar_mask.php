<?php
ob_start();
// 1. Session & Auth Control
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');
require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$u_name = $_SESSION['user_name'] ?? 'User';

$swal_msg = "";

// 2. Pricing Fetch (aadhar_to_pan ka price nikalna)
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'aadhar_to_mask_pan'");
$service_price = ($price_check && $price_check->num_rows > 0) ? $price_check->fetch_assoc()['price'] : 10.00;

// 3. API Config Fetch from TitanPayment
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = $api_settings['titanurl'] ?? '';
$api_key = $api_settings['titan_api_key'] ?? '';

if (empty($TitanApi_Url) || empty($api_key)) {
    echo "<script>alert('System Error: TitanPayment API URL or Key is not configured. Contact Admin.'); window.location.href='titanhome.php';</script>";
    exit();
}

// 4. Handle Find PAN Logic
if (isset($_POST['find_pan_btn'])) {
    $input_aadhar = mysqli_real_escape_string($conn, trim($_POST['aadhar_number']));
    
    // Wallet Check
    $user_wallet = $conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet'];
    
    if (strlen($input_aadhar) !== 12 || !is_numeric($input_aadhar)) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a valid 12-digit Aadhaar number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet to use this service.', 'warning');";
    } else {
        // Build API URL Dynamically using $TitanApi_Url
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/AadharToMaskPan.php?api_key=" . urlencode($api_key) . "&aadhaar_no=" . urlencode($input_aadhar);
        
        // cURL Request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $api_response = curl_exec($ch);
        curl_close($ch);
        
        if ($api_response) {
            $data = json_decode($api_response, true);
            
            // Check API Success (Yahan status 200 check kar rahe hain json ke hisaab se)
            if (isset($data['result']['status']) && $data['result']['status'] == 200) {
                
                // Deduct Wallet
                $conn->query("UPDATE users SET wallet = wallet - $service_price WHERE id = '$uid'");
                
                // Save to Database History Table
                $mask_pan = mysqli_real_escape_string($conn, $data['result']['mask_pan']);
                $msg = mysqli_real_escape_string($conn, $data['result']['message']);
                $provider = mysqli_real_escape_string($conn, $data['result']['Provider']);
                $date_time = date('Y-m-d H:i:s');
                
                $insert = "INSERT INTO aadhar_to_pan_history (user_id, aadhar_no, mask_pan, message, provider, created_at) 
                           VALUES ('$uid', '$input_aadhar', '$mask_pan', '$msg', '$provider', '$date_time')";
                
                if ($conn->query($insert)) {
                    $swal_msg = "Swal.fire('Record Found!', 'Masked PAN retrieved successfully! Redirecting...', 'success').then(() => { window.location.href='aadhaar_mask_list.php'; });";
                } else {
                    $swal_msg = "Swal.fire('Database Error!', 'Found PAN but failed to save history.', 'error');";
                }
                
            } else {
                $err = $data['result']['message'] ?? 'Record not found or API issue.';
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
                    <div class="icon-item bg-warning-subtle shadow-none me-3">
                        <span class="fas fa-id-badge text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">PAN Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhar to <span class="text-warning fw-medium">Mask PAN</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <a href="aadhaar_mask_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Find PAN Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-warning border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-warning"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successfully finding the PAN.</p>
                </div>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-fingerprint"></i></span>
                            <input type="text" class="form-control shadow-none" name="aadhar_number" placeholder="Enter 12-digit number" maxlength="12" pattern="\d{12}" required>
                        </div>
                    </div>
                    
                    <button class="btn btn-warning w-100 fw-bold shadow-none py-2 text-dark" type="submit" name="find_pan_btn">
                        <i class="fas fa-search-plus me-2"></i> Find Masked PAN
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