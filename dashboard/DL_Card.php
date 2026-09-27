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
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'dl_pdf'");
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
if (isset($_POST['get_dl_btn'])) {
    $input_dl = mysqli_real_escape_string($conn, strtoupper(trim($_POST['dl_number'])));
    // Format DOB to DD-MM-YYYY strictly as required by API
    $raw_dob = trim($_POST['dob']); 
    $input_dob = date('d-m-Y', strtotime($raw_dob)); 
    
    // Wallet Check
    $user_wallet = $conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet'];
    
    if (empty($input_dl) || empty($raw_dob)) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter both DL Number and DOB.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        // Build Endpoint
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/DL_Card.php?api_key=" . urlencode($api_key) . "&dlNo=" . urlencode($input_dl) . "&dob=" . urlencode($input_dob);
        
        // cURL Request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $api_response = curl_exec($ch);
        curl_close($ch);
        
        if ($api_response) {
            $data = json_decode($api_response, true);
            
            // Check Success directly in main array
            if (isset($data['status']) && strtolower($data['status']) === 'success') {
                
                // Deduct Wallet
                $conn->query("UPDATE users SET wallet = wallet - $service_price WHERE id = '$uid'");
                
                // Extract Data
                $pdf_base64 = mysqli_real_escape_string($conn, $data['base64_pdf']);
                $msg = mysqli_real_escape_string($conn, $data['message']);
                
                // Save to History
                $insert = "INSERT INTO dl_pdf_history (user_id, dl_no, dob, pdf_data, message) 
                           VALUES ('$uid', '$input_dl', '$input_dob', '$pdf_base64', '$msg')";
                
                if ($conn->query($insert)) {
                    $swal_msg = "Swal.fire('Success!', 'DL PDF Fetched Successfully! Redirecting...', 'success').then(() => { window.location.href='dl_pdf_list.php'; });";
                } else {
                    $swal_msg = "Swal.fire('Database Error!', 'Generated but failed to save history.', 'error');";
                }
                
            } else {
                $err = $data['message'] ?? 'Failed to fetch DL Card. Check details.';
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
                    <div class="icon-item bg-danger-subtle shadow-none me-3">
                        <span class="fas fa-car text-danger fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-danger fs-10 mb-0">Transport Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Driving License <span class="text-danger fw-medium">(DL) Print</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="dl_pdf_list.php" class="btn btn-sm btn-outline-danger shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-danger">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-id-card me-2 text-danger"></i>Fetch DL PDF</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-danger border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-danger"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successful fetch.</p>
                </div>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Driving License (DL) Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-hashtag"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase" name="dl_number" placeholder="MH1220250015315" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Date of Birth (DOB) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" class="form-control shadow-none" name="dob" required>
                        </div>
                        <small class="text-500 mt-1 d-block">Select exact DOB linked to the DL.</small>
                    </div>
                    
                    <button class="btn btn-danger w-100 fw-bold shadow-none py-2" type="submit" name="get_dl_btn">
                        <i class="fas fa-download me-2"></i> Get DL PDF
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