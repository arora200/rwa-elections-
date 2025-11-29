<?php
$pageTitle = "Register New Member";
include 'includes/header.php'; // session_start() and db.php are included here
include 'includes/validation_util.php'; // Include validation utility

$is_logged_in = isset($_SESSION['user_id']);
$can_assisted_register = ($is_logged_in && ($_SESSION['role_name'] == 'data_entry' || $_SESSION['role_name'] == 'super_admin'));
$can_self_register = !$is_logged_in; // Allow if not logged in

// Redirect if not authorized for any type of registration
if (!$can_assisted_register && !$can_self_register) {
    header("Location: index.php");
    exit();
}

$success = '';
$error = '';
$errors = []; // Array to hold validation errors

// Get the voter role ID
$voter_role_id = null;
try {
    $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'voter'");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($role) {
        $voter_role_id = $role['id'];
    } else {
        $errors[] = "Voter role not found. Please ensure 'voter' role is configured in the database.";
    }
} catch (PDOException $e) {
    $errors[] = "Database error fetching voter role: " . $e->getMessage();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($errors)) {
    check_csrf_token(); // CSRF protection

    // --- Sanitize all inputs first ---
    $username = sanitize_string($_POST['username'] ?? '');
    $user_email = sanitize_email($_POST['user_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = sanitize_string($_POST['full_name'] ?? '');
    $unit_flat_number = sanitize_string($_POST['unit_flat_number'] ?? '');
    $full_residential_address = sanitize_string($_POST['full_residential_address'] ?? '');
    $occupation = sanitize_string($_POST['occupation'] ?? '');
    $date_of_birth = sanitize_string($_POST['date_of_birth'] ?? '');
    $contact_number = sanitize_string($_POST['contact_number'] ?? '');
    $member_email = sanitize_email($_POST['member_email'] ?? '');

    // --- Perform all validations ---

    // File Upload Validation
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
        if ($_FILES['profile_image']['size'] > (5 * 1024 * 1024)) { $errors[] = "Profile image size exceeds the maximum limit of 5 MB."; }
        $img_ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($img_ext, ['jpg', 'jpeg', 'png'])) { $errors[] = "Invalid profile image file type. Only JPG, JPEG, and PNG are allowed."; }
    } else { $errors[] = "Profile image is required."; }

    if (isset($_FILES['aadhaar_card']) && $_FILES['aadhaar_card']['error'] == UPLOAD_ERR_OK) {
        if ($_FILES['aadhaar_card']['size'] > (5 * 1024 * 1024)) { $errors[] = "Aadhaar card file size exceeds the maximum limit of 5 MB."; }
        $doc_ext = strtolower(pathinfo($_FILES['aadhaar_card']['name'], PATHINFO_EXTENSION));
        if (!in_array($doc_ext, ['jpg', 'jpeg', 'png', 'pdf'])) { $errors[] = "Invalid Aadhaar card file type. Only JPG, JPEG, PNG, and PDF are allowed."; }
    } else { $errors[] = "Aadhaar card document is required."; }

    // User Account Validation
    if (empty($username)) { $errors[] = "Username is required."; }
    if (empty($user_email) || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) { $errors[] = "A valid login email is required."; }
    if (empty($password)) { $errors[] = "Password is required for login."; }
    elseif (strlen($password) < 6) { $errors[] = "Password must be at least 6 characters."; }

    // Member Details Validation
    if (empty($full_name)) { $errors[] = "Full Name is required."; }
    elseif (!validate_alpha_space($full_name)) { $errors[] = "Full Name can only contain letters and spaces."; }

    if (empty($unit_flat_number)) { $errors[] = "Unit/Flat Number is required."; }
    elseif (!preg_match('/^[a-zA-Z0-9\-\s\/]+$/', $unit_flat_number)) { $errors[] = "Unit/Flat Number contains invalid characters."; }

    if (empty($full_residential_address)) { $errors[] = "Full Residential Address is required."; }

    if (empty($date_of_birth)) { $errors[] = "Date of Birth is required."; }
    elseif (!validate_date($date_of_birth)) { $errors[] = "Date of Birth is not a valid date (YYYY-MM-DD)."; }
    else {
        $dob_timestamp = strtotime($date_of_birth);
        $min_age_timestamp = strtotime('-18 years');
        if ($dob_timestamp > $min_age_timestamp) { $errors[] = "Member must be at least 18 years old."; }
    }

    if (empty($contact_number)) { $errors[] = "Contact Number is required."; }
    elseif (!validate_digits($contact_number) || strlen($contact_number) < 10 || strlen($contact_number) > 15) { $errors[] = "Contact Number must be 10-15 digits."; }

    if (empty($member_email)) { $errors[] = "Member Contact Email is required."; }
    elseif (!validate_email($member_email)) { $errors[] = "Member Contact Email is not in a valid format."; }

    // --- Database Duplicate Checks (only if other validations pass) ---
    if (empty($errors)) {
        try {
            // Check for duplicate user account
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :user_email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':user_email', $user_email);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "A user account with this username or login email already exists.";
            }

            // Check for duplicate member details
            $stmt = $db->prepare("SELECT COUNT(*) FROM members WHERE unit_flat_number = :unit_flat_number OR email = :member_email");
            $stmt->bindParam(':unit_flat_number', $unit_flat_number);
            $stmt->bindParam(':member_email', $member_email);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "A member with this Unit/Flat Number or Contact Email already exists.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database validation error: " . $e->getMessage();
        }
    }


    // --- Process if NO errors found ---
    if (empty($errors)) {
        // All validations passed, now handle file moving
        $profile_image_dir = 'uploads/profiles/';
        $aadhaar_card_dir = 'uploads/documents/';
        
        if (!is_dir($profile_image_dir)) { mkdir($profile_image_dir, 0777, true); }
        if (!is_dir($aadhaar_card_dir)) { mkdir($aadhaar_card_dir, 0777, true); }

        $img_ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $doc_ext = strtolower(pathinfo($_FILES['aadhaar_card']['name'], PATHINFO_EXTENSION));
        
        $profile_image_name = 'profile_' . uniqid() . '.' . $img_ext;
        $aadhaar_card_name = 'aadhaar_' . uniqid() . '.' . $doc_ext;

        $profile_image_path = $profile_image_dir . $profile_image_name;
        $aadhaar_card_path = $aadhaar_card_dir . $aadhaar_card_name;
        
        $moved_profile = move_uploaded_file($_FILES['profile_image']['tmp_name'], $profile_image_path);
        $moved_aadhaar = move_uploaded_file($_FILES['aadhaar_card']['tmp_name'], $aadhaar_card_path);

        if ($moved_profile && $moved_aadhaar) {
            try {
                $db->beginTransaction();

                // 3. Create User Account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, role_id) VALUES (:username, :password_hash, :user_email, :role_id)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password_hash', $hashed_password);
                $stmt->bindParam(':user_email', $user_email);
                $stmt->bindParam(':role_id', $voter_role_id, PDO::PARAM_INT);
                $stmt->execute();
                $new_user_id = $db->lastInsertId();

                // 4. Create Member Record
                $stmt = $db->prepare("INSERT INTO members (full_name, unit_flat_number, full_residential_address, occupation, date_of_birth, contact_number, email, created_by, profile_image_path, status) VALUES (:full_name, :unit_flat_number, :full_residential_address, :occupation, :date_of_birth, :contact_number, :member_email, :created_by, :profile_image_path, 'documents_uploaded')");
                $stmt->bindValue(':full_name', $full_name);
                $stmt->bindValue(':unit_flat_number', $unit_flat_number);
                $stmt->bindValue(':full_residential_address', $full_residential_address);
                $stmt->bindValue(':occupation', $occupation);
                $stmt->bindValue(':date_of_birth', $date_of_birth);
                $stmt->bindValue(':contact_number', $contact_number);
                $stmt->bindValue(':member_email', $member_email);
                $stmt->bindValue(':created_by', $new_user_id);
                $stmt->bindValue(':profile_image_path', $profile_image_path);
                $stmt->execute();
                $new_member_id = $db->lastInsertId();

                // 5. Create Aadhaar Card Document Record
                $stmt = $db->prepare("INSERT INTO documents (member_id, document_type, file_path, status) VALUES (:member_id, 'Aadhaar Card', :file_path, 'pending')");
                $stmt->bindParam(':member_id', $new_member_id);
                $stmt->bindParam(':file_path', $aadhaar_card_path);
                $stmt->execute();

                $db->commit();
                $success = "Member '{$full_name}' and user '{$username}' registered successfully! Documents are uploaded.";
                
                if ($can_self_register) {
                    $_SESSION['user_id'] = $new_user_id;
                    $_SESSION['role_id'] = $voter_role_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role_name'] = 'voter';
                }
                
                header("Location: dashboard.php?message=" . urlencode($success));
                exit();

            } catch (PDOException $e) {
                $db->rollBack();
                if (file_exists($profile_image_path)) unlink($profile_image_path);
                if (file_exists($aadhaar_card_path)) unlink($aadhaar_card_path);
                $error = "Database error during registration: " . $e->getMessage();
                error_log("Database Error in register.php: " . $e->getMessage());
            }
        } else {
             if (file_exists($profile_image_path)) unlink($profile_image_path);
             if (file_exists($aadhaar_card_path)) unlink($aadhaar_card_path);
             $errors[] = "Failed to save uploaded files. Please ensure the 'uploads' directory is writable.";
        }
    } 
    
    // If we reach here, it means there were errors from validation or processing
    if (!empty($errors)) {
        $error = "Please correct the following errors: <br>" . implode("<br>", $errors);
    }

} else if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($errors)) {
    // This catches file validation errors from the top of the script
    $error = "Please correct the following errors: <br>" . implode("<br>", $errors);
} else if (!empty($errors)) { // Catch errors from initial role fetching etc.
    $error = "An error occurred: <br>" . implode("<br>", $errors);
}

