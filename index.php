<?php
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);

// ====== Hantera utloggning ======
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    setcookie('remember_me', '', time() - 3600, '/');
    if (isset($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];
        $sql = "UPDATE users
                SET remember_token = NULL,
                    remember_expires = NULL
                WHERE remember_token = :token";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
    }
    header("Location: index.php");
    exit;
}

// ====== Eventuellt login-error ======
$loginError = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

// För diagram
$dateLabelsJson = '[]';
$totalsJson     = '[]';

// Variabel för välkomstmeddelande
$welcomeMessage = '';

if ($isLoggedIn) {
    $userId = $_SESSION['user_id'];

    // 1) Hämta user-info: first_name, investerat (ur tabellen users)
    $sqlUser = "SELECT first_name, investerat
                FROM users
                WHERE id = :id
                LIMIT 1";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([':id' => $userId]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($userRow) {
        $firstName  = $userRow['first_name'];
        $investerat = (int)$userRow['investerat'];
    } else {
        // Om inte hittas, sätt standard
        $firstName  = 'Okänd';
        $investerat = 0;
    }

    // 2) Hämta SENASTE riskposten i user_risk_history
    //    för att få aktuella risk1, risk2, risk3
    $sqlLatest = "SELECT risk1, risk2, risk3
                  FROM user_risk_history
                  WHERE user_id = :uid
                  ORDER BY changed_at DESC
                  LIMIT 1";
    $stmtLatest = $pdo->prepare($sqlLatest);
    $stmtLatest->execute([':uid' => $userId]);
    $latestRow = $stmtLatest->fetch(PDO::FETCH_ASSOC);

    // Om vi inte har någon historik, sätt risk = 0
    if ($latestRow) {
        $risk1 = (int)$latestRow['risk1'];
        $risk2 = (int)$latestRow['risk2'];
        $risk3 = (int)$latestRow['risk3'];
    } else {
        $risk1 = 0;
        $risk2 = 0;
        $risk3 = 0;
    }

    // Beräkna total risk
    $totalRisk = $risk1 + $risk2 + $risk3;

    // Skapa välkomstmeddelande (tjänat/förlorat)
    if ($totalRisk > $investerat) {
        $profit = $totalRisk - $investerat;
        $welcomeMessage = "Välkommen $firstName! Du har tjänat $profit.";
    } elseif ($totalRisk < $investerat) {
        $loss = $investerat - $totalRisk;
        $welcomeMessage = "Välkommen $firstName! Du har förlorat $loss.";
    } else {
        $welcomeMessage = "Välkommen $firstName! Du ligger plus/minus noll just nu.";
    }

    // 3) Skapa diagram-data
    //    Hämta HELA historiken i stigande ordning
    $sqlHist = "SELECT risk1, risk2, risk3, changed_at
                FROM user_risk_history
                WHERE user_id = :uid
                ORDER BY changed_at ASC";
    $stmtHist = $pdo->prepare($sqlHist);
    $stmtHist->execute([':uid' => $userId]);
    $histRows = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

    $dateLabels = [];
    $totals     = [];

    // Första punkt = investerat
    $dateLabels[] = "Start";
    $totals[]     = $investerat;

    // Loopar historiken
    foreach ($histRows as $row) {
        // Summan av risk1+risk2+risk3
        $sum = $row['risk1'] + $row['risk2'] + $row['risk3'];
        // Endast datum (YYYY-MM-DD)
        $justDate = date('Y-m-d', strtotime($row['changed_at']));

        $dateLabels[] = $justDate;
        $totals[]     = $sum;
    }

    // Gör arrayerna till JSON
    $dateLabelsJson = json_encode($dateLabels);
    $totalsJson     = json_encode($totals);
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>G-Invest</title>
  <link rel="stylesheet" href="master.css">
  <!-- Font Awesome -->
  <script src="https://kit.fontawesome.com/bfa6b27da4.js" crossorigin="anonymous"></script>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <!-- Toppmeny -->
  <header class="top-bar">
    <div class="brand">G-Invest</div>

    <div class="user" id="userIcon">
      <i class="fa-solid fa-user"></i>
      <div class="user-menu" id="userMenu">
        <?php if (!$isLoggedIn): ?>
          <!-- EJ inloggad -->
          <div class="menu-item" id="loginBtn">
            <i class="fa-solid fa-bars"></i>
            <span>Logga in</span>
          </div>
        <?php else: ?>
          <!-- Inloggad: "Min profil" + "Logga ut" -->
          <div class="menu-item">
            <i class="fa-solid fa-user"></i>
            <span><a href="#" style="color:#fff; text-decoration:none;">Min profil</a></span>
          </div>

          <!-- GÖR HELA menyposten klickbar för utloggning -->
          <div class="menu-item" id="logoutBtn" style="cursor:pointer;">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logga ut</span>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- Sidomeny -->
  <aside class="sidebar">
    <nav>
      <ul>
        <li>
          <a href="#">
            <i class="fa-solid fa-home"></i>
            <span>Hem</span>
          </a>
        </li>
        <li>
          <a href="#">
            <i class="fa-solid fa-newspaper"></i>
            <span>Nyheter</span>
          </a>
        </li>
        <li>
          <a href="#">
            <i class="fa-solid fa-briefcase"></i>
            <span>Portfölj</span>
          </a>
        </li>
        <li>
          <a href="#">
            <i class="fa-solid fa-chart-line"></i>
            <span>Aktivitet</span>
          </a>
        </li>
      </ul>
    </nav>
  </aside>

  <!-- Huvudinnehåll -->
  <main class="main-content">
    <?php if ($isLoggedIn): ?>
      <!-- Första rutan: Välkomstmeddelande -->
      <div class="content-box">
        <p><?php echo htmlspecialchars($welcomeMessage); ?></p>
      </div>

      <!-- Diagram -->
      <div class="content-box" style="margin-top:1rem;">
        <h2>Riskutveckling</h2>
        <canvas id="riskChart" width="400" height="200"></canvas>
      </div>
    <?php else: ?>
      <!-- Om man inte är inloggad -->
      <div class="content-box">
        <h2>Välkommen till G-Invest</h2>
        <p>Logga in för att se dina värden och diagram!</p>
      </div>
    <?php endif; ?>

    <!-- Felmeddelande från login -->
    <?php if (!empty($loginError)): ?>
      <div style="color: #f66;">
        <?php echo htmlspecialchars($loginError); ?>
      </div>
    <?php endif; ?>
  </main>

  <!-- Modal för inloggning, enbart om man EJ är inloggad -->
  <?php if (!$isLoggedIn): ?>
    <div class="modal-overlay" id="loginModal">
      <div class="modal-content">
        <h2>Logga in</h2>
        <form method="POST" action="login.php">
          <label for="loginEmail">E-post</label>
          <input type="email" id="loginEmail" name="loginEmail" required>

          <label for="loginPassword">Lösenord</label>
          <input type="password" id="loginPassword" name="loginPassword" required>

          <!-- Remember Me -->
          <div>
            <input type="checkbox" id="rememberMe" name="rememberMe" value="1">
            <label for="rememberMe">Kom ihåg mig</label>
          </div>
          <button type="submit">Logga in</button>
        </form>
        <p>Har du inget konto?
          <a href="user_creation.php" class="create-account-link">Skapa användare</a>
        </p>
        <div class="close-button-wrapper">
          <button class="close-modal" id="closeModalBtn">Stäng</button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- JS för hamburgarmeny och inloggningsmodal -->
  <script>
    const userIcon = document.getElementById('userIcon');
    const userMenu = document.getElementById('userMenu');
    userIcon.addEventListener('click', () => {
      userMenu.classList.toggle('show');
    });

    <?php if (!$isLoggedIn): ?>
      // Visa inloggningsmodal
      const loginBtn   = document.getElementById('loginBtn');
      const loginModal = document.getElementById('loginModal');
      const closeModalBtn = document.getElementById('closeModalBtn');

      loginBtn.addEventListener('click', () => {
        loginModal.classList.add('active');
        userMenu.classList.remove('show');
      });

      closeModalBtn.addEventListener('click', () => {
        loginModal.classList.remove('active');
      });

      loginModal.addEventListener('click', (e) => {
        if(e.target === loginModal) {
          loginModal.classList.remove('active');
        }
      });
    <?php else: ?>
      // Gör "Logga ut" menyposten klickbar
      const logoutBtn = document.getElementById('logoutBtn');
      logoutBtn.addEventListener('click', () => {
        // Skicka användaren till ?logout=1
        window.location.href = "index.php?logout=1";
      });
    <?php endif; ?>
  </script>

  <!-- Om inloggad -> skapa Chart.js-linjer -->
  <?php if ($isLoggedIn): ?>
  <script>
    const dateLabels = <?php echo $dateLabelsJson; ?>;  // t.ex. ["Start","2025-02-25","2025-03-02",...]
    const totalsData = <?php echo $totalsJson; ?>;      // [10000, 5000, 7500, ...]

    if (dateLabels.length > 0) {
      const ctx = document.getElementById('riskChart').getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: dateLabels,
          datasets: [{
            label: 'Totalt värde',
            data: totalsData,
            borderColor: 'rgba(75, 192, 192, 1)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            fill: true,
            tension: 0.2
          }]
        },
        options: {
          responsive: true,
          scales: {
            x: {
              title: {
                display: true,
                text: 'Datum'
              }
            },
            y: {
              title: {
                display: true,
                text: 'Totalt värde'
              }
            }
          }
        }
      });
    }
  </script>
  <?php endif; ?>
</body>
</html>
