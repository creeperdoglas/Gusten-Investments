<?php
// Om formuläret skickas via POST, hantera datainsättningen
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Hämta in data från formuläret
    $email         = $_POST['email']        ?? '';
    $phone         = $_POST['phone']        ?? '';
    $address       = $_POST['address']      ?? '';
    $address2      = $_POST['address2']     ?? '';
    $postnummer    = $_POST['Postnummer']   ?? '';
    $stad          = $_POST['stad']         ?? '';
    $password      = $_POST['password']     ?? '';
    $confirmPass   = $_POST['confirmPassword'] ?? '';
    
    // 2. Validera att lösenorden matchar
    if ($password !== $confirmPass) {
        $errorMessage = "Lösenorden matchar inte!";
    } else {
        // 3. Hasha lösenordet
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // 4. Förifyll investerat, risk1, risk2, risk3 (heltal)
        //    Just nu sätter vi dem till 0 eller valfritt startvärde. 
        //    Du kan även hämta in dem från formuläret om du vill att användaren ska välja risker direkt.
        $investerat = 0; 
        $risk1      = 0;
        $risk2      = 0;
        $risk3      = 0;

        // 5. Koppla upp mot databasen via PDO
        $dsn = 'mysql:host=localhost;dbname=gusten_investments;charset=utf8mb4';
        $dbUser = 'min_anvandare';      
        $dbPass = 'mIssAn04';  

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 6. Förbered en INSERT-sats
            $sql = "INSERT INTO users (email, phone, address, address2, postnummer, stad, password_hash, investerat, risk1, risk2, risk3)
                    VALUES (:email, :phone, :address, :address2, :postnummer, :stad, :hash, :investerat, :risk1, :risk2, :risk3)";

            $stmt = $pdo->prepare($sql);

            // 7. Binda parametrar och köra
            $stmt->bindParam(':email',       $email);
            $stmt->bindParam(':phone',       $phone);
            $stmt->bindParam(':address',     $address);
            $stmt->bindParam(':address2',    $address2);
            $stmt->bindParam(':postnummer',  $postnummer);
            $stmt->bindParam(':stad',        $stad);
            $stmt->bindParam(':hash',        $hashedPassword);
            $stmt->bindParam(':investerat',  $investerat, PDO::PARAM_INT);
            $stmt->bindParam(':risk1',       $risk1,      PDO::PARAM_INT);
            $stmt->bindParam(':risk2',       $risk2,      PDO::PARAM_INT);
            $stmt->bindParam(':risk3',       $risk3,      PDO::PARAM_INT);

            $stmt->execute();

            // 8. Meddela att skapandet lyckades eller gör en redirect
            //    T.ex. spara en sessionsvariabel eller visa meddelande
            $successMessage = "Användare skapades! Du kan nu logga in.";

        } catch (PDOException $e) {
            $errorMessage = "Fel vid databasanslutning eller INSERT: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="sv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Skapa användare</title>
  <link rel="stylesheet" href="master.css">
  <script src="https://kit.fontawesome.com/bfa6b27da4.js" crossorigin="anonymous"></script>
</head>
<body class="create-user-body">

  <header class="top-bar">
    <div class="brand">G-Invest</div>
  </header>

  <main class="create-user-main">
    <div class="create-user-box">
      <h2>Skapa användare</h2>
      
      <!-- Eventuella fel- och framgångsmeddelanden -->
      <?php if (!empty($errorMessage)): ?>
        <div style="color: #f66; margin-bottom: 1rem;">
          <?php echo htmlspecialchars($errorMessage); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($successMessage)): ?>
        <div style="color: #6f6; margin-bottom: 1rem;">
          <?php echo htmlspecialchars($successMessage); ?>
        </div>
      <?php endif; ?>

      <form id="createUserForm" action="" method="POST">
        <label for="email">E-post</label>
        <input type="email" id="email" name="email" required>

        <label for="phone">Telefon</label>
        <input type="tel" id="phone" name="phone" required>

        <label for="address">Adress</label>
        <input type="text" id="address" name="address" required>

        <label for="address2">Adressrad 2</label>
        <input type="text" id="address2" name="address2">

        <label for="postnummer">Postnummer</label>
        <input type="text" id="postnummer" name="Postnummer" required>

        <label for="stad">Stad</label>
        <input type="text" id="stad" name="stad" required>

        <label for="password">Lösenord</label>
        <input type="password" id="password" name="password" required>

        <label for="confirmPassword">Bekräfta lösenord</label>
        <input type="password" id="confirmPassword" name="confirmPassword" required>

        <button type="submit">Skapa konto</button>
      </form>
      
      <!-- Tillbaka-knapp, t ex: -->
      <div style="margin-top: 1rem; text-align: center;">
        <a href="index.php" class="back-link">Gå tillbaka</a>
      </div>
    </div>
  </main>

  <script>
    // Enkel klient-side-kontroll av att lösenorden matchar
    const form = document.getElementById('createUserForm');
    form.addEventListener('submit', (e) => {
      const password        = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirmPassword').value;

      if(password !== confirmPassword) {
        e.preventDefault(); 
        alert("Lösenorden stämmer inte överens. Försök igen.");
      }
    });
  </script>

</body>
</html>

