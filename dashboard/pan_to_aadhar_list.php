<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");

require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Retailer';

$filter = ($utype == 'TitanAdmin') ? "" : "WHERE user_id = '$uid'";
$query = "SELECT * FROM pan_to_aadhar_history $filter ORDER BY id DESC";
$result = $conn->query($query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-list text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">History Log</h6>
                        <h4 class="text-primary fw-bold mb-0">PAN To Aadhaar <span class="fw-medium">Records</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="pan_to_aadhar.php" class="btn btn-sm btn-primary shadow-none fw-bold"><i class="fas fa-search me-1"></i> New Search</a>
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
                        <th>PAN Number</th>
                        <th>Linked Aadhaar (Full)</th>
                        <th>Profile Info</th>
                        <th class="text-end pe-4">Date & Time</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            // ★ FIX: Ab masking hata di gayi hai, full number dikhega ★
                            $full_aadhar = htmlspecialchars($row['aadhaar_no']);
                        ?>
                            <tr>
                                <td class="py-3 font-monospace fw-bold text-dark">#<?php echo $row['id']; ?></td>
                                <td>
                                    <span class="badge badge-subtle-primary fs-11 px-3 py-2 font-monospace border border-primary"><i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($row['pan_no']); ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-subtle-success fs-11 px-3 py-2 font-monospace border border-success"><i class="fas fa-fingerprint me-1"></i> <?php echo $full_aadhar; ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($row['name']); ?></span>
                                    <small class="text-500">DOB: <?php echo htmlspecialchars($row['dob']); ?> | Gen: <?php echo htmlspecialchars($row['gender']); ?></small>
                                </td>
                                <td class="text-end pe-4 text-600">
                                    <?php echo date('d M Y', strtotime($row['created_at'])); ?><br>
                                    <small><?php echo date('h:i A', strtotime($row['created_at'])); ?></small>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-falcon-primary btn-sm shadow-none fw-bold px-3" 
                                            onclick="showFullData('<?php echo htmlspecialchars($row['pan_no']); ?>', '<?php echo $full_aadhar; ?>', '<?php echo htmlspecialchars($row['name']); ?>', '<?php echo htmlspecialchars($row['dob']); ?>', '<?php echo htmlspecialchars($row['gender']); ?>')">
                                        <i class="fas fa-eye me-1"></i> View Full
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="py-4 text-500 fst-italic">No records found. Search a PAN Number first.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function showFullData(pan, aadhar, name, dob, gender) {
        let popupHtml = `
            <div class="text-start fs-10 mt-3">
                <table class="table table-bordered table-sm">
                    <tbody>
                        <tr><th class="bg-200" style="width:40%;">PAN Number</th><td class="font-monospace fw-bold text-primary">${pan}</td></tr>
                        <tr><th class="bg-200 text-success">Linked Aadhaar</th><td class="font-monospace fw-bold fs-6 text-success">${aadhar}</td></tr>
                        <tr><th class="bg-200">Name</th><td class="fw-bold text-dark">${name}</td></tr>
                        <tr><th class="bg-200">Date of Birth</th><td>${dob}</td></tr>
                        <tr><th class="bg-200">Gender</th><td>${gender}</td></tr>
                    </tbody>
                </table>
            </div>
        `;

        Swal.fire({
            title: '<h5 class="mb-0 text-primary"><i class="fas fa-user-check me-2"></i>Full Mapped Details</h5>',
            html: popupHtml,
            width: '500px',
            confirmButtonText: 'Close',
            customClass: { confirmButton: 'btn btn-primary shadow-none px-4' },
            buttonsStyling: false
        });
    }
</script>

<?php require_once('../titancore/TitanFooter.php'); ob_end_flush(); ?>