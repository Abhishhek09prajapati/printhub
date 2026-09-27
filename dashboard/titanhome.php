<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); 
    exit();
}

header('Content-Type: text/html; charset=utf-8');

$uid = $_SESSION['user_id'];
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? ''; 
$u_name = $_SESSION['user_name'] ?? 'User';
$u_phone = $_SESSION['phone'] ?? ''; 

$timeout_duration = 1800; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php?reason=timeout");
    exit();
}
$_SESSION['last_activity'] = time();

require_once('../titancore/titanconfig.php');
require_once('../titancore/titanheader.php');

$uid = mysqli_real_escape_string($conn, $uid);
$u_phone = mysqli_real_escape_string($conn, $u_phone);

$site_query = $conn->query("SELECT site_name, version FROM settings WHERE id = 1");
$site_data = $site_query ? $site_query->fetch_assoc() : [];
$display_name = $site_data['site_name'] ?? 'ApiNexus';
$current_version = $site_data['version'] ?? '1.0.0';

$service_status = [];
$status_query = $conn->query("SELECT service_name, status FROM pricing");
if ($status_query) {
    while ($row = $status_query->fetch_assoc()) {
        $service_status[$row['service_name']] = $row['status'];
    }
}

$filter = ($utype == 'TitanAdmin') ? "" : " AND referred_by = '$uid'";
$verify_filter = ($utype == 'TitanAdmin') ? "" : "WHERE user_id = '$uid'";

$q = $conn->query("SELECT COUNT(*) as total FROM users WHERE status = 'Active' $filter");
$total_active = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM users WHERE status = 'Inactive' $filter");
$total_inactive = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'Retailer' $filter");
$total_retailer = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'Distributor' $filter");
$total_distributor = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'Head Branch' $filter");
$total_super = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$wallet_check = $conn->query("SELECT wallet FROM users WHERE id = '$uid'"); 
$user_wallet_data = $wallet_check ? $wallet_check->fetch_assoc() : [];
$total_wallet = $user_wallet_data['wallet'] ?? 0;

$q = $conn->query("SELECT COUNT(*) as total FROM aadhar_verify_history $verify_filter");
$total_aadhar_verify = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM aadharadvance $verify_filter");
$total_aadhar_advance = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM aadhar_to_pan_history WHERE user_id = '$uid'");
$total_mask_pan = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM aadhar_to_name_history WHERE user_id = '$uid'");
$total_aadhar_name = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM voterauto1 WHERE userid = '$uid' OR userid = '$u_phone'");
$total_voter_manual = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM pantitan_records WHERE userid = '$uid' OR userid = '$u_phone'");
$total_pan_manual = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM farmer_card_history WHERE user_id = '$uid'");
$total_farmer_card = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM npci_status_history WHERE user_id = '$uid'");
$total_npci_status = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM panadvance_history WHERE userid = '$uid' OR userid = '$u_phone'");
$total_pan_advance = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM dl_pdf_history WHERE user_id = '$uid'");
$total_dl_pdf = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM rc_pro_history WHERE user_id = '$uid'");
$total_rc_pro = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(*) as total FROM aayushman_card_history WHERE user_id = '$uid'");
$total_aayushman = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(srno) as total FROM aadharmanual WHERE userid = '$uid'");
$total_aadhar_manual = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(srno) as total FROM voteradvance_history WHERE userid = '$uid' OR userid = '$u_phone'");
$total_voter_advance = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(id) as total FROM ration_pdf_history WHERE user_id = '$uid'");
$total_ration_pdf = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(id) as total FROM aadhar_ration_pdf_history WHERE user_id = '$uid'");
$total_aadhar_ration = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(id) as total FROM ll_pdf_history WHERE user_id = '$uid'");
$total_ll_pdf = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(id) as total FROM pan_details_history WHERE user_id = '$uid'");
$total_pan_details = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(id) as total FROM rc_to_mobile_history WHERE user_id = '$uid'");
$total_rc_mobile = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(id) as total FROM up_ration_to_uid_history WHERE user_id = '$uid'");
$total_upration_uid = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(id) as total FROM voter_mobile_link_history WHERE user_id = '$uid'");
$total_voter_link = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(id) as total FROM voter_pdf_history WHERE user_id = '$uid'");
$total_voter_pdf = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(id) as total FROM aadhar_to_pan_req_history WHERE user_id = '$uid'");
$total_aadhar_pan = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(id) as total FROM pan_to_aadhar_history WHERE user_id = '$uid'");
$total_pan_aadhar = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;

