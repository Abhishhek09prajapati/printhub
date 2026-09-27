<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proxy_print_json'])) {
    
    $server_url = $_POST['target_url'];
    $json_data = $_POST['proxy_print_json'];

    $ch = curl_init($server_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['print_json' => $json_data]));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    
    if(curl_errno($ch)){
        echo "<div style='text-align:center; margin-top:50px; font-family:sans-serif; color:red;'>";
        echo "<h3>Print Server Offline or Blocked!</h3>";
        echo "<p>Error: " . curl_error($ch) . "</p>";
        echo "</div>";
    } else {
        $parsed_url = parse_url($server_url);
        $path_dir = dirname($parsed_url['path']);
        $base_path = $parsed_url['scheme'] . '://' . $parsed_url['host'] . ($path_dir === '\\' ? '/' : $path_dir) . '/';
        
        $base_tag = "<base href='" . $base_path . "'>";
        
        $response = str_ireplace("<head>", "<head>\n" . $base_tag, $response);
        
        echo $response;
    }
    
    curl_close($ch);
    exit(); 
}

require_once('../titancore/titanconfig.php');

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");
mysqli_query($conn, "SET CHARACTER SET 'utf8mb4'");

$uid = $_SESSION['user_id'];
$u_phone = $_SESSION['phone'] ?? '';
$u_name = $_SESSION['user_name'] ?? 'User';
$swal_msg = "";

$user_res = $conn->query("SELECT * FROM users WHERE id = '$uid'");
$udata = $user_res->fetch_assoc();
$current_wallet = floatval($udata['wallet'] ?? 0);

$price_res = $conn->query("SELECT price FROM pricing WHERE service_name='voter_advance'");
$fee = ($price_res && $price_res->num_rows > 0) ? floatval($price_res->fetch_assoc()['price']) : 10.00; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'fetch_voter') {
    header('Content-Type: application/json; charset=utf-8');
    $epic_no = mysqli_real_escape_string($conn, trim($_POST['epicno']));
    
    $api_settings = $conn->query("SELECT titanurl, titan_api_key FROM TitanPayment WHERE id = 1")->fetch_assoc();
    $TitanApi_Url = $api_settings['titanurl'] ?? '';
    $api_key = $api_settings['titan_api_key'] ?? '';

    if (empty($TitanApi_Url) || empty($api_key)) {
        echo json_encode(['status' => 'error', 'msg' => 'API Config missing.']); exit();
    }

    $endpoint = rtrim($TitanApi_Url, '/') . "/api/v1/Voter_Verification.php?api_key=" . urlencode($api_key) . "&epic_no=" . urlencode($epic_no);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $api_response = curl_exec($ch);
    curl_close($ch);

    if ($api_response) {
        $data = json_decode($api_response, true);
        if (isset($data['result']['success']) && $data['result']['success'] == true) {
            $res = $data['result']['result'];
            
            $g = strtoupper($res['gender'] ?? '');
            if($g == 'M') $gender = 'Male'; elseif($g == 'F') $gender = 'Female'; else $gender = 'Third Gender';
            
            $rel = strtoupper($res['relationType'] ?? '');
            if($rel == 'FTHR') $relation = 'Father'; elseif($rel == 'HSBN') $relation = 'Husband'; elseif($rel == 'MTHR') $relation = 'Mother'; else $relation = 'Father';

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'name' => $res['fullName'] ?? '',
                    'fathername' => $res['relativeFullName'] ?? '',
                    'relation_type' => $relation,
                    'gender' => $gender,
                    'dob' => $res['age'] ?? $res['dob'] ?? '', 
                    'assembly' => ($res['acNumber'] ?? '') . ' - ' . ($res['asmblyName'] ?? ''),
                    'partno' => $res['partNumber'] ?? '',
                    'partname' => $res['partName'] ?? '',
                    'tahshil' => $res['tehsil'] ?? '',
                    'district' => $res['districtValue'] ?? '',
                    'state' => $res['stateName'] ?? '',
                    'pincode' => $res['pinCode'] ?? ''
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Voter not found!']);
        }
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Server Error.']);
    }
    exit();
}

require_once('../titancore/titanheader.php');

