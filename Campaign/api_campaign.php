<?php

declare(strict_types=1);

session_start();

$root = dirname(__DIR__);
require_once $root . '/Database/Database.php';

function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getCurrentUserId(): ?int
{
    if (isset($_SESSION['userID']) && is_numeric($_SESSION['userID'])) {
        return (int)$_SESSION['userID'];
    }
    return null;
}

/**
 * Generates a short campaign code like: DH-7K3P9Q
 */
function generate_campaign_code(int $len = 6): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no O/0, I/1
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'DH-' . $out;
}

$uid = getCurrentUserId();
if ($uid === null) {
    json_out(['ok' => false, 'error' => 'Nicht eingeloggt.'], 401);
}

$db = Database::getInstance($root . '/Database/Daggerheart.db');
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

try {

    // -----------------------------
    // LIST: Owner + Mitglied (über campaign_character -> character.userID)
    // plus memberCharacterID used for sheet redirect
    // -----------------------------
    if ($action === 'list') {
        $rows = $db->fetchAll(
            "
            SELECT
                cp.campaignID,
                cp.name,
                cp.description,
                cp.code,
                u.username AS ownerName,

                CASE WHEN cp.userID = :uid THEN 1 ELSE 0 END AS isOwner,

                MIN(CASE WHEN ch.userID = :uid THEN ch.characterID END) AS memberCharacterID,
                MIN(CASE WHEN ch.userID = :uid THEN ch.name END)        AS memberCharacterName,
                SUM(CASE WHEN ch.userID = :uid THEN 1 ELSE 0 END)       AS memberCharacterCount

            FROM campaign cp
            LEFT JOIN user u
                   ON u.userID = cp.userID
            LEFT JOIN campaign_character cc
                   ON cc.campaignID = cp.campaignID
            LEFT JOIN character ch
                   ON ch.characterID = cc.characterID

            WHERE cp.userID = :uid
               OR ch.userID = :uid

            GROUP BY cp.campaignID
            ORDER BY cp.campaignID DESC
            ",
            [':uid' => $uid]
        );

        json_out(['ok' => true, 'rows' => $rows]);
    }

    // -----------------------------
    // SHARE CODE (Owner only): generate/store campaign.code
    // If code exists and force=0 => return existing
    // -----------------------------
    if ($action === 'share_code') {
        $cid = (int)($_POST['campaignID'] ?? 0);
        $force = (int)($_POST['force'] ?? 0) === 1;

        if ($cid <= 0) json_out(['ok' => false, 'error' => 'campaignID fehlt.'], 400);

        $camp = $db->fetch(
            "SELECT campaignID, userID, code FROM campaign WHERE campaignID = :cid LIMIT 1",
            [':cid' => $cid]
        );
        if (!$camp) json_out(['ok' => false, 'error' => 'Kampagne nicht gefunden.'], 404);
        if ((int)$camp['userID'] !== $uid) json_out(['ok' => false, 'error' => 'Nicht erlaubt.'], 403);

        $existing = trim((string)($camp['code'] ?? ''));
        if ($existing !== '' && !$force) {
            json_out(['ok' => true, 'code' => $existing, 'regenerated' => false]);
        }

        $code = '';
        for ($i = 0; $i < 30; $i++) {
            $try = generate_campaign_code(6);
            $dup = $db->fetch("SELECT 1 FROM campaign WHERE code = :c LIMIT 1", [':c' => $try]);
            if (!$dup) {
                $code = $try;
                break;
            }
        }
        if ($code === '') {
            json_out(['ok' => false, 'error' => 'Code konnte nicht generiert werden.'], 500);
        }

        $db->execute(
            "UPDATE campaign SET code = :code WHERE campaignID = :cid",
            [':code' => $code, ':cid' => $cid]
        );

        json_out(['ok' => true, 'code' => $code, 'regenerated' => true]);
    }

    // -----------------------------
    // CREATE (Owner only)
    // -----------------------------
    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            json_out(['ok' => false, 'error' => 'Name darf nicht leer sein.'], 400);
        }

        $db->execute(
            "INSERT INTO campaign (name, description, userID) VALUES (:n, :d, :uid)",
            [':n' => $name, ':d' => $description, ':uid' => $uid]
        );

        json_out(['ok' => true]);
    }

    // -----------------------------
    // UPDATE (Owner only)
    // -----------------------------
    if ($action === 'update') {
        $cid = (int)($_POST['campaignID'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($cid <= 0) json_out(['ok' => false, 'error' => 'campaignID fehlt.'], 400);
        if ($name === '') json_out(['ok' => false, 'error' => 'Name darf nicht leer sein.'], 400);

        $camp = $db->fetch(
            "SELECT campaignID, userID FROM campaign WHERE campaignID = :cid LIMIT 1",
            [':cid' => $cid]
        );

        if (!$camp) json_out(['ok' => false, 'error' => 'Kampagne nicht gefunden.'], 404);
        if ((int)$camp['userID'] !== $uid) json_out(['ok' => false, 'error' => 'Nicht erlaubt.'], 403);

        $db->execute(
            "UPDATE campaign SET name = :n, description = :d WHERE campaignID = :cid",
            [':n' => $name, ':d' => $description, ':cid' => $cid]
        );

        json_out(['ok' => true]);
    }

    // -----------------------------
    // DELETE (Owner only)
    // -----------------------------
    if ($action === 'delete') {
        $cid = (int)($_POST['campaignID'] ?? 0);
        if ($cid <= 0) json_out(['ok' => false, 'error' => 'campaignID fehlt.'], 400);

        $camp = $db->fetch(
            "SELECT campaignID, userID FROM campaign WHERE campaignID = :cid LIMIT 1",
            [':cid' => $cid]
        );

        if (!$camp) json_out(['ok' => false, 'error' => 'Kampagne nicht gefunden.'], 404);
        if ((int)$camp['userID'] !== $uid) json_out(['ok' => false, 'error' => 'Nicht erlaubt.'], 403);

        $db->execute("DELETE FROM campaign_character WHERE campaignID = :cid", [':cid' => $cid]);
        $db->execute("DELETE FROM campaign WHERE campaignID = :cid", [':cid' => $cid]);

        json_out(['ok' => true]);
    }

    // -----------------------------
    // LEAVE (Mitglied): remove only mappings for user's characters
    // -----------------------------
    if ($action === 'leave') {
        $cid = (int)($_POST['campaignID'] ?? 0);
        if ($cid <= 0) json_out(['ok' => false, 'error' => 'campaignID fehlt.'], 400);

        $camp = $db->fetch(
            "SELECT campaignID, userID FROM campaign WHERE campaignID = :cid LIMIT 1",
            [':cid' => $cid]
        );

        if (!$camp) json_out(['ok' => false, 'error' => 'Kampagne nicht gefunden.'], 404);
        if ((int)$camp['userID'] === $uid) {
            json_out(['ok' => false, 'error' => 'Owner kann die eigene Kampagne nicht verlassen.'], 400);
        }

        $db->execute(
            "
            DELETE FROM campaign_character
            WHERE campaignID = :cid
              AND characterID IN (SELECT characterID FROM character WHERE userID = :uid)
            ",
            [':cid' => $cid, ':uid' => $uid]
        );

        json_out(['ok' => true]);
    }

    json_out(['ok' => false, 'error' => 'Unknown action'], 400);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
