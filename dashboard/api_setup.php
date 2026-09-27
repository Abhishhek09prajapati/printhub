<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Force UTF-8 Headers
header('Content-Type: text/html; charset=utf-8');

// 2. Database Connection
require_once('../titancore/titanconfig.php');

// 3. ADMIN ONLY AUTHENTICATION (Fixed Security)
// Hum check kar rahe hain 'user_type' ya 'utype' jo bhi aapke system me set ho
$admin_check = $_SESSION['user_type'] ?? $_SESSION['utype'] ?? 'Guest';

if ($admin_check !== 'TitanAdmin') {
    // Agar admin nahi hai toh login page par bhejo
    header("Location: login.php"); 
    exit("Unauthorized Access!");
}

$swal_script = "";

// ==========================================
// HANDLE API UPDATE (POST REQUEST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_api'])) {
    
    $gateway_token = mysqli_real_escape_string($conn, trim($_POST['gateway_token']));
    $titan_api_key = mysqli_real_escape_string($conn, trim($_POST['titan_api_key']));
    $whatsapp_key  = mysqli_real_escape_string($conn, trim($_POST['whatsapp_api_key']));
    $whatsapp_send = mysqli_real_escape_string($conn, trim($_POST['whatsapp_sender']));

    // Update TitanPayment Table
    $update_sql = "UPDATE TitanPayment SET 
        gateway_token = '$gateway_token',
        titan_api_key = '$titan_api_key',
        WhatsappApiKey = '$whatsapp_key',
        WhatsappSender = '$whatsapp_send'
        WHERE id = 1";

    if ($conn->query($update_sql)) {
        $swal_script = "
            Swal.fire({
                icon: 'success',
                title: 'All Settings Saved!',
                text: 'API and WhatsApp configurations updated.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'api_setup.php';
            });";
    } else {
        $swal_script = "Swal.fire('Update Failed', 'Error: " . $conn->error . "', 'error');";
    }
}

// Fetch Current Settings
$api_res = $conn->query("SELECT * FROM TitanPayment WHERE id = 1");
$api = $api_res->fetch_assoc();

require_once('../titancore/titanheader.php');
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-primary-subtle shadow-none border border-primary-subtle text-center">
            <div class="p-3">
                <h4 class="text-primary fw-bold mb-0"><i class="fas fa-tools me-2"></i>API & Gateway Configuration</h4>
                <p class="text-700 fs-11 mb-0">Logged in as: <span class="badge bg-primary"><?php echo $admin_check; ?></span></p>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="apiForm" autocomplete="off">
    <input type="hidden" name="update_api" value="1">
    
    <div class="row g-3">
        
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-0 border-top border-4 border-primary">
                <div class="card-header bg-light py-3">
                    <h6 class="mb-0 text-900 fw-bold"><i class="fas fa-wallet me-2 text-primary"></i>Wallet Gateway</h6>
                </div>
                <div class="card-body p-4 bg-body-tertiary">
                    <label class="form-label fs-10 text-800 fw-bold">GATEWAY TOKEN</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-key text-primary"></i></span>
                        <input class="form-control" name="gateway_token" type="password" id="gt_key" value="<?php echo htmlspecialchars($api['gateway_token'] ?? ''); ?>" placeholder="Enter Token">
                        <button class="btn btn-link text-primary border border-start-0" type="button" onclick="togglePass('gt_key')"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-0 border-top border-4 border-info">
                <div class="card-header bg-light py-3">
                    <h6 class="mb-0 text-900 fw-bold"><i class="fas fa-server me-2 text-info"></i>Titan Server API</h6>
                </div>
                <div class="card-body p-4 bg-body-tertiary">
                    <label class="form-label fs-10 text-800 fw-bold">TITAN API KEY</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-code text-info"></i></span>
                        <input class="form-control" name="titan_api_key" type="password" id="titan_key" value="<?php echo htmlspecialchars($api['titan_api_key'] ?? ''); ?>" placeholder="Enter API Key">
                        <button class="btn btn-link text-info border border-start-0" type="button" onclick="togglePass('titan_key')"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-0 border-top border-4 border-success">
                <div class="card-header bg-light py-3">
                    <h6 class="mb-0 text-900 fw-bold"><i class="fab fa-whatsapp me-2 text-success"></i>WhatsApp API Key</h6>
                </div>
                <div class="card-body p-4 bg-body-tertiary">
                    <label class="form-label fs-10 text-800 fw-bold">WHATSAPP API KEY</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-fingerprint text-success"></i></span>
                        <input class="form-control" name="whatsapp_api_key" type="password" id="wa_key" value="<?php echo htmlspecialchars($api['WhatsappApiKey'] ?? ''); ?>" placeholder="Enter WA Key">
                        <button class="btn btn-link text-success border border-start-0" type="button" onclick="togglePass('wa_key')"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-0 border-top border-4 border-success">
                <div class="card-header bg-light py-3">
                    <h6 class="mb-0 text-900 fw-bold"><i class="fas fa-phone-volume me-2 text-success"></i>WhatsApp Sender</h6>
                </div>
                <div class="card-body p-4 bg-body-tertiary">
                    <label class="form-label fs-10 text-800 fw-bold">SENDER NUMBER (91...)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-headset text-success"></i></span>
                        <input class="form-control" name="whatsapp_sender" type="text" value="<?php echo htmlspecialchars($api['WhatsappSender'] ?? ''); ?>" placeholder="e.g. 918757963416">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 mt-4">
            <button type="submit" class="btn btn-primary btn-lg w-100 shadow fw-bold p-3">
                <i class="fas fa-save me-2"></i>UPDATE INTEGRATION SETTINGS
            </button>
        </div>

    </div>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    <?php echo $swal_script; ?>

    function togglePass(id) {
        var x = document.getElementById(id);
        if (x.type === "password") {
            x.type = "text";
        } else {
            x.type = "password";
        }
    }
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>