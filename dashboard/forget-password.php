<?php
ob_start();
session_start();
error_reporting(0); 

// 1. Database Connection
require_once('../titancore/titanconfig.php'); 

$message_status = "";

// 2. Fetch Website Name & API Settings from SQL
$web_query = mysqli_query($conn, "SELECT website_name FROM TitanActivation WHERE id = 1 LIMIT 1");
$web_data = mysqli_fetch_assoc($web_query);
$web_name = $web_data['website_name'] ?? "TitanApi";

// TitanPayment table se WhatsApp API settings uthayenge
$api_query = mysqli_query($conn, "SELECT WhatsappApiUrl, WhatsappApiKey, WhatsappSender FROM TitanPayment WHERE id = 1 LIMIT 1");
$api = mysqli_fetch_assoc($api_query);

// 3. Handle Form Submission
if (isset($_POST['submit'])) {
    $user_phone = mysqli_real_escape_string($conn, $_POST['phone_number']);
    
    // Table 'users' aur column 'phone'
    $user_check = mysqli_query($conn, "SELECT name, password FROM users WHERE phone = '$user_phone' LIMIT 1");
    
    if ($user_check && mysqli_num_rows($user_check) > 0) {
        $user_data = mysqli_fetch_assoc($user_check);
        $user_real_name = $user_data['name'];
        $user_real_pass = $user_data['password'];

        // --- WHATSAPP MESSAGE DESIGN ---
        // Isko humne bold tags aur professional spacing ke sath design kiya hai
        $msg_text = "*🔐 PASSWORD RECOVERY*\n\n";
        $msg_text .= "Hello *$user_real_name*,\n\n";
        $msg_text .= "As per your request, here are your login details for *$web_name*:\n\n";
        $msg_text .= "📞 *Phone:* $user_phone\n";
        $msg_text .= "🔑 *Password:* $user_real_pass\n\n";
        $msg_text .= "--------------------------------\n";
        $msg_text .= "©️ Powered by *$web_name*"; // SQL se dynamic name
        
        $encoded_msg = urlencode($msg_text);
        
        if ($api && !empty($api['WhatsappApiUrl'])) {
            $apiUrl = $api['WhatsappApiUrl'];
            $apiKey = $api['WhatsappApiKey'];
            $sender = $api['WhatsappSender'];
            
            // Number formatting (Prefix 91)
            $clean_number = preg_replace('/[^0-9]/', '', $user_phone);
            $target = (strlen($clean_number) == 10) ? "91" . $clean_number : $clean_number;

            // Final API URL
            $final_url = "$apiUrl?api_key=$apiKey&sender=$sender&number=$target&message=$encoded_msg";
            
            // cURL Request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $final_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $response = curl_exec($ch);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_status == 200 || strpos($response, 'true') !== false) {
                $message_status = "success";
            } else {
                $message_status = "api_error";
            }
        } else {
            $message_status = "config_missing";
        }
    } else {
        $message_status = "user_not_found";
    }
}
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $web_name; ?> | Recovery</title>

    <link href="../../../assets/css/theme.css" rel="stylesheet" id="style-default">
    <link href="../../../assets/css/user.css" rel="stylesheet" id="user-style-default">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <main class="main" id="top">
        <div class="container-fluid">
            <div class="row min-vh-100 flex-center g-0">
                <div class="col-lg-8 col-xxl-5 py-3 position-relative">
                    <img class="bg-auth-circle-shape" src="../../../assets/img/icons/spot-illustrations/bg-shape.png" alt="" width="250">
                    
                    <div class="card overflow-hidden z-1 shadow-lg border-0">
                        <div class="card-body p-0">
                            <div class="row g-0 h-100">
                                <div class="col-md-5 text-center bg-card-gradient text-white p-4">
                                    <div class="position-relative mt-md-5">
                                        <div class="bg-holder bg-auth-card-shape" style="background-image:url(../../../assets/img/icons/spot-illustrations/half-circle.png);"></div>
                                        <h3 class="text-white fw-bolder mb-3"><?php echo strtoupper($web_name); ?></h3>
                                        <p class="opacity-75 fs-11">Secure WhatsApp-based account recovery for our valued members.</p>
                                    </div>
                                </div>
                                
                                <div class="col-md-7 d-flex flex-center">
                                    <div class="p-4 p-md-5 flex-grow-1">
                                        <h4 class="mb-1">Forgot Password?</h4>
                                        <p class="mb-4 fs-10 text-600">Please enter your 10-digit registered phone number.</p>
                                        
                                        <form method="POST" id="recoveryForm">
                                            <div class="mb-3 text-start">
                                                <label class="form-label fs-11 fw-bold">Phone Number</label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-200">+91</span>
                                                    <input class="form-control shadow-none border-2" type="number" name="phone_number" placeholder="900672XXXX" required />
                                                </div>
                                            </div>
                                            <button class="btn btn-primary d-block w-100 mt-3 fw-bold shadow-sm" type="submit" name="submit" id="subBtn">
                                                <i class="fas fa-paper-plane me-2"></i>Send on WhatsApp
                                            </button>
                                        </form>
                                        
                                        <div class="mt-3 text-center border-top pt-3">
                                            <a class="fs-10 text-600 fw-semi-bold" href="login.php">Back to Login</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-center fs-11 mt-3 opacity-50">© <?php echo date('Y') . " " . $web_name; ?></p>
                </div>
            </div>
        </div>
    </main>

    <script>
        const btn = document.getElementById('subBtn');
        const form = document.getElementById('recoveryForm');
        if(form){
            form.addEventListener('submit', () => {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Connecting...';
                btn.classList.add('disabled');
            });
        }

        <?php if($message_status == "success"): ?>
            Swal.fire({ icon: 'success', title: 'Details Sent!', text: 'Your password has been sent to your WhatsApp successfully.', confirmButtonColor: '#2c7be5' });
        <?php elseif($message_status == "user_not_found"): ?>
            Swal.fire({ icon: 'error', title: 'Not Registered', text: 'This phone number is not found in our records.' });
        <?php elseif($message_status == "api_error"): ?>
            Swal.fire({ icon: 'warning', title: 'API Delay', text: 'Gateway is taking longer than usual. Please check WhatsApp in a moment.' });
        <?php elseif($message_status == "config_missing"): ?>
            Swal.fire({ icon: 'info', title: 'System Setup', text: 'WhatsApp API configuration is not complete in Database.' });
        <?php endif; ?>
    </script>

    <script src="../../../vendors/bootstrap/bootstrap.min.js"></script>
    <script src="../../../assets/js/theme.js"></script>
</body>
</html>