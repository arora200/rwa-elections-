<?php
$pageTitle = "Verify Documents";
include 'includes/header.php'; // session_start() and db.php are included here
include 'includes/cache_util.php'; // Include caching utility

if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] != 'returning_officer' && $_SESSION['role_name'] != 'super_admin')) {
    header("Location: index.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    check_csrf_token(); // CSRF protection

    $document_id = $_POST['document_id'];
    $member_id = $_POST['member_id'];
    $action = $_POST['action'];
    $rejection_reason = $_POST['rejection_reason'] ?? null;
    $verified_by = $_SESSION['user_id'];
    $current_datetime = date('Y-m-d H:i:s');

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE documents SET status = :status, verified_by = :verified_by, verified_at = :verified_at, rejection_reason = :rejection_reason WHERE id = :document_id");
        $stmt->bindValue(':status', $action == 'approve' ? 'verified' : 'rejected', PDO::PARAM_STR);
        $stmt->bindValue(':verified_by', $verified_by, PDO::PARAM_INT);
        $stmt->bindValue(':verified_at', $current_datetime, PDO::PARAM_STR);
        $stmt->bindValue(':rejection_reason', $rejection_reason, PDO::PARAM_STR);
        $stmt->bindValue(':document_id', $document_id, PDO::PARAM_INT);
        $stmt->execute();

        // Check if all documents for the member are verified, then update member status
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE member_id = :member_id AND status != 'verified'");
        $stmt->bindValue(':member_id', $member_id, PDO::PARAM_INT);
        $pending_docs = $stmt->fetchColumn();

        if ($pending_docs == 0) {
            // Fetch current member status to ensure we only update if it's not already qualified_voter
            $stmt = $db->prepare("SELECT status FROM members WHERE id = :member_id");
            $stmt->bindValue(':member_id', $member_id, PDO::PARAM_INT);
            $member_current_status = $stmt->fetchColumn();

            if ($member_current_status != 'qualified_voter') {
                 $stmt = $db->prepare("UPDATE members SET status = 'qualified_voter', ro_verified_by = :verified_by, ro_verified_date = :verified_at, voter_card_number = 'VOTER-' || :member_id WHERE id = :member_id");
                $stmt->bindValue(':verified_by', $verified_by, PDO::PARAM_INT);
                $stmt->bindValue(':verified_at', $current_datetime, PDO::PARAM_STR);
                $stmt->bindValue(':member_id', $member_id, PDO::PARAM_INT);
                $stmt->execute();


                // Log audit for member status update
                $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value) VALUES (:user_id, :action, 'members', :record_id, :old_status, 'qualified_voter')");
                $stmt->bindValue(':user_id', $verified_by, PDO::PARAM_INT);
                $stmt->bindValue(':action', 'Member Qualified as Voter', PDO::PARAM_STR);
                $stmt->bindValue(':record_id', $member_id, PDO::PARAM_INT);
                $stmt->bindValue(':old_status', $member_current_status, PDO::PARAM_STR);
                $stmt->execute();

                // Invalidate qualified_voters cache when a member becomes a qualified voter
                invalidate_cache('qualified_voters');
            }
        }

        // Log audit for document verification
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value) VALUES (:user_id, :action_type, 'documents', :record_id, 'pending', :new_status)");
        $stmt->bindValue(':user_id', $verified_by, PDO::PARAM_INT);
        $stmt->bindValue(':action_type', 'Document Verification', PDO::PARAM_STR);
        $stmt->bindValue(':record_id', $document_id, PDO::PARAM_INT);
        $stmt->bindValue(':new_status', $action == 'approve' ? 'verified' : 'rejected', PDO::PARAM_STR);
        $stmt->execute();

        $db->commit();
        $message = "Document updated successfully.";
    } catch (PDOException $e) {
        $db->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

$documents = $db->query("SELECT d.*, m.full_name, m.unit_flat_number, m.status as member_status FROM documents d JOIN members m ON d.member_id = m.id WHERE d.status = 'pending'")->fetchAll(PDO::FETCH_ASSOC);

?>
        <h2>Verify Documents</h2>
        <?php if ($message): ?>
            <p class="<?php echo strpos($message, 'Error') !== false ? 'error' : 'success'; ?>"><?php echo $message; ?></p>
        <?php endif; ?>

        <?php if (empty($documents)): ?>
            <p>No pending documents to verify.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="responsive-table">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Unit/Flat</th>
                            <th>Document Type</th>
                            <th>File Path</th>
                            <th>Uploaded At</th>
                            <th>Member Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $document): ?>
                            <tr>
                                <td data-label="Member Name"><?php echo htmlspecialchars($document['full_name']); ?></td>
                                <td data-label="Unit/Flat"><?php echo htmlspecialchars($document['unit_flat_number']); ?></td>
                                <td data-label="Document Type"><?php echo htmlspecialchars($document['document_type']); ?></td>
                                <td data-label="File Path"><a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank">View Document</a></td>
                                <td data-label="Uploaded At"><?php echo htmlspecialchars($document['uploaded_at']); ?></td>
                                <td data-label="Member Status"><?php echo htmlspecialchars($document['member_status']); ?></td>
                                <td data-label="Action" class="actions-cell">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                        <input type="hidden" name="member_id" value="<?php echo $document['member_id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-small">Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                        <input type="hidden" name="member_id" value="<?php echo $document['member_id']; ?>">
                                        <input type="text" name="rejection_reason" placeholder="Reason (optional)" class="input-inline">
                                        <button type="submit" name="action" value="reject" class="btn btn-error btn-small">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
<?php include 'includes/footer.php'; ?>