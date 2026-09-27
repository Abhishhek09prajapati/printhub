<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once('../titancore/titanconfig.php');

$uid = $_SESSION['user_id'];
$u_phone = $_SESSION['phone'] ?? '';
$u_name = $_SESSION['user_name'] ?? 'User';

$user_res = $conn->query("SELECT * FROM users WHERE id = '$uid'");
$udata = $user_res->fetch_assoc();
$current_wallet = floatval($udata['wallet'] ?? 0);

$fee = 7.00; 
$price_res = $conn->query("SELECT price FROM pricing WHERE service_name='pan_advance' AND status='1'");
if ($price_res && $price_res->num_rows > 0) {
    $fee = floatval($price_res->fetch_assoc()['price']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'fetch_pan') {
    header('Content-Type: application/json');
    
    $pan_no = mysqli_real_escape_string($conn, trim($_POST['panNumber']));
    
    $api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
    $TitanApi_Url = $api_settings['titanurl'] ?? '';
    $api_key = $api_settings['titan_api_key'] ?? '';

    if (empty($TitanApi_Url) || empty($api_key)) {
        echo json_encode(['status' => 'error', 'msg' => 'API Configuration missing.']);
        exit();
    }

    $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/Pan_Details.php?api_key=" . urlencode($api_key) . "&pan_no=" . urlencode($pan_no);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $api_response = curl_exec($ch);
    curl_close($ch);

    if ($api_response) {
        $data = json_decode($api_response, true);
        
        if (isset($data['result']['status']) && $data['result']['status'] == "100") {
            $res = $data['result'];
            
            $formatted_dob = "";
            if (!empty($res['dob'])) {
                $formatted_dob = date('d/m/Y', strtotime($res['dob']));
            }
            
            $formatted_gender = "";
            if (strtoupper($res['gender']) == 'M') $formatted_gender = 'Male';
            elseif (strtoupper($res['gender']) == 'F') $formatted_gender = 'Female';
            else $formatted_gender = 'Transgender';

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'name' => $res['name'] ?? '',
                    'fatherName' => $res['fatherName'] ?? '',
                    'dob' => $formatted_dob,
                    'gender' => $formatted_gender
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid PAN or details not found!']);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Could not connect to Master API.']);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'save_pan') {
    
    header('Content-Type: application/json'); 
    
    if ($current_wallet < $fee) {
        echo json_encode(['status' => 'error', 'msg' => "Insufficient Wallet Balance! Required: ₹{$fee}"]);
        exit();
    } 

    $name = mysqli_real_escape_string($conn, strtoupper(trim($_POST['name'] ?? '')));
    $fathername = mysqli_real_escape_string($conn, strtoupper(trim($_POST['fathername'] ?? '')));
    $panNumber = mysqli_real_escape_string($conn, strtoupper(trim($_POST['panNumber'] ?? '')));
    $dob = mysqli_real_escape_string($conn, trim($_POST['dobadhar'] ?? ''));
    $gender = mysqli_real_escape_string($conn, trim($_POST['gender'] ?? ''));
    
    $order_id = "PAN" . time() . rand(100, 999);

    if (!empty($_POST['image_data']) && !empty($_POST['sign_data'])) {
        $base64Image = $_POST['image_data'];
        $base64Sign = $_POST['sign_data'];

        $printData = [
            'name' => $name, 'fathername' => $fathername, 'panNumber' => $panNumber,
            'dob' => $dob, 'gender' => $gender, 'imgdata' => $base64Image, 'signdata' => $base64Sign
        ];
        
        $json_data = mysqli_real_escape_string($conn, json_encode($printData, JSON_UNESCAPED_UNICODE));

        $insert_query = "INSERT INTO `panadvance_history` (
            `order_id`, `userid`, `name`, `pan_number`, `service_type`, `print_data`, `amount_cut`, `status`
        ) VALUES (
            '$order_id', '$uid', '$name', '$panNumber', 'PAN ADVANCE', '$json_data', '$fee', 'Success'
        )";

        if (mysqli_query($conn, $insert_query)) {
            $new_bal = $current_wallet - $fee;
            if ($conn->query("UPDATE users SET wallet = '$new_bal' WHERE id = '$uid'")) {
                $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) 
                              VALUES ('$uid', '$fee', '$new_bal', 'PAN Advance Print ($panNumber)', '1', 'Debit')");
                
                echo json_encode(['status' => 'success', 'msg' => "PAN Record Saved & ₹{$fee} Deducted."]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Data saved but balance update failed.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Database Error! Data not saved.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Photo & Signature both required!']);
    }
    exit(); 
}

header('Content-Type: text/html; charset=utf-8');
require_once('../titancore/titanheader.php');
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-id-card text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Welcome, <?php echo htmlspecialchars($u_name); ?></h6>
                        <h4 class="text-primary fw-bold mb-0">PAN Advance <span class="text-info fw-medium">Application</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <div class="form-control form-control-sm d-flex align-items-center bg-white border-200 shadow-sm">
                        <span class="fas fa-wallet text-success me-2"></span>
                        <span class="fw-bold text-success">Wallet: ₹<?php echo number_format($current_wallet, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="panForm" autocomplete="off">
    <input type="hidden" name="action_type" id="action_type" value="save_pan">
    
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 border-top border-4 border-primary h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-1000 fw-bold"><i class="fas fa-edit me-2 text-primary"></i>Enter PAN Details</h5>
                    <div class="badge badge-subtle-danger fs-10 p-2 fw-bold"><i class="fas fa-rupee-sign me-1"></i> Print Fee: ₹<?php echo $fee; ?></div>
                </div>
                <div class="card-body p-4 bg-body-tertiary">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fs-10 text-900 fw-bold">PAN Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input class="form-control shadow-sm" name="panNumber" id="panNumber" type="text" placeholder="ABCDE1234F" maxlength="10" required>
                                <button class="btn btn-primary shadow-sm px-4 fw-bold" type="button" onclick="autoFetchPan()">
                                    <i class="fas fa-cloud-download-alt me-1"></i> Fetch Data
                                </button>
                            </div>
                            <small class="text-500">Enter PAN and click Fetch Data to auto-fill details below.</small>
                        </div>
                        <div class="col-md-6 mt-4">
                            <label class="form-label fs-10 text-900 fw-bold">Full Name <span class="text-danger">*</span></label>
                            <input class="form-control shadow-sm text-uppercase bg-white" name="name" type="text" placeholder="ENTER NAME" required>
                        </div>
                        <div class="col-md-6 mt-4">
                            <label class="form-label fs-10 text-900 fw-bold">Father's Name <span class="text-danger">*</span></label>
                            <input class="form-control shadow-sm text-uppercase bg-white" name="fathername" type="text" placeholder="FATHER NAME" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-10 text-900 fw-bold">Gender <span class="text-danger">*</span></label>
                            <select class="form-select shadow-sm bg-white" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Transgender">Transgender</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-10 text-900 fw-bold">Date of Birth <span class="text-danger">*</span></label>
                            <input class="form-control shadow-sm bg-white" name="dobadhar" type="text" placeholder="DD/MM/YYYY" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card shadow-sm border-0 border-top border-4 border-info">
                        <div class="card-header bg-light py-2 text-center">
                            <h6 class="mb-0 fw-bold fs-10">Applicant Photo (Under 100KB)</h6>
                        </div>
                        <div class="card-body text-center bg-body-tertiary">
                            <div class="border rounded bg-white d-flex align-items-center justify-content-center mx-auto mb-3" style="height: 140px; width:120px; overflow: hidden;">
                                <img id="imgPreview" src="https://via.placeholder.com/120x140?text=Photo" style="max-height: 100%;">
                            </div>
                            <input type="file" id="img_up" class="form-control form-control-sm" accept="image/png, image/jpeg" onchange="readImg(this);" required>
                            <input type="hidden" name="image_data" id="image_data">
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <div class="card shadow-sm border-0 border-top border-4 border-warning">
                        <div class="card-header bg-light py-2 text-center">
                            <h6 class="mb-0 fw-bold fs-10">Applicant Signature (Under 100KB)</h6>
                        </div>
                        <div class="card-body text-center bg-body-tertiary">
                            <div class="border rounded bg-white d-flex align-items-center justify-content-center mx-auto mb-3" style="height: 60px; width:150px; overflow: hidden;">
                                <img id="signPreview" src="https://via.placeholder.com/150x60?text=Sign" style="max-height: 100%;">
                            </div>
                            <input type="file" id="sign_up" class="form-control form-control-sm" accept="image/png, image/jpeg" onchange="readSign(this);" required>
                            <input type="hidden" name="sign_data" id="sign_data">
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <button type="button" class="btn btn-success w-100 shadow-sm fw-bold py-3" onclick="submitAjaxForm()">
                        <i class="fas fa-save me-2"></i>Save & Generate Print (₹<?php echo $fee; ?>)
                    </button>
                </div>
            </div>
        </div>

    </div>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.getElementById('panNumber').addEventListener('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });

    function autoFetchPan() {
        let pan = $('#panNumber').val().trim();
        
        if (pan.length !== 10) {
            Swal.fire('Warning', 'Please enter a valid 10-digit PAN number.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Fetching Data...',
            text: 'Connecting to server to fetch details...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        let fetchFormData = new FormData();
        fetchFormData.append('action_type', 'fetch_pan');
        fetchFormData.append('panNumber', pan);

        fetch(window.location.href, {
            method: 'POST',
            body: fetchFormData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                $('input[name="name"]').val(data.data.name);
                $('input[name="fathername"]').val(data.data.fatherName);
                $('input[name="dobadhar"]').val(data.data.dob);
                $('select[name="gender"]').val(data.data.gender);
                
                Swal.fire('Found!', 'PAN details auto-filled successfully.', 'success');
            } else {
                Swal.fire('Error', data.msg, 'error');
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Server connection failed while fetching.', 'error');
        });
    }

    function readImg(input) {
        if (input.files && input.files[0]) {
            if(input.files[0].size > 102400) { Swal.fire('Error','Photo size must be under 100KB','error'); input.value=''; return; }
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#imgPreview').attr('src', e.target.result);
                $('#image_data').val(e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function readSign(input) {
        if (input.files && input.files[0]) {
            if(input.files[0].size > 102400) { Swal.fire('Error','Signature size must be under 100KB','error'); input.value=''; return; }
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#signPreview').attr('src', e.target.result);
                $('#sign_data').val(e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function submitAjaxForm() {
        let form = document.getElementById('panForm');
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        if (!$('#image_data').val() || !$('#sign_data').val()) {
            Swal.fire('Warning', 'Please upload both Photo and Signature.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: '₹<?php echo $fee; ?> will be deducted from your wallet.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Submit!'
        }).then((result) => {
            if (result.isConfirmed) {
                
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait while we save your data.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $('#action_type').val('save_pan');
                let formData = new FormData(form);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.msg,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = 'pan_advance_list.php'; 
                        });
                    } else {
                        Swal.fire('Error', data.msg, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Server Error', 'Failed to connect to the server. Data might be too large.', 'error');
                });
            }
        });
    }
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>