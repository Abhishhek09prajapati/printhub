<?php
// 1. Session & Auth
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');
require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];

/** 
 * ★ LINE 10 FIX (JAD SE KHATAM) ★
 * Hum check kar rahe hain ki 'user_type' ya 'utype' dono mein se jo mile wo utha le.
 * Agar dono nahi hain toh default 'Retailer' set ho jayega warning ke bina.
 */
$utype = $_SESSION['user_type'] ?? $_SESSION['utype'] ?? 'Retailer';
$u_name = $_SESSION['user_name'] ?? 'User'; 
$message = "";

// 2. Fetch Site Settings for Header
$site_query = $conn->query("SELECT site_name FROM settings WHERE id = 1");
$site_data = $site_query->fetch_assoc();
$display_name = $site_data['site_name'] ?? 'ApiNexus';

// 3. Form Submission Logic
if (isset($_POST['add_user'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $shop_name = mysqli_real_escape_string($conn, $_POST['shop_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $target_role = mysqli_real_escape_string($conn, $_POST['role_selection']); // Named uniquely to avoid conflict
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $is_allowed = false;
    
    // Authorization Check based on $utype
    if ($utype == 'TitanAdmin') { 
        $is_allowed = true; 
    } elseif ($utype == 'Head Branch' && ($target_role == 'Distributor' || $target_role == 'Retailer')) { 
        $is_allowed = true; 
    } elseif ($utype == 'Distributor' && $target_role == 'Retailer') { 
        $is_allowed = true; 
    }

    if ($is_allowed) {
        $check = $conn->query("SELECT id FROM users WHERE email = '$email' OR phone = '$phone'");
        if ($check->num_rows > 0) {
            $message = "error_exists";
        } else {
            // DB insertion
            $insert = $conn->query("INSERT INTO users (name, shop_name, email, phone, password, user_type, status, referred_by, wallet) 
                                   VALUES ('$name', '$shop_name', '$email', '$phone', '$password', '$target_role', '$status', '$uid', '0')");
            $message = $insert ? "success" : "error_db";
        }
    } else {
        $message = "not_authorized";
    }
}
?>

<!-- WELCOME BANNER (Aapka Falcon Design) -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Welcome back, <?php echo htmlspecialchars($u_name); ?></h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($display_name); ?> <span class="text-info fw-medium">CRM</span></h4>
                    </div>
                    <img class="ms-n4 d-md-none d-lg-block" src="../assets/img/illustrations/crm-line-chart.png" alt="" width="150" />
                </div>
                <div class="col-md-auto p-3">
                    <div class="form-control form-control-sm d-flex align-items-center bg-white border-200">
                        <span class="fas fa-calendar-day text-primary me-2"></span>
                        <span class="fw-bold text-primary"><?php echo date('d M, Y'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ADD USER FORM CARD -->
<div class="card mb-3">
    <div class="card-header bg-light">
        <h5 class="mb-0 text-primary"><span class="fas fa-user-plus me-2"></span>Add New User</h5>
        <p class="mb-0 fs-11">Create a new account for your branch, distributor, or retailer.</p>
    </div>
    <div class="card-body bg-body-tertiary">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input class="form-control shadow-none" name="name" type="text" placeholder="Enter Full Name" required />
            </div>
            <div class="col-md-6">
                <label class="form-label">Shop Name</label>
                <input class="form-control shadow-none" name="shop_name" type="text" placeholder="Enter Business Name" required />
            </div>
            <div class="col-md-6">
                <label class="form-label">Email ID</label>
                <input class="form-control shadow-none" name="email" type="email" placeholder="email@example.com" required />
            </div>
            <div class="col-md-6">
                <label class="form-label">Mobile Number</label>
                <input class="form-control shadow-none" name="phone" type="text" maxlength="10" placeholder="10 Digit Number" required />
            </div>
            <div class="col-md-6">
                <label class="form-label">Login Password</label>
                <input class="form-control shadow-none" name="password" type="text" placeholder="Min 6 characters" required />
            </div>
            <div class="col-md-3">
                <label class="form-label">User Role</label>
                <select class="form-select shadow-none" name="role_selection" required>
                    <option value="">Select Role</option>
                    <?php if ($utype == 'TitanAdmin'): ?>
                        <option value="Head Branch">Head Branch</option>
                        <option value="Distributor">Distributor</option>
                        <option value="Retailer">Retailer</option>
                    <?php elseif ($utype == 'Head Branch'): ?>
                        <option value="Distributor">Distributor</option>
                        <option value="Retailer">Retailer</option>
                    <?php elseif ($utype == 'Distributor'): ?>
                        <option value="Retailer">Retailer</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select shadow-none" name="status" required>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <div class="col-12 mt-4 text-end">
                <hr>
                <button class="btn btn-primary px-5 shadow-none" type="submit" name="add_user">
                    <span class="fas fa-save me-2"></span>Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if ($message == "success"): ?>
    <script>Swal.fire('Success', 'User Account Created Successfully!', 'success');</script>
<?php elseif ($message == "error_exists"): ?>
    <script>Swal.fire('Warning', 'Mobile or Email Already Exists', 'warning');</script>
<?php elseif ($message == "error_db"): ?>
    <script>Swal.fire('Error', 'Database Error!', 'error');</script>
<?php elseif ($message == "not_authorized"): ?>
    <script>Swal.fire('Access Denied', 'You are not authorized to create this user', 'error');</script>
<?php endif; ?>

<?php require_once('../titancore/TitanFooter.php'); ?>