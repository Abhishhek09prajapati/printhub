<?php
session_start();
error_reporting(0);
require_once('../titancore/titanconfig.php'); 

$LICENSE_FILE = __DIR__ . '/../license.key'; 
$current_domain = str_replace(['http://', 'https://', 'www.'], '', strtolower($_SERVER['HTTP_HOST']));
$swal_script = "";
$is_already_activated = false;

$stmtCheck = $conn->query("SELECT master_url, is_activated, licensed_domain, expiry_date FROM TitanActivation WHERE id = 1 LIMIT 1");
if ($stmtCheck && $row = $stmtCheck->fetch_assoc()) {
    $MASTER_VERIFY_URL = $row['master_url'] ?? '';
    if ($row['is_activated'] == 1 && $row['licensed_domain'] === $current_domain && $row['expiry_date'] > date('Y-m-d H:i:s')) {
        $is_already_activated = true;
    }
}

if ($is_already_activated) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['activate_btn'])) {
    $client_key = trim($_POST['license_key']);
    
    if (!empty($client_key)) {
        if(empty($MASTER_VERIFY_URL)){
             $swal_script = "Swal.fire({ icon: 'error', title: 'System Error', text: 'Master API URL missing in Database.' });";
        } else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $MASTER_VERIFY_URL);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['license_key' => $client_key, 'domain' => $current_domain]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if($http_code != 200 || empty($response)){
                $swal_script = "Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Master Server is unreachable right now.' });";
            } else {
                $res = json_decode($response, true);
                // Agar API status 'valid' bhejti hai toh 1 year ke liye active hoga
                if (isset($res['status']) && $res['status'] === 'valid') {
                    
                    file_put_contents($LICENSE_FILE, $client_key);
                    
                    // AUTO 1 YEAR EXPIRY SET
                    $valid_until = date('Y-m-d H:i:s', strtotime('+365 days'));
                    $update_query = "UPDATE TitanActivation SET is_activated = 1, licensed_domain = '$current_domain', expiry_date = '$valid_until' WHERE id = 1";
                    
                    if ($conn->query($update_query)) {
                        $swal_script = "Swal.fire({ title: 'Activation Successful!', text: 'Your system is fully licensed for 365 Days on this domain.', icon: 'success', confirmButtonColor: '#0d6efd', timer: 2500, showConfirmButton: false }).then(() => { window.location.href = 'login.php'; });";
                    } else {
                        $swal_script = "Swal.fire({ icon: 'error', title: 'Database Error', text: 'Failed to save license locally.' });";
                    }
                } else {
                    $err = isset($res['message']) ? $res['message'] : 'Invalid Key or Domain mismatch.';
                    $swal_script = "Swal.fire({ icon: 'error', title: 'Activation Failed', text: '$err' });";
                }
            }
        }
    } else {
        $swal_script = "Swal.fire({ icon: 'warning', title: 'Attention', text: 'Please enter a license key.' });";
    }
}

$error_msg = "";
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'expired') {
        $error_msg = "<div class='alert alert-warning fw-bold fs-10'><i class='fas fa-clock me-1'></i> Your license expired after 1 year. Please renew your key.</div>";
    } elseif ($_GET['error'] == 'suspended') {
        // EXACT MESSAGE AS YOU REQUESTED
        $error_msg = "<div class='alert alert-danger fw-bold fs-10'><i class='fas fa-ban me-1'></i> Your license key suspended please contact license provider.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Titan Security | Software Activation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f3f6ff; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .activation-card { background: #fff; border-radius: 24px; padding: 40px; width: 100%; max-width: 480px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); }
        .brand-logo { width: 70px; height: 70px; background: linear-gradient(135deg, #0d6efd 0%, #002d72 100%); border-radius: 18px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: #fff; font-size: 30px; }
        .domain-tag { background: rgba(13,110,253,0.08); color: #0d6efd; padding: 6px 16px; border-radius: 100px; font-size: 13px; font-weight: 600; display: inline-block; margin-bottom: 20px; }
        .license-input { border-radius: 12px; padding: 14px; text-align: center; font-weight: bold; letter-spacing: 1px; font-family: monospace; }
        .btn-activate { background: linear-gradient(135deg, #0d6efd 0%, #002d72 100%); color: white; font-weight: 700; border-radius: 12px; padding: 14px; width: 100%; margin-top: 15px; border: none; }
    </style>
</head>
<body>
    <div class="activation-card text-center">
        <div class="brand-logo"><i class="fas fa-shield-halved"></i></div>
        <h3 class="fw-bold mb-1">Software Activation</h3>
        <span class="domain-tag"><i class="fas fa-link me-1"></i> <?php echo $current_domain; ?></span>
        <?php echo $error_msg; ?>
        <form method="POST" id="activationForm">
            <div class="text-start mb-3">
                <label class="form-label fs-10 fw-bold">Enter License Key (365 Days Validation)</label>
                <input type="text" name="license_key" class="form-control license-input shadow-none" placeholder="TITAN-XXXX-XXXX-XXXX" required>
            </div>
            <button type="submit" name="activate_btn" class="btn btn-activate" id="subBtn"><i class="fas fa-bolt me-2"></i> Verify & Activate</button>
        </form>
    </div>
    <script>
        <?php echo $swal_script; ?>
        const form = document.getElementById('activationForm');
        if(form) {
            form.addEventListener('submit', function() {
                const btn = document.getElementById('subBtn');
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
                btn.classList.add('disabled');
            });
        }
    </script>
</body>
</html>