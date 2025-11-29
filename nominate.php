<?php
$pageTitle = "Nominate Candidate";
include 'includes/header.php'; // session_start() and db.php are included here
include 'includes/cache_util.php'; // Include caching utility

if (!isset($_SESSION['user_id']) || ($_SESSION['role_name'] != 'returning_officer' && $_SESSION['role_name'] != 'super_admin')) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

// Fetch all qualified voters for nomination, proposer, and seconder
$qualified_voters = get_cache('qualified_voters');
if ($qualified_voters === null) {
    // Include full_name and unit_flat_number in the select for JS use
    $qualified_voters = $db->query("SELECT id, full_name, unit_flat_number FROM members WHERE status = 'qualified_voter' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    set_cache('qualified_voters', $qualified_voters);
}

// Fetch all office bearer positions
$positions = $db->query("SELECT id, position_name FROM office_bearer_positions")->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    check_csrf_token(); // CSRF protection

    $member_id = filter_var($_POST['member_id'], FILTER_VALIDATE_INT);
    $position_id = filter_var($_POST['position_id'], FILTER_VALIDATE_INT);
    
    $proposer_member_id = filter_var($_POST['proposer_member_id'], FILTER_VALIDATE_INT);
    $seconder_member_id = filter_var($_POST['seconder_member_id'], FILTER_VALIDATE_INT);

    $ownership_status = sanitize_string($_POST['ownership_status']);
    $statutory_declaration = isset($_POST['statutory_declaration']) ? 1 : 0;
    $litigation_declaration = isset($_POST['litigation_declaration']) ? 1 : 0;
    $bye_laws_agreement = isset($_POST['bye_laws_agreement']) ? 1 : 0;
    $nomination_date = date('Y-m-d H:i:s');
    
    $errors = []; // Reset errors for POST validation

    // Basic Validation
    if (!$member_id) { $errors[] = "Nominated candidate is required."; }
    if (!$position_id) { $errors[] = "Position is required."; }
    if (!$proposer_member_id) { $errors[] = "Proposer is required."; }
    if (!$seconder_member_id) { $errors[] = "Seconder is required."; }
    if (empty($ownership_status)) { $errors[] = "Ownership status is required."; }
    if (!$statutory_declaration) { $errors[] = "Statutory declaration is required."; }
    if (!$litigation_declaration) { $errors[] = "Litigation declaration is required."; }
    if (!$bye_laws_agreement) { $errors[] = "Bye-laws agreement is required."; }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // 1. Check if the nominated member is a qualified voter
            $stmt = $db->prepare("SELECT full_name, unit_flat_number, status FROM members WHERE id = :member_id");
            $stmt->bindValue(':member_id', $member_id, PDO::PARAM_INT);
            $stmt->execute();
            $candidate_member = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$candidate_member || $candidate_member['status'] != 'qualified_voter') {
                throw new Exception("Only qualified voters can be nominated as candidates.");
            }

            // Fetch Proposer details
            $stmt = $db->prepare("SELECT full_name, unit_flat_number, status FROM members WHERE id = :proposer_member_id");
            $stmt->bindValue(':proposer_member_id', $proposer_member_id, PDO::PARAM_INT);
            $stmt->execute();
            $proposer_member = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$proposer_member || $proposer_member['status'] != 'qualified_voter') {
                throw new Exception("Proposer must be a qualified voter.");
            }
            $proposer_name = $proposer_member['full_name'];
            $proposer_unit = $proposer_member['unit_flat_number'];

            // Fetch Seconder details
            $stmt = $db->prepare("SELECT full_name, unit_flat_number, status FROM members WHERE id = :seconder_member_id");
            $stmt->bindValue(':seconder_member_id', $seconder_member_id, PDO::PARAM_INT);
            $stmt->execute();
            $seconder_member = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$seconder_member || $seconder_member['status'] != 'qualified_voter') {
                throw new Exception("Seconder must be a qualified voter.");
            }
            $seconder_name = $seconder_member['full_name'];
            $seconder_unit = $seconder_member['unit_flat_number'];

            // Ensure candidate, proposer, seconder are distinct (optional but good practice)
            if ($member_id == $proposer_member_id || $member_id == $seconder_member_id || $proposer_member_id == $seconder_member_id) {
                throw new Exception("Candidate, Proposer, and Seconder must be distinct individuals.");
            }

            // 3. Insert candidate nomination
            $stmt = $db->prepare("INSERT INTO candidates (member_id, position_id, proposer_name, proposer_unit, seconder_name, seconder_unit, ownership_status, statutory_declaration, litigation_declaration, bye_laws_agreement, nomination_date, status, verified_by, verified_date) VALUES (:member_id, :position_id, :proposer_name, :proposer_unit, :seconder_name, :seconder_unit, :ownership_status, :statutory_declaration, :litigation_declaration, :bye_laws_agreement, :nomination_date, 'pending', NULL, NULL)");
            $stmt->bindValue(':member_id', $member_id, PDO::PARAM_INT);
            $stmt->bindValue(':position_id', $position_id, PDO::PARAM_INT);
            $stmt->bindValue(':proposer_name', $proposer_name, PDO::PARAM_STR);
            $stmt->bindValue(':proposer_unit', $proposer_unit, PDO::PARAM_STR);
            $stmt->bindValue(':seconder_name', $seconder_name, PDO::PARAM_STR);
            $stmt->bindValue(':seconder_unit', $seconder_unit, PDO::PARAM_STR);
            $stmt->bindValue(':ownership_status', $ownership_status, PDO::PARAM_STR);
            $stmt->bindValue(':statutory_declaration', $statutory_declaration, PDO::PARAM_INT);
            $stmt->bindValue(':litigation_declaration', $litigation_declaration, PDO::PARAM_INT);
            $stmt->bindValue(':bye_laws_agreement', $bye_laws_agreement, PDO::PARAM_INT);
            $stmt->bindValue(':nomination_date', $nomination_date, PDO::PARAM_STR);
            $stmt->execute();

            // Log audit
            $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, new_value) VALUES (:user_id, 'Candidate Nomination', 'candidates', :record_id, :new_value)");
            $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT); // Changed from verified_by as it's not verified yet
            $stmt->bindValue(':record_id', $db->lastInsertId(), PDO::PARAM_INT);
            $stmt->bindValue(':new_value', "Nominated member_id: $member_id for position_id: $position_id", PDO::PARAM_STR);
            $stmt->execute();

            $db->commit();
            $message = "Candidate nominated successfully. Awaiting verification from a Returning Officer.";

        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error: " . $e->getMessage();
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Please correct the following errors: <br>" . implode("<br>", $errors);
    }
}


