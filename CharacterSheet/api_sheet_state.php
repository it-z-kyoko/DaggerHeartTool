<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

$root = dirname(__DIR__);
require_once $root . '/Database/Database.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $p, int $code = 200): never {
    http_response_code($code);
    echo json_encode($p, JSON_UNESCAPED_UNICODE);
    exit;
}

function format_mod(int $v): string {
    return ($v >= 0 ? '+' : '') . (string)$v;
}

function load_hope_feature_description(Database $db, int $classId): string
{
    if ($classId <= 0) return '';

    $sql = "
        SELECT description, hope
        FROM feature
        WHERE classID = :cid
          AND hope = 3
        LIMIT 1
    ";
    $row = $db->fetch($sql, [':cid' => $classId]);

    if (!$row) return '';
    $hope = (int)($row['hope'] ?? 0);
    $desc = (string)($row['description'] ?? '');
    if ($desc === '') return '';

    // gewünschtes Format:
    // "Spend [Hope] [Description]"
    return "Spend {$hope} {$desc}";
}

function load_armor_by_id(Database $db, int $armorId): ?array
{
    if ($armorId <= 0) return null;

    $sql = "
        SELECT armorID, name, base_score, major_threshold, severe_threshold, feature
        FROM armor
        WHERE armorID = :aid
        LIMIT 1
    ";
    return $db->fetch($sql, [':aid' => $armorId]);
}

function load_character_weapon(Database $db, int $characterId, int $primary): ?array
{
    $sql = "
        SELECT
            cw.weaponID,
            cw.primary,
            w.name    AS weapon_name,
            w.trait   AS weapon_trait,
            w.range   AS weapon_range,
            w.damage  AS weapon_damage,
            w.feature AS weapon_feature
        FROM character_weapon cw
        JOIN weapon w ON w.weaponID = cw.weaponID
        WHERE cw.characterID = :cid
          AND cw.primary = :p
        LIMIT 1
    ";
    return $db->fetch($sql, [':cid' => $characterId, ':p' => $primary]);
}

function load_character_experiences(Database $db, int $characterId): array
{
    $sql = "
        SELECT experienceID, experience, mod
        FROM character_experience
        WHERE characterID = :cid
        ORDER BY experienceID ASC
    ";
    return $db->fetchAll($sql, [':cid' => $characterId]);
}

function load_inventory(Database $db, int $characterId): array
{
    $sql = "
        SELECT itemID, Item, Description, Amount
        FROM character_inventory
        WHERE characterID = :cid
        ORDER BY itemID DESC
    ";
    return $db->fetchAll($sql, [':cid' => $characterId]);
}

function load_roll_history(Database $db, int $userId, int $characterId, int $limit = 10): array
{
    if ($userId <= 0) return [];

    // Falls deine Tabelle anders heißt: hier anpassen.
    // Erwartete Spalten: rollID, userID, characterID, dice, total, fear, created_at
    $sql = "
        SELECT rollID, dice, total, fear
        FROM roll_history
        WHERE userID = :uid
          AND characterID = :cid
        ORDER BY rollID DESC
        LIMIT {$limit}
    ";
    return $db->fetchAll($sql, [':uid' => $userId, ':cid' => $characterId]);
}

/* ------------------- AUTH ------------------- */
if (!isset($_SESSION['userID'])) {
    json_out(['ok' => false, 'error' => 'Not logged in'], 401);
}

$uid = (int)$_SESSION['userID'];
$characterID = (int)($_GET['characterID'] ?? 0);
if ($characterID <= 0) json_out(['ok' => false, 'error' => 'characterID missing'], 400);

$db = Database::getInstance($root . '/Database/Daggerheart.db');

/* Ownership check */
$ch = $db->fetch(
    'SELECT characterID, userID, "name", pronouns, classID, subclassID, heritageID, communityID, armorID, armor, "Level"
     FROM character
     WHERE characterID = :id',
    [':id' => $characterID]
);
if (!$ch) json_out(['ok' => false, 'error' => 'Character not found'], 404);
if ((int)$ch['userID'] !== $uid) json_out(['ok' => false, 'error' => 'Forbidden'], 403);

/* Profile + joins (names) + class starting_evasion_score */
$profile = $db->fetch(
    "
    SELECT
      c.characterID,
      c.userID,
      c.name,
      c.pronouns,
      c.classID,
      c.subclassID,
      c.heritageID,
      c.communityID,
      c.armorID,
      c.armor AS armor_tracker,
      c.Level AS level,

      h.name  AS heritage_name,
      co.name AS community_name,
      cl.name AS class_name,
      sc.name AS subclass_name,

      cl.starting_evasion_score AS starting_evasion_score
    FROM character c
    LEFT JOIN heritage  h  ON h.heritageID  = c.heritageID
    LEFT JOIN community co ON co.communityID = c.communityID
    LEFT JOIN class     cl ON cl.classID     = c.classID
    LEFT JOIN subclass  sc ON sc.subclassID  = c.subclassID
    WHERE c.characterID = :id
    LIMIT 1
    ",
    [':id' => $characterID]
);

