<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once('../titancore/titanconfig.php');

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

if (isset($_GET['action']) && $_GET['action'] == 'check_status' && isset($_GET['order_id'])) {
    header('Content-Type: application/json');
    
    $check_token = $gateway_api_token; 
    $check_url = $gateway_base_url . "/api/check-order-status";
    $check_order_id = $conn->real_escape_string(trim($_GET['order_id']));
    
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
    $gateway_raw_msg = "No response from gateway.";
    
    if ($data) {
        $gateway_raw_msg = isset($data['message']) ? $data['message'] : (isset($data['result']['message']) ? $data['result']['message'] : 'PENDING');

        if (isset($data['status'])) {
            if ($data['status'] === true) $api_status = 'SUCCESS';
            else $api_status = strtoupper((string)$data['status']);
        }
        if (isset($data['result']['status'])) $api_status = strtoupper((string)$data['result']['status']);
        if (isset($data['result']['txnStatus'])) $api_status = strtoupper((string)$data['result']['txnStatus']);
        
        if ($api_status === 'COMPLETED' || $api_status === 'SUCCESS' || $api_status === 'TRUE') {
            
            $chk_query = $conn->query("SELECT * FROM wallet_requests WHERE utr_number='$check_order_id' LIMIT 1");
            
            if ($chk_query && $chk_query->num_rows > 0) {
                $row = $chk_query->fetch_assoc();
                
                if (strtoupper($row['status']) !== 'SUCCESS' && strtoupper($row['status']) !== 'COMPLETED') {
                    $amount = floatval($row['amount']);
                    $userid = $conn->real_escape_string($row['userid']); 
                    
                    $upd1 = $conn->query("UPDATE wallet_requests SET status='SUCCESS' WHERE utr_number='$check_order_id'");
                    $upd2 = $conn->query("UPDATE users SET wallet = wallet + $amount WHERE phone='$userid'");
                    
                    if ($upd1 && $upd2) {
                        $local_updated = true;
                    }
                } else {
                    $local_updated = true; 
                }
            }
        }
    }
    
    echo json_encode([
        "local_success" => $local_updated,
        "gateway_msg" => $api_status,
        "raw_response" => $gateway_raw_msg
    ]);
    exit;
}

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once('../titancore/titanheader.php');
mysqli_set_charset($conn, "utf8mb4");

$uid_fetch = $_SESSION['user_id'];
$user_query = $conn->query("SELECT * FROM users WHERE id='$uid_fetch'");
$u_row = $user_query->fetch_assoc() ?? [];
$u_name = $u_row['user_name'] ?? $_SESSION['user_name'] ?? 'User';
$u_mobile = $u_row['phone'] ?? '0000000000';
$wallet_bal = $u_row['wallet'] ?? 0.00;

$site_name = "TitanApi"; 
$settings_query = $conn->query("SELECT site_name FROM settings LIMIT 1");
if ($settings_query && $settings_query->num_rows > 0) {
    $set_row = $settings_query->fetch_assoc();
    if (!empty($set_row['site_name'])) {
        $site_name = htmlspecialchars($set_row['site_name'], ENT_QUOTES, 'UTF-8');
    }
}

$swal_script = "";