$q = $conn->query("SELECT COUNT(id) as total FROM aadhar_details_history WHERE user_id = '$uid'");
$total_aadhar_details = $q ? ($q->fetch_assoc()['total'] ?? 0) : 0;
?>


<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Welcome back, <?php echo htmlspecialchars($u_name); ?></h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($display_name); ?> <span class="text-info fw-medium">CRM</span></h4>
                    </div>
                    <img class="ms-n4 d-md-none d-lg-block" src="../assets/img/illustrations/crm-line-chart.png" alt="" width="150" />
                </div>
                <div class="col-md-auto p-3">
                    <div class="row align-items-center g-3">
                        <div class="col-auto"><h6 class="text-700 mb-0">Showing Data For: </h6></div>
                        <div class="col-md-auto">
                            <div class="form-control form-control-sm d-flex align-items-center bg-white border-200" style="min-width: 140px;">
                                <span class="fas fa-calendar-day text-primary me-2"></span>
                                <span class="fw-bold text-primary"><?php echo date('d M, Y'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$msg_query = $conn->query("SELECT running_msg FROM system_message WHERE id = 1");
$msg_data = $msg_query ? $msg_query->fetch_assoc() : [];
$running_text = $msg_data['running_msg'] ?? 'Welcome to our portal! All services are running smoothly.';
?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card bg-body-tertiary dark__bg-opacity-50">
            <div class="card-body p-3">
                <p class="fs-10 mb-0 d-flex align-items-center">
                    <span class="fas fa-bullhorn text-danger me-2"></span>
                    <marquee behavior="scroll" direction="left" onmouseover="this.stop();" onmouseout="this.start();" class="text-danger fw-bold m-0">
                        <?php echo htmlspecialchars($running_text, ENT_QUOTES, 'UTF-8'); ?>
                    </marquee>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="row flex-between-center">
                    <div class="col">
                        <h6 class="mb-2 text-900">My Wallet</h6>
                        <span class="badge rounded-pill badge-subtle-primary"><span class="fas fa-wallet"></span> Balance</span>
                    </div>
                    <div class="col-auto">
                       <h4 class="fs-6 fw-normal text-primary mb-0">&#8377;<?php echo number_format($total_wallet, 2); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if($utype !== 'Retailer'): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="row flex-between-center">
                    <div class="col">
                        <h6 class="mb-2 text-900">Active Users</h6>
                        <h4 class="fs-6 fw-normal text-700 mb-0"><?php echo number_format($total_active); ?></h4>
                    </div>
                    <div class="col-auto">
                        <div style="height: 50px; min-width: 80px;" data-echarts='{"xAxis":{"show":false,"boundaryGap":false},"series":[{"data":[3,7,6,8,5,12,11],"type":"line","symbol":"none"}],"grid":{"right":"0px","left":"0px","bottom":"0px","top":"0px"}}'></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="row flex-between-center">
                    <div class="col">
                        <h6 class="mb-2 text-900">Inactive User</h6>
                        <span class="badge rounded-pill badge-subtle-danger"><span class="fas fa-user-slash"></span> Total: <?php echo $total_inactive; ?></span>
                    </div>
                    <div class="col-auto text-end">
                        <h4 class="fs-6 fw-normal text-danger mb-0"><?php echo number_format($total_inactive); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="row flex-between-center">
                    <div class="col">
                        <h6 class="mb-2 text-900">Total Retailer</h6>
                        <span class="badge rounded-pill badge-subtle-primary"><span class="fas fa-caret-up"></span> Live Now</span>
                    </div>
                    <div class="col-auto">
                        <h4 class="fs-6 fw-normal text-primary" data-countup='{"endValue":<?php echo $total_retailer; ?>}'>0</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($utype == 'TitanAdmin' || $utype == 'Head Branch'): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="row flex-between-center">
                    <div class="col">
                        <h6 class="mb-2 text-900">Total Distributor</h6>
                        <h4 class="fs-6 fw-normal text-700 mb-0"><?php echo number_format($total_distributor); ?></h4>
                    </div>
                    <div class="col-auto">
                        <div style="height: 50px; min-width: 80px;" data-echarts='{"xAxis":{"show":false,"boundaryGap":false},"series":[{"data":[5,8,6,10,7,15,12],"type":"line","symbol":"none"}],"grid":{"right":"0px","left":"0px","bottom":"0px","top":"0px"}}'></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($utype == 'TitanAdmin'): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="row flex-between-center">
                    <div class="col">
                        <h6 class="mb-2 text-900">Super Distributor</h6>
                        <span class="badge rounded-pill badge-subtle-success"><span class="fas fa-shield-alt"></span> Verified</span>
                    </div>
                    <div class="col-auto">
                        <h4 class="fs-6 fw-normal text-700 mb-0"><?php echo number_format($total_super); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>


<div class="row g-3 mb-3">
    
    <?php if(!isset($service_status['aadhar_advance']) || $service_status['aadhar_advance'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-info fs-11">VERIFY <span class="fas fa-check-circle ms-1"></span></span>
                </div>
        <div class="row align-items-center g-0">
    <div class="col-7">
        <h6 class="text-700 mb-1">AADHAR ADVANCE</h6>
        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary">
            <?php echo number_format($total_aadhar_advance); ?>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-primary px-3 shadow-none" href="Aadhar_TitanBio.php">Print</a>
            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="aadhar_advance_list.php">List</a>
        </div>
    </div>

                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/aadhaar-card.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if(!isset($service_status['aadhar_verify']) || $service_status['aadhar_verify'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-info fs-11">VERIFY <span class="fas fa-check-circle ms-1"></span></span>
                </div>
        <div class="row align-items-center g-0">
    <div class="col-7">
        <h6 class="text-700 mb-1">AADHAR VERIFY</h6>
        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary">
            <?php echo number_format($total_aadhar_verify); ?>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-primary px-3 shadow-none" href="aadhaar_verify.php">Verify</a>
            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="aadhaar_verify_list.php">List</a>
        </div>
    </div>

                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/aadhaar-card.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['aadhar_mask']) || $service_status['aadhar_mask'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-secondary fs-11">MASK <span class="fas fa-mask ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">PAN MASK</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_mask_pan); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="aadhaar_mask.php">Mask</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="aadhaar_mask_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/pan-card (1).png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['aadhar_to_name']) || $service_status['aadhar_to_name'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-primary fs-11">NAME <span class="fas fa-user ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">AADHAR TO NAME</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_aadhar_name); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="aadhaar_to_name.php">Search</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="aadhaar_to_name_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/aadhaar-card.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['voter_manual_titan']) || $service_status['voter_manual_titan'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-warning fs-11">MANUAL <span class="fas fa-edit ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">VOTER MANUAL</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_voter_manual); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="voter_manual.php">Print</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="voter_manual_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/pvc-voter-id-card.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['pan_manual']) || $service_status['pan_manual'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-warning fs-11">NEW <span class="fas fa-star ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">PAN MANUAL</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_pan_manual); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="pan_manual.php">Print</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="pan_manual_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/02-07-WedSub-Images-2-1024x577-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['find_farmer']) || $service_status['find_farmer'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-3.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-success fs-11">FARMER <span class="fas fa-tractor ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">FARMER PDF</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_farmer_card); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="FarmerPdf.php">Search</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="FarmerPdf_List.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/images__1_-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['npci_status']) || $service_status['npci_status'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-primary fs-11">NPCI <span class="fas fa-credit-card ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">NPCI STATUS</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_npci_status); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="npci_status.php">Check</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="npci_status_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/NPCI-Logo-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['pan_advance']) || $service_status['pan_advance'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-danger fs-11">ADVANCE <span class="fas fa-bolt ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">PAN CARD ADVANCE PRINT</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_pan_advance); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="pan_advance_print.php">Print</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="pan_advance_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/pan-card (1).png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['dl']) || $service_status['dl'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-success fs-11">FAST <span class="fas fa-bolt ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">DL PRINT</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_dl_pdf); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="DL_Card.php">Print</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="dl_pdf_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/WhatsApp-Image-2022-11-12-at-5.02.59-PM-3-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['rc_pdf']) || $service_status['rc_pdf'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-success fs-11">FAST <span class="fas fa-bolt ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">RC PRINT</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_rc_pro); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="rc_pro.php">Print</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="rc_pro_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/61ZpM4H0C8L._AC_UF1000_1000_QL80_-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['pmjay_print']) || $service_status['pmjay_print'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-success fs-11">FAST <span class="fas fa-bolt ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">PMJAY PRINT</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_aayushman); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="ayushman_card.php">Print</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="ayushman_card_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/images__2_-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['aadhar_manual_fee']) || $service_status['aadhar_manual_fee'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-3.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-info fs-11">LIVE <span class="fas fa-check-circle ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">AADHAR MANUAL</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_aadhar_manual); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="aadhaar_manual.php">Print</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="aadharmanual_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/95fc71dcfe471ef8e7b8073d40016b13b733be77fab5b796d21c4c7223017412-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['voter_advance']) || $service_status['voter_advance'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-danger fs-11">FAST <span class="fas fa-bolt ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">VOTER ADVANCE</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_voter_advance); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="voter_advance.php">Print</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="voter_advance_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/What-is-Voter-ID-Card_Inside-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['ration_card']) || $service_status['ration_card'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-primary fs-11">Ration <span class="fas fa-shopping-basket ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">RATION CARD</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_ration_pdf); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="ration_card.php">Print</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="ration_pdf_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/100.0.z5d9f6duly--removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['aadhaar_to_ration_pdf']) || $service_status['aadhaar_to_ration_pdf'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-info fs-11">PDF <span class="fas fa-file-pdf ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">AADHAR TO RATION PDF</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_aadhar_ration); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="aadhar_ration_pdf.php">Generate</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="aadhar_ration_pdf_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/Smart-ration-card-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['learning_pdf']) || $service_status['learning_pdf'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-info fs-11">LEARN <span class="fas fa-graduation-cap ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">LEARNING PDF</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_ll_pdf); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="ll_pdf.php">View</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="ll_pdf_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/ChatGPT_Image_May_12__2026__05_27_00_AM-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['pan_find']) || $service_status['pan_find'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-3.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-warning fs-11">PAN <span class="fas fa-id-card ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">PAN TO FULL DETAILS</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_pan_details); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="pan_full_details.php">Search</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="pan_full_details_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/pan-card (1).png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['rc_to_mobile']) || $service_status['rc_to_mobile'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-success fs-11">RC <span class="fas fa-car ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">RC TO MOBILE</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_rc_mobile); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="rc_to_mobile.php">Search</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="rc_to_mobile_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/61ZpM4H0C8L._AC_UF1000_1000_QL80_-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['up_ration_to_aadhaar']) || $service_status['up_ration_to_aadhaar'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-info fs-11">UP <span class="fas fa-map-marker-alt ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">ONLY UP RATION TO AADHAR</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_upration_uid); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="up_ration_to_uid.php">Search</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="up_ration_to_uid_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/100.0.z5d9f6duly--removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

   <?php if(!isset($service_status['voter_mobile_link']) || $service_status['voter_mobile_link'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-success fs-11">LINK <span class="fas fa-link ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">VOTER MOBILE LINK</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_voter_link); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="voter_mobile_link.php">Link</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="voter_mobile_link_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/pvc-voter-id-card.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['voter_pdf']) || $service_status['voter_pdf'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-3.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-info fs-11">PDF <span class="fas fa-file-pdf ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">VOTER PDF</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary"><?php echo number_format($total_voter_pdf); ?></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="voter_pdf.php">Fetch</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="voter_pdf_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/pvc-voter-id-card.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['aadhar_to_pan']) || $service_status['aadhar_to_pan'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-1.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-danger fs-11">PAN FIND <span class="fas fa-search ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">AADHAAR TO PAN FIND</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary">
                            <?php echo number_format($total_aadhar_pan); ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="aadhar_to_pan.php">Find</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="aadhar_to_pan_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/02-07-WedSub-Images-2-1024x577-removebg-preview.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!isset($service_status['pan_to_aadhar']) || $service_status['pan_to_aadhar'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-primary fs-11">AADHAAR FIND <span class="fas fa-search ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">PAN TO AADHAAR FIND</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary">
                            <?php echo number_format($total_pan_aadhar); ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="pan_to_aadhar.php">Find</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="pan_to_aadhar_list.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/pan-card (1).png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
 <?php if(!isset($service_status['aadhar_to_details']) || $service_status['aadhar_to_details'] == 1): ?>
    <div class="col-sm-6 col-md-4">
        <div class="card overflow-hidden h-100">
            <div class="bg-holder bg-card" style="background-image:url(../assets/img/icons/spot-illustrations/corner-2.png);"></div>
            <div class="card-body position-relative">
                <div class="position-absolute top-0 end-0 mt-2 me-2" style="z-index: 10;">
                    <span class="badge rounded-pill badge-subtle-primary fs-11">AADHAAR FIND <span class="fas fa-search ms-1"></span></span>
                </div>
                <div class="row align-items-center g-0">
                    <div class="col-7">
                        <h6 class="text-700 mb-1">AADHAR TO DETAILS</h6>
                        <div class="display-4 fs-5 mb-3 fw-normal font-sans-serif text-primary">
                            <?php echo number_format($total_aadhar_details); ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-primary px-3 shadow-none" href="aadhar_to_details.php">Find</a>
                            <a class="btn btn-sm btn-outline-primary px-3 shadow-none" href="aadhaar_details_history.php">List</a>
                        </div>
                    </div>
                    <div class="col-5 text-end">
                        <img class="img-fluid" src="../assets/img/icons/spot-illustrations/aadhaar-card.png" alt="" style="width: 100px; filter: drop-shadow(2px 5px 10px rgba(0,0,0,0.1));" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card h-100 overflow-hidden shadow-sm">
            <div class="card-header bg-body-tertiary">
                <div class="row flex-between-center">
                    <div class="col-auto"><h6 class="mb-0 fw-bold">Today's Transactions & Forecast</h6></div>
                    <div class="col-auto mt-2 text-end">
                        <div class="row g-sm-4">
                            <div class="col-12 col-sm-auto">
                                <div class="mb-3 pe-4 border-end-sm border-200">
                                    <h6 class="fs-11 text-600 mb-1">Forecast Hours</h6>
                                    <div class="d-flex align-items-center">
                                        <h5 class="fs-9 text-900 mb-0 me-2"></h5><span class="badge rounded-pill badge-subtle-primary"><span class="fas fa-caret-up"></span> 20.2%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-auto">
                                <div class="mb-3 pe-0">
                                    <h6 class="fs-11 text-600 mb-1">Forecast Income</h6>
                                    <div class="d-flex align-items-center">
                                        <h5 class="fs-9 text-900 mb-0 me-2"></h5><span class="badge rounded-pill badge-subtle-primary"><span class="fas fa-caret-up"></span> 18%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body bg-white p-0 border-top">
                <div class="table-responsive scrollbar" style="max-height: 300px;">
                    <table class="table table-sm table-striped fs-10 mb-0 align-middle">
                        <thead class="bg-200 text-900 position-sticky top-0 shadow-sm" style="z-index: 1;">
                            <tr>
                                <th class="ps-3 py-2">Time</th>
                                <th>Purpose / Service</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center pe-3">Type</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            <?php
                            $history_query = $conn->query("SELECT * FROM wallethistory WHERE userid = '$uid' AND DATE(created_at) = CURDATE() ORDER BY id DESC");
                            
                            if ($history_query && $history_query->num_rows > 0) {
                                while ($row = $history_query->fetch_assoc()) {
                                    $type = $row['type'] ?? 'Debit';
                                    $color = ($type == 'Credit') ? 'success' : 'danger';
                                    $sign = ($type == 'Credit') ? '+' : '-';
                                    
                                    $date_val = $row['created_at'] ?? $row['date'] ?? 'now';
                            ?>
                            <tr>
                                <td class="ps-3 text-600 py-2 fw-semi-bold"><?php echo date('h:i A', strtotime($date_val)); ?></td>
                                <td class="fw-semi-bold text-dark"><?php echo htmlspecialchars($row['purpose']); ?></td>
                                <td class="text-end fw-bold text-<?php echo $color; ?>"><?php echo $sign; ?>₹<?php echo number_format($row['amount'], 2); ?></td>
                                <td class="text-center pe-3">
                                    <span class="badge badge-subtle-<?php echo $color; ?> fs-11 px-2"><?php echo htmlspecialchars($type); ?></span>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                            ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-500 fst-italic">No transactions yet for today.</td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$alert_html = "";
if(file_exists('alert_popup.php')) {
    $alert_msg_content = file_get_contents('alert_popup.php');
    $alert_msg_content = trim($alert_msg_content);
    
    if(!empty($alert_msg_content)) {
        $formatted_msg = nl2br($alert_msg_content);
        $js_msg = json_encode($formatted_msg);
        $js_name = json_encode($display_name ?? 'System');
        
        $alert_html = "
        <style>
            .custom-alert-popup { border-radius: 16px !important; overflow: hidden !important; width: 340px !important; background: transparent !important; padding: 0 !important; }
            .alert-container { background-color: #aeb4bc; border-radius: 16px; overflow: hidden; font-family: 'Poppins', sans-serif; position: relative; }
            .alert-header { background: #f4f5f7; color: #570fa3; font-weight: 800; font-size: 19px; padding: 16px; text-align: center; }
            .alert-close-btn { position: absolute; top: 12px; right: 12px; background: white; border: 1px solid #ccc; color: #333; border-radius: 50%; width: 28px; height: 28px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; font-size: 16px; transition: 0.2s; }
            .alert-close-btn:hover { background: #f8d7da; color: #d32f2f; border-color: #f5c6cb; }
            .alert-merchant-bar { background: #570fa3; color: #fff; padding: 8px; text-align: center; font-weight: bold; font-size: 14px; }
            .alert-body { padding: 20px; text-align: center; }
            .alert-msg-box { background: #fff; padding: 25px 15px; border-radius: 12px; border: 2px dashed #570fa3; margin-bottom: 15px; font-weight: 600; color: #333; min-height: 120px; display: block; text-align: center; word-wrap: break-word; font-size: 15px; line-height: 1.6; box-shadow: inset 0 0 10px rgba(0,0,0,0.05); }
            .alert-msg-box p { margin-bottom: 8px; }
            .alert-msg-box h1, .alert-msg-box h2, .alert-msg-box h3, .alert-msg-box h4, .alert-msg-box h5, .alert-msg-box h6 { color: #570fa3; margin-bottom: 10px; font-weight: 800; }
            .alert-secure-badge { background: #e8f5e9; color: #2e7d32; border-radius: 6px; padding: 8px; font-size: 12px; font-weight: bold; margin-bottom: 10px; border: 1px solid #c8e6c9; }
            .alert-footer { color: #4b4b4b; font-size: 12px; font-weight: bold; padding-bottom: 10px; }
        </style>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var popupMessage = " . $js_msg . ";
                var siteName = " . $js_name . ";
                
                Swal.fire({
                    html: `
                    <div class='alert-container'>
                        <div class='alert-close-btn' onclick='Swal.close()'>&times;</div>
                        <div class='alert-header'>System Notice</div>
                        <div class='alert-merchant-bar'>Notification: \${siteName}</div>
                        <div class='alert-body'>
                            <div class='alert-msg-box'>\${popupMessage}</div>
                            <div class='alert-secure-badge'><i class='fas fa-shield-alt'></i> Secured by \${siteName}</div>
                            <div class='alert-footer'>
                                Powered by \${siteName}
                            </div>
                        </div>
                    </div>`,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    background: 'transparent',
                    padding: 0,
                    customClass: { popup: 'custom-alert-popup' }
                });
            });
        </script>
        ";
    }
}
echo $alert_html;
?>

<?php if($utype == 'TitanAdmin'): 
    
$update_api_url = base64_decode("aHR0cHM6Ly91cGRhdGUudGl0YW5hcGkuaW4vdXBkYXRlX2FwaS92ZXJzaW9uLmpzb24=");
    
    $context = stream_context_create([
        'http' => ['timeout' => 3], 
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    
    $update_json = @file_get_contents($update_api_url, false, $context);
    
    if($update_json) {
        $update_data = json_decode($update_json, true);
        
        if(isset($update_data['version']) && $update_data['version'] !== $current_version) {
            $new_ver = addslashes($update_data['version']);
            $upd_msg = addslashes(str_replace(array("\r\n", "\r", "\n"), '<br>', $update_data['message']));
            $zip_link = addslashes($update_data['zip_url']);
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: '🚀 New Update Available!',
        html: '<b>Version:</b> <?php echo $new_ver; ?><br><br><b>Changelog:</b><br><?php echo $upd_msg; ?>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-download"></i> Update Now',
        cancelButtonText: 'Later',
        confirmButtonColor: '#28a745',
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            startUpdate('<?php echo $zip_link; ?>', '<?php echo $new_ver; ?>');
        }
    });

    function startUpdate(zipUrl, newVersion) {
        Swal.fire({
            title: 'Downloading Update...',
            html: 'Please wait, do not close or refresh this page.<br><br><b>Progress: </b><span id="update-progress">Processing...</span>',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = new FormData();
        formData.append('zip_url', zipUrl);
        formData.append('version', newVersion);

        fetch('../titancore/TitanUpdate.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success') {
                Swal.fire('Updated!', 'System has been successfully updated to v' + newVersion, 'success').then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Error!', res.message, 'error');
            }
        }).catch(err => {
            Swal.fire('Error!', 'Server connection failed during update.', 'error');
        });
    }
});
</script>
<?php 
        } 
    }
endif; 
?>

<?php 
require_once('../titancore/TitanFooter.php'); 
?>