?>
        <h2>Nominate Candidate</h2>
        <?php if ($message): ?>
            <p class="success"><?php echo $message; ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <?php if (empty($qualified_voters)): ?>
            <p>No qualified voters available for nomination yet.</p>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="input-group">
                    <label for="member_id">Select Qualified Voter to Nominate *</label>
                    <select id="member_id" name="member_id" required>
                        <option value="">-- Select Member --</option>
                        <?php foreach ($qualified_voters as $voter): ?>
                            <option value="<?php echo $voter['id']; ?>"><?php echo htmlspecialchars($voter['full_name']) . " (" . htmlspecialchars($voter['unit_flat_number']) . ")"; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="candidate_details_display" class="input-group" style="display: none;">
                    <strong>Candidate:</strong> <span id="candidate_name_display"></span> (<span id="candidate_unit_display"></span>)
                </div>

                <div class="input-group">
                    <label for="position_id">Select Position *</label>
                    <select id="position_id" name="position_id" required>
                        <option value="">-- Select Position --</option>
                        <?php foreach ($positions as $position): ?>
                            <option value="<?php echo $position['id']; ?>"><?php echo htmlspecialchars($position['position_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <h3>Proposer Details</h3>
                <div class="input-group">
                    <label for="proposer_member_id">Select Proposer *</label>
                    <select id="proposer_member_id" name="proposer_member_id" required>
                        <option value="">-- Select Proposer --</option>
                        <?php foreach ($qualified_voters as $voter): ?>
                            <option value="<?php echo $voter['id']; ?>"><?php echo htmlspecialchars($voter['full_name']) . " (" . htmlspecialchars($voter['unit_flat_number']) . ")"; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="proposer_details_display" class="input-group" style="display: none;">
                    <strong>Proposer:</strong> <span id="proposer_name_display"></span> (<span id="proposer_unit_display"></span>)
                </div>

                <h3>Seconder Details</h3>
                <div class="input-group">
                    <label for="seconder_member_id">Select Seconder *</label>
                    <select id="seconder_member_id" name="seconder_member_id" required>
                        <option value="">-- Select Seconder --</option>
                        <?php foreach ($qualified_voters as $voter): ?>
                            <option value="<?php echo $voter['id']; ?>"><?php echo htmlspecialchars($voter['full_name']) . " (" . htmlspecialchars($voter['unit_flat_number']) . ")"; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="seconder_details_display" class="input-group" style="display: none;">
                    <strong>Seconder:</strong> <span id="seconder_name_display"></span> (<span id="seconder_unit_display"></span>)
                </div>

                <h3>Declarations</h3>
                <div class="input-group">
                    <input type="checkbox" id="ownership_sole" name="ownership_status" value="sole_owner" required>
                    <label for="ownership_sole">I am a sole owner of the property.</label>
                </div>
                <div class="input-group">
                    <input type="checkbox" id="statutory_declaration" name="statutory_declaration" value="1" required>
                    <label for="statutory_declaration">I agree to the statutory declaration.</label>
                </div>
                <div class="input-group">
                    <input type="checkbox" id="litigation_declaration" name="litigation_declaration" value="1" required>
                    <label for="litigation_declaration">I declare that I am not involved in any litigation against the association.</label>
                </div>
                <div class="input-group">
                    <input type="checkbox" id="bye_laws_agreement" name="bye_laws_agreement" value="1" required>
                    <label for="bye_laws_agreement">I agree to abide by the bye-laws of the association.</label>
                </div>

                <div class="input-group">
                    <button type="submit" class="btn">Submit Nomination</button>
                </div>
            </form>
        <?php endif; ?>

<script>
    const qualifiedVoters = <?php echo json_encode($qualified_voters); ?>;
    const qualifiedVotersMap = qualifiedVoters.reduce((map, voter) => {
        map[voter.id] = voter;
        return map;
    }, {});

    function updateDetailsDisplay(selectId, nameDisplayId, unitDisplayId, containerId) {
        const selectElement = document.getElementById(selectId);
        const nameDisplay = document.getElementById(nameDisplayId);
        const unitDisplay = document.getElementById(unitDisplayId);
        const container = document.getElementById(containerId);

        const selectedVoterId = selectElement.value;
        if (selectedVoterId && qualifiedVotersMap[selectedVoterId]) {
            const voter = qualifiedVotersMap[selectedVoterId];
            nameDisplay.textContent = voter.full_name;
            unitDisplay.textContent = voter.unit_flat_number;
            container.style.display = 'block';
        } else {
            nameDisplay.textContent = '';
            unitDisplay.textContent = '';
            container.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initial call for candidate
        updateDetailsDisplay('member_id', 'candidate_name_display', 'candidate_unit_display', 'candidate_details_display');
        document.getElementById('member_id').addEventListener('change', function() {
            updateDetailsDisplay('member_id', 'candidate_name_display', 'candidate_unit_display', 'candidate_details_display');
        });

        // Initial call for proposer
        updateDetailsDisplay('proposer_member_id', 'proposer_name_display', 'proposer_unit_display', 'proposer_details_display');
        document.getElementById('proposer_member_id').addEventListener('change', function() {
            updateDetailsDisplay('proposer_member_id', 'proposer_name_display', 'proposer_unit_display', 'proposer_details_display');
        });

        // Initial call for seconder
        updateDetailsDisplay('seconder_member_id', 'seconder_name_display', 'seconder_unit_display', 'seconder_details_display');
        document.getElementById('seconder_member_id').addEventListener('change', function() {
            updateDetailsDisplay('seconder_member_id', 'seconder_name_display', 'seconder_unit_display', 'seconder_details_display');
        });
    });
</script>
<?php include 'includes/footer.php'; ?>