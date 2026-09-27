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
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'aayushman_card'");
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
if (isset($_POST['get_card_btn'])) {
    $input_aadhar = mysqli_real_escape_string($conn, trim($_POST['aadhar_number']));
    $input_state = mysqli_real_escape_string($conn, trim($_POST['state_code']));
    
    // Wallet Check
    $user_wallet = $conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet'];
    
    if (strlen($input_aadhar) !== 12 || !is_numeric($input_aadhar)) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a valid 12-digit Aadhaar number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        // Build Endpoint
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/AayushmanCard.php?api_key=" . urlencode($api_key) . "&aadhaar_no=" . urlencode($input_aadhar) . "&state_code=" . urlencode($input_state);
        
        // cURL Request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $api_response = curl_exec($ch);
        curl_close($ch);
        
        if ($api_response) {
            $data = json_decode($api_response, true);
            
            // Check Success
            if (isset($data['result']['status']) && strtolower($data['result']['status']) === 'success') {
                
                // Deduct Wallet
                $conn->query("UPDATE users SET wallet = wallet - $service_price WHERE id = '$uid'");
                
                // Extract Data
                $card_details = $data['result']['data']['card_details'] ?? [];
                $card_no = mysqli_real_escape_string($conn, $card_details['card_no'] ?? 'N/A');
                $card_image = mysqli_real_escape_string($conn, $card_details['card_file'] ?? '');
                $msg = mysqli_real_escape_string($conn, $data['result']['message'] ?? '');
                
                // Save to History
                $insert = "INSERT INTO aayushman_card_history (user_id, aadhaar_no, state_code, card_no, card_image, message) 
                           VALUES ('$uid', '$input_aadhar', '$input_state', '$card_no', '$card_image', '$msg')";
                
                if ($conn->query($insert)) {
                    $swal_msg = "Swal.fire('Success!', 'Aayushman Card Fetched Successfully! Redirecting...', 'success').then(() => { window.location.href='ayushman_card_list.php'; });";
                } else {
                    $swal_msg = "Swal.fire('Database Error!', 'Generated but failed to save history.', 'error');";
                }
                
            } else {
                $err = $data['result']['message'] ?? 'Failed to fetch Aayushman Card. Check details.';
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
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-heartbeat text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Health Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Aayushman <span class="text-info fw-medium">Card Print</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="ayushman_card_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-id-card-alt me-2 text-primary"></i>Fetch Aayushman Card</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                
                <div class="alert alert-primary border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-primary"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successful fetch.</p>
                </div>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                            <input type="text" class="form-control shadow-none" name="aadhar_number" placeholder="Enter 12-digit number" maxlength="12" pattern="\d{12}" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Select State <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <select class="form-select shadow-none" name="state_code" required>
                                <option value="" disabled selected>Select State...</option>
                                <option value="35">ANDAMAN AND NICOBAR ISLANDS</option>
                                <option value="28">ANDHRA PRADESH</option>
                                <option value="12">ARUNACHAL PRADESH</option>
                                <option value="18">ASSAM</option>
                                <option value="10">BIHAR</option>
                                <option value="4">CHANDIGARH</option>
                                <option value="22">CHHATTISGARH</option>
                                <option value="26">DADRA AND NAGAR HAVELI AND DAMAN AND DIU</option>
                                <option value="7">DELHI</option>
                                <option value="30">GOA</option>
                                <option value="24">GUJARAT</option>
                                <option value="6">HARYANA</option>
                                <option value="2">HIMACHAL PRADESH</option>
                                <option value="1">JAMMU AND KASHMIR</option>
                                <option value="20">JHARKHAND</option>
                                <option value="29">KARNATAKA</option>
                                <option value="32">KERALA</option>
                                <option value="37">LADAKH</option>
                                <option value="31">LAKSHADWEEP</option>
                                <option value="23">MADHYA PRADESH</option>
                                <option value="27">MAHARASHTRA</option>
                                <option value="14">MANIPUR</option>
                                <option value="17">MEGHALAYA</option>
                                <option value="15">MIZORAM</option>
                                <option value="13">NAGALAND</option>
                                <option value="21">ODISHA</option>
                                <option value="34">PUDUCHERRY</option>
                                <option value="3">PUNJAB</option>
                                <option value="8">RAJASTHAN</option>
                                <option value="11">SIKKIM</option>
                                <option value="33">TAMIL NADU</option>
                                <option value="36">TELANGANA</option>
                                <option value="16">TRIPURA</option>
                                <option value="9">UTTAR PRADESH</option>
                                <option value="5">UTTARAKHAND</option>
                                <option value="19">WEST BENGAL</option>
                            </select>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary w-100 fw-bold shadow-none py-2" type="submit" name="get_card_btn">
                        <i class="fas fa-download me-2"></i> Fetch Aayushman Card
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