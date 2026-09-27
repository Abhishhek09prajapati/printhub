<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// 1. Database Connection
require_once('../titancore/titanconfig.php');

// =================================================================================
// ★ FETCH GATEWAY DETAILS ★
// =================================================================================
$gateway_base_url = "https://titangateway.in"; 
$gateway_api_token = ""; 

$pay_query = $conn->query("SELECT gateway_url, gateway_token FROM TitanPayment LIMIT 1");
if ($pay_query && $pay_query->num_rows > 0) {
    $pay_row = $pay_query->fetch_assoc();
    if (!empty($pay_row['gateway_url'])) {
        $gateway_base_url = rtrim(trim($pay_row['gateway_url']), '/'); 
    }
    if (!empty($pay_row['gateway_token'])) {
        $gateway_api_token = trim($pay_row['gateway_token']);
    }
}
// =================================================================================

// =================================================================================
// ★ EMBEDDED AJAX STATUS CHECKER & REGISTRATION INSERTER (SECURE POST) ★
// =================================================================================
if (isset($_GET['action']) && $_GET['action'] == 'check_status' && isset($_POST['order_id'])) {
    header('Content-Type: application/json');
    
    $check_token = $gateway_api_token; 
    $check_url = $gateway_base_url . "/api/check-order-status";
    $check_order_id = $conn->real_escape_string(trim($_POST['order_id']));
    
    // Gateway Check
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $check_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'user_token' => $check_token,
        'order_id'   => $check_order_id
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    $local_updated = false;
    $api_status = 'PENDING';
    
    if ($data) {
        if (isset($data['status'])) {
            if ($data['status'] === true) $api_status = 'SUCCESS';
            else $api_status = strtoupper((string)$data['status']);
        }
        if (isset($data['result']['status'])) $api_status = strtoupper((string)$data['result']['status']);
        if (isset($data['result']['txnStatus'])) $api_status = strtoupper((string)$data['result']['txnStatus']);
        
        // Agar Gateway se Success hai toh DB me Account banao
        if ($api_status === 'COMPLETED' || $api_status === 'SUCCESS' || $api_status === 'TRUE') {
            
            // POST se data nikal rahe hain
            $reg_phone = $conn->real_escape_string(trim($_POST['phone']));
            $reg_email = $conn->real_escape_string(trim($_POST['email']));
            $reg_name = $conn->real_escape_string(trim($_POST['name']));
            $reg_shop = $conn->real_escape_string(trim($_POST['shop_name']));
            $reg_type = $conn->real_escape_string(trim($_POST['type']));
            $reg_pass = $conn->real_escape_string(trim($_POST['pass']));
            
            // Double entry rokne ke liye check karo
            $chk_query = $conn->query("SELECT id FROM users WHERE phone='$reg_phone' OR email='$reg_email' LIMIT 1");
            
            if ($chk_query && $chk_query->num_rows == 0) {
                // New User Insert 
                $ins_sql = "INSERT INTO users (name, shop_name, email, phone, user_type, password, status) 
                            VALUES ('$reg_name', '$reg_shop', '$reg_email', '$reg_phone', '$reg_type', '$reg_pass', 'Active')";
                if ($conn->query($ins_sql)) {
                    $local_updated = true;
                }
            } else {
                $local_updated = true; // User already ban chuka hai pichle 1 second me
            }
        }
    }
    
    echo json_encode([
        "local_success" => $local_updated,
        "gateway_msg" => $api_status
    ]);
    exit;
}
// =================================================================================

// 2. Settings fetch karein
$settings_query = $conn->query("SELECT * FROM settings WHERE id = 1");
$site = $settings_query->fetch_assoc();

$display_name = $site['site_name'] ?? "ApiNexus"; 
$is_reg_open = $site['self_register'] ?? 1;

// ★ REDIRECT IF CLOSED ★
if ($is_reg_open == 0) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
          <body style='font-family: sans-serif;'>
          <script>
            setTimeout(function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Registration Closed',
                    text: 'Self-registration is currently disabled by Admin.',
                    confirmButtonText: 'Back to Login'
                }).then(() => { window.location.href = 'login.php'; });
            }, 100);
          </script>
          </body>";
    exit; 
}

