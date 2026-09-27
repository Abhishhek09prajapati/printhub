<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Auth & Configuration
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
require_once('../titancore/titanconfig.php');

// Fetch Website Settings (Falcon standard requires site data)
$settings_res = $conn->query("SELECT site_name FROM settings LIMIT 1");
$web = $settings_res->fetch_assoc();

require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$u_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$swal_msg = "";

// Site Name for Header
$display_name = $web['site_name'] ?? 'ApiNexus';

// 2. Fetch Current User Data
$user_res = $conn->query("SELECT * FROM users WHERE id = '$uid'");
$user = $user_res->fetch_assoc();

// 3. Handle Profile Update
if (isset($_POST['update_profile'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    $update = $conn->query("UPDATE users SET name = '$name', email = '$email' WHERE id = '$uid'");
    if ($update) {
        $_SESSION['user_name'] = $name; // Update session name
        $swal_msg = "Swal.fire({
            title: 'Success!',
            text: 'Profile Updated Successfully',
            icon: 'success',
            confirmButtonColor: '#2c7be5'
        }).then(() => { window.location.href='profile.php'; });";
    }
}

// 4. Handle Password Change
if (isset($_POST['change_pass'])) {
    $current_pass = mysqli_real_escape_string($conn, $_POST['current_pass']);
    $new_pass = mysqli_real_escape_string($conn, $_POST['new_pass']);
    $confirm_pass = mysqli_real_escape_string($conn, $_POST['confirm_pass']);

    if ($user['password'] !== $current_pass) {
        $swal_msg = "Swal.fire('Error', 'Current password is incorrect', 'error');";
    } elseif ($new_pass !== $confirm_pass) {
        $swal_msg = "Swal.fire('Error', 'New passwords do not match', 'warning');";
    } elseif (strlen($new_pass) < 6) {
        $swal_msg = "Swal.fire('Error', 'Password must be at least 6 characters', 'warning');";
    } else {
        $update_p = $conn->query("UPDATE users SET password = '$new_pass' WHERE id = '$uid'");
        if ($update_p) {
            $swal_msg = "Swal.fire({
                title: 'Success!',
                text: 'Password Changed Successfully',
                icon: 'success',
                confirmButtonColor: '#2c7be5'
            });";
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Manage your Account,</h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($display_name); ?> <span class="text-info fw-medium">CRM</span></h4>
                    </div>
                    <img class="ms-n4 d-md-none d-lg-block" src="../assets/img/illustrations/crm-line-chart.png" alt="" width="150" />
                </div>
                <div class="col-md-auto p-3">
                    <div class="row align-items-center g-3">
                        <div class="col-auto"><h6 class="text-700 mb-0">Today: </h6></div>
                        <div class="col-md-auto">
                            <div class="form-control form-control-sm d-flex align-items-center bg-white border-200" style="min-width: 140px;">
                                <span class="fas fa-calendar-day text-primary me-2"></span>
                                <span class="fw-bold text-primary"><?php echo date('d M, Y'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-user-circle me-2"></i>Profile Settings</h5>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <form method="POST" class="p-4">
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="name">Full Name</label>
                        <input class="form-control" name="name" id="name" type="text" value="<?php echo htmlspecialchars($user['name']); ?>" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="phone">Mobile Number (Login ID)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-200 border-200"><i class="fas fa-lock fs-11"></i></span>
                            <input class="form-control bg-200" id="phone" type="text" value="<?php echo htmlspecialchars($user['phone']); ?>" readonly />
                        </div>
                        <div class="fs-11 text-danger mt-1"><i class="fas fa-info-circle me-1"></i>Mobile number cannot be changed.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="email">Email Address</label>
                        <input class="form-control" name="email" id="email" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" required />
                    </div>
                    <div class="mt-4 text-end">
                        <button class="btn btn-falcon-primary btn-sm px-4 fw-semi-bold" type="submit" name="update_profile">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-shield-alt me-2"></i>Security</h5>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <form method="POST" class="p-4">
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="current_pass">Current Password</label>
                        <input class="form-control" name="current_pass" id="current_pass" type="password" placeholder="••••••••" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="new_pass">New Password</label>
                        <input class="form-control" name="new_pass" id="new_pass" type="password" placeholder="Min 6 chars" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-10 text-900" for="confirm_pass">Confirm Password</label>
                        <input class="form-control" name="confirm_pass" id="confirm_pass" type="password" placeholder="••••••••" required />
                    </div>
                    <div class="mt-4 text-end">
                        <button class="btn btn-falcon-danger btn-sm px-4 fw-semi-bold" type="submit" name="change_pass">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </div>
                </form>
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