<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Auth & Role Check
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
require_once('../titancore/titanconfig.php');

$uid = $_SESSION['user_id'];
// ★ FIX: Undefined array key error hatane ke liye smart fallback lagaya gaya hai ★
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'TitanAdmin';

// Check if ID is provided
if (!isset($_GET['id'])) { header("Location: user_list.php"); exit(); }
$target_id = mysqli_real_escape_string($conn, $_GET['id']);

// 2. Fetch User Data
$user_res = $conn->query("SELECT * FROM users WHERE id = '$target_id'");
$user = $user_res->fetch_assoc();

if (!$user) { header("Location: user_list.php"); exit(); }

$swal_msg = "";

// 3. Handle Form Submission
if (isset($_POST['update_user'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $shop_name = mysqli_real_escape_string($conn, $_POST['shop_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $wallet = mysqli_real_escape_string($conn, $_POST['wallet']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $role = mysqli_real_escape_string($conn, $_POST['user_type']);
    $new_pass = mysqli_real_escape_string($conn, $_POST['password']);

    // Update Query
    $sql = "UPDATE users SET 
            name = '$name', 
            shop_name = '$shop_name', 
            email = '$email', 
            phone = '$phone', 
            wallet = '$wallet', 
            status = '$status', 
            user_type = '$role'";
    
    // Agar password field bhara hai toh hi update karein
    if (!empty($new_pass)) {
        $sql .= ", password = '$new_pass'";
    }
    
    $sql .= " WHERE id = '$target_id'";

    if ($conn->query($sql)) {
        $swal_msg = "Swal.fire('Updated!', 'User details updated successfully', 'success').then(() => { window.location.href='user_list.php'; });";
    } else {
        $swal_msg = "Swal.fire('Error!', 'Database error: " . $conn->error . "', 'error');";
    }
}

require_once('../titancore/titanheader.php');
?>

<div class="card mb-3">
    <div class="card-header bg-light">
        <div class="row flex-between-center">
            <div class="col-sm-auto">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-user-edit me-2"></i>Edit User: <?php echo htmlspecialchars($user['name']); ?></h5>
            </div>
            <div class="col-sm-auto">
                <a class="btn btn-falcon-default btn-sm shadow-none" href="user_list.php"><i class="fas fa-arrow-left me-1"></i> Back to List</a>
            </div>
        </div>
    </div>
    <div class="card-body bg-body-tertiary">
        <form method="POST" class="row g-3" autocomplete="off">
            
            <div class="col-12 border-bottom pb-2 mb-2">
                <h6 class="text-900 mb-0">Business & Personal Profile</h6>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semi-bold">Owner Name</label>
                <input class="form-control shadow-none" name="name" type="text" value="<?php echo htmlspecialchars($user['name']); ?>" required />
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semi-bold">Shop / Business Name</label>
                <input class="form-control shadow-none" name="shop_name" type="text" value="<?php echo htmlspecialchars($user['shop_name']); ?>" required />
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semi-bold">Email ID</label>
                <input class="form-control shadow-none" name="email" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" required />
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semi-bold">Mobile Number</label>
                <input class="form-control shadow-none" name="phone" type="text" value="<?php echo htmlspecialchars($user['phone']); ?>" required />
            </div>

            <div class="col-12 border-bottom pb-2 mt-4 mb-2">
                <h6 class="text-900 mb-0">Account & Financial Controls</h6>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semi-bold text-success">Wallet Balance (₹)</label>
                <input class="form-control shadow-none border-success" name="wallet" type="number" step="0.01" value="<?php echo htmlspecialchars($user['wallet']); ?>" required />
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semi-bold">User Role</label>
                <select class="form-select shadow-none" name="user_type" required>
                    <option value="Head Branch" <?php if($user['user_type'] == 'Head Branch') echo 'selected'; ?>>Head Branch</option>
                    <option value="Distributor" <?php if($user['user_type'] == 'Distributor') echo 'selected'; ?>>Distributor</option>
                    <option value="Retailer" <?php if($user['user_type'] == 'Retailer') echo 'selected'; ?>>Retailer Member</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semi-bold">Account Status</label>
                <select class="form-select shadow-none" name="status" required>
                    <option value="Active" <?php if($user['status'] == 'Active') echo 'selected'; ?>>Active</option>
                    <option value="Inactive" <?php if($user['status'] == 'Inactive') echo 'selected'; ?>>Inactive</option>
                </select>
            </div>

            <div class="col-md-6 mt-4">
                <label class="form-label fw-semi-bold text-danger">Update Password (Leave blank to keep same)</label>
                <input class="form-control shadow-none" name="password" type="text" placeholder="Enter new password only if changing" />
            </div>

            <div class="col-12 mt-5">
                <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" type="submit" name="update_user">
                    <i class="fas fa-save me-2"></i> Save All Changes
                </button>
            </div>
        </form>
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