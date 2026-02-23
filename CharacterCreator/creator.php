<?php
/**
 * CharacterCreator/creator.php
 *
 * Implemented:
 * - NO local Draft Save / NO localStorage
 * - HP is readonly and derived from class.starting_hit_point
 * - Hope is hidden (numeric), Hope Feature shown from feature where feature.classID = selected ClassID AND hope > 0
 *
 * Saving:
 * - Basics -> character (unchanged)
 * - Traits + HP -> character_stats (strength, agility, finesse, instinct, presence, knowledge, HP)
 * - Defense -> character.evasion, character.armor
 * - Active Armor -> character_armor (characterID -> armorID)
 * - Experience list -> character_experience (characterID, experience text, mod=2), one row per entry
 *
 * Assumed schema (adjust if your names differ):
 *  character(characterID, userID, name, pronouns, level, heritageID, classID, subclassID, communityID, evasion, armor)
 *  character_stats(characterID UNIQUE, strength, agility, finesse, instinct, presence, knowledge, HP)
 *  character_armor(characterID UNIQUE, armorID)
 *  character_experience(characterID, experience, mod)
 *  class(classID, name, starting_evasion_score, starting_hit_point)
 *  armor(armorID, name, base_score, major_threshold, severe_threshold, feature, min_level)
 *  feature(classID, hope, description)
 */

declare(strict_types=1);
session_start();

$root = dirname(__DIR__);
require_once $root . '/Database/Database.php';

