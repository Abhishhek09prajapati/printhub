<?php
// print_aadhar_advance.php
if (empty($_POST)) {
    die("Direct access not allowed");
}

$aadharno = $_POST['aadharno'] ?? '';
$aadharname = $_POST['aadharname'] ?? '';
$localname = $_POST['localname'] ?? '';
$fathername = $_POST['fathername'] ?? '';
$dob = $_POST['dob'] ?? '';
$localdob = $_POST['localdob'] ?? '';
$gender = $_POST['gender'] ?? '';
$localgender = $_POST['localgender'] ?? '';
$fulladdress = $_POST['fulladdress'] ?? '';
$localaddress = $_POST['localaddress'] ?? '';
$imageurl = $_POST['imageurl'] ?? '';
$originalaadharno = $_POST['originalaadharno'] ?? '';
$locallanguage = $_POST['locallanguage'] ?? '';

$formatted_aadhar = $originalaadharno ?: (substr($aadharno, 0, 4) . ' ' . substr($aadharno, 4, 4) . ' ' . substr($aadharno, 8, 4));

$lang_names = ['hi'=>'हिन्दी','pa'=>'ਪੰਜਾਬੀ','gu'=>'ગુજરાતી','mr'=>'मराठी','ta'=>'தமிழ்','kn'=>'ಕನ್ನಡ','bn'=>'বাংলা','te'=>'తెలుగు','or'=>'ଓଡ଼ିଆ','sd'=>'سنڌي'];
$language_name = $lang_names[$locallanguage] ?? 'English';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Aadhaar Card - <?php echo $aadharname; ?></title>
    <style>
        @media print { body { margin: 0; padding: 0; } .no-print { display: none; } }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Arial', sans-serif;
            background: #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .print-container {
            width: 800px;
            background: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            border-radius: 15px;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1a237e, #283593);
            color: white;
            padding: 15px 20px;
            text-align: center;
        }
        .header h2 { margin: 0; font-size: 22px; }
        .header p { margin: 5px 0 0; font-size: 12px; }
        .card-content { padding: 20px; }
        .photo-section {
            float: right;
            width: 120px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 10px;
            background: #f9f9f9;
            margin-left: 15px;
        }
        .photo-section img { width: 100px; height: 100px; object-fit: cover; border-radius: 5px; }
        .details-table { width: 100%; border-collapse: collapse; }
        .details-table tr td { padding: 8px 5px; border-bottom: 1px solid #eee; vertical-align: top; }
        .details-table tr td:first-child {
            width: 130px;
            font-weight: bold;
            color: #1a237e;
            background: #f5f5f5;
        }
        .local-text { font-size: 11px; color: #666; margin-top: 3px; font-style: italic; }
        .english-text { font-size: 13px; font-weight: 500; }
        .footer {
            background: #f5f5f5;
            padding: 10px 20px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
        }
        .clearfix { clear: both; }
        .btn-print {
            background: #1a237e;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-print:hover { background: #283593; }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="header">
            <h2>भारत सरकार | Government of India</h2>
            <p>Unique Identification Authority of India (UIDAI)</p>
        </div>
        <div class="card-content">
            <div class="photo-section">
                <?php if(!empty($imageurl)): ?>
                    <img src="<?php echo htmlspecialchars($imageurl); ?>" alt="Photo">
                <?php else: ?>
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' fill='%23cccccc'/%3E%3Ctext x='50' y='55' font-size='14' text-anchor='middle' fill='%23666'%3ENo Photo%3C/text%3E%3C/svg%3E" alt="No Photo">
                <?php endif; ?>
                <p>Aadhaar Card</p>
            </div>
            <table class="details-table">
                <tr><td class="label">Aadhaar Number</td><td><strong><?php echo $formatted_aadhar; ?></strong></td></tr>
                <tr><td class="label">Name</td><td><div class="english-text"><?php echo strtoupper($aadharname); ?></div><?php if($localname) echo '<div class="local-text">'.$localname.'</div>'; ?></td></tr>
                <tr><td class="label">Father/Husband Name</td><td><?php echo $fathername; ?></td></tr>
                <tr><td class="label">Date of Birth</td><td><div class="english-text"><?php echo $dob; ?></div><?php if($localdob) echo '<div class="local-text">'.$localdob.'</div>'; ?></td></tr>
                <tr><td class="label">Gender</td><td><div class="english-text"><?php echo $gender; ?></div><?php if($localgender) echo '<div class="local-text">'.$localgender.'</div>'; ?></td></tr>
                <tr><td class="label">Address</td><td><div class="english-text"><?php echo nl2br($fulladdress); ?></div><?php if($localaddress) echo '<div class="local-text">'.nl2br($localaddress).'</div>'; ?></td></tr>
                <?php if($locallanguage): ?>
                <tr><td class="label">Language</td><td><?php echo $language_name; ?></td></tr>
                <?php endif; ?>
            </table>
            <div class="clearfix"></div>
        </div>
        <div class="footer">
            <p>This is a computer generated document. No signature required.</p>
        </div>
    </div>
    <div class="no-print" style="position: fixed; bottom: 20px; right: 20px;">
        <button class="btn-print" onclick="window.print();">🖨️ Print / Save as PDF</button>
    </div>
</body>
</html>