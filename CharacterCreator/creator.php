<?php

declare(strict_types=1);
session_start();

$root = dirname(__DIR__); // -> DAGGERHEARTTOOL
require_once $root . '/Database/Database.php';

function getCurrentUserId(): ?int
{
  if (isset($_SESSION['userID']) && is_numeric($_SESSION['userID'])) return (int)$_SESSION['userID'];
  if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
  if (isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
  if (isset($_SESSION['user']['userID']) && is_numeric($_SESSION['user']['userID'])) return (int)$_SESSION['user']['userID'];
  return null;
}
function h(?string $s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$currentUserId = getCurrentUserId();
if ($currentUserId === null) {
  header('Location: /Login/login.php');
  exit;
}

$dbFile = $root . '/Database/Daggerheart.db';
header('Content-Type: text/html; charset=utf-8');

try {
  $db = Database::getInstance($dbFile);

  $heritages   = $db->fetchAll("SELECT heritageID, name FROM heritage ORDER BY name");
  $classes     = $db->fetchAll("SELECT classID, name FROM class ORDER BY name");
  $communities = $db->fetchAll("SELECT communityID, name FROM community ORDER BY name");
} catch (Throwable $e) {
  http_response_code(500);
  echo '<p style="color:red;">DB Error: ' . h($e->getMessage()) . '</p>';
  exit;
}

if ($action === 'getDomainsForClass') {
    try {
        $classID = (int)($_GET['classID'] ?? 0);
        if ($classID <= 0) json_out(['ok' => true, 'domains' => []]);

        $rows = $db->fetchAll(
            "SELECT domainID
             FROM domain_class
             WHERE classID = :cid
             ORDER BY domainID",
            [':cid' => $classID]
        );

        $domains = array_values(array_map(fn($r) => (int)$r['domainID'], $rows));
        json_out(['ok' => true, 'domains' => $domains]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'saveDomainCards') {
    try {
        if (empty($_SESSION['creator']['characterID'])) {
            throw new RuntimeException("No characterID in session. Fill Basics first.");
        }
        $characterID = (int)$_SESSION['creator']['characterID'];

        $own = $db->fetch(
            "SELECT characterID FROM character WHERE characterID = :cid AND userID = :uid",
            [':cid' => $characterID, ':uid' => $currentUserId]
        );
        if (!$own) throw new RuntimeException("Character not owned by current user.");

        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '[]', true, 512, JSON_THROW_ON_ERROR);

        $files = $data['files'] ?? [];
        if (!is_array($files)) $files = [];
        $files = array_values(array_unique(array_filter(array_map('strval', $files))));

        if (count($files) > 2) {
            throw new RuntimeException("You can select at most 2 domain cards.");
        }

        // replace-all
        $db->execute("DELETE FROM character_domain_card WHERE characterID = :cid", [':cid' => $characterID]);

        foreach ($files as $base) {
            // security: no paths
            $base = basename($base);

            if (!preg_match('/^(\d+)_([0-9]+)_(.+)\.jpg$/i', $base, $m)) {
                throw new RuntimeException("Invalid domain card filename: $base");
            }

            $domainID   = (int)$m[1];
            $spellLevel = (int)$m[2];
            $name       = (string)$m[3];

            $db->execute(
                "INSERT INTO character_domain_card (characterID, domainID, spellLevel, name)
                 VALUES (:cid, :did, :lvl, :name)",
                [
                    ':cid'  => $characterID,
                    ':did'  => $domainID,
                    ':lvl'  => $spellLevel,
                    ':name' => $name,
                ]
            );
        }

        json_out(['ok' => true, 'count' => count($files)]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}


?>
<!doctype html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Character Creation</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

  <!-- Global + Page CSS -->
  <link rel="stylesheet" href="/Global/styles.css" />
  <link rel="stylesheet" href="/CharacterCreator/assets/creator.css" />
</head>

<body>
  <?php
  $navPath = $root . '/Global/nav.html';
  if (is_file($navPath)) include $navPath;
  ?>

  <header class="py-4">
    <div class="container">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <a class="btn btn-outline-light" href="/dashboard.php">
          <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
        </a>

        <div class="d-flex gap-2">
          <button class="btn btn-outline-light" id="btnToggleTheme" type="button" title="Toggle theme">
            <i class="bi bi-sun"></i>
          </button>

          <!-- FINAL SAVE BUTTON -->
          <button class="btn btn-primary" id="btnSaveAll" type="button">
            <i class="bi bi-save2 me-2"></i>Save Character
          </button>
        </div>
      </div>
    </div>
  </header>

  <main class="container pb-5">
    <section class="cc-glass cc-panel">
      <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
          <div class="text-uppercase cc-muted" style="letter-spacing:.14em;font-size:.78rem;">Character Creation</div>
          <h1 class="mb-1" style="letter-spacing:-0.03em;line-height:1.05;">Step-by-step Wizard</h1>
          <div class="cc-muted">Fill everything. Save at the end.</div>
        </div>

        <div class="cc-progress-pill" style="min-width: min(520px, 100%);">
          <div class="d-flex align-items-center gap-2">
            <span class="cc-pill"><i class="bi bi-person-badge"></i><span id="liveName">Unnamed</span></span>
            <span class="cc-pill"><i class="bi bi-check2-circle"></i><span id="saveState">Not saved</span></span>
          </div>
          <div class="cc-bar" aria-label="Progress">
            <div id="progressBar"></div>
          </div>
        </div>
      </div>

      <div class="cc-divider"></div>

      <div class="cc-wizard">
        <aside class="cc-steps" aria-label="Steps">
          <button class="cc-step-btn active" type="button" data-step="1"><span class="cc-step-num">1</span><span>
              <div class="cc-step-title">Basics</div>
              <div class="cc-step-desc">Identity & class</div>
            </span></button>
          <button class="cc-step-btn" type="button" data-step="2"><span class="cc-step-num">2</span><span>
              <div class="cc-step-title">Traits</div>
              <div class="cc-step-desc">(+2,+1,+1,0,0,-1)</div>
            </span></button>
          <button class="cc-step-btn" type="button" data-step="3"><span class="cc-step-num">3</span><span>
              <div class="cc-step-title">Defense</div>
              <div class="cc-step-desc">Armor, thresholds, HP</div>
            </span></button>
          <button class="cc-step-btn" type="button" data-step="4"><span class="cc-step-num">4</span><span>
              <div class="cc-step-title">Experience</div>
              <div class="cc-step-desc">List</div>
            </span></button>
          <button class="cc-step-btn" type="button" data-step="5"><span class="cc-step-num">5</span><span>
              <div class="cc-step-title">Gear</div>
              <div class="cc-step-desc">Weapons + Inventory</div>
            </span></button>
          <button class="cc-step-btn" type="button" data-step="6"><span class="cc-step-num">6</span><span>
              <div class="cc-step-title">Cards</div>
              <div class="cc-step-desc">Pick Domain cards.</div>
            </span></button>
          <button class="cc-step-btn" type="button" data-step="7"><span class="cc-step-num">7</span><span>
              <div class="cc-step-title">Review</div>
              <div class="cc-step-desc">Validate and finalize.</div>
            </span></button>
        </aside>

        <section class="cc-sheet" aria-label="Step content">
          <?php include __DIR__ . '/partials/step1_basics.php'; ?>
          <?php include __DIR__ . '/partials/step2_traits.php'; ?>
          <?php include __DIR__ . '/partials/step3_defense.php'; ?>
          <?php include __DIR__ . '/partials/step4_experience.php'; ?>
          <?php include __DIR__ . '/partials/step5_gear.php'; ?>
          <?php include __DIR__ . '/partials/step6_cards.php'; ?>
          <?php include __DIR__ . '/partials/step7_review.php'; ?>

          <div class="cc-controls">
            <button class="btn btn-outline-light" type="button" id="btnPrev">
              <i class="bi bi-arrow-left me-2"></i>Previous
            </button>

            <button class="btn btn-outline-light" type="button" id="btnNext">
              Next<i class="bi bi-arrow-right ms-2"></i>
            </button>
          </div>
        </section>
      </div>
    </section>
  </main>

  <?php
  $footerPath = $root . '/Global/footer.html';
  if (is_file($footerPath)) include $footerPath;
  ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>

  <script>
    // server data for dropdowns
    window.CC_BOOT = {
      heritages: <?php echo json_encode($heritages, JSON_UNESCAPED_UNICODE); ?>,
      classes: <?php echo json_encode($classes, JSON_UNESCAPED_UNICODE); ?>,
      communities: <?php echo json_encode($communities, JSON_UNESCAPED_UNICODE); ?>
    };
  </script>
  <script src="/CharacterCreator/assets/creator.js"></script>
</body>

</html>