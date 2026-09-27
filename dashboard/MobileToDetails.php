<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');
mysqli_set_charset($conn, "utf8mb4");

$uid = mysqli_real_escape_string($conn, $_SESSION['user_id']);
$u_phone = mysqli_real_escape_string($conn, $_SESSION['phone'] ?? '');
$u_name = $_SESSION['user_name'] ?? 'User';
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? '';
$swal_msg = "";

$price_check = $conn->query("SELECT price FROM pricing WHERE service_name = 'pan_full_details'");
$fee = ($price_check && $price_check->num_rows > 0) ? floatval($price_check->fetch_assoc()['price']) : 10.00;

$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = rtrim($api_settings['titanurl'] ?? '', '/');
$api_key = $api_settings['titan_api_key'] ?? '';

if (isset($_POST['get_pan_btn'])) {
    $input_pan = strtoupper(trim($_POST['pan_number']));
    $user_wallet = floatval($conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc()['wallet']);
    
    if ($user_wallet < $fee) {
        $swal_msg = "Swal.fire('Low Balance', 'You need ₹$fee.', 'warning');";
    } else {
        $endpoint = $TitanApi_Url . "/api/v1/Pan_Details.php?api_key=" . urlencode($api_key) . "&pan_no=" . urlencode($input_pan);
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $api_response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($api_response, true);
        
        if (isset($data['result']['status']) && ($data['result']['status'] == '100' || strtolower($data['result']['statusMessage']) == 'success')) {
            $new_wallet = $user_wallet - $fee;
            $conn->query("UPDATE users SET wallet = '$new_wallet' WHERE id = '$uid'");
            $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$fee', '$new_wallet', 'PAN Full Details ($input_pan)', '1', 'Debit')");
            
            $res = $data['result'];
            $name = mysqli_real_escape_string($conn, $res['name'] ?? 'N/A');
            $fname = mysqli_real_escape_string($conn, $res['fatherName'] ?? 'N/A');
            $dob = mysqli_real_escape_string($conn, $res['dob'] ?? 'N/A');
            $gender = mysqli_real_escape_string($conn, $res['gender'] ?? 'N/A');
            $masked = mysqli_real_escape_string($conn, $res['maskedAadhaar'] ?? 'N/A');
            $linked = (isset($res['aadhaarLinked']) && $res['aadhaarLinked']) ? 'Yes' : 'No';
            
            $conn->query("INSERT INTO pan_details_history (user_id, pan_no, name, father_name, dob, gender, masked_aadhaar, aadhaar_linked) 
                          VALUES ('$uid', '$input_pan', '$name', '$fname', '$dob', '$gender', '$masked', '$linked')");
            
            $swal_msg = "Swal.fire('Success', 'PAN Details Fetched!', 'success').then(() => { window.location.href='pan_full_details_list.php'; });";
        } else {
            $swal_msg = "Swal.fire('Failed', 'PAN Details not found!', 'error');";
        }
    }
}

require_once('../titancore/titanheader.php');
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3"><span class="fas fa-id-card text-primary fs-4"></span></div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Welcome, <?php echo $u_name; ?></h6>
                        <h4 class="text-primary fw-bold mb-0">PAN To <span class="fw-medium">Full Details</span></h4>
                    </div>
                </div>
                <div class="col-md-auto"><a href="pan_full_details_list.php" class="btn btn-sm btn-outline-primary fw-bold"><i class="fas fa-list me-1"></i> View History</a></div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST" id="panForm">
                    <div class="mb-3">
                        <label class="fw-bold">Enter 10 Digit PAN Number</label>
                        <input type="text" name="pan_number" class="form-control text-uppercase" maxlength="10" required>
                    </div>
                    <button type="submit" name="get_pan_btn" class="btn btn-primary w-100 fw-bold">Fetch PAN Details (₹<?php echo $fee; ?>)</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script><?php echo $swal_msg; ?></script>
<?php require_once('../titancore/TitanFooter.php'); ob_end_flush(); ?>