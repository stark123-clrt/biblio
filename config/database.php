<?php

$host = "localhost";
$dbname = "mydb"; 
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES utf8");
} catch(PDOException $e) {
    echo "Erreur de connexion : " . $e->getMessage();
    die();
}
?>
