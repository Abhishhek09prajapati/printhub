<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");

require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$swal_msg = "";

// Pricing Fetch
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'voter_mobile_link'");
$service_price = ($price_check && $price_check->num_rows > 0) ? floatval($price_check->fetch_assoc()['price']) : 25.00;

// API Config
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = rtrim($api_settings['titanurl'] ?? '', '/');
$api_key = $api_settings['titan_api_key'] ?? '';

// Variables to handle state
$step = 1;
$uniq_titanapi_id = "";
$epic_val = "";
$mobile_val = "";
$aadhar_val = "";
$name_val = "";
$initiate_msg = "";

// STEP 1: INITIATE REQUEST
if (isset($_POST['initiate_btn'])) {
    $epic_val = trim($_POST['epic_no']);
    $mobile_val = trim($_POST['mobile_no']);
    $aadhar_val = trim($_POST['aadhar_no']);
    $name_val = trim($_POST['aadhar_name']);
    
    // Check balance before even initiating
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    if ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        $endpoint = $TitanApi_Url . "/api/v1/Voter_Mobile_Link.php?api_key=" . urlencode($api_key) . 
                    "&action=initiate&epic=" . urlencode($epic_val) . 
                    "&mobile=" . urlencode($mobile_val) . 
                    "&aadhar=" . urlencode($aadhar_val) . 
                    "&aadhaar_name=" . urlencode($name_val);
                    
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($res, true);
        if (isset($data['status']) && strtolower($data['status']) == 'success') {
            $step = 2; // Switch to OTP Form
            $uniq_titanapi_id = $data['uniq_titanapi_id'];
            $initiate_msg = $data['message'];
            $swal_msg = "Swal.fire('OTP Sent!', 'Please check Aadhaar linked mobile number.', 'success');";
        } else {
            $err = $data['message'] ?? 'Failed to initiate. Check details.';
            $swal_msg = "Swal.fire('Failed!', '" . addslashes($err) . "', 'error');";
        }
    }
}

