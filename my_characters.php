<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/Database/Database.php';

if (!isset($_SESSION['userID'])) {
    header('Location: login.php');
    exit;
}

$dbFile = __DIR__ . '/Database/Daggerheart.db';
$db = Database::getInstance($dbFile);

$userID = (int)$_SESSION['userID'];

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
LEFT JOIN "class"     cl  ON cl.classID     = c.classID
LEFT JOIN "subclass"  sc  ON sc.subclassID  = c.subclassID
LEFT JOIN "heritage"  h   ON h.heritageID   = c.heritageID
LEFT JOIN "community" com ON com.communityID = c.communityID
LEFT JOIN "campaign"  cam ON cam.campaignID = c.campaignID

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
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Characters</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    background: radial-gradient(circle at top, #1e1e2f, #0f0f18);
    color: #fff;
}
.glass-card {
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.12);
    backdrop-filter: blur(12px);
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,.35);
    transition: .2s ease;
}
.glass-card:hover {
    transform: translateY(-4px);
}
.pill {
    display:inline-flex;
    align-items:center;
    gap:.4rem;
    padding:.3rem .6rem;
    border-radius:999px;
    background: rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.15);
    font-size:.85rem;
}
hr { border-color: rgba(255,255,255,.1); }
</style>
</head>
<?php include __DIR__ . "/Global/nav.html"; ?>

<body>
<div class="container py-5">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people"></i> My Characters</h2>
    <a href="/CharacterCreator/creator.php" class="btn btn-outline-light">
        <i class="bi bi-plus-circle"></i> Create Character
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (empty($characters)): ?>
    <div class="glass-card p-4 text-center">
        <h5>No characters found</h5>
        <p>Create your first hero.</p>
    </div>
<?php else: ?>

<div class="row g-4">
<?php foreach ($characters as $c): ?>
<div class="col-md-4">

<div class="glass-card p-4 h-100 d-flex flex-column justify-content-between">

<div>
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h4><?= htmlspecialchars($c['name'] ?: 'Unnamed') ?></h4>
            <div style="opacity:.7"><?= htmlspecialchars($c['pronouns'] ?: '') ?></div>
        </div>
        <span class="pill">
            <i class="bi bi-stars"></i> Lv <?= (int)$c['level'] ?>
        </span>
    </div>

    <hr>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="pill"><i class="bi bi-shield"></i> Armor <?= (int)$c['armor'] ?></span>
        <span class="pill"><i class="bi bi-lightning"></i> Evasion <?= (int)$c['evasion'] ?></span>
        <?php if ($c['spellcast_trait']): ?>
            <span class="pill"><i class="bi bi-magic"></i> <?= htmlspecialchars($c['spellcast_trait']) ?></span>
        <?php endif; ?>
    </div>

    <div style="opacity:.85; font-size:.95rem">
        <div><strong>Class:</strong> <?= htmlspecialchars($c['class_name'] ?? '—') ?></div>
        <div><strong>Subclass:</strong> <?= htmlspecialchars($c['subclass_name'] ?? '—') ?></div>
        <div><strong>Heritage:</strong> <?= htmlspecialchars($c['heritage_name'] ?? '—') ?></div>
        <div><strong>Community:</strong> <?= htmlspecialchars($c['community_name'] ?? '—') ?></div>
        <div><strong>Campaign:</strong> <?= htmlspecialchars($c['campaign_name'] ?? '—') ?></div>
    </div>
</div>

<div class="d-flex justify-content-between mt-4">
    <a class="btn btn-sm btn-outline-light"
       href="character_view.php?id=<?= (int)$c['characterID'] ?>">
        <i class="bi bi-eye"></i>
    </a>

    <a class="btn btn-sm btn-outline-warning"
       href="creator.php?id=<?= (int)$c['characterID'] ?>">
        <i class="bi bi-pencil"></i>
    </a>

    <a class="btn btn-sm btn-outline-danger"
       href="delete_character.php?id=<?= (int)$c['characterID'] ?>"
       onclick="return confirm('Delete this character?');">
        <i class="bi bi-trash"></i>
    </a>
</div>

</div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

</div>
</body>
</html>