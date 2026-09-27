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
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'rc_pro_pdf'");
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
if (isset($_POST['get_rc_btn'])) {
    // Remove spaces and make uppercase
    $input_vehicle = strtoupper(str_replace(' ', '', trim($_POST['vehicle_number'])));
    
    // Wallet Check
    $user_wallet = $conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet'];
    
    if (empty($input_vehicle)) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a Vehicle Number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        // Build Endpoint
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/RCPro.php?api_key=" . urlencode($api_key) . "&vehicle_no=" . urlencode($input_vehicle);
        
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
                $resData = $data['data'] ?? [];
                $owner_name = mysqli_real_escape_string($conn, $resData['owner_name'] ?? 'N/A');
                $vehicle_class = mysqli_real_escape_string($conn, $resData['vehicle_class'] ?? 'N/A');
                $pdf_base64 = mysqli_real_escape_string($conn, $data['base64_pdf'] ?? '');
                $msg = mysqli_real_escape_string($conn, $data['message'] ?? '');
                
                // Save to History
                $insert = "INSERT INTO rc_pro_history (user_id, vehicle_no, owner_name, vehicle_class, pdf_data, message) 
                           VALUES ('$uid', '$input_vehicle', '$owner_name', '$vehicle_class', '$pdf_base64', '$msg')";
                
                if ($conn->query($insert)) {
                    $swal_msg = "Swal.fire('Success!', 'RC PRO PDF Fetched Successfully! Redirecting...', 'success').then(() => { window.location.href='rc_pro_list.php'; });";
                } else {
                    $swal_msg = "Swal.fire('Database Error!', 'Generated but failed to save history.', 'error');";
                }
                
            } else {
                $err = $data['message'] ?? 'Failed to fetch RC Card. Check details.';
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
                        <span class="fas fa-car-side text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">RTO Services</h6>
                        <h4 class="text-primary fw-bold mb-0">RC PRO <span class="text-warning fw-medium">PDF Print</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="rc_pro_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-warning">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-file-invoice me-2 text-warning"></i>Fetch RC PRO PDF</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-warning border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-warning"></i>
                    <p class="mb-0 fs-11 text-dark">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successful fetch.</p>
                </div>
                
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Vehicle Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-truck-pickup"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase" name="vehicle_number" placeholder="e.g. MH34AA7482" required>
                        </div>
                        <small class="text-500 mt-1 d-block">Enter without spaces.</small>
                    </div>
                    
                    <button class="btn btn-warning text-dark w-100 fw-bold shadow-none py-2" type="submit" name="get_rc_btn">
                        <i class="fas fa-download me-2"></i> Get RC PDF
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_msg; ?>
    // Auto remove spaces and make uppercase while typing
    document.querySelector('input[name="vehicle_number"]').addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/\s/g, '');
    });
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>