// -------------------------
// Auth: require session
// -------------------------
function getCurrentUserId(): ?int {
    if (isset($_SESSION['userID']) && is_numeric($_SESSION['userID'])) return (int)$_SESSION['userID'];
    if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    if (isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    if (isset($_SESSION['user']['userID']) && is_numeric($_SESSION['user']['userID'])) return (int)$_SESSION['user']['userID'];
    return null;
}

$currentUserId = getCurrentUserId();
if ($currentUserId === null) {
    header('Location: ' . '/Login/login.php');
    exit;
}

// -------------------------
// DB init
// -------------------------
$dbFile = $root . '/Database/Daggerheart.db';
header('Content-Type: text/html; charset=utf-8');

try {
    $db = Database::getInstance($dbFile);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<p style="color:red;">DB Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

// -------------------------
// Helpers
// -------------------------
function json_out(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Ensure creator session container exists
if (!isset($_SESSION['creator']) || !is_array($_SESSION['creator'])) {
    $_SESSION['creator'] = [];
}

// =========================================================
// API ROUTES (AJAX)
// =========================================================
$action = $_GET['action'] ?? null;

if ($action === 'getSubclasses') {
    try {
        $classID = (int)($_GET['classID'] ?? 0);
        if ($classID <= 0) json_out(['ok' => true, 'rows' => []]);

        $rows = $db->fetchAll(
            "SELECT subclassID, name
             FROM subclass
             WHERE classID = :cid
             ORDER BY name",
            [':cid' => $classID]
        );

        json_out(['ok' => true, 'rows' => $rows]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'getClassInfo') {
    try {
        $classID = (int)($_GET['classID'] ?? 0);
        if ($classID <= 0) json_out(['ok' => true, 'row' => null]);

        $row = $db->fetch(
            "SELECT
                c.classID,
                c.starting_evasion_score,
                c.starting_hit_point,
                (
                  SELECT f.description
                  FROM feature f
                  WHERE f.classID = c.classID
                    AND COALESCE(f.hope, 0) > 0
                  ORDER BY COALESCE(f.hope, 0) DESC, rowid ASC
                  LIMIT 1
                ) AS hope_feature
             FROM class c
             WHERE c.classID = :cid",
            [':cid' => $classID]
        );

        json_out(['ok' => true, 'row' => $row]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'getArmors') {
    try {
        $level = (int)($_GET['level'] ?? 1);
        if ($level < 1) $level = 1;

        $rows = $db->fetchAll(
            "SELECT armorID, name
             FROM armor
             WHERE COALESCE(min_level, 1) <= :lvl
             ORDER BY COALESCE(min_level, 1), name",
            [':lvl' => $level]
        );

        json_out(['ok' => true, 'rows' => $rows]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'getArmorInfo') {
    try {
        $armorID = (int)($_GET['armorID'] ?? 0);
        if ($armorID <= 0) json_out(['ok' => true, 'row' => null]);

        $row = $db->fetch(
            "SELECT armorID, name, base_score, major_threshold, severe_threshold, feature, min_level
             FROM armor
             WHERE armorID = :aid",
            [':aid' => $armorID]
        );

        json_out(['ok' => true, 'row' => $row]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($action === 'saveBasics') {
    try {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '[]', true, 512, JSON_THROW_ON_ERROR);

        $name        = trim((string)($data['name'] ?? ''));
        $pronouns    = trim((string)($data['pronouns'] ?? ''));
        $level       = (int)($data['level'] ?? 1);
        $heritageID  = (int)($data['heritageID'] ?? 0);
        $classID     = (int)($data['classID'] ?? 0);
        $subclassID  = (int)($data['subclassID'] ?? 0);
        $communityID = (int)($data['communityID'] ?? 0);

        if ($level < 1) $level = 1;

        $characterID = isset($_SESSION['creator']['characterID']) ? (int)$_SESSION['creator']['characterID'] : 0;

        if ($characterID > 0) {
            $own = $db->fetch(
                "SELECT characterID FROM character WHERE characterID = :cid AND userID = :uid",
                [':cid' => $characterID, ':uid' => $currentUserId]
            );
            if (!$own) throw new RuntimeException("Character not owned by current user.");

            $db->execute(
                "UPDATE character
                 SET name = :name,
                     pronouns = :pro,
                     level = :lvl,
                     heritageID = :hid,
                     classID = :clid,
                     subclassID = :scid,
                     communityID = :comid
                 WHERE characterID = :cid",
                [
                    ':name'  => $name,
                    ':pro'   => $pronouns,
                    ':lvl'   => $level,
                    ':hid'   => $heritageID ?: null,
                    ':clid'  => $classID ?: null,
                    ':scid'  => $subclassID ?: null,
                    ':comid' => $communityID ?: null,
                    ':cid'   => $characterID,
                ]
            );
        } else {
            $db->execute(
                "INSERT INTO character (userID, name, pronouns, level, heritageID, classID, subclassID, communityID)
                 VALUES (:uid, :name, :pro, :lvl, :hid, :clid, :scid, :comid)",
                [
                    ':uid'   => $currentUserId,
                    ':name'  => $name,
                    ':pro'   => $pronouns,
                    ':lvl'   => $level,
                    ':hid'   => $heritageID ?: null,
                    ':clid'  => $classID ?: null,
                    ':scid'  => $subclassID ?: null,
                    ':comid' => $communityID ?: null,
                ]
            );
            $characterID = (int)$db->lastInsertId();
            $_SESSION['creator']['characterID'] = $characterID;
        }

        json_out(['ok' => true, 'characterID' => $characterID]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Save Defense:
 * - character.evasion, character.armor
 * - character_armor.armorID
 */
if ($action === 'saveDefense') {
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

        $evasion = array_key_exists('evasion', $data) ? (int)$data['evasion'] : null;
        $armor   = array_key_exists('armor',   $data) ? (int)$data['armor']   : null;
        $armorID = isset($data['armorID']) ? (int)$data['armorID'] : 0;

        $db->execute(
            "UPDATE character
             SET evasion = :eva,
                 armor   = :arm
             WHERE characterID = :cid AND userID = :uid",
            [
                ':eva' => $evasion,
                ':arm' => $armor,
                ':cid' => $characterID,
                ':uid' => $currentUserId,
            ]
        );

        if ($armorID > 0) {
            $db->execute(
                "INSERT INTO character_armor (characterID, armorID)
                 VALUES (:cid, :aid)
                 ON CONFLICT(characterID) DO UPDATE SET
                   armorID = excluded.armorID",
                [':cid' => $characterID, ':aid' => $armorID]
            );
        } else {
            $db->execute(
                "DELETE FROM character_armor WHERE characterID = :cid",
                [':cid' => $characterID]
            );
        }

        json_out(['ok' => true]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Save Traits + HP together in character_stats
 */
if ($action === 'saveTraits') {
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

        $strength  = (int)($data['strength']  ?? 0);
        $agility   = (int)($data['agility']   ?? 0);
        $finesse   = (int)($data['finesse']   ?? 0);
        $instinct  = (int)($data['instinct']  ?? 0);
        $presence  = (int)($data['presence']  ?? 0);
        $knowledge = (int)($data['knowledge'] ?? 0);

        $hp = isset($data['hp']) ? (int)$data['hp'] : 0;

        $vals = [$strength, $agility, $finesse, $instinct, $presence, $knowledge];
        sort($vals);
        $pool = [-1, 0, 0, 1, 1, 2];
        if ($vals !== $pool) {
            throw new RuntimeException("Invalid trait distribution. Must be (+2,+1,+1,0,0,-1).");
        }

        $db->execute(
            "INSERT INTO character_stats (characterID, strength, agility, finesse, instinct, presence, knowledge, HP)
             VALUES (:cid, :str, :agi, :fin, :ins, :pre, :kno, :hp)
             ON CONFLICT(characterID) DO UPDATE SET
               strength  = excluded.strength,
               agility   = excluded.agility,
               finesse   = excluded.finesse,
               instinct  = excluded.instinct,
               presence  = excluded.presence,
               knowledge = excluded.knowledge,
               HP        = excluded.HP",
            [
                ':cid' => $characterID,
                ':str' => $strength,
                ':agi' => $agility,
                ':fin' => $finesse,
                ':ins' => $instinct,
                ':pre' => $presence,
                ':kno' => $knowledge,
                ':hp'  => $hp,
            ]
        );

        json_out(['ok' => true]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Save Experiences list:
 * - character_experience rows (characterID, experience, mod=2)
 * - Replace-all strategy for current character
 */
if ($action === 'saveExperiences') {
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

        $items = $data['items'] ?? [];
        if (!is_array($items)) $items = [];

        $clean = [];
        foreach ($items as $it) {
            $t = trim((string)$it);
            if ($t === '') continue;
            if (mb_strlen($t) > 200) $t = mb_substr($t, 0, 200);
            $clean[] = $t;
        }
        $clean = array_values(array_unique($clean));

        $db->execute("DELETE FROM character_experience WHERE characterID = :cid", [':cid' => $characterID]);

        foreach ($clean as $txt) {
            $db->execute(
                "INSERT INTO character_experience (characterID, experience, mod)
                 VALUES (:cid, :exp, 2)",
                [':cid' => $characterID, ':exp' => $txt]
            );
        }

        json_out(['ok' => true, 'count' => count($clean)]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// =========================================================
// PAGE LOAD: fetch dropdown data + existing character (if any)
// =========================================================
try {
    $heritages    = $db->fetchAll("SELECT heritageID, name FROM heritage ORDER BY name");
    $classes      = $db->fetchAll("SELECT classID, name FROM class ORDER BY name");
    $communities  = $db->fetchAll("SELECT communityID, name FROM community ORDER BY name");

    $character    = null;
    $stats        = null;
    $experiences  = [];

    $characterID = isset($_SESSION['creator']['characterID']) ? (int)$_SESSION['creator']['characterID'] : 0;
    if ($characterID > 0) {
        $character = $db->fetch(
            "SELECT characterID, userID, name, pronouns, level, heritageID, classID, subclassID, communityID, evasion, armor
             FROM character
             WHERE characterID = :cid AND userID = :uid",
            [':cid' => $characterID, ':uid' => $currentUserId]
        );

        if ($character) {
            $stats = $db->fetch(
                "SELECT characterID, strength, agility, finesse, instinct, presence, knowledge, HP
                 FROM character_stats
                 WHERE characterID = :cid",
                [':cid' => $characterID]
            );

            $expRows = $db->fetchAll(
                "SELECT experience
                 FROM character_experience
                 WHERE characterID = :cid
                 ORDER BY rowid ASC",
                [':cid' => $characterID]
            );
            $experiences = array_values(array_filter(array_map(
                fn($r) => trim((string)($r['experience'] ?? '')),
                $expRows
            ), fn($s) => $s !== ''));
        } else {
            unset($_SESSION['creator']['characterID']);
            $characterID = 0;
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<p style="color:red;">Fehler: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Character Creation – Step by Step</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

  <style>
    :root {
      --brand: #a78bfa;
      --brand2: #22c55e;
      --ink: rgba(255, 255, 255, .90);
      --muted: rgba(255, 255, 255, .70);
      --surface: rgba(255, 255, 255, .06);
      --border: rgba(255, 255, 255, .12);
    }
    body {
      color: var(--ink);
      background:
        radial-gradient(1100px 520px at 18% 10%, rgba(167, 139, 250, .22), transparent 60%),
        radial-gradient(900px 500px at 80% 25%, rgba(34, 197, 94, .12), transparent 60%),
        linear-gradient(0deg, rgba(255, 255, 255, .02), rgba(255, 255, 255, .02));
      min-height: 100vh;
    }
    .muted { color: var(--muted); }
    .glass {
      background: var(--surface);
      border: 1px solid var(--border);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border-radius: 1.25rem;
    }
    .panel { padding: 1.25rem; }
    .btn-brand {
      --bs-btn-bg: var(--brand);
      --bs-btn-border-color: var(--brand);
      --bs-btn-hover-bg: #8b5cf6;
      --bs-btn-hover-border-color: #8b5cf6;
      --bs-btn-focus-shadow-rgb: 167, 139, 250;
      --bs-btn-color: #0b0b0f;
      font-weight: 800;
    }
    .sheet-block {
      border-radius: 1rem;
      border: 1px solid rgba(255, 255, 255, .12);
      background: rgba(255, 255, 255, .04);
      padding: 1rem;
      height: 100%;
      position: relative;
      overflow: hidden;
      isolation: isolate;
      min-width: 0;
    }
    .block-title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      margin-bottom: .85rem;
    }
    .block-title h3 {
      font-size: 1rem;
      margin: 0;
      letter-spacing: .10em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, .88);
    }
    .chip {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .35rem .6rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, .12);
      background: rgba(255, 255, 255, .05);
      color: var(--muted);
      font-size: .85rem;
      white-space: nowrap;
    }
    .field { display: grid; gap: .35rem; margin-bottom: .75rem; min-width: 0; }
    .field label {
      font-size: .78rem;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, .70);
    }
    .field input, .field textarea, .field select {
      width: 100%;
      min-width: 0;
      border-radius: .85rem;
      border: 1px solid rgba(255, 255, 255, .14);
      background: rgba(255, 255, 255, .05);
      color: var(--ink);
      padding: .65rem .75rem;
      outline: none;
      box-sizing: border-box;
    }
    .field textarea { min-height: 110px; resize: vertical; }
    .sheet-block input:focus, .sheet-block textarea:focus, .sheet-block select:focus {
      outline: none;
      border-color: rgba(167, 139, 250, .55);
      box-shadow: 0 0 0 .18rem rgba(167, 139, 250, .22);
    }
    .divider { border-top: 1px solid rgba(255, 255, 255, .10); margin: .9rem 0; }

    .wizard {
      display: grid;
      grid-template-columns: 320px minmax(0, 1fr);
      gap: 1rem;
      align-items: start;
    }
    @media (max-width: 992px) { .wizard { grid-template-columns: 1fr; } }
    .steps { display: grid; gap: .6rem; }
    .step-btn {
      display: flex; gap: .75rem; align-items: center;
      width: 100%; text-align: left;
      padding: .85rem; border-radius: 1rem;
      border: 1px solid rgba(255, 255, 255, .12);
      background: rgba(255, 255, 255, .04);
      color: var(--ink);
      transition: background .15s ease, border-color .15s ease, transform .15s ease;
    }
    .step-btn:hover {
      background: rgba(255, 255, 255, .07);
      border-color: rgba(255, 255, 255, .18);
      transform: translateY(-1px);
      color: var(--ink);
    }
    .step-btn.active {
      border-color: rgba(167, 139, 250, .55);
      background: linear-gradient(90deg, rgba(167, 139, 250, .12), rgba(34, 197, 94, .07));
    }
    .step-num {
      width: 34px; height: 34px;
      border-radius: 12px;
      display: inline-flex; align-items: center; justify-content: center;
      border: 1px solid rgba(255, 255, 255, .14);
      background: rgba(0, 0, 0, .12);
      font-weight: 800;
      flex: 0 0 auto;
    }
    .step-meta { min-width: 0; }
    .step-title { font-weight: 800; margin: 0; line-height: 1.1; }
    .step-desc { margin: .15rem 0 0 0; color: var(--muted); font-size: .9rem; }

    .progress-pill {
      display: flex; align-items: center; justify-content: space-between;
      gap: .75rem; padding: .8rem 1rem;
      border-radius: 1rem;
      border: 1px solid rgba(255, 255, 255, .12);
      background: rgba(255, 255, 255, .04);
    }
    .progress-pill .bar {
      flex: 1; height: 10px;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, .12);
      background: rgba(255, 255, 255, .04);
      overflow: hidden;
    }
    .progress-pill .bar>div { height: 100%; width: 0%; background: rgba(167, 139, 250, .55); }
    .pill {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .25rem .55rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, .12);
      background: rgba(255, 255, 255, .04);
      font-size: .85rem;
      color: var(--muted);
      white-space: nowrap;
    }
    .wizard-controls {
      display: flex; justify-content: space-between; gap: .75rem;
      flex-wrap: wrap; margin-top: 1rem;
    }
    .step-panel { display: none; }
    .step-panel.active { display: block; }

    /* Threshold row */
    .th-row {
      display: grid;
      grid-template-columns: 1fr auto 120px auto 1fr;
      gap: .5rem;
      align-items: center;
    }
    .th-label {
      border-radius: .85rem;
      border: 1px solid rgba(255, 255, 255, .14);
      background: rgba(255, 255, 255, .03);
      padding: .65rem .75rem;
      color: rgba(255,255,255,.85);
      font-size: .95rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .th-arrow { color: rgba(255,255,255,.55); }
    .th-mid {
      text-align: center;
      border-radius: .85rem;
      border: 1px solid rgba(255, 255, 255, .14);
      background: rgba(255, 255, 255, .05);
      padding: .65rem .75rem;
      font-weight: 800;
      color: rgba(255,255,255,.90);
    }
    @media (max-width: 992px) {
      .th-row { grid-template-columns: 1fr; }
      .th-arrow { display: none; }
      .th-mid { text-align: left; }
    }

    /* Hope hidden */
    .hope-hidden { display:none !important; }

    /* Experience list UI */
    .exp-row {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: .5rem;
      align-items: center;
    }
    .exp-list {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      padding: .6rem;
      border-radius: .85rem;
      border: 1px solid rgba(255, 255, 255, .12);
      background: rgba(255, 255, 255, .03);
      min-height: 52px;
    }
    .exp-item {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      max-width: 100%;
      padding: .35rem .55rem;
      border-radius: 999px;
      border: 1px solid rgba(255, 255, 255, .12);
      background: rgba(255, 255, 255, .05);
      color: rgba(255,255,255,.90);
      font-size: .9rem;
    }
    .exp-text {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: min(520px, 62vw);
    }
    .exp-del {
      border: 0;
      background: transparent;
      color: rgba(255,255,255,.65);
      padding: 0 .2rem;
      line-height: 1;
      cursor: pointer;
    }
    .exp-del:hover { color: rgba(255,255,255,.95); }
  </style>
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
        <button class="btn btn-brand" id="btnFinishTop" type="button">
          <i class="bi bi-check2-circle me-2"></i>Finish &amp; Create
        </button>
      </div>
    </div>
  </div>
</header>

<main class="container pb-5">
  <section class="glass panel">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
      <div>
        <div class="text-uppercase muted" style="letter-spacing:.14em;font-size:.78rem;">Character Creation</div>
        <h1 class="mb-1" style="letter-spacing:-0.03em;line-height:1.05;">Step-by-step Wizard</h1>
        <div class="muted">Create a character in guided steps. You can jump between steps at any time.</div>
      </div>

      <div class="progress-pill" style="min-width: min(520px, 100%);">
        <div class="d-flex align-items-center gap-2">
          <span class="pill"><i class="bi bi-list-check"></i><span id="progressText">0/5</span></span>
          <span class="pill"><i class="bi bi-person-badge"></i><span id="liveName">Unnamed</span></span>
        </div>
        <div class="bar" aria-label="Progress">
          <div id="progressBar"></div>
        </div>
      </div>
    </div>

    <div class="divider"></div>

    <div class="wizard">
      <!-- LEFT: steps -->
      <aside class="steps" aria-label="Steps">
        <button class="step-btn active" type="button" data-step="1">
          <span class="step-num">1</span>
          <span class="step-meta">
            <p class="step-title">Basics</p>
            <p class="step-desc">Name, pronouns, heritage, class.</p>
          </span>
        </button>

        <button class="step-btn" type="button" data-step="2">
          <span class="step-num">2</span>
          <span class="step-meta">
            <p class="step-title">Traits</p>
            <p class="step-desc">Set the six trait modifiers.</p>
          </span>
        </button>

        <button class="step-btn" type="button" data-step="3">
          <span class="step-num">3</span>
          <span class="step-meta">
            <p class="step-title">Defense, Health & Hope</p>
            <p class="step-desc">Evasion, armor, HP</p>
          </span>
        </button>

        <button class="step-btn" type="button" data-step="4">
          <span class="step-num">4</span>
          <span class="step-meta">
            <p class="step-title">Experience</p>
            <p class="step-desc">Experience List</p>
          </span>
        </button>

        <button class="step-btn" type="button" data-step="5">
          <span class="step-num">5</span>
          <span class="step-meta">
            <p class="step-title">Gear</p>
            <p class="step-desc">Weapons, inventory, gold.</p>
          </span>
        </button>

        <button class="step-btn" type="button" data-step="6">
          <span class="step-num">6</span>
          <span class="step-meta">
            <p class="step-title">Review</p>
            <p class="step-desc">Validate and finalize.</p>
          </span>
        </button>
      </aside>

      <!-- RIGHT: step content -->
      <section class="sheet-block" aria-label="Step content">
        <!-- STEP 1 -->
        <div class="step-panel active" data-step-panel="1">
          <div class="block-title">
            <h3>Basics</h3>
            <span class="chip"><i class="bi bi-person"></i> Identity</span>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-5">
              <div class="field">
                <label for="cName">Name</label>
                <input id="cName" type="text" placeholder="Character name"
                       value="<?php echo h($character['name'] ?? ''); ?>" />
              </div>
            </div>

            <div class="col-12 col-lg-5">
              <div class="field">
                <label for="cPronouns">Pronouns</label>
                <input id="cPronouns" type="text" placeholder="they/them"
                       value="<?php echo h($character['pronouns'] ?? ''); ?>" />
              </div>
            </div>

            <div class="col-12 col-lg-2">
              <div class="field">
                <label for="cLevel">Level</label>
                <input id="cLevel" type="number" min="1" value="<?php echo (int)($character['level'] ?? 1); ?>" />
              </div>
            </div>

            <div class="col-12 col-lg-3">
              <div class="field">
                <label for="cHeritage">Heritage</label>
                <select id="cHeritage">
                  <option value="">Select…</option>
                  <?php
                    $selHer = (int)($character['heritageID'] ?? 0);
                    foreach ($heritages as $hrow):
                      $hid = (int)$hrow['heritageID'];
                  ?>
                    <option value="<?php echo $hid; ?>" <?php echo ($hid === $selHer ? 'selected' : ''); ?>>
                      <?php echo h($hrow['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="col-12 col-lg-3">
              <div class="field">
                <label for="cClass">Class</label>
                <select id="cClass">
                  <option value="">Select…</option>
                  <?php
                    $selClass = (int)($character['classID'] ?? 0);
                    foreach ($classes as $crow):
                      $cid = (int)$crow['classID'];
                  ?>
                    <option value="<?php echo $cid; ?>" <?php echo ($cid === $selClass ? 'selected' : ''); ?>>
                      <?php echo h($crow['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="col-12 col-lg-3">
              <div class="field">
                <label for="cSubClass">Subclass</label>
                <select id="cSubClass" data-selected="<?php echo (int)($character['subclassID'] ?? 0); ?>">
                  <option value="">Select class first…</option>
                </select>
              </div>
            </div>

            <div class="col-12 col-lg-3">
              <div class="field">
                <label for="cCommunity">Community</label>
                <select id="cCommunity">
                  <option value="">Select…</option>
                  <?php
                    $selCom = (int)($character['communityID'] ?? 0);
                    foreach ($communities as $com):
                      $coid = (int)$com['communityID'];
                  ?>
                    <option value="<?php echo $coid; ?>" <?php echo ($coid === $selCom ? 'selected' : ''); ?>>
                      <?php echo h($com['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="muted small mt-2">
            Autosave is enabled for Basics (debounced).
          </div>
        </div>

        <!-- STEP 2 -->
        <div class="step-panel" data-step-panel="2">
          <div class="block-title">
            <h3>Traits</h3>
            <span class="chip"><i class="bi bi-speedometer2"></i> Modifiers</span>
          </div>

          <div class="muted mb-3">
            Set your character's six trait modifiers using the following list (+2,+1,+1,0,0,-1).
          </div>

          <div class="stat-inputs">
            <div class="field">
              <label for="tAgility">Agility</label>
              <select id="tAgility" class="trait-select" data-trait="agility"></select>
            </div>
            <div class="field">
              <label for="tStrength">Strength</label>
              <select id="tStrength" class="trait-select" data-trait="strength"></select>
            </div>
            <div class="field">
              <label for="tFinesse">Finesse</label>
              <select id="tFinesse" class="trait-select" data-trait="finesse"></select>
            </div>
            <div class="field">
              <label for="tInstinct">Instinct</label>
              <select id="tInstinct" class="trait-select" data-trait="instinct"></select>
            </div>
            <div class="field">
              <label for="tPresence">Presence</label>
              <select id="tPresence" class="trait-select" data-trait="presence"></select>
            </div>
            <div class="field">
              <label for="tKnowledge">Knowledge</label>
              <select id="tKnowledge" class="trait-select" data-trait="knowledge"></select>
            </div>
          </div>

          <div class="divider"></div>

          <div class="sheet-block" style="padding:.85rem;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <strong>Quick presets</strong>
              <span class="muted small">All presets respect the required distribution.</span>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-2">
              <button class="btn btn-outline-light btn-sm" type="button" data-preset="balanced">
                Balanced (valid)
              </button>
              <button class="btn btn-outline-light btn-sm" type="button" data-preset="agile">
                Agile (+2 Agility)
              </button>
              <button class="btn btn-outline-light btn-sm" type="button" data-preset="strong">
                Strong (+2 Strength)
              </button>
            </div>
          </div>

          <div class="muted small mt-2">
            Traits are saved automatically once all 6 are selected and valid.
          </div>
        </div>

        <!-- STEP 3 -->
        <div class="step-panel" data-step-panel="3">
          <div class="block-title">
            <h3>Defense, Health & Hope</h3>
            <span class="chip"><i class="bi bi-shield-heart"></i> Survival</span>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-6">
              <div class="sheet-block">
                <div class="block-title">
                  <h3>Defense</h3>
                  <span class="chip"><i class="bi bi-shield-check"></i> Values</span>
                </div>

                <div class="row g-3">
                  <div class="col-6">
                    <div class="field mb-0">
                      <label for="cEvasion">Evasion</label>
                      <input id="cEvasion" type="number" readonly />
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="field mb-0">
                      <label for="cArmor">Armor</label>
                      <input id="cArmor" type="number" readonly />
                    </div>
                  </div>
                </div>

                <div class="divider"></div>

                <div class="block-title">
                  <h3>Armor</h3>
                  <span class="chip"><i class="bi bi-shield"></i> Loadout</span>
                </div>

                <div class="field">
                  <label for="aName">Active Armor</label>
                  <select id="aName">
                    <option value="">Select…</option>
                  </select>
                  <div class="muted small mt-1" id="armorLevelHint"></div>
                </div>

                <div class="field">
                  <label>Thresholds</label>
                  <div class="th-row">
                    <div class="th-label">Minor Damage</div>
                    <div class="th-arrow"><i class="bi bi-arrow-right"></i></div>
                    <div class="th-mid" id="thMajor">—</div>
                    <div class="th-arrow"><i class="bi bi-arrow-right"></i></div>
                    <div class="th-label">Major Damage</div>
                  </div>
                  <div class="th-row mt-2">
                    <div class="th-label">Major Damage</div>
                    <div class="th-arrow"><i class="bi bi-arrow-right"></i></div>
                    <div class="th-mid" id="thSevere">—</div>
                    <div class="th-arrow"><i class="bi bi-arrow-right"></i></div>
                    <div class="th-label">Severe Damage</div>
                  </div>
                </div>

                <div class="field mb-0">
                  <label for="aFeat">Armor Feature</label>
                  <textarea id="aFeat" readonly placeholder="—"></textarea>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-6">
              <div class="sheet-block">
                <div class="block-title">
                  <h3>Health</h3>
                  <span class="chip"><i class="bi bi-heart-pulse"></i> Tracks</span>
                </div>

                <div class="row g-3">
                  <div class="col-6">
                    <div class="field mb-0">
                      <label for="cHPMax">HP</label>
                      <input id="cHPMax" type="number" min="0" value="" readonly />
                    </div>
                  </div>
                </div>

                <div class="divider"></div>

                <!-- Hope numeric hidden -->
                <div class="hope-hidden">
                  <label for="hopeMax">Hope</label>
                  <input id="hopeMax" type="hidden" value="6" />
                </div>

                <div class="block-title">
                  <h3>Hope Feature</h3>
                  <span class="chip"><i class="bi bi-stars"></i> Feature</span>
                </div>

                <div class="field mb-0">
                  <label for="cHopeFeature">Hope Feature</label>
                  <textarea id="cHopeFeature" readonly placeholder="—"></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- STEP 4 -->
        <div class="step-panel" data-step-panel="4">
          <div class="block-title">
            <h3>Experience</h3>
            <span class="chip"><i class="bi bi-book"></i> Notes</span>
          </div>

          <div class="sheet-block">
            <div class="block-title">
              <h3>Experience</h3>
              <span class="chip"><i class="bi bi-stars"></i> Roleplay</span>
            </div>

            <div class="muted mb-2">
              Add one or more Experiences / Tags. Each entry will be stored as its own row (mod = 2).
            </div>

            <div class="field">
              <label for="expInput">Add Experience</label>
              <div class="exp-row">
                <input id="expInput" type="text" placeholder="e.g. Veteran of the Mistroads" />
                <button class="btn btn-outline-light" type="button" id="btnAddExp">
                  <i class="bi bi-plus-lg me-2"></i>Add
                </button>
              </div>
              <div class="muted small mt-1" id="expHint">Press Enter to add. Duplicates are ignored.</div>
            </div>

            <div class="field mb-0">
              <label>Current List</label>
              <div id="expList" class="exp-list" aria-label="Experience list"></div>
              <div class="muted small mt-2" id="expSaveState"></div>
            </div>
          </div>
        </div>

        <!-- STEP 5 -->
        <div class="step-panel" data-step-panel="5">
          <div class="block-title">
            <h3>Gear</h3>
            <span class="chip"><i class="bi bi-backpack"></i> Equipment</span>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-7">
              <div class="sheet-block">
                <div class="block-title">
                  <h3>Weapons</h3>
                  <span class="chip"><i class="bi bi-crosshair"></i> Active</span>
                </div>

                <div class="">
                  <div class="sheet-block" style="padding:.85rem;">
                    <div>
                      <div class="d-flex align-items-center justify-content-between mb-2">
                        <strong>Primary</strong>
                        <span class="muted small">Proficiency (0-6)</span>
                      </div>
                      <div class="row g-2">
                        <div class="col-12">
                          <div class="field mb-0">
                            <label>Name</label>
                            <input id="w1Name" type="text" placeholder="Longbow" />
                          </div>
                        </div>
                        <div class="col-6">
                          <div class="field mb-0">
                            <label>Trait &amp; Range</label>
                            <input id="w1Trait" type="text" placeholder="Agility • Far" />
                          </div>
                        </div>
                        <div class="col-6">
                          <div class="field mb-0">
                            <label>Damage</label>
                            <input id="w1Dmg" type="text" placeholder="d8 • Piercing" />
                          </div>
                        </div>
                        <div class="col-12">
                          <div class="field mb-0">
                            <label>Feature</label>
                            <input id="w1Feat" type="text" placeholder="Special rule..." />
                          </div>
                        </div>
                        <div class="col-6">
                          <div class="field mb-0">
                            <label>Proficiency</label>
                            <input id="w1Prof" type="number" min="0" max="6" value="0" />
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="divider"></div>

                    <div>
                      <div class="d-flex align-items-center justify-content-between mb-2">
                        <strong>Secondary</strong>
                        <span class="muted small">Proficiency (0-6)</span>
                      </div>
                      <div class="row g-2">
                        <div class="col-12">
                          <div class="field mb-0">
                            <label>Name</label>
                            <input id="w2Name" type="text" placeholder="Dagger" />
                          </div>
                        </div>
                        <div class="col-6">
                          <div class="field mb-0">
                            <label>Trait &amp; Range</label>
                            <input id="w2Trait" type="text" placeholder="Finesse • Close" />
                          </div>
                        </div>
                        <div class="col-6">
                          <div class="field mb-0">
                            <label>Damage</label>
                            <input id="w2Dmg" type="text" placeholder="d6 • Slashing" />
                          </div>
                        </div>
                        <div class="col-12">
                          <div class="field mb-0">
                            <label>Feature</label>
                            <input id="w2Feat" type="text" placeholder="Special rule..." />
                          </div>
                        </div>
                        <div class="col-6">
                          <div class="field mb-0">
                            <label>Proficiency</label>
                            <input id="w2Prof" type="number" min="0" max="6" value="0" />
                          </div>
                        </div>
                      </div>
                    </div>

                  </div>
                </div>

              </div>
            </div>

            <div class="col-12 col-lg-5">
              <div class="sheet-block">
                <div class="field">
                  <label for="cInventory">Inventory</label>
                  <textarea id="cInventory"></textarea>
                </div>

                <div class="divider"></div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                  <strong>Gold</strong>
                  <span class="muted small">Handfuls / Bags / Chest</span>
                </div>
                <div class="row g-3">
                  <div class="col-4">
                    <div class="field mb-0">
                      <label for="gHandfuls">Handfuls</label>
                      <input id="gHandfuls" type="number" min="0" value="0" />
                    </div>
                  </div>
                  <div class="col-4">
                    <div class="field mb-0">
                      <label for="gBags">Bags</label>
                      <input id="gBags" type="number" min="0" value="0" />
                    </div>
                  </div>
                  <div class="col-4">
                    <div class="field mb-0">
                      <label for="gChest">Chest</label>
                      <input id="gChest" type="number" min="0" value="0" />
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- STEP 6 -->
        <div class="step-panel" data-step-panel="6">
          <div class="block-title">
            <h3>Review</h3>
            <span class="chip"><i class="bi bi-clipboard-check"></i> Final</span>
          </div>

          <div class="muted mb-3">
            Review the details below. Click “Finish & Create” to finalize.
          </div>

          <div class="sheet-block" style="padding:1rem;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <strong>Live summary</strong>
              <span class="muted small" id="validationText">No validation errors.</span>
            </div>
            <div class="divider"></div>

            <div class="row g-2">
              <div class="col-12 col-lg-6">
                <div class="muted small">Basics</div>
                <div><strong id="sumName">—</strong> <span class="muted" id="sumPronouns"></span></div>
                <div class="muted" id="sumLine2">—</div>
              </div>
              <div class="col-12 col-lg-6">
                <div class="muted small">Traits</div>
                <div id="sumTraits">—</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Controls -->
        <div class="wizard-controls">
          <button class="btn btn-outline-light" type="button" id="btnPrev">
            <i class="bi bi-arrow-left me-2"></i>Previous
          </button>

          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-brand" type="button" id="btnNext">
              Next<i class="bi bi-arrow-right ms-2"></i>
            </button>
          </div>
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
  // ---------- Theme toggle ----------
  const btnToggleTheme = document.getElementById('btnToggleTheme');
  btnToggleTheme?.addEventListener('click', () => {
    const html = document.documentElement;
    const cur = html.getAttribute('data-bs-theme') || 'dark';
    html.setAttribute('data-bs-theme', cur === 'dark' ? 'light' : 'dark');
    btnToggleTheme.innerHTML = cur === 'dark' ? '<i class="bi bi-moon"></i>' : '<i class="bi bi-sun"></i>';
  });

  // ---------- Wizard navigation ----------
  const stepButtons = Array.from(document.querySelectorAll('.step-btn'));
  const stepPanels = Array.from(document.querySelectorAll('.step-panel'));
  const btnPrev = document.getElementById('btnPrev');
  const btnNext = document.getElementById('btnNext');
  const btnFinishTop = document.getElementById('btnFinishTop');

  let currentStep = 1;
  const totalSteps = 6;

  function setStep(step) {
    currentStep = Math.max(1, Math.min(totalSteps, step));
    stepButtons.forEach(b => b.classList.toggle('active', parseInt(b.dataset.step, 10) === currentStep));
    stepPanels.forEach(p => p.classList.toggle('active', parseInt(p.dataset.stepPanel, 10) === currentStep));

    btnPrev.disabled = currentStep === 1;
    btnNext.innerHTML = currentStep === totalSteps
      ? 'Go to Review<i class="bi bi-clipboard-check ms-2"></i>'
      : 'Next<i class="bi bi-arrow-right ms-2"></i>';

    updateProgress();
    updateSummary();
    validateBasics();
  }

  stepButtons.forEach(b => b.addEventListener('click', () => setStep(parseInt(b.dataset.step, 10))));
  btnPrev.addEventListener('click', () => setStep(currentStep - 1));
  btnNext.addEventListener('click', () => setStep(currentStep + 1));

  // ---------- Progress ----------
  const progressText = document.getElementById('progressText');
  const progressBar = document.getElementById('progressBar');
  const liveName = document.getElementById('liveName');

  function countFilled() {
    const required = [
      document.getElementById('cName'),
      document.getElementById('cPronouns'),
      document.getElementById('cHeritage'),
      document.getElementById('cClass'),
      document.getElementById('cLevel'),
    ];
    return required.filter(el => (el?.value ?? '').toString().trim().length > 0).length;
  }

  function updateProgress() {
    const filled = countFilled();
    progressText.textContent = `${filled}/5`;
    const pct = Math.round((filled / 5) * 100);
    progressBar.style.width = `${pct}%`;
  }

  // ---------- Summary ----------
  const sumName = document.getElementById('sumName');
  const sumPronouns = document.getElementById('sumPronouns');
  const sumLine2 = document.getElementById('sumLine2');
  const sumTraits = document.getElementById('sumTraits');
  const validationText = document.getElementById('validationText');

  function v(id) {
    const el = document.getElementById(id);
    return (el?.value ?? '').toString().trim();
  }

  function signed(n) {
    if (n === '' || n == null) return '—';
    const num = parseInt(n, 10);
    if (Number.isNaN(num)) return '—';
    return (num >= 0 ? `+${num}` : `${num}`);
  }

  function updateSummary() {
    const name = v('cName') || 'Unnamed';
    liveName.textContent = name;

    if (sumName) sumName.textContent = v('cName') || '—';
    if (sumPronouns) sumPronouns.textContent = v('cPronouns') ? `(${v('cPronouns')})` : '';

    const herSel = document.querySelector('#cHeritage option:checked');
    const classSel = document.querySelector('#cClass option:checked');
    const subSel = document.querySelector('#cSubClass option:checked');
    const comSel = document.querySelector('#cCommunity option:checked');

    const parts = [];
    if (herSel && herSel.value) parts.push(`Heritage: ${herSel.textContent.trim()}`);
    if (classSel && classSel.value) parts.push(`Class: ${classSel.textContent.trim()}`);
    if (subSel && subSel.value) parts.push(`Subclass: ${subSel.textContent.trim()}`);
    if (comSel && comSel.value) parts.push(`Community: ${comSel.textContent.trim()}`);
    parts.push(`Level: ${v('cLevel') || '—'}`);

    if (sumLine2) sumLine2.textContent = parts.join(' • ');

    if (sumTraits) {
      sumTraits.textContent =
        `AGI ${signed(v('tAgility'))}, STR ${signed(v('tStrength'))}, FIN ${signed(v('tFinesse'))}, ` +
        `INS ${signed(v('tInstinct'))}, PRE ${signed(v('tPresence'))}, KNO ${signed(v('tKnowledge'))}`;
    }

    updateProgress();
  }

  // ---------- Validation ----------
  function validateBasics(showAlert = false) {
    const missing = [];
    if (!v('cName')) missing.push('Name');
    if (!v('cPronouns')) missing.push('Pronouns');
    if (!v('cHeritage')) missing.push('Heritage');
    if (!v('cClass')) missing.push('Class');
    if (!v('cLevel')) missing.push('Level');

    if (missing.length) {
      if (validationText) validationText.textContent = `Missing: ${missing.join(', ')}.`;
      if (showAlert) alert(`Please fill required fields: ${missing.join(', ')}`);
      return false;
    }
    if (validationText) validationText.textContent = 'No validation errors.';
    return true;
  }

  // =========================================================
  // STEP 1: Subclass loading
  // =========================================================
  const cClass = document.getElementById('cClass');
  const cSubClass = document.getElementById('cSubClass');

  async function loadSubclasses(classID) {
    cSubClass.innerHTML = '';
    if (!classID) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'Select class first…';
      cSubClass.appendChild(opt);
      return;
    }

    try {
      const res = await fetch(`creator.php?action=getSubclasses&classID=${encodeURIComponent(classID)}`, {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();

      const optBlank = document.createElement('option');
      optBlank.value = '';
      optBlank.textContent = 'Select…';
      cSubClass.appendChild(optBlank);

      if (!json.ok) return;

      const selected = parseInt(cSubClass.dataset.selected || '0', 10);

      for (const row of json.rows) {
        const opt = document.createElement('option');
        opt.value = String(row.subclassID);
        opt.textContent = row.name;
        if (selected && parseInt(row.subclassID, 10) === selected) opt.selected = true;
        cSubClass.appendChild(opt);
      }
    } catch (e) {
      console.error(e);
    }
  }

  // =========================================================
  // STEP 3: Derived + saving Defense
  // =========================================================
  const cLevel = document.getElementById('cLevel');
  const cEvasion = document.getElementById('cEvasion');
  const cArmor = document.getElementById('cArmor');

  const cHPMax = document.getElementById('cHPMax');
  const cHopeFeature = document.getElementById('cHopeFeature');

  const aName = document.getElementById('aName');
  const aFeat = document.getElementById('aFeat');
  const thMajor = document.getElementById('thMajor');
  const thSevere = document.getElementById('thSevere');
  const armorLevelHint = document.getElementById('armorLevelHint');

  let defenseSaveTimer = null;
  function scheduleSaveDefense() {
    window.clearTimeout(defenseSaveTimer);
    defenseSaveTimer = window.setTimeout(saveDefense, 300);
  }

  async function saveDefense() {
    const payload = {
      evasion: cEvasion.value === '' ? null : parseInt(cEvasion.value, 10),
      armor:   cArmor.value === '' ? null : parseInt(cArmor.value, 10),
      armorID: aName.value === '' ? 0 : parseInt(aName.value, 10)
    };

    try {
      const res = await fetch('creator.php?action=saveDefense', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!json.ok) console.debug(json.error || 'saveDefense failed');
    } catch (e) {
      console.error(e);
    }
  }

  async function loadClassDerived() {
    const classID = parseInt(cClass?.value || '0', 10) || 0;

    if (!classID) {
      cEvasion.value = '';
      cHPMax.value = '';
      cHopeFeature.value = '';
      scheduleSaveDefense();
      return;
    }

    try {
      const res = await fetch(`creator.php?action=getClassInfo&classID=${encodeURIComponent(classID)}`, {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();

      if (!json.ok || !json.row) {
        cEvasion.value = '';
        cHPMax.value = '';
        cHopeFeature.value = '';
        scheduleSaveDefense();
        return;
      }

      cEvasion.value = (json.row.starting_evasion_score ?? '').toString();
      cHPMax.value = (json.row.starting_hit_point ?? '').toString();
      cHopeFeature.value = (json.row.hope_feature ?? '').toString();

      scheduleSaveDefense();
      scheduleSaveTraits(); // if traits are valid, HP gets stored too
    } catch (e) {
      console.error(e);
    }
  }

  async function loadArmorOptions() {
    const lvl = parseInt(cLevel?.value || '1', 10) || 1;
    const prev = aName.value ? parseInt(aName.value, 10) : 0;

    aName.innerHTML = '';
    const optBlank = document.createElement('option');
    optBlank.value = '';
    optBlank.textContent = 'Select…';
    aName.appendChild(optBlank);

    try {
      const res = await fetch(`creator.php?action=getArmors&level=${encodeURIComponent(lvl)}`, {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.ok) return;

      for (const row of json.rows) {
        const opt = document.createElement('option');
        opt.value = String(row.armorID);
        opt.textContent = row.name;
        aName.appendChild(opt);
      }

      if (prev) {
        const still = Array.from(aName.options).some(o => parseInt(o.value || '0', 10) === prev);
        if (still) aName.value = String(prev);
        else aName.value = '';
      }

      armorLevelHint.textContent = `Showing armors with min_level ≤ ${lvl}.`;
      await loadArmorInfo();
    } catch (e) {
      console.error(e);
    }
  }

  async function loadArmorInfo() {
    const armorID = parseInt(aName?.value || '0', 10) || 0;

    if (!armorID) {
      cArmor.value = '';
      thMajor.textContent = '—';
      thSevere.textContent = '—';
      aFeat.value = '';
      scheduleSaveDefense();
      return;
    }

    try {
      const res = await fetch(`creator.php?action=getArmorInfo&armorID=${encodeURIComponent(armorID)}`, {
        headers: { 'Accept': 'application/json' }
      });
      const json = await res.json();
      if (!json.ok || !json.row) return;

      cArmor.value = (json.row.base_score ?? '').toString();
      thMajor.textContent = (json.row.major_threshold ?? '—').toString();
      thSevere.textContent = (json.row.severe_threshold ?? '—').toString();
      aFeat.value = (json.row.feature ?? '').toString();

      scheduleSaveDefense();
    } catch (e) {
      console.error(e);
    }
  }

  aName?.addEventListener('change', async () => {
    await loadArmorInfo();
  });

  // =========================================================
  // STEP 1: Autosave basics (debounced)
  // =========================================================
  let basicsTimer = null;
  function scheduleSaveBasics() {
    window.clearTimeout(basicsTimer);
    basicsTimer = window.setTimeout(saveBasics, 350);
  }

  async function saveBasics() {
    const payload = {
      name: document.getElementById('cName')?.value ?? '',
      pronouns: document.getElementById('cPronouns')?.value ?? '',
      level: parseInt(document.getElementById('cLevel')?.value ?? '1', 10) || 1,
      heritageID: parseInt(document.getElementById('cHeritage')?.value ?? '0', 10) || 0,
      classID: parseInt(document.getElementById('cClass')?.value ?? '0', 10) || 0,
      subclassID: parseInt(document.getElementById('cSubClass')?.value ?? '0', 10) || 0,
      communityID: parseInt(document.getElementById('cCommunity')?.value ?? '0', 10) || 0,
    };

    try {
      const res = await fetch('creator.php?action=saveBasics', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!json.ok) console.error(json.error || 'saveBasics failed');

      // If basics exists, allow derived pieces to persist
      if (json.ok) {
        scheduleSaveDefense();
        scheduleSaveExperiences();
      }
    } catch (e) {
      console.error(e);
    }
  }

  ['cName','cPronouns','cHeritage','cCommunity'].forEach(id => {
    const el = document.getElementById(id);
    el?.addEventListener('input', () => { scheduleSaveBasics(); updateSummary(); });
    el?.addEventListener('change', () => { scheduleSaveBasics(); updateSummary(); });
  });

  cLevel?.addEventListener('change', async () => {
    scheduleSaveBasics();
    updateSummary();
    await loadArmorOptions();
  });

  cClass?.addEventListener('change', async () => {
    cSubClass.dataset.selected = '0';
    await loadSubclasses(cClass.value);
    scheduleSaveBasics();
    updateSummary();
    await loadClassDerived();
  });

  cSubClass?.addEventListener('change', () => {
    scheduleSaveBasics();
    updateSummary();
  });

  // initial loads
  loadSubclasses(cClass?.value || '');
  loadClassDerived();
  loadArmorOptions();

  // =========================================================
  // STEP 2: Traits pool dropdown + integrity + autosave (Traits + HP together)
  // =========================================================
  const TRAIT_POOL = new Map([[ 2, 1],[ 1, 2],[ 0, 2],[-1, 1]]);
  const traitSelects = Array.from(document.querySelectorAll('.trait-select'));

  function initTraitSelects() {
    for (const sel of traitSelects) {
      sel.innerHTML = '';
      const optBlank = document.createElement('option');
      optBlank.value = '';
      optBlank.textContent = 'Select…';
      sel.appendChild(optBlank);
    }
    refreshTraitOptions();
  }

  function countMap(arr) {
    const m = new Map();
    for (const x of arr) m.set(x, (m.get(x) || 0) + 1);
    return m;
  }

  function refreshTraitOptions() {
    for (const sel of traitSelects) {
      const cur = sel.value === '' ? null : parseInt(sel.value, 10);

      const other = traitSelects
        .filter(s => s !== sel)
        .map(s => s.value)
        .filter(v => v !== '' && v != null)
        .map(v => parseInt(v, 10));

      const used = countMap(other);
      const prev = cur;

      sel.innerHTML = '';
      const optBlank = document.createElement('option');
      optBlank.value = '';
      optBlank.textContent = 'Select…';
      sel.appendChild(optBlank);

      const order = [2, 1, 0, -1];
      for (const val of order) {
        const maxCount = TRAIT_POOL.get(val) || 0;
        const usedCount = used.get(val) || 0;
        const remaining = maxCount - usedCount;

        if (remaining > 0 || (prev !== null && val === prev)) {
          const opt = document.createElement('option');
          opt.value = String(val);
          opt.textContent = (val >= 0 ? `+${val}` : `${val}`);
          sel.appendChild(opt);
        }
      }

      if (prev !== null) sel.value = String(prev);
    }
  }

  function traitsCompleteAndValid() {
    const vals = traitSelects
      .map(s => s.value)
      .filter(v => v !== '')
      .map(v => parseInt(v, 10));

    if (vals.length !== 6) return false;
    vals.sort((a,b) => a-b);
    const pool = [-1,0,0,1,1,2];
    return vals.every((v,i) => v === pool[i]);
  }

  let traitsSaveTimer = null;
  function scheduleSaveTraits() {
    window.clearTimeout(traitsSaveTimer);
    traitsSaveTimer = window.setTimeout(saveTraits, 300);
  }

  async function saveTraits() {
    if (!traitsCompleteAndValid()) return;

    const payload = {
      strength:  parseInt(document.getElementById('tStrength').value, 10),
      agility:   parseInt(document.getElementById('tAgility').value, 10),
      finesse:   parseInt(document.getElementById('tFinesse').value, 10),
      instinct:  parseInt(document.getElementById('tInstinct').value, 10),
      presence:  parseInt(document.getElementById('tPresence').value, 10),
      knowledge: parseInt(document.getElementById('tKnowledge').value, 10),
      hp:        (cHPMax.value === '' ? 0 : parseInt(cHPMax.value, 10)),
    };

    try {
      const res = await fetch('creator.php?action=saveTraits', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!json.ok) console.error(json.error || 'saveTraits failed');
    } catch (e) {
      console.error(e);
    }
  }

  traitSelects.forEach(sel => {
    sel.addEventListener('change', () => {
      refreshTraitOptions();
      scheduleSaveTraits();
      updateSummary();
    });
  });

  document.querySelectorAll('[data-preset]').forEach(btn => {
    btn.addEventListener('click', () => {
      const p = btn.dataset.preset;
      const set = (id, val) => document.getElementById(id).value = String(val);

      if (p === 'balanced') {
        set('tStrength', 0);
        set('tAgility', 0);
        set('tFinesse', 1);
        set('tInstinct', 1);
        set('tPresence', -1);
        set('tKnowledge', 2);
      }
      if (p === 'agile') {
        set('tAgility', 2);
        set('tStrength', 0);
        set('tFinesse', 1);
        set('tInstinct', 1);
        set('tPresence', 0);
        set('tKnowledge', -1);
      }
      if (p === 'strong') {
        set('tStrength', 2);
        set('tAgility', 0);
        set('tFinesse', 1);
        set('tInstinct', 1);
        set('tPresence', 0);
        set('tKnowledge', -1);
      }

      refreshTraitOptions();
      scheduleSaveTraits();
      updateSummary();
    });
  });

  // Prefill traits from server if present
  (function prefillFromServerStats() {
    const serverStats = <?php echo json_encode($stats ?: null, JSON_UNESCAPED_UNICODE); ?>;
    if (!serverStats) return;

    const set = (id, val) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.value = (val ?? '').toString();
    };

    set('tStrength',  serverStats.strength);
    set('tAgility',   serverStats.agility);
    set('tFinesse',   serverStats.finesse);
    set('tInstinct',  serverStats.instinct);
    set('tPresence',  serverStats.presence);
    set('tKnowledge', serverStats.knowledge);
  })();

  // =========================================================
  // STEP 4: Experience list UI + autosave
  // =========================================================
  const expInput = document.getElementById('expInput');
  const btnAddExp = document.getElementById('btnAddExp');
  const expList = document.getElementById('expList');
  const expSaveState = document.getElementById('expSaveState');

  // Start from DB-prefill
  let experiences = <?php echo json_encode($experiences ?: [], JSON_UNESCAPED_UNICODE); ?>;
  if (!Array.isArray(experiences)) experiences = [];

  function normalizeExp(s) {
    return (s ?? '').toString().trim().replace(/\s+/g, ' ');
  }

  function renderExperiences() {
    expList.innerHTML = '';

    if (experiences.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'muted small';
      empty.textContent = 'No experiences added yet.';
      expList.appendChild(empty);
      return;
    }

    experiences.forEach((txt, idx) => {
      const item = document.createElement('div');
      item.className = 'exp-item';

      const span = document.createElement('span');
      span.className = 'exp-text';
      span.title = txt;
      span.textContent = txt;

      const del = document.createElement('button');
      del.type = 'button';
      del.className = 'exp-del';
      del.innerHTML = '<i class="bi bi-x-lg"></i>';
      del.title = 'Remove';
      del.addEventListener('click', () => {
        experiences.splice(idx, 1);
        renderExperiences();
        scheduleSaveExperiences();
      });

      item.appendChild(span);
      item.appendChild(del);
      expList.appendChild(item);
    });
  }

  function addExperienceFromInput() {
    const t = normalizeExp(expInput.value);
    if (!t) return;

    // cap length
    const capped = t.length > 200 ? t.slice(0, 200) : t;

    // ignore duplicates (case-sensitive as DB unique optional; you can change to lower-case compare if you want)
    if (experiences.includes(capped)) {
      expInput.value = '';
      return;
    }

    experiences.push(capped);
    expInput.value = '';
    renderExperiences();
    scheduleSaveExperiences();
  }

  btnAddExp?.addEventListener('click', addExperienceFromInput);
  expInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      addExperienceFromInput();
    }
  });

  let expSaveTimer = null;
  function scheduleSaveExperiences() {
    window.clearTimeout(expSaveTimer);
    expSaveTimer = window.setTimeout(saveExperiences, 350);
    if (expSaveState) expSaveState.textContent = 'Pending save…';
  }

  async function saveExperiences() {
    const payload = { items: experiences.slice() };

    try {
      const res = await fetch('creator.php?action=saveExperiences', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!json.ok) {
        console.debug(json.error || 'saveExperiences failed');
        if (expSaveState) expSaveState.textContent = 'Not saved (character not created yet).';
        return;
      }
      if (expSaveState) expSaveState.textContent = `Saved ${json.count ?? experiences.length} entr${(json.count ?? 0) === 1 ? 'y' : 'ies'}.`;
    } catch (e) {
      console.error(e);
      if (expSaveState) expSaveState.textContent = 'Save failed (network).';
    }
  }

  renderExperiences();

  // =========================================================
  // Finish action: saves Basics + Traits + Defense + Experiences
  // =========================================================
  async function finishCreate() {
    if (!validateBasics(true)) {
      setStep(1);
      return;
    }
    await saveBasics();
    await saveTraits();
    await saveDefense();
    await saveExperiences();
    alert('Saved to database (Basics + Traits+HP + Defense + Active Armor + Experiences).');
  }
  btnFinishTop.addEventListener('click', finishCreate);

  // Init
  initTraitSelects();
  setStep(1);
  updateSummary();
</script>
</body>
</html>