<?php
$pageTitle = "Edit Member";
include 'includes/header.php';
include 'includes/validation_util.php';

// Ensure user is a super_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'super_admin') {
    header("Location: member_management.php");
    exit();
}

$errors = [];
$member_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$member = null;
$aadhaar_doc = null;

if (!$member_id) {
    header("Location: member_management.php");
    exit();
}

// Fetch the existing member and document data
try {
    $stmt = $db->prepare("SELECT * FROM members WHERE id = :id");
    $stmt->bindParam(':id', $member_id);
    $stmt->execute();
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt_doc = $db->prepare("SELECT * FROM documents WHERE member_id = :id AND document_type = 'Aadhaar Card'");
    $stmt_doc->bindParam(':id', $member_id);
    $stmt_doc->execute();
    $aadhaar_doc = $stmt_doc->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        header("Location: member_management.php?message=" . urlencode("Error: Member not found."));
        exit();
    }
} catch (PDOException $e) {
    die("Database error fetching member data: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token();

    // Sanitize text inputs
    $full_name = sanitize_string($_POST['full_name'] ?? '');
    $unit_flat_number = sanitize_string($_POST['unit_flat_number'] ?? '');
    $full_residential_address = sanitize_string($_POST['full_residential_address'] ?? '');
    $occupation = sanitize_string($_POST['occupation'] ?? '');
    $date_of_birth = sanitize_string($_POST['date_of_birth'] ?? '');
    $contact_number = sanitize_string($_POST['contact_number'] ?? '');
    $member_email = sanitize_email($_POST['member_email'] ?? '');

    // --- File Upload Validation (if files are provided) ---
    $new_profile_image_path = $member['profile_image_path']; // Default to old path
    $new_aadhaar_path = $aadhaar_doc ? $aadhaar_doc['file_path'] : null; // Default to old path
    
    // Validate new profile image
    if (isset($_FILES['new_profile_image']) && $_FILES['new_profile_image']['error'] == UPLOAD_ERR_OK) {
        if ($_FILES['new_profile_image']['size'] > (5 * 1024 * 1024)) { $errors[] = "Profile image size exceeds 5 MB."; }
        $img_ext = strtolower(pathinfo($_FILES['new_profile_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($img_ext, ['jpg', 'jpeg', 'png'])) { $errors[] = "Invalid profile image file type."; }
    }

    // Validate new Aadhaar card
    if (isset($_FILES['new_aadhaar_card']) && $_FILES['new_aadhaar_card']['error'] == UPLOAD_ERR_OK) {
        if ($_FILES['new_aadhaar_card']['size'] > (5 * 1024 * 1024)) { $errors[] = "Aadhaar card file size exceeds 5 MB."; }
        $doc_ext = strtolower(pathinfo($_FILES['new_aadhaar_card']['name'], PATHINFO_EXTENSION));
        if (!in_array($doc_ext, ['jpg', 'jpeg', 'png', 'pdf'])) { $errors[] = "Invalid Aadhaar card file type."; }
    }
    
    // --- Text Field Validation ---
    if (empty($full_name)) { $errors[] = "Full Name is required."; }
    if (empty($unit_flat_number)) { $errors[] = "Unit/Flat Number is required."; }
    // ... other validations from before
    
    // --- Database Duplicate Checks ---
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM members WHERE (unit_flat_number = :unit OR email = :email) AND id != :id");
            $stmt->execute([':unit' => $unit_flat_number, ':email' => $member_email, ':id' => $member_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Another member with this Unit/Flat Number or Email already exists.";
            }
        } catch (PDOException $e) { $errors[] = "Database validation error: " . $e->getMessage(); }
    }

    // If no errors, process uploads and update DB
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Process new profile image
            if (isset($_FILES['new_profile_image']) && $_FILES['new_profile_image']['error'] == UPLOAD_ERR_OK) {
                $old_profile_path = $member['profile_image_path'];
                $img_ext = strtolower(pathinfo($_FILES['new_profile_image']['name'], PATHINFO_EXTENSION));
                $new_profile_image_path = 'uploads/profiles/profile_' . uniqid() . '.' . $img_ext;
                
                if (move_uploaded_file($_FILES['new_profile_image']['tmp_name'], $new_profile_image_path)) {
                    if ($old_profile_path && file_exists($old_profile_path)) {
                        unlink($old_profile_path);
                    }
                } else { throw new Exception("Failed to move new profile image."); }
            }

            // Process new Aadhaar card
            if (isset($_FILES['new_aadhaar_card']) && $_FILES['new_aadhaar_card']['error'] == UPLOAD_ERR_OK) {
                $old_aadhaar_path = $aadhaar_doc ? $aadhaar_doc['file_path'] : null;
                $doc_ext = strtolower(pathinfo($_FILES['new_aadhaar_card']['name'], PATHINFO_EXTENSION));
                $new_aadhaar_path = 'uploads/documents/aadhaar_' . uniqid() . '.' . $doc_ext;

                if (move_uploaded_file($_FILES['new_aadhaar_card']['tmp_name'], $new_aadhaar_path)) {
                    if ($old_aadhaar_path && file_exists($old_aadhaar_path)) {
                        unlink($old_aadhaar_path);
                    }
                    // Update or Insert into documents table
                    $stmt = $db->prepare("UPDATE documents SET file_path = :path WHERE member_id = :id AND document_type = 'Aadhaar Card'");
                    $stmt->execute([':path' => $new_aadhaar_path, ':id' => $member_id]);
                } else { throw new Exception("Failed to move new Aadhaar card."); }
            }

            // Update members table
            $sql = "UPDATE members SET 
                        full_name = :full_name, 
                        unit_flat_number = :unit_flat_number, 
                        full_residential_address = :full_residential_address, 
                        occupation = :occupation, 
                        date_of_birth = :date_of_birth, 
                        contact_number = :contact_number, 
                        email = :email,
                        profile_image_path = :p_path
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':full_name' => $full_name, ':unit_flat_number' => $unit_flat_number,
                ':full_residential_address' => $full_residential_address, ':occupation' => $occupation,
                ':date_of_birth' => $date_of_birth, ':contact_number' => $contact_number,
                ':email' => $member_email, ':p_path' => $new_profile_image_path, ':id' => $member_id
            ]);
            
            $db->commit();
            header("Location: member_management.php?message=" . urlencode("Member details updated successfully!"));
            exit();

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Update failed: " . $e->getMessage();
        }
    }
}

