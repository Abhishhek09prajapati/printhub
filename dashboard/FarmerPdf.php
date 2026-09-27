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
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'farmer_card'");
$service_price = ($price_check && $price_check->num_rows > 0) ? $price_check->fetch_assoc()['price'] : 5.00;

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
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/FarmerCard.php?api_key=" . urlencode($api_key) . "&aadhar=" . urlencode($input_aadhar) . "&state=" . urlencode($input_state);
        
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
            if (isset($data['status']) && strtolower($data['status']) === 'success') {
                
                // Deduct Wallet
                $conn->query("UPDATE users SET wallet = wallet - $service_price WHERE id = '$uid'");
                
                // Extract Data
                $pdf_base64 = mysqli_real_escape_string($conn, $data['base64_pdf']);
                $msg = mysqli_real_escape_string($conn, $data['message']);
                $date_time = date('Y-m-d H:i:s');
                
                // Save to History
                $insert = "INSERT INTO farmer_card_history (user_id, aadhar_no, state_code, pdf_data, message, created_at) 
                           VALUES ('$uid', '$input_aadhar', '$input_state', '$pdf_base64', '$msg', '$date_time')";
                
                if ($conn->query($insert)) {
                    $swal_msg = "Swal.fire('Success!', 'Farmer Card PDF Generated! Redirecting...', 'success').then(() => { window.location.href='FarmerPdf_List.php'; });";
                } else {
                    $swal_msg = "Swal.fire('Database Error!', 'Generated but failed to save history.', 'error');";
                }
                
            } else {
                $err = $data['message'] ?? 'Failed to generate card.';
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
                        <span class="fas fa-tractor text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Agri Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Farmer <span class="text-success fw-medium">Card Print</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <a href="FarmerPdf_List.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-leaf me-2 text-success"></i>Generate Farmer Card</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-success"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successful PDF generation.</p>
                </div>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control shadow-none" name="aadhar_number" placeholder="Enter 12-digit number" maxlength="12" pattern="\d{12}" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Select State <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-map-marker-alt"></i></span>
                            <select class="form-select shadow-none" name="state_code" required>
                                <option value="" disabled selected>Select State Code...</option>
<option value="an">Andaman and Nicobar Islands (AN)</option>
<option value="ap">Andhra Pradesh (AP)</option>
<option value="ar">Arunachal Pradesh (AR)</option>
<option value="as">Assam (AS)</option>
<option value="br">Bihar (BR)</option>
<option value="ch">Chandigarh (CH)</option>
<option value="cg">Chhattisgarh (CG)</option>
<option value="dn">Dadra and Nagar Haveli and Daman and Diu (DN)</option>
<option value="dl">Delhi (DL)</option>
<option value="ga">Goa (GA)</option>
<option value="gj">Gujarat (GJ)</option>
<option value="hr">Haryana (HR)</option>
<option value="hp">Himachal Pradesh (HP)</option>
<option value="jk">Jammu and Kashmir (JK)</option>
<option value="jh">Jharkhand (JH)</option>
<option value="ka">Karnataka (KA)</option>
<option value="kl">Kerala (KL)</option>
<option value="la">Ladakh (LA)</option>
<option value="ld">Lakshadweep (LD)</option>
<option value="mp">Madhya Pradesh (MP)</option>
<option value="mh">Maharashtra (MH)</option>
<option value="mn">Manipur (MN)</option>
<option value="ml">Meghalaya (ML)</option>
<option value="mz">Mizoram (MZ)</option>
<option value="nl">Nagaland (NL)</option>
<option value="od">Odisha (OD)</option>
<option value="py">Puducherry (PY)</option>
<option value="pb">Punjab (PB)</option>
<option value="rj">Rajasthan (RJ)</option>
<option value="sk">Sikkim (SK)</option>
<option value="tn">Tamil Nadu (TN)</option>
<option value="tg">Telangana (TG)</option>
<option value="tr">Tripura (TR)</option>
<option value="up">Uttar Pradesh (UP)</option>
<option value="uk">Uttarakhand (UK)</option>
<option value="wb">West Bengal (WB)</option>
                                <!-- Add more states as needed -->
                            </select>
                        </div>
                    </div>
                    
                    <button class="btn btn-success w-100 fw-bold shadow-none py-2" type="submit" name="get_card_btn">
                        <i class="fas fa-file-pdf me-2"></i> Generate Card PDF
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