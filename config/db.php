<?php
// Verilənlər bazası konfiqurasiyası
// Quraşdırma: saytı açıb /install/ qovluğuna daxil olun — bu fayl quraşdırıcı tərəfindən avtomatik yazılır.
// Və ya lazımlı dəyərləri əl ilə doldurun:
$servername = "localhost";
$username = "";
$password = "";
$dbname = "";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
