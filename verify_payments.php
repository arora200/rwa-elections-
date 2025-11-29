<?php
$pageTitle = "Verify Payments";
include 'includes/header.php';
include 'includes/validation_util.php';

// Check if the user is authorized
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'], ['accounts', 'super_admin'])) {
    header("Location: dashboard.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$errors = [];
$success_message = '';

// Handle form submission for update payment info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id'])) {
    check_csrf_token();

    $member_id = filter_var($_POST['member_id'], FILTER_VALIDATE_INT);
    $new_status = sanitize_string($_POST['new_status'] ?? '');
    $receipt_number = sanitize_string($_POST['receipt_number'] ?? '');
    $rejection_reason = sanitize_string($_POST['rejection_reason'] ?? null);
    
    // Define allowed statuses for accounts
    $allowed_accounts_statuses = ['documents_uploaded', 'accounts_verified', 'accounts_rejected'];

    if (!$member_id) {
        $errors[] = "Invalid member ID.";
    }
    if (!in_array($new_status, $allowed_accounts_statuses)) {
        $errors[] = "Invalid status selected.";
    }
    if ($new_status === 'accounts_verified' && empty($receipt_number)) {
        $errors[] = "Receipt Number is required for 'Approved' status.";
    }

    if (empty($errors)) {
        try {
            // Fetch current member status to determine audit trail value
            $stmt_current_status = $db->prepare("SELECT status FROM members WHERE id = :member_id");
            $stmt_current_status->bindParam(':member_id', $member_id);
            $stmt_current_status->execute();
            $old_status = $stmt_current_status->fetchColumn();


            $stmt = $db->prepare(
                "UPDATE members SET 
                    status = :new_status, 
                    receipt_number = :receipt, 
                    accounts_verified_by = :user_id, 
                    accounts_verified_date = datetime('now'),
                    rejection_reason = :rejection_reason
                 WHERE id = :id"
            );
            $stmt->execute([
                ':new_status' => $new_status,
                ':receipt' => ($new_status === 'accounts_verified') ? $receipt_number : NULL, // Clear receipt if not approved
                ':user_id' => $current_user_id,
                ':rejection_reason' => ($new_status === 'accounts_rejected') ? $rejection_reason : NULL, // Clear reason if not rejected
                ':id' => $member_id
            ]);

            // Add audit log
            $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value) VALUES (:user_id, :action, 'members', :record_id, :old_status, :new_status)");
            $stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
            $stmt->bindValue(':action', 'Payment Status Update', PDO::PARAM_STR);
            $stmt->bindValue(':record_id', $member_id, PDO::PARAM_INT);
            $stmt->bindValue(':old_status', $old_status, PDO::PARAM_STR);
            $stmt->bindValue(':new_status', $new_status, PDO::PARAM_STR);
            $stmt->execute();

            $success_message = "Member payment info updated successfully.";
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // On success, redirect to clear the form
    if (empty($errors)) {
        header("Location: verify_payments.php?message=" . urlencode($success_message));
        exit();
    }
}

// Display message from GET request
if (isset($_GET['message'])) {
    $success_message = htmlspecialchars($_GET['message']);
}

// Fetch members for accounts management
// Fetch all members who have either uploaded documents, been approved/rejected by accounts, or rejected by RO (so accounts can revisit)
try {
    $stmt = $db->prepare("SELECT id, full_name, unit_flat_number, registration_date, status, receipt_number, rejection_reason FROM members 
                         WHERE status IN ('documents_uploaded', 'accounts_verified', 'accounts_rejected', 'ro_rejected')
                         ORDER BY registration_date ASC");
    $stmt->execute();
    $members_for_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error fetching members for payment management: " . $e->getMessage();
    $members_for_accounts = [];
}

$accounts_possible_statuses = [
    'documents_uploaded' => 'Pending Accounts Review',
    'accounts_verified' => 'Accounts Verified',
    'accounts_rejected' => 'Accounts Rejected'
];

?>

<h2>Manage Member Payments</h2>
<p>View and update payment verification status and receipt numbers for members.</p>

<?php if (!empty($errors)): ?>
    <div class="error-messages">
        <?php foreach ($errors as $error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
    <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
<?php endif; ?>

<div class="table-container">
    <?php if (empty($members_for_accounts)): ?>
        <p>No members currently require or have undergone payment management.</p>
    <?php else: ?>
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>Member Name</th>
                    <th>Unit/Flat No.</th>
                    <th>Reg. Date</th>
                    <th>Current Status</th>
                    <th>Receipt No.</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members_for_accounts as $member): ?>
                    <tr>
                        <td data-label="Member Name"><?php echo htmlspecialchars($member['full_name']); ?></td>
                        <td data-label="Unit/Flat No."><?php echo htmlspecialchars($member['unit_flat_number']); ?></td>
                        <td data-label="Reg. Date"><?php echo htmlspecialchars($member['registration_date']); ?></td>
                        <td data-label="Current Status">
                            <span class="status-badge status-<?php echo str_replace('_', '-', htmlspecialchars($member['status'])); ?>">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $member['status']))); ?>
                            </span>
                            <?php if ($member['status'] === 'accounts_rejected' && $member['rejection_reason']): ?>
                                <br><small>(Reason: <?php echo htmlspecialchars($member['rejection_reason']); ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Receipt No.">
                            <form method="POST" class="payment-management-form">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                <input type="text" name="receipt_number" value="<?php echo htmlspecialchars($member['receipt_number'] ?? ''); ?>" placeholder="Receipt No." class="input-inline" <?php echo ($member['status'] === 'accounts_verified') ? '' : ''; ?>>
                                
                                <select name="new_status" class="input-inline">
                                    <?php foreach ($accounts_possible_statuses as $statusCode => $statusLabel): ?>
                                        <option value="<?php echo $statusCode; ?>" <?php echo ($statusCode === $member['status']) ? 'selected' : ''; ?>>
                                            <?php echo $statusLabel; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="rejection_reason" value="<?php echo htmlspecialchars($member['rejection_reason'] ?? ''); ?>" placeholder="Reason for rejection (if applicable)" class="input-inline" style="margin-top: 5px;">
                                <button type="submit" class="btn btn-small" style="margin-top: 5px;">Update</button>
                            </form>
                        </td>
                        <td data-label="Actions" class="actions-cell">
                            <!-- Could add more specific actions here if needed, but the form above covers update -->
                            <span class="text-muted">Managed via form</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