if (isset($_POST['savedata'])) {
    
    $votername = mysqli_real_escape_string($conn, strtoupper(trim($_POST['name'])));
    $namelocal = mysqli_real_escape_string($conn, trim($_POST['namelocal']));
    $gender = mysqli_real_escape_string($conn, trim($_POST['gender']));
    $genderlocal = mysqli_real_escape_string($conn, trim($_POST['genderlocal']));
    $dob = mysqli_real_escape_string($conn, trim($_POST['dobadhar'])); 
    $relation_type = mysqli_real_escape_string($conn, trim($_POST['father_husband']));
    $fathername = mysqli_real_escape_string($conn, strtoupper(trim($_POST['fathername'])));
    $fathernamelocal = mysqli_real_escape_string($conn, trim($_POST['fathernamelocal']));
    $epicno = mysqli_real_escape_string($conn, trim($_POST['epicno']));
    $policestation = mysqli_real_escape_string($conn, trim($_POST['policestation']));
    $tahshil = mysqli_real_escape_string($conn, trim($_POST['tahshil']));
    $dist = mysqli_real_escape_string($conn, trim($_POST['district']));
    $pincode = mysqli_real_escape_string($conn, trim($_POST['pincode']));
    $state = mysqli_real_escape_string($conn, trim($_POST['statename']));
    $language = mysqli_real_escape_string($conn, trim($_POST['language']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $addresslocal = mysqli_real_escape_string($conn, trim($_POST['addresslocal']));
    $assconnameno = mysqli_real_escape_string($conn, trim($_POST['assemblyconnameno']));
    $assconnamenolocal = mysqli_real_escape_string($conn, trim($_POST['assemblyconnamenolocal']));
    $partno = mysqli_real_escape_string($conn, trim($_POST['partno'])); 
    $partname = mysqli_real_escape_string($conn, trim($_POST['partname'])); 
    $partnamelocal = mysqli_real_escape_string($conn, trim($_POST['partnamelocal']));
    
    $birthtithilocal = mysqli_real_escape_string($conn, trim($_POST['birthtithilocal']));
    $patalocal = mysqli_real_escape_string($conn, trim($_POST['patalocal']));
    $kanamelocal = mysqli_real_escape_string($conn, trim($_POST['kanamelocal']));
    $sexlocal = mysqli_real_escape_string($conn, trim($_POST['sexlocal']));
    $signlocal = mysqli_real_escape_string($conn, trim($_POST['signlocal']));
    $partnoandnamelocal = mysqli_real_escape_string($conn, trim($_POST['partnoandnamelocal']));
    $user_image = mysqli_real_escape_string($conn, $_POST['image_data']);
    $spousenamelocal = "";

    if ($current_wallet < $fee) {
        $swal_msg = "Swal.fire('Error', 'Insufficient Wallet Balance!', 'error');";
    } elseif ($votername == "") {
        $swal_msg = "Swal.fire('Warning', 'Please Enter Voter Name', 'warning');";
    } else {
        
        $insert_query = "INSERT INTO `voteradvance_history` (
            `userid`, `votername`, `namelocal`, `dob`, `dobinlocal`, 
            `gender`, `genderlocal`, `spousename`, `spousenamelocal`, 
            `fathername`, `fathernamelocal`, `epicno`, `policestation`, 
            `tahshil`, `locallanguage`, `fulladdress`, `localaddress`, 
            `pata`, `kaname`, `signlocal`, `assconnameno`, `assconnamenolocal`, 
            `partno`, `partname`, `partnamelocal`, `partnoandnamelocal`, 
            `imagepathoriginal`, `sexlocal`, `status`
        ) VALUES (
            '$uid', '$votername', N'$namelocal', '$dob', N'$birthtithilocal', 
            '$gender', N'$genderlocal', '$relation_type', N'$spousenamelocal', 
            '$fathername', N'$fathernamelocal', '$epicno', '$policestation', 
            '$tahshil', '$language', '$address', N'$addresslocal', 
            N'$patalocal', N'$kanamelocal', N'$signlocal', '$assconnameno', N'$assconnamenolocal', 
            '$partno', '$partname', N'$partnamelocal', N'$partnoandnamelocal', 
            '$user_image', N'$sexlocal', 'SUCCESS'
        )";

        if (mysqli_query($conn, $insert_query)) {
            $new_bal = $current_wallet - $fee;
            mysqli_query($conn, "UPDATE users SET wallet = '$new_bal' WHERE id = '$uid'");
            mysqli_query($conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$uid', '$fee', '$new_bal', 'Voter Advance Print ($epicno)', '1', 'Debit')");
            $swal_msg = "Swal.fire('Success', 'Voter Details Saved Successfully!', 'success').then(() => { window.location.href='voter_advance_list.php'; });";
        } else {
            $swal_msg = "Swal.fire('Database Error!', 'Failed to save', 'error');";
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
                        <h6 class="text-primary fs-11 mb-0">Welcome, <?php echo htmlspecialchars($u_name, ENT_QUOTES, 'UTF-8'); ?></h6>
                        <h4 class="text-primary fw-bold mb-0">Voter Advance <span class="text-info fw-medium">Application</span></h4>
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
        <div class="badge badge-subtle-danger fs-10 p-2 fw-bold"><i class="fas fa-rupee-sign me-1"></i> Fee: ₹<?php echo $fee; ?></div>
    </div>
    <div class="card-body p-4 bg-body-tertiary">
        <form method="post" autocomplete="off" onsubmit="return validation();" accept-charset="utf-8">
            
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label fs-10 text-900 fw-bold">Applicant Photo</label>
                    <div class="image-preview border rounded bg-white d-flex align-items-center justify-content-center" style="height: 180px; overflow: hidden;">
                        <img id="blah" src="../assets/img/illustrations/default-avatar.png" style="max-height: 100%; width: auto;">
                    </div>
                    <input type="file" id="file_up" class="form-control mt-2 form-control-sm" accept="image/*" onchange="readURL(this);">
                    <input type="hidden" name="image_data" id="image_data">
                </div>

                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fs-10 text-900">EPIC Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input class="form-control text-uppercase" name="epicno" id="epicno" type="text" placeholder="ABC1234567" required>
                                <button class="btn btn-primary px-4 fw-bold shadow-none" type="button" onclick="autoFetchVoter()"><i class="fas fa-download me-1"></i> Fetch Details</button>
                            </div>
                        </div>
                        <div class="col-md-4"><label class="form-label">Voter Name</label><input class="form-control text-uppercase" name="name" id="name" required></div>
                        <div class="col-md-4"><label class="form-label">Name (Local)</label><input class="form-control" name="namelocal" id="name_regional" required></div>
                        <div class="col-md-4">
                            <label class="form-label">Relation Type</label>
                            <select class="form-select" name="father_husband" id="spouse"><option value="Father">Father</option><option value="Husband">Husband</option></select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Relation Name</label><input class="form-control text-uppercase" name="fathername" id="fathername" required></div>
                        <div class="col-md-6"><label class="form-label">Relation (Local)</label><input class="form-control" name="fathernamelocal" id="fathernamelocal" required></div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3"><label class="form-label">Age / DOB</label><input class="form-control" name="dobadhar" id="dob" required></div>
                <div class="col-md-3">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender" id="gender" required><option value="Male">Male</option><option value="Female">Female</option></select>
                </div>
                <div class="col-md-3"><label class="form-label">Police Station</label><input class="form-control text-uppercase" name="policestation" id="policestation" oninput="setaddress()"></div>
                <div class="col-md-3"><label class="form-label">Tahshil</label><input class="form-control text-uppercase" name="tahshil" id="tahshil" oninput="setaddress()"></div>
                <div class="col-md-4"><label class="form-label">District</label><input class="form-control text-uppercase" name="district" id="dists" oninput="setaddress()" required></div>
                <div class="col-md-4"><label class="form-label">Pincode</label><input class="form-control" name="pincode" id="pincodes" oninput="setaddress()" maxlength="6"></div>
                <div class="col-md-4"><label class="form-label">State</label><input class="form-control text-uppercase" name="statename" id="statename" oninput="setaddress()" required></div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label">Assembly Const.</label><input class="form-control text-uppercase" name="assemblyconnameno" id="assconnameno_input" required></div>
                <div class="col-md-4"><label class="form-label">Part No</label><input class="form-control text-uppercase" name="partno" id="partno" required></div>
                <div class="col-md-4"><label class="form-label">Part Name</label><input class="form-control text-uppercase" name="partname" id="partname" required></div>
            </div>

            <div class="p-3 bg-200 rounded mb-4 border shadow-sm">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Local Language</label>
                        <select class="form-select" onchange="changelang()" name="language" id="lang1" required>
                            <option value="">SELECT</option><option value="HI">Hindi</option><option value="PA">Punjabi</option><option value="GU">Gujarati</option><option value="MR">Marathi</option><option value="BN">Bengali</option>
                        </select>
                    </div>
                    <div class="col-md-9"><label class="form-label">Address (Local)</label><textarea class="form-control bg-white" name="addresslocal" id="txtTarget" rows="2"></textarea></div>
                </div>
            </div>

            <input type="hidden" name="address" id="txtSource">
            <input type="hidden" name="genderlocal" id="genderlocal">
            <input type="hidden" name="birthtithilocal" id="birthtithilocal">
            <input type="hidden" name="patalocal" id="patalocal">
            <input type="hidden" name="kanamelocal" id="kanamelocal">
            <input type="hidden" name="sexlocal" id="sexlocal">
            <input type="hidden" name="signlocal" id="signlocal">
            <input type="hidden" name="assconnamenolocal" id="assconnamenolocal">
            <input type="hidden" name="partnoandnamelocal" id="partnoandnamelocal">
            <input type="hidden" name="partnamelocal" id="partnamelocal">

            <input type="hidden" id="pata" value="Address">
            <input type="hidden" id="kaname" value="Voter ID">
            <input type="hidden" id="sex" value="Gender">
            <input type="hidden" id="sign" value="Electoral Registration Officer">
            <input type="hidden" id="birthtithi" value="Age">

            <div class="text-end border-top pt-3"><button type="submit" name="savedata" class="btn btn-falcon-success px-5 fw-semi-bold">Save Record</button></div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    <?php echo $swal_msg; ?>

    function autoFetchVoter() {
        let epic = $('#epicno').val().trim();
        if (!epic) { Swal.fire('Warning', 'Enter EPIC Number', 'warning'); return; }

        Swal.fire({title: 'Fetching...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

        let fd = new FormData();
        fd.append('action_type', 'fetch_voter'); fd.append('epicno', epic);

        fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                $('#name').val(data.data.name); $('#fathername').val(data.data.fathername);
                $('#spouse').val(data.data.relation_type); $('#dob').val(data.data.dob);
                $('#gender').val(data.data.gender); $('#assconnameno_input').val(data.data.assembly);
                $('#partno').val(data.data.partno); $('#partname').val(data.data.partname);
                $('#tahshil').val(data.data.tahshil); $('#dists').val(data.data.district);
                $('#statename').val(data.data.state); $('#pincodes').val(data.data.pincode);
                setaddress();
                Swal.fire('Found!', 'Auto-filled successfully.', 'success');
            } else { Swal.fire('Error', data.msg, 'error'); }
        }).catch(e => { Swal.fire('Error', 'Server connection failed.', 'error'); });
    }

    document.getElementById('epicno').addEventListener('input', function () { this.value = this.value.toUpperCase().replace(/\s/g, ''); });

    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) { $('#blah').attr('src', e.target.result); $('#image_data').val(e.target.result); };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function setaddress(){
        var ps = $('#policestation').val(), block = $('#tahshil').val(), dist = $('#dists').val(), pin = $('#pincodes').val(), state = $('#statename').val();
        var fullAdd = [];
        if(ps) fullAdd.push("P.S.- " + ps); if(block) fullAdd.push("Block- " + block);
        if(dist) fullAdd.push("Dist- " + dist); if(state) fullAdd.push("State- " + state); if(pin) fullAdd.push("Pin- " + pin);
        $('#txtSource').val(fullAdd.join(", "));
    }

    function changelang() {
        var lang = $("#lang1").val();
        if(!lang) return;

        function tF(sId, tId) {
            var txt = $("#" + sId).val();
            if(!txt) return;
            $.get("https://translate.googleapis.com/translate_a/single?client=gtx&sl=EN&tl=" + lang + "&dt=t&q=" + encodeURIComponent(txt), function (d) {
                var res = ""; for(var i=0; i<d[0].length; i++) res += d[0][i][0];
                $("#" + tId).val(res);
            });
        }
        
        tF("name", "name_regional"); tF("fathername", "fathernamelocal"); tF("txtSource", "txtTarget");
        tF("assconnameno_input", "assconnamenolocal"); tF("partname", "partnamelocal");
        tF("gender", "genderlocal"); tF("birthtithi", "birthtithilocal"); tF("pata", "patalocal");
        tF("kaname", "kanamelocal"); tF("sex", "sexlocal"); tF("sign", "signlocal");
        
        setTimeout(() => {
            let pNo = $('#partno').val();
            let pNameLocal = $('#partnamelocal').val();
            $('#partnoandnamelocal').val(pNo + " - " + pNameLocal);
        }, 1500); 
    }

    function validation() { if (!$('#image_data').val()) { Swal.fire('Error', 'Upload photo!', 'error'); return false; } return true; }
</script>

<?php require_once('../titancore/TitanFooter.php'); ob_end_flush(); ?>