<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once('../titancore/titanconfig.php');

$uid = $_SESSION['user_id'];
$u_phone = $_SESSION['phone'] ?? $_SESSION['user_id'];
$u_name = $_SESSION['user_name'] ?? 'User';
$swal_msg = "";
$show_form = true;

$settings_res = $conn->query("SELECT site_name FROM settings LIMIT 1");
$web = $settings_res->fetch_assoc();
$display_name = $web['site_name'] ?? 'ApiNexus';

$user_res = $conn->query("SELECT * FROM users WHERE id = '$uid'");
$udata = $user_res->fetch_assoc();
$current_wallet = $udata['wallet'] ?? 0;

$price_res = $conn->query("SELECT price FROM pricing WHERE service_name='aadhar_advance_biometric_fee'");
$fee = ($price_res && $price_res->num_rows > 0) ? $price_res->fetch_assoc()['price'] : 10;

$api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
$TitanApi_Url = rtrim($api_settings['titanurl'] ?? 'https://titanapi.in', '/');
$api_key = $api_settings['titan_api_key'] ?? '';

$aadharno = "";
$name = "";
$fname = "";
$dob = "";
$gender = "";
$address = "";
$photo_data = "";
$data_missing_fields = [];
$full_address_with_father = "";

$raw_pid = $_POST['pid_data'] ?? '';
$aadhar  = trim($_POST['aadhar'] ?? '');

