<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Auth & Configuration
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once('../titancore/titanconfig.php');

// ========== UTF-8 FIX FUNCTION ==========
function utf8_fix($text) {
    if (empty($text)) return '';
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'auto');
    }
    return $text;
}

// ========== DELETE RECORD FUNCTION ==========
if (isset($_POST['delete_id']) && isset($_POST['delete_action'])) {
    $delete_id = intval($_POST['delete_id']);
    
    // CRITICAL: Check ownership before deleting
    $check_query = "SELECT userid FROM aadharmanual WHERE srno = '$delete_id'";
    $check_res = mysqli_query($conn, $check_query);
    $check_data = mysqli_fetch_assoc($check_res);
    
    if($check_data) {
        $record_owner = $check_data['userid'];
        $current_user = $_SESSION['phone'] ?? $_SESSION['user_id'];
        
        // Only allow deletion if admin OR record owner
        if($_SESSION['role'] == 'admin' || $record_owner == $current_user) {
            $conn->query("DELETE FROM aadharmanual WHERE srno = '$delete_id'");
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?msg=deleted");
    exit();
}

// =====================================================================
// ★ PRINT PROXY LOGIC ★
// =====================================================================
if (isset($_POST['action']) && $_POST['action'] === 'print_card') {
    
    header('Content-Type: text/html; charset=utf-8');
    
    $hit_res = $conn->query("SELECT server_url FROM titanhit WHERE url_name='aadhar_print' AND status=1 LIMIT 1");
    if ($hit_res && $hit_res->num_rows > 0) {
        $PRINT_SERVER_URL = rtrim($hit_res->fetch_assoc()['server_url'], '/');
        if(strpos($PRINT_SERVER_URL, '.php') === false) {
            $PRINT_SERVER_URL .= "/aadharmanual.php";
        }
    } else {
        die("<h3 style='color:red; text-align:center;'>Print Server URL Database (titanhit) me set nahi hai!</h3>");
    }

    $post_data = [];
    foreach ($_POST as $key => $value) {
        if ($key === 'action') {
            $post_data[$key] = $value;
        } else {
            $post_data[$key] = utf8_fix($value);
        }
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $PRINT_SERVER_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Accept-Charset: UTF-8'
    ]);

    $html_response = curl_exec($ch);
    
    if(curl_errno($ch)){
        die("Print Server Connect Error: " . curl_error($ch));
    }
    curl_close($ch);

    $html_response = mb_convert_encoding($html_response, 'UTF-8', 'auto');
    $base_remote_dir = str_replace(basename($PRINT_SERVER_URL), '', $PRINT_SERVER_URL);
    $html_response = str_replace('"images/', '"' . $base_remote_dir . 'images/', $html_response);
    $html_response = str_replace("'images/", "'" . $base_remote_dir . "images/", $html_response);

    echo $html_response;
    exit();
}

// ========== USER SESSION DATA ==========
$uid = $_SESSION['user_id'];
$u_phone = $_SESSION['phone'] ?? $_SESSION['user_id']; // Use phone or user_id as identifier
$u_name = $_SESSION['user_name'] ?? 'User';
$u_role = $_SESSION['role'] ?? 'retailer'; // retailer, distributor, admin
$u_username = $_SESSION['username'] ?? $u_phone;

$settings_res = $conn->query("SELECT site_name FROM settings LIMIT 1");
$web = $settings_res->fetch_assoc();
$display_name = $web['site_name'] ?? 'ApiNexus';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$MAIN_SERVER_URL = $protocol . "://" . $_SERVER['HTTP_HOST'];

require_once('../titancore/titanheader.php');
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Welcome back, <?php echo htmlspecialchars($u_name, ENT_QUOTES, 'UTF-8'); ?></h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'); ?> <span class="text-info fw-medium">Aadhaar History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto p-3">
                    <div class="d-flex gap-2">
                        <a href="aadhaar_manual.php" class="btn btn-sm btn-primary shadow-none fw-semi-bold">
                            <i class="fas fa-plus-circle me-1"></i> New Application
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i> Record deleted successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-list-alt me-2 text-primary"></i>Aadhaar Manual Records</h5>
        <span class="badge bg-<?php echo ($u_role == 'admin') ? 'danger' : 'info'; ?>">
            <?php 
            if($u_role == 'admin') {
                echo "Admin View - All Records";
            } else {
                echo "My Records Only - " . strtoupper($u_role);
            }
            ?>
        </span>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped table-hover fs-10 mb-0 align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3 text-center" style="width: 5%;">#</th>
                        <th>Name</th>
                        <th class="text-center">Aadhaar Number</th>
                        <th class="text-center">Date</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $conn->set_charset("utf8mb4");
                    
                    // ========== CRITICAL FIX: Sirf current user ke records show karo ==========
                    // Check konsa column use ho raha hai user identify karne ke liye
                    $sample_query = "SELECT userid FROM aadharmanual LIMIT 1";
                    $sample_res = mysqli_query($conn, $sample_query);
                    
                    // Current user identifier (phone number ya user_id)
                    $current_user_id = $u_phone; // Phone number se match karo
                    
                    // Agar admin hai toh saare records, warna sirf apne records
                    if($u_role == 'admin') {
                        // Admin: All records (can see everything)
                        $query = "SELECT * FROM aadharmanual ORDER BY srno DESC";
                        $filter_note = "Admin View - Showing all records";
                    } else {
                        // Normal User (Retailer/Distributor): Sirf apne records
                        // CRITICAL: Exact match karo userid ke saath
                        $query = "SELECT * FROM aadharmanual WHERE userid = '$current_user_id' ORDER BY srno DESC";
                        $filter_note = "Showing only records created by you (ID: $current_user_id)";
                    }
                    
                    $res = mysqli_query($conn, $query); 
                    
                    if($res && mysqli_num_rows($res) > 0){
                        $x = 0;
                        while($data = mysqli_fetch_assoc($res)){
                            $x++;
                            $photo_url = $MAIN_SERVER_URL . "/dashboard/" . $data['imagepathoriginal'];
                            
                            // Fix encoding
                            $aadhar_name = utf8_fix($data['aadharname']);
                            $local_name = utf8_fix($data['localname']);
                            $local_address = utf8_fix($data['localaddress']);
                            $dobin_local = utf8_fix($data['dobinlocal']);
                            $sexin_local = utf8_fix($data['sexinlocal']);
                            
                            // Record owner info
                            $record_owner = $data['userid'];
                    ?>
                    <tr>
                        <td class="ps-3 text-center fw-semi-bold"><?php echo $x; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-xl me-2">
                                    <div class="avatar-name rounded-circle bg-primary-subtle text-primary">
                                        <span><?php echo substr(strtoupper($aadhar_name), 0, 1); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-1000 fw-bold"><?php echo strtoupper(htmlspecialchars($aadhar_name, ENT_QUOTES, 'UTF-8')); ?></h6>
                                    <small class="text-500"><?php echo htmlspecialchars($local_name, ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php if($u_role == 'admin'): ?>
                                    <small class="text-info d-block">Created by: <?php echo htmlspecialchars($record_owner, ENT_QUOTES, 'UTF-8'); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-subtle-secondary fs-11">
                                <?php echo htmlspecialchars($data['originalaadharno'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td class="text-center text-700 fw-semi-bold">
                            <?php echo date('d M Y, h:i A', strtotime($data['createdatetime'])); ?>
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-flex justify-content-end gap-2">
                                <!-- Print Form -->
                                <form action="" method="POST" target="_blank" class="m-0 p-0" accept-charset="UTF-8">
                                    <input type="hidden" name="action" value="print_card">
                                    <input type="hidden" name="aadharno" value="<?php echo htmlspecialchars($data['aadharno'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="aadharname" value="<?php echo htmlspecialchars($aadhar_name, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="localname" value="<?php echo htmlspecialchars($local_name, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="fathername" value="<?php echo htmlspecialchars($data['fathername'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="dob" value="<?php echo htmlspecialchars($data['dob'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="dobinlocal" value="<?php echo htmlspecialchars($dobin_local, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="gender" value="<?php echo htmlspecialchars($data['gender'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="sexinlocal" value="<?php echo htmlspecialchars($sexin_local, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="fulladdress" value="<?php echo htmlspecialchars($data['fulladdress'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="localaddress" value="<?php echo htmlspecialchars($local_address, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="imageurl" value="<?php echo htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="pincode" value="<?php echo htmlspecialchars($data['pincode'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="srno" value="<?php echo htmlspecialchars($data['srno'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="originalaadharno" value="<?php echo htmlspecialchars($data['originalaadharno'], ENT_QUOTES, 'UTF-8'); ?>">
                                    
                                    <button type="submit" class="btn btn-falcon-success btn-sm" title="Print Card">
                                        <i class="fas fa-print me-1"></i> Print
                                    </button>
                                </form>
                                
                                <!-- Delete Form - Sirf admin ya record owner delete kar sakta hai -->
                                <?php if($u_role == 'admin' || $record_owner == $current_user_id): ?>
                                <form action="" method="POST" class="m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $data['srno']; ?>">
                                    <input type="hidden" name="delete_action" value="1">
                                    <button type="submit" class="btn btn-falcon-danger btn-sm" title="Delete Record">
                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php
                        }
                    } else {
                        // Debug info for non-admin users
                        $debug_info = "";
                        if($u_role != 'admin') {
                            $debug_info = "<br><small class='text-muted'>Your user ID: " . htmlspecialchars($current_user_id) . "<br>Check: aadharmanual table should have userid column with matching value</small>";
                        }
                    ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-500 fs-11 fst-italic">
                            <img src="../assets/img/illustrations/empty.png" alt="No Data" width="80" class="mb-2 opacity-50"><br>
                            No Aadhaar manual records found.<br>
                            <small><?php echo $filter_note ?? 'Click "New Application" to create your first Aadhaar card.'; ?></small>
                            <?php echo $debug_info; ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .btn-falcon-success, .btn-falcon-danger {
        padding: 0.25rem 0.6rem;
        font-size: 0.75rem;
    }
    .avatar-xl {
        width: 2.5rem;
        height: 2.5rem;
    }
    .avatar-name {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        font-weight: bold;
        font-size: 1rem;
    }
    .badge-subtle-secondary {
        background: #e9ecef;
        color: #495057;
        padding: 0.35rem 0.65rem;
        font-weight: 500;
    }
</style>

<script>
setTimeout(function() {
    let alert = document.querySelector('.alert');
    if(alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.remove();
        }, 500);
    }
}, 3000);
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>