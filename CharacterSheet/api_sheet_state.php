<?php
declare(strict_types=1);
session_start();

$root = dirname(__DIR__);
require_once $root . '/Database/Database.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $p, int $code=200): void {
  http_response_code($code);
  echo json_encode($p, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!isset($_SESSION['userID'])) {
  json_out(['ok' => false, 'error' => 'Not logged in'], 401);
}

$uid = (int)$_SESSION['userID'];
$characterID = (int)($_GET['characterID'] ?? 0);
if ($characterID <= 0) json_out(['ok'=>false,'error'=>'characterID missing'], 400);

$db = Database::getInstance($root . '/Database/Daggerheart.db');

/* Ownership check (adjust if you have campaign sharing etc.) */
$ch = $db->fetch(
  "SELECT characterID, userID, name, evasion, armor, level
   FROM character
   WHERE characterID = :id",
  [':id' => $characterID]
);
if (!$ch) json_out(['ok'=>false,'error'=>'Character not found'], 404);
if ((int)$ch['userID'] !== $uid) json_out(['ok'=>false,'error'=>'Forbidden'], 403);

/* Example: pull the live “resources” from your tables.
   Adjust these queries to your real schema/columns. */
$stats = $db->fetch(
  "SELECT hope, stress, hit_points_current, hit_points_max
   FROM character_stats
   WHERE characterID = :id",
  [':id' => $characterID]
);

/* Return what the UI needs */
json_out([
  'ok' => true,
  'character' => [
    'characterID' => (int)$ch['characterID'],
    'name'        => (string)$ch['name'],
    'evasion'     => (int)$ch['evasion'],
    'armor'       => (int)$ch['armor'],
    'level'       => (int)$ch['level'],
  ],
  'stats' => [
    'hope' => isset($stats['hope']) ? (int)$stats['hope'] : null,
    'stress' => isset($stats['stress']) ? (int)$stats['stress'] : null,
    'hp_cur' => isset($stats['hit_points_current']) ? (int)$stats['hit_points_current'] : null,
    'hp_max' => isset($stats['hit_points_max']) ? (int)$stats['hit_points_max'] : null,
  ],
]);