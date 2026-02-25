<?php
declare(strict_types=1);
session_start();

$root = dirname(__DIR__);
require_once $root . '/Database/Database.php';

if (!isset($_SESSION['userID'])) {
    header('Location: ' . $root . '/Login/login.php');
    exit;
}

$uid = (int)$_SESSION['userID'];
$cid = (int)($_GET['campaignID'] ?? 0);

if ($cid <= 0) {
    http_response_code(400);
    exit('campaignID fehlt.');
}

$db = Database::getInstance($root . '/Database/Daggerheart.db');

/* Owner Check */
$camp = $db->fetch(
    "SELECT campaignID, name, userID
     FROM campaign
     WHERE campaignID = :cid",
    [':cid' => $cid]
);

if (!$camp) {
    http_response_code(404);
    exit('Kampagne nicht gefunden.');
}

if ((int)$camp['userID'] !== $uid) {
    http_response_code(403);
    exit('Kein Zugriff.');
}

/* Charaktere laden */
$characters = $db->fetchAll(
    "SELECT ch.characterID, ch.name
     FROM campaign_character cc
     JOIN character ch ON ch.characterID = cc.characterID
     WHERE cc.campaignID = :cid
     ORDER BY ch.name COLLATE NOCASE",
    [':cid' => $cid]
);
?>
<!doctype html>
<html lang="de" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<title>GM Live View – <?= htmlspecialchars($camp['name']) ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../Global/styles.css" rel="stylesheet">

<style>
.character-frame{
    height:650px;
    width:100%;
    border:none;
    display:block;
}

.accordion-button{ font-weight:600; }
</style>
</head>
<body>

<div class="container py-4">
    <h3 class="mb-4">GM Live View: <?= htmlspecialchars($camp['name']) ?></h3>

    <?php if (!$characters): ?>
        <div class="text-secondary">Keine Charaktere zugeordnet.</div>
    <?php else: ?>

    <div class="accordion" id="gmAccordion">

        <?php foreach ($characters as $i => $ch): ?>
        <div class="accordion-item bg-dark border-secondary mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed bg-dark text-light"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapse<?= $i ?>">
                    <?= htmlspecialchars($ch['name']) ?>
                </button>
            </h2>

            <div id="collapse<?= $i ?>"
                 class="accordion-collapse collapse"
                 data-bs-parent="#gmAccordion">

                <div class="accordion-body p-0">

                    <div class="frame-wrap position-relative"
                         data-character-id="<?= (int)$ch['characterID'] ?>">

                        <iframe
                            class="character-frame frame-a"
                            src="../CharacterSheet/charactersheet.php?characterID=<?= (int)$ch['characterID'] ?>&readonly=1">
                        </iframe>

                        <iframe
                            class="character-frame frame-b d-none"
                            src="about:blank">
                        </iframe>

                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* -------- Live Refresh ohne Flackern + ohne Scroll-Sprung -------- */

function refreshVisibleFrames() {

  document.querySelectorAll('.accordion-collapse.show .frame-wrap')
    .forEach(wrap => {

      const cid = wrap.dataset.characterId;
      const a = wrap.querySelector('.frame-a');
      const b = wrap.querySelector('.frame-b');

      const aVisible = !a.classList.contains('d-none');
      const visible = aVisible ? a : b;
      const hidden  = aVisible ? b : a;

      // Scroll-Position sichern
      let scrollTop = 0;
      try {
          scrollTop = visible.contentWindow.document.documentElement.scrollTop
                   || visible.contentWindow.document.body.scrollTop
                   || 0;
      } catch(e) {}

      const url = `../CharacterSheet/charactersheet.php?characterID=${encodeURIComponent(cid)}&readonly=1&t=${Date.now()}`;

      hidden.onload = () => {

          try {
              hidden.contentWindow.document.documentElement.scrollTop = scrollTop;
              hidden.contentWindow.document.body.scrollTop = scrollTop;
          } catch(e) {}

          visible.classList.add('d-none');
          hidden.classList.remove('d-none');
          hidden.onload = null;
      };

      hidden.src = url;
  });
}

setInterval(() => {
  document.querySelectorAll('.accordion-collapse.show iframe')
    .forEach(frame => {
      try {
        frame.contentWindow.__gmRefresh?.();
      } catch(e) {}
    });
}, 5000);
</script>

</body>
</html>