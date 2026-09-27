<?php
ob_start();
// 1. Session & Auth Control
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');

$uid = $_SESSION['user_id'];
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Retailer';
$u_name = $_SESSION['user_name'] ?? 'User';

// 2. STRICT ADMIN SECURITY CHECK
if ($utype !== 'TitanAdmin') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        window.onload = function() {
            Swal.fire({
                title: 'Access Denied!',
                text: 'Only TitanAdmin has permission to manage System Alerts.',
                icon: 'error',
                confirmButtonColor: '#fb1752'
            }).then(() => { window.location.href = 'titanhome.php'; });
        };
    </script>";
    exit();
}

require_once('../titancore/titanheader.php');

// Fetch Site Settings for Branding
$site_query = $conn->query("SELECT site_name FROM settings WHERE id = 1");
$site_data = $site_query->fetch_assoc();
$display_name = $site_data['site_name'] ?? 'ApiNexus';

$swal_msg = "";
$alert_file = "alert_popup.php";

// 3. Handle Alert Message Update
if (isset($_POST['save_alert'])) {
    // Basic sanitization, allowing HTML tags so Admin can design the text if needed
    $alert_content = $_POST['alert_message'];
    
    // Save to alert_popup.php
    if (file_put_contents($alert_file, $alert_content) !== false) {
        $swal_msg = "Swal.fire('Success', 'Alert Popup Message Updated and Enabled!', 'success');";
    } else {
        $swal_msg = "Swal.fire('Error', 'Failed to write file. Check folder permissions.', 'error');";
    }
}

// 4. Handle Alert Message Clear (Disable Popup)
if (isset($_POST['clear_alert'])) {
    if (file_put_contents($alert_file, "") !== false) {
        $swal_msg = "Swal.fire('Disabled', 'Alert Popup has been removed from user dashboard.', 'info');";
    } else {
        $swal_msg = "Swal.fire('Error', 'Failed to clear file.', 'error');";
    }
}

// Read Current Alert Message
$current_alert = file_exists($alert_file) ? file_get_contents($alert_file) : "";
?>

<!-- WELCOME CRM HEADER -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-bell text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">System Settings</h6>
                        <h4 class="text-primary fw-bold mb-0">Manage <span class="text-info fw-medium">Global Alert Popup</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <div class="form-control form-control-sm bg-white border-200">
                        <span class="fas fa-shield-alt text-danger me-2"></span>
                        <span class="fw-bold text-danger">Admin Access Only</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ALERT MANAGEMENT FORM -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3 h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-edit me-2"></i>Write Popup Message</h5>
                <p class="mb-0 fs-11">This message will immediately appear as a popup on all users' dashboards.</p>
            </div>
            <div class="card-body bg-body-tertiary">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Alert Message Body</label>
                        <textarea class="form-control shadow-none border-400" name="alert_message" rows="8" placeholder="Type your important notification, update, or warning here... (You can also use basic HTML like <b> for bold or <br> for line breaks)"><?php echo htmlspecialchars($current_alert); ?></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <button class="btn btn-outline-danger fw-bold shadow-none px-4" name="clear_alert" type="submit" onclick="return confirm('Are you sure you want to disable the popup?');">
                            <i class="fas fa-trash-alt me-2"></i> Clear & Disable Popup
                        </button>
                        
                        <button class="btn btn-primary fw-bold shadow-none px-5" name="save_alert" type="submit">
                            <i class="fas fa-broadcast-tower me-2"></i> Publish Popup Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- INSTRUCTIONS / PREVIEW INFO -->
    <div class="col-lg-4">
        <div class="card mb-3 h-100">
            <div class="card-header bg-light">
                <h6 class="mb-0 text-700"><i class="fas fa-info-circle me-2"></i>How it works</h6>
            </div>
            <div class="card-body">
                <p class="fs-10 text-600 mb-3">
                    The Global Alert Popup system uses a dynamic file generator. When you write a message and click <strong>Publish</strong>, the system automatically builds the <code>alert_popup.php</code> file.
                </p>
                <hr>
                <h6 class="fw-bold text-success"><i class="fas fa-check-circle me-1"></i> To Enable Popup:</h6>
                <p class="fs-11 text-600">Type any message in the box and click Publish. The popup will start appearing on every login and dashboard load.</p>
                
                <h6 class="fw-bold text-danger mt-3"><i class="fas fa-times-circle me-1"></i> To Disable Popup:</h6>
                <p class="fs-11 text-600 mb-0">Click the <strong>Clear & Disable Popup</strong> button. It will empty the file and stop the popup from showing to users.</p>
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