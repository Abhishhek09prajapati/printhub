<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Auth Check
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once('../titancore/titanconfig.php');

$utype = $_SESSION['user_type'] ?? '';
$swal_msg = "";
$show_form = true;

// ★ SECURITY CHECK: SweetAlert Error for Non-Admins ★
if ($utype !== 'TitanAdmin') {
    $show_form = false;
    $swal_msg = "Swal.fire({
        icon: 'error',
        title: 'Permission Denied!',
        text: 'You are not allowed to access this page. Only Titan Admin can update this message.',
        allowOutsideClick: false
    }).then(() => { 
        window.location.href = 'titanhome.php'; // Redirect back to dashboard
    });";
} else {
    // 2. Handle Form Submit (Message Update)
    if (isset($_POST['update_msg'])) {
        $new_msg = mysqli_real_escape_string($conn, trim($_POST['running_msg']));
        
        if (!empty($new_msg)) {
            $update_query = "UPDATE `system_message` SET `running_msg` = '$new_msg' WHERE `id` = 1";
            if (mysqli_query($conn, $update_query)) {
                $swal_msg = "Swal.fire('Updated!', 'Running message updated successfully.', 'success').then(() => { window.location.href='popup_msg.php'; });";
            } else {
                $swal_msg = "Swal.fire('Error', 'Failed to update database.', 'error');";
            }
        } else {
            $swal_msg = "Swal.fire('Warning', 'Message cannot be empty.', 'warning');";
        }
    }
}

// 3. Fetch Current Message (Sirf tabhi jab user admin ho)
$current_msg = "";
if ($show_form) {
    $fetch_query = $conn->query("SELECT running_msg FROM system_message WHERE id = 1");
    $current_msg = $fetch_query->fetch_assoc()['running_msg'] ?? '';
}

require_once('../titancore/titanheader.php');
?>

<?php if ($show_form): ?>
<!-- ★ Agar Admin hai toh Form Show Hoga ★ -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 border-top border-4 border-primary">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-bullhorn me-2 text-primary"></i>Update Dashboard Running Message</h5>
            </div>
            
            <div class="card-body p-4 bg-body-tertiary">
                <form method="post" autocomplete="off" accept-charset="UTF-8">
                    
                    <div class="mb-4">
                        <label class="form-label fs-10 text-900 fw-bold">Current Running Message</label>
                        <textarea class="form-control shadow-sm bg-white" name="running_msg" rows="4" required><?php echo htmlspecialchars($current_msg, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <small class="text-500 mt-1 d-block">This message will scroll across the top of all user dashboards.</small>
                    </div>

                    <div class="text-end border-top pt-3">
                        <button type="submit" name="update_msg" class="btn btn-primary px-5 fw-semi-bold shadow-sm">
                            <i class="fas fa-save me-2"></i>Save & Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Live Preview Box -->
        <div class="card mt-4 bg-body-tertiary border-info border-start border-4 shadow-sm">
            <div class="card-body p-2 d-flex align-items-center">
                <span class="fas fa-bullhorn text-info fs-3 me-3 ms-2"></span>
                <marquee behavior="scroll" direction="left" class="fs-10 fw-semi-bold text-danger mb-0" style="padding-top: 3px;">
                    <?php echo htmlspecialchars($current_msg, ENT_QUOTES, 'UTF-8'); ?>
                </marquee>
            </div>
        </div>
        <p class="text-center text-500 fs-11 mt-2">Live Preview of the message</p>

    </div>
</div>
<?php else: ?>
<!-- ★ Agar Admin NAHI hai toh Screen par Redirecting text dikhega ★ -->
<div class="row justify-content-center mt-5">
    <div class="col-auto text-center">
        <div class="spinner-border text-primary" role="status"></div>
        <h5 class="mt-3 text-700">Verifying Permissions...</h5>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        <?php echo $swal_msg; ?>
    });
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>