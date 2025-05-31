<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "gestion_notes";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connexion échouée: " . $conn->connect_error);
}
?>
