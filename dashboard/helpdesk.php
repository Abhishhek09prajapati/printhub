<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Auth & Configuration
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
require_once('../titancore/titanconfig.php');

// 2. Fetch Website Settings
$settings_res = $conn->query("SELECT * FROM settings LIMIT 1");
$web = $settings_res->fetch_assoc();

require_once('../titancore/titanheader.php');

$uid = $_SESSION['user_id'];
$u_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$swal_msg = "";

// Site Name for Header
$display_name = $web['site_name'] ?? 'ApiNexus';

// 3. Handle Support Request & Store in SQL
if (isset($_POST['submit_ticket'])) {
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    
    $insert_sql = "INSERT INTO support_tickets (user_id, subject, message, status, created_at) 
                   VALUES ('$uid', '$subject', '$message', 'Open', NOW())";

    if ($conn->query($insert_sql)) {
        $swal_msg = "Swal.fire({
            title: 'Ticket Raised!',
            text: 'Your request has been stored. Ticket ID: " . $conn->insert_id . "',
            icon: 'success',
            confirmButtonColor: '#2c7be5'
        });";
    } else {
        $swal_msg = "Swal.fire('Error', 'Failed to submit ticket.', 'error');";
    }
}

// 4. Fetch User's Ticket History
$history_res = $conn->query("SELECT * FROM support_tickets WHERE user_id = '$uid' ORDER BY id DESC");
?>

<div class="row mb-3">
    <div class="col">
        <div class="card bg-100 shadow-none border">
            <div class="row gx-0 flex-between-center">
                <div class="col-sm-auto d-flex align-items-center">
                    <img class="ms-n2" src="../assets/img/illustrations/crm-bar-chart.png" alt="" width="90" />
                    <div>
                        <h6 class="text-primary fs-10 mb-0">Welcome back, <?php echo htmlspecialchars($u_name); ?></h6>
                        <h4 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($display_name); ?> <span class="text-info fw-medium">CRM</span></h4>
                    </div>
                    <img class="ms-n4 d-md-none d-lg-block" src="../assets/img/illustrations/crm-line-chart.png" alt="" width="150" />
                </div>
                <div class="col-md-auto p-3">
                    <div class="row align-items-center g-3">
                        <div class="col-auto"><h6 class="text-700 mb-0">Today: </h6></div>
                        <div class="col-md-auto">
                            <div class="form-control form-control-sm d-flex align-items-center bg-white border-200" style="min-width: 140px;">
                                <span class="fas fa-calendar-day text-primary me-2"></span>
                                <span class="fw-bold text-primary"><?php echo date('d M, Y'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100 shadow-none border">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-plus-circle me-2"></i>Raise New Ticket</h5>
                <p class="mb-0 fs-11">Describe your issue and we'll help you soon.</p>
            </div>
            <div class="card-body bg-white">
                <form method="POST" class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semi-bold text-900">Category / Issue Type</label>
                        <select class="form-select shadow-none border-200" name="subject" required>
                            <option value="">Choose Category...</option>
                            <option value="Wallet Balance">Wallet Balance Issue</option>
                            <option value="API Integration">API Integration Support</option>
                            <option value="Service Down">Service / Portal Down</option>
                            <option value="Report Error">Incorrect Document Report</option>
                            <option value="General Query">General Inquiry</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semi-bold text-900">Detail Message</label>
                        <textarea class="form-control shadow-none border-200" name="message" rows="4" placeholder="Briefly explain your problem..." required></textarea>
                    </div>
                    <div class="col-12 mt-3">
                        <button class="btn btn-primary w-100 py-2 fw-bold shadow-none" name="submit_ticket" type="submit">
                            <span class="fas fa-paper-plane me-2"></span>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100 shadow-none border">
            <div class="card-header bg-light border-bottom">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-history me-2"></i>Support History</h5>
            </div>
            <div class="card-body scrollbar bg-body-tertiary" style="max-height: 450px;">
                <?php if ($history_res->num_rows > 0): ?>
                    <?php while($row = $history_res->fetch_assoc()): ?>
                        <div class="mb-3 p-3 border rounded bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge badge-subtle-primary fs-11"><?php echo htmlspecialchars($row['subject']); ?></span>
                                <span class="fs-11 text-600"><i class="far fa-clock me-1"></i><?php echo date('d M, h:i A', strtotime($row['created_at'])); ?></span>
                            </div>
                            <div class="bg-light p-2 rounded mb-2">
                                <p class="fs-11 text-800 mb-0"><strong><i class="fas fa-user me-1 text-primary"></i>You:</strong> <?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                            </div>
                            
                            <?php if(!empty($row['admin_reply'])): ?>
                                <div class="mt-2 p-2 rounded bg-subtle-success border-start border-success border-3">
                                    <h6 class="fs-11 fw-bold text-success mb-1"><i class="fas fa-reply me-1"></i>Admin Response:</h6>
                                    <p class="fs-11 text-900 mb-0"><?php echo nl2br(htmlspecialchars($row['admin_reply'])); ?></p>
                                </div>
                                <div class="mt-2 text-end">
                                    <span class="badge badge-subtle-success rounded-pill"><span class="fas fa-check-circle me-1"></span>Resolved</span>
                                </div>
                            <?php else: ?>
                                <div class="mt-2 text-end">
                                    <span class="badge badge-subtle-warning rounded-pill"><i class="fas fa-spinner fa-spin me-1"></i>Awaiting Response</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <img src="../assets/img/illustrations/empty.png" alt="" width="80" class="opacity-50 mb-2"/>
                        <p class="text-500 mb-0 fs-11 italic">No previous support tickets found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php echo $swal_msg; ?>
</script>

<style>
    .form-select, .form-control { padding: 0.75rem 1rem; border-radius: 0.5rem; }
    .card { border-radius: 0.75rem; overflow: hidden; }
    .italic { font-style: italic; }
    .bg-subtle-success { background-color: rgba(0, 210, 122, 0.1); }
</style>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>