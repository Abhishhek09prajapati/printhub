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
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'voter_pdf_download'");
$service_price = ($price_check && $price_check->num_rows > 0) ? floatval($price_check->fetch_assoc()['price']) : 7.00;

// API Config
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = rtrim($api_settings['titanurl'] ?? '', '/');
$api_key = $api_settings['titan_api_key'] ?? '';

// Variables to handle state
$step = 1;
$epic_val = "";
$state_cd_val = "";
$name_val = "";
$hindi_name_val = "";
$state_name_val = "";
$otp_msg = "";

// STEP 1: SEND OTP
if (isset($_POST['send_otp_btn'])) {
    $epic_val = strtoupper(trim($_POST['epic_no']));
    
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    if ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        $endpoint = $TitanApi_Url . "/api/v1/Voter_Download.php?api_key=" . urlencode($api_key) . 
                    "&action=send_otp&epic=" . urlencode($epic_val);
                    
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($res, true);
        if (isset($data['status']) && strtolower($data['status']) == 'success') {
            $step = 2; // Switch to OTP Form
            $state_cd_val = $data['state_cd'] ?? $data['stateCd'] ?? '';
            $name_val = $data['full_name'] ?? 'N/A';
            $hindi_name_val = $data['full_name_hindi'] ?? ''; // Safely passing Hindi
            $state_name_val = $data['state_name'] ?? 'N/A';
            $otp_msg = $data['otp_message'] ?? 'OTP has been sent to registered mobile.';
            
            $swal_msg = "Swal.fire('OTP Sent!', 'Please check your registered mobile number.', 'success');";
        } else {
            $err = $data['message'] ?? 'Failed to send OTP. Mobile might not be linked.';
            $swal_msg = "Swal.fire('Failed!', '" . addslashes($err) . "', 'error');";
        }
    }
}