/* Stats: Traits + Trackers */
$stats = $db->fetch(
    "
    SELECT
      Agility, Strength, Finesse, Instinct, Presence, Knowledge,
      Hope, Stress, HP
    FROM character_stats
    WHERE characterID = :id
    LIMIT 1
    ",
    [':id' => $characterID]
) ?? [];

/* Derived */
$classId = (int)($profile['classID'] ?? 0);
$armorId = (int)($profile['armorID'] ?? 0);

$hopeFeatureDesc = load_hope_feature_description($db, $classId);
$equippedArmor   = load_armor_by_id($db, $armorId);

$primaryWeapon   = load_character_weapon($db, $characterID, 1);
$secondaryWeapon = load_character_weapon($db, $characterID, 0);

$experiences = load_character_experiences($db, $characterID);
$inventory   = load_inventory($db, $characterID);
$rollHistory = load_roll_history($db, $uid, $characterID, 10);

/* Evasion: prefer class.starting_evasion_score */
$evasion = 0;
if (isset($profile['starting_evasion_score']) && is_numeric($profile['starting_evasion_score'])) {
    $evasion = (int)$profile['starting_evasion_score'];
}

/* Return everything the UI needs */
json_out([
    'ok' => true,

    'character' => [
        'characterID' => (int)($profile['characterID'] ?? $ch['characterID']),
        'name'        => (string)($profile['name'] ?? $ch['name'] ?? ''),
        'pronouns'    => (string)($profile['pronouns'] ?? $ch['pronouns'] ?? ''),
        'level'       => (int)($profile['level'] ?? $ch['Level'] ?? 1),

        'classID'     => (int)($profile['classID'] ?? $ch['classID'] ?? 0),
        'subclassID'  => (int)($profile['subclassID'] ?? $ch['subclassID'] ?? 0),
        'heritageID'  => (int)($profile['heritageID'] ?? $ch['heritageID'] ?? 0),
        'communityID' => (int)($profile['communityID'] ?? $ch['communityID'] ?? 0),
        'armorID'     => (int)($profile['armorID'] ?? $ch['armorID'] ?? 0),

        'heritage_name'  => (string)($profile['heritage_name'] ?? '—'),
        'community_name' => (string)($profile['community_name'] ?? '—'),
        'class_name'     => (string)($profile['class_name'] ?? '—'),
        'subclass_name'  => (string)($profile['subclass_name'] ?? '—'),

        'armor_tracker' => (int)($profile['armor_tracker'] ?? $ch['armor'] ?? 0),
        'evasion'       => (int)$evasion,
    ],

    'stats' => [
        // Traits
        'Agility'   => isset($stats['Agility'])   ? (int)$stats['Agility']   : 0,
        'Strength'  => isset($stats['Strength'])  ? (int)$stats['Strength']  : 0,
        'Finesse'   => isset($stats['Finesse'])   ? (int)$stats['Finesse']   : 0,
        'Instinct'  => isset($stats['Instinct'])  ? (int)$stats['Instinct']  : 0,
        'Presence'  => isset($stats['Presence'])  ? (int)$stats['Presence']  : 0,
        'Knowledge' => isset($stats['Knowledge']) ? (int)$stats['Knowledge'] : 0,

        // Trackers
        'HP'     => isset($stats['HP'])     ? (int)$stats['HP']     : 0,
        'Stress' => isset($stats['Stress']) ? (int)$stats['Stress'] : 0,
        'Hope'   => isset($stats['Hope'])   ? (int)$stats['Hope']   : 0,
    ],

    'hope_feature' => [
        'text' => $hopeFeatureDesc,
    ],

    'armor' => [
        'equipped' => $equippedArmor ? [
            'name'            => (string)($equippedArmor['name'] ?? '—'),
            'base_score'      => (int)($equippedArmor['base_score'] ?? 0),
            'major_threshold' => (int)($equippedArmor['major_threshold'] ?? 0),
            'severe_threshold'=> (int)($equippedArmor['severe_threshold'] ?? 0),
            'feature'         => (string)($equippedArmor['feature'] ?? ''),
        ] : null
    ],

    'weapons' => [
        'primary'   => $primaryWeapon,
        'secondary' => $secondaryWeapon,
    ],

    'experiences' => array_map(static function(array $r): array {
        $mod = (int)($r['mod'] ?? 0);
        return [
            'experienceID' => (int)($r['experienceID'] ?? 0),
            'experience'   => (string)($r['experience'] ?? ''),
            'mod'          => $mod,
            'mod_text'     => format_mod($mod),
        ];
    }, $experiences),

    'inventory' => array_map(static function(array $r): array {
        return [
            'itemID'       => (int)($r['itemID'] ?? 0),
            'Item'         => (string)($r['Item'] ?? ''),
            'Description'  => (string)($r['Description'] ?? ''),
            'Amount'       => (int)($r['Amount'] ?? 0),
        ];
    }, $inventory),

    'roll_history' => array_map(static function(array $r): array {
        return [
            'rollID' => (int)($r['rollID'] ?? 0),
            'dice'   => (string)($r['dice'] ?? ''),
            'total'  => (int)($r['total'] ?? 0),
            'fear'   => ($r['fear'] === null) ? null : (int)$r['fear'],
        ];
    }, $rollHistory),
]);