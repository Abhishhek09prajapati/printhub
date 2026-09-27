<?php
ob_start();
// 1. Session & Auth Control
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');

$uid = $_SESSION['user_id'];
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Retailer';
$u_name = $_SESSION['user_name'] ?? 'User';

// 2. ★ STRICT ADMIN SECURITY CHECK ★
// Sirf TitanAdmin ko hi allow karega, warna bahar nikal dega.
if ($utype !== 'TitanAdmin') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        window.onload = function() {
            Swal.fire({
                title: 'Access Denied!',
                text: 'Only TitanAdmin can manage System Services.',
                icon: 'error',
                confirmButtonColor: '#fb1752',
                allowOutsideClick: false
            }).then(() => { window.location.href = 'titanhome.php'; });
        };
    </script>";
    exit();
}

require_once('../titancore/titanheader.php');

$swal_msg = "";

// 3. Handle Status Toggle Logic (ON/OFF)
if (isset($_GET['toggle_id'])) {
    $target_id = mysqli_real_escape_string($conn, $_GET['toggle_id']);
    
    $check_status = $conn->query("SELECT status, service_name FROM pricing WHERE id = '$target_id'")->fetch_assoc();
    if ($check_status) {
        $new_status = ($check_status['status'] == 1) ? 0 : 1;
        $s_name = $check_status['service_name'];
        
        $update = $conn->query("UPDATE pricing SET status = '$new_status' WHERE id = '$target_id'");
        
        if ($update) {
            $status_text = ($new_status == 1) ? "Activated" : "Deactivated";
            $swal_msg = "Swal.fire('Updated!', 'Service ($s_name) has been $status_text.', 'success').then(() => { window.location.href='service_manage.php'; });";
        }
    }
}

// 4. Handle Price Update Logic
if (isset($_POST['update_price_btn'])) {
    $target_id = mysqli_real_escape_string($conn, $_POST['service_id']);
    $new_price = floatval(mysqli_real_escape_string($conn, $_POST['new_price']));
    
    $update = $conn->query("UPDATE pricing SET price = '$new_price' WHERE id = '$target_id'");
    
    if ($update) {
        $swal_msg = "Swal.fire('Success!', 'Service Price has been updated to ₹$new_price', 'success').then(() => { window.location.href='service_manage.php'; });";
    } else {
        $swal_msg = "Swal.fire('Error!', 'Database Error. Failed to update price.', 'error');";
    }
}

// 5. Fetch All Services from 'pricing' table
$services_query = "SELECT * FROM pricing ORDER BY id ASC";
$result = $conn->query($services_query);
?>

<!-- WELCOME CRM HEADER -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-cogs text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">System Control</h6>
                        <h4 class="text-primary fw-bold mb-0">Manage <span class="text-info fw-medium">Services & Pricing</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <div class="form-control form-control-sm bg-white border-200">
                        <span class="fas fa-shield-alt text-danger me-2"></span>
                        <span class="fw-bold text-danger">Master Admin Area</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SERVICES MANAGEMENT TABLE -->
<div class="card mb-3">
    <div class="card-header bg-light">
        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-list-alt me-2"></i>All Available Services</h5>
        <p class="mb-0 fs-11">Turn services ON/OFF and update their prices in real-time.</p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3 align-middle">ID</th>
                        <th class="align-middle">Service Name (DB Key)</th>
                        <th class="align-middle text-center">Set New Price (₹)</th>
                        <th class="align-middle text-center">Current Status</th>
                        <th class="align-middle text-end pe-3">Action (ON/OFF)</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-3 py-2 align-middle fw-bold">#<?php echo $row['id']; ?></td>
                        <td class="align-middle">
                            <span class="badge badge-subtle-primary fs-11"><i class="fas fa-code me-1"></i> <?php echo htmlspecialchars($row['service_name']); ?></span>
                        </td>
                        <td class="align-middle text-center" style="width: 250px;">
                            <form method="POST" class="d-flex justify-content-center align-items-center mb-0">
                                <input type="hidden" name="service_id" value="<?php echo $row['id']; ?>">
                                <div class="input-group input-group-sm w-75">
                                    <span class="input-group-text bg-light text-900 border-end-0">₹</span>
                                    <input type="number" step="0.01" class="form-control shadow-none border-start-0" name="new_price" value="<?php echo $row['price']; ?>" required>
                                    <button type="submit" name="update_price_btn" class="btn btn-success" title="Save Price"><i class="fas fa-save"></i></button>
                                </div>
                            </form>
                        </td>
                        <td class="align-middle text-center">
                            <?php if($row['status'] == 1): ?>
                                <span class="badge rounded-pill badge-subtle-success"><i class="fas fa-check-circle me-1"></i> Active</span>
                            <?php else: ?>
                                <span class="badge rounded-pill badge-subtle-danger"><i class="fas fa-times-circle me-1"></i> Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle text-end pe-3">
                            <?php if($row['status'] == 1): ?>
                                <a href="service_manage.php?toggle_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger shadow-none" title="Click to Disable">
                                    <i class="fas fa-power-off me-1"></i> Turn OFF
                                </a>
                            <?php else: ?>
                                <a href="service_manage.php?toggle_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success shadow-none" title="Click to Enable">
                                    <i class="fas fa-power-off me-1"></i> Turn ON
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="5" class="text-center py-4 text-500">No services found in 'pricing' table.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
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