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
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'pan_to_aadhar'");
$service_price = ($price_check && $price_check->num_rows > 0) ? floatval($price_check->fetch_assoc()['price']) : 20.00;

// API Config
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = rtrim($api_settings['titanurl'] ?? '', '/');
$api_key = $api_settings['titan_api_key'] ?? '';

if (isset($_POST['find_aadhar_btn'])) {
    $pan_val = mysqli_real_escape_string($conn, strtoupper(trim($_POST['pan_no'])));
    
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    
    if (empty($pan_val) || strlen($pan_val) < 10) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a valid 10-digit PAN Number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        $endpoint = $TitanApi_Url . "/api/v1/PanToAadhar.php?api_key=" . urlencode($api_key) . "&pan_no=" . urlencode($pan_val);
                    
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($res, true);
        
        if (isset($data['status']) && strtolower($data['status']) == 'success' && isset($data['result']['data']['aadhaar_number'])) {
            
            // Deduct Wallet
            $new_wallet = $user_wallet - $service_price;
            $conn->query("UPDATE users SET wallet = '$new_wallet' WHERE id = '$uid'");
            
            // Log Transaction
            $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$service_price', '$new_wallet', 'PAN To Aadhaar ($pan_val)', '1', 'Debit')");
            
            // ★ FIX: Extracting and saving exact data from API ★
            $resData = $data['result']['data'];
            $aadhar_number = mysqli_real_escape_string($conn, $resData['aadhaar_number']);
            $name = mysqli_real_escape_string($conn, $resData['name'] ?? 'N/A');
            $dob = mysqli_real_escape_string($conn, $resData['dob'] ?? 'N/A');
            $gender = mysqli_real_escape_string($conn, $resData['gender'] ?? 'N/A');
            
            $insert = "INSERT INTO pan_to_aadhar_history (user_id, pan_no, aadhaar_no, name, dob, gender) 
                       VALUES ('$uid', '$pan_val', '$aadhar_number', '$name', '$dob', '$gender')";
                       
            if($conn->query($insert)){
                $swal_msg = "Swal.fire('Found Successfully!', 'Aadhaar details fetched from PAN.', 'success').then(() => { window.location.href='pan_to_aadhar_list.php'; });";
            } else {
                $swal_msg = "Swal.fire('Database Error!', 'Generated but failed to save history.', 'error');";
            }
        } else {
            $err = $data['result']['message'] ?? $data['message'] ?? 'Failed to find Aadhaar details.';
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
                        <span class="fas fa-id-card-alt text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Identity Services</h6>
                        <h4 class="text-primary fw-bold mb-0">PAN To <span class="fw-medium">Aadhaar Find</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="pan_to_aadhar_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View Search History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-body-tertiary border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Find Aadhaar by PAN</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successful search.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="panToAadharForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold">PAN Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-keyboard"></i></span>
                            <input type="text" class="form-control shadow-none fw-bold text-uppercase tracking-wide" name="pan_no" maxlength="10" placeholder="e.g. ABCDE1234F" required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" name="find_aadhar_btn" class="btn btn-primary w-100 fw-bold shadow-none py-2" onclick="showLoader('Searching Database...')">
                        <i class="fas fa-fingerprint me-2"></i> Get Linked Aadhaar
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
            html: 'Connecting to server, please wait...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
</script>

<?php require_once('../titancore/TitanFooter.php'); ob_end_flush(); ?>