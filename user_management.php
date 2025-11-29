<?php
$pageTitle = "User Management";
include 'includes/header.php';
include 'includes/validation_util.php'; // For sanitization

// Check if the user is logged in and is a super_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_name'] !== 'super_admin') {
    header("Location: index.php"); // Redirect to login if not authorized
    exit();
}

$errors = [];
$success_message = '';
$edit_user = null; // Will hold user data if in edit mode

// Fetch all roles for the dropdown
try {
    $stmt = $db->prepare("SELECT id, name FROM roles ORDER BY name");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error fetching roles: " . $e->getMessage();
    $roles = [];
}

// Handle POST requests for Add/Edit/Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token(); // CSRF protection

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $username = sanitize_string($_POST['username']);
                $email = sanitize_string($_POST['email']);
                $password = $_POST['password'];
                $role_id = filter_var($_POST['role_id'], FILTER_VALIDATE_INT);

                // Basic Validation
                if (empty($username)) { $errors[] = "Username is required."; }
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "A valid email is required."; }
                if (empty($password)) { $errors[] = "Password is required."; }
                if (!$role_id || !in_array($role_id, array_column($roles, 'id'))) { $errors[] = "Invalid role selected."; }

                if (empty($errors)) {
                    try {
                        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':email', $email);
                        $stmt->execute();
                        if ($stmt->fetchColumn() > 0) {
                            $errors[] = "Username or email already exists.";
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, role_id) VALUES (:username, :password_hash, :email, :role_id)");
                            $stmt->bindParam(':username', $username);
                            $stmt->bindParam(':password_hash', $hashed_password);
                            $stmt->bindParam(':email', $email);
                            $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
                            $stmt->execute();
                            $success_message = "User '{$username}' added successfully!";
                            header("Location: user_management.php?message=" . urlencode($success_message));
                            exit();
                        }
                    } catch (PDOException $e) { $errors[] = "Database error: " . $e->getMessage(); }
                }
                break;

            case 'edit_user':
                $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
                $username = sanitize_string($_POST['username']);
                $email = sanitize_string($_POST['email']);
                $password = $_POST['password']; // New password, optional
                $role_id = filter_var($_POST['role_id'], FILTER_VALIDATE_INT);

                // Basic Validation
                if (!$user_id) { $errors[] = "Invalid User ID for editing."; }
                if (empty($username)) { $errors[] = "Username is required."; }
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "A valid email is required."; }
                if (!$role_id || !in_array($role_id, array_column($roles, 'id'))) { $errors[] = "Invalid role selected."; }

                if (empty($errors)) {
                    try {
                        // Check for duplicate username/email excluding current user
                        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE (username = :username OR email = :email) AND id != :user_id");
                        $stmt->bindParam(':username', $username);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        if ($stmt->fetchColumn() > 0) {
                            $errors[] = "Username or email already exists for another user.";
                        } else {
                            $sql = "UPDATE users SET username = :username, email = :email, role_id = :role_id";
                            if (!empty($password)) {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $sql .= ", password_hash = :password_hash";
                            }
                            $sql .= " WHERE id = :user_id";

                            $stmt = $db->prepare($sql);
                            $stmt->bindParam(':username', $username);
                            $stmt->bindParam(':email', $email);
                            $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
                            if (!empty($password)) {
                                $stmt->bindParam(':password_hash', $hashed_password);
                            }
                            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                            $stmt->execute();
                            $success_message = "User '{$username}' updated successfully!";
                            header("Location: user_management.php?message=" . urlencode($success_message));
                            exit();
                        }
                    } catch (PDOException $e) { $errors[] = "Database error: " . $e->getMessage(); }
                }
                break;
        }
    }
}

