<?php
// db.php

$host = 'localhost';
$db   = 'spendit';
$user = 'root'; // Change to your DB user
$pass = '';     // Change to your DB password

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}