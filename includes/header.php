<?php
session_start();

// Configure error logging
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/../error.log'); // Log errors to error.log in the project root

// CSRF Protection
if (empty($_SESSION['csrf_token']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function check_csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("CSRF token validation failed. Session ID: " . session_id() . ", IP: " . $_SERVER['REMOTE_ADDR']);
            die("CSRF token validation failed.");
        }
    }
}


// Include the database connection if not already included
if (!defined('DB_CONNECTION_INCLUDED')) {
    include_once 'db.php';
    define('DB_CONNECTION_INCLUDED', true);
}

$loggedIn = isset($_SESSION['user_id']);
$username = '';
$role_name = '';

if ($loggedIn) {
    $user_id = $_SESSION['user_id'];
    $role_id = $_SESSION['role_id'];

    // Fetch username
    $stmt = $db->prepare("SELECT username FROM users WHERE id = :user_id");
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $username = $user['username'];
    }

    // Fetch role name
    $stmt = $db->prepare("SELECT name FROM roles WHERE id = :role_id");
    $stmt->bindValue(':role_id', $role_id, PDO::PARAM_INT);
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($role) {
        $role_name = $role['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Election App'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuTT9Rk6nMT0WzHjG9oJ/zL/y6s/k/zLw6Z6aZ6cZ6g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" type="text/css" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
</head>
<body>
    <header class="main-header">
        <div class="container header-content">
            <div class="logo">
                <a href="dashboard.php">Aanchal Vihar Election Tracking </a>
            </div>
            <button class="menu-toggle" id="menu-toggle" aria-label="Toggle navigation" role="button" aria-expanded="false" aria-controls="main-nav">&#9776;</button>
            <nav class="main-nav" id="main-nav" aria-label="Main navigation">
                <ul>
                    <?php if ($loggedIn): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <?php if ($role_name == 'data_entry' || $role_name == 'super_admin'): ?>
                            <li><a href="register.php">Register Aanchal Vihar Member</a></li>
                        <?php endif; ?>
                        <?php if ($role_name == 'accounts' || $role_name == 'super_admin'): ?>
                            <li><a href="verify_payments.php">Verify Payments</a></li>
                        <?php endif; ?>
                        <?php if ($role_name == 'returning_officer' || $role_name == 'super_admin'): ?>
                            <li><a href="verify_documents.php">Verify Documents</a></li>
                            <li><a href="nominate.php">Nominate Candidate</a></li>
                            <li><a href="verify_nominations.php">Verify Nominations</a></li>
                            <li><a href="member_management.php">Member Management</a></li>
                        <?php endif; ?>
                        <?php if ($role_name == 'super_admin'): ?>
                            <li><a href="user_management.php">User Management</a></li>
                        <?php endif; ?>
                        <li class="user-info">
                            <span>Welcome, <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role_name); ?>)</span>
                            <a href="logout.php" class="btn btn-logout">Logout</a>
                        </li>
                    <?php else: ?>
                        <li><a href="index.php">Login</a></li>
                        <li><a href="register.php">Register as New Voter</a></li>
                        <li><a href="public_list.php">Public Lists</a></li>
                        <li><a href="about_election.php">About Election</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main class="main-content">
        <div class="container">
