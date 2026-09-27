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
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'ration_pdf'");
$service_price = ($price_check && $price_check->num_rows > 0) ? floatval($price_check->fetch_assoc()['price']) : 4.00;

// API Config Fetch
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = $api_settings['titanurl'] ?? '';
$api_key = $api_settings['titan_api_key'] ?? '';

if (empty($TitanApi_Url) || empty($api_key)) {
    echo "<script>alert('System Error: API Configuration missing.'); window.location.href='titanhome.php';</script>";
    exit();
}

// Handle Form Logic
if (isset($_POST['get_ration_btn'])) {
    $input_ration = mysqli_real_escape_string($conn, trim($_POST['ration_number']));
    
    // Wallet Check
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    
    if (empty($input_ration)) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a valid Ration Number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        // Build Endpoint
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/Ration_Pdf.php?api_key=" . urlencode($api_key) . "&ration_no=" . urlencode($input_ration);
        
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
            
            // Flexible Status Check for API
            $api_status = $data['result']['status'] ?? $data['status'] ?? 'failed';
            $api_msg = $data['result']['message'] ?? $data['message'] ?? 'Failed to fetch Ration Card.';
            
            // Check Success
            if (strtolower($api_status) === 'success') {
                
                // ★ FIX: Calculate new balance first ★
                $new_wallet = $user_wallet - $service_price;
                
                // Deduct Wallet
                $conn->query("UPDATE users SET wallet = '$new_wallet' WHERE id = '$uid'");
                
                // History log update (Now using $new_wallet variable safely)
                $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$service_price', '$new_wallet', 'Ration PDF Print ($input_ration)', '1', 'Debit')");
                
                // Extract Data
                $resData = $data['result'] ?? $data;
                $head_name = mysqli_real_escape_string($conn, $resData['head_name'] ?? 'N/A');
                $homestate = mysqli_real_escape_string($conn, $resData['homestate'] ?? 'N/A');
                $district = mysqli_real_escape_string($conn, $resData['district'] ?? 'N/A');
                $pdf_base64 = mysqli_real_escape_string($conn, $resData['pdf_file'] ?? '');
                $msg = mysqli_real_escape_string($conn, $api_msg);
                
                // Save to History
                $insert = "INSERT INTO ration_pdf_history (user_id, ration_no, head_name, homestate, district, pdf_data, message) 
                           VALUES ('$uid', '$input_ration', '$head_name', '$homestate', '$district', '$pdf_base64', '$msg')";
                
                if ($conn->query($insert)) {
                    $swal_msg = "Swal.fire('Success!', 'Ration PDF Fetched Successfully!', 'success').then(() => { window.location.href='ration_pdf_list.php'; });";
                } else {
                    $swal_msg = "Swal.fire('Database Error!', 'Generated but failed to save history.', 'error');";
                }
                
            } else {
                $swal_msg = "Swal.fire('Failed!', 'Reason: " . addslashes($api_msg) . "', 'error');";
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
                        <span class="fas fa-file-invoice text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-success fs-10 mb-0">Food & Civil Supplies</h6>
                        <h4 class="text-primary fw-bold mb-0">Ration Card <span class="text-success fw-medium">PDF Print</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="ration_pdf_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-success">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-download me-2 text-success"></i>Fetch Ration PDF</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-success border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-success"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successful fetch.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="rationForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Ration Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase" name="ration_number" id="ration_number" placeholder="Enter Ration Number" required>
                        </div>
                    </div>
                    
                    <button class="btn btn-success w-100 fw-bold shadow-none py-2" type="button" onclick="confirmFetch()">
                        <i class="fas fa-search me-2"></i> Get Ration PDF
                    </button>
                    <button type="submit" name="get_ration_btn" id="realSubmit" class="d-none"></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_msg; ?>

    // Confirmation aur Loading spinner
    function confirmFetch() {
        let rationNo = document.getElementById('ration_number').value.trim();
        if(rationNo === '') {
            Swal.fire('Warning', 'Please enter a Ration Number.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Fetching Data...',
            html: 'Please wait while we connect to server.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Trigger real submit
        document.getElementById('realSubmit').click();
    }
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>