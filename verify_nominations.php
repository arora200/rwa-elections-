<?php
$pageTitle = "Verify Nominations";
include 'includes/header.php';
include 'includes/validation_util.php';

// Check if the user is authorized
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_name'], ['super_admin', 'returning_officer'])) {
    header("Location: dashboard.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$errors = [];
$success_message = '';

// Handle form submission for approve/reject nomination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nomination_id'])) {
    check_csrf_token();

    $nomination_id = filter_var($_POST['nomination_id'], FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    
    if (!$nomination_id) {
        $errors[] = "Invalid nomination ID.";
    } else {
        try {
            if ($action === 'approve') {
                $stmt = $db->prepare(
                    "UPDATE candidates SET 
                        status = 'approved', 
                        verified_by = :user_id, 
                        verified_date = datetime('now'),
                        rejection_reason = NULL
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':user_id' => $current_user_id,
                    ':id' => $nomination_id
                ]);
                $success_message = "Nomination approved successfully.";
            } elseif ($action === 'reject') {
                $rejection_reason = sanitize_string($_POST['rejection_reason'] ?? 'Rejected by Returning Officer/Super Admin.');
                $stmt = $db->prepare(
                    "UPDATE candidates SET 
                        status = 'rejected', 
                        rejection_reason = :reason,
                        verified_by = :user_id, 
                        verified_date = datetime('now')
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':reason' => $rejection_reason,
                    ':user_id' => $current_user_id,
                    ':id' => $nomination_id
                ]);
                $success_message = "Nomination rejected successfully.";
            } else {
                $errors[] = "Invalid action.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // On success, redirect to clear the form
    if (empty($errors)) {
        header("Location: verify_nominations.php?message=" . urlencode($success_message));
        exit();
    }
}

// Display message from GET request
if (isset($_GET['message'])) {
    $success_message = htmlspecialchars($_GET['message']);
}

// Fetch pending nominations
try {
    $stmt = $db->prepare(
        "SELECT c.id, m.full_name as candidate_name, m.unit_flat_number, obp.position_name,
                c.proposer_name, c.proposer_unit, c.seconder_name, c.seconder_unit,
                c.ownership_status, c.statutory_declaration, c.litigation_declaration, c.bye_laws_agreement,
                c.nomination_date
         FROM candidates c
         JOIN members m ON c.member_id = m.id
         JOIN office_bearer_positions obp ON c.position_id = obp.id
         WHERE c.status = 'pending'
         ORDER BY c.nomination_date ASC"
    );
    $stmt->execute();
    $pending_nominations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error fetching pending nominations: " . $e->getMessage();
    $pending_nominations = [];
}

?>

<h2>Verify Candidate Nominations</h2>
<p>Review and decide on pending candidate nominations.</p>

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
    <?php if (empty($pending_nominations)): ?>
        <p>There are no nominations currently pending verification.</p>
    <?php else: ?>
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Position</th>
                    <th>Proposer</th>
                    <th>Seconder</th>
                    <th>Declarations</th>
                    <th>Nominated On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_nominations as $nomination): ?>
                    <tr>
                        <td data-label="Candidate"><?php echo htmlspecialchars($nomination['candidate_name']); ?> (<?php echo htmlspecialchars($nomination['unit_flat_number']); ?>)</td>
                        <td data-label="Position"><?php echo htmlspecialchars($nomination['position_name']); ?></td>
                        <td data-label="Proposer"><?php echo htmlspecialchars($nomination['proposer_name']); ?> (<?php echo htmlspecialchars($nomination['proposer_unit']); ?>)</td>
                        <td data-label="Seconder"><?php echo htmlspecialchars($nomination['seconder_name']); ?> (<?php echo htmlspecialchars($nomination['seconder_unit']); ?>)</td>
                        <td data-label="Declarations">
                            Ownership: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $nomination['ownership_status']))); ?><br>
                            Statutory: <?php echo $nomination['statutory_declaration'] ? 'Yes' : 'No'; ?><br>
                            Litigation: <?php echo $nomination['litigation_declaration'] ? 'No' : 'Yes'; ?><br>
                            Bye-laws: <?php echo $nomination['bye_laws_agreement'] ? 'Yes' : 'No'; ?>
                        </td>
                        <td data-label="Nominated On"><?php echo htmlspecialchars($nomination['nomination_date']); ?></td>
                        <td data-label="Actions" class="actions-cell">
                            <form method="POST" class="nomination-verification-form" style="margin-bottom: 5px;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="nomination_id" value="<?php echo $nomination['id']; ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-small">Approve</button>
                            </form>
                            <form method="POST" class="nomination-verification-form">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="nomination_id" value="<?php echo $nomination['id']; ?>">
                                <input type="text" name="rejection_reason" placeholder="Reason for rejection (optional)" class="input-inline">
                                <button type="submit" name="action" value="reject" class="btn btn-error btn-small" onclick="return confirm('Are you sure you want to reject this nomination?');">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