if (isset($_POST['smartupi']) && !empty($_POST['amount'])) {
    
    $amount = floatval(trim($_POST['amount'])); 
    
    if ($amount >= 100 && $amount <= 10000) {
        
        $order_id = "TXN" . time() . rand(100, 999); 
        
        $insert_query = "INSERT INTO wallet_requests (userid, amount, utr_number, status) 
                         VALUES ('$u_mobile', '$amount', '$order_id', 'Process')";
        
        if (mysqli_query($conn, $insert_query)) { 
            
            $api_url = $gateway_base_url . "/api/create-order";
            
            $post_data = array(
                'customer_mobile' => $u_mobile,
                'user_token'      => $gateway_api_token, 
                'amount'          => $amount,
                'order_id'        => $order_id,
                'redirect_url'    => "https://" . $_SERVER['SERVER_NAME'] . "/dashboard/addwallet.php",
                'remark1'         => $u_name,
                'remark2'         => 'WalletTopup'
            );
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $curl_err = curl_error($ch);
            curl_close($ch);
            
            if ($response) {
                $resArray = json_decode($response, true);
                
                if (isset($resArray['status']) && $resArray['status'] === true && !empty($resArray['result']['payment_url'])) {
                    
                    $payment_url = $resArray['result']['payment_url'];
                    
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
                    
                    $swal_script = "
                        const orderId = '{$order_id}';
                        const amount = '{$amount}';
                        const dynamicSiteName = '{$site_name}'; 
                        
                        let actionButton = '';
                        if ('{$is_direct_upi}' === '1') {
                            actionButton = `<a href='{$final_qr_data}' class='btn w-100 fw-bold text-white mb-2 shadow-sm' style='background: #570fa3; border-radius: 8px;'><i class='fas fa-mobile-alt me-2'></i> Pay via UPI App</a>`;
                        }
                        
                        let sweetAlertHtml = `
                            <div class='upi-container'>
                                <iframe src='{$payment_url}' sandbox='allow-scripts allow-same-origin' style='width:0;height:0;border:0;display:none;position:absolute;'></iframe>

                                <div class='upi-header'>Scan or Click to Pay</div>
                                <div class='upi-merchant-bar'>Merchant: \${dynamicSiteName}</div>
                                <div class='upi-body'>
                                    <div id='mainPaymentView'>
                                        
                                        <div class='upi-qr-box'>
                                            <img src='{$qrImg}' alt='QR Code'>
                                        </div>
                                        
                                        <div class='upi-amount-box'>
                                            <div class='amount-text'>Amount: ₹\${amount}</div>
                                            <div class='timer-text'>
                                                <i class='fas fa-clock'></i> Expires in: <span id='qrTimer'>30:00</span>
                                            </div>
                                        </div>

                                        \${actionButton}
                                        
                                        <button id='manualVerifyBtn' class='btn w-100 fw-bold text-white mb-3 shadow-sm' style='background: #28a745; border-radius: 8px;'>
                                            <i class='fas fa-sync-alt me-1'></i> I Have Paid (Verify)
                                        </button>

                                        <div class='upi-secure-badge'>
                                            <i class='fas fa-shield-alt'></i> Secured by \${dynamicSiteName}
                                        </div>
                                        <div class='upi-footer-text'>Powered by UPI & \${dynamicSiteName}</div>
                                        <div id='payStatus' class='fw-bold mt-2' style='color: #4b4b4b; font-size: 13px;'></div>
                                    </div>

                                    <div id='successContainer' style='display:none; padding: 40px 10px;'>
                                        <div class='icon-item bg-success-subtle shadow-none mx-auto mb-3' style='width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center;'>
                                            <span class='fas fa-check text-success' style='font-size: 40px;'></span>
                                        </div>
                                        <h3 class='text-success fw-bold'>Payment Successful!</h3>
                                        <p class='text-dark fw-semi-bold'>Your wallet has been credited.</p>
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
                                const successContainer = document.getElementById('successContainer');
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

                                function fireConfetti() {
                                    var duration = 4 * 1000;
                                    var animationEnd = Date.now() + duration;
                                    var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 999999 };
                                    function randomInRange(min, max) { return Math.random() * (max - min) + min; }
                                    var interval = setInterval(function() {
                                        var timeLeft = animationEnd - Date.now();
                                        if (timeLeft <= 0) { return clearInterval(interval); }
                                        var particleCount = 50 * (timeLeft / duration);
                                        confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
                                        confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
                                    }, 250);
                                }
                                
                                const verifyPaymentStatus = async (isManualClick = false) => {
                                    if(isProcessing || timerDuration < 0) return;
                                    isProcessing = true;
                                    
                                    if(isManualClick) {
                                        payStatus.innerHTML = '<span class=\"spinner-border spinner-border-sm text-success\"></span> Verifying Transaction...';
                                        manualVerifyBtn.disabled = true;
                                    }

                                    try {
                                        const fetchUrl = '?action=check_status&order_id=' + orderId + '&t=' + new Date().getTime();
                                        const response = await fetch(fetchUrl);
                                        const data = await response.json();
                                        
                                        if (data.local_success === true) {
                                            clearInterval(statusCheckInterval);
                                            clearInterval(countdown);
                                            
                                            mainPaymentView.style.display = 'none';
                                            successContainer.style.display = 'block';
                                            fireConfetti();
                                            setTimeout(() => window.location.href = 'addwallet.php', 3500);
                                        } else if (isManualClick) {
                                            let errorStatus = data.gateway_msg || 'PENDING';
                                            let rawMsg = data.raw_response ? data.raw_response : '';
                                            payStatus.innerHTML = `<span class=\"text-danger\">Status: \${errorStatus}. \${rawMsg} Wait 5 sec & try again.</span>`;
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
                    $gate_msg = isset($resArray['message']) ? $resArray['message'] : (isset($resArray['result']['message']) ? $resArray['result']['message'] : 'Gateway structure invalid.');
                    $swal_script = "Swal.fire('Gateway Error', '" . addslashes($gate_msg) . "', 'error');";
                }
            } else {
                $swal_script = "Swal.fire('Error', 'Gateway timeout or connection failed. CURL: " . addslashes($curl_err) . "', 'error');";
            }
        } else {
            $swal_script = "Swal.fire('Error', 'Database Save error.', 'error');";
        }
    } else {
        $swal_script = "Swal.fire('Warning', 'Amount range: 100 to 10000', 'warning');";
    }
}
?>

