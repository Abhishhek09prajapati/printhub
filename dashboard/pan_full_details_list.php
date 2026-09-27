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
$query = "SELECT * FROM pan_details_history $filter ORDER BY id DESC";
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
                        <h4 class="text-primary fw-bold mb-0">PAN Details <span class="fw-medium">Records</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="pan_full_details.php" class="btn btn-sm btn-primary shadow-none fw-bold"><i class="fas fa-plus me-1"></i> Fetch New Details</a>
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
                        <th>PAN No.</th>
                        <th class="text-start">Applicant Name</th>
                        <th>DOB / Gender</th>
                        <th>Aadhaar Linked</th>
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
                                    <span class="badge badge-subtle-primary fs-11 px-2 font-monospace"><?php echo htmlspecialchars($row['pan_no']); ?></span>
                                </td>
                                <td class="text-start">
                                    <span class="fw-bold text-dark d-block"><i class="fas fa-user text-primary me-1"></i> <?php echo htmlspecialchars($row['name']); ?></span>
                                    <?php if(!empty($row['father_name']) && $row['father_name'] != 'N/A') { ?>
                                        <small class="text-500">S/O: <?php echo htmlspecialchars($row['father_name']); ?></small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="fw-semi-bold text-dark d-block"><?php echo htmlspecialchars($row['dob']); ?></span>
                                    <span class="badge badge-subtle-secondary"><?php echo htmlspecialchars($row['gender']); ?></span>
                                </td>
                                <td>
                                    <?php if($row['aadhaar_linked'] == 'Yes') { ?>
                                        <span class="badge badge-subtle-success d-block mb-1"><i class="fas fa-check-circle me-1"></i> Linked</span>
                                    <?php } else { ?>
                                        <span class="badge badge-subtle-danger d-block mb-1"><i class="fas fa-times-circle me-1"></i> Not Linked</span>
                                    <?php } ?>
                                    <small class="text-500 font-monospace"><?php echo htmlspecialchars($row['masked_aadhaar']); ?></small>
                                </td>
                                <td class="text-600">
                                    <?php echo date('d M Y', strtotime($row['created_at'])); ?><br>
                                    <small><?php echo date('h:i A', strtotime($row['created_at'])); ?></small>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-falcon-primary btn-sm shadow-none fw-bold px-3" 
                                        data-pan="<?php echo htmlspecialchars($row['pan_no'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-name="<?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-father="<?php echo htmlspecialchars($row['father_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-dob="<?php echo htmlspecialchars($row['dob'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-gender="<?php echo htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-linked="<?php echo htmlspecialchars($row['aadhaar_linked'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-aadhaar="<?php echo htmlspecialchars($row['masked_aadhaar'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-date="<?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>"
                                        onclick="showFullDetails(this)">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="py-4 text-500 fst-italic">No PAN details found. Fetch a record first.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // ★ JAVASCRIPT FUNCTION TO SHOW FULL DETAILS IN A NICE POPUP ★
    function showFullDetails(btn) {
        // Button se saara data nikalna
        let pan = btn.getAttribute('data-pan');
        let name = btn.getAttribute('data-name');
        let father = btn.getAttribute('data-father');
        let dob = btn.getAttribute('data-dob');
        let gender = btn.getAttribute('data-gender');
        let linked = btn.getAttribute('data-linked');
        let aadhaar = btn.getAttribute('data-aadhaar');
        let date = btn.getAttribute('data-date');

        // Status ke hisaab se color set karna
        let linkedHtml = (linked === 'Yes') 
            ? '<span class="text-success fw-bold"><i class="fas fa-check-circle"></i> Yes (Linked)</span>' 
            : '<span class="text-danger fw-bold"><i class="fas fa-times-circle"></i> No (Not Linked)</span>';

        // Table ka HTML design
        let popupHtml = `
            <div class="text-start fs-10 mt-3">
                <table class="table table-bordered table-sm">
                    <tbody>
                        <tr><th class="bg-200" style="width:40%;">PAN Number</th><td class="font-monospace fw-bold text-primary">${pan}</td></tr>
                        <tr><th class="bg-200">Applicant Name</th><td class="fw-bold text-dark">${name}</td></tr>
                        <tr><th class="bg-200">Father's Name</th><td>${father}</td></tr>
                        <tr><th class="bg-200">Date of Birth</th><td>${dob}</td></tr>
                        <tr><th class="bg-200">Gender</th><td>${gender}</td></tr>
                        <tr><th class="bg-200">Aadhaar Status</th><td>${linkedHtml}</td></tr>
                        <tr><th class="bg-200">Masked Aadhaar</th><td class="font-monospace">${aadhaar}</td></tr>
                        <tr><th class="bg-200">Fetched On</th><td>${date}</td></tr>
                    </tbody>
                </table>
            </div>
        `;

        // SweetAlert2 me dikhana
        Swal.fire({
            title: '<h4 class="mb-0 text-primary"><i class="fas fa-id-card me-2"></i>Full PAN Details</h4>',
            html: popupHtml,
            width: '500px',
            confirmButtonText: 'Close',
            customClass: {
                confirmButton: 'btn btn-primary shadow-none px-4'
            },
            buttonsStyling: false
        });
    }
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>