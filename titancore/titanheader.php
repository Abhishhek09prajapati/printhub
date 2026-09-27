<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (basename($_SERVER['PHP_SELF']) === 'titanheader.php') {
    header("Location: ../dashboard/login.php");
    exit();
}

$user_type = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Guest'; 
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (empty($user_id) || $user_type === 'Guest') {
    if (!headers_sent()) {
        header("Location: ../dashboard/login.php");
    }
    echo '<script type="text/javascript">window.location.href = "../dashboard/login.php";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=../dashboard/login.php" /></noscript>';
    exit();
}

require_once('../titancore/titanconfig.php');

$settings_res = $conn->query("SELECT * FROM settings WHERE id = 1");
$web = ($settings_res && $settings_res->num_rows > 0) ? $settings_res->fetch_assoc() : [];

$site_title = $web['site_name'] ?? 'TitanApi';

$site_logo = (isset($web['logo']) && strpos($web['logo'], 'data:image') === 0) ? $web['logo'] : '../assets/img/icons/spot-illustrations/falcon.png';

$allowed_roles = ['TitanAdmin', 'Head Branch', 'Distributor'];
$can_manage_members = in_array($user_type, $allowed_roles);

$is_admin = ($user_type === 'TitanAdmin');

if (isset($_GET['action']) && $_GET['action'] === 'check_api_balance' && $is_admin) {
    header('Content-Type: application/json');
    
    $key_query = $conn->query("SELECT titan_api_key FROM TitanPayment WHERE id = 1");
    $api_key = ($key_query && $key_query->num_rows > 0) ? $key_query->fetch_assoc()['titan_api_key'] : '';

    if (empty($api_key)) {
        echo json_encode(["status" => "error", "message" => "Titan API Key missing in Settings."]);
        exit();
    }

    $url = "https://titanapi.in/api/v1/check_balance.php?api_key=" . urlencode($api_key);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    echo $response;
    exit();
}