// STEP 2: DOWNLOAD PDF
if (isset($_POST['download_btn'])) {
    $otp_val = trim($_POST['otp_code']);
    
    // Retrieve hidden details
    $epic_save = mysqli_real_escape_string($conn, $_POST['h_epic']);
    $state_cd_save = mysqli_real_escape_string($conn, $_POST['h_state_cd']);
    $name_save = mysqli_real_escape_string($conn, $_POST['h_name']);
    $hindi_name_save = mysqli_real_escape_string($conn, $_POST['h_hindi_name']);
    $state_name_save = mysqli_real_escape_string($conn, $_POST['h_state_name']);
    
    // Combine English and Hindi Name for DB
    $full_name_to_save = $name_save;
    if(!empty($hindi_name_save)) {
        $full_name_to_save .= " (" . $hindi_name_save . ")";
    }
    
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    if ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'Wallet balance low.', 'error');";
    } else {
        $endpoint = $TitanApi_Url . "/api/v1/Voter_Download.php?api_key=" . urlencode($api_key) . 
                    "&action=download&epic=" . urlencode($epic_save) . 
                    "&stateCd=" . urlencode($state_cd_save) . 
                    "&otp=" . urlencode($otp_val);
                    
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($res, true);
        if (isset($data['status']) && strtolower($data['status']) == 'success' && isset($data['base64_pdf'])) {
            
            // Deduct Wallet Only on Success
            $new_wallet = $user_wallet - $service_price;
            $conn->query("UPDATE users SET wallet = '$new_wallet' WHERE id = '$uid'");
            
            // Wallet History
            $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$service_price', '$new_wallet', 'Voter PDF Download ($epic_save)', '1', 'Debit')");
            
            // Clean & Save Base64
            $pdf_raw = $data['base64_pdf'];
            if(strpos($pdf_raw, 'data:application/pdf;base64,') === false) {
                $pdf_base64 = 'data:application/pdf;base64,' . $pdf_raw;
            } else {
                $pdf_base64 = $pdf_raw;
            }
            $pdf_base64_escaped = mysqli_real_escape_string($conn, $pdf_base64);
            
            // Save to Service History
            $insert = "INSERT INTO voter_pdf_history (user_id, epic_no, full_name, state_name, pdf_data) 
                       VALUES ('$uid', '$epic_save', '$full_name_to_save', '$state_name_save', '$pdf_base64_escaped')";
            $conn->query($insert);
            
            $swal_msg = "Swal.fire('PDF Downloaded!', 'Voter Card fetched successfully.', 'success').then(() => { window.location.href='voter_pdf_list.php'; });";
        } else {
            // Stay on step 2 if OTP fails
            $step = 2;
            $epic_val = $epic_save; $state_cd_val = $state_cd_save; 
            $name_val = $name_save; $hindi_name_val = $hindi_name_save; $state_name_val = $state_name_save;
            
            $err = $data['message'] ?? 'Invalid OTP or Download failed.';
            $swal_msg = "Swal.fire('Failed!', '" . addslashes($err) . "', 'error');";
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
                        <span class="fas fa-file-pdf text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-info fs-10 mb-0">Election Commission</h6>
                        <h4 class="text-primary fw-bold mb-0">Voter Card <span class="fw-medium">PDF Download</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="voter_pdf_list.php" class="btn btn-sm btn-outline-info shadow-none fw-bold"><i class="fas fa-list me-1"></i> View Download History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-info">
            <div class="card-header bg-body-tertiary border-bottom">
                <h5 class="mb-0 text-info fw-bold text-center"><i class="fas fa-download me-2"></i>Download Original Voter PDF</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                
                <?php if($step == 1): ?>
                <!-- STEP 1 FORM -->
                <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-info"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong>. Deducted only when PDF is downloaded.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="sendOtpForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold">EPIC Number (Voter ID) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase" name="epic_no" placeholder="e.g., ABC1234567" required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" name="send_otp_btn" class="btn btn-info text-white w-100 fw-bold shadow-none py-2" onclick="showLoader('Sending OTP...')">
                        <i class="fas fa-paper-plane me-2"></i> Get OTP
                    </button>
                </form>

                <?php elseif($step == 2): ?>
                <!-- STEP 2 FORM -->
                <div class="alert alert-success border-0 mb-4">
                    <h6 class="fw-bold text-success"><i class="fas fa-check-circle me-1"></i> Details Found!</h6>
                    <ul class="mb-0 fs-11 mt-2 list-unstyled text-dark">
                        <li><strong>Name:</strong> <?php echo htmlspecialchars($name_val); ?> <?php echo (!empty($hindi_name_val)) ? '('.$hindi_name_val.')' : ''; ?></li>
                        <li><strong>State:</strong> <?php echo htmlspecialchars($state_name_val); ?></li>
                    </ul>
                    <hr class="my-2">
                    <p class="mb-0 fs-11 fw-semi-bold text-danger"><?php echo htmlspecialchars($otp_msg); ?></p>
                </div>

                <form method="POST" autocomplete="off">
                    <!-- Hidden State -->
                    <input type="hidden" name="h_epic" value="<?php echo htmlspecialchars($epic_val); ?>">
                    <input type="hidden" name="h_state_cd" value="<?php echo htmlspecialchars($state_cd_val); ?>">
                    <input type="hidden" name="h_name" value="<?php echo htmlspecialchars($name_val); ?>">
                    <input type="hidden" name="h_hindi_name" value="<?php echo htmlspecialchars($hindi_name_val); ?>">
                    <input type="hidden" name="h_state_name" value="<?php echo htmlspecialchars($state_name_val); ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Enter OTP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control shadow-none fs-3 tracking-widest text-center fw-bold" name="otp_code" maxlength="6" placeholder="XXXXXX" required autofocus>
                    </div>
                    
                    <button type="submit" name="download_btn" class="btn btn-primary w-100 fw-bold shadow-none py-2" onclick="showLoader('Downloading PDF...')">
                        <i class="fas fa-download me-2"></i> Verify & Download PDF
                    </button>
                    
                    <a href="voter_pdf.php" class="btn btn-outline-danger w-100 mt-3 shadow-none btn-sm">Cancel</a>
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