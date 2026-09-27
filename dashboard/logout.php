<?php
// 1. Session Start
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// 2. Sabhi Session Variables ko khaali karein
$_SESSION = array();

// 3. Agar session cookie use ho rahi hai, toh use bhi expire karein (Security ke liye)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Session Destroy karein
session_destroy();

// Ab PHP se direct redirect nahi karenge, balki stylish UI dikhayenge
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - ApiNexus</title>
    <!-- Fonts & SweetAlert2 -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Falcon theme jaisa soft background */
        body {
            background-color: #f9fafd; 
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
    </style>
</head>
<body>

    <script>
        $(document).ready(function() {
            // Stylish SweetAlert2 Logout Animation
            Swal.fire({
                title: 'Logging Out...',
                html: 'Please wait, securely closing your session.',
                icon: 'info',
                allowEscapeKey: false,
                allowOutsideClick: false,
                showConfirmButton: false,
                timer: 1500, // 1.5 seconds ka timer
                timerProgressBar: true,
                didOpen: () => {
                    Swal.showLoading();
                }
            }).then((result) => {
                // Timer khatam hone ke baad login page pe bhej do
                window.location.href = 'login.php?msg=logout_success';
            });
        });
    </script>

</body>
</html>