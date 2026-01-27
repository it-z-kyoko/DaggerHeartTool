<?php
// test.php
// Verbindung zu einer SQLite-Datei in /Database und Laden der Tabelle "class" in ein Dropdown.

// Pfad zur SQLite-Datei anpassen falls nötig
$dbFile = __DIR__ . '/Database/Daggerheart.db';

header('Content-Type: text/html; charset=utf-8');

if (!file_exists($dbFile)) {
    echo '<p style="color:red;">Datenbankdatei nicht gefunden: ' . htmlspecialchars($dbFile) . '</p>';
    exit;
}

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query('SELECT classID, name FROM class ORDER BY name');
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo '<p style="color:red;">Fehler bei der Datenbankverbindung: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Klassen auswählen</title>
</head>
<body>
  <form method="post" action="">
    <label for="classSelect">Klasse wählen:</label>
    <select id="classSelect" name="classID" required>
      <option value="">Bitte wählen...</option>
      <?php foreach ($classes as $c): ?>
        <option value="<?php echo htmlspecialchars($c['classID'], ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Auswählen</button>
  </form>

  <?php
  // Beispiel: ausgewählte Klasse verarbeiten
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['classID'])) {
      $selected = $_POST['classID'];
      echo '<p>Ausgewählte classID: ' . htmlspecialchars($selected) . '</p>';
  }
  ?>
</body>
</html>