$wallet_bal = 0.00;
if (isset($_SESSION['user_id']) && isset($conn)) {
    $uid = mysqli_real_escape_string($conn, $_SESSION['user_id']);
    $w_query = $conn->query("SELECT wallet FROM users WHERE id='$uid'");
    if ($w_query && $w_query->num_rows > 0) {
        $w_row = $w_query->fetch_assoc();
        $wallet_bal = $w_row['wallet'];
    }
}
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">

  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo htmlspecialchars($site_title); ?> | Dashboard</title>

    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicons/favicon.ico">
    <link rel="manifest" href="../assets/img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="../assets/img/favicons/mstile-150x150.png">
    <meta name="theme-color" content="#ffffff">
    
    <script src="../assets/js/config.js"></script>
    <script src="../vendors/simplebar/simplebar.min.js"></script>
    <link href="../vendors/leaflet/leaflet.css" rel="stylesheet">
    <link href="../vendors/leaflet.markercluster/MarkerCluster.css" rel="stylesheet">
    <link href="../vendors/leaflet.markercluster/MarkerCluster.Default.css" rel="stylesheet">
    <link href="../vendors/flatpickr/flatpickr.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700%7cPoppins:300,400,500,600,700,800,900&amp;display=swap" rel="stylesheet">
    <link href="../vendors/simplebar/simplebar.min.css" rel="stylesheet">
    <link href="../assets/css/theme-rtl.css" rel="stylesheet" id="style-rtl">
    <link href="../assets/css/theme.css" rel="stylesheet" id="style-default">
    <link href="../assets/css/user-rtl.css" rel="stylesheet" id="user-style-rtl">
    <link href="../assets/css/user.css" rel="stylesheet" id="user-style-default">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
      var isRTL = JSON.parse(localStorage.getItem('isRTL'));
      if (isRTL) {
        var linkDefault = document.getElementById('style-default');
        var userLinkDefault = document.getElementById('user-style-default');
        linkDefault.setAttribute('disabled', true);
        userLinkDefault.setAttribute('disabled', true);
        document.querySelector('html').setAttribute('dir', 'rtl');
      } else {
        var linkRTL = document.getElementById('style-rtl');
        var userLinkRTL = document.getElementById('user-style-rtl');
        linkRTL.setAttribute('disabled', true);
        userLinkRTL.setAttribute('disabled', true);
      }
    </script>
  </head>

  <body>
    <main class="main" id="top">
      <div class="container" data-layout="container">
        <script>
          var isFluid = JSON.parse(localStorage.getItem('isFluid'));
          if (isFluid) {
            var container = document.querySelector('[data-layout]');
            container.classList.remove('container');
            container.classList.add('container-fluid');
          }
        </script>
        
        <nav class="navbar navbar-light navbar-vertical navbar-expand-xl">
          <script>
            var navbarStyle = localStorage.getItem("navbarStyle");
            if (navbarStyle && navbarStyle !== 'transparent') {
              document.querySelector('.navbar-vertical').classList.add(`navbar-${navbarStyle}`);
            }
          </script>
          <div class="d-flex align-items-center">
            <div class="toggle-icon-wrapper">
              <button class="btn navbar-toggler-humburger-icon navbar-vertical-toggle" data-bs-toggle="tooltip" data-bs-placement="left" title="Toggle Navigation">
                <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
              </button>
            </div>
            <a class="navbar-brand" href="titanhome.php">
              <div class="d-flex align-items-center py-3">
                <img class="me-2" src="<?php echo $site_logo; ?>" alt="" width="40" style="object-fit: contain;" />
                <span class="font-sans-serif text-primary"><?php echo htmlspecialchars($site_title); ?></span>
              </div>
            </a>
          </div>
          
          <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
            <div class="navbar-vertical-content scrollbar">
              <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">
                
                <li class="nav-item">
                  <a class="nav-link" href="titanhome.php" role="button">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-home"></span></span>
                      <span class="nav-link-text ps-1">Dashboard</span>
                    </div>
                  </a>
                </li>

                <?php if($can_manage_members): ?>
                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#memberMgmt" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="memberMgmt">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-users-cog text-info"></span></span>
                      <span class="nav-link-text ps-1">Member Management</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="memberMgmt">
                    <li class="nav-item"><a class="nav-link" href="add_user.php">Add New Member</a></li>
                    <li class="nav-item"><a class="nav-link" href="user_list.php">Manage Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="balance_transfer.php">Wallet Transfer</a></li>
                  </ul>
                </li>
                <?php endif; ?>

                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">AADHAAR SERVICES</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#aadharMan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadharMan">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-fingerprint text-success"></span></span>
                      <span class="nav-link-text ps-1">Aadhar Manual</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="aadharMan">
                    <li class="nav-item"><a class="nav-link" href="aadhaar_manual.php">Manual Entry</a></li>
                    <li class="nav-item"><a class="nav-link" href="aadharmanual_list.php">Manual List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#aadharVer" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadharVer">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-check-circle text-success"></span></span>
                      <span class="nav-link-text ps-1">Aadhar Verify</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="aadharVer">
                    <li class="nav-item"><a class="nav-link" href="aadhaar_verify.php">Verify Aadhar</a></li>
                    <li class="nav-item"><a class="nav-link" href="aadhaar_verify_list.php">Verify List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#aadharMsk" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadharMsk">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-user-secret text-success"></span></span>
                      <span class="nav-link-text ps-1">Aadhar Mask</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="aadharMsk">
                    <li class="nav-item"><a class="nav-link" href="aadhaar_mask.php">Mask Aadhar</a></li>
                    <li class="nav-item"><a class="nav-link" href="aadhaar_mask_list.php">Mask List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#aadharName" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadharName">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-id-badge text-success"></span></span>
                      <span class="nav-link-text ps-1">Aadhar To Name</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="aadharName">
                    <li class="nav-item"><a class="nav-link" href="aadhar_to_name.php">Get Name</a></li>
                    <li class="nav-item"><a class="nav-link" href="aadhaar_to_name_list.php">Name List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#aadharDet" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadharDet">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-address-card text-success"></span></span>
                      <span class="nav-link-text ps-1">Aadhar To Details</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="aadharDet">
                    <li class="nav-item"><a class="nav-link" href="aadhar_to_details.php">Get Details</a></li>
                    <li class="nav-item"><a class="nav-link" href="aadhaar_details_history.php">Details List</a></li>
                  </ul>
                </li>


                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">PAN SERVICES</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#panAdv" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="panAdv">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-bolt text-warning"></span></span>
                      <span class="nav-link-text ps-1">Pan Advance</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="panAdv">
                    <li class="nav-item"><a class="nav-link" href="pan_advance_print.php">Advance Print</a></li>
                    <li class="nav-item"><a class="nav-link" href="pan_advance_list.php">Print List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#panMan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="panMan">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-keyboard text-warning"></span></span>
                      <span class="nav-link-text ps-1">Pan Manual</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="panMan">
                    <li class="nav-item"><a class="nav-link" href="pan_manual.php">Manual Apply</a></li>
                    <li class="nav-item"><a class="nav-link" href="pan_manual_list.php">Manual List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#aadhaarPan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadhaarPan">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-search text-warning"></span></span>
                      <span class="nav-link-text ps-1">Aadhaar To Pan</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="aadhaarPan">
                    <li class="nav-item"><a class="nav-link" href="aadhar_to_pan.php">Find Pan</a></li>
                    <li class="nav-item"><a class="nav-link" href="aadhar_to_pan_list.php">Pan List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#panAadhaar" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="panAadhaar">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-search-plus text-warning"></span></span>
                      <span class="nav-link-text ps-1">Pan To Aadhaar</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="panAadhaar">
                    <li class="nav-item"><a class="nav-link" href="pan_to_aadhar.php">Find Aadhaar</a></li>
                    <li class="nav-item"><a class="nav-link" href="pan_to_aadhar_list.php">Aadhaar List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#panFull" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="panFull">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-info-circle text-warning"></span></span>
                      <span class="nav-link-text ps-1">Pan Full Details</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="panFull">
                    <li class="nav-item"><a class="nav-link" href="pan_full_details.php">Get Details</a></li>
                    <li class="nav-item"><a class="nav-link" href="pan_full_details_list.php">Details List</a></li>
                  </ul>
                </li>


                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">VOTER SERVICES</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#voterAdv" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="voterAdv">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-bolt text-primary"></span></span>
                      <span class="nav-link-text ps-1">Voter Advance</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="voterAdv">
                    <li class="nav-item"><a class="nav-link" href="voter_advance.php">Voter Search</a></li>
                    <li class="nav-item"><a class="nav-link" href="voter_advance_list.php">Advance List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#voterMan" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="voterMan">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-edit text-primary"></span></span>
                      <span class="nav-link-text ps-1">Voter Manual</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="voterMan">
                    <li class="nav-item"><a class="nav-link" href="voter_manual.php">Apply Manual</a></li>
                    <li class="nav-item"><a class="nav-link" href="voter_manual_list.php">Manual List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#voterPdf" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="voterPdf">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-file-pdf text-primary"></span></span>
                      <span class="nav-link-text ps-1">Voter PDF</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="voterPdf">
                    <li class="nav-item"><a class="nav-link" href="voter_pdf.php">Fetch PDF</a></li>
                    <li class="nav-item"><a class="nav-link" href="voter_pdf_list.php">PDF List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#voterLink" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="voterLink">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-link text-primary"></span></span>
                      <span class="nav-link-text ps-1">Voter Mobile Link</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="voterLink">
                    <li class="nav-item"><a class="nav-link" href="voter_mobile_link.php">Link Mobile</a></li>
                    <li class="nav-item"><a class="nav-link" href="voter_mobile_link_list.php">Link List</a></li>
                  </ul>
                </li>


                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">DL & RC SERVICES</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#dlPrint" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="dlPrint">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-id-card text-info"></span></span>
                      <span class="nav-link-text ps-1">DL Print</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="dlPrint">
                    <li class="nav-item"><a class="nav-link" href="DL_Card.php">Print DL</a></li>
                    <li class="nav-item"><a class="nav-link" href="dl_pdf_list.php">DL List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#rcPrint" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="rcPrint">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-car text-info"></span></span>
                      <span class="nav-link-text ps-1">RC Print</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="rcPrint">
                    <li class="nav-item"><a class="nav-link" href="rc_pro.php">Print RC</a></li>
                    <li class="nav-item"><a class="nav-link" href="rc_pro_list.php">RC List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#rcMob" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="rcMob">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-mobile-alt text-info"></span></span>
                      <span class="nav-link-text ps-1">RC To Mobile</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="rcMob">
                    <li class="nav-item"><a class="nav-link" href="rc_to_mobile.php">Find Mobile</a></li>
                    <li class="nav-item"><a class="nav-link" href="rc_to_mobile_list.php">Mobile List</a></li>
                  </ul>
                </li>


                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">GOVT & BANK SERVICES</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#upRat" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="upRat">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-map-marker-alt text-secondary"></span></span>
                      <span class="nav-link-text ps-1">UP Ration to Aadhar</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="upRat">
                    <li class="nav-item"><a class="nav-link" href="up_ration_to_uid.php">Find Aadhar</a></li>
                    <li class="nav-item"><a class="nav-link" href="up_ration_to_uid_list.php">Find List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#aadRatPdf" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="aadRatPdf">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-file-alt text-secondary"></span></span>
                      <span class="nav-link-text ps-1">Aadhar To Ration PDF</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="aadRatPdf">
                    <li class="nav-item"><a class="nav-link" href="aadhar_ration_pdf.php">Generate PDF</a></li>
                    <li class="nav-item"><a class="nav-link" href="aadhar_ration_pdf_list.php">PDF List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#farmerPdf" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="farmerPdf">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-tractor text-success"></span></span>
                      <span class="nav-link-text ps-1">Farmer PDF</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="farmerPdf">
                    <li class="nav-item"><a class="nav-link" href="FarmerPdf.php">Generate PDF</a></li>
                    <li class="nav-item"><a class="nav-link" href="FarmerPdf_List.php">PDF List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#pmjay" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="pmjay">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-heartbeat text-danger"></span></span>
                      <span class="nav-link-text ps-1">PMJAY Card</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="pmjay">
                    <li class="nav-item"><a class="nav-link" href="ayushman_card.php">Apply Card</a></li>
                    <li class="nav-item"><a class="nav-link" href="ayushman_card_list.php">Card List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#npci" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="npci">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-university text-primary"></span></span>
                      <span class="nav-link-text ps-1">NPCI Status</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="npci">
                    <li class="nav-item"><a class="nav-link" href="npci_status.php">Check NPCI</a></li>
                    <li class="nav-item"><a class="nav-link" href="npci_status_list.php">NPCI List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#ratCard" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="ratCard">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-shopping-basket text-warning"></span></span>
                      <span class="nav-link-text ps-1">Ration Card</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="ratCard">
                    <li class="nav-item"><a class="nav-link" href="ration_card.php">Print Ration</a></li>
                    <li class="nav-item"><a class="nav-link" href="ration_pdf_list.php">Ration List</a></li>
                  </ul>
                </li>

                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#learnPdf" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="learnPdf">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-book-open text-info"></span></span>
                      <span class="nav-link-text ps-1">Learning PDF</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="learnPdf">
                    <li class="nav-item"><a class="nav-link" href="ll_pdf.php">View PDF</a></li>
                    <li class="nav-item"><a class="nav-link" href="ll_pdf_list.php">PDF List</a></li>
                  </ul>
                </li>

                <?php if($is_admin): ?>
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label text-danger">ADMIN CONTROLS</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider border-danger" /></div>
                    </div>
                </li>
                <li class="nav-item">
                  <a class="nav-link dropdown-indicator" href="#adminCtrl" role="button" data-bs-toggle="collapse" aria-expanded="false" aria-controls="adminCtrl">
                    <div class="d-flex align-items-center">
                      <span class="nav-link-icon"><span class="fas fa-tools text-danger"></span></span>
                      <span class="nav-link-text ps-1 text-danger fw-bold">Website Manage</span>
                    </div>
                  </a>
                  <ul class="nav collapse" id="adminCtrl">
                    <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
                    <li class="nav-item"><a class="nav-link" href="api_setup.php">API Setup</a></li>
                    <li class="nav-item"><a class="nav-link" href="service_manage.php">Service Manage</a></li>
                    <li class="nav-item"><a class="nav-link" href="alert_manage.php">Alert Manage (Popup)</a></li>
                    <li class="nav-item"><a class="nav-link" href="update_message.php">Running Message </a></li>
                    <li class="nav-item"><a class="nav-link" href="tiketview.php">Ticket View </a></li>
                  </ul>
                </li>
                <?php endif; ?>

                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">HELP & SUPPORT</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                    <a class="nav-link" href="helpdesk.php" role="button">
                        <div class="d-flex align-items-center">
                          <span class="nav-link-icon"><span class="fas fa-headset text-danger"></span></span>
                          <span class="nav-link-text ps-1">Customer Support</span>
                        </div>
                    </a>
                </li>
              </ul>
              
              <div class="settings my-3">
                <div class="card shadow-none">
                  <div class="card-body alert mb-0" role="alert">
                    <div class="btn-close-falcon-container">
                      <button class="btn btn-link btn-close-falcon p-0" aria-label="Close" data-bs-dismiss="alert"></button>
                    </div>
                    <div class="text-center">
                      <img src="../assets/img/icons/spot-illustrations/navbar-vertical.png" alt="" width="80" />
                      <p class="fs-11 mt-2">Welcome to <strong><?php echo htmlspecialchars($site_title); ?></strong></p>
                      <div class="d-grid"><a class="btn btn-sm btn-primary" href="helpdesk.php">Open Ticket</a></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </nav>

        <div class="content">
          <nav class="navbar navbar-light navbar-glass navbar-top navbar-expand">
            <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle Navigation">
              <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
            </button>
            <a class="navbar-brand me-1 me-sm-3" href="titanhome.php">
              <div class="d-flex align-items-center">
                <img class="me-2" src="<?php echo $site_logo; ?>" alt="" width="40" style="object-fit: contain;" />
                <span class="font-sans-serif text-primary"><?php echo htmlspecialchars($site_title); ?></span>
              </div>
            </a>
            
            <ul class="navbar-nav align-items-center d-none d-lg-block">
              <li class="nav-item">
                <div class="search-box">
                  <form class="position-relative">
                    <input class="form-control search-input" type="search" placeholder="Search..." aria-label="Search">
                    <span class="fas fa-search search-box-icon"></span>
                  </form>
                </div>
              </li>
            </ul>

            <ul class="navbar-nav navbar-nav-icons ms-auto flex-row align-items-center">
              
              <?php if($is_admin): ?>
              <li class="nav-item px-2 pe-0">
                <button onclick="checkApiBalance()" class="btn btn-warning btn-sm fw-bold d-flex align-items-center shadow-sm" style="border: none; border-radius: 6px; padding: 0.35rem 0.6rem; font-size: 14px;">
                  <span class="fas fa-server" style="margin-top: 1px;"></span><span class="ms-1 d-none d-sm-inline-block"> API Balance</span>
                </button>
              </li>
              <?php endif; ?>

              <li class="nav-item px-2">
                <a class="btn btn-primary btn-sm fw-bold d-flex align-items-center shadow-sm" href="addwallet.php" style="background-color: #0d6efd; border: none; border-radius: 6px; padding: 0.35rem 0.6rem; font-size: 14px;">
                  <span class="fas fa-wallet" style="margin-top: 1px;"></span><span class="ms-1">: <?php echo (is_numeric($wallet_bal) && floor($wallet_bal) == $wallet_bal) ? number_format($wallet_bal, 0) : number_format($wallet_bal, 2); ?></span>
                </a>
              </li>

              <li class="nav-item ps-2 pe-0">
                <div class="dropdown theme-control-dropdown">
                  <a class="nav-link d-flex align-items-center dropdown-toggle fs-9 pe-1 py-0" href="https://shop.pgecm.online/" role="button" id="themeSwitchDropdown" data-bs-toggle="dropdown">
                    <span class="fas fa-sun fs-7" data-theme-dropdown-toggle-icon="light"></span>
                    <span class="fas fa-moon fs-7" data-theme-dropdown-toggle-icon="dark"></span>
                    <span class="fas fa-adjust fs-7" data-theme-dropdown-toggle-icon="auto"></span>
                  </a>
                  <div class="dropdown-menu dropdown-menu-end border py-0 mt-3">
                    <div class="bg-white dark__bg-1000 rounded-2 py-2">
                      <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-theme-control="theme" value="light"><span class="fas fa-sun"></span>Light</button>
                      <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-theme-control="theme" value="dark"><span class="fas fa-moon"></span>Dark</button>
                      <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-theme-control="theme" value="auto"><span class="fas fa-adjust"></span>Auto</button>
                    </div>
                  </div>
                </div>
              </li>

              <li class="nav-item dropdown">
                <a class="nav-link pe-0 ps-2" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <div class="avatar avatar-xl">
                    <img class="rounded-circle" src="../assets/img/team/3-thumb.png" alt="User" />
                  </div>
                </a>
                <div class="dropdown-menu dropdown-caret dropdown-menu-end py-0" aria-labelledby="navbarDropdownUser">
                  <div class="bg-white dark__bg-1000 rounded-2 py-2">
                    <a class="dropdown-item fw-bold text-warning" href="#!"><span class="fas fa-crown me-1"></span><span>Go Pro</span></a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="profile.php">Profile & account</a>
                    <a class="dropdown-item" href="helpdesk.php">HelpDesk</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="logout.php">Logout</a>
                  </div>
                </div>
              </li>
            </ul>
          </nav>

          <script>
              function checkApiBalance() {
                Swal.fire({
                    title: 'Fetching Balance...',
                    html: 'Please wait while we connect to Master API.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('?action=check_api_balance')
                .then(response => response.json())
                .then(data => {
                    if(data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'API Balance',
                            html: `
                                <div class="text-start mt-3 p-3 bg-light rounded border border-success shadow-sm">
                                    <p class="mb-1"><i class="fas fa-server text-secondary me-2"></i><b>Provider:</b> ${data.provider}</p>
                                    <p class="mb-1"><i class="fas fa-user text-secondary me-2"></i><b>Account:</b> ${data.data.name} <span class="badge bg-primary ms-1">${data.data.role}</span></p>
                                    <hr>
                                    <h4 class="mt-2 text-success fw-bold text-center"><i class="fas fa-wallet me-2"></i>${data.data.balance} ${data.data.currency}</h4>
                                </div>
                            `,
                            confirmButtonColor: '#0d6efd'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: data.message || 'Could not fetch API balance.',
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Network Error',
                        text: 'Failed to connect to the server.'
                    });
                });
            }
          </script>