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
$swal_msg = "";

// API Config
$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = rtrim($api_settings['titanurl'] ?? 'https://titanapi.in', '/');
$api_key = $api_settings['titan_api_key'] ?? '';

// LIVE STATUS CHECK LOGIC
if(isset($_POST['check_status_btn'])){
    $rec_id = mysqli_real_escape_string($conn, $_POST['record_id']);
    $aadhaar_chk = trim($_POST['aadhaar_no']);
    
    // Construct the endpoint exactly as required
    $endpoint = $TitanApi_Url . "/api/v1/AadharToPanReqCheck.php?api_key=" . urlencode($api_key) . "&aadhaar_no=" . urlencode($aadhaar_chk);
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($res, true);
    
    if(isset($data['status']) && strtolower($data['status']) === 'success'){
        
        $curr_status = strtolower($data['data']['current_status'] ?? 'pending');
        
        if($curr_status === 'success' && !empty($data['data']['pan_no'])) {
            $pan_no = mysqli_real_escape_string($conn, $data['data']['pan_no']);
            // Update database with success and PAN number
            $conn->query("UPDATE aadhar_to_pan_req_history SET status='success', pan_no='$pan_no' WHERE id='$rec_id'");
            
            $swal_msg = "Swal.fire({
                title: 'Completed!',
                text: 'PAN Details found successfully: " . htmlspecialchars($pan_no) . "',
                icon: 'success'
            }).then(() => { window.location.href = window.location.href; });";
            
        } elseif($curr_status === 'failed' || $curr_status === 'rejected') {
            // Update database with rejected status
            $conn->query("UPDATE aadhar_to_pan_req_history SET status='rejected' WHERE id='$rec_id'");
            
            $swal_msg = "Swal.fire({
                title: 'Rejected',
                text: 'This request was rejected by the server.',
                icon: 'error'
            }).then(() => { window.location.href = window.location.href; });";
            
        } else {
            // Still pending
            $swal_msg = "Swal.fire('Still Pending', 'The request is still in process. Please check later.', 'info');";
        }
    } else {
        $error_msg = $data['message'] ?? 'Could not check status right now.';
        $swal_msg = "Swal.fire('API Error', '" . addslashes($error_msg) . "', 'error');";
    }
}

// Fetch Records
$filter = ($utype == 'TitanAdmin') ? "" : "WHERE user_id = '$uid'";
$query = "SELECT * FROM aadhar_to_pan_req_history $filter ORDER BY id DESC";
$result = $conn->query($query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-tasks text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">History Log</h6>
                        <h4 class="text-primary fw-bold mb-0">Aadhaar To PAN <span class="fw-medium">Tracker</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="aadhar_to_pan.php" class="btn btn-sm btn-primary shadow-none fw-bold"><i class="fas fa-plus me-1"></i> New Request</a>
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
                        <th class="py-2">App No.</th>
                        <th>Aadhaar No.</th>
                        <th>PAN Status</th>
                        <th>Date & Time</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="py-3 font-monospace fw-bold text-dark">
                                    <?php echo htmlspecialchars($row['application_no'] ?? ''); ?>
                                </td>
                                <td>
                                    <span class="badge badge-subtle-secondary fs-11 px-3 py-2 font-monospace">
                                        <?php echo htmlspecialchars($row['aadhaar_no'] ?? ''); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if(strtolower($row['status']) === 'success' && !empty($row['pan_no'])): ?>
                                        <span class="badge badge-subtle-success fs-11 px-3 py-2 font-monospace border border-success">
                                            <i class="fas fa-check-circle me-1"></i> 
                                            <?php echo htmlspecialchars($row['pan_no']); ?>
                                        </span>
                                    <?php elseif(strtolower($row['status']) === 'success' && empty($row['pan_no'])): ?>
                                        <span class="badge badge-subtle-warning fs-11 px-3 py-2 border border-warning text-dark">
                                            <i class="fas fa-exclamation-triangle me-1"></i> PAN Missing
                                        </span>
                                    <?php elseif(strtolower($row['status']) === 'rejected' || strtolower($row['status']) === 'failed'): ?>
                                        <span class="badge badge-subtle-danger fs-11 px-3 py-2 border border-danger">
                                            <i class="fas fa-times-circle me-1"></i> Rejected
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-subtle-warning fs-11 px-3 py-2 font-monospace border border-warning">
                                            <i class="fas fa-clock me-1"></i> <?php echo htmlspecialchars($row['mask_pan'] ?? 'Pending'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-600">
                                    <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php 
                                    // Allow checking status if it's pending OR if it's success but the PAN is missing from the DB
                                    if(strtolower($row['status']) === 'pending' || (strtolower($row['status']) === 'success' && empty($row['pan_no']))): 
                                    ?>
                                        <form method="POST" style="display:inline;" onsubmit="showLoader('Checking Server Status...')">
                                            <input type="hidden" name="record_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="aadhaar_no" value="<?php echo htmlspecialchars($row['aadhaar_no']); ?>">
                                            <button type="submit" name="check_status_btn" class="btn btn-falcon-warning btn-sm shadow-none fw-bold px-3">
                                                <i class="fas fa-sync-alt me-1"></i> Check Status
                                            </button>
                                        </form>
                                    <?php elseif(strtolower($row['status']) === 'success' && !empty($row['pan_no'])): ?>
                                        <button class="btn btn-falcon-success btn-sm shadow-none fw-bold px-3" disabled>
                                            <i class="fas fa-check"></i> Completed
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-falcon-danger btn-sm shadow-none fw-bold px-3" disabled>
                                            <i class="fas fa-times"></i> Failed
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="py-4 text-500 fst-italic">No records found. Create a request first.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_msg; ?>
    function showLoader(txt) {
        Swal.fire({
            title: txt,
            html: 'Connecting to Master API...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>