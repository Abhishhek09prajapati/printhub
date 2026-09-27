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
$query = "SELECT * FROM rc_pro_history $filter ORDER BY id DESC";
$result = $conn->query($query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-warning-subtle shadow-none me-3">
                        <span class="fas fa-file-pdf text-warning fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-warning fs-10 mb-0">History Log</h6>
                        <h4 class="text-primary fw-bold mb-0">RC PRO <span class="text-warning fw-medium">Downloads</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="rc_pro.php" class="btn btn-sm btn-warning shadow-none fw-bold text-dark"><i class="fas fa-plus me-1"></i> Fetch New RC</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3 border-0 shadow-sm border-top border-4 border-warning">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 text-center align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">#ID</th>
                        <th>Vehicle No.</th>
                        <th>Owner Details</th>
                        <th>Vehicle Class</th>
                        <th>Date & Time</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="py-2 fw-bold">#<?php echo $row['id']; ?></td>
                                <td>
                                    <span class="badge bg-primary fs-11 px-2 font-monospace"><?php echo htmlspecialchars($row['vehicle_no']); ?></span>
                                </td>
                                <td class="text-start">
                                    <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($row['owner_name']); ?></span>
                                </td>
                                <td><span class="text-600 fw-semi-bold"><?php echo htmlspecialchars($row['vehicle_class']); ?></span></td>
                                <td><span class="text-600"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></span></td>
                                <td class="text-end pe-4">
                                    <a href="<?php echo htmlspecialchars($row['pdf_data']); ?>" download="RC_PRO_<?php echo htmlspecialchars($row['vehicle_no']); ?>.pdf" class="btn btn-falcon-danger btn-sm shadow-none fw-bold px-3">
                                        <i class="fas fa-file-pdf me-1"></i> Download
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="py-4 text-500">No RC records found. Fetch a Vehicle RC first.</td></tr>
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