$swal_script = ""; 

if (isset($_POST['register_btn'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $shop_name = mysqli_real_escape_string($conn, $_POST['shop_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $user_type = mysqli_real_escape_string($conn, $_POST['user_type']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = $_POST['confirm_password'];

    // Get Fees from Settings
    $reg_fee = 0;
    if($user_type == 'Retailer') $reg_fee = floatval($site['retailer_price']);
    if($user_type == 'Distributor') $reg_fee = floatval($site['distributor_price']);
    if($user_type == 'Head Branch') $reg_fee = floatval($site['super_price']);

    if ($password !== $confirm_password) {
        $swal_script = "Swal.fire('Error', 'Password match nahi ho rahe!', 'error');";
    } else {
        $check_user = $conn->query("SELECT id FROM users WHERE email = '$email' OR phone = '$phone'");
        if ($check_user->num_rows > 0) {
            $swal_script = "Swal.fire('Warning', 'Email ya Phone pehle se registered hai!', 'warning');";
        } else {
            
            // --- CASE 1: PAID REGISTRATION ---
            if ($reg_fee > 0) {
                $order_id = "REG" . time() . rand(100, 999);
                $post_data = [
                    'customer_mobile' => $phone,
                    'user_token'      => $gateway_api_token,
                    'amount'          => $reg_fee,
                    'order_id'        => $order_id,
                    'remark1'         => $name,
                    'remark2'         => 'Registration'
                ];

                $ch = curl_init($gateway_base_url . "/api/create-order");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                curl_close($ch);

                if ($response) {
                    $resArray = json_decode($response, true);
                    
                    if (!empty($resArray['status']) && isset($resArray['result']['payment_url'])) {
                        
                        $payment_url = $resArray['result']['payment_url'];
                        
                        // =====================================================================
                        // ★ DIRECT UPI EXTRACTOR ★
                        // =====================================================================
                        $ch_qr = curl_init();
                        curl_setopt($ch_qr, CURLOPT_URL, $payment_url);
                        curl_setopt($ch_qr, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch_qr, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch_qr, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch_qr, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64)");
                        $html_resp = curl_exec($ch_qr);
                        curl_close($ch_qr);

                        $final_qr_data = $payment_url; 
                        $is_direct_upi = 0; 
                        if (preg_match('/upi:\/\/pay\?[^\s"\'<>]+/i', $html_resp, $matches)) {
                            $final_qr_data = html_entity_decode($matches[0]);
                            $is_direct_upi = 1;
                        } 
                        
                        $qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=1&data=" . urlencode($final_qr_data);
                        
                        // JS Safe Variables
                        $js_name = addslashes($name);
                        $js_shop = addslashes($shop_name);
                        $js_email = addslashes($email);
                        $js_phone = addslashes($phone);
                        $js_type = addslashes($user_type);
                        $js_pass = addslashes($password);

                        // ★ UI POPUP WITH INVISIBLE IFRAME (KEEP SESSION ALIVE) ★
                        $swal_script = "
                            const orderId = '{$order_id}';
                            const dynamicSiteName = '{$display_name}'; 
                            
                            let actionButton = '';
                            if ('{$is_direct_upi}' === '1') {
                                actionButton = `<a href='{$final_qr_data}' class='btn w-100 fw-bold text-white mb-2 shadow-sm' style='background: #570fa3; border-radius: 8px;'><i class='fas fa-mobile-alt me-2'></i> Pay via UPI App</a>`;
                            }
                            
                            let sweetAlertHtml = `
                                <div class='upi-container'>
                                    <iframe src='{$payment_url}' sandbox='allow-scripts allow-same-origin' style='width:0;height:0;border:0;display:none;position:absolute;'></iframe>

                                    <div class='upi-header'>Account Activation</div>
                                    <div class='upi-merchant-bar'>Merchant: \${dynamicSiteName}</div>
                                    <div class='upi-body'>
                                        <div id='mainPaymentView'>
                                            
                                            <div class='upi-qr-box'>
                                                <img src='{$qrImg}' alt='QR Code'>
                                            </div>
                                            
                                            <div class='upi-amount-box'>
                                                <div class='amount-text'>Pay Amount: ₹{$reg_fee}</div>
                                                <div class='timer-text'>
                                                    <i class='fas fa-clock'></i> Expires in: <span id='qrTimer'>30:00</span>
                                                </div>
                                            </div>

                                            \${actionButton}
                                            
                                            <button id='manualVerifyBtn' class='btn w-100 fw-bold text-white mb-3 shadow-sm' style='background: #28a745; border-radius: 8px;'>
                                                <i class='fas fa-sync-alt me-1'></i> I Have Paid (Verify)
                                            </button>

                                            <div class='upi-secure-badge'>
                                                <i class='fas fa-shield-alt'></i> Secure Account Activation
                                            </div>
                                            <div id='payStatus' class='fw-bold mt-2' style='color: #4b4b4b; font-size: 13px;'></div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            Swal.fire({
                                html: sweetAlertHtml,
                                showConfirmButton: false,
                                showCloseButton: true,
                                allowOutsideClick: false,
                                background: 'transparent',
                                padding: 0,
                                customClass: { popup: 'custom-swal-popup', closeButton: 'custom-swal-close' },
                                didOpen: () => {
                                    const mainPaymentView = document.getElementById('mainPaymentView');
                                    const payStatus = document.getElementById('payStatus');
                                    const timerDisplay = document.getElementById('qrTimer');
                                    const manualVerifyBtn = document.getElementById('manualVerifyBtn');
                                    
                                    let statusCheckInterval;
                                    let isProcessing = false;
                                    let timerDuration = 1800; 
                                    
                                    const countdown = setInterval(() => {
                                        let minutes = parseInt(timerDuration / 60, 10);
                                        let seconds = parseInt(timerDuration % 60, 10);
                                        minutes = minutes < 10 ? '0' + minutes : minutes;
                                        seconds = seconds < 10 ? '0' + seconds : seconds;
                                        if(timerDisplay) timerDisplay.textContent = minutes + ':' + seconds;
                                        if (--timerDuration < 0) {
                                            clearInterval(countdown);
                                            if(timerDisplay) timerDisplay.textContent = 'EXPIRED';
                                            payStatus.innerHTML = '<span class=\"text-danger\">QR Code Expired. Please retry.</span>';
                                        }
                                    }, 1000);
                                    
                                    const verifyPaymentStatus = async (isManualClick = false) => {
                                        if(isProcessing || timerDuration < 0) return;
                                        isProcessing = true;
                                        
                                        if(isManualClick) {
                                            payStatus.innerHTML = '<span class=\"spinner-border spinner-border-sm text-success\"></span> Verifying Transaction...';
                                            manualVerifyBtn.disabled = true;
                                        }

                                        try {
                                            const formData = new URLSearchParams();
                                            formData.append('order_id', orderId);
                                            formData.append('name', '{$js_name}');
                                            formData.append('shop_name', '{$js_shop}');
                                            formData.append('email', '{$js_email}');
                                            formData.append('phone', '{$js_phone}');
                                            formData.append('type', '{$js_type}');
                                            formData.append('pass', '{$js_pass}');

                                            const fetchUrl = '?action=check_status&t=' + new Date().getTime();
                                            const response = await fetch(fetchUrl, {
                                                method: 'POST',
                                                body: formData
                                            });
                                            const data = await response.json();
                                            
                                            if (data.local_success === true) {
                                                clearInterval(statusCheckInterval);
                                                clearInterval(countdown);
                                                
                                                // ★ SWEET ALERT SUCCESS POPUP ADDED HERE ★
                                                confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                                                Swal.fire({
                                                    icon: 'success',
                                                    title: 'Payment Successful!',
                                                    text: 'Account created successfully!',
                                                    showConfirmButton: false,
                                                    timer: 3000
                                                }).then(() => {
                                                    window.location.href = 'login.php';
                                                });
                                                
                                            } else if (isManualClick) {
                                                let errorStatus = data.gateway_msg || 'PENDING';
                                                payStatus.innerHTML = `<span class=\"text-danger\">Status: \${errorStatus}. Wait 5 sec & try again.</span>`;
                                                setTimeout(() => { manualVerifyBtn.disabled = false; }, 3000);
                                            }
                                        } catch(err) { }
                                        
                                        isProcessing = false;
                                    };

                                    manualVerifyBtn.addEventListener('click', () => {
                                        verifyPaymentStatus(true);
                                    });
                                    
                                    document.addEventListener('visibilitychange', () => {
                                        if (document.visibilityState === 'visible') {
                                            payStatus.innerHTML = '<span class=\"spinner-border spinner-border-sm\" style=\"color:#570fa3\"></span> Resuming check...';
                                            verifyPaymentStatus(false);
                                        }
                                    });
                                    
                                    payStatus.innerHTML = '<span class=\"spinner-border spinner-border-sm\" style=\"color:#570fa3\"></span> Listening for payment...';
                                    
                                    statusCheckInterval = setInterval(() => {
                                        if(document.visibilityState === 'visible') {
                                            verifyPaymentStatus(false);
                                        }
                                    }, 3500); 
                                    
                                    Swal.getPopup().addEventListener('close', () => {
                                        if (statusCheckInterval) clearInterval(statusCheckInterval);
                                        clearInterval(countdown);
                                    });
                                }
                            });
                        ";
                    } else {
                        $swal_script = "Swal.fire('Gateway Error', 'Failed to generate Gateway Token.', 'error');";
                    }
                } else {
                    $swal_script = "Swal.fire('Error', 'Gateway timeout or connection failed.', 'error');";
                }
            } 
            // --- CASE 2: FREE REGISTRATION ---
            else {
                $sql = "INSERT INTO users (name, shop_name, email, phone, user_type, password, status) VALUES ('$name', '$shop_name', '$email', '$phone', '$user_type', '$password', 'Active')";
                if ($conn->query($sql)) {
                    $swal_script = "Swal.fire('Success', 'Account created successfully!', 'success').then(() => { window.location.href = 'login.php'; });";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $display_name; ?> | Register</title>

    <link rel="apple-touch-icon" sizes="180x180" href="../../../assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../../../assets/img/favicons/favicon-32x32.png">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700%7cPoppins:300,400,500,600,700,800,900&amp;display=swap" rel="stylesheet">
    <link href="../../../assets/css/theme.css" rel="stylesheet" id="style-default">
    <link href="../../../assets/css/user.css" rel="stylesheet" id="user-style-default">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        .custom-swal-popup { border-radius: 16px !important; overflow: hidden !important; background: transparent !important; padding: 0 !important; width: 340px !important; }
        .swal2-html-container { margin: 0 !important; padding: 0 !important; overflow: hidden !important; }
        .custom-swal-close { color: #4b4b4b !important; background: rgba(255,255,255,0.8) !important; border-radius: 50% !important; top: 8px !important; right: 8px !important; width: 30px !important; height: 30px !important; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .upi-container { background-color: #aeb4bc; border-radius: 16px; font-family: 'Poppins', sans-serif; overflow: hidden; }
        .upi-header { background: linear-gradient(to bottom, #f4f5f7, #e8eaed); color: #570fa3; font-weight: 800; font-size: 19px; padding: 16px 10px; text-align: center; }
        .upi-merchant-bar { background: #570fa3; color: #fff; padding: 8px 10px; text-align: center; font-weight: bold; font-size: 15px; }
        .upi-body { padding: 20px 25px; text-align: center; }
        .upi-qr-box { background: #fff; padding: 10px; border-radius: 12px; border: 2px dashed #570fa3; display: inline-block; margin-bottom: 15px; }
        .upi-qr-box img { width: 180px; height: 180px; display: block; }
        .upi-amount-box { background: #fff; border-radius: 10px; padding: 12px; margin-bottom: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .amount-text { color: #570fa3; font-weight: 900; font-size: 22px; }
        .timer-text { color: #d32f2f; font-weight: bold; font-size: 14px; margin-top: 4px; }
        .upi-secure-badge { background: #e8f5e9; color: #2e7d32; border-radius: 6px; padding: 8px; font-size: 12px; font-weight: bold; margin-bottom: 15px; border: 1px solid #c8e6c9; }
    </style>
  </head>

  <body>
    <main class="main" id="top">
      <div class="container-fluid">
        <div class="row min-vh-100 flex-center g-0">
          <div class="col-lg-8 col-xxl-6 py-3 position-relative">
            <img class="bg-auth-circle-shape" src="../../../assets/img/icons/spot-illustrations/bg-shape.png" alt="" width="250">
            <img class="bg-auth-circle-shape-2" src="../../../assets/img/icons/spot-illustrations/shape-1.png" alt="" width="150">
            
            <div class="card overflow-hidden z-1">
              <div class="card-body p-0">
                <div class="row g-0 h-100">
                  <div class="col-md-5 text-center bg-card-gradient">
                    <div class="position-relative p-4 pt-md-5 pb-md-7" data-bs-theme="light">
                      <div class="bg-holder bg-auth-card-shape" style="background-image:url(../../../assets/img/icons/spot-illustrations/half-circle.png);"></div>
                      <div class="z-1 position-relative">
                        <a class="link-light mb-4 font-sans-serif fs-5 d-inline-block fw-bolder" href="#"><?php echo strtolower($display_name); ?></a>
                        <p class="opacity-75 text-white">With the power of <?php echo $display_name; ?>, manage your business efficiently.</p>
                      </div>
                    </div>
                    <div class="mt-3 mb-4 mt-md-4 mb-md-5" data-bs-theme="light">
                      <p class="pt-3 text-white">Already have an account?<br><a class="btn btn-outline-light mt-2 px-4" href="login.php">Log In</a></p>
                    </div>
                  </div>

                  <div class="col-md-7 d-flex flex-center">
                    <div class="p-4 p-md-5 flex-grow-1">
                      <h3 class="mb-3">Register Account</h3>
                      <form method="POST" action="">
                        <div class="row gx-2">
                            <div class="mb-3 col-sm-6">
                              <label class="form-label">Full Name</label>
                              <input class="form-control" type="text" name="name" placeholder="Titan Boss" required />
                            </div>
                            <div class="mb-3 col-sm-6">
                              <label class="form-label">Shop Name</label>
                              <input class="form-control" type="text" name="shop_name" placeholder="Shop Name" required />
                            </div>
                        </div>

                        <div class="row gx-2">
                            <div class="mb-3 col-sm-6">
                              <label class="form-label">Email ID</label>
                              <input class="form-control" type="email" name="email" placeholder="example@mail.com" required />
                            </div>
                            <div class="mb-3 col-sm-6">
                              <label class="form-label">Phone Number</label>
                              <input class="form-control" type="tel" name="phone" placeholder="10 Digit Mobile" maxlength="10" required />
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">User Type</label>
                            <select class="form-select" name="user_type" required>
                                <option value="" selected disabled>Select Role Type</option>
                                <option value="Retailer">Retailer (₹<?php echo $site['retailer_price']; ?>)</option>
                                <option value="Distributor">Distributor (₹<?php echo $site['distributor_price']; ?>)</option>
                                <option value="Head Branch">Head Branch (₹<?php echo $site['super_price']; ?>)</option>
                            </select>
                        </div>

                        <div class="row gx-2">
                          <div class="mb-3 col-sm-6">
                            <label class="form-label">Password</label>
                            <input class="form-control" type="password" name="password" required />
                          </div>
                          <div class="mb-3 col-sm-6">
                            <label class="form-label">Confirm Password</label>
                            <input class="form-control" type="password" name="confirm_password" required />
                          </div>
                        </div>

                        <div class="form-check mb-3">
                          <input class="form-check-input" type="checkbox" id="terms" required />
                          <label class="form-label" for="terms">I accept the <a href="#!">terms & conditions</a></label>
                        </div>
                        <button class="btn btn-primary d-block w-100 mt-3" type="submit" name="register_btn">Register & Activate</button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <script src="../../../vendors/bootstrap/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script><?php echo $swal_script; ?></script>
  </body>
</html>