<?php
$host = "localhost";
$user = "titanpr1_TitanPrint";
$pass = "titanpr1_TitanPrint";
$dbname = "titanpr1_TitanPrint";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>