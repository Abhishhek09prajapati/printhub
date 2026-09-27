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
$query = "SELECT * FROM dl_pdf_history $filter ORDER BY id DESC";
$result = $conn->query($query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-danger-subtle shadow-none me-3">
                        <span class="fas fa-file-pdf text-danger fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-danger fs-10 mb-0">History Log</h6>
                        <h4 class="text-primary fw-bold mb-0">DL PDF <span class="text-danger fw-medium">Downloads</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="dl_pdf.php" class="btn btn-sm btn-danger shadow-none fw-bold"><i class="fas fa-plus me-1"></i> Fetch New DL</a>
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
                        <th>DL Number</th>
                        <th>DOB</th>
                        <th>Date & Time</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="py-2 fw-bold">#<?php echo $row['id']; ?></td>
                                <td><span class="fw-bold text-dark font-monospace text-uppercase"><?php echo htmlspecialchars($row['dl_no']); ?></span></td>
                                <td><span class="badge badge-subtle-secondary fs-10 px-2"><?php echo htmlspecialchars($row['dob']); ?></span></td>
                                <td><span class="text-600"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></span></td>
                                <td class="text-end pe-4">
                                    <a href="<?php echo htmlspecialchars($row['pdf_data']); ?>" download="DL_Card_<?php echo htmlspecialchars($row['dl_no']); ?>.pdf" class="btn btn-falcon-danger btn-sm shadow-none fw-bold px-3">
                                        <i class="fas fa-file-pdf me-1"></i> Download PDF
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="py-4 text-500">No DL records found. Fetch a Driving License first.</td></tr>
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