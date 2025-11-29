<?php
$pageTitle = "Public Lists: Voters & Candidates";
// Manually include database connection
include_once 'includes/db.php';

// Fetch Qualified Voters
try {
    $stmt_voters = $db->prepare("SELECT full_name, unit_flat_number FROM members WHERE status = 'qualified_voter' ORDER BY full_name ASC");
    $stmt_voters->execute();
    $qualified_voters = $stmt_voters->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Public list voters query failed: " . $e->getMessage());
    $qualified_voters = []; // Avoid crashing the page
    $voter_error = "Could not retrieve the list of qualified voters at this time.";
}

// Fetch Approved Candidates
try {
    $stmt_candidates = $db->prepare(
        "SELECT m.full_name, m.unit_flat_number, obp.position_name 
         FROM candidates c
         JOIN members m ON c.member_id = m.id
         JOIN office_bearer_positions obp ON c.position_id = obp.id
         WHERE c.status = 'approved'
         ORDER BY obp.id, m.full_name"
    );
    $stmt_candidates->execute();
    $approved_candidates = $stmt_candidates->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Public list candidates query failed: " . $e->getMessage());
    $approved_candidates = [];
    $candidate_error = "Could not retrieve the list of approved candidates at this time.";
}

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
                    <li><a href="public_list.php" class="active">Public Lists</a></li>
                    <li><a href="about_election.php">About Election</a></li>
                    <li><a href="faq.php">FAQ</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main class="main-content">
        <div class="container">
            <h2>Public Election Information</h2>
            <p>This page shows the final, approved lists of qualified voters and nominated candidates for the upcoming election.</p>
            
            <hr>

            <h3>List of Approved Candidates</h3>
            <?php if (!empty($candidate_error)): ?>
                <p class="error"><?php echo $candidate_error; ?></p>
            <?php elseif (empty($approved_candidates)): ?>
                <p>There are no approved candidates to display at this time.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>Position</th>
                                <th>Candidate Name</th>
                                <th>Unit/Flat No.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approved_candidates as $candidate): ?>
                                <tr>
                                    <td data-label="Position"><strong><?php echo htmlspecialchars($candidate['position_name']); ?></strong></td>
                                    <td data-label="Candidate Name"><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                                    <td data-label="Unit/Flat No."><?php echo htmlspecialchars($candidate['unit_flat_number']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <hr>

            <h3>List of Qualified Voters</h3>
            <?php if (!empty($voter_error)): ?>
                <p class="error"><?php echo $voter_error; ?></p>
            <?php elseif (empty($qualified_voters)): ?>
                <p>There are no qualified voters to display at this time.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>Voter Name</th>
                                <th>Unit/Flat No.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($qualified_voters as $voter): ?>
                                <tr>
                                    <td data-label="Voter Name"><?php echo htmlspecialchars($voter['full_name']); ?></td>
                                    <td data-label="Unit/Flat No."><?php echo htmlspecialchars($voter['unit_flat_number']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

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
