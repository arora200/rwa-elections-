<?php
$pageTitle = "About the Election";
// This is a public page, so we build the header manually.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container header-content">
            <div class="logo">
                <a href="index.php">Aanchal Vihar Election Application</a>
            </div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php">Admin Login</a></li>
                    <li><a href="public_list.php">Public Lists</a></li>
                    <li><a href="about_election.php" class="active">About Election</a></li>
                    <li><a href="faq.php">FAQ</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main class="main-content">
        <div class="container">
            <h2>About the RWA Election 2025</h2>
            <p>This page contains important details and dates for the upcoming Resident Welfare Association election.</p>
            
            <div class="election-timeline">
                <h3>Election Timeline</h3>
                <ul>
                    <li>
                        <strong>Voter Registration Period:</strong>
                        <span>December 1, 2025 - December 15, 2025</span>
                    </li>
                    <li>
                        <strong>Deadline for Payment & Document Verification:</strong>
                        <span>December 20, 2025</span>
                    </li>
                    <li>
                        <strong>Publication of Final Voter List:</strong>
                        <span>December 22, 2025</span>
                    </li>
                    <li>
                        <strong>Nomination Period for Candidates:</strong>
                        <span>December 23, 2025 - December 28, 2025</span>
                    </li>
                     <li>
                        <strong>Scrutiny and Final List of Candidates:</strong>
                        <span>December 29, 2025</span>
                    </li>
                    <li>
                        <strong>Election Day:</strong>
                        <span>January 5, 2026</span>
                    </li>
                     <li>
                        <strong>Result Declaration:</strong>
                        <span>January 5, 2026 (Evening)</span>
                    </li>
                </ul>
            </div>

            <div class="election-details">
                <h3>Details</h3>
                <p>The election is being held to elect the new managing committee for the RWA. All residents are encouraged to participate, ensure their details are correct, and cast their vote on election day.</p>
                <p>For any queries not covered in the <a href="faq.php">FAQ</a>, please contact the RWA office.</p>
            </div>

        </div>
    </main>
    <footer class="main-footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> RWA Election Committee. All Rights Reserved.</p>
            <p>Made in India, by Ecologic</p>
        </div>
    </footer>
</body>
</html>
