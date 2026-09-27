<?php
/**
 * FINAL WORKING LOGIN SCRIPT - RE-OPTIMIZED
 * Design: Falcon Premium UI
 */

// 1. Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Database Connection
require_once('../titancore/titanconfig.php');

// 3. Fetch Site Settings (Branding & Reg Toggle)
$site_query = $conn->query("SELECT site_name, self_register FROM settings WHERE id = 1");
$site_data = $site_query->fetch_assoc();
$brand_name = $site_data['site_name'] ?? 'ApiNexus'; 
$reg_open_status = $site_data['self_register'] ?? 1; // 1 = Open, 0 = Closed

$login_status = "";

// 4. Handle Login Logic
if (isset($_POST['submit'])) {
    $login_id = mysqli_real_escape_string($conn, $_POST['login_id']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // SQL Query to match Email or Phone
    $sql = "SELECT * FROM users WHERE (email = '$login_id' OR phone = '$login_id') AND password = '$password' LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $user_row = $result->fetch_assoc();
        
        // Account Status Validation
        if ($user_row['status'] == 'Inactive' || $user_row['status'] == 'Pending') {
            $login_status = "blocked"; 
        } else {
            // Success: Setting Sessions
            $_SESSION['user_id'] = $user_row['id'];
            $_SESSION['user_name'] = $user_row['name']; 
            $_SESSION['utype'] = $user_row['user_type']; 
            $_SESSION['last_activity'] = time(); 
            
            $login_status = "success";
        }
    } else {
        $login_status = "failed"; 
    }
}
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en-US">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($brand_name); ?> | Secure Login</title>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700%7cPoppins:300,400,500,600,700,800,900&amp;display=swap" rel="stylesheet">
    <link href="../../../assets/css/theme.css" rel="stylesheet">
    <link href="../../../assets/css/user.css" rel="stylesheet">
</head>

<body>
    <main class="main">
        <div class="container-fluid">
            <div class="row min-vh-100 flex-center g-0">
                <div class="col-lg-8 col-xxl-5 py-3 position-relative">
                    <img class="bg-auth-circle-shape" src="../../../assets/img/icons/spot-illustrations/bg-shape.png" alt="" width="250">
                    
                    <div class="card overflow-hidden z-1 shadow-lg border-0">
                        <div class="card-body p-0">
                            <div class="row g-0 h-100">
                                
                                <div class="col-md-5 text-center bg-card-gradient">
                                    <div class="position-relative p-4 pt-md-5 pb-md-7" data-bs-theme="light">
                                        <div class="bg-holder bg-auth-card-shape" style="background-image:url(../../../assets/img/icons/spot-illustrations/half-circle.png);"></div>
                                        <div class="z-1 position-relative">
                                            <a class="link-light mb-4 font-sans-serif fs-5 d-inline-block fw-bolder" href="#"><?php echo strtoupper(htmlspecialchars($brand_name)); ?></a>
                                            <p class="opacity-75 text-white">Sign in to access your administrative tools and service management dashboard.</p>
                                        </div>
                                    </div>
                                    <div class="mt-3 mb-4 mt-md-4 mb-md-5" data-bs-theme="light">
                                        <p class="text-white small">New User?<br>
                                            <a class="text-decoration-underline link-light fw-bold" href="javascript:void(0);" onclick="handleRegistration()">Create An Account</a>
                                        </p>
                                    </div>
                                </div>

                                <div class="col-md-7 d-flex flex-center">
                                    <div class="p-4 p-md-5 flex-grow-1">
                                        <h3>Member Login</h3>
                                        <form method="POST" class="mt-3" autocomplete="off">
                                            <div class="mb-3">
                                                <label class="form-label text-900 fw-semi-bold">Email or Phone</label>
                                                <input class="form-control" name="login_id" type="text" placeholder="Enter ID" required />
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-900 fw-semi-bold">Password</label>
                                                <input class="form-control" name="password" type="password" placeholder="••••••••" required />
                                            </div>
                                            <div class="d-flex flex-between-center mb-3">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" id="rememberMe" checked />
                                                    <label class="form-check-label mb-0" for="rememberMe">Remember me</label>
                                                </div>
                                                <a class="fs-11 fw-semi-bold" href="#">Forgot Password?</a>
                                            </div>
                                            <button class="btn btn-primary d-block w-100 mt-3 py-2 fw-bold shadow-sm" type="submit" name="submit">Secure Sign In</button>
                                        </form>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- REGISTRATION HANDLER SCRIPT -->
    <script>
        function handleRegistration() {
            const isRegOpen = <?php echo $reg_open_status; ?>;
            if (isRegOpen == 1) {
                window.location.href = "register.php";
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Registration Closed',
                    text: 'Self-registration is currently disabled by the administrator. Please contact support to create an account.',
                    confirmButtonColor: '#2c7be5'
                });
            }
        }
    </script>

    <!-- LOGIN NOTIFICATIONS -->
    <?php if($login_status == "success"): ?>
    <script>
        Swal.fire({
            title: 'Success!',
            text: 'Loading your personalized dashboard...',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false,
            background: '#fff'
        }).then(() => { window.location.href = "titanhome.php"; });
    </script>
    <?php elseif($login_status == "blocked"): ?>
    <script>
        Swal.fire({
            title: 'Account Restricted',
            text: 'Your account is pending or inactive. Please contact support.',
            icon: 'warning',
            confirmButtonColor: '#3085d6'
        });
    </script>
    <?php elseif($login_status == "failed"): ?>
    <script>
        Swal.fire({
            title: 'Error',
            text: 'Invalid Credentials. Please check your ID and Password.',
            icon: 'error',
            confirmButtonColor: '#fb1752'
        });
    </script>
    <?php endif; ?>

    <script src="../../../vendors/bootstrap/bootstrap.min.js"></script>
    <script src="../../../assets/js/theme.js"></script>
</body>
</html>