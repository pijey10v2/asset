<?php
// Connection settings 
error_reporting(1);
GLOBAL $conn ;

$host = "127.0.0.1";
$port = 3307;
$user = "root";
$password = "";
$database = "jwdb"; // Replace with your DB name

// Create connection
$conn = new mysqli($host, $user, $password, $database, $port);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// // Your SQL query
$sql = "SELECT * FROM app_fd_inv_pavement"; // Replace with your table name

$result = $conn->query($sql);
// // Check if rows exist
if ($result->num_rows > 0) {
    // Fetch data
    while($row = $result->fetch_assoc()) {
        echo "ID: " . $row["id"] . " - Name: " . $row["createdBy"] . "<br>"; // Replace with your column names
    }
} else {
    echo "0 results";
}

// Close connection
$conn->close();
?>