// Populate form values
$full_name_val = $_POST['full_name'] ?? $member['full_name'];
$unit_flat_number_val = $_POST['unit_flat_number'] ?? $member['unit_flat_number'];
$full_residential_address_val = $_POST['full_residential_address'] ?? $member['full_residential_address'];
$occupation_val = $_POST['occupation'] ?? $member['occupation'];
$date_of_birth_val = $_POST['date_of_birth'] ?? $member['date_of_birth'];
$contact_number_val = $_POST['contact_number'] ?? $member['contact_number'];
$member_email_val = $_POST['email'] ?? $member['email'];

?>
<?php include 'includes/message.php'; ?>

<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'link' => 'dashboard.php'],
    ['label' => 'Member Management', 'link' => 'member_management.php'],
    ['label' => 'Edit Member', 'link' => '']
];
include 'includes/breadcrumb.php';
?>
<h2>Edit Member: <?php echo htmlspecialchars($member['full_name']); ?></h2>
<p>Use this form to edit the details for this member. Leave file inputs blank to keep current files.</p>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

    <h3>Member Details</h3>
    <div class="input-group">
        <label for="full_name">Full Name *</label>
        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name_val); ?>" required>
    </div>
    <div class="input-group">
        <label for="unit_flat_number">Unit/Flat Number *</label>
        <input type="text" id="unit_flat_number" name="unit_flat_number" value="<?php echo htmlspecialchars($unit_flat_number_val); ?>" required>
    </div>
    <div class="input-group">
        <label for="full_residential_address">Full Residential Address *</label>
        <input type="text" id="full_residential_address" name="full_residential_address" value="<?php echo htmlspecialchars($full_residential_address_val); ?>" required>
    </div>
    <div class="input-group">
        <label for="occupation">Occupation</label>
        <select id="occupation" name="occupation">
            <option value="">Select Occupation</option>
            <option value="Student" <?php echo ($occupation_val == 'Student') ? 'selected' : ''; ?>>Student</option>
            <option value="Business" <?php echo ($occupation_val == 'Business') ? 'selected' : ''; ?>>Business</option>
            <option value="Service" <?php echo ($occupation_val == 'Service') ? 'selected' : ''; ?>>Service</option>
            <option value="Retired" <?php echo ($occupation_val == 'Retired') ? 'selected' : ''; ?>>Retired</option>
            <option value="Retired and Employed" <?php echo ($occupation_val == 'Retired and Employed') ? 'selected' : ''; ?>>Retired and Employed</option>
            <option value="Other" <?php echo ($occupation_val == 'Other') ? 'selected' : ''; ?>>Other</option>
        </select>
    </div>
    <div class="input-group">
        <label for="date_of_birth">Date of Birth *</label>
        <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($date_of_birth_val); ?>" required>
    </div>
    <div class="input-group">
        <label for="contact_number">Contact Number *</label>
        <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($contact_number_val); ?>" required>
    </div>
    <div class="input-group">
        <label for="member_email">Member Contact Email *</label>
        <input type="email" id="member_email" name="member_email" value="<?php echo htmlspecialchars($member_email_val); ?>" required>
    </div>

    <hr>
    <h3>Documents</h3>
    <div class="input-group">
        <label>Current Profile Image</label>
        <?php if ($member['profile_image_path'] && file_exists($member['profile_image_path'])): ?>
            <img src="<?php echo htmlspecialchars($member['profile_image_path']); ?>" alt="Profile Image" style="max-width: 150px; height: auto; display: block; margin-bottom: 10px;">
        <?php else: ?>
            <p>No profile image uploaded.</p>
        <?php endif; ?>
        <label for="new_profile_image">Upload New Profile Image (optional)</label>
        <input type="file" id="new_profile_image" name="new_profile_image" accept="image/jpeg,image/png">
    </div>
    <div class="input-group">
        <label>Current Aadhaar Card</label>
        <?php if ($aadhaar_doc && file_exists($aadhaar_doc['file_path'])): ?>
            <p><a href="<?php echo htmlspecialchars($aadhaar_doc['file_path']); ?>" target="_blank">View Current Document</a></p>
        <?php else: ?>
            <p>No Aadhaar card uploaded.</p>
        <?php endif; ?>
        <label for="new_aadhaar_card">Upload New Aadhaar Card (optional)</label>
        <input type="file" id="new_aadhaar_card" name="new_aadhaar_card" accept=".pdf,image/jpeg,image/png">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Update Member</button>
        <a href="member_management.php" class="btn btn-cancel">Cancel</a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
