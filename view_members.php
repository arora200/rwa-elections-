<?php
$pageTitle = "View All Members";
include 'includes/header.php';

// Check if the user is authorized to view this page
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'], ['data_entry', 'super_admin', 'returning_officer', 'accounts'])) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];

// Fetch all members to display
try {
    $stmt = $db->prepare("SELECT id, full_name, unit_flat_number, email, contact_number, status FROM members ORDER BY registration_date DESC");
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error fetching members: " . $e->getMessage();
    $members = [];
}

?>

<h2>View All Members</h2>
<p>A read-only list of all registered members in the system.</p>

<?php if (!empty($errors)): ?>
    <div class="error-messages">
        <?php foreach ($errors as $error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (empty($members) && empty($errors)): ?>
    <p>No members have been registered yet.</p>
<?php elseif (!empty($members)): ?>
    <div class="table-container">
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Unit/Flat No.</th>
                    <th>Email</th>
                    <th>Contact Number</th>
                    <th>Current Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td data-label="ID"><?php echo htmlspecialchars($member['id']); ?></td>
                        <td data-label="Full Name"><?php echo htmlspecialchars($member['full_name']); ?></td>
                        <td data-label="Unit/Flat No."><?php echo htmlspecialchars($member['unit_flat_number']); ?></td>
                        <td data-label="Email"><?php echo htmlspecialchars($member['email']); ?></td>
                        <td data-label="Contact No."><?php echo htmlspecialchars($member['contact_number']); ?></td>
                        <td data-label="Status"><span class="status-badge status-<?php echo str_replace('_', '-', htmlspecialchars($member['status'])); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $member['status']))); ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