// Populate fields for form display in case of validation errors
$full_name_val = $_POST['full_name'] ?? '';
$unit_flat_number_val = $_POST['unit_flat_number'] ?? '';
$full_residential_address_val = $_POST['full_residential_address'] ?? '';
$occupation_val = $_POST['occupation'] ?? '';
$date_of_birth_val = $_POST['date_of_birth'] ?? '';
$contact_number_val = $_POST['contact_number'] ?? '';
$member_email_val = $_POST['member_email'] ?? '';
$username_val = $_POST['username'] ?? '';
$user_email_val = $_POST['user_email'] ?? '';

?>
        <h2>Register New Member</h2>
<?php include 'includes/message.php'; ?>

<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'link' => 'dashboard.php'],
    ['label' => 'Register New Member', 'link' => '']
];
include 'includes/breadcrumb.php';
?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <?php if ($can_assisted_register): ?>
                <p>You are logged in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role_name']); ?>)</strong> and can register a member on their behalf.</p>
            <?php else: ?>
                <p>Register a new account and member profile. All documents are mandatory.</p>
            <?php endif; ?>

            <h3>Your Login Details <small style="font-weight: normal;">(* indicates a required field)</small></h3>
            <div class="input-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username_val); ?>" required>
            </div>
            <div class="input-group">
                <label for="user_email">Login Email *</label>
                <input type="email" id="user_email" name="user_email" value="<?php echo htmlspecialchars($user_email_val); ?>" required>
            </div>
            <div class="input-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required>
            </div>

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
                <label for="profile_image">Profile Image (JPG, PNG) *</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png" required>
            </div>
            <div class="input-group">
                <label for="aadhaar_card">Aadhaar Card (PDF, JPG, PNG) *</label>
                <input type="file" id="aadhaar_card" name="aadhaar_card" accept=".pdf,image/jpeg,image/png" required>
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
            <div class="form-actions">
                <button type="submit" class="btn">Register Member</button>
            </div>
        </form>
<?php include 'includes/footer.php'; ?>