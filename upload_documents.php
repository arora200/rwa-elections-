<?php
$pageTitle = "Upload Documents";
include 'includes/header.php'; // session_start() and db.php are included here
include 'includes/validation_util.php'; // Include validation utility

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_role_name = $_SESSION['role_name'];

$errors = [];
$success_message = '';
$target_member_id = null;
$member_full_name = '';

// Determine the member_id for which documents are being uploaded
// Priority 1: member_id passed via GET (e.g., by data_entry or super_admin after registration)
if (isset($_GET['member_id'])) {
    $get_member_id = filter_var($_GET['member_id'], FILTER_VALIDATE_INT);
    if ($get_member_id) {
        // Check if the current user is authorized to upload for this member_id
        // Allow if current user is super_admin, data_entry, OR the member themselves
        $stmt_check_auth = $db->prepare("SELECT created_by FROM members WHERE id = :member_id");
        $stmt_check_auth->bindParam(':member_id', $get_member_id, PDO::PARAM_INT);
        $stmt_check_auth->execute();
        $member_creator_user_id = $stmt_check_auth->fetchColumn();

        if ($current_role_name == 'super_admin' || $current_role_name == 'data_entry' || $member_creator_user_id == $current_user_id) {
            $target_member_id = $get_member_id;
        } else {
            $errors[] = "You are not authorized to upload documents for this member.";
        }
    } else {
        $errors[] = "Invalid Member ID provided.";
    }
} 

// Priority 2: If not from GET, find member_id associated with the current logged-in user
// This path is primarily for a voter who registered themselves and is now logged in.
if (!$target_member_id && empty($errors) && $current_role_name == 'voter') {
    try {
        $stmt = $db->prepare("SELECT id FROM members WHERE created_by = :user_id");
        $stmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
        $stmt->execute();
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($member) {
            $target_member_id = $member['id'];
        } else {
            $errors[] = "You are logged in, but not associated with a member profile. Please register as a member first.";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error fetching member ID: " . $e->getMessage();
    }
} else if (!$target_member_id && empty($errors)) { // If not a voter trying to self-upload, and no member_id provided
    $errors[] = "No member specified for document upload. Please navigate from a member's profile or registration process.";
}


// Fetch member's full name for display if target_member_id is set
if ($target_member_id) {
    try {
        $stmt_name = $db->prepare("SELECT full_name FROM members WHERE id = :member_id");
        $stmt_name->bindParam(':member_id', $target_member_id, PDO::PARAM_INT);
        $stmt_name->execute();
        $member_info = $stmt_name->fetch(PDO::FETCH_ASSOC);
        if ($member_info) {
            $member_full_name = htmlspecialchars($member_info['full_name']);
        }
    } catch (PDOException $e) {
        $errors[] = "Database error fetching member name: " . $e->getMessage();
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && $target_member_id && empty($errors)) {
    check_csrf_token(); // CSRF protection

    $document_type = sanitize_string($_POST['document_type'] ?? '');

    // Validate document type
    $allowed_document_types = ['Aadhaar Card', 'Voter ID', 'Passport', 'Driving License', 'Educational Certificate', 'Other'];
    if (!in_array($document_type, $allowed_document_types)) {
        $errors[] = "Invalid document type selected.";
    }

    // Handle file upload
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['document_file']['tmp_name'];
        $file_name = $_FILES['document_file']['name'];
        $file_size = $_FILES['document_file']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $max_file_size = 5 * 1024 * 1024; // 5 MB

        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "Invalid file type. Only JPG, JPEG, PNG, and PDF are allowed.";
        }
        if ($file_size > $max_file_size) {
            $errors[] = "File size exceeds the maximum limit of 5 MB.";
        }

        if (empty($errors)) {
            // Create a unique file name and path
            $upload_dir = 'uploads/documents/'; // Make sure this directory exists and is writable
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_file_name = uniqid('doc_') . '.' . $file_ext;
            $file_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp_path, $file_path)) {
                try {
                    // Insert document record into the database
                    $stmt = $db->prepare("INSERT INTO documents (member_id, document_type, file_path) VALUES (:member_id, :document_type, :file_path)");
                    $stmt->bindParam(':member_id', $target_member_id, PDO::PARAM_INT);
                    $stmt->bindParam(':document_type', $document_type, PDO::PARAM_STR);
                    $stmt->bindParam(':file_path', $file_path, PDO::PARAM_STR);
                    $stmt->execute();

                    $success_message = "Document '{$document_type}' uploaded successfully for " . $member_full_name . "!";
                    // Redirect to prevent form resubmission
                    header("Location: upload_documents.php?member_id=" . $target_member_id . "&message=" . urlencode($success_message));
                    exit();

                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                    unlink($file_path); // Delete uploaded file if DB insert fails
                }
            } else {
                $errors[] = "Failed to move uploaded file.";
            }
        }
    } else if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors[] = "File upload error: " . $_FILES['document_file']['error'];
    } else {
        $errors[] = "No file selected for upload.";
    }
}

// Display messages after redirect
if (isset($_GET['message'])) {
    $success_message = htmlspecialchars($_GET['message']);
}
?>

<h2>Upload Additional Documents <?php echo $member_full_name ? "for " . $member_full_name : ""; ?></h2>

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

<?php if ($target_member_id): ?>
    <p>Use this form to upload any *additional* documents. (Aadhaar Card is uploaded during registration).</p>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <div class="input-group">
            <label for="document_type">Document Type:</label>
            <select id="document_type" name="document_type" required>
                <option value="">Select Document Type</option>
                <option value="Voter ID">Voter ID</option>
                <option value="Passport">Passport</option>
                <option value="Driving License">Driving License</option>
                <option value="Educational Certificate">Educational Certificate</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="input-group">
            <label for="document_file">Select File (JPG, PNG, PDF - Max 5MB):</label>
            <input type="file" id="document_file" name="document_file" accept=".jpg,.jpeg,.png,.pdf" required>
        </div>
        <div class="input-group">
            <button type="submit" class="btn">Upload Document</button>
        </div>
    </form>
<?php else: ?>
    <p class="error">Unable to determine the member for document upload. Please ensure you are logged in and associated with a member profile, or contact support.</p>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
