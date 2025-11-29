<?php
$pageTitle = "Login";
include 'includes/header.php'; // session_start() and db.php are included here
include 'includes/validation_util.php'; // Include validation utility

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    check_csrf_token(); // CSRF protection

    $username = sanitize_string($_POST['username']);
    $password = sanitize_string($_POST['password']);

    // Using PDO for consistency, as SQLite3 is not directly used for query execution here.
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        // Also store username and role_name in session for easy access in header
        $_SESSION['username'] = $user['username'];
        $stmt = $db->prepare("SELECT name FROM roles WHERE id = :role_id");
        $stmt->bindValue(':role_id', $user['role_id'], PDO::PARAM_INT);
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['role_name'] = $role['name'];

        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password";
    }
}
?>
        <h2>Login</h2>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <div class="input-group">
                <button type="submit" class="btn">Login</button>
            </div>
        </form>
        <p class="register-link">New to the system? <a href="register.php">Register as New Voter</a></p>
        <p class="public-list-link">Looking for public information? <a href="public_list.php">View Public Lists of Voters & Candidates</a></p>
<?php include 'includes/footer.php'; ?>