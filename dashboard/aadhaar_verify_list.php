<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');
require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Retailer';

// Admin saari detail dekh payega, Retailer sirf apni
$filter = ($utype == 'TitanAdmin') ? "" : "WHERE user_id = '$uid'";
$query = "SELECT * FROM aadhar_verify_history $filter ORDER BY id DESC";
$result = $conn->query($query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-list-alt text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">History Log</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar Verification <span class="text-info fw-medium">List</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <a href="aadhaar_verify.php" class="btn btn-sm btn-primary shadow-none fw-bold"><i class="fas fa-plus me-1"></i> New Verify</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 text-center">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">#ID</th>
                        <th>Aadhaar No.</th>
                        <th>Message Status</th>
                        <th>IP Address</th>
                        <th>Provider</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="py-2 fw-bold">#<?php echo $row['id']; ?></td>
                                <td><code class="fw-bold fs-11 text-primary"><?php echo htmlspecialchars($row['aadhar_no']); ?></code></td>
                                <td class="text-success fw-semi-bold"><?php echo htmlspecialchars($row['message']); ?></td>
                                <td><span class="badge badge-subtle-secondary"><?php echo htmlspecialchars($row['ip_used']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['provider']); ?></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="py-4 text-500">No verification history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>