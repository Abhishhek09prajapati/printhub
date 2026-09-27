<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once('../titancore/titanconfig.php');

// Fix Database Encoding for Hindi/Regional languages in JSON
mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");
mysqli_query($conn, "SET CHARACTER SET 'utf8mb4'");

require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? 'Retailer';

// Admin sees all details, Retailer sees only their own
$filter = ($utype == 'TitanAdmin') ? "" : "WHERE user_id = '$uid'";
$query = "SELECT * FROM aadhar_details_history $filter ORDER BY id DESC";
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
                        <h4 class="text-primary fw-bold mb-0">Linked Details <span class="fw-medium">Records</span></h4>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <a href="aadhaar_to_details.php" class="btn btn-sm btn-primary shadow-none fw-bold"><i class="fas fa-search me-1"></i> New Search</a>
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
                        <th>Search Ref / Txn ID</th>
                        <th>Records Found</th>
                        <th>Date & Time</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            // Decode JSON to count total linked records found
                            $records = json_decode($row['full_data'], true);
                            $total_records = is_array($records) ? count($records) : 0;
                            
                            // Secure JSON encode for the HTML data attribute
                            $secure_json_data = htmlspecialchars($row['full_data'], ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr>
                                <td class="py-3 fw-bold">#<?php echo $row['id']; ?></td>
                                <td>
                                    <span class="badge badge-subtle-primary fs-11 px-3 py-2 font-monospace border border-primary">
                                        <i class="fas fa-receipt me-1"></i> <?php echo htmlspecialchars($row['txn_id']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-subtle-success fs-11 px-3 py-2 border border-success">
                                        <i class="fas fa-database me-1"></i> <?php echo $total_records; ?> Found
                                    </span>
                                </td>
                                <td class="text-600">
                                    <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if($total_records > 0): ?>
                                    <button class="btn btn-sm btn-falcon-primary fw-bold px-3 shadow-none" 
                                            data-json="<?php echo $secure_json_data; ?>" 
                                            data-txn="<?php echo htmlspecialchars($row['txn_id']); ?>" 
                                            onclick="viewDetails(this)">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </button>
                                    <?php else: ?>
                                    <span class="badge badge-subtle-secondary">No Data</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="py-4 text-500 fst-italic">No records found. Search first.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function viewDetails(btn) {
        let txnId = btn.getAttribute('data-txn');
        
        // Direct JSON Parse securely
        let rawData = btn.getAttribute('data-json');
        let records = JSON.parse(rawData);

        // Build HTML Table dynamically
        let htmlTable = `
            <div class="table-responsive mt-3" style="max-height: 450px; overflow-y: auto;">
                <table class="table table-bordered table-sm fs-10 text-start align-middle">
                    <thead class="bg-200 text-900 position-sticky top-0 shadow-sm">
                        <tr>
                            <th class="text-center py-2">Sr.</th>
                            <th class="py-2">Name Details</th>
                            <th class="py-2">Contact Info</th>
                            <th class="py-2">Telecom Circle</th>
                            <th class="py-2">Registered Address</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        // Loop through each linked record
        records.forEach((m, index) => {
            let altNumber = m.alt ? `<br><small class="text-600"><i class="fas fa-phone-alt me-1"></i> ${m.alt}</small>` : '';
            let formattedAddress = m.ADDRESS ? m.ADDRESS.replace(/!/g, ', ') : 'N/A'; // Replace exclamation marks with commas if needed
            
            htmlTable += `
                <tr>
                    <td class="text-center fw-bold text-600">${index + 1}</td>
                    <td>
                        <strong class="text-primary d-block">${m.NAME || 'N/A'}</strong>
                        <small class="text-900 fw-semi-bold fs-11">S/o: ${m.fname || 'N/A'}</small>
                    </td>
                    <td>
                        <strong class="text-success d-block"><i class="fas fa-mobile-alt me-1"></i> ${m.MOBILE || 'N/A'}</strong>
                        ${altNumber}
                    </td>
                    <td>
                        <span class="badge badge-subtle-info fs-11 px-2 py-1"><i class="fas fa-sim-card me-1"></i> ${m.circle || 'N/A'}</span>
                    </td>
                    <td class="text-700" style="min-width: 250px; white-space: normal;">
                        <i class="fas fa-map-marker-alt text-danger me-1"></i> ${formattedAddress}
                    </td>
                </tr>
            `;
        });

        htmlTable += `</tbody></table></div>`;

        // Pop-up show karna
        Swal.fire({
            title: `<h5 class="mb-0 text-primary"><i class="fas fa-id-card me-2"></i>Linked Records Details</h5>`,
            html: htmlTable,
            width: '900px', // Wide popup for better data viewing
            confirmButtonText: 'Close Window',
            customClass: { confirmButton: 'btn btn-primary shadow-none px-4' },
            buttonsStyling: false
        });
    }
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>