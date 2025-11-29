<?php
$pageTitle = "Member Management";
include 'includes/header.php';
include 'includes/validation_util.php';

// Check if the user is authorized
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'], ['super_admin', 'returning_officer'])) {
    header("Location: dashboard.php");
    exit();
}
$is_super_admin = $_SESSION['role_name'] === 'super_admin';

$errors = [];
$success_message = '';

// --- Handle CSV Export Action ---
if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && $is_super_admin) {
    try {
        $stmt = $db->query("SELECT id, full_name, unit_flat_number, email, contact_number, status, registration_date FROM members ORDER BY id ASC");
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="members_export_'.date("Y-m-d").'.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add header row
        fputcsv($output, ['ID', 'Full Name', 'Unit/Flat Number', 'Email', 'Contact Number', 'Status', 'Registration Date']);
        
        // Add data rows
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
        
    } catch (PDOException $e) {
        die("Export failed: " . $e->getMessage());
    }
}


// --- Handle POST Actions (Update Status, Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    check_csrf_token();
    
    $member_id = filter_var($_POST['member_id'], FILTER_VALIDATE_INT);
    if (!$member_id) {
        $errors[] = "Invalid member ID.";
    }

    if (empty($errors)) {
        switch ($_POST['action']) {
            case 'update_status':
                $new_status = sanitize_string($_POST['new_status']);
                $all_statuses = ['pending', 'documents_uploaded', 'accounts_verified', 'accounts_rejected', 'ro_verified', 'ro_rejected', 'qualified_voter', 'disqualified', 'debarred', 'ceased_to_exist', 'transferred', 'inactive'];
                
                if (!in_array($new_status, $all_statuses)) {
                    $errors[] = "Invalid status selected.";
                } else {
                    try {
                        $stmt = $db->prepare("UPDATE members SET status = :new_status WHERE id = :member_id");
                        $stmt->bindParam(':new_status', $new_status);
                        $stmt->bindParam(':member_id', $member_id);
                        $stmt->execute();
                        $success_message = "Member status updated successfully!";
                    } catch (PDOException $e) { $errors[] = "Database error: " . $e->getMessage(); }
                }
                break;

            case 'delete_member':
                // Only super_admin can delete
                if ($is_super_admin) {
                    try {
                        // Soft delete by setting status to 'inactive'
                        $stmt = $db->prepare("UPDATE members SET status = 'inactive' WHERE id = :member_id");
                        $stmt->bindParam(':member_id', $member_id);
                        $stmt->execute();
                        $success_message = "Member marked as inactive successfully.";
                    } catch (PDOException $e) { $errors[] = "Database error: " . $e->getMessage(); }
                } else {
                    $errors[] = "You are not authorized to perform this action.";
                }
                break;
        }
    }
    
    // Redirect after action to prevent form resubmission
    if (empty($errors)) {
        header("Location: member_management.php?message=" . urlencode($success_message));
        exit();
    }
}


// Display message from GET request
if (isset($_GET['message'])) {
    $success_message = htmlspecialchars($_GET['message']);
}

// Fetch all members to display
try {
    $stmt = $db->prepare("SELECT id, full_name, unit_flat_number, email, contact_number, status FROM members ORDER BY full_name");
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error fetching members: " . $e->getMessage();
    $members = [];
}

?>

<h2>Member Management</h2>
<p>Here you can view, edit, and manage all members in the system.</p>

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

<?php if ($is_super_admin): ?>
<div class="page-actions">
    <a href="register.php" class="btn">Add New Member</a>
    <a href="member_management.php?action=export_csv" class="btn">Export to CSV</a>
</div>
<?php endif; ?>


<h3>All Members</h3>
<?php if (empty($members)): ?>
    <p>No members found in the system.</p>
<?php else: ?>
    <div class="table-container">
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Unit/Flat No.</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td data-label="ID"><?php echo htmlspecialchars($member['id']); ?></td>
                        <td data-label="Full Name"><?php echo htmlspecialchars($member['full_name']); ?></td>
                        <td data-label="Unit/Flat No."><?php echo htmlspecialchars($member['unit_flat_number']); ?></td>
                        <td data-label="Contact"><?php echo htmlspecialchars($member['email']); ?><br><?php echo htmlspecialchars($member['contact_number']); ?></td>
                        <td data-label="Status"><span class="status-badge status-<?php echo str_replace('_', '-', htmlspecialchars($member['status'])); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $member['status']))); ?></span></td>
                        <td data-label="Actions" class="actions-cell">
                            <!-- Status Update Form -->
                            <form method="POST" class="status-update-form">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($member['id']); ?>">
                                <select name="new_status" onchange="this.form.submit()" title="Update Status">
                                    <?php $all_statuses = ['pending', 'documents_uploaded', 'accounts_verified', 'accounts_rejected', 'ro_verified', 'ro_rejected', 'qualified_voter', 'disqualified', 'debarred', 'ceased_to_exist', 'transferred', 'inactive']; ?>
                                    <?php foreach ($all_statuses as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo ($status == $member['status']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            
                            <!-- Edit/Delete Buttons -->
                            <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="btn btn-edit btn-small">Edit</a>
                            
                            <?php if ($is_super_admin): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to mark this member as inactive?');" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="delete_member">
                                <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($member['id']); ?>">
                                <button type="submit" class="btn btn-delete btn-small">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
