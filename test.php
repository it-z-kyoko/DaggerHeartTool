<?php
require_once __DIR__ . '/Database/Database.php';

$dbFile = __DIR__ . '/Database/Daggerheart.db';
header('Content-Type: text/html; charset=utf-8');

try {
    $db = Database::getInstance($dbFile);

    $classes = $db->fetchAll(
        "SELECT classID, name FROM class ORDER BY name"
    );
} catch (Throwable $e) {
    echo '<p style="color:red;">Fehler: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
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
  <form method="post">
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

  <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['classID'])): ?>
    <p>Ausgewählte classID: <?php echo htmlspecialchars($_POST['classID'], ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
</body>
</html>