<style>
.custom-swal-popup { border-radius: 16px !important; overflow: hidden !important; background: transparent !important; padding: 0 !important; width: 340px !important; }
.swal2-html-container { margin: 0 !important; padding: 0 !important; overflow: hidden !important; }
.custom-swal-close { color: #4b4b4b !important; background: rgba(255,255,255,0.8) !important; border-radius: 50% !important; top: 8px !important; right: 8px !important; width: 30px !important; height: 30px !important; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
.upi-container { background-color: #aeb4bc; border-radius: 16px; font-family: 'Arial', sans-serif; overflow: hidden; }
.upi-header { background: linear-gradient(to bottom, #f4f5f7, #e8eaed); color: #570fa3; font-weight: 800; font-size: 20px; padding: 16px 10px; text-align: center; }
.upi-merchant-bar { background: #570fa3; color: #ffffff; font-weight: bold; font-size: 15px; padding: 8px 10px; text-align: center; }
.upi-body { padding: 20px 25px; text-align: center; }
.upi-qr-box { background: #ffffff; padding: 10px; border-radius: 12px; border: 2px dashed #570fa3; display: inline-block; margin-bottom: 15px; }
.upi-qr-box img { width: 180px; height: 180px; display: block; }
.upi-amount-box { background: #ffffff; border-radius: 10px; padding: 12px; margin-bottom: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.amount-text { color: #570fa3; font-weight: 900; font-size: 20px; letter-spacing: 0.5px; }
.timer-text { color: #d32f2f; font-weight: bold; font-size: 14px; margin-top: 4px; }
.upi-secure-badge { background: #e8f5e9; color: #2e7d32; border-radius: 6px; padding: 8px; font-size: 13px; font-weight: bold; margin-bottom: 15px; border: 1px solid #c8e6c9; }
.upi-footer-text { font-size: 11px; color: #5c636a; font-weight: 600; }
</style>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-success-subtle shadow-none me-3">
                        <span class="fas fa-wallet text-success fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Current Balance</h6>
                        <h4 class="text-success fw-bold mb-0">₹<?php echo number_format($wallet_bal, 2); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100 border-top border-4 border-primary">
            <div class="card-header bg-light border-bottom text-center">
                <h5 class="mb-0 fw-bold"><i class="fas fa-bolt me-2 text-warning"></i>Instant Wallet TopUp</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                <form method="POST" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label fs-10 text-900 fw-bold">Enter Amount (₹)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white text-900 fw-bold">₹</span>
                            <input type="number" class="form-control shadow-sm fw-bold text-primary" placeholder="e.g. 50" name="amount" min="100" max="10000" required>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg fw-semi-bold shadow-sm" name="smartupi">
                            <i class="fas fa-qrcode me-2"></i>Generate QR & Pay
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100 border-top border-4 border-info">
            <div class="card-header bg-light border-bottom">
                <h6 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-info"></i>Recent Transactions</h6>
            </div>
            <div class="card-body p-0 bg-white">
                <div class="table-responsive scrollbar">
                    <table class="table table-sm table-striped table-hover fs-10 mb-0">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>Amount</th>
                                <th>Txn ID</th>
                                <th class="text-end pe-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $req_hist = $conn->query("SELECT * FROM wallet_requests WHERE userid='$u_mobile' ORDER BY id DESC LIMIT 6");
                            if($req_hist && $req_hist->num_rows > 0) {
                                while($row = $req_hist->fetch_assoc()) {
                                    $status = $row['status'] ?? 'Pending';
                                    $badge = (strtoupper($status) == 'SUCCESS' || strtoupper($status) == 'COMPLETED') ? 'success' : ((strtoupper($status) == 'FAILED') ? 'danger' : 'warning');
                            ?>
                            <tr>
                                <td class="ps-3 py-2 text-600"><?php echo date('d M, Y', strtotime($row['request_date'] ?? date('Y-m-d H:i:s'))); ?></td>
                                <td class="fw-bold text-success">₹<?php echo number_format($row['amount'], 2); ?></td>
                                <td class="text-500 font-monospace"><?php echo htmlspecialchars($row['utr_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-end pe-3">
                                    <span class="badge badge-subtle-<?php echo $badge; ?>"><?php echo strtoupper($status); ?></span>
                                </td>
                            </tr>
                            <?php } } else { ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-500 fst-italic">No recent top-ups found.</td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    <?php echo $swal_script; ?>
});
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush(); 
?>