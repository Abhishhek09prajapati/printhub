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
$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'll_pdf'");
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
if (isset($_POST['get_ll_btn'])) {
    $input_app_no = mysqli_real_escape_string($conn, trim($_POST['application_number']));
    
    // Wallet Check
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    
    if (empty($input_app_no)) {
        $swal_msg = "Swal.fire('Invalid Input!', 'Please enter a valid Application Number.', 'error');";
    } elseif ($user_wallet < $service_price) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$service_price in your wallet.', 'warning');";
    } else {
        // Build Endpoint for LL PDF
        $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/LLPdf.php?api_key=" . urlencode($api_key) . "&application_no=" . urlencode($input_app_no);
        
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
            $api_msg = $data['result']['message'] ?? $data['message'] ?? 'Failed to fetch Learning License.';
            
            // Check Success
            if (strtolower($api_status) === 'success' && isset($data['result']['data']['pdf'])) {
                
                // Calculate new balance
                $new_wallet = $user_wallet - $service_price;
                
                // Deduct Wallet
                $conn->query("UPDATE users SET wallet = '$new_wallet' WHERE id = '$uid'");
                
                // History log update
                $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$service_price', '$new_wallet', 'LL PDF Print ($input_app_no)', '1', 'Debit')");
                
                // Extract PDF Data aur prefix fix karna
                $pdf_raw = $data['result']['data']['pdf'];
                if(strpos($pdf_raw, 'data:application/pdf;base64,') === false) {
                    $pdf_base64 = 'data:application/pdf;base64,' . $pdf_raw;
                } else {
                    $pdf_base64 = $pdf_raw;
                }
                
                $pdf_base64_escaped = mysqli_real_escape_string($conn, $pdf_base64);
                $msg_escaped = mysqli_real_escape_string($conn, $api_msg);
                
                // Save to History
                $insert = "INSERT INTO ll_pdf_history (user_id, application_no, pdf_data, message) 
                           VALUES ('$uid', '$input_app_no', '$pdf_base64_escaped', '$msg_escaped')";
                
                if ($conn->query($insert)) {
                    $swal_msg = "Swal.fire('Success!', 'Learning License Fetched Successfully!', 'success').then(() => { window.location.href='ll_pdf_list.php'; });";
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
                    <div class="icon-item bg-warning-subtle shadow-none me-3">
                        <span class="fas fa-car text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">RTO Services</h6>
                        <h4 class="text-primary fw-bold mb-0">Learning License <span class="text-warning fw-medium">PDF Fetch</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="ll_pdf_list.php" class="btn btn-sm btn-outline-primary shadow-none fw-bold"><i class="fas fa-list me-1"></i> View History</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm border-top border-4 border-warning">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 text-primary fw-bold text-center"><i class="fas fa-download me-2 text-warning"></i>Fetch LL PDF</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <div class="alert alert-warning border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-rupee-sign fs-3 me-3 text-warning"></i>
                    <p class="mb-0 fs-11">Service charge: <strong>₹<?php echo number_format($service_price, 2); ?></strong> will be deducted upon successful fetch.</p>
                </div>
                
                <form method="POST" autocomplete="off" id="llForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-900">Application Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                            <input type="text" class="form-control shadow-none text-uppercase" name="application_number" id="application_number" placeholder="Enter Application Number" required>
                        </div>
                    </div>
                    
                    <button class="btn btn-warning w-100 fw-bold shadow-none py-2 text-dark" type="button" onclick="confirmFetch()">
                        <i class="fas fa-search me-2"></i> Get LL PDF
                    </button>
                    <button type="submit" name="get_ll_btn" id="realSubmit" class="d-none"></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_msg; ?>

    function confirmFetch() {
        let appNo = document.getElementById('application_number').value.trim();
        if(appNo === '') {
            Swal.fire('Warning', 'Please enter a valid Application Number.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Fetching Data...',
            html: 'Please wait while we connect to RTO server.',
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