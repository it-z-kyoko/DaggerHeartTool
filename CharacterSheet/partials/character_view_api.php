<?php
declare(strict_types=1);

/**
 * Handles POST actions for character_view.php
 * - saveTracker
 * - inv_list / inv_upsert / inv_delete
 * - roll_save / roll_history
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

$action = (string)($_POST['action'] ?? '');

if ($action === '') return;

// Ensure rolls table exists (fear nullable!)
try {
    $db->execute("
        CREATE TABLE IF NOT EXISTS rolls (
            rollID INTEGER PRIMARY KEY AUTOINCREMENT,
            userID INTEGER,
            dice   TEXT,
            total  INTEGER,
            fear   INTEGER NULL,
            FOREIGN KEY (userID) REFERENCES user(userID)
        )
    ");
} catch (Throwable $e) {
    // ignore
}

// -----------------------
// Trackers
// -----------------------
if ($action === 'saveTracker') {
    $characterId = (int)($_POST['characterID'] ?? 0);
    $track       = (string)($_POST['track'] ?? '');
    $value       = (int)($_POST['value'] ?? 0);

    if ($characterId <= 0) json_out(['ok' => false, 'error' => 'Invalid characterID'], 400);

    $allowed = ['HP', 'Stress', 'Hope', 'Armor'];
    if (!in_array($track, $allowed, true)) json_out(['ok' => false, 'error' => 'Invalid track'], 400);

    if ($value < 0) $value = 0;
    if (in_array($track, ['Stress','Hope'], true) && $value > 6) $value = 6;
    if (in_array($track, ['HP','Armor'], true) && $value > 9) $value = 9;

    try {
        if (in_array($track, ['HP','Stress','Hope'], true)) {
            $db->execute("UPDATE character_stats SET {$track} = :v WHERE characterID = :id", [':v'=>$value, ':id'=>$characterId]);
        } else {
            $db->execute("UPDATE \"character\" SET armor = :v WHERE characterID = :id", [':v'=>$value, ':id'=>$characterId]);
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
    if ($characterId <= 0) json_out(['ok' => false, 'error' => 'Invalid characterID'], 400);

    try {
        json_out(['ok'=>true, 'items'=> load_inventory($db, $characterId)]);
    } catch (Throwable $e) {
        json_out(['ok'=>false,'error'=>$e->getMessage()], 500);
    }
}

if ($action === 'inv_upsert') {
    $characterId = (int)($_POST['characterID'] ?? 0);
    $itemId      = (int)($_POST['itemID'] ?? 0);

    if ($characterId <= 0) json_out(['ok'=>false,'error'=>'Invalid characterID'], 400);

    $name = trim((string)($_POST['Item'] ?? ''));
    $desc = trim((string)($_POST['Description'] ?? ''));
    $amt  = (int)($_POST['Amount'] ?? 1);
    if ($amt < 0) $amt = 0;
    if ($amt > 9999) $amt = 9999;

    if ($name === '') json_out(['ok'=>false,'error'=>'Name is required'], 400);

    try {
        if ($itemId > 0) {
            $db->execute(
                "UPDATE character_inventory SET Item=:i, Description=:d, Amount=:a WHERE itemID=:iid AND characterID=:cid",
                [':i'=>$name, ':d'=>$desc, ':a'=>$amt, ':iid'=>$itemId, ':cid'=>$characterId]
            );
        } else {
            $db->execute(
                "INSERT INTO character_inventory (characterID, Item, Description, Amount) VALUES (:cid,:i,:d,:a)",
                [':cid'=>$characterId, ':i'=>$name, ':d'=>$desc, ':a'=>$amt]
            );
        }

        json_out(['ok'=>true, 'items'=> load_inventory($db, $characterId)]);
    } catch (Throwable $e) {
        json_out(['ok'=>false,'error'=>$e->getMessage()], 500);
    }
}

if ($action === 'inv_delete') {
    $characterId = (int)($_POST['characterID'] ?? 0);
    $itemId      = (int)($_POST['itemID'] ?? 0);

    if ($characterId <= 0 || $itemId <= 0) json_out(['ok'=>false,'error'=>'Invalid parameters'], 400);

    try {
        $db->execute("DELETE FROM character_inventory WHERE itemID=:iid AND characterID=:cid", [':iid'=>$itemId, ':cid'=>$characterId]);
        json_out(['ok'=>true, 'items'=> load_inventory($db, $characterId)]);
    } catch (Throwable $e) {
        json_out(['ok'=>false,'error'=>$e->getMessage()], 500);
    }
}

// -----------------------
// Roll save + history
// fear is nullable:
//   1 => FEAR (blue)
//   0 => HOPE (gold)
//   NULL => neutral roll (Damage)
// -----------------------
if ($action === 'roll_save') {
    global $userId;
    if ($userId === null) json_out(['ok'=>false,'error'=>'Not logged in'], 401);

    $characterId = (int)($_POST['characterID'] ?? 0);
    $dice  = trim((string)($_POST['dice'] ?? ''));
    $total = (int)($_POST['total'] ?? 0);

    // fear can be NULL (damage) OR 0/1 (duality)
    $fear = null;
    if (array_key_exists('fear', $_POST) && $_POST['fear'] !== '' && $_POST['fear'] !== null) {
        $fearInt = (int)$_POST['fear'];
        $fear = ($fearInt === 1) ? 1 : 0;
    }

    if ($characterId <= 0) json_out(['ok'=>false,'error'=>'Invalid characterID'], 400);
    if ($dice === '' || strlen($dice) > 200) json_out(['ok'=>false,'error'=>'Invalid dice string'], 400);

    try {
        $db->execute(
            "INSERT INTO rolls (userID, characterID, dice, total, fear)
             VALUES (:uid, :cid, :dice, :total, :fear)",
            [
                ':uid'  => $userId,
                ':cid'  => $characterId,
                ':dice' => $dice,
                ':total'=> $total,
                ':fear' => $fear
            ]
        );

        // Return latest 10 for THIS character (recommended)
        $history = load_roll_history($db, $userId, $characterId, 10);
        json_out(['ok'=>true,'history'=>$history]);
    } catch (Throwable $e) {
        json_out(['ok'=>false,'error'=>$e->getMessage()], 500);
    }
}

if ($action === 'roll_history') {
    global $userId;
    if ($userId === null) json_out(['ok'=>false,'error'=>'Not logged in'], 401);

    $characterId = (int)($_POST['characterID'] ?? 0);
    if ($characterId <= 0) json_out(['ok'=>false,'error'=>'Invalid characterID'], 400);

    json_out(['ok'=>true, 'history'=> load_roll_history($db, $userId, $characterId, 10)]);
}

json_out(['ok'=>false,'error'=>'Unknown action'], 400);