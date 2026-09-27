<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');

// Fix Database Encoding
mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");
mysqli_query($conn, "SET CHARACTER SET 'utf8mb4'");

require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Retailer';

$filter = ($utype == 'TitanAdmin') ? "" : "WHERE user_id = '$uid'";
$query = "SELECT * FROM up_ration_to_uid_history $filter ORDER BY id DESC";
$result = $conn->query($query);
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center p-3">
                <div class="col-sm-auto d-flex align-items-center">
                    <div class="icon-item bg-primary-subtle shadow-none me-3">
                        <span class="fas fa-list text-primary fs-4"></span>
                    </div>
                    <div>
                        <h6 class="text-primary fs-10 mb-0">History Log</h6>
                        <h4 class="text-primary fw-bold mb-0">UP Ration UID <span class="fw-medium">Records</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="up_ration_to_uid.php" class="btn btn-sm btn-primary shadow-none fw-bold"><i class="fas fa-search me-1"></i> Search New Ration</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3 border-0 shadow-sm border-top border-4 border-primary">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 text-center align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">#ID</th>
                        <th>Ration No.</th>
                        <th>Total Members</th>
                        <th>Date & Time</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            // Decode JSON to count total members
                            $members = json_decode($row['member_data'], true);
                            $total_members = is_array($members) ? count($members) : 0;
                            
                            // ★ THE FIX: Base64 ki jagah direct secure JSON pass kar rahe hain HTML attribute me ★
                            $secure_json_data = htmlspecialchars($row['member_data'], ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr>
                                <td class="py-3 fw-bold">#<?php echo $row['id']; ?></td>
                                <td>
                                    <span class="badge badge-subtle-primary fs-11 px-3 py-2 font-monospace border border-primary"><i class="fas fa-hashtag me-1"></i> <?php echo htmlspecialchars($row['ration_no']); ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-subtle-secondary fs-11 px-3 py-2 border border-secondary"><i class="fas fa-users me-1"></i> <?php echo $total_members; ?> Members</span>
                                </td>
                                <td class="text-600">
                                    <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-falcon-primary fw-bold px-3 shadow-none" 
                                            data-json="<?php echo $secure_json_data; ?>" 
                                            data-ration="<?php echo htmlspecialchars($row['ration_no']); ?>" 
                                            onclick="viewMembers(this)">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="py-4 text-500 fst-italic">No records found. Search a Ration number first.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function viewMembers(btn) {
        let ration = btn.getAttribute('data-ration');
        
        // ★ THE FIX: Direct JSON Parse (Bina atob() ke, jisse Hindi corrupt nahi hogi) ★
        let rawData = btn.getAttribute('data-json');
        let members = JSON.parse(rawData);

        // Build HTML Table dynamically
        let htmlTable = `
            <div class="table-responsive mt-3" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-bordered table-sm fs-10 text-start align-middle">
                    <thead class="bg-200 text-900 position-sticky top-0 shadow-sm">
                        <tr>
                            <th class="text-center">Sr.</th>
                            <th>Name (En/Hi)</th>
                            <th>Father (En/Hi)</th>
                            <th>Gender / DOB</th>
                            <th>Relation</th>
                            <th>Aadhaar (UID)</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        // Array ke har member ko loop karke table me dikhana
        members.forEach(m => {
            htmlTable += `
                <tr>
                    <td class="text-center fw-bold">${m.srno}</td>
                    <td>
                        <strong class="text-primary">${m.Nameof_Family_Member_EN}</strong><br>
                        <small class="text-900 fw-bold fs-11">${m.Nameof_Family_Member_LL}</small>
                    </td>
                    <td>
                        <strong class="text-dark">${m.Father_Name_EN}</strong><br>
                        <small class="text-900 fw-bold fs-11">${m.Father_Name_LL}</small>
                    </td>
                    <td>
                        <span class="text-dark d-block">${m.Gender}</span>
                        <span class="badge badge-subtle-secondary">${m.DOB}</span>
                    </td>
                    <td><span class="badge badge-subtle-info fs-11 px-2 py-1">${m.RELATION}</span></td>
                    <td class="font-monospace fw-bold text-success fs-11">${m.UIDNo}</td>
                </tr>
            `;
        });

        htmlTable += `</tbody></table></div>`;

        // Pop-up show karna
        Swal.fire({
            title: `<h5 class="mb-0 text-primary"><i class="fas fa-users me-2"></i>Family Details - ${ration}</h5>`,
            html: htmlTable,
            width: '850px', // Bada popup table ke liye
            confirmButtonText: 'Close',
            customClass: { confirmButton: 'btn btn-primary shadow-none px-4' },
            buttonsStyling: false
        });
    }
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>