<?php
declare(strict_types=1);

session_start();

$root = dirname(__DIR__); // Projekt-Root
require_once $root . '/Database/Database.php';

function json_out(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getCurrentUserId(): ?int {
    if (isset($_SESSION['userID']) && is_numeric($_SESSION['userID'])) {
        return (int)$_SESSION['userID'];
    }
    return null;
}

$uid = getCurrentUserId();
if ($uid === null) {
    json_out(['ok' => false, 'error' => 'Nicht eingeloggt.'], 401);
}

// DB init
$dbFile = $root . '/Database/Daggerheart.db';
$db = Database::getInstance($dbFile);

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

try {
    // Optional: Tabellen absichern (falls noch nicht existieren)
    // Du hast sie laut ERD schon, aber das schadet nicht.
    $db->execute("
        CREATE TABLE IF NOT EXISTS campaign (
            campaignID INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            userID INTEGER NOT NULL,
            FOREIGN KEY (userID) REFERENCES user(userID) ON DELETE CASCADE
        );
    ");

    $db->execute("
        CREATE TABLE IF NOT EXISTS user_campaign (
            userID INTEGER NOT NULL,
            campaignID INTEGER NOT NULL,
            PRIMARY KEY (userID, campaignID),
            FOREIGN KEY (userID) REFERENCES user(userID) ON DELETE CASCADE,
            FOREIGN KEY (campaignID) REFERENCES campaign(campaignID) ON DELETE CASCADE
        );
    ");

    if ($action === 'list') {
        // Zeige Kampagnen, in denen der User Mitglied ist (oder Owner)
        $rows = $db->fetchAll(
            "SELECT
                c.campaignID,
                c.name,
                c.description,
                c.userID AS ownerUserID,
                u.username AS ownerName,
                CASE WHEN c.userID = :uid THEN 1 ELSE 0 END AS isOwner
             FROM user_campaign uc
             JOIN campaign c ON c.campaignID = uc.campaignID
             JOIN user u ON u.userID = c.userID
             WHERE uc.userID = :uid
             ORDER BY c.name COLLATE NOCASE",
            [':uid' => $uid]
        );

        json_out(['ok' => true, 'rows' => $rows]);
    }

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            json_out(['ok' => false, 'error' => 'Name darf nicht leer sein.'], 422);
        }
        if (mb_strlen($name) > 80) {
            json_out(['ok' => false, 'error' => 'Name ist zu lang (max 80).'], 422);
        }
        if (mb_strlen($desc) > 2000) {
            json_out(['ok' => false, 'error' => 'Beschreibung ist zu lang (max 2000).'], 422);
        }

        $db->begin();

        $db->execute(
            "INSERT INTO campaign (name, description, userID)
             VALUES (:name, :desc, :uid)",
            [':name' => $name, ':desc' => $desc, ':uid' => $uid]
        );
        $newId = (int)$db->lastInsertId();

        // Owner automatisch als Member hinzufügen
        $db->execute(
            "INSERT OR IGNORE INTO user_campaign (userID, campaignID)
             VALUES (:uid, :cid)",
            [':uid' => $uid, ':cid' => $newId]
        );

        $db->commit();

        json_out(['ok' => true, 'campaignID' => $newId]);
    }

    if ($action === 'update') {
        $cid  = (int)($_POST['campaignID'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));

        if ($cid <= 0) json_out(['ok' => false, 'error' => 'campaignID ungültig.'], 422);
        if ($name === '') json_out(['ok' => false, 'error' => 'Name darf nicht leer sein.'], 422);

        $camp = $db->fetch(
            "SELECT campaignID, userID FROM campaign WHERE campaignID = :cid",
            [':cid' => $cid]
        );
        if (!$camp) json_out(['ok' => false, 'error' => 'Kampagne nicht gefunden.'], 404);
        if ((int)$camp['userID'] !== $uid) {
            json_out(['ok' => false, 'error' => 'Nur der Owner darf bearbeiten.'], 403);
        }

        $db->execute(
            "UPDATE campaign
             SET name = :name, description = :desc
             WHERE campaignID = :cid",
            [':name' => $name, ':desc' => $desc, ':cid' => $cid]
        );

        json_out(['ok' => true]);
    }

    if ($action === 'delete') {
        $cid = (int)($_POST['campaignID'] ?? 0);
        if ($cid <= 0) json_out(['ok' => false, 'error' => 'campaignID ungültig.'], 422);

        $camp = $db->fetch(
            "SELECT campaignID, userID FROM campaign WHERE campaignID = :cid",
            [':cid' => $cid]
        );
        if (!$camp) json_out(['ok' => false, 'error' => 'Kampagne nicht gefunden.'], 404);
        if ((int)$camp['userID'] !== $uid) {
            json_out(['ok' => false, 'error' => 'Nur der Owner darf löschen.'], 403);
        }

        $db->begin();
        $db->execute("DELETE FROM user_campaign WHERE campaignID = :cid", [':cid' => $cid]);
        $db->execute("DELETE FROM campaign WHERE campaignID = :cid", [':cid' => $cid]);
        $db->commit();

        json_out(['ok' => true]);
    }

    if ($action === 'leave') {
        $cid = (int)($_POST['campaignID'] ?? 0);
        if ($cid <= 0) json_out(['ok' => false, 'error' => 'campaignID ungültig.'], 422);

        $camp = $db->fetch(
            "SELECT campaignID, userID FROM campaign WHERE campaignID = :cid",
            [':cid' => $cid]
        );
        if (!$camp) json_out(['ok' => false, 'error' => 'Kampagne nicht gefunden.'], 404);

        // Owner darf nicht "leave" (sonst verwaist es)
        if ((int)$camp['userID'] === $uid) {
            json_out(['ok' => false, 'error' => 'Owner kann Kampagne nicht verlassen (nur löschen oder Owner-Wechsel einbauen).'], 403);
        }

        $db->execute(
            "DELETE FROM user_campaign WHERE userID = :uid AND campaignID = :cid",
            [':uid' => $uid, ':cid' => $cid]
        );

        json_out(['ok' => true]);
    }

    json_out(['ok' => false, 'error' => 'Unbekannte action.'], 400);

} catch (Throwable $e) {
    if ($db->pdo()->inTransaction()) {
        $db->rollBack();
    }
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}