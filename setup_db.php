<?php
try {
    $db = new PDO('sqlite:election.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents('sql.txt');

    $db->exec($sql);

    echo "Database and tables created successfully.";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>