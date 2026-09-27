<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proxy_print_json'])) {
    
    $server_url = $_POST['target_url'];
    $json_data = $_POST['proxy_print_json'];

    $ch = curl_init($server_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['print_json' => $json_data]));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    
    if(curl_errno($ch)){
        echo "<div style='text-align:center; margin-top:50px; font-family:sans-serif; color:red;'>";
        echo "<h3>Print Server Offline or Blocked!</h3>";
        echo "<p>Error: " . curl_error($ch) . "</p>";
        echo "</div>";
    } else {
        $parsed_url = parse_url($server_url);
        $path_dir = dirname($parsed_url['path']);
        $base_path = $parsed_url['scheme'] . '://' . $parsed_url['host'] . ($path_dir === '\\' ? '/' : $path_dir) . '/';
        
        $base_tag = "<base href='" . $base_path . "'>";
        
        $response = str_ireplace("<head>", "<head>\n" . $base_tag, $response);
        
        echo $response;
    }
    
    curl_close($ch);
    exit();
}

require_once('../titancore/titanconfig.php');
require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$u_phone = $_SESSION['phone'] ?? '';
$u_name = $_SESSION['user_name'] ?? 'User';

$print_url = "#"; 
$url_res = $conn->query("SELECT server_url FROM titanhit WHERE url_name='pan_manual' AND status='1'");
if ($url_res && $url_res->num_rows > 0) {
    $print_url = $url_res->fetch_assoc()['server_url'];
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-info-subtle shadow-none me-3">
                        <span class="fas fa-list text-info fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Welcome, <?php echo htmlspecialchars($u_name); ?></h6>
                        <h4 class="text-primary fw-bold mb-0">PAN Manual <span class="text-info fw-medium">Records</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="pan_manual.php" class="btn btn-primary btn-sm shadow-sm fw-bold">
                        <i class="fas fa-plus me-1"></i> Apply New PAN
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-4 border-info">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-history me-2 text-info"></i>Your Print History</h5>
        <?php if($print_url == "#") { ?>
            <span class="badge badge-subtle-danger fs-10"><i class="fas fa-exclamation-triangle me-1"></i> Print Server Offline</span>
        <?php } else { ?>
            <span class="badge badge-subtle-success fs-10"><i class="fas fa-check-circle me-1"></i> Print Server Active</span>
        <?php } ?>
    </div>
    <div class="card-body p-0 bg-white">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped table-hover fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3 py-3">Date & Time</th>
                        <th>Order ID</th>
                        <th>Applicant Name</th>
                        <th>PAN Number</th>
                        <th>Amount Cut</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $list_res = $conn->query("SELECT * FROM pantitan_records WHERE (userid='$uid' OR userid='$u_phone') AND service_type='PAN MANUAL' ORDER BY id DESC");
                    
                    if ($list_res && $list_res->num_rows > 0) {
                        while ($row = $list_res->fetch_assoc()) {
                    ?>
                    <tr>
                        <td class="ps-3 py-3 text-600 align-middle">
                            <?php echo date('d M, Y', strtotime($row['created_at'])); ?><br>
                            <small class="text-500"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small>
                        </td>
                        <td class="fw-bold font-monospace text-primary align-middle"><?php echo $row['order_id']; ?></td>
                        <td class="fw-semi-bold text-dark align-middle"><?php echo htmlspecialchars($row['name']); ?></td>
                        <td class="fw-bold align-middle">
                            <span class="badge badge-subtle-secondary"><?php echo htmlspecialchars($row['pan_number']); ?></span>
                        </td>
                        <td class="text-danger fw-bold align-middle">₹<?php echo $row['amount_cut']; ?></td>
                        <td class="text-end pe-3 align-middle">
                            
                            <?php if ($print_url != "#") { ?>
                            
                            <form action="" method="POST" target="_blank" class="m-0 p-0">
                                <input type="hidden" name="target_url" value="<?php echo htmlspecialchars($print_url, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="proxy_print_json" value="<?php echo htmlspecialchars($row['print_data'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-sm btn-info shadow-sm fw-bold px-3">
                                    <i class="fas fa-print me-1"></i> Print PDF
                                </button>
                            </form>

                            <?php } else { ?>
                                <button class="btn btn-sm btn-secondary fw-bold px-3" disabled>Offline</button>
                            <?php } ?>

                        </td>
                    </tr>
                    <?php } } else { ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-500 fst-italic">
                            <div class="fs-1 mb-2"><i class="fas fa-box-open"></i></div>
                            No PAN records found. Click "Apply New PAN" to get started!
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>