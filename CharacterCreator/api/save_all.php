<?php
declare(strict_types=1);
session_start();

$root = dirname(__DIR__, 2);
require_once $root . '/Database/Database.php';

function json_out(array $p, int $s=200): void {
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
if ($userID === null) json_out(['ok'=>false,'error'=>'Not logged in'], 401);

try {
  $db = Database::getInstance($root . '/Database/Daggerheart.db');

  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '[]', true, 512, JSON_THROW_ON_ERROR);

  $basics = $data['basics'] ?? [];
  $traits = $data['traits'] ?? [];
  $def    = $data['defense'] ?? [];
  $experiences = $data['experiences'] ?? [];
  $gear = $data['gear'] ?? [];
  $inventory = $data['inventory'] ?? [];

  $name = trim((string)($basics['name'] ?? ''));
  $pronouns = trim((string)($basics['pronouns'] ?? ''));
  $level = (int)($basics['level'] ?? 1); if ($level < 1) $level = 1;

  $heritageID  = (int)($basics['heritageID'] ?? 0);
  $classID     = (int)($basics['classID'] ?? 0);
  $subclassID  = (int)($basics['subclassID'] ?? 0);
  $communityID = (int)($basics['communityID'] ?? 0);

  // ---- Validate minimum
  if ($name === '' || $pronouns === '' || $heritageID <= 0 || $classID <= 0 || $level <= 0) {
    json_out(['ok'=>false,'error'=>'Basics incomplete'], 400);
  }

  // ---- Validate trait distribution
  $strength  = (int)($traits['strength'] ?? 0);
  $agility   = (int)($traits['agility'] ?? 0);
  $finesse   = (int)($traits['finesse'] ?? 0);
  $instinct  = (int)($traits['instinct'] ?? 0);
  $presence  = (int)($traits['presence'] ?? 0);
  $knowledge = (int)($traits['knowledge'] ?? 0);
  $hp        = (int)($traits['hp'] ?? 0);

  $vals = [$strength,$agility,$finesse,$instinct,$presence,$knowledge];
  sort($vals);
  $pool = [-1,0,0,1,1,2];
  if ($vals !== $pool) json_out(['ok'=>false,'error'=>'Invalid trait distribution'], 400);

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
      ':uid' => $userID,
      ':name' => $name,
      ':pro' => $pronouns,
      ':lvl' => $level,
      ':hid' => $heritageID ?: null,
      ':clid' => $classID ?: null,
      ':scid' => $subclassID ?: null,
      ':comid' => $communityID ?: null,
      ':eva' => $evasion ?: null,
      ':arm' => $armor ?: null
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
      ':cid'=>$characterID, ':str'=>$strength, ':agi'=>$agility, ':fin'=>$finesse,
      ':ins'=>$instinct, ':pre'=>$presence, ':kno'=>$knowledge, ':hp'=>$hp
    ]
  );

  // 3) character_armor (active armor)
  if ($armorID > 0) {
    $db->execute(
      "INSERT INTO character_armor (characterID, armorID)
       VALUES (:cid,:aid)
       ON CONFLICT(characterID) DO UPDATE SET armorID=excluded.armorID",
      [':cid'=>$characterID, ':aid'=>$armorID]
    );
  }

  // 4) experiences replace-all
  $db->execute("DELETE FROM character_experience WHERE characterID = :cid", [':cid'=>$characterID]);
  if (is_array($experiences)) {
    $clean = [];
    foreach ($experiences as $x) {
      $t = trim((string)$x);
      if ($t === '') continue;
      if (mb_strlen($t) > 200) $t = mb_substr($t, 0, 200);
      $clean[] = $t;
    }
    $clean = array_values(array_unique($clean));
    foreach ($clean as $txt) {
      $db->execute(
        "INSERT INTO character_experience (characterID, experience, mod) VALUES (:cid,:exp,2)",
        [':cid'=>$characterID, ':exp'=>$txt]
      );
    }
  }

  // 5) weapons replace-all (character_weapon)  NOTE: column name "primary" is quoted
  $db->execute("DELETE FROM character_weapon WHERE characterID = :cid", [':cid'=>$characterID]);

  if ($primaryWeaponID > 0) {
    // ensure selected weapon is primary
    $ok = $db->fetch("SELECT weaponID FROM weapon WHERE weaponID = :id AND COALESCE(\"primary\",1)=1", [':id'=>$primaryWeaponID]);
    if ($ok) {
      $db->execute(
        "INSERT INTO character_weapon (characterID, weaponID, \"primary\") VALUES (:cid,:wid,1)",
        [':cid'=>$characterID, ':wid'=>$primaryWeaponID]
      );
    }
  }

  if ($secondaryWeaponID > 0) {
    // ensure selected weapon is secondary
    $ok = $db->fetch("SELECT weaponID FROM weapon WHERE weaponID = :id AND COALESCE(\"primary\",1)=0", [':id'=>$secondaryWeaponID]);
    if ($ok) {
      $db->execute(
        "INSERT INTO character_weapon (characterID, weaponID, \"primary\") VALUES (:cid,:wid,0)",
        [':cid'=>$characterID, ':wid'=>$secondaryWeaponID]
      );
    }
  }

  // 6) inventory replace-all
  $db->execute("DELETE FROM character_inventory WHERE characterID = :cid", [':cid'=>$characterID]);
  if (is_array($inventory)) {
    $map = [];
    foreach ($inventory as $it) {
      if (!is_array($it)) continue;
      $item = trim((string)($it['item'] ?? ''));
      if ($item === '') continue;
      $desc = trim((string)($it['description'] ?? ''));
      $amt  = (int)($it['amount'] ?? 1);
      if ($amt < 0) $amt = 0;

      if (mb_strlen($item) > 120) $item = mb_substr($item, 0, 120);
      if (mb_strlen($desc) > 500) $desc = mb_substr($desc, 0, 500);

      $key = mb_strtolower($item) . '|' . mb_strtolower($desc);
      $map[$key] = ['item'=>$item,'desc'=>$desc,'amt'=>$amt];
    }
    foreach (array_values($map) as $row) {
      $db->execute(
        "INSERT INTO character_inventory (characterID, Item, Description, Amount)
         VALUES (:cid,:item,:desc,:amt)",
        [':cid'=>$characterID, ':item'=>$row['item'], ':desc'=>$row['desc'], ':amt'=>$row['amt']]
      );
    }
  }

  // (Optional) gold saving is not specified as table in your request, so we don’t persist it here.

  $db->execute("COMMIT");

  json_out(['ok'=>true,'characterID'=>$characterID]);

} catch (Throwable $e) {
  try {
    if (isset($db)) $db->execute("ROLLBACK");
  } catch (Throwable $ignore) {}
  json_out(['ok'=>false,'error'=>$e->getMessage()], 500);
}