if (!empty($raw_pid) && !empty($aadhar)) {
    
    if ($current_wallet < $fee) {
        $swal_msg = "Swal.fire('Low Balance!', 'You need ₹$fee in your wallet.', 'warning').then(() => { window.location.href='aadhar_advance_biometric.php'; });";
        $show_form = false;
    } else {
        $endpoint = $TitanApi_Url . "/api/v1/Aadhar_Advance.php";
        
        $post_data = json_encode([
            'action' => 'verify',
            'aadhaar_number' => $aadhar,
            'pid_data' => $raw_pid,
            'api_key' => $api_key
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            $swal_msg = "Swal.fire('Connection Error!', '$curl_error', 'error').then(() => { window.location.href='aadhar_advance_biometric.php'; });";
            $show_form = false;
        } else {
            $result = json_decode($response, true);
            
            if (isset($result['success']) && $result['success'] === true) {
                
                $new_bal = $current_wallet - $fee;
                $conn->query("UPDATE users SET wallet = '$new_bal' WHERE id = '$uid'");
                $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$fee', '$new_bal', 'Aadhar Advance Biometric', '1', 'Debit')");
                
                $data = $result['data'];
                $aadharno = $data['aadhaar_number'] ?? $data['aadhaarNumber'] ?? $aadhar;
                
                // Check for missing/NA values in each field
                $name = $data['name'] ?? '';
                if (empty($name) || trim($name) == '' || strtoupper(trim($name)) == 'NA') {
                    $name = '';
                    $data_missing_fields[] = 'Name';
                }
                
                $fname = $data['father_name'] ?? $data['co'] ?? '';
                if (empty($fname) || trim($fname) == '' || strtoupper(trim($fname)) == 'NA') {
                    $fname = '';
                    $data_missing_fields[] = 'Father/Husband Name';
                }
                
                $dob_raw = $data['dob'] ?? '';
                $dob = strstr($dob_raw, 'T', true) ?: $dob_raw;
                if (empty($dob) || trim($dob) == '' || strtoupper(trim($dob)) == 'NA') {
                    $dob = '';
                    $data_missing_fields[] = 'Date of Birth';
                }
                
                $raw_gender = strtoupper($data['gender'] ?? '');
                $gender = ($raw_gender == 'F' || $raw_gender == 'FEMALE') ? 'Female' : 'Male';
                if ($raw_gender == 'NA' || empty($raw_gender)) {
                    $gender = '';
                    $data_missing_fields[] = 'Gender';
                }
                
                $address = $data['address'] ?? $data['full_address'] ?? '';
                if (empty($address) || trim($address) == '' || strtoupper(trim($address)) == 'NA') {
                    $address = '';
                    $data_missing_fields[] = 'Address';
                }
                
                // FORCE REMOVE S/O, W/O, D/O, C/O from father name so it does not duplicate
                $fname = preg_replace('/^(S\/O|W\/O|D\/O|C\/O|s\/o|w\/o|d\/o|c\/o)[\s:\-]*/i', '', trim($fname));
                
                // Clean the address just in case it already contains S/O FatherName
                $clean_address = preg_replace('/^(S\/O|W\/O|D\/O|C\/O|s\/o|w\/o|d\/o|c\/o)[\s:\-]*[^,]+,\s*/i', '', trim($address));
                $clean_address = preg_replace('/^(S\/O|W\/O|D\/O|C\/O|s\/o|w\/o|d\/o|c\/o)[\s:\-]*[^,]+$/i', '', trim($clean_address));

                // Create full address with father name accurately (Only 1 S/O)
                if (!empty($fname) && !empty($clean_address)) {
                    $full_address_with_father = "S/O " . $fname . ", " . $clean_address;
                } elseif (!empty($fname) && empty($clean_address)) {
                    $full_address_with_father = "S/O " . $fname;
                } elseif (empty($fname) && !empty($clean_address)) {
                    $full_address_with_father = $clean_address;
                } else {
                    $full_address_with_father = "";
                }
                
                $photo_url_from_api = $data['photo'] ?? '';
                $photo_data = "";
                
                if (!empty($photo_url_from_api)) {
                    if (strpos($photo_url_from_api, 'http') === 0) {
                        $photo_data = $photo_url_from_api; 
                    } else {
                        $photo_data = $photo_url_from_api;
                    }
                }
                
                // Generate warning message for missing fields
                if (!empty($data_missing_fields)) {
                    $missing_list = implode(', ', $data_missing_fields);
                    $swal_msg = "Swal.fire({
                        title: 'Partial Data Received!',
                        html: '<strong>Service is slow or data incomplete!</strong><br><br>The following fields returned NA/Empty:<br><span class=\"text-warning\">" . addslashes($missing_list) . "</span><br><br>You can manually fill/edit these details below before saving.',
                        icon: 'warning',
                        confirmButtonText: 'OK, I will fill manually'
                    });";
                } else {
                    $swal_msg = "Swal.fire('Success!', 'Aadhaar details fetched successfully!', 'success');";
                }
                
            } else {
                $err = $result['error'] ?? $result['message'] ?? 'Verification failed';
                $swal_msg = "Swal.fire('Verification Failed!', '" . addslashes($err) . "', 'error').then(() => { window.location.href='aadhar_advance_biometric.php'; });";
                $show_form = false;
            }
        }
    }
}

