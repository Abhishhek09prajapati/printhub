<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Basic Login Check
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// 2. ★ STRICT ADMIN SECURITY CHECK ★
// User ka type nikalna (utype ya user_type)
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? '';

// Agar user 'TitanAdmin' NAHI hai, toh usko bahar nikal do
if ($utype !== 'TitanAdmin') {
    // Alert dikhakar wapas home page par bhej dena
    echo "<script>alert('Access Denied! 🚫 Only Admins can access this page.'); window.location.href='titanhome.php';</script>";
    exit(); 
}

require_once('../titancore/titanconfig.php');

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");

require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$swal_msg = "";

// Update Logic
if (isset($_POST['update_msg_btn'])) {
    // Message ko safely escape karke lena taaki ' ya " se error na aaye
    $new_msg = mysqli_real_escape_string($conn, trim($_POST['running_msg']));
    
    // Database me update karna (Hamesha id=1 update hoga)
    $update_query = "UPDATE system_message SET running_msg = '$new_msg' WHERE id = 1";
    
    if ($conn->query($update_query)) {
        $swal_msg = "Swal.fire('Updated!', 'Running message has been updated successfully.', 'success').then(() => { window.location.href='update_message.php'; });";
    } else {
        $swal_msg = "Swal.fire('Error!', 'Database error: " . $conn->error . "', 'error');";
    }
}

// Current Message Fetch karna (Text box me dikhane ke liye)
$msg_query = $conn->query("SELECT running_msg FROM system_message WHERE id = 1");
$msg_data = $msg_query->fetch_assoc();
$current_msg = $msg_data['running_msg'] ?? '';
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-bullhorn text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Portal Settings (Admin Only)</h6>
                        <h4 class="text-primary fw-bold mb-0">Update <span class="fw-medium">Running Notice</span></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm border-top border-4 border-primary">
            <div class="card-header bg-body-tertiary border-bottom">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-edit me-2"></i>Live Marquee Message</h5>
            </div>
            <div class="card-body bg-body-tertiary p-4">
                
                <div class="alert alert-info border-0 d-flex align-items-center mb-4">
                    <i class="fas fa-info-circle fs-3 me-3 text-info"></i>
                    <p class="mb-0 fs-11">Ye message aapke portal ke dashboard par sabhi users ko running (scroll hote hue) dikhai dega. Koi bhi naya offer ya alert yahan type karein.</p>
                </div>
                
                <form method="POST" autocomplete="off">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Enter Notice / Message <span class="text-danger">*</span></label>
                        <textarea class="form-control shadow-none fw-semi-bold" name="running_msg" rows="4" placeholder="Enter your running text here..." required><?php echo htmlspecialchars($current_msg); ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_msg_btn" class="btn btn-primary w-100 fw-bold shadow-none py-2">
                        <i class="fas fa-save me-2"></i> Save Live Message
                    </button>
                </form>

            </div>
            <div class="card-footer bg-light text-center border-top">
                <h6 class="mb-0 text-600 fs-11"><i class="fas fa-shield-alt text-success me-1"></i> Secured Page: Only Admin can make changes here.</h6>
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