// Handle GET requests for Edit/Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'edit':
            $user_id_to_edit = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            if ($user_id_to_edit) {
                try {
                    $stmt = $db->prepare("SELECT u.id, u.username, u.email, u.role_id, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id");
                    $stmt->bindParam(':id', $user_id_to_edit, PDO::PARAM_INT);
                    $stmt->execute();
                    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$edit_user) {
                        $errors[] = "User not found for editing.";
                    }
                } catch (PDOException $e) {
                    $errors[] = "Database error fetching user for edit: " . $e->getMessage();
                }
            } else {
                $errors[] = "Invalid user ID for editing.";
            }
            break;

        case 'delete':
            $user_id_to_delete = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            if ($user_id_to_delete && $user_id_to_delete != $_SESSION['user_id']) { // Prevent self-deletion
                try {
                    check_csrf_token(); // CSRF protection for GET delete (though usually POST is preferred)
                    // In a real application, you'd likely use a POST request for deletions for better security
                    // or require a CSRF token confirmation on the delete link itself.
                    // For this example, we'll proceed with GET for simplicity.

                    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
                    $stmt->bindParam(':id', $user_id_to_delete, PDO::PARAM_INT);
                    $stmt->execute();
                    if ($stmt->rowCount() > 0) {
                        $success_message = "User deleted successfully!";
                    } else {
                        $errors[] = "User not found or could not be deleted.";
                    }
                    header("Location: user_management.php?message=" . urlencode($success_message));
                    exit();
                } catch (PDOException $e) {
                    $errors[] = "Database error deleting user: " . $e->getMessage();
                }
            } else {
                $errors[] = "Invalid user ID for deletion or cannot delete your own account.";
            }
            break;
    }
}


// Display messages after redirect (for GET actions)
if (isset($_GET['message'])) {
    $success_message = htmlspecialchars($_GET['message']);
}


// Fetch all users and their roles from the database (for listing)
try {
    $stmt = $db->prepare("SELECT u.id, u.username, u.email, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.username");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error fetching users: " . $e->getMessage();
    $users = [];
}
?>

<?php
// We need to pass the local $errors array and $success_message string to the message component
$current_errors = $errors;
$current_success = $success_message;
include 'includes/message.php';
?>

<?php
$breadcrumbs = [
    ['label' => 'Dashboard', 'link' => 'dashboard.php'],
    ['label' => 'User Management', 'link' => '']
];
include 'includes/breadcrumb.php';
?>

<h2>User Management</h2>

<div class="user-management-actions">
    <?php if ($edit_user): ?>
        <h3>Edit User: <?php echo htmlspecialchars($edit_user['username']); ?></h3>
        <form method="POST" class="edit-user-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($edit_user['id']); ?>">
            <div class="input-group">
                <label for="username_edit">Username:</label>
                <input type="text" id="username_edit" name="username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
            </div>
            <div class="input-group">
                <label for="email_edit">Email:</label>
                <input type="email" id="email_edit" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
            </div>
            <div class="input-group">
                <label for="password_edit">New Password (leave blank to keep current):</label>
                <input type="password" id="password_edit" name="password">
            </div>
            <div class="input-group">
                <label for="role_id_edit">Role:</label>
                <select id="role_id_edit" name="role_id" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo htmlspecialchars($role['id']); ?>" <?php echo ($role['id'] == $edit_user['role_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Update User</button>
                <a href="user_management.php" class="btn btn-cancel">Cancel</a>
            </div>
        </form>
    <?php else: ?>
        <h3>Add New User</h3>
        <form method="POST" class="add-user-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="add_user">
            <div class="input-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="input-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="input-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="input-group">
                <label for="role_id">Role:</label>
                <select id="role_id" name="role_id" required>
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Add User</button>
            </div>
        </form>
    <?php endif; ?>

    <h3>Existing Users</h3>
    <?php if (empty($users)): ?>
        <p>No users found in the system.</p>
    <?php else: ?>
        <div class="table-container">
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td data-label="ID"><?php echo htmlspecialchars($user['id']); ?></td>
                            <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                            <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td data-label="Role"><?php echo htmlspecialchars($user['role_name']); ?></td>
                            <td data-label="Actions" class="actions-cell">
                                <a href="user_management.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-edit btn-small" aria-label="Edit User"><i class="fas fa-edit"></i></a>
                                <?php if ($user['id'] != $_SESSION['user_id']): // Prevent self-deletion ?>
                                    <a href="user_management.php?action=delete&id=<?php echo $user['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn btn-delete btn-small" onclick="return confirm('Are you sure you want to delete this user?');" aria-label="Delete User"><i class="fas fa-trash-alt"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
