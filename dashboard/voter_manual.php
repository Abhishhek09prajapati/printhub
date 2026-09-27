<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once('../titancore/titanconfig.php');

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");
mysqli_query($conn, "SET CHARACTER SET 'utf8mb4'");

$uid = mysqli_real_escape_string($conn, $_SESSION['user_id']);
$u_phone = mysqli_real_escape_string($conn, $_SESSION['phone'] ?? '');
$u_name = $_SESSION['user_name'] ?? 'User';
$swal_msg = "";

$user_res = $conn->query("SELECT * FROM users WHERE id = '$uid'");
$udata = $user_res->fetch_assoc();
$current_wallet = $udata['wallet'] ?? 0;

$price_res = $conn->query("SELECT price FROM pricing WHERE service_name='voter_manual_titan'");
$fee = ($price_res && $price_res->num_rows > 0) ? $price_res->fetch_assoc()['price'] : 10; 

require_once('../titancore/titanheader.php');

if (isset($_POST['savedata'])) {
    $votername = strtoupper(trim($_POST['name']));
    $namelocal = trim($_POST['namelocal']);
    $gender = trim($_POST['gender']);
    $genderlocal = trim($_POST['genderlocal']);
    $dob = trim($_POST['dobadhar']); 
    $relation_type = trim($_POST['father_husband']);
    $fathername = strtoupper(trim($_POST['fathername']));
    $fathernamelocal = trim($_POST['fathernamelocal']);
    $epicno = trim($_POST['epicno']);
    $policestation = trim($_POST['policestation']);
    $tahshil = trim($_POST['tahshil']);
    $dist = trim($_POST['district']);
    $pincode = trim($_POST['pincode']);
    $state = trim($_POST['statename']);
    $language = trim($_POST['language']);
    $address = trim($_POST['address']);
    $addresslocal = trim($_POST['addresslocal']);
    $assconnameno = trim($_POST['assemblyconnameno']);
    $assconnamenolocal = trim($_POST['assemblyconnamenolocal']);
    $partno = trim($_POST['partno']);
    $partname = trim($_POST['partname']);
    $partnamelocal = trim($_POST['partnamelocal']);
    
    $birthtithilocal = trim($_POST['birthtithilocal']);
    $patalocal = trim($_POST['patalocal']);
    $kanamelocal = trim($_POST['kanamelocal']);
    $sexlocal = trim($_POST['sexlocal']);
    $signlocal = trim($_POST['signlocal']);
    $partnoandnamelocal = trim($_POST['partnoandnamelocal']);
    $user_image = $_POST['image_data'];

    if ($current_wallet < $fee) {
        $swal_msg = "Swal.fire('Error', 'Insufficient Wallet Balance!', 'error');";
    } elseif ($votername == "") {
        $swal_msg = "Swal.fire('Warning', 'Please Enter Voter Name', 'warning');";
    } else {
        $resultm = mysqli_query($conn, "SELECT srno FROM voterauto1 ORDER BY srno DESC LIMIT 1");
        $num_rows = mysqli_fetch_array($resultm);
        $srno = ($num_rows['srno'] ?? 0) + 1;

        $insert_query = "INSERT INTO `voterauto1` (
            `votername`, `namelocal`, `dob`, `dobinlocal`, `gender`, `genderlocal`, `sexlocal`, 
            `spousename`, `fathername`, `fathernamelocal`, `epicno`, `policestation`, `tahshil`, 
            `locallanguage`, `fulladdress`, `localaddress`, `pata`, `kaname`, `signlocal`, 
            `assconnameno`, `assconnamenolocal`, `partno`, `partname`, `partnamelocal`, 
            `partnoandnamelocal`, `imagepathoriginal`, `srno`, `userid`, `status`, `createdatetime`
        ) VALUES (
            '$votername', N'$namelocal', '$dob', N'$birthtithilocal', '$gender', N'$genderlocal', N'$sexlocal',
            '$relation_type', '$fathername', N'$fathernamelocal', '$epicno', '$policestation', '$tahshil',
            '$language', '$address', N'$addresslocal', N'$patalocal', N'$kanamelocal', N'$signlocal',
            '$assconnameno', N'$assconnamenolocal', '$partno', '$partname', N'$partnamelocal',
            N'$partnoandnamelocal', '$user_image', '$srno', '$uid', 'SUCCESS', NOW()
        )";

        if (mysqli_query($conn, $insert_query)) {
            $new_bal = $current_wallet - $fee;
            mysqli_query($conn, "UPDATE users SET wallet = '$new_bal' WHERE id = '$uid'");
            mysqli_query($conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$fee', '$new_bal', 'Voter Manual Print', '1', 'Debit')");
            
            $swal_msg = "Swal.fire('Success', 'Voter Details Saved Successfully!', 'success').then(() => { window.location.href='voter_manual_list.php'; });";
        } else {
            $swal_msg = "Swal.fire('Error', 'Database Error!', 'error');";
        }
    }
}
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-11 mb-0">Welcome, <?php echo htmlspecialchars($u_name); ?></h6>
                        <h4 class="text-primary fw-bold mb-0">Voter Manual <span class="text-info fw-medium">Application</span></h4>
                    </div>
                </div>
                <div class="col-md-auto">
                    <div class="form-control form-control-sm d-flex align-items-center bg-white border-200 shadow-sm">
                        <span class="fas fa-wallet text-success me-2"></span>
                        <span class="fw-bold text-success">Wallet: ₹<?php echo number_format($current_wallet, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 border-top border-4 border-primary">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-1000 fw-bold"><i class="bx bxs-id-card me-2 text-primary"></i>Enter Voter Details</h5>
        <a href="https://www.google.com/intl/sa/inputtools/try/" target="_blank" class="btn btn-falcon-default btn-sm">
            <i class="fas fa-language me-1 text-info"></i> Google Input Tools
        </a>
    </div>
    <div class="card-body p-4 bg-body-tertiary">
        <form method="post" autocomplete="off" onsubmit="return validation();">
            
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900 fw-bold">Applicant Photo</label>
                    <div class="image-preview border rounded bg-white d-flex align-items-center justify-content-center" style="height: 180px; overflow: hidden; position: relative;">
                        <img id="blah" src="../assets/img/illustrations/default-avatar.png" style="max-height: 100%; width: auto;">
                    </div>
                    <input type="file" id="file_up" class="form-control mt-2 form-control-sm" accept="image/*" onchange="readURL(this);">
                    <input type="hidden" name="image_data" id="image_data">
                </div>

                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">EPIC Number (Voter ID)</label>
                            <input class="form-control" name="epicno" id="epicno" type="text" placeholder="ABC1234567" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Voter Name (English)</label>
                            <input class="form-control" name="name" id="name" type="text" placeholder="Full Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Voter Name (Local)</label>
                            <input class="form-control" name="namelocal" id="name_regional" type="text" placeholder="Auto Translate" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Relation Type</label>
                            <select class="form-select" name="father_husband" id="spouse" required>
                                <option value="Father">Father</option>
                                <option value="Husband">Husband</option>
                                <option value="Mother">Mother</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Relation Name (English)</label>
                            <input class="form-control" name="fathername" id="fathername" type="text" placeholder="Guardian Name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 text-900">Relation Name (Local)</label>
                            <input class="form-control" name="fathernamelocal" id="fathernamelocal" type="text" placeholder="Auto Translate" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">Age / DOB</label>
                    <input class="form-control" name="dobadhar" id="dob" type="text" placeholder="e.g. 25" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">Gender</label>
                    <select class="form-select" name="gender" id="gender" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Third Gender">Third Gender</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">Police Station</label>
                    <input class="form-control" name="policestation" id="policestation" oninput="setaddress()" type="text" placeholder="Thana" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">Tahshil / Block</label>
                    <input class="form-control" name="tahshil" id="tahshil" oninput="setaddress()" type="text" placeholder="Tehsil" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">District</label>
                    <input class="form-control" name="district" id="dists" oninput="setaddress()" type="text" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">Pincode</label>
                    <input class="form-control" name="pincode" id="pincodes" oninput="setaddress()" maxlength="6" type="text" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900">State</label>
                    <input class="form-control" name="statename" id="statename" oninput="setaddress()" type="text" value="Bihar" required>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fs-10 text-900">Assembly Const. (No & Name)</label>
                    <input class="form-control" name="assemblyconnameno" id="assconnameno_input" type="text" placeholder="e.g. 154 - Patna Sahib" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fs-10 text-900">Part (No & Name)</label>
                    <input class="form-control" name="partnoandname" id="partnoandname_input" type="text" placeholder="e.g. 45 - Govt School" required>
                </div>
            </div>

            <div class="p-3 bg-200 rounded mb-4 border shadow-sm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fs-10 text-900 fw-bold">Select Local Language</label>
                        <select class="form-select" onchange="changelang()" name="language" id="lang1" required>
                            <option value="">SELECT</option>
                            <option value="HI">Hindi</option>
                            <option value="PA">Punjabi</option>
                            <option value="GU">Gujarati</option>
                            <option value="MR">Marathi</option>
                            <option value="TA">Tamil</option>
                            <option value="KN">Kannada</option>
                            <option value="BN">Bengali</option>
                            <option value="TE">Telugu</option>
                            <option value="OR">Oriya</option>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label fs-10 text-900">Full Address (Local Language)</label>
                        <textarea class="form-control bg-white" name="addresslocal" id="txtTarget" rows="2" placeholder="Auto-translated address..."></textarea>
                    </div>
                </div>
            </div>

            <input type="hidden" name="address" id="txtSource">
            <input type="hidden" name="genderlocal" id="genderlocal">
            <input type="hidden" name="birthtithilocal" id="birthtithilocal">
            <input type="hidden" name="patalocal" id="patalocal">
            <input type="hidden" name="kanamelocal" id="kanamelocal">
            <input type="hidden" name="sexlocal" id="sexlocal">
            <input type="hidden" name="signlocal" id="signlocal">
            <input type="hidden" name="assemblyconnamenolocal" id="assconnamenolocal">
            <input type="hidden" name="partnoandnamelocal" id="partnoandnamelocal">
            <input type="hidden" name="partno" id="partno_hidden">
            <input type="hidden" name="partname" id="partname_hidden">
            <input type="hidden" name="partnamelocal" id="partnamelocal">

            <input type="hidden" id="pata" value="Address">
            <input type="hidden" id="kaname" value="Voter ID">
            <input type="hidden" id="sex" value="Gender">
            <input type="hidden" id="sign" value="Electoral Registration Officer">
            <input type="hidden" id="birthtithi" value="Age">
            <input type="hidden" id="assconnameno" value="Assembly Constituency Number and Name">
            <input type="hidden" id="partnoandname" value="Part Number and Name">

            <div class="text-end border-top pt-3">
                <h6 class="text-danger d-inline-block me-4">Application Fee: ₹<?php echo $fee; ?></h6>
                <button type="submit" name="savedata" class="btn btn-falcon-success px-5 fw-semi-bold">
                    <i class="fas fa-check-circle me-2"></i>Submit & Save
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    <?php echo $swal_msg; ?>

    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#blah').attr('src', e.target.result);
                $('#image_data').val(e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function setaddress(){
        var ps = $('#policestation').val();
        var block = $('#tahshil').val();
        var dist = $('#dists').val();
        var pin = $('#pincodes').val();
        var state = $('#statename').val();
        var fullAdd = "P.S.- " + ps + ", Block- " + block + ", Dist- " + dist + ", State- " + state + ", Pin- " + pin;
        $('#txtSource').val(fullAdd);
    }

    function changelang() {
        var lang = $("#lang1").val();
        if(!lang) return;

        function translateField(sourceId, targetId) {
            var text = $("#" + sourceId).val();
            if(!text) return;
            var url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=EN&tl=" + lang + "&dt=t&q=" + encodeURI(text);
            $.get(url, function (data) {
                var res = "";
                for(var i=0; i<data[0].length; i++) { res += data[0][i][0]; }
                $("#" + targetId).val(res);
            });
        }

        translateField("name", "name_regional");
        translateField("fathername", "fathernamelocal");
        translateField("txtSource", "txtTarget");
        translateField("assconnameno_input", "assconnamenolocal");
        translateField("partnoandname_input", "partnoandnamelocal");

        translateField("gender", "genderlocal");
        translateField("birthtithi", "birthtithilocal");
        translateField("pata", "patalocal");
        translateField("kaname", "kanamelocal");
        translateField("sex", "sexlocal");
        translateField("sign", "signlocal");
    }

    function validation() {
        if (!$('#image_data').val()) { 
            Swal.fire('Error', 'Please upload applicant photo!', 'error');
            return false; 
        }
        return true;
    }
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>