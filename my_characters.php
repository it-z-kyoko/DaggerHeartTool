<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/Database/Database.php';

if (!isset($_SESSION['userID'])) {
    header('Location: /Login/login.php');
    exit;
}

$dbFile = __DIR__ . '/Database/Daggerheart.db';
$db     = Database::getInstance($dbFile);

$userID = (int)$_SESSION['userID'];

function h(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$sql = '
SELECT
    c.characterID,
    c.name,
    c.pronouns,
    c.Level as level,
    c.evasion,
    c.armor,
    c."spellcast trait" AS spellcast_trait,

    cl.name  AS class_name,
    sc.name  AS subclass_name,
    h.name   AS heritage_name,
    com.name AS community_name,
    cam.name AS campaign_name
FROM "character" c
LEFT JOIN "class"     cl   ON cl.classID      = c.classID
LEFT JOIN "subclass"  sc   ON sc.subclassID   = c.subclassID
LEFT JOIN "heritage"  h    ON h.heritageID    = c.heritageID
LEFT JOIN "community" com  ON com.communityID = c.communityID
LEFT JOIN "campaign"  cam  ON cam.campaignID  = c.campaignID
WHERE c.userID = :uid
ORDER BY c.name COLLATE NOCASE ASC
';

try {
    $characters = $db->fetchAll($sql, [':uid' => $userID]);
    $error = null;
} catch (Throwable $e) {
    $characters = [];
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Characters</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/Global/styles.css">

  <style>
    /* ---- Page background: closer to Campaigns / Dashboard ---- */
    body {
      min-height: 100vh;
      color: rgba(255,255,255,.92);
      background:
        radial-gradient(1100px 520px at 12% 18%, rgba(167,139,250,.18), transparent 62%),
        radial-gradient(1000px 520px at 78% 26%, rgba(34,197,94,.12), transparent 60%),
        radial-gradient(900px 520px at 55% 88%, rgba(59,130,246,.10), transparent 55%),
        linear-gradient(180deg, #0b0b12 0%, #07070c 100%);
    }

    /* ---- Glass block: aligns with your existing glassmorphism ---- */
    .glass {
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.10);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-radius: 18px;
      box-shadow: 0 12px 32px rgba(0,0,0,.35);
    }

    .page-wrap { padding: 3.25rem 0 4rem; }
    .page-head h1 { font-size: 2.0rem; margin: 0; }
    .page-sub { opacity: .65; font-size: .95rem; }

    .pill {
      display:inline-flex;
      align-items:center;
      gap:.45rem;
      padding:.28rem .65rem;
      border-radius:999px;
      background: rgba(255,255,255,.05);
      border:1px solid rgba(255,255,255,.12);
      font-size:.84rem;
      line-height: 1;
      white-space: nowrap;
    }

    .char-card {
      padding: 1.1rem 1.15rem;
      transition: transform .18s ease, border-color .18s ease;
    }
    .char-card:hover {
      transform: translateY(-3px);
      border-color: rgba(255,255,255,.18);
    }

    .char-name { font-size: 1.25rem; font-weight: 650; margin: 0; }
    .muted { opacity: .72; }

    .divider {
      border-top: 1px solid rgba(255,255,255,.10);
      margin: .9rem 0;
    }

    .kv { font-size: .95rem; }
    .kv strong { opacity: .92; }
    .kv span { opacity: .85; }

    .btn-icon {
      width: 38px;
      height: 34px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding: 0;
      border-radius: 10px;
    }

    /* keep your icon-button colors consistent with rest of site */
    .btn-outline-warning { border-color: rgba(245,158,11,.55); }
    .btn-outline-danger  { border-color: rgba(239,68,68,.55); }
    .btn-outline-light   { border-color: rgba(255,255,255,.22); }

    /* Optional: make action buttons feel "glass" */
    .btn-glass {
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.14);
      backdrop-filter: blur(10px);
    }
    .btn-glass:hover { background: rgba(255,255,255,.09); }
  </style>
</head>

<body>
<?php
  $nav = __DIR__ . '/Global/nav.html';
  if (file_exists($nav)) include $nav;
?>

<main class="page-wrap">
  <div class="container-xxl">

    <div class="glass p-4 p-md-4 mb-4">
      <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 page-head">
        <div>
          <h1 class="d-flex align-items-center gap-2">
            <i class="bi bi-people"></i>
            <span>My Characters</span>
          </h1>
          <div class="page-sub">View, edit, or delete your heroes.</div>
        </div>

        <div class="d-flex gap-2">
          <a href="/Dashboard/dashboard.php" class="btn btn-outline-light btn-glass">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
          </a>
          <a href="/CharacterCreator/creator.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Create Character
          </a>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger mt-3 mb-0"><?= h($error) ?></div>
      <?php endif; ?>
    </div>

    <?php if (empty($characters)): ?>
      <div class="glass p-4 text-center">
        <h5 class="mb-1">No characters found</h5>
        <div class="muted">Create your first hero to get started.</div>
      </div>
    <?php else: ?>

      <div class="row g-4">
        <?php foreach ($characters as $c): ?>
          <?php
            $cid   = (int)($c['characterID'] ?? 0);
            $name  = (string)($c['name'] ?? '');
            $pro   = (string)($c['pronouns'] ?? '');
            $lvl   = (int)($c['level'] ?? 1);
            $arm   = (int)($c['armor'] ?? 0);
            $eva   = (int)($c['evasion'] ?? 0);
            $spell = (string)($c['spellcast_trait'] ?? '');
          ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="glass char-card h-100 d-flex flex-column justify-content-between">

              <div>
                <div class="d-flex align-items-start justify-content-between gap-3">
                  <div class="flex-grow-1">
                    <p class="char-name"><?= h($name !== '' ? $name : 'Unnamed') ?></p>
                    <?php if ($pro !== ''): ?>
                      <div class="muted"><?= h($pro) ?></div>
                    <?php endif; ?>
                  </div>

                  <span class="pill">
                    <i class="bi bi-stars"></i>
                    Lv <?= $lvl ?>
                  </span>
                </div>

                <div class="divider"></div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                  <span class="pill"><i class="bi bi-shield"></i> Armor <?= $arm ?></span>
                  <span class="pill"><i class="bi bi-lightning"></i> Evasion <?= $eva ?></span>
                  <?php if ($spell !== ''): ?>
                    <span class="pill"><i class="bi bi-magic"></i> <?= h($spell) ?></span>
                  <?php endif; ?>
                </div>

                <div class="kv">
                  <div><strong>Class:</strong> <span><?= h($c['class_name'] ?? '—') ?></span></div>
                  <div><strong>Subclass:</strong> <span><?= h($c['subclass_name'] ?? '—') ?></span></div>
                  <div><strong>Heritage:</strong> <span><?= h($c['heritage_name'] ?? '—') ?></span></div>
                  <div><strong>Community:</strong> <span><?= h($c['community_name'] ?? '—') ?></span></div>
                  <div><strong>Campaign:</strong> <span><?= h($c['campaign_name'] ?? '—') ?></span></div>
                </div>
              </div>

              <div class="d-flex justify-content-between mt-4 pt-2">
                <a class="btn btn-sm btn-outline-light btn-icon"
                   href="/character_view.php?id=<?= $cid ?>"
                   title="View">
                  <i class="bi bi-eye"></i>
                </a>

                <a class="btn btn-sm btn-outline-warning btn-icon"
                   href="/CharacterCreator/creator.php?id=<?= $cid ?>"
                   title="Edit">
                  <i class="bi bi-pencil"></i>
                </a>

                <a class="btn btn-sm btn-outline-danger btn-icon"
                   href="/delete_character.php?id=<?= $cid ?>"
                   onclick="return confirm('Delete this character?');"
                   title="Delete">
                  <i class="bi bi-trash"></i>
                </a>
              </div>

            </div>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

    <?php
      // Optional footer include, if you have it like the Campaigns page.
      $footer = __DIR__ . '/Global/footer.html';
      if (file_exists($footer)) {
        echo '<div class="mt-5">';
        include $footer;
        echo '</div>';
      }
    ?>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>