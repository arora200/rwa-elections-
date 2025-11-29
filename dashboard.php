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
        <h2>Dashboard</h2>

        <?php if ($success_message): ?>
            <p class="success"><?php echo $success_message; ?></p>
        <?php endif; ?>

        <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($role_name); ?>)!</p>
        <p>From here, you can manage different aspects of the election based on your role.</p>
        <h3>Your Role-Based Actions:</h3>
        <ul>
            <?php if ($role_name == 'data_entry' || $role_name == 'super_admin'): ?>
                <li><a href="register.php">Register New Member</a> - For adding new residents to the system.</li>
                <li><a href="view_members.php">View All Members</a> - A list of all registered members.</li>
            <?php endif; ?>
            <?php if ($role_name == 'accounts' || $role_name == 'super_admin'): ?>
                <li><a href="verify_payments.php">Verify Payments</a> - For confirming financial standing of members.</li>
            <?php endif; ?>
            <?php if ($role_name == 'returning_officer' || $role_name == 'super_admin'): ?>
                <li><a href="verify_documents.php">Verify Documents</a> - For approving submitted documents.</li>
                <li><a href="nominate.php">Nominate Candidate</a> - For qualified voters to nominate themselves or others.</li>
                <li><a href="verify_nominations.php">Verify Nominations</a> - To review and approve/reject pending candidate nominations.</li>
                <li><a href="member_management.php">Member Status Management</a> - To manage the status of all members.</li>
            <?php endif; ?>
        </ul>
<?php include 'includes/footer.php'; ?>