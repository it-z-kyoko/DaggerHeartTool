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

$uid = getCurrentUserId();
if ($uid === null) {
    json_out(['ok' => false, 'error' => 'Nicht eingeloggt.'], 401);
}

$db = Database::getInstance($root . '/Database/Daggerheart.db');
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

try {

    // ---------------------------------------------------------
    // rolls_list (Owner only): alle Rolls für Kampagnen-Charaktere
    // ---------------------------------------------------------
    if ($action === 'rolls_list') {
        $cid = (int)($_GET['campaignID'] ?? $_POST['campaignID'] ?? 0);
        $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? 50);
        $limit = max(1, min(200, $limit));

        if ($cid <= 0) json_out(['ok' => false, 'error' => 'campaignID fehlt.'], 400);

        // Owner Check
        $camp = $db->fetch(
            "SELECT campaignID, userID
             FROM campaign
             WHERE campaignID = :cid
             LIMIT 1",
            [':cid' => $cid]
        );
        if (!$camp) json_out(['ok' => false, 'error' => 'Kampagne nicht gefunden.'], 404);
        if ((int)$camp['userID'] !== $uid) json_out(['ok' => false, 'error' => 'Nicht erlaubt.'], 403);

        // Rolls laden (neueste zuerst)
        $rows = $db->fetchAll(
            "SELECT
                r.rollID,
                r.characterID,
                ch.name AS characterName,
                r.dice,
                r.total,
                r.fear
             FROM rolls r
             JOIN campaign_character cc ON cc.characterID = r.characterID
             JOIN character ch ON ch.characterID = r.characterID
             WHERE cc.campaignID = :cid
             ORDER BY r.rollID DESC
             LIMIT {$limit}",
            [':cid' => $cid]
        );

        if (!is_array($rows)) $rows = [];

        json_out(['ok' => true, 'rows' => $rows]);
    }

    json_out(['ok' => false, 'error' => 'Unknown action'], 400);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
