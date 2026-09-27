<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Basic Login Check
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// 2. ★ STRICT ADMIN SECURITY CHECK ★
$utype = $_SESSION['utype'] ?? $_SESSION['user_type'] ?? '';

if ($utype !== 'TitanAdmin') {
    echo "<script>alert('Access Denied! 🚫 Only Admins can manage support tickets.'); window.location.href='titanhome.php';</script>";
    exit();
}

require_once('../titancore/titanconfig.php');

// Fix Database Encoding
mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET NAMES 'utf8mb4'");

require_once('../titancore/titanheader.php');

// 3. Handle Reply & Status Update (Same Page)
if (isset($_POST['submit_reply'])) {
    $t_id = mysqli_real_escape_string($conn, $_POST['ticket_id']);
    $reply = mysqli_real_escape_string($conn, $_POST['admin_reply']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $update = $conn->query("UPDATE support_tickets SET admin_reply = '$reply', status = '$status' WHERE id = '$t_id'");
    if ($update) {
        echo "<script>window.location.href = 'tiketview.php?msg=updated';</script>";
        exit();
    }
}

// 4. Fetch Tickets with User Info
$query = "SELECT t.*, u.name, u.shop_name, u.phone FROM support_tickets t 
          INNER JOIN users u ON t.user_id = u.id ORDER BY t.status DESC, t.id DESC";
$result = $conn->query($query);
?>

<div class="card border-0 shadow-sm border-top border-4 border-primary mb-3">
    <div class="card-header bg-body-tertiary py-3 border-bottom">
        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-headset me-2"></i>Support Center (Admin Only)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3 py-2">MEMBER INFO</th>
                        <th class="py-2">ISSUE DETAILS</th>
                        <th class="py-2 text-center">STATUS</th>
                        <th class="py-2 text-end pe-4">ACTION</th>
                    </tr>
                </thead>
                <tbody class="list">
                    <?php if($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="align-middle">
                            <td class="ps-3 py-3">
                                <div class="fw-bold text-primary"><?php echo htmlspecialchars($row['name']); ?></div>
                                <div class="fs-11 text-600"><i class="fas fa-phone-alt me-1"></i> <?php echo htmlspecialchars($row['phone']); ?></div>
                                <div class="fs-12 text-500 mt-1"><?php echo htmlspecialchars($row['shop_name']); ?></div>
                            </td>
                            <td class="py-3">
                                <span class="badge badge-subtle-primary mb-1"><i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($row['subject']); ?></span>
                                <div class="text-truncate fs-11 text-dark" style="max-width: 200px;" title="<?php echo htmlspecialchars($row['message']); ?>">
                                    <?php echo htmlspecialchars($row['message']); ?>
                                </div>
                            </td>
                            <td class="py-3 text-center">
                                <?php if($row['status'] == 'Open'): ?>
                                    <span class="badge rounded-pill badge-subtle-danger px-3"><i class="fas fa-exclamation-circle me-1"></i> Open</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill badge-subtle-success px-3"><i class="fas fa-check-circle me-1"></i> Closed</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-end pe-4">
                                <button class="btn btn-primary btn-sm shadow-none fw-bold px-3" 
                                        onclick="openReplyModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                    <i class="fas fa-reply me-1"></i> Manage
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-4 text-500 fst-italic">No support tickets found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="replyModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" autocomplete="off">
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-primary fw-bold" id="modalTitle"><i class="fas fa-reply-all me-2"></i>Reply to Ticket</h5>
                    <button class="btn-close shadow-none" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="ticket_id" id="m_ticket_id">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">User's Message:</label>
                        <div class="fs-11 p-3 bg-200 rounded border text-dark fw-medium" id="m_user_msg" style="white-space: pre-wrap;"></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-primary">Your Reply (Visible to Client): <span class="text-danger">*</span></label>
                        <textarea class="form-control shadow-none" name="admin_reply" id="m_admin_reply" rows="4" placeholder="Type your response here..." required></textarea>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label fw-bold text-dark">Ticket Status:</label>
                        <select class="form-select shadow-none border-primary" name="status" id="m_status">
                            <option value="Open">🔴 Keep Open (Working on it)</option>
                            <option value="Closed">🟢 Resolve & Close Ticket</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button class="btn btn-outline-secondary btn-sm shadow-none" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary btn-sm shadow-none fw-bold px-4" name="submit_reply" type="submit">Update Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openReplyModal(data) {
        document.getElementById('m_ticket_id').value = data.id;
        document.getElementById('m_user_msg').innerText = data.message;
        document.getElementById('m_admin_reply').value = data.admin_reply || '';
        document.getElementById('m_status').value = data.status;
        
        var myModal = new bootstrap.Modal(document.getElementById('replyModal'));
        myModal.show();
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg') === 'updated') {
        Swal.fire({
            title: 'Ticket Updated!',
            text: 'Your reply has been sent and status updated.',
            icon: 'success',
            confirmButtonText: 'OK',
            customClass: { confirmButton: 'btn btn-primary shadow-none px-4' },
            buttonsStyling: false
        }).then(() => {
            // Remove ?msg=updated from URL after showing alert
            window.history.replaceState(null, null, window.location.pathname);
        });
    }
</script>

<?php 
require_once('../titancore/TitanFooter.php'); 
ob_end_flush();
?>