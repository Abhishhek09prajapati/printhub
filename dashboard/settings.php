<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Force UTF-8 Headers
header('Content-Type: text/html; charset=utf-8');

/** 
 * ★ STRICT ADMIN AUTHENTICATION ★ 
 * Sirf 'TitanAdmin' hi is page ko access kar sakta hai.
 */
$user_role = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Guest';

if ($user_role !== 'TitanAdmin') {
    // Agar user Admin nahi hai, toh use Dashboard par bhej do
    header("Location: titanhome.php");
    exit("Access Denied: You do not have permission to access this page.");
}

require_once('../titancore/titanconfig.php');
$swal_script = "";

// ==========================================
// HANDLE SETTINGS UPDATE (POST REQUEST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    
    // Text Inputs (Sanitized for safety)
    $site_name = mysqli_real_escape_string($conn, trim($_POST['site_name']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    
    // Prices
    $r_price = floatval($_POST['retailer_price']);
    $d_price = floatval($_POST['distributor_price']);
    $s_price = floatval($_POST['super_price']);
    
    // Toggles
    $self_register = isset($_POST['self_register']) ? 1 : 0;
    $gateway_status = isset($_POST['gateway_status']) ? 1 : 0;
    $whatsapp_status = isset($_POST['whatsapp_status']) ? 1 : 0;
    $email_status = isset($_POST['email_status']) ? 1 : 0;

    // ★ LOGO TO BASE64 LOGIC ★
    $logo_query_part = "";
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['logo']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
        
        if (in_array($file_ext, $allowed_exts)) {
            $image_data = file_get_contents($tmp_name);
            $base64_string = base64_encode($image_data);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $tmp_name);
            finfo_close($finfo);
            $base64_logo_url = "data:" . $mime_type . ";base64," . $base64_string;
            $safe_base64_logo = mysqli_real_escape_string($conn, $base64_logo_url);
            $logo_query_part = ", logo = '$safe_base64_logo'";
        } else {
            $swal_script = "Swal.fire('Warning', 'Only JPG, PNG, SVG allowed.', 'warning');";
        }
    }

    // Update Database Table (`settings`)
    if(empty($swal_script)){
        $update_sql = "UPDATE settings SET 
            site_name = '$site_name',
            phone = '$phone',
            email = '$email',
            address = '$address',
            retailer_price = '$r_price',
            distributor_price = '$d_price',
            super_price = '$s_price',
            self_register = '$self_register',
            gateway_status = '$gateway_status',
            whatsapp_status = '$whatsapp_status',
            email_status = '$email_status'
            $logo_query_part
            WHERE id = 1";

        if ($conn->query($update_sql)) {
            $swal_script = "
                Swal.fire({
                    icon: 'success',
                    title: 'Settings Updated!',
                    text: 'Your website settings have been saved.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'settings.php';
                });";
        } else {
            $swal_script = "Swal.fire('Error', 'Database error. Ensure your logo column is LONGTEXT.', 'error');";
        }
    }
}

// Fetch Current Settings
$settings_res = $conn->query("SELECT * FROM settings WHERE id = 1");
$site = $settings_res->fetch_assoc();

if(!$site) {
    $site = ['site_name'=>'', 'phone'=>'', 'email'=>'', 'address'=>'', 'retailer_price'=>0, 'distributor_price'=>0, 'super_price'=>0, 'self_register'=>1, 'gateway_status'=>1, 'whatsapp_status'=>1, 'email_status'=>1, 'logo'=>''];
}

require_once('../titancore/titanheader.php');
?>

