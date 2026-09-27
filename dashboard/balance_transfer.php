<?php
ob_start();
// 1. Session & Auth Control
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');

/** 
 * ★ SESSION KEY FIX ★
 * Isse line 10-15 wala error jad se khatam ho jayega.
 */
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Retailer';
$uid = $_SESSION['user_id'];
$u_name = $_SESSION['user_name'] ?? 'User';

// 2. Retailer Security Check (Retailers balance transfer nahi kar sakte)
if ($utype == 'Retailer') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        window.onload = function() {
            Swal.fire({
                title: 'Access Denied!',
                text: 'Retailers cannot transfer balance.',
                icon: 'error',
                confirmButtonColor: '#2c7be5'
            }).then(() => { window.location.href = 'titanhome.php'; });
        };
    </script>";
    exit();
}

require_once('../titancore/titanheader.php');

$swal_msg = "";

// Fetch Site Settings for CRM Header
$site_query = $conn->query("SELECT site_name FROM settings WHERE id = 1");
$site_data = $site_query->fetch_assoc();
$display_name = $site_data['site_name'] ?? 'ApiNexus';

// 3. Handle Transfer Logic
if (isset($_POST['transfer_btn'])) {
    $receiver_id = mysqli_real_escape_string($conn, $_POST['receiver_id']);
    $amount = floatval(mysqli_real_escape_string($conn, $_POST['amount']));

    if ($amount <= 0) {
        $swal_msg = "Swal.fire('Error', 'Please enter a valid amount', 'error');";
    } else {
        // Sender balance check
        $sender = $conn->query("SELECT wallet FROM users WHERE id = '$uid'")->fetch_assoc();
        
        if ($utype != 'TitanAdmin' && $sender['wallet'] < $amount) {
            $swal_msg = "Swal.fire('Low Balance', 'Insufficient wallet balance', 'warning');";
        } else {
            // Transaction Start
            $conn->begin_transaction();
            try {
                // 1. Update Receiver
                $conn->query("UPDATE users SET wallet = wallet + $amount WHERE id = '$receiver_id'");
                
                // 2. Update Sender (If not Admin)
                if ($utype != 'TitanAdmin') {
                    $conn->query("UPDATE users SET wallet = wallet - $amount WHERE id = '$uid'");
                }
                
                // Transaction Commit
                $conn->commit();
                $swal_msg = "Swal.fire('Success', '₹$amount Transferred Successfully', 'success').then(() => { window.location.href='balance_transfer.php'; });";
            } catch (Exception $e) {
                $conn->rollback();
                $swal_msg = "Swal.fire('Failed', 'Transaction Error', 'error');";
            }
        }
    }
}

// 4. Fetch User List based on Role
$query = ($utype == 'TitanAdmin') ? "SELECT id, name, shop_name, wallet, user_type FROM users WHERE id != '$uid'" : "SELECT id, name, shop_name, wallet, user_type FROM users WHERE referred_by = '$uid'";
$user_list = $conn->query($query);
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
                        <h4 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($display_name); ?> <span class="text-info fw-medium">Wallet</span></h4>
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

<!-- TRANSFER FORM -->
<div class="card mb-3">
    <div class="card-header bg-light">
        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-exchange-alt me-2"></i>Wallet Balance Transfer</h5>
        <p class="mb-0 fs-11">Send funds instantly to your team members' wallets.</p>
    </div>
    <div class="card-body bg-body-tertiary">
        <form method="POST" class="row g-3 justify-content-center">
            <div class="col-md-5">
                <label class="form-label fw-bold">Select User (Receiver)</label>
                <select class="form-select shadow-none" name="receiver_id" required>
                    <option value="">Choose Member...</option>
                    <?php if($user_list && $user_list->num_rows > 0): ?>
                    <?php while($row = $user_list->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>">
                            <?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['shop_name']); ?>) - Bal: ₹<?php echo number_format($row['wallet'], 2); ?>
                        </option>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label class="form-label fw-bold">Amount (₹)</label>
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white border-end-0">₹</span>
                    <input class="form-control shadow-none border-start-0" name="amount" type="number" step="0.01" placeholder="0.00" required />
                </div>
            </div>

            <div class="col-md-3 align-self-end">
                <button class="btn btn-primary w-100 fw-bold shadow-none" name="transfer_btn" type="submit">
                    <i class="fas fa-paper-plane me-2"></i> Transfer Now
                </button>
            </div>
        </form>
    </div>
</div>

<!-- WALLET OVERVIEW TABLE -->
<div class="card mb-3">
    <div class="card-header bg-light border-bottom">
        <h6 class="mb-0 text-700"><i class="fas fa-users-cog me-2"></i>Team Wallet Overview</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3 align-middle">Member Name</th>
                        <th class="align-middle">Role</th>
                        <th class="text-end pe-3 align-middle">Current Wallet Balance</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php 
                    if($user_list && $user_list->num_rows > 0):
                        $user_list->data_seek(0); // Reset pointer
                        while($row = $user_list->fetch_assoc()): 
                    ?>
                    <tr>
                        <td class="ps-3 py-2 align-middle">
                            <div class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></div>
                            <div class="fs-11 text-500"><?php echo htmlspecialchars($row['shop_name']); ?></div>
                        </td>
                        <td class="align-middle">
                            <span class="badge badge-subtle-secondary"><?php echo $row['user_type']; ?></span>
                        </td>
                        <td class="text-end pe-3 align-middle fw-bold text-success">
                            ₹<?php echo number_format($row['wallet'], 2); ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="3" class="text-center py-3">No team members found.</td></tr>
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