<?php
declare(strict_types=1);

/**
 * Handles POST actions for character_view.php
 * - saveTracker
 * - inv_list / inv_upsert / inv_delete
 * - roll_save / roll_history
 */

if (!function_exists('json_out')) {
  function json_out(array $payload, int $status = 200): void
  {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  return; // only handle POST
}

$action = (string)($_POST['action'] ?? '');
if ($action === '') {
  return;
}

/**
 * Resolve readonly flag for this request (comes from URL query string)
 */
$readonly = (int)($_GET['readonly'] ?? 0) === 1;

/**
 * Ensure we have $db (when included from character_view.php it exists already).
 * Make it robust in case someone hits this file directly.
 */
if (!isset($db) || !($db instanceof Database)) {
  // /CharacterSheet/partials -> /CharacterSheet -> project root (htdocs)
  $sheetRoot = dirname(__DIR__);       // .../CharacterSheet
  $appRoot   = dirname($sheetRoot);    // .../htdocs
  require_once $appRoot . '/Database/Database.php';

  $db = Database::getInstance($appRoot . '/Database/Daggerheart.db');
}

/**
 * Resolve current userId from session (more reliable than relying on parent scope)
 */
$userId = null;
if (isset($_SESSION['userID']) && is_numeric($_SESSION['userID'])) {
  $userId = (int)$_SESSION['userID'];
}

/**
 * Helper: ownership check
 */
$ownershipChecked = false;
$ownedCharacterId = null;

$checkOwnership = function (int $characterId) use (&$ownershipChecked, &$ownedCharacterId, $db, $userId): void {
  if ($characterId <= 0) {
    json_out(['ok' => false, 'error' => 'Invalid characterID'], 400);
  }
  if ($userId === null) {
    json_out(['ok' => false, 'error' => 'Not logged in'], 401);
  }

  // avoid repeating the same query in one request
  if ($ownershipChecked && $ownedCharacterId === $characterId) {
    return;
  }

  $row = $db->fetch(
    'SELECT characterID, userID FROM character WHERE characterID = :id LIMIT 1',
    [':id' => $characterId]
  );

  if (!$row) {
    json_out(['ok' => false, 'error' => 'Character not found'], 404);
  }
  if ((int)$row['userID'] !== $userId) {
    json_out(['ok' => false, 'error' => 'Forbidden'], 403);
  }

  $ownershipChecked = true;
  $ownedCharacterId = $characterId;
};

/**
 * Ensure rolls table exists (fear nullable, includes characterID)
 */
try {
  $db->execute("
    CREATE TABLE IF NOT EXISTS rolls (
      rollID INTEGER PRIMARY KEY AUTOINCREMENT,
      userID INTEGER NOT NULL,
      characterID INTEGER NOT NULL,
      dice   TEXT,
      total  INTEGER,
      fear   INTEGER NULL,
      FOREIGN KEY (userID) REFERENCES \"user\"(userID) ON DELETE CASCADE ON UPDATE CASCADE,
      FOREIGN KEY (characterID) REFERENCES \"character\"(characterID) ON DELETE CASCADE ON UPDATE CASCADE
    )
  ");
} catch (Throwable $e) {
  // ignore (non-fatal)
}

/**
 * Block writes when readonly
 */
$writeActions = ['saveTracker', 'inv_upsert', 'inv_delete', 'roll_save'];
if ($readonly && in_array($action, $writeActions, true)) {
  json_out(['ok' => false, 'error' => 'Readonly mode'], 403);
}

// -----------------------
// Trackers
// -----------------------
if ($action === 'saveTracker') {
  $characterId = (int)($_POST['characterID'] ?? 0);
  $track       = (string)($_POST['track'] ?? '');
  $value       = (int)($_POST['value'] ?? 0);

  $checkOwnership($characterId);

  $allowed = ['HP', 'Stress', 'Hope', 'Armor'];
  if (!in_array($track, $allowed, true)) json_out(['ok' => false, 'error' => 'Invalid track'], 400);

  if ($value < 0) $value = 0;
  if (in_array($track, ['Stress', 'Hope'], true) && $value > 6) $value = 6;
  if (in_array($track, ['HP', 'Armor'], true) && $value > 9) $value = 9;

  try {
    if (in_array($track, ['HP', 'Stress', 'Hope'], true)) {
      $db->execute("UPDATE character_stats SET {$track} = :v WHERE characterID = :id", [
        ':v' => $value,
        ':id' => $characterId
      ]);
    } else {
      // Armor tracker stored in character.armor (per your sheet)
      $db->execute("UPDATE \"character\" SET armor = :v WHERE characterID = :id", [
        ':v' => $value,
        ':id' => $characterId
      ]);
    }

    json_out(['ok' => true]);
  } catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
  }
}

// -----------------------
// Inventory CRUD
// -----------------------
require_once __DIR__ . '/character_view_data.php';

if ($action === 'inv_list') {
  $characterId = (int)($_POST['characterID'] ?? 0);
  $checkOwnership($characterId);

  try {
    json_out(['ok' => true, 'items' => load_inventory($db, $characterId)]);
  } catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
  }
}

