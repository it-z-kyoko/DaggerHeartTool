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

function generate_campaign_code(int $len = 6): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
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

    // =========================
    // LIST
    // =========================
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
            LEFT JOIN user u ON u.userID = cp.userID
            LEFT JOIN campaign_character cc ON cc.campaignID = cp.campaignID
            LEFT JOIN character ch ON ch.characterID = cc.characterID
            WHERE cp.userID = :uid OR ch.userID = :uid
            GROUP BY cp.campaignID
            ORDER BY cp.campaignID DESC
            ",
            [':uid' => $uid]
        );

        json_out(['ok' => true, 'rows' => $rows]);
    }

    // =========================
    // JOIN BY CODE
    // =========================
    if ($action === 'join') {

        $code = trim((string)($_POST['code'] ?? ''));
        $characterID = (int)($_POST['characterID'] ?? 0);

        if ($code === '') json_out(['ok' => false, 'error' => 'Code fehlt.'], 400);
        if ($characterID <= 0) json_out(['ok' => false, 'error' => 'characterID fehlt.'], 400);

        $camp = $db->fetch(
            "SELECT campaignID, userID FROM campaign WHERE code = :c LIMIT 1",
            [':c' => $code]
        );

        if (!$camp) json_out(['ok' => false, 'error' => 'Ungültiger Code.'], 404);

        $cid = (int)$camp['campaignID'];
        $ownerId = (int)$camp['userID'];

        if ($ownerId === $uid) {
            json_out(['ok' => false, 'error' => 'Du bist Owner dieser Kampagne.'], 400);
        }

        $ch = $db->fetch(
            "SELECT characterID FROM character WHERE characterID = :chid AND userID = :uid LIMIT 1",
            [':chid' => $characterID, ':uid' => $uid]
        );

        if (!$ch) json_out(['ok' => false, 'error' => 'Character gehört nicht dir.'], 403);

        $exists = $db->fetch(
            "SELECT 1 FROM campaign_character WHERE campaignID = :cid AND characterID = :chid LIMIT 1",
            [':cid' => $cid, ':chid' => $characterID]
        );

        if (!$exists) {
            $db->execute(
                "INSERT INTO campaign_character (campaignID, characterID)
                 VALUES (:cid, :chid)",
                [':cid' => $cid, ':chid' => $characterID]
            );
        }

        json_out(['ok' => true, 'campaignID' => $cid]);
    }

    // =========================
    // SHARE CODE
    // =========================
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
            json_out(['ok' => true, 'code' => $existing]);
        }

        $code = '';
        for ($i = 0; $i < 30; $i++) {
            $try = generate_campaign_code();
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

        json_out(['ok' => true, 'code' => $code]);
    }

    json_out(['ok' => false, 'error' => 'Unknown action'], 400);

} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}