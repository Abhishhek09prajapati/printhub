<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Auth & Configuration
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once('../titancore/titanconfig.php');

$uid = $_SESSION['user_id'];
$u_phone = $_SESSION['phone'] ?? $_SESSION['user_id']; // FIX: Use phone or user_id
$u_name = $_SESSION['user_name'] ?? 'User';
$u_role = $_SESSION['role'] ?? 'retailer';
$swal_msg = "";

// Fetch Site Settings for Header
$settings_res = $conn->query("SELECT site_name FROM settings LIMIT 1");
$web = $settings_res->fetch_assoc();
$display_name = $web['site_name'] ?? 'ApiNexus';

// Fetch Current User & Wallet Data
$user_res = $conn->query("SELECT * FROM users WHERE id = '$uid'");
$udata = $user_res->fetch_assoc();
$current_wallet = $udata['wallet'] ?? 0;

// Fetch Pricing
$price_res = $conn->query("SELECT price FROM pricing WHERE service_name='aadhar_manual_fee'");
$fee = ($price_res && $price_res->num_rows > 0) ? $price_res->fetch_assoc()['price'] : 1; 

require_once('../titancore/titanheader.php');

// ==========================================
// HANDLE FORM SUBMISSION & SAVE DATA
// ==========================================
if(isset($_POST['savedataauto'])) {	
    $aadharno = trim($_POST['aadharno']);
    $name = trim($_POST['name']);
    $fathername = trim($_POST['fathername']);
    $dobadhar = trim($_POST['dobadhar']);
    $birthtithilocal = trim($_POST['birthtithilocal']);
    $gender = strtoupper(trim($_POST['gender']));
    $genderlocal = trim($_POST['genderlocal']);
    $address = trim($_POST['address']);
    $language = trim($_POST['language']);
    $namelocal = trim($_POST['namelocal']);
    $localaddress = trim($_POST['addresslocal']);
    $patalocal = trim($_POST['patalocal']);
    $houseno = trim($_POST['houseno']);
    $street = trim($_POST['street']); 
    $vtcandpost = trim($_POST['vtcandpost']);
    $dist = trim($_POST['dist']);
    $statename = trim($_POST['statename']);
    $pincode = trim($_POST['pincode']);

    // Validate required fields
    if ($current_wallet < $fee){
        $swal_msg = "Swal.fire('Error', 'Wallet Balance is Low! Please Recharge.', 'error');";
    } elseif (empty($aadharno) || empty($name)) {
        $swal_msg = "Swal.fire('Warning', 'Aadhar Card No and Name are required.', 'warning');";
    } elseif (empty($_FILES['imagefile']['name'])) {
        $swal_msg = "Swal.fire('Warning', 'Applicant Photo is required.', 'warning');";
    } else {
        // Check for duplicate Aadhaar number
        $check_dup = $conn->query("SELECT aadharno FROM aadharmanual WHERE aadharno='$aadharno'");
        if ($check_dup && $check_dup->num_rows > 0) {
            $swal_msg = "Swal.fire('Error', 'This Aadhar Card No Already Exists.', 'error');";
        } else {
            // Format Aadhaar number with spaces
            $adhrno = substr($aadharno, 0, 4) . ' ' . substr($aadharno, 4, 4) . ' ' . substr($aadharno, 8, 4);
            $sex = ($gender == 'MALE' || $gender == 'M') ? 'M' : 'F';
            
            // Photo Upload
            $target_file = "";
            $target_dir = "aadharpvc/imgmanualaadhaar/";
            if(!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $target_file = $target_dir . md5(time() . $_FILES["imagefile"]["name"]) . ".jpg";
            
            if(move_uploaded_file($_FILES["imagefile"]["tmp_name"], $target_file)) {
                // Deduct Wallet & History
                $new_bal = $current_wallet - $fee;
                $conn->query("UPDATE users SET wallet = '$new_bal' WHERE id = '$uid'");
                $conn->query("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$fee', '$new_bal', 'Aadhar Manual Print', '1', 'Debit')");

                // Commission Distribution Logic
                $parentid = $udata['refrenceid'] ?? 0;
                $discom = isset($slct['discom']) ? $slct['discom'] : 0; 
                if($parentid != 0 && $discom > 0) {
                    $conn->query("INSERT INTO commission_report(userid, commission, cardprint, refid, date) VALUES ('$parentid', '$discom', 1, '$uid', CURDATE())");
                    $conn->query("UPDATE users SET wallet = wallet + $discom WHERE id = '$parentid'");
                }

                // Get next SRNO
                $srno_res = $conn->query("SELECT MAX(srno) as max_srno FROM aadharmanual");
                $srno_row = $srno_res->fetch_assoc();
                $srno = ($srno_row['max_srno'] ?? 0) + 1;

                // ========== CRITICAL FIX: Store user identifier correctly ==========
                // Store phone number or user_id in userid column for record ownership
                $user_identifier = $u_phone; // Using phone number for consistency
                
                $insert_q = "INSERT INTO aadharmanual 
                            (aadharno, originalaadharno, aadharname, fathername, dob, gender, sexinlocal, fulladdress, locallanguage, localname, localaddress, imagepathoriginal, dobinlocal, pata, houseno, street, vtcandpost, dist, statename, pincode, srno, userid, createdatetime) 
                            VALUES 
                            ('$aadharno', '$adhrno', '$name', '$fathername', '$dobadhar', '$gender', N'$genderlocal', '$address', '$language', N'$namelocal', N'$localaddress', '$target_file', N'$birthtithilocal', N'$patalocal', '$houseno', '$street', '$vtcandpost', '$dist', '$statename', '$pincode', '$srno', '$user_identifier', NOW())";
                
                if($conn->query($insert_q)){
                    $swal_msg = "Swal.fire('Success', 'Aadhaar Details Saved Successfully', 'success').then(() => { window.location.href='aadharmanual_list.php'; });";
                } else {
                    $swal_msg = "Swal.fire('Error', 'Database error: " . addslashes($conn->error) . "', 'error');";
                }
            } else {
                $swal_msg = "Swal.fire('Error', 'Failed to upload photo!', 'error');";
            }
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Welcome back, <?php echo htmlspecialchars($u_name); ?></h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($display_name); ?> <span class="text-info fw-medium">Aadhaar Manual</span></h4>
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

<div class="row g-3">
    <div class="col-12">
        <div class="card shadow-sm border-0 border-top border-4 border-primary">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-1000 fw-bold"><i class="bx bxs-id-card me-2 text-primary"></i>Enter Aadhaar Details Manually</h5>
                <a href="https://www.google.com/intl/sa/inputtools/try/" target="_blank" class="btn btn-falcon-default btn-sm">
                    <i class="fas fa-language me-1 text-info"></i> Google Input Tools
                </a>
            </div>
            <div class="card-body p-4 bg-body-tertiary">
                <form method="post" autocomplete="off" enctype="multipart/form-data" onsubmit="return validation();">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Aadhaar Card No. <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="aadharno" name="aadharno" type="text" maxlength="12" placeholder="12 Digit Aadhaar Number" required>
                            <span id="erroraadharno" class="text-danger fs-11"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Full Name <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="name" name="name" type="text" placeholder="Applicant Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Father/Husband Name <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="fathername" name="fathername" type="text" placeholder="Guardian Name" oninput="setaddress()" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">House No</label>
                            <input class="form-control bg-white" id="houseno" name="houseno" type="text" oninput="setaddress()" placeholder="House/Flat No">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Gali, Locality</label>
                            <input class="form-control bg-white" id="streetlocality" name="street" type="text" oninput="setaddress()" placeholder="Street, Area, Locality">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Post Office</label>
                            <input class="form-control bg-white" id="vtcandpost" name="vtcandpost" type="text" oninput="setaddress()" placeholder="Post Office">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">City / District <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="city" name="dist" type="text" oninput="setaddress()" placeholder="District Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">State <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="state" name="statename" type="text" oninput="setaddress()" placeholder="State Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Pin code <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" id="pincode" name="pincode" type="text" maxlength="6" oninput="setaddress()" placeholder="6 Digit Pincode" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Date Of Birth <span class="text-danger">*</span></label>
                            <input class="form-control bg-white" name="dobadhar" type="date" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Date Of Birth (Local)</label>
                            <input class="form-control bg-white" id="birthtithilocal" name="birthtithilocal" type="text" placeholder="Auto Fill via Translation">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-10 text-900">Gender <span class="text-danger">*</span></label>
                            <select class="form-select bg-white" name="gender" id="gender" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-10 text-900">Gender (Local)</label>
                            <input class="form-control bg-white" id="genderlocal" name="genderlocal" type="text" placeholder="Auto Fill">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-9">
                            <label class="form-label fs-10 text-900">Full Address (English) <span class="text-danger">*</span></label>
                            <textarea class="form-control bg-white" id="txtSource" name="address" rows="3" placeholder="Address will auto-generate here..." required></textarea>
                            <span id="errortxtSource" class="text-danger fs-11"></span>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-10 text-900 text-danger fw-bold"><i class="fas fa-image me-1"></i>Applicant Photo <span class="text-danger">*</span></label>
                            <input type="file" name="imagefile" class="form-control bg-white" id="imgInp" accept="image/jpeg, image/png" required />
                            <div class="mt-2 text-center">
                                <img src="" id="blah" class="img-thumbnail" style="display: none; max-height: 100px;">
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-200 rounded mb-4 border">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fs-10 text-900 fw-bold">1. Select Local Language</label>
                                <select class="form-select" onchange="changelang()" name="language" id="language" required>
                                    <option value="">SELECT</option>
                                    <option value="hi">Hindi</option>
                                    <option value="pa">Punjabi</option>
                                    <option value="gu">Gujarati</option>
                                    <option value="mr">Marathi</option>
                                    <option value="kn">Kannada</option>
                                    <option value="bn">Bengali</option>
                                    <option value="te">Telugu</option>
                                    <option value="or">Oriya</option>
                                    <option value="sd">Sindhi</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs-10 text-900">Name (Local) <span class="text-danger">*</span></label>
                                <input class="form-control bg-white" id="name_regional" name="namelocal" type="text" placeholder="Auto Translate" readonly required>
                                <span id="errorname_regional" class="text-danger fs-11"></span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-10 text-900">Address (Local) <span class="text-danger">*</span></label>
                                <textarea class="form-control bg-white" id="txtTarget" name="addresslocal" rows="1" placeholder="Auto Translate" readonly required></textarea>
                                <span id="errortxtTarget" class="text-danger fs-11"></span>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" id="pata" value="address">
                    <input type="hidden" id="patalocal" name="patalocal" value="">

                    <div class="text-end border-top pt-3">
                        <h6 class="text-danger d-inline-block me-4">Application Fee: ₹<?php echo $fee; ?></h6>
                        <button type="submit" name="savedataauto" class="btn btn-falcon-success px-5 fw-semi-bold shadow-none">
                            <i class="fas fa-check-circle me-2"></i>Save & Deduct ₹<?php echo $fee; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Sweet Alert Logic
    <?php echo $swal_msg; ?>

    // Image Preview
    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#blah').attr('src', e.target.result).show();
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    $("#imgInp").change(function(){ readURL(this); });

    // Address Builder
    function setaddress(){
        var fathername = document.getElementById('fathername').value;
        var houseno = document.getElementById('houseno').value;
        var streetlocality = document.getElementById('streetlocality').value;
        var vtcandpost = document.getElementById('vtcandpost').value;
        var state = document.getElementById('state').value;
        var city = document.getElementById('city').value;
        var pincode = document.getElementById('pincode').value;
        
        var addressParts = [];
        if(fathername) addressParts.push("S/O : " + fathername);
        if(houseno) addressParts.push(houseno);
        if(streetlocality) addressParts.push(streetlocality);
        if(vtcandpost) addressParts.push(vtcandpost);
        if(city) addressParts.push(city);
        if(state) addressParts.push(state);
        if(pincode) addressParts.push("- " + pincode);

        var fullAddress = addressParts.join(", ");
        document.getElementById('txtSource').value = fullAddress;
        
        // Auto-trigger translation if language is selected
        if(document.getElementById('language').value != "") {
            changelang();
        }
    }

    // Form Validation
    function validation() {
        var txtSource = document.getElementById('txtSource').value;
        if (txtSource.trim() == "") {
            document.getElementById('errortxtSource').innerHTML = " **Please Enter Address !!!";
            document.getElementById('txtSource').style.border = "1px solid red";
            return false;
        }
        var name_regional = document.getElementById('name_regional').value;
        if (name_regional.trim() == "") {
            document.getElementById('errorname_regional').innerHTML = " **Please Select Language to Translate !!!";
            document.getElementById('name_regional').style.border = "1px solid red";
            return false;
        }
        var txtTarget = document.getElementById('txtTarget').value;
        if (txtTarget.trim() == "") {
            document.getElementById('errortxtTarget').innerHTML = " **Please Select Language to Translate !!!";
            document.getElementById('txtTarget').style.border = "1px solid red";
            return false;
        }
        
        // Validate Aadhaar number
        var aadharno = document.getElementById('aadharno').value;
        if(aadharno.length != 12 || isNaN(aadharno)) {
            document.getElementById('erroraadharno').innerHTML = " **Please enter valid 12 digit Aadhaar number !!!";
            return false;
        }
        
        return true;
    }

    // Google Translate API Logic
    function changelang() {
        var lang = document.getElementById("language").value;
        if(lang == "") return;

        function translateField(sourceId, targetId, callback = null) {
            var text = $("#" + sourceId).val();
            if(text == "") return;
            var url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=" + lang + "&dt=t&q=" + encodeURI(text);
            
            $.get(url, function (data) {
                var result = '';
                for(var i=0; i<data[0].length; i++) {
                    result += data[0][i][0];
                }
                $("#" + targetId).val(result);
                if(callback) callback(result);
            }).fail(function() {
                console.log("Translation failed for: " + sourceId);
            });
        }

        // Translate fields
        translateField("txtSource", "txtTarget");
        translateField("name", "name_regional");
        
        var birthTithiText = "Birth Tithi"; // You can customize this
        $("#birthtithilocal").val(birthTithiText);
        
        // Translate gender with overrides
        var genderText = $("#gender").find(":selected").text();
        if(genderText) {
            translateField("gender_text_field", "genderlocal", function(result) {
                // Override for specific languages
                if(lang == 'hi' && result == "नर") $('#genderlocal').val("पुरुष");
                if(lang == 'pa' && result == "ਨਰ") $('#genderlocal').val("ਮਰਦ");
            });
        }
    }
    
    // Helper for gender translation
    $(document).ready(function() {
        // Create hidden field for gender text
        $("<input>").attr({
            type: "hidden",
            id: "gender_text_field",
            value: ""
        }).appendTo("form");
        
        $("#gender").change(function() {
            $("#gender_text_field").val($(this).find(":selected").text());
            if($("#language").val()) changelang();
        });
        
        // Trigger address build on page load if fields have values
        if($("#fathername").val()) setaddress();
    });
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>