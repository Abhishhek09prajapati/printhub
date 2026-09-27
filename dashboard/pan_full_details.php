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
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'pan_full_details'");
$service_price = ($price_check && $price_check->num_rows > 0) ? floatval($price_check->fetch_assoc()['price']) : 10.00;

// API Config Fetch
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = $api_settings['titanurl'] ?? '';
$api_key = $api_settings['titan_api_key'] ?? '';

if (empty($TitanApi_Url) || empty($api_key)) {
    echo "<script>alert('System Error: API Configuration missing.'); window.location.href='titanhome.php';</script>";
    exit();
}

// Handle Form Logic
if (isset($_POST['get_pan_btn'])) {
    $input_pan = mysqli_real_escape_string($conn, strtoupper(trim($_POST['pan_number'])));
    
    // Wallet Check
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    
    if (empty($input_pan)) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a valid PAN Number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        // Build Endpoint
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/Pan_Details.php?api_key=" . urlencode($api_key) . "&pan_no=" . urlencode($input_pan);
        
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
            
            // Status Check (API JSON format ke hisaab se)
            $api_status_msg = $data['result']['statusMessage'] ?? 'failed';
            $api_status_code = $data['result']['status'] ?? '';
            
            // Check Success
            if (strtolower($api_status_msg) === 'success' || $api_status_code === '100') {
                
                // Calculate new balance
                $new_wallet = $user_wallet - $service_price;
                
                // Deduct Wallet
                $conn->query("UPDATE users SET wallet = '$new_wallet' WHERE id = '$uid'");
                
                // History log update
                $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$service_price', '$new_wallet', 'PAN Full Details ($input_pan)', '1', 'Debit')");
                
                // Extract Data
                $resData = $data['result'];
                $pan_no = mysqli_real_escape_string($conn, $resData['panNumber'] ?? $input_pan);
                $name = mysqli_real_escape_string($conn, $resData['name'] ?? 'N/A');
                $father_name = mysqli_real_escape_string($conn, $resData['fatherName'] ?? 'N/A');
                $dob = mysqli_real_escape_string($conn, $resData['dob'] ?? 'N/A');
                $gender = mysqli_real_escape_string($conn, $resData['gender'] ?? 'N/A');
                $masked_aadhaar = mysqli_real_escape_string($conn, $resData['maskedAadhaar'] ?? 'N/A');
                
                // Aadhaar link check
                $aadhaar_linked = (isset($resData['aadhaarLinked']) && $resData['aadhaarLinked']) ? 'Yes' : 'No';
                
                // Save to History
                $insert = "INSERT INTO pan_details_history (user_id, pan_no, name, father_name, dob, gender, masked_aadhaar, aadhaar_linked) 
                           VALUES ('$uid', '$pan_no', '$name', '$father_name', '$dob', '$gender', '$masked_aadhaar', '$aadhaar_linked')";
                
                if ($conn->query($insert)) {
                    $swal_msg = "Swal.fire('Success!', 'PAN Details Fetched Successfully!', 'success').then(() => { window.location.href='pan_full_details_list.php'; });";
                } else {
                    $swal_msg = "Swal.fire('Database Error!', 'Generated but failed to save history.', 'error');";
                }
                
            } else {
                $err = $data['result']['message'] ?? 'PAN Details not found.';
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
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-id-card text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Identity Services</h6>
                        <h4 class="text-primary fw-bold mb-0">PAN To <span class="fw-medium">Full Details</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="pan_full_details_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View Details History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-search me-2 text-primary"></i>Fetch PAN Details</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successful fetch.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="panForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">PAN Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-keyboard"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase" name="pan_number" id="pan_number" placeholder="Enter 10-digit PAN" maxlength="10" required>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary w-100 fw-bold shadow-none py-2" type="button" onclick="confirmFetch()">
                        <i class="fas fa-cloud-download-alt me-2"></i> Get PAN Details
                    </button>
                    <button type="submit" name="get_pan_btn" id="realSubmit" class="d-none"></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_msg; ?>

    function confirmFetch() {
        let panNo = document.getElementById('pan_number').value.trim();
        if(panNo === '' || panNo.length < 10) {
            Swal.fire('Warning', 'Please enter a valid 10-digit PAN Number.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Fetching Details...',
            html: 'Please wait while we connect to NSDL/UTI server.',
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