<!-- ================================================================================= -->
<!-- ★ FALCON UI GLOBAL SETTINGS ★ -->
<!-- ================================================================================= -->

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-user-shield text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Admin Official Panel</h6>
                        <h4 class="text-primary fw-bold mb-0">Global <span class="text-info fw-medium">Settings</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-2">
                    <span class="badge badge-subtle-danger rounded-pill"><i class="fas fa-lock me-1"></i> Admin Secured Area</span>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="settingsForm" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="update_settings" value="1">
    
    <div class="row g-3">
        
        <!-- LEFT COLUMN -->
        <div class="col-lg-8">
            
            <!-- SECTION 1: GENERAL INFO -->
            <div class="card shadow-sm border-0 border-top border-4 border-primary mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-globe me-2 text-primary"></i>Website Information</h5>
                </div>
                <div class="card-body p-4 bg-body-tertiary">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fs-10 text-900 fw-bold">Website Name</label>
                            <input class="form-control shadow-sm" name="site_name" type="text" value="<?php echo htmlspecialchars($site['site_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-10 text-900 fw-bold">Support Email ID</label>
                            <input class="form-control shadow-sm" name="email" type="email" value="<?php echo htmlspecialchars($site['email']); ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fs-10 text-900 fw-bold">Support Mobile Number</label>
                            <input class="form-control shadow-sm" name="phone" type="text" value="<?php echo htmlspecialchars($site['phone']); ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fs-10 text-900 fw-bold">Office Address</label>
                            <textarea class="form-control shadow-sm" name="address" rows="2"><?php echo htmlspecialchars($site['address']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: PRICING -->
            <div class="card shadow-sm border-0 border-top border-4 border-success">
                <div class="card-header bg-light">
                    <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-tags me-2 text-success"></i>Registration Fees Setup</h5>
                </div>
                <div class="card-body p-4 bg-body-tertiary">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900 fw-bold">Retailer Price (₹)</label>
                            <input class="form-control shadow-sm text-success fw-bold" name="retailer_price" type="number" step="0.01" value="<?php echo $site['retailer_price']; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900 fw-bold">Distributor Price (₹)</label>
                            <input class="form-control shadow-sm text-success fw-bold" name="distributor_price" type="number" step="0.01" value="<?php echo $site['distributor_price']; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900 fw-bold">Head/Super Price (₹)</label>
                            <input class="form-control shadow-sm text-success fw-bold" name="super_price" type="number" step="0.01" value="<?php echo $site['super_price']; ?>" required>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- RIGHT COLUMN -->
        <div class="col-lg-4">
            
            <!-- SECTION 3: TOGGLES -->
            <div class="card shadow-sm border-0 border-top border-4 border-warning mb-3">
                <div class="card-header bg-light text-center">
                    <h6 class="mb-0 fw-bold fs-10"><i class="fas fa-toggle-on me-2 text-warning"></i>Service Controls</h6>
                </div>
                <div class="card-body p-4 bg-body-tertiary">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-dashed border-300">
                        <div>
                            <h6 class="mb-1 fs-10 fw-semi-bold">Self Registration</h6>
                            <p class="fs-11 text-500 mb-0">Allow users to sign up.</p>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="self_register" value="1" <?php echo ($site['self_register'] == 1) ? 'checked' : ''; ?>>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-dashed border-300">
                        <div>
                            <h6 class="mb-1 fs-10 fw-semi-bold">Payment Gateway</h6>
                            <p class="fs-11 text-500 mb-0">Allow wallet add money.</p>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="gateway_status" value="1" <?php echo ($site['gateway_status'] == 1) ? 'checked' : ''; ?>>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-dashed border-300">
                        <div>
                            <h6 class="mb-1 fs-10 fw-semi-bold">WhatsApp Alerts</h6>
                            <p class="fs-11 text-500 mb-0">Send msgs on WhatsApp.</p>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="whatsapp_status" value="1" <?php echo ($site['whatsapp_status'] == 1) ? 'checked' : ''; ?>>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 fs-10 fw-semi-bold">Email Alerts</h6>
                            <p class="fs-11 text-500 mb-0">Send msgs on Email.</p>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" name="email_status" value="1" <?php echo ($site['email_status'] == 1) ? 'checked' : ''; ?>>
                        </div>
                    </div>

                </div>
            </div>

            <!-- SECTION 4: LOGO -->
            <div class="card shadow-sm border-0 border-top border-4 border-info mb-3">
                <div class="card-header bg-light text-center">
                    <h6 class="mb-0 fw-bold fs-10"><i class="fas fa-image me-2 text-info"></i>Website Logo</h6>
                </div>
                <div class="card-body text-center bg-body-tertiary">
                    <div class="border rounded bg-white d-flex align-items-center justify-content-center mx-auto mb-3 p-2 shadow-sm" style="height: 100px; width:100%; overflow: hidden;">
                        <?php 
                        $logo_src = (isset($site['logo']) && strpos($site['logo'], 'data:image') === 0) ? $site['logo'] : 'https://via.placeholder.com/300x100?text=Logo';
                        ?>
                        <img id="logoPreview" src="<?php echo $logo_src; ?>" style="max-height: 100%; max-width: 100%;">
                    </div>
                    <input type="file" name="logo" id="logo_up" class="form-control form-control-sm" accept="image/*" onchange="previewLogo(this);">
                    <small class="text-500 fs-11 mt-2 d-block">Instant Base64 storage in database.</small>
                </div>
            </div>

            <!-- SUBMIT BUTTON -->
            <div class="d-grid mt-3">
                <button type="button" class="btn btn-primary shadow-sm fw-bold py-3 fs-9" onclick="confirmSave()">
                    <i class="fas fa-save me-2"></i>Save All Settings
                </button>
            </div>
            
        </div>
    </div>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    <?php echo $swal_script; ?>

    function previewLogo(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#logoPreview').attr('src', e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function confirmSave() {
        Swal.fire({
            title: 'Save Settings?',
            text: "Only Admin can modify these global parameters.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2c7be5',
            cancelButtonColor: '#fa5c7c',
            confirmButtonText: 'Yes, Apply Changes'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('settingsForm').submit();
            }
        });
    }
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>