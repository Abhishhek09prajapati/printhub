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
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'aadhar_to_pan_req'");
$service_price = ($price_check && $price_check->num_rows > 0) ? floatval($price_check->fetch_assoc()['price']) : 80.00;

// API Config
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = rtrim($api_settings['titanurl'] ?? '', '/');
$api_key = $api_settings['titan_api_key'] ?? '';

// Handle Form Submit
if (isset($_POST['req_pan_btn'])) {
    $aadhaar_val = trim($_POST['aadhaar_no']);
    
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    
    if (empty($aadhaar_val) || strlen($aadhaar_val) != 12) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a valid 12-digit Aadhaar Number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        $endpoint = $TitanApi_Url . "/api/v1/AadharToPanReq.php?api_key=" . urlencode($api_key) . "&aadhaar_no=" . urlencode($aadhaar_val);
                    
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($res, true);
        
        if (isset($data['status']) && strtolower($data['status']) == 'success') {
            
            // Deduct Wallet
            $new_wallet = $user_wallet - $service_price;
            $conn->query("UPDATE users SET wallet = '$new_wallet' WHERE id = '$uid'");
            
            // Log Transaction
            $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$service_price', '$new_wallet', 'Aadhaar to PAN Request', '1', 'Debit')");
            
            // Save Data
            $app_no = mysqli_real_escape_string($conn, $data['application_no'] ?? '');
            $mask_pan = mysqli_real_escape_string($conn, $data['result']['mask_pan'] ?? '');
            $api_status = mysqli_real_escape_string($conn, $data['result']['status'] ?? 'pending');
            $aadhaar_safe = mysqli_real_escape_string($conn, $aadhaar_val);
            
            $insert = "INSERT INTO aadhar_to_pan_req_history (user_id, application_no, aadhaar_no, mask_pan, status) 
                       VALUES ('$uid', '$app_no', '$aadhaar_safe', '$mask_pan', '$api_status')";
                       
            if($conn->query($insert)){
                $swal_msg = "Swal.fire('Request Submitted!', 'Your PAN request is pending. Check list to track status.', 'success').then(() => { window.location.href='aadhar_to_pan_list.php'; });";
            } else {
                $swal_msg = "Swal.fire('Database Error!', 'Generated but failed to save history.', 'error');";
            }
        } else {
            $err = $data['message'] ?? $data['result']['message'] ?? 'Failed to submit request.';
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
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-id-card text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Identity Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar To PAN <span class="fw-medium">Request</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="aadhar_to_pan_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> Track Requests</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-body-tertiary border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-paper-plane me-2"></i>Initiate PAN Request</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted for this request.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="reqForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold tracking-wide" name="aadhaar_no" maxlength="12" placeholder="Enter 12-digit Aadhaar No." required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" name="req_pan_btn" class="btn btn-primary w-100 fw-bold shadow-none py-2" onclick="showLoader('Submitting Request...')">
                        <i class="fas fa-cloud-upload-alt me-2"></i> Submit Request
                    </button>
                </form>
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