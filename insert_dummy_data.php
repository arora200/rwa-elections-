<?php
try {
    $db = new PDO('sqlite:election.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents('dummy_data_sql.txt');

    $db->exec($sql);

    echo "Dummy data inserted successfully.";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>