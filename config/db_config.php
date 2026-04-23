<?php
$host = 'localhost';
// $dbname = 'u403269249_muhaddisa_db';
// $db_user = 'u403269249_muhaddisauser';
// $db_pass = 'u403269249_Pass123';



// $dbname = 'u403269249_msst_main_db';
// $db_user = 'u403269249_msst_main_user';
// $db_pass = 'u403269249_msst_main_Pass'; 

$host = 'localhost';
$dbname = 'msst_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}
?>