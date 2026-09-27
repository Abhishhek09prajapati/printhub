<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');

// Fix Database Encoding
mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");
mysqli_query($conn, "SET CHARACTER SET 'utf8mb4'");

require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Retailer';

// Admin ko sab dikhega, Retailer ko sirf apna
$filter = ($utype == 'TitanAdmin') ? "" : "WHERE user_id = '$uid'";
$query = "SELECT * FROM rc_to_mobile_history $filter ORDER BY id DESC";
$result = $conn->query($query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-danger-subtle shadow-none me-3">
                        <span class="fas fa-list text-danger fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-danger fs-10 mb-0">History Log</h6>
                        <h4 class="text-primary fw-bold mb-0">RC To Mobile <span class="fw-medium">Records</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="rc_to_mobile.php" class="btn btn-sm btn-danger shadow-none fw-bold"><i class="fas fa-plus me-1"></i> Search New RC</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3 border-0 shadow-sm border-top border-4 border-danger">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 text-center align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">#ID</th>
                        <th>Vehicle No (RC)</th>
                        <th>Linked Mobile No.</th>
                        <th class="text-end pe-4">Date & Time</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="py-3 fw-bold">#<?php echo $row['id']; ?></td>
                                <td>
                                    <span class="badge badge-subtle-primary fs-11 px-3 py-2 font-monospace border border-primary"><i class="fas fa-car-side me-1"></i> <?php echo htmlspecialchars($row['vehicle_no']); ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-subtle-success fs-11 px-3 py-2 font-monospace border border-success"><i class="fas fa-phone-alt me-1"></i> +91 <?php echo htmlspecialchars($row['mobile_no']); ?></span>
                                </td>
                                <td class="text-end pe-4 text-600">
                                    <?php echo date('d M Y', strtotime($row['created_at'])); ?><br>
                                    <small><?php echo date('h:i A', strtotime($row['created_at'])); ?></small>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="py-4 text-500 fst-italic">No records found. Search a vehicle number first.</td></tr>
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