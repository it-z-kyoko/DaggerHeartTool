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
.character-frame {
    height: 650px;
    width: 100%;
    border: none;
    overflow: auto;
}

.accordion-button {
    font-weight: 600;
}
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

                    <iframe
                        id="frame<?= $ch['characterID'] ?>"
                        class="character-frame"
                        src="../CharacterSheet/charactersheet.php?characterID=<?= (int)$ch['characterID'] ?>">
                    </iframe>

                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* -------- Live Refresh --------
   Alle 5 Sekunden werden geöffnete Frames neu geladen
---------------------------------- */

setInterval(() => {

    document.querySelectorAll('.accordion-collapse.show iframe')
        .forEach(frame => {
            frame.contentWindow.location.reload();
        });

}, 5000);
</script>

</body>
</html>