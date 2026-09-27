<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');
require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Retailer';

// Admin ko sab dikhega, Retailer ko sirf apna
$filter = ($utype == 'TitanAdmin') ? "" : "WHERE user_id = '$uid'";
$query = "SELECT * FROM aadhar_to_pan_history $filter ORDER BY id DESC";
$result = $conn->query($query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-clipboard-list text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">History Log</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhar to Mask PAN <span class="text-warning fw-medium">Records</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <a href="aadhaar_mask.php" class="btn btn-sm btn-warning shadow-none fw-bold text-dark"><i class="fas fa-search me-1"></i> New Search</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 text-center align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">#ID</th>
                        <th>Aadhaar No.</th>
                        <th>Masked PAN</th>
                        <th>System Message</th>
                        <th>Provider</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="py-2 fw-bold">#<?php echo $row['id']; ?></td>
                                <td><span class="fw-semi-bold text-600"><?php echo htmlspecialchars($row['aadhar_no']); ?></span></td>
                                <td>
                                    <span class="badge badge-subtle-success fs-10 px-2 py-1 border border-success">
                                        <i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($row['mask_pan']); ?>
                                    </span>
                                </td>
                                <td class="text-start px-3" style="max-width: 250px;">
                                    <small class="text-700"><?php echo htmlspecialchars($row['message']); ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['provider']); ?></span></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="py-4 text-500">No records found. Try searching for a PAN first.</td></tr>
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