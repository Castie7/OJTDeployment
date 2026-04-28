<?php
$host = 'localhost';
$user = 'root';
$pass = 'Server@123';
$db   = 'rootcrop_db';

try {
    $mysqli = new mysqli($host, $user, $pass, $db);
    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "Connected to $db\n";
    $result = $mysqli->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        echo $row[0] . "\n";
    }
    
    echo "\n--- researches structure ---\n";
    $result = $mysqli->query("DESCRIBE researches");
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }

    echo "\n--- research_details structure ---\n";
    $result = $mysqli->query("DESCRIBE research_details");
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    
    $mysqli->close();
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
