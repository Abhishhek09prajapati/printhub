<?php
// 1. Session & Auth Control
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');
require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];

/** 
 * ★ FORCE FIX FOR UNDEFINED KEY ★
 * Isse Line 10 wala error 100% khatam ho jayega.
 */
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Retailer';
$u_name = $_SESSION['user_name'] ?? 'User';
$swal_script = "";

// Fetch Site Settings for Branding
$site_query = $conn->query("SELECT site_name FROM settings WHERE id = 1");
$site_data = $site_query->fetch_assoc();
$display_name = $site_data['site_name'] ?? 'ApiNexus';

// 2. Handle Status Toggle Logic (Activate/Deactivate)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $target_id = mysqli_real_escape_string($conn, $_GET['id']);
    $new_status = ($_GET['action'] == 'activate') ? 'Active' : 'Inactive';
    
    $update = $conn->query("UPDATE users SET status = '$new_status' WHERE id = '$target_id'");
    
    if ($update) {
        $swal_script = "Swal.fire({
            title: 'Success!',
            text: 'User status updated to $new_status.',
            icon: 'success',
            confirmButtonColor: '#2c7be5'
        }).then(() => { window.location.href='user_list.php'; });";
    }
}

// 3. Admin Secret Login Logic
if (isset($_GET['login_as']) && $utype == 'TitanAdmin') {
    $login_id = mysqli_real_escape_string($conn, $_GET['login_as']);
    $user_data = $conn->query("SELECT * FROM users WHERE id = '$login_id'")->fetch_assoc();
    if ($user_data) {
        $_SESSION['user_id'] = $user_data['id'];
        $_SESSION['utype'] = $user_data['user_type']; 
        $_SESSION['user_type'] = $user_data['user_type'];
        $_SESSION['user_name'] = $user_data['name'];
        
        $swal_script = "Swal.fire({
            title: 'Switching Account',
            text: 'Redirecting to User Dashboard...',
            icon: 'info',
            timer: 1500,
            showConfirmButton: false
        }).then(() => { window.location.href='titanhome.php'; });";
    }
}

// 4. Fetch Users based on Role
$query = ($utype == 'TitanAdmin') ? "SELECT * FROM users ORDER BY id DESC" : "SELECT * FROM users WHERE referred_by = '$uid' ORDER BY id DESC";
$result = $conn->query($query);
?>

<!-- WELCOME CRM HEADER -->
<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Welcome back, <?php echo htmlspecialchars($u_name); ?></h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($display_name); ?> <span class="text-info fw-medium">Users</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <div class="form-control form-control-sm bg-white border-200">
                        <span class="fas fa-calendar-day text-primary me-2"></span>
                        <span class="fw-bold text-primary"><?php echo date('d M, Y'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- USER LIST TABLE -->
<div class="card mb-3">
    <div class="card-header bg-light">
        <div class="row align-items-center">
            <div class="col">
                <h5 class="mb-0 text-primary fw-bold"><span class="fas fa-users me-2"></span>Manage Accounts</h5>
            </div>
            <div class="col-auto">
                <a class="btn btn-primary btn-sm shadow-none" href="add_user.php"><span class="fas fa-plus me-1"></span> New User</a>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="align-middle ps-3">User Details</th>
                        <th class="align-middle">Contact Info</th>
                        <th class="align-middle">Role</th>
                        <th class="align-middle">Balance</th>
                        <th class="align-middle text-center">Status</th>
                        <th class="align-middle text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="align-middle ps-3">
                            <div class="fw-bold text-1000"><?php echo htmlspecialchars($row['name']); ?></div>
                            <div class="fs-11 text-500"><?php echo htmlspecialchars($row['shop_name']); ?></div>
                        </td>
                        <td class="align-middle">
                            <div class="fw-semi-bold text-primary"><?php echo htmlspecialchars($row['phone']); ?></div>
                            <div class="fs-11 text-600"><?php echo htmlspecialchars($row['email']); ?></div>
                        </td>
                        <td class="align-middle">
                            <?php 
                                $u_role = $row['user_type'];
                                $badge_class = 'badge-subtle-primary';
                                if($u_role == 'TitanAdmin') $badge_class = 'badge-subtle-danger';
                                if($u_role == 'Head Branch') $badge_class = 'badge-subtle-warning';
                                if($u_role == 'Distributor') $badge_class = 'badge-subtle-info';
                            ?>
                            <span class="badge <?php echo $badge_class; ?>"><?php echo $u_role; ?></span>
                        </td>
                        <td class="align-middle fw-bold text-success">
                            ₹<?php echo number_format($row['wallet'], 2); ?>
                        </td>
                        <td class="align-middle text-center">
                            <?php if($row['status'] == 'Active'): ?>
                                <span class="badge rounded-pill badge-subtle-success"><span class="fas fa-check me-1"></span>Active</span>
                            <?php else: ?>
                                <span class="badge rounded-pill badge-subtle-danger"><span class="fas fa-times me-1"></span>Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle text-end pe-3">
                            <div class="btn-group">
                                <!-- Status Toggle -->
                                <?php if($row['status'] == 'Active'): ?>
                                    <a class="btn btn-link text-danger p-0 ms-3" href="user_list.php?action=deactivate&id=<?php echo $row['id']; ?>" title="Deactivate"><span class="fas fa-user-slash"></span></a>
                                <?php else: ?>
                                    <a class="btn btn-link text-success p-0 ms-3" href="user_list.php?action=activate&id=<?php echo $row['id']; ?>" title="Activate"><span class="fas fa-user-check"></span></a>
                                <?php endif; ?>

                                <!-- Login As (Admin Only) -->
                                <?php if($utype == 'TitanAdmin' && $row['id'] != $uid): ?>
                                    <a class="btn btn-link text-info p-0 ms-3" href="user_list.php?login_as=<?php echo $row['id']; ?>" title="Secret Login"><span class="fas fa-user-secret"></span></a>
                                <?php endif; ?>
                                
                                <!-- Edit User -->
                                <a class="btn btn-link text-warning p-0 ms-3" href="edit_user.php?id=<?php echo $row['id']; ?>" title="Edit Info"><span class="fas fa-user-edit"></span></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4">No members found in your list.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_script; ?>
</script>

<?php require_once('../titancore/TitanFooter.php'); ?>