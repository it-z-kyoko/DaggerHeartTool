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
if (getCurrentUserId() === null) json_out(['ok'=>false,'error'=>'Not logged in'], 401);

try {
  $db = Database::getInstance($root . '/Database/Daggerheart.db');
  $classID = (int)($_GET['classID'] ?? 0);
  if ($classID <= 0) json_out(['ok'=>true,'row'=>null]);

  $row = $db->fetch(
    "SELECT
        c.classID,
        c.starting_evasion_score,
        c.starting_hit_point,
        (
          SELECT f.description
          FROM feature f
          WHERE f.classID = c.classID
            AND COALESCE(f.hope, 0) > 0
          ORDER BY COALESCE(f.hope, 0) DESC, rowid ASC
          LIMIT 1
        ) AS hope_feature
     FROM class c
     WHERE c.classID = :cid",
    [':cid'=>$classID]
  );

  json_out(['ok'=>true,'row'=>$row]);
} catch (Throwable $e) {
  json_out(['ok'=>false,'error'=>$e->getMessage()], 500);
}