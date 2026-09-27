<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');
require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$u_name = $_SESSION['user_name'] ?? 'User';

$swal_msg = "";

// Pricing Fetch
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'aadhar_to_name'");
$service_price = ($price_check && $price_check->num_rows > 0) ? $price_check->fetch_assoc()['price'] : 10.00;

// API Config Fetch
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = $api_settings['titanurl'] ?? '';
$api_key = $api_settings['titan_api_key'] ?? '';

if (empty($TitanApi_Url) || empty($api_key)) {
    echo "<script>alert('System Error: API Configuration missing.'); window.location.href='titanhome.php';</script>";
    exit();
}

// Handle Form Logic
if (isset($_POST['find_name_btn'])) {
    $input_aadhar = mysqli_real_escape_string($conn, trim($_POST['aadhar_number']));
    
    // Wallet Check
    $user_wallet = $conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet'];
    
    if (strlen($input_aadhar) !== 12 || !is_numeric($input_aadhar)) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a valid 12-digit Aadhaar number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        // Build Endpoint
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/AadharToName.php?api_key=" . urlencode($api_key) . "&aadhaar_no=" . urlencode($input_aadhar);
        
        // cURL Request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $api_response = curl_exec($ch);
        curl_close($ch);
        
        if ($api_response) {
            $data = json_decode($api_response, true);
            
            // Success Check (API gives "status": "Success")
            if (isset($data['result']['status']) && strtolower($data['result']['status']) === 'success') {
                
                // Deduct Wallet
                $conn->query("UPDATE users SET wallet = wallet - $service_price WHERE id = '$uid'");
                
                // Extract Data
                $resData = $data['result']['responseData'];
                $fetched_name = mysqli_real_escape_string($conn, $resData['name'] ?? 'N/A');
                $fetched_local = mysqli_real_escape_string($conn, $resData['localName'] ?? 'N/A');
                $fetched_mobile = mysqli_real_escape_string($conn, $resData['mobile'] ?? 'N/A');
                
                $msg = mysqli_real_escape_string($conn, $data['result']['message']);
                $provider = mysqli_real_escape_string($conn, $data['result']['Provider']);
                $date_time = date('Y-m-d H:i:s');
                
                // Save to History
                $insert = "INSERT INTO aadhar_to_name_history (user_id, aadhar_no, name, local_name, mobile, message, provider, created_at) 
                           VALUES ('$uid', '$input_aadhar', '$fetched_name', '$fetched_local', '$fetched_mobile', '$msg', '$provider', '$date_time')";
                
                if ($conn->query($insert)) {
                    $swal_msg = "Swal.fire('Name Found!', 'Details fetched successfully! Redirecting...', 'success').then(() => { window.location.href='aadhar_to_name_list.php'; });";
                } else {
                    $swal_msg = "Swal.fire('Database Error!', 'Fetched but failed to save history.', 'error');";
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
                    <div class="icon-item bg-success-subtle shadow-none me-3">
                        <span class="fas fa-user-tag text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Identity Search</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhar To <span class="text-success fw-medium">Name</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <a href="aadhar_to_name_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2"></i>Find Name Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-success"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successfully finding the details.</p>
                </div>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control shadow-none" name="aadhar_number" placeholder="Enter 12-digit number" maxlength="12" pattern="\d{12}" required>
                        </div>
                    </div>
                    
                    <button class="btn btn-success w-100 fw-bold shadow-none py-2" type="submit" name="find_name_btn">
                        <i class="fas fa-user-check me-2"></i> Fetch Name & Mobile
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