<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Auth & Configuration
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once('../titancore/titanconfig.php');

$uid = $_SESSION['user_id'];
$u_phone = $_SESSION['phone'] ?? $_SESSION['user_id'];
$u_name = $_SESSION['user_name'] ?? 'User';
$swal_msg = "";

// Fetch Site Settings
$settings_res = $conn->query("SELECT site_name FROM settings LIMIT 1");
$web = $settings_res->fetch_assoc();
$display_name = $web['site_name'] ?? 'ApiNexus';

// Fetch User Wallet
$user_res = $conn->query("SELECT * FROM users WHERE id = '$uid'");
$udata = $user_res->fetch_assoc();
$current_wallet = $udata['wallet'] ?? 0;

// Fetch Pricing
$price_res = $conn->query("SELECT price FROM pricing WHERE service_name='aadhar_advance_biometric_fee'");
$fee = ($price_res && $price_res->num_rows > 0) ? floatval($price_res->fetch_assoc()['price']) : 10.00; 

// ========== API Config Fetch from TitanPayment (Database se) ==========
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = rtrim($api_settings['titanurl'] ?? 'https://titanapi.in', '/');
$api_key = $api_settings['titan_api_key'] ?? 'TitanApi_7D4F66F3AC579EDD50A19257C3880C59';

require_once('../titancore/titanheader.php');
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-fingerprint text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Welcome back, <?php echo htmlspecialchars($u_name); ?></h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar <span class="text-info fw-medium">Advance Biometric</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="form-control form-control-sm d-flex align-items-center bg-white border-200 shadow-sm" style="min-width: 140px;">
                        <span class="fas fa-wallet text-success me-2"></span>
                        <span class="fw-bold text-success">Wallet: ₹<?php echo number_format($current_wallet, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 border-top border-4 border-primary h-100">
            <div class="card-header bg-light d-flex justify-content-between align-items-center border-bottom">
                <h5 class="mb-0 text-1000 fw-bold"><i class="bx bxs-fingerprint me-2 text-primary"></i>Biometric eKYC</h5>
                <a href="aadhar_advance_list.php" class="btn btn-outline-primary btn-sm fw-bold shadow-none">
                    <i class="fas fa-list me-1"></i> View History
                </a>
            </div>
            <div class="card-body p-4 bg-body-tertiary">
                
                <div class="alert alert-warning border-0 d-flex align-items-center mb-4 shadow-sm p-3">
                    <i class="fas fa-rupee-sign fs-4 text-warning me-3"></i>
                    <p class="mb-0 fs-10 text-900">Service charge: <strong class="text-danger">₹<?php echo number_format($fee, 2); ?></strong> will be deducted upon successful verification.</p>
                </div>
                
                <div class="alert alert-info border-0 d-flex align-items-center mb-4 shadow-sm p-3">
                    <i class="fas fa-info-circle fs-4 text-info me-3"></i>
                    <p class="mb-0 fs-10 text-900">Requirements: RD Service running on <strong class="text-primary">Port 11100</strong> + L1 Fingerprint Scanner connected.</p>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <label class="form-label fs-10 fw-bold text-900">Aadhaar Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-id-card text-500"></i></span>
                            <input class="form-control shadow-none bg-white fs-10 tracking-wide" id="txtUID" maxlength="12" pattern="\d{12}" type="text" placeholder="Enter 12-digit Aadhaar Number" required>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <button type="button" class="btn btn-primary btn-lg w-100 fw-bold shadow-none py-3" name="capture" id="capture">
                        <i class="fas fa-fingerprint me-2 fs-5 align-middle"></i> Capture Fingerprint & Verify
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<form action="aadhar_advance_biometric_save.php" method="post" name="f1" id="hiddenForm" style="display:none;">
    <input type="hidden" name="aadhar" id="aadhar"/>
    <input type="hidden" name="pid_data" id="pid_data"/>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function()
{
    $("#capture").on('click',function()
    {
        var aadhar = $("#txtUID").val().trim();
        if(aadhar == '')
        {
            Swal.fire('Warning', 'Please enter Aadhaar number.', 'warning');
            return false;
        }
        else if(aadhar.length != 12 || isNaN(aadhar))
        { 
            Swal.fire('Warning', 'Please enter a valid 12-digit Aadhaar number.', 'warning');
            return false;
        }
        else 
        {
            $("#capture").prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2 fs-5 align-middle"></i> Processing...');

            var configUrl = "<?php echo $TitanApi_Url; ?>/api/v1/Aadhar_Advance.php?api_key=<?php echo $api_key; ?>&action=get_device_key";
            
            fetch(configUrl)
            .then(response => response.json())
            .then(configData => {
                console.log("API Response:", configData);
                
                if(configData.success === true && configData.device_key) {
                    var deviceKey = configData.device_key;
                    console.log("Device Key received:", deviceKey);
                    
                    var rdPort = "11100"; 
                    var rdUrl = "http://127.0.0.1:" + rdPort + "/rd/capture";
                    
                    var pidOptions = '<' + '?xml version="1.0"?>\n' +
                        '<PidOptions ver="1.0">\n' +
                        '  <Opts fCount="1" fType="2" iCount="0" pgCount="0" format="0" pidVer="2.0" timeout="15000" posh="UNKNOWN" env="P" wadh="' + deviceKey + '" />\n' +
                        '</PidOptions>';
                    
                    return fetch(rdUrl, {
                        method: 'CAPTURE',
                        headers: { 'Content-Type': 'text/xml; charset=utf-8' },
                        body: pidOptions
                    });
                } else {
                    throw new Error(configData.error || "Failed to get device key");
                }
            })
            .then(response => {
                if(!response.ok) {
                    throw new Error("RD Service Error: " + response.status + ". Make sure RD service is running on port 11100.");
                }
                return response.text();
            })
            .then(pidXml => {
                console.log("PID Data Captured Successfully");
                $("#aadhar").val(aadhar);
                $("#pid_data").val(pidXml);
                
                Swal.fire({
                    title: 'Verifying...',
                    html: 'Please wait while we verify your biometric data.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                
                document.getElementById("hiddenForm").submit();
            })
            .catch(error => {
                console.error("Error:", error);
                Swal.fire('Capture Failed!', error.message, 'error');
                $("#capture").prop('disabled', false).html('<i class="fas fa-fingerprint me-2 fs-5 align-middle"></i> Capture Fingerprint & Verify');
            });
        } 
    });
});
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>