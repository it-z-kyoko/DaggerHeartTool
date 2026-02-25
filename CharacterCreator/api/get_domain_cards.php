<?php
// CharacterCreator/api/get_domain_cards.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 2); // .../DaggerHeartTool
require_once $root . '/Database/Database.php';

function json_out(array $p, int $code = 200): void {
  http_response_code($code);
  echo json_encode($p, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $db = Database::getInstance($root . '/Database/Daggerheart.db');

  $classID = (int)($_GET['classID'] ?? 0);
  $level   = (int)($_GET['level'] ?? 1);
  if ($level < 1) $level = 1;

  if ($classID <= 0) {
    json_out(['ok' => true, 'level' => $level, 'domains' => [], 'cards' => []]);
  }

  // ---- domains for class (expect 2)
  $rows = $db->fetchAll(
    "SELECT domainID
     FROM domain_class
     WHERE classID = :cid
     ORDER BY domainID",
    [':cid' => $classID]
  );

  $domains = [];
  foreach ($rows as $r) {
    if (isset($r['domainID']) && is_numeric($r['domainID'])) $domains[] = (int)$r['domainID'];
  }
  $domains = array_values(array_unique($domains));

  // ---- scan folder
  $dirFs = $root . '/img/Cards/Domains';
  if (!is_dir($dirFs)) {
    json_out(['ok' => false, 'error' => 'Domains folder not found: ' . $dirFs], 500);
  }

  $cards = [];
  foreach ($domains as $did) {
    // prefix: DomainID_Level_
    $prefix = $did . '_' . $level . '_';

    foreach (glob($dirFs . '/' . $prefix . '*.jpg') ?: [] as $path) {
      $filename = basename($path); // e.g. 5_1_Song_of_Rest.jpg

      $m = [];
      if (preg_match('/^(\d+)_(\d+)_(.+)\.jpg$/i', $filename, $m)) {
        $cards[] = [
          'filename'   => $filename,

          // ✅ WICHTIG: relativ zur Seite /CharacterCreator/ -> ../img/... (nicht ././img/...)
          'src'        => '../img/Cards/Domains/' . rawurlencode($filename),

          'domainID'   => (int)$m[1],
          'spellLevel' => (int)$m[2],
          'name'       => $m[3],
        ];
      }
    }
  }

  usort($cards, function($a, $b) {
    return [$a['domainID'], $a['spellLevel'], $a['filename']]
       <=> [$b['domainID'], $b['spellLevel'], $b['filename']];
  });

  json_out(['ok' => true, 'level' => $level, 'domains' => $domains, 'cards' => $cards]);
} catch (Throwable $e) {
  json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}