<?php
// Sessioninställningar + start
session_start();

// Databasuppkoppling
$dsn    = 'mysql:host=localhost;dbname=gusten_investments;charset=utf8mb4';
$dbUser = 'min_anvandare';
$dbPass = 'mIssAn04';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Fel vid databasanslutning: " . $e->getMessage());
}

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];

    $sql = "SELECT * FROM users
            WHERE remember_token = :token
              AND remember_expires > NOW()
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        // Ev. förnya token eller uppdatera expires här
    }
}