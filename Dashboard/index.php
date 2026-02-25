<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/partials/auth.php';
require_once __DIR__ . '/partials/db.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$pageTitle = 'Dashboard';
$userId = (int)$_SESSION['auth']['userID'];

// DEV: Fehler sichtbar machen (für PROD später entfernen)
ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
  // Charaktere: character.name + class/subclass + Level (deine Spalte heißt Level)
  $characters = $db->fetchAll(
    "SELECT
  c.characterID,
  c.name AS characterName,
  COALESCE(cl.name, '') AS className,
  COALESCE(sc.name, '') AS subclassName,
  COALESCE(c.Level, 1) AS level
FROM character c
LEFT JOIN class cl ON cl.classID = c.classID
LEFT JOIN subclass sc ON sc.subclassID = c.subclassID
WHERE c.userID = :uid
ORDER BY c.characterID DESC
LIMIT 6",
    [':uid' => $userId]
  );

  // Kampagnen: campaign.name
  $campaigns = $db->fetchAll(
    "SELECT
  cp.campaignID,
  cp.name AS campaignName
FROM campaign cp
WHERE cp.userID = :uid
ORDER BY cp.campaignID DESC
LIMIT 6",
    [':uid' => $userId]
  );

} catch (Throwable $e) {
  // Wenn’s knallt, siehst du den echten Fehler statt nur 500
  http_response_code(500);
  echo "<pre style='color:#ffb4b4;background:#200;padding:12px;border-radius:8px;'>";
  echo "Dashboard Error:\n" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
  echo "\n</pre>";
  exit;
}

require_once __DIR__ . '/partials/header.php';
?>

<header class="py-5">
  <div class="container">
    <div class="glass panel">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <div class="text-uppercase muted" style="letter-spacing:.14em;font-size:.78rem;">Dashboard</div>
          <h1 class="mb-1" style="letter-spacing:-0.03em;line-height:1.05;">
            Willkommen zurück, <?= htmlspecialchars($_SESSION['auth']['username'], ENT_QUOTES, 'UTF-8') ?>
          </h1>
          <div class="muted">Verwalte Charaktere und Kampagnen an einem Ort.</div>
        </div>
      </div>
    </div>
  </div>
</header>

<main class="container pb-5">
  <div class="row g-3 align-items-stretch">

    <!-- Deine Charaktere -->
    <div class="col-12 col-lg-6">
      <section class="glass panel feature h-100">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <h3 class="panel-title mb-0">Deine Charaktere</h3>
            <div class="panel-subtitle mb-0">Schnellzugriff auf deine zuletzt genutzten Bögen.</div>
          </div>
          <span class="icon-pill" aria-hidden="true"><i class="bi bi-person-badge"></i></span>
        </div>

        <div class="d-grid gap-2">
          <?php if (empty($characters)): ?>
            <div class="muted">Noch keine Charaktere vorhanden.</div>
          <?php else: ?>
            <?php foreach ($characters as $ch):
              $charId = (int)$ch['characterID'];
              $name   = (string)$ch['characterName'];
              $class  = trim((string)$ch['className']);
              $sub    = trim((string)$ch['subclassName']);
              $lvl    = (int)$ch['level'];

              $parts = [];
              if ($class !== '') $parts[] = $class;
              if ($sub !== '')   $parts[] = $sub;
              $parts[] = "Level $lvl";
              $meta = implode(' • ', $parts);
            ?>
              <a class="char-card" href="/Character/view.php?id=<?= $charId ?>">
                <img class="char-avatar" src="img/char-placeholder.jpg" alt="Charakterbild" />
                <div class="flex-grow-1">
                  <p class="char-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
                  <p class="char-meta"><?= htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <i class="bi bi-chevron-right muted"></i>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <hr class="my-4" style="border-top:1px solid rgba(255,255,255,.10);" />
        <div class="d-flex gap-2">
          <a class="btn btn-brand flex-grow-1" href="/Character/create.php">
            <i class="bi bi-person-plus me-2"></i>Charakter erstellen
          </a>
          <a class="btn btn-outline-light" href="/Character/list.php">
            <i class="bi bi-collection me-2"></i>Alle
          </a>
        </div>
      </section>
    </div>

    <!-- Deine Kampagnen -->
    <div class="col-12 col-lg-6">
      <section class="glass panel feature h-100">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <h3 class="panel-title mb-0">Deine Kampagnen</h3>
            <div class="panel-subtitle mb-0">Erstellen, verwalten und Sessions tracken.</div>
          </div>
          <span class="icon-pill" aria-hidden="true"><i class="bi bi-journal-bookmark"></i></span>
        </div>

        <div class="d-grid gap-2">
          <?php if (empty($campaigns)): ?>
            <div class="muted">Noch keine Kampagnen vorhanden.</div>
          <?php else: ?>
            <?php foreach ($campaigns as $cp):
              $campaignId = (int)$cp['campaignID'];
              $name = (string)$cp['campaignName'];
            ?>
              <a class="char-card" href="/Campaign/view.php?id=<?= $campaignId ?>">
                <img class="char-avatar" src="img/char-placeholder.jpg" alt="Kampagnenbild" />
                <div class="flex-grow-1">
                  <p class="char-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <i class="bi bi-chevron-right muted"></i>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <hr class="my-4" style="border-top:1px solid rgba(255,255,255,.10);" />
        <div class="d-flex gap-2">
          <a class="btn btn-brand flex-grow-1" href="/Campaign/create.php">
            <i class="bi bi-plus-lg me-2"></i>Kampagne erstellen
          </a>
          <a class="btn btn-outline-light" href="/Campaign/list.php">
            <i class="bi bi-collection me-2"></i>Alle
          </a>
        </div>
      </section>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>