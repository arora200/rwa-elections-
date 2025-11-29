<?php
$pageTitle = "Dashboard";
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$role_name = $_SESSION['role_name'];
$success_message = '';
if (isset($_GET['message'])) {
    $success_message = htmlspecialchars($_GET['message']);
}
?>
<?php
// Pass existing success message to the message component
$success = $success_message;
include 'includes/message.php';
?>

<?php
// Initialize counts
$pending_payments_count = 0;
$pending_documents_count = 0;
$pending_nominations_count = 0;

try {
    // For Accounts/Super Admin: Pending Payments
    if ($role_name == 'accounts' || $role_name == 'super_admin') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM members WHERE payment_status = 'pending'");
        $stmt->execute();
        $pending_payments_count = $stmt->fetchColumn();
    }

    // For Returning Officer/Super Admin: Pending Documents
    if ($role_name == 'returning_officer' || $role_name == 'super_admin') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE status = 'pending'");
        $stmt->execute();
        $pending_documents_count = $stmt->fetchColumn();
        
        // Pending Nominations
        $stmt = $db->prepare("SELECT COUNT(*) FROM nominations WHERE status = 'pending'");
        $stmt->execute();
        $pending_nominations_count = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    // Log error, but don't stop page render
    error_log("Database error fetching dashboard counts: " . $e->getMessage());
}
?>

        <h2>Dashboard</h2>

        <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($role_name); ?>)!</p>
        <p>From here, you can manage different aspects of the election based on your role.</p>

        <div class="dashboard-widgets">
            <?php if ($role_name == 'accounts' || $role_name == 'super_admin'): ?>
                <div class="widget">
                    <h3>Pending Payments</h3>
                    <p class="count <?php echo ($pending_payments_count > 0 ? 'highlight-count' : ''); ?>"><?php echo $pending_payments_count; ?></p>
                    <a href="verify_payments.php" class="btn btn-small">Verify Payments</a>
                </div>
            <?php endif; ?>

            <?php if ($role_name == 'returning_officer' || $role_name == 'super_admin'): ?>
                <div class="widget">
                    <h3>Pending Documents</h3>
                    <p class="count <?php echo ($pending_documents_count > 0 ? 'highlight-count' : ''); ?>"><?php echo $pending_documents_count; ?></p>
                    <a href="verify_documents.php" class="btn btn-small">Verify Documents</a>
                </div>
                <div class="widget">
                    <h3>Pending Nominations</h3>
                    <p class="count <?php echo ($pending_nominations_count > 0 ? 'highlight-count' : ''); ?>"><?php echo $pending_nominations_count; ?></p>
                    <a href="verify_nominations.php" class="btn btn-small">Verify Nominations</a>
                </div>
            <?php endif; ?>
        </div>

        <h3>Your Role-Based Actions:</h3>
        <ul>
            <?php if ($role_name == 'data_entry' || $role_name == 'super_admin'): ?>
                <li><a href="register.php"><i class="fas fa-user-plus"></i> Register New Member</a> - For adding new residents to the system.</li>
                <li><a href="view_members.php"><i class="fas fa-users"></i> View All Members</a> - A list of all registered members.</li>
            <?php endif; ?>
            <?php if ($role_name == 'accounts' || $role_name == 'super_admin'): ?>
                <li><a href="verify_payments.php"><i class="fas fa-money-check-alt"></i> Verify Payments</a> - For confirming financial standing of members.</li>
            <?php endif; ?>
            <?php if ($role_name == 'returning_officer' || $role_name == 'super_admin'): ?>
                <li><a href="verify_documents.php"><i class="fas fa-file-alt"></i> Verify Documents</a> - For approving submitted documents.</li>
                <li><a href="nominate.php"><i class="fas fa-user-tie"></i> Nominate Candidate</a> - For qualified voters to nominate themselves or others.</li>
                <li><a href="verify_nominations.php"><i class="fas fa-clipboard-check"></i> Verify Nominations</a> - To review and approve/reject pending candidate nominations.</li>
                <li><a href="member_management.php"><i class="fas fa-user-cog"></i> Member Status Management</a> - To manage the status of all members.</li>
            <?php endif; ?>
        </ul>
<?php include 'includes/footer.php'; ?>