if ($action === 'inv_upsert') {
  $characterId = (int)($_POST['characterID'] ?? 0);
  $itemId      = (int)($_POST['itemID'] ?? 0);

  $checkOwnership($characterId);

  // accept BOTH naming conventions (JS might send invName/invDesc/invAmt)
  $name = trim((string)($_POST['Item'] ?? $_POST['invName'] ?? ''));
  $desc = trim((string)($_POST['Description'] ?? $_POST['invDesc'] ?? ''));
  $amt  = (int)($_POST['Amount'] ?? $_POST['invAmt'] ?? 1);

  if ($amt < 0) $amt = 0;
  if ($amt > 9999) $amt = 9999;

  if ($name === '') json_out(['ok' => false, 'error' => 'Name is required'], 400);

  try {
    if ($itemId > 0) {
      $db->execute(
        "UPDATE character_inventory
         SET Item = :i, Description = :d, Amount = :a
         WHERE itemID = :iid AND characterID = :cid",
        [':i' => $name, ':d' => $desc, ':a' => $amt, ':iid' => $itemId, ':cid' => $characterId]
      );
    } else {
      $db->execute(
        "INSERT INTO character_inventory (characterID, Item, Description, Amount)
         VALUES (:cid, :i, :d, :a)",
        [':cid' => $characterId, ':i' => $name, ':d' => $desc, ':a' => $amt]
      );
    }

    json_out(['ok' => true, 'items' => load_inventory($db, $characterId)]);
  } catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
  }
}

if ($action === 'inv_delete') {
  $characterId = (int)($_POST['characterID'] ?? 0);
  $itemId      = (int)($_POST['itemID'] ?? 0);

  $checkOwnership($characterId);

  if ($itemId <= 0) json_out(['ok' => false, 'error' => 'Invalid itemID'], 400);

  try {
    $db->execute(
      "DELETE FROM character_inventory WHERE itemID = :iid AND characterID = :cid",
      [':iid' => $itemId, ':cid' => $characterId]
    );

    json_out(['ok' => true, 'items' => load_inventory($db, $characterId)]);
  } catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
  }
}

// -----------------------
// Roll save + history
// fear nullable:
//   1 => FEAR
//   0 => HOPE
//   NULL => neutral (Damage)
// -----------------------
if ($action === 'roll_save') {
  if ($userId === null) json_out(['ok' => false, 'error' => 'Not logged in'], 401);

  $characterId = (int)($_POST['characterID'] ?? 0);
  $checkOwnership($characterId);

  $dice  = trim((string)($_POST['dice'] ?? ''));
  $total = (int)($_POST['total'] ?? 0);

  $fear = null;
  if (array_key_exists('fear', $_POST) && $_POST['fear'] !== '' && $_POST['fear'] !== null) {
    $fearInt = (int)$_POST['fear'];
    $fear = ($fearInt === 1) ? 1 : 0;
  }

  if ($dice === '' || strlen($dice) > 200) json_out(['ok' => false, 'error' => 'Invalid dice string'], 400);

  try {
    $db->execute(
      "INSERT INTO rolls (userID, characterID, dice, total, fear)
       VALUES (:uid, :cid, :dice, :total, :fear)",
      [
        ':uid'   => $userId,
        ':cid'   => $characterId,
        ':dice'  => $dice,
        ':total' => $total,
        ':fear'  => $fear
      ]
    );

    $history = load_roll_history($db, $userId, $characterId, 10);
    json_out(['ok' => true, 'history' => $history]);
  } catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
  }
}

if ($action === 'roll_history') {
  if ($userId === null) json_out(['ok' => false, 'error' => 'Not logged in'], 401);

  $characterId = (int)($_POST['characterID'] ?? 0);
  $checkOwnership($characterId);

  json_out(['ok' => true, 'history' => load_roll_history($db, $userId, $characterId, 10)]);
}

json_out(['ok' => false, 'error' => 'Unknown action'], 400);