if (isset($_POST['save_data'])) {
    $aadharno = mysqli_real_escape_string($conn, trim($_POST['aadharno']));
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    
    // Ensure S/O is completely stripped before saving to the DB for fathername column
    $fathername = mysqli_real_escape_string($conn, trim($_POST['fathername']));
    $fathername = preg_replace('/^(S\/O|W\/O|D\/O|C\/O|s\/o|w\/o|d\/o|c\/o)[\s:\-]*/i', '', $fathername);
    
    $dobadhar = mysqli_real_escape_string($conn, trim($_POST['dobadhar']));
    $gender = mysqli_real_escape_string($conn, trim($_POST['gender']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $photo_url_or_base64 = $_POST['photo_data'] ?? '';
    
    $language = mysqli_real_escape_string($conn, trim($_POST['language'] ?? ''));
    $namelocal = mysqli_real_escape_string($conn, trim($_POST['namelocal'] ?? ''));
    $addresslocal = mysqli_real_escape_string($conn, trim($_POST['addresslocal'] ?? ''));
    $doblocal = mysqli_real_escape_string($conn, trim($_POST['doblocal'] ?? ''));
    $genderlocal = mysqli_real_escape_string($conn, trim($_POST['genderlocal'] ?? ''));
    
    $originalaadharno = substr($aadharno, 0, 4) . ' ' . substr($aadharno, 4, 4) . ' ' . substr($aadharno, 8, 4);
    
    $photo_path = "";
    
    if (!empty($photo_url_or_base64)) {
        
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/dashboard/aadharpvc/imgadvance/";
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $photo_name = time() . "_" . rand(1000, 9999) . ".jpg";
        $full_path = $upload_dir . $photo_name;
        $success = false;
        
        if (strpos($photo_url_or_base64, 'http') === 0) {
            $ch = curl_init($photo_url_or_base64);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $image_data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200 && $image_data !== false && strlen($image_data) > 1000) {
                if (file_put_contents($full_path, $image_data)) {
                    $photo_path = "aadharpvc/imgadvance/" . $photo_name;
                    $success = true;
                }
            }
        } 
        else if (strlen($photo_url_or_base64) > 100 && strpos($photo_url_or_base64, 'base64') !== false) {
            $clean_photo = $photo_url_or_base64;
            $clean_photo = preg_replace('#^data:image/[^;]+;base64,#', '', $clean_photo);
            $clean_photo = str_replace(' ', '+', $clean_photo);
            $image_data = base64_decode($clean_photo);
            
            if ($image_data !== false && strlen($image_data) > 1000) {
                if (file_put_contents($full_path, $image_data)) {
                    $photo_path = "aadharpvc/imgadvance/" . $photo_name;
                    $success = true;
                }
            }
        }
        else if (strlen($photo_url_or_base64) > 10 && strlen($photo_url_or_base64) < 200) {
            $full_url = $photo_url_or_base64;
            if (strpos($full_url, '//') === false) {
                $full_url = 'https://titanapi.in/' . ltrim($full_url, '/');
            }
            
            $ch = curl_init($full_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $image_data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200 && $image_data !== false && strlen($image_data) > 1000) {
                if (file_put_contents($full_path, $image_data)) {
                    $photo_path = "aadharpvc/imgadvance/" . $photo_name;
                    $success = true;
                }
            }
        }
    }
    
    $srno_res = $conn->query("SELECT MAX(srno) as max_srno FROM aadharadvance");
    $srno_row = $srno_res->fetch_assoc();
    $srno = ($srno_row['max_srno'] ?? 0) + 1;
    
    $user_identifier = $u_phone;
    
    $insert_q = "INSERT INTO aadharadvance 
                (srno, aadharno, originalaadharno, aadharname, fathername, dob, gender, fulladdress, photo, 
                 locallanguage, localname, localaddress, localdob, localgender, userid, createdatetime) 
                VALUES 
                ('$srno', '$aadharno', '$originalaadharno', '$name', '$fathername', '$dobadhar', '$gender', '$address', '$photo_path',
                 '$language', '$namelocal', '$addresslocal', '$doblocal', '$genderlocal', '$user_identifier', NOW())";
    
    if ($conn->query($insert_q)) {
        $swal_msg = "Swal.fire('Success!', 'Aadhaar Details Saved Successfully!', 'success').then(() => { window.location.href='aadhar_advance_list.php'; });";
    } else {
        $swal_msg = "Swal.fire('Error!', 'Database error: " . addslashes($conn->error) . "', 'error')";
    }
}

require_once('../titancore/titanheader.php');
?>

<style>
.local-language-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
    border: 1px solid #dee2e6;
}
.language-select-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
}
.language-select-card select {
    background: white;
    border: none;
    border-radius: 8px;
    padding: 10px;
    font-weight: bold;
    width: 100%;
}
.translate-field {
    background-color: #fff !important;
}
.translate-label {
    font-weight: bold;
    color: #856404;
    font-size: 12px;
}
.na-warning {
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 5px;
}
.missing-field {
    border: 2px solid #ffc107 !important;
    background-color: #fffbf0 !important;
}
.info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
}
</style>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Welcome back, <?php echo htmlspecialchars($u_name); ?></h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($display_name); ?> <span class="text-info fw-medium">Aadhaar Advance Biometric</span></h4>
                    </div>
                </div>
                <div class="col-md-auto p-3">
                    <div class="form-control form-control-sm d-flex align-items-center bg-white border-200 shadow-sm" style="min-width: 140px;">
                        <span class="fas fa-wallet text-success me-2"></span>
                        <span class="fw-bold text-success">Wallet: ₹<?php echo number_format($current_wallet, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if($show_form && !empty($aadharno)): ?>
<div class="row g-3">
    <div class="col-12">
        <div class="card shadow-sm border-0 border-top border-4 border-success">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-success fw-bold"><i class="bx bx-check-circle me-2"></i>Verified Aadhaar Details</h5>
                <a href="https://www.google.com/intl/sa/inputtools/try/" target="_blank" class="btn btn-falcon-default btn-sm">
                    <i class="fas fa-language me-1 text-info"></i> Google Input Tools
                </a>
            </div>
            <div class="card-body p-4 bg-body-tertiary">
                <?php if(!empty($data_missing_fields)): ?>
                <div class="na-warning">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    <strong>⚠️ Service is slow or data incomplete!</strong><br>
                    The following fields returned NA/Empty: <span class="text-warning fw-bold"><?php echo implode(', ', $data_missing_fields); ?></span><br>
                    <small class="text-muted">You can manually fill/edit these details below.</small>
                </div>
                <?php endif; ?>
                
                <div class="info-card mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> Father's name will automatically be included in the full address with "S/O" prefix.
                </div>
                
                <form method="post" autocomplete="off" id="saveForm">
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-2 text-center">
                            <?php if (!empty($photo_data)): ?>
                                <?php if (strpos($photo_data, 'http') === 0): ?>
                                    <img src="<?php echo $photo_data; ?>" class="img-thumbnail rounded-circle" style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #28a745;" id="photo_preview">
                                <?php elseif(strlen($photo_data) > 100): ?>
                                    <img src="<?php echo $photo_data; ?>" class="img-thumbnail rounded-circle" style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #28a745;" id="photo_preview">
                                <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto border" style="width:120px; height:120px;">
                                        <i class="fas fa-image fa-4x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <input type="hidden" name="photo_data" id="photo_data" value="<?php echo htmlspecialchars($photo_data); ?>">
                                <small class="text-success">Photo Received</small>
                            <?php else: ?>
                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto border" style="width:120px; height:120px;">
                                    <i class="fas fa-user fa-4x text-muted"></i>
                                </div>
                                <input type="hidden" name="photo_data" id="photo_data" value="">
                                <small class="text-danger">No Photo</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-10">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-primary">Aadhaar Number</label>
                                    <input type="text" class="form-control bg-white" name="aadharno" value="<?php echo htmlspecialchars($aadharno); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-primary">Full Name (English)</label>
                                    <input type="text" class="form-control bg-white <?php echo empty($name) ? 'missing-field' : ''; ?>" id="name_en" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="<?php echo empty($name) ? 'Enter name manually' : ''; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-primary">Father/Husband Name</label>
                                    <input type="text" class="form-control bg-white <?php echo empty($fname) ? 'missing-field' : ''; ?>" id="father_name" name="fathername" value="<?php echo htmlspecialchars($fname); ?>" placeholder="<?php echo empty($fname) ? 'Enter father name manually' : ''; ?>" onkeyup="updateFullAddress()">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-primary">Date of Birth</label>
                                    <input type="text" class="form-control bg-white <?php echo empty($dob) ? 'missing-field' : ''; ?>" id="dob_en" name="dobadhar" value="<?php echo htmlspecialchars($dob); ?>" placeholder="<?php echo empty($dob) ? 'YYYY-MM-DD' : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-bold text-primary">Gender</label>
                                    <input type="text" class="form-control bg-white <?php echo empty($gender) ? 'missing-field' : ''; ?>" id="gender_en" name="gender" value="<?php echo htmlspecialchars($gender); ?>" placeholder="<?php echo empty($gender) ? 'Male/Female' : ''; ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold text-primary">Address (with Father's Name)</label>
                                    <textarea class="form-control bg-white" id="address_en" name="address" rows="2" onkeyup="updateFullAddress()" placeholder="Address will be auto-generated with father's name"><?php 
                                        if (!empty($full_address_with_father)) {
                                            echo htmlspecialchars($full_address_with_father);
                                        } elseif (!empty($address)) {
                                            echo htmlspecialchars($address);
                                        }
                                    ?></textarea>
                                    <small class="text-muted">Father's name automatically included with "S/O" prefix. You can edit if needed.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="local-language-section">
                        <h5 class="mb-3 text-primary"><i class="fas fa-language me-2"></i>Local Language Translation</h5>
                        <p class="text-muted small mb-3">Select a language to automatically translate the details below. You can also edit them manually.</p>
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Select Language <span class="text-danger">*</span></label>
                                <select class="form-select" id="language" name="language" onchange="translateAllFields()" style="border: 2px solid #6f42c1;">
                                    <option value="">-- Select Language --</option>
                                    <option value="hi">हिन्दी (Hindi)</option>
                                    <option value="pa">ਪੰਜਾਬੀ (Punjabi)</option>
                                    <option value="gu">ગુજરાતી (Gujarati)</option>
                                    <option value="mr">मराठी (Marathi)</option>
                                    <option value="ta">தமிழ் (Tamil)</option>
                                    <option value="kn">ಕನ್ನಡ (Kannada)</option>
                                    <option value="bn">বাংলা (Bengali)</option>
                                    <option value="te">తెలుగు (Telugu)</option>
                                    <option value="or">ଓଡ଼ିଆ (Oriya)</option>
                                    <option value="sd">سنڌي (Sindhi)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label translate-label"><i class="fas fa-user me-1"></i> Name (Local Language)</label>
                                <input type="text" class="form-control translate-field" id="namelocal" name="namelocal" placeholder="Translation will appear here">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label translate-label"><i class="fas fa-calendar me-1"></i> जनम तिथि (DOB Local)</label>
                                <input type="text" class="form-control translate-field" id="doblocal" name="doblocal" placeholder="Translation will appear here">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label translate-label"><i class="fas fa-venus-mars me-1"></i> लिंग (Gender Local)</label>
                                <input type="text" class="form-control translate-field" id="genderlocal" name="genderlocal" placeholder="Translation will appear here">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label translate-label"><i class="fas fa-map-marker-alt me-1"></i> पता (Address Local)</label>
                                <textarea class="form-control translate-field" id="addresslocal" name="addresslocal" rows="2" placeholder="Translation will appear here"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="text-end border-top pt-3 mt-4">
                        <h6 class="text-danger d-inline-block me-4">Amount Deducted: ₹<?php echo number_format($fee, 2); ?></h6>
                        <button type="submit" name="save_data" class="btn btn-success px-5 py-2 fw-bold shadow-none">
                            <i class="fas fa-save me-2"></i> Save Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_msg; ?>
    
    function updateFullAddress() {
        var fatherName = document.getElementById('father_name').value.trim();
        var currentAddress = document.getElementById('address_en').value;
        
        // Remove any S/O variations from fatherName just in case user manually typed it
        var cleanFather = fatherName.replace(/^(S\/O|W\/O|D\/O|C\/O|s\/o|w\/o|d\/o|c\/o)[\s:\-]*/i, '').trim();
        
        // Remove existing S/O from address to rebuild it cleanly
        var cleanAddress = currentAddress.replace(/^(S\/O|W\/O|D\/O|C\/O|s\/o|w\/o|d\/o|c\/o)[\s:\-]*[^,]+,\s*/i, '');
        cleanAddress = cleanAddress.replace(/^(S\/O|W\/O|D\/O|C\/O|s\/o|w\/o|d\/o|c\/o)[\s:\-]*[^,]+$/i, '');
        
        if (cleanFather !== '') {
            if (cleanAddress !== '') {
                document.getElementById('address_en').value = "S/O " + cleanFather + ", " + cleanAddress.trim();
            } else {
                document.getElementById('address_en').value = "S/O " + cleanFather;
            }
        } else {
            document.getElementById('address_en').value = cleanAddress.trim();
        }
    }
    
    function translateAllFields() {
        var lang = document.getElementById('language').value;
        
        if(lang != '') {
            $('#namelocal, #doblocal, #genderlocal, #addresslocal').val('Translating...');
            
            var nameEn = document.getElementById('name_en').value;
            if(nameEn && nameEn.trim() != '') {
                translateText(nameEn, lang, function(result) {
                    document.getElementById('namelocal').value = result;
                });
            } else {
                document.getElementById('namelocal').value = '';
            }
            
            var genderEn = document.getElementById('gender_en').value;
            if(genderEn && genderEn.trim() != '') {
                translateText(genderEn, lang, function(result) {
                    if(lang == 'hi' && result == "नर") result = "पुरुष";
                    if(lang == 'hi' && result == "मादा") result = "महिला";
                    if(lang == 'pa' && result == "ਨਰ") result = "ਮਰਦ";
                    if(lang == 'pa' && result == "ਮਾਦਾ") result = "ਔਰਤ";
                    document.getElementById('genderlocal').value = result;
                });
            } else {
                document.getElementById('genderlocal').value = '';
            }
            
            var addressEn = document.getElementById('address_en').value;
            if(addressEn && addressEn.trim() != '') {
                translateText(addressEn, lang, function(result) {
                    document.getElementById('addresslocal').value = result;
                });
            } else {
                document.getElementById('addresslocal').value = '';
            }
            
            // EXACT REQUIREMENT: ONLY showing the local string ("जन्म तिथि"), NO DATE NUMBERS
            var dobText = document.getElementById('dob_en').value;
            if(dobText && dobText.trim() != '') {
                var dobPrefixes = {
                    'hi': 'जन्म तिथि',
                    'pa': 'ਜਨਮ ਮਿਤੀ',
                    'gu': 'જન્મ તારીખ',
                    'mr': 'जन्म तारीख',
                    'ta': 'பிறந்த தேதி',
                    'kn': 'ಹುಟ್ಟಿದ ದಿನಾಂಕ',
                    'bn': 'জন্ম তারিখ',
                    'te': 'పుట్టిన తేదీ',
                    'or': 'ଜନ୍ମ ତାରିଖ',
                    'sd': 'ڄمڻ جي تاريخ'
                };
                
                // Set the value strictly to the local text only
                document.getElementById('doblocal').value = dobPrefixes[lang] ? dobPrefixes[lang] : 'DOB';
            } else {
                document.getElementById('doblocal').value = '';
            }
            
        } else {
            document.getElementById('namelocal').value = '';
            document.getElementById('doblocal').value = '';
            document.getElementById('genderlocal').value = '';
            document.getElementById('addresslocal').value = '';
        }
    }
    
    function translateText(text, targetLang, callback) {
        var url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=" + targetLang + "&dt=t&q=" + encodeURIComponent(text);
        
        $.get(url, function(data) {
            var translated = '';
            if(data && data[0]) {
                for(var i = 0; i < data[0].length; i++) {
                    if(data[0][i] && data[0][i][0]) {
                        translated += data[0][i][0];
                    }
                }
                callback(translated);
            } else {
                callback(text);
            }
        }).fail(function() {
            callback(text);
        });
    }
    
    $(document).ready(function() {
        var selectedLang = document.getElementById('language').value;
        if(selectedLang != '') {
            translateAllFields();
        }
        
        // Auto-update address when father name is entered
        var fatherNameInput = document.getElementById('father_name');
        if (fatherNameInput) {
            fatherNameInput.addEventListener('input', function() {
                updateFullAddress();
            });
        }
    });
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>