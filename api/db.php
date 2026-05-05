<?php
$conn = new mysqli("localhost", "root", "", "wowasco");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>