// STEP 2: VERIFY OTP
if (isset($_POST['verify_btn'])) {
    $otp_val = trim($_POST['otp_code']);
    $uniq_id_val = trim($_POST['uniq_id']);
    
    // Retrieve hidden details to save in history later
    $epic_save = mysqli_real_escape_string($conn, $_POST['h_epic']);
    $mobile_save = mysqli_real_escape_string($conn, $_POST['h_mobile']);
    $aadhar_save = mysqli_real_escape_string($conn, $_POST['h_aadhar']);
    $name_save = mysqli_real_escape_string($conn, $_POST['h_name']);
    
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    if ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'Wallet balance low.', 'error');";
    } else {
        $endpoint = $TitanApi_Url . "/api/v1/Voter_Mobile_Link.php?api_key=" . urlencode($api_key) . 
                    "&action=verify&uniq_titanapi_id=" . urlencode($uniq_id_val) . 
                    "&otp=" . urlencode($otp_val);
                    
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($res, true);
        if (isset($data['status']) && strtolower($data['status']) == 'success') {
            
            // Deduct Wallet Only on Success
            $new_wallet = $user_wallet - $service_price;
            $conn->query("UPDATE users SET wallet = '$new_wallet' WHERE id = '$uid'");
            
            // Wallet History
            $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$service_price', '$new_wallet', 'Voter Mobile Link ($epic_save)', '1', 'Debit')");
            
            // Save to Service History
            $ref_id = mysqli_real_escape_string($conn, $data['reference_id'] ?? '');
            $note = mysqli_real_escape_string($conn, $data['note'] ?? 'Linked Successfully');
            $masked_aadhar = "XXXX-XXXX-" . substr($aadhar_save, -4);
            
            $insert = "INSERT INTO voter_mobile_link_history (user_id, epic_no, mobile_no, aadhaar_name, masked_aadhaar, reference_id, message) 
                       VALUES ('$uid', '$epic_save', '$mobile_save', '$name_save', '$masked_aadhar', '$ref_id', '$note')";
            $conn->query($insert);
            
            $swal_msg = "Swal.fire('Linked Successfully!', 'Your mobile number is linked with voter card.', 'success').then(() => { window.location.href='voter_mobile_link_list.php'; });";
        } else {
            // If wrong OTP, stay on step 2
            $step = 2;
            $uniq_titanapi_id = $uniq_id_val;
            $epic_val = $epic_save; $mobile_val = $mobile_save; $aadhar_val = $aadhar_save; $name_val = $name_save;
            
            $err = $data['message'] ?? 'Invalid OTP.';
            $swal_msg = "Swal.fire('Verification Failed!', '" . addslashes($err) . "', 'error');";
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-success-subtle shadow-none me-3">
                        <span class="fas fa-link text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Election Commission</h6>
                        <h4 class="text-primary fw-bold mb-0">Voter Mobile <span class="fw-medium">Link Instant</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="voter_mobile_link_list.php" class="btn btn-sm btn-outline-success shadow-none fw-bold"><i class="fas fa-list me-1"></i> View Link History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success">
            <div class="card-header bg-body-tertiary border-bottom">
                <h5 class="mb-0 text-success fw-bold text-center"><i class="fas fa-sync-alt me-2"></i>Aadhaar Based Voter Linking</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                
                <?php if($step == 1): ?>
                <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-success"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong>. Deducted only on successful OTP verification.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="initiateForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">EPIC Number (Voter ID) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control shadow-none text-uppercase" name="epic_no" placeholder="e.g., ABC1234567" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mobile Number to Link <span class="text-danger">*</span></label>
                        <input type="text" class="form-control shadow-none" name="mobile_no" maxlength="10" placeholder="Enter 10-digit Mobile No." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Aadhaar Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control shadow-none" name="aadhar_no" maxlength="12" placeholder="Enter 12-digit Aadhaar No." required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Name (As per Aadhaar) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control shadow-none" name="aadhar_name" placeholder="Enter full name as per Aadhaar" required>
                    </div>
                    
                    <button type="submit" name="initiate_btn" class="btn btn-success w-100 fw-bold shadow-none py-2" onclick="showLoader('Initiating Request...')">
                        <i class="fas fa-paper-plane me-2"></i> Send OTP
                    </button>
                </form>

                <?php elseif($step == 2): ?>
                <div class="alert alert-info border-0 mb-4">
                    <h6 class="fw-bold text-info"><i class="fas fa-check-circle me-1"></i> OTP Sent Successfully!</h6>
                    <p class="mb-0 fs-11 mt-1"><?php echo htmlspecialchars($initiate_msg); ?></p>
                </div>

                <form method="POST" autocomplete="off">
                    <input type="hidden" name="uniq_id" value="<?php echo htmlspecialchars($uniq_titanapi_id); ?>">
                    <input type="hidden" name="h_epic" value="<?php echo htmlspecialchars($epic_val); ?>">
                    <input type="hidden" name="h_mobile" value="<?php echo htmlspecialchars($mobile_val); ?>">
                    <input type="hidden" name="h_aadhar" value="<?php echo htmlspecialchars($aadhar_val); ?>">
                    <input type="hidden" name="h_name" value="<?php echo htmlspecialchars($name_val); ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Enter OTP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control shadow-none fs-3 tracking-widest text-center fw-bold" name="otp_code" maxlength="6" placeholder="XXXXXX" required autofocus>
                    </div>
                    
                    <button type="submit" name="verify_btn" class="btn btn-primary w-100 fw-bold shadow-none py-2" onclick="showLoader('Verifying OTP...')">
                        <i class="fas fa-check-double me-2"></i> Verify & Link
                    </button>
                    
                    <a href="voter_mobile_link.php" class="btn btn-outline-danger w-100 mt-3 shadow-none btn-sm">Cancel</a>
                </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_msg; ?>
    function showLoader(txt) {
        Swal.fire({
            title: txt,
            html: 'Please wait...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
</script>

<?php require_once('../titancore/TitanFooter.php'); ob_end_flush(); ?>