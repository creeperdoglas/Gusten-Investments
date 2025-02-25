
<?php
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = $_POST['loginEmail']    ?? '';
    $password = $_POST['loginPassword'] ?? '';
    $remember = isset($_POST['rememberMe']) ? true : false;

    // 1. Hitta användare med given e-post
    $sql  = "SELECT * FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 2. Kontrollera lösenord
        if (password_verify($password, $user['password_hash'])) {
            // 3. Sätt session
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_email'] = $user['email'];

            // 4. Om “Kom ihåg mig” är ikryssat => generera token, spara i DB och cookie
            if ($remember) {
                $token  = bin2hex(random_bytes(16)); // 32 hex-tecken
                $expiry = date('Y-m-d H:i:s', time() + 60*60*24*30); // 30 dagar fram

                // Uppdatera users-tabellen
                $updSql = "UPDATE users
                           SET remember_token = :token,
                               remember_expires = :expires
                           WHERE id = :id";
                $updStmt = $pdo->prepare($updSql);
                $updStmt->bindParam(':token',   $token);
                $updStmt->bindParam(':expires', $expiry);
                $updStmt->bindParam(':id',      $user['id']);
                $updStmt->execute();

                // Sätt cookie, tex. 30 dagar
                setcookie(
                    'remember_me',
                    $token,
                    [
                      'expires' => time() + 60*60*24*30,
                      'path'    => '/',
                      'secure'  => true,  // true om HTTPS
                      'httponly'=> true,   // hindra JavaScript åtkomst
                      'samesite'=> 'Strict'
                    ]
                );
            }

            // 5. Skicka tillbaka till startsidan
            header("Location: index.php");
            exit;
        } else {
            $loginError = "Fel lösenord.";
        }
    } else {
        $loginError = "Ingen användare hittades med den e-posten.";
    }
} else {
    $loginError = "Ogiltig förfrågan.";
}

// Om något gick fel
$_SESSION['login_error'] = $loginError;
header("Location: index.php");
exit;
