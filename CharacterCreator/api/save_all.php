<?php
declare(strict_types=1);
session_start();

$root = dirname(__DIR__, 2);
require_once $root . '/Database/Database.php';

/**
 * UTF-8 safe helpers (mbstring optional)
 */
function str_len(string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}
function str_cut(string $s, int $max): string {
    if ($max <= 0) return '';
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max, 'UTF-8');
    }
    return substr($s, 0, $max);
}
function str_lower(string $s): string {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function json_out(array $p, int $s = 200): void {
    http_response_code($s);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($p, JSON_UNESCAPED_UNICODE);
    exit;
}

function getCurrentUserId(): ?int {
    if (isset($_SESSION['userID']) && is_numeric($_SESSION['userID'])) return (int)$_SESSION['userID'];
    if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    if (isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    if (isset($_SESSION['user']['userID']) && is_numeric($_SESSION['user']['userID'])) return (int)$_SESSION['user']['userID'];
    return null;
}

$userID = getCurrentUserId();
if ($userID === null) json_out(['ok' => false, 'error' => 'Not logged in'], 401);

try {
    $db = Database::getInstance($root . '/Database/Daggerheart.db');

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true, 512, JSON_THROW_ON_ERROR);

    $basics      = $data['basics'] ?? [];
    $traits      = $data['traits'] ?? [];
    $def         = $data['defense'] ?? [];
    $experiences = $data['experiences'] ?? [];
    $gear        = $data['gear'] ?? [];
    $inventory   = $data['inventory'] ?? [];

    // NEW: domain cards (filenames)
    $domainCards = $data['domainCards'] ?? [];

    $name     = trim((string)($basics['name'] ?? ''));
    $pronouns = trim((string)($basics['pronouns'] ?? ''));
    $level    = (int)($basics['level'] ?? 1);
    if ($level < 1) $level = 1;

    $heritageID  = (int)($basics['heritageID'] ?? 0);
    $classID     = (int)($basics['classID'] ?? 0);
    $subclassID  = (int)($basics['subclassID'] ?? 0);
    $communityID = (int)($basics['communityID'] ?? 0);

    // ---- Validate minimum
    if ($name === '' || $pronouns === '' || $heritageID <= 0 || $classID <= 0 || $level <= 0) {
        json_out(['ok' => false, 'error' => 'Basics incomplete'], 400);
    }

    // ---- Validate trait distribution
    $strength  = (int)($traits['strength'] ?? 0);
    $agility   = (int)($traits['agility'] ?? 0);
    $finesse   = (int)($traits['finesse'] ?? 0);
    $instinct  = (int)($traits['instinct'] ?? 0);
    $presence  = (int)($traits['presence'] ?? 0);
    $knowledge = (int)($traits['knowledge'] ?? 0);
    $hp        = (int)($traits['hp'] ?? 0);

    $vals = [$strength, $agility, $finesse, $instinct, $presence, $knowledge];
    sort($vals);
    $pool = [-1, 0, 0, 1, 1, 2];
    if ($vals !== $pool) json_out(['ok' => false, 'error' => 'Invalid trait distribution'], 400);

    // ---- Defense
    $evasion = (int)($def['evasion'] ?? 0);
    $armor   = (int)($def['armor'] ?? 0);
    $armorID = (int)($def['armorID'] ?? 0);

    // ---- Gear weapons
    $primaryWeaponID   = (int)($gear['primaryWeaponID'] ?? 0);
    $secondaryWeaponID = (int)($gear['secondaryWeaponID'] ?? 0);

    // ---- Start transaction
    $db->execute("BEGIN");

    // 1) Insert character (NEW each time)
    $db->execute(
        "INSERT INTO character (userID, name, pronouns, level, heritageID, classID, subclassID, communityID, evasion, armor)
         VALUES (:uid,:name,:pro,:lvl,:hid,:clid,:scid,:comid,:eva,:arm)",
        [
            ':uid'   => $userID,
            ':name'  => $name,
            ':pro'   => $pronouns,
            ':lvl'   => $level,
            ':hid'   => $heritageID ?: null,
            ':clid'  => $classID ?: null,
            ':scid'  => $subclassID ?: null,
            ':comid' => $communityID ?: null,
            ':eva'   => $evasion ?: null,
            ':arm'   => $armor ?: null
        ]
    );

    $characterID = (int)$db->lastInsertId();
    if ($characterID <= 0) throw new RuntimeException("Failed to create character");

    // 2) character_stats
    $db->execute(
        "INSERT INTO character_stats (characterID, strength, agility, finesse, instinct, presence, knowledge, HP)
         VALUES (:cid,:str,:agi,:fin,:ins,:pre,:kno,:hp)
         ON CONFLICT(characterID) DO UPDATE SET
           strength=excluded.strength,
           agility=excluded.agility,
           finesse=excluded.finesse,
           instinct=excluded.instinct,
           presence=excluded.presence,
           knowledge=excluded.knowledge,
           HP=excluded.HP",
        [
            ':cid' => $characterID,
            ':str' => $strength,
            ':agi' => $agility,
            ':fin' => $finesse,
            ':ins' => $instinct,
            ':pre' => $presence,
            ':kno' => $knowledge,
            ':hp'  => $hp
        ]
    );

    // 3) character_armor (active armor)
    if ($armorID > 0) {
        $db->execute(
            "INSERT INTO character_armor (characterID, armorID)
             VALUES (:cid,:aid)
             ON CONFLICT(characterID) DO UPDATE SET armorID=excluded.armorID",
            [':cid' => $characterID, ':aid' => $armorID]
        );
    }

    // 4) experiences replace-all
    $db->execute("DELETE FROM character_experience WHERE characterID = :cid", [':cid' => $characterID]);
    if (is_array($experiences)) {
        $clean = [];
        foreach ($experiences as $x) {
            $t = trim((string)$x);
            if ($t === '') continue;
            if (str_len($t) > 200) $t = str_cut($t, 200);
            $clean[] = $t;
        }
        $clean = array_values(array_unique($clean));
        foreach ($clean as $txt) {
            $db->execute(
                "INSERT INTO character_experience (characterID, experience, mod) VALUES (:cid,:exp,2)",
                [':cid' => $characterID, ':exp' => $txt]
            );
        }
    }

    // 5) weapons replace-all (character_weapon)
    $db->execute("DELETE FROM character_weapon WHERE characterID = :cid", [':cid' => $characterID]);

    if ($primaryWeaponID > 0) {
        $ok = $db->fetch(
            "SELECT weaponID FROM weapon WHERE weaponID = :id AND COALESCE(\"primary\",1)=1",
            [':id' => $primaryWeaponID]
        );
        if ($ok) {
            $db->execute(
                "INSERT INTO character_weapon (characterID, weaponID, \"primary\") VALUES (:cid,:wid,1)",
                [':cid' => $characterID, ':wid' => $primaryWeaponID]
            );
        }
    }

    if ($secondaryWeaponID > 0) {
        $ok = $db->fetch(
            "SELECT weaponID FROM weapon WHERE weaponID = :id AND COALESCE(\"primary\",1)=0",
            [':id' => $secondaryWeaponID]
        );
        if ($ok) {
            $db->execute(
                "INSERT INTO character_weapon (characterID, weaponID, \"primary\") VALUES (:cid,:wid,0)",
                [':cid' => $characterID, ':wid' => $secondaryWeaponID]
            );
        }
    }

    // 6) inventory replace-all
    $db->execute("DELETE FROM character_inventory WHERE characterID = :cid", [':cid' => $characterID]);
    if (is_array($inventory)) {
        $map = [];
        foreach ($inventory as $it) {
            if (!is_array($it)) continue;

            $item = trim((string)($it['item'] ?? ''));
            if ($item === '') continue;

            $desc = trim((string)($it['description'] ?? ''));
            $amt  = (int)($it['amount'] ?? 1);
            if ($amt < 0) $amt = 0;

            if (str_len($item) > 120) $item = str_cut($item, 120);
            if (str_len($desc) > 500) $desc = str_cut($desc, 500);

            $key = str_lower($item) . '|' . str_lower($desc);

            // Merge duplicates by key (sum amounts)
            if (!isset($map[$key])) {
                $map[$key] = ['item' => $item, 'desc' => $desc, 'amt' => 0];
            }
            $map[$key]['amt'] += $amt;
        }

        foreach (array_values($map) as $row) {
            $db->execute(
                "INSERT INTO character_inventory (characterID, Item, Description, Amount)
                 VALUES (:cid,:item,:desc,:amt)",
                [':cid' => $characterID, ':item' => $row['item'], ':desc' => $row['desc'], ':amt' => $row['amt']]
            );
        }
    }

    // 7) domain cards replace-all (character_domain_card)
    $db->execute("DELETE FROM character_domain_card WHERE characterID = :cid", [':cid' => $characterID]);

    if (is_array($domainCards)) {
        // Normalize, unique, max 2
        $domainCards = array_values(array_unique(array_filter(array_map(
            fn($x) => trim((string)$x),
            $domainCards
        ), fn($x) => $x !== '')));

        if (count($domainCards) > 2) {
            throw new RuntimeException("Too many Domain cards selected (max 2).");
        }

        foreach ($domainCards as $file) {
            // expected: DomainID_Level_Name.jpg  (case-insensitive .jpg)
            if (!preg_match('/^(\d+)_(\d+)_(.+)\.jpg$/i', $file, $m)) {
                throw new RuntimeException("Invalid domain card filename: " . $file);
            }

            $did  = (int)$m[1];
            $lvl  = (int)$m[2];
            $n    = (string)$m[3];

            if ($did <= 0 || $lvl <= 0) {
                throw new RuntimeException("Invalid domain card values in: " . $file);
            }

            if (str_len($n) > 200) $n = str_cut($n, 200);

            $db->execute(
                "INSERT INTO character_domain_card (characterID, domainID, spellLevel, name)
                 VALUES (:cid, :did, :lvl, :name)",
                [
                    ':cid'  => $characterID,
                    ':did'  => $did,
                    ':lvl'  => $lvl,
                    ':name' => $n
                ]
            );
        }
    }

    $db->execute("COMMIT");
    json_out(['ok' => true, 'characterID' => $characterID]);

} catch (Throwable $e) {
    try { if (isset($db)) $db->execute("ROLLBACK"); } catch (Throwable $ignore) {}
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}