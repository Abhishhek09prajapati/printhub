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
$query = "SELECT * FROM aadhar_to_name_history $filter ORDER BY id DESC";
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
                        <h4 class="text-primary fw-bold mb-0">Aadhar To Name <span class="text-success fw-medium">Records</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <a href="aadhaar_to_name.php" class="btn btn-sm btn-success shadow-none fw-bold"><i class="fas fa-search me-1"></i> New Search</a>
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
                        <th>Full Name</th>
                        <th>Local Name</th>
                        <th>Mobile</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="py-2 fw-bold">#<?php echo $row['id']; ?></td>
                                <td><span class="fw-semi-bold text-600"><?php echo htmlspecialchars($row['aadhar_no']); ?></span></td>
                                <td><span class="fw-bold text-primary"><?php echo htmlspecialchars($row['name']); ?></span></td>
                                <td><span class="badge badge-subtle-info fs-10 px-2 py-1"><?php echo htmlspecialchars($row['local_name']); ?></span></td>
                                <td><span class="text-700 fw-semi-bold"><i class="fas fa-phone-alt fs-11 me-1"></i> <?php echo htmlspecialchars($row['mobile']); ?></span></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="py-4 text-500">No records found. Try fetching a name first.</td></tr>
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