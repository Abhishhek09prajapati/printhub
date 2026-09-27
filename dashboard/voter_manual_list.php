<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proxy_voter_pdf'])) {
    
    $server_url = $_POST['target_url'];
    
    unset($_POST['target_url']);
    unset($_POST['proxy_voter_pdf']);

    $ch = curl_init($server_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
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
        
        $print_script = "<script>window.onload = function() { window.print(); }</script>";

        $response = str_ireplace("<head>", "<head>\n" . $base_tag, $response);
        $response = str_ireplace("</body>", $print_script . "\n</body>", $response);
        
        echo $response;
    }
    
    curl_close($ch);
    exit(); 
}

require_once('../titancore/titanconfig.php');

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");
mysqli_query($conn, "SET CHARACTER SET 'utf8mb4'");

$uid = $_SESSION['user_id'];
$u_phone = $_SESSION['phone'] ?? ''; 
$u_name = $_SESSION['user_name'] ?? 'User';
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Retailer';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = mysqli_real_escape_string($conn, $_POST['delete_id']);
    
    $delete_query = "DELETE FROM voterauto1 WHERE (voterautoid='$delete_id' OR srno='$delete_id') AND (userid='$uid' OR userid='$u_phone')";
    
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['swal_msg'] = "Record deleted successfully!";
        $_SESSION['swal_type'] = "success";
    } else {
        $_SESSION['swal_msg'] = "Failed to delete record.";
        $_SESSION['swal_type'] = "error";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$settings_res = $conn->query("SELECT site_name FROM settings LIMIT 1");
$web = $settings_res->fetch_assoc();
$display_name = $web['site_name'] ?? 'TitanApi';

$PRINT_SERVER_URL = "https://titanapi.in/PrintData/aadharpvc/voter_manual_print.php"; 
$hit_res = $conn->query("SELECT server_url FROM titanhit WHERE url_name='voter_print' AND status=1 LIMIT 1");
if ($hit_res && $hit_res->num_rows > 0) {
    $PRINT_SERVER_URL = rtrim($hit_res->fetch_assoc()['server_url'], '/');
    if(strpos($PRINT_SERVER_URL, '.php') === false) { $PRINT_SERVER_URL .= "/voter_manual_print.php"; }
}

require_once('../titancore/titanheader.php');

$query = "SELECT * FROM voterauto1 WHERE userid='$uid' OR userid='$u_phone' ORDER BY srno DESC";
$res = mysqli_query($conn, $query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Welcome back, <?php echo htmlspecialchars($u_name, ENT_QUOTES, 'UTF-8'); ?></h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'); ?> <span class="text-info fw-medium">Voter History</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <a href="voter_manual.php" class="btn btn-sm btn-primary shadow-none fw-semi-bold">
                        <i class="fas fa-plus-circle me-1"></i> New Application
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-light border-bottom">
        <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-id-card me-2 text-primary"></i>All Voter Manual List</h5>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped table-hover fs-10 mb-0 align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3 text-center" style="width: 5%;">SL.</th>
                        <th>Applicant Name</th>
                        <th class="text-center">Voter Number</th>
                        <th class="text-center">Date & Time</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php
                    if($res && mysqli_num_rows($res) > 0){
                        $x = 0;
                        while($data = mysqli_fetch_assoc($res)){
                            $x++;
                            $row_id = $data['srno'] ?? $data['srno'];
                    ?>
                    <tr>
                        <td class="ps-3 text-center fw-semi-bold"><?php echo $x; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-xl me-2">
                                    <div class="avatar-name rounded-circle bg-success-subtle text-success">
                                        <span><?php echo substr(strtoupper($data['votername']), 0, 1); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-1000 fw-bold"><?php echo strtoupper(htmlspecialchars($data['votername'], ENT_QUOTES, 'UTF-8')); ?></h6>
                                    <small class="text-500">Manual Advance</small>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-subtle-primary fs-11"><?php echo strtoupper(htmlspecialchars($data['epicno'], ENT_QUOTES, 'UTF-8')); ?></span>
                        </td>
                        <td class="text-center text-700 fw-semi-bold">
                            <?php echo date('d M Y, h:i A', strtotime($data['createdatetime'])); ?>
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-flex justify-content-end gap-2">
                                
                                <form action="" method="POST" target="_blank" accept-charset="UTF-8" class="m-0 p-0">
                                    <input type="hidden" name="target_url" value="<?php echo htmlspecialchars($PRINT_SERVER_URL, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="proxy_voter_pdf" value="1">
                                    
                                    <input type="hidden" name="srno" value="<?php echo htmlspecialchars($data['srno'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="epicno" value="<?php echo htmlspecialchars($data['epicno'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="votername" value="<?php echo htmlspecialchars($data['votername'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="namelocal" value="<?php echo htmlspecialchars($data['namelocal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="fathername" value="<?php echo htmlspecialchars($data['fathername'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="fathernamelocal" value="<?php echo htmlspecialchars($data['fathernamelocal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="dob" value="<?php echo htmlspecialchars($data['dob'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="dobinlocal" value="<?php echo htmlspecialchars($data['dobinlocal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="gender" value="<?php echo htmlspecialchars($data['gender'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="genderlocal" value="<?php echo htmlspecialchars($data['genderlocal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="fulladdress" value="<?php echo htmlspecialchars($data['fulladdress'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="localaddress" value="<?php echo htmlspecialchars($data['localaddress'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="assconnameno" value="<?php echo htmlspecialchars($data['assconnameno'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="assconnamenolocal" value="<?php echo htmlspecialchars($data['assconnamenolocal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="partno" value="<?php echo htmlspecialchars($data['partno'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="partname" value="<?php echo htmlspecialchars($data['partname'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="partnamelocal" value="<?php echo htmlspecialchars($data['partnamelocal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    
                                    <input type="hidden" name="imageurl" value="<?php echo htmlspecialchars($data['imagepathoriginal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    
                                    <input type="hidden" name="sexlocal" value="<?php echo htmlspecialchars($data['sexlocal'] ?? 'लिंग', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="signlocal" value="<?php echo htmlspecialchars($data['signlocal'] ?? 'निर्वाचक रजिस्ट्रीकरण अधिकारी', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="pata" value="<?php echo htmlspecialchars($data['pata'] ?? 'पता', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="kaname" value="<?php echo htmlspecialchars($data['kaname'] ?? 'का नाम', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="language" value="<?php echo htmlspecialchars($data['locallanguage'] ?? 'HI', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="spousenamelocal" value="<?php echo htmlspecialchars($data['spousenamelocal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="spousename" value="<?php echo htmlspecialchars($data['spousename'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                                    <button type="submit" class="btn btn-falcon-success btn-sm"><i class="fas fa-print"></i> Print</button>
                                </form>
                                
                                <button type="button" class="btn btn-falcon-danger btn-sm" onclick="deleteRecord(<?php echo $row_id; ?>)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>

                            </div>
                        </td>
                    </tr>
                    <?php
                        }
                    } else {
                    ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-500 fs-11 fst-italic">No Voter manual records found.</td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function deleteRecord(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this record!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e63757',
        cancelButtonColor: '#5e6e82', 
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = ''; 
            
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_id';
            input.value = id;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    })
}
</script>

<?php 
if (isset($_SESSION['swal_msg'])): 
?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            title: "<?php echo ucfirst($_SESSION['swal_type']); ?>!",
            text: "<?php echo $_SESSION['swal_msg']; ?>",
            icon: "<?php echo $_SESSION['swal_type']; ?>",
            confirmButtonColor: '#00d27a' 
        });
    });
</script>
<?php 
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_type']);
endif; 
?>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>