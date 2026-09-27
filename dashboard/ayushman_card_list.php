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
$query = "SELECT * FROM aayushman_card_history $filter ORDER BY id DESC";
$result = $conn->query($query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-images text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">History Log</h6>
                        <h4 class="text-primary fw-bold mb-0">Aayushman Card <span class="text-info fw-medium">Downloads</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="ayushman_card.php" class="btn btn-sm btn-primary shadow-none fw-bold"><i class="fas fa-plus me-1"></i> Fetch New Card</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3 border-0 shadow-sm border-top border-4 border-primary">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 text-center align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">#ID</th>
                        <th>Aadhaar No.</th>
                        <th>State Code</th>
                        <th>Card Number</th>
                        <th>Date & Time</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="py-2 fw-bold">#<?php echo $row['id']; ?></td>
                                <td><span class="fw-bold text-dark font-monospace"><?php echo htmlspecialchars($row['aadhaar_no']); ?></span></td>
                                <td><span class="badge badge-subtle-secondary fs-10 px-2"><?php echo htmlspecialchars($row['state_code']); ?></span></td>
                                <td><span class="text-600 fw-semi-bold"><?php echo htmlspecialchars($row['card_no']); ?></span></td>
                                <td><span class="text-600"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></span></td>
                                <td class="text-end pe-4">
                                    <a href="<?php echo htmlspecialchars($row['card_image']); ?>" download="Aayushman_<?php echo htmlspecialchars($row['aadhaar_no']); ?>.png" class="btn btn-falcon-success btn-sm shadow-none fw-bold px-3">
                                        <i class="fas fa-image me-1"></i> Download Card
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="py-4 text-500">No Aayushman records found. Fetch a card first.</td></tr>
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