<?php
// /CharacterCreator/api/get_domain_cards.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 2); // .../DaggerHeartTool
require_once $root . '/Database/Database.php';

function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {

    $db = Database::getInstance($root . '/Database/Daggerheart.db');

    $classID = (int)($_GET['classID'] ?? 0);
    $level   = (int)($_GET['level'] ?? 1);
    if ($level < 1) {
        $level = 1;
    }

    if ($classID <= 0) {
        json_out([
            'ok'      => true,
            'level'   => $level,
            'domains' => [],
            'cards'   => []
        ]);
    }

    // ------------------------------------------------------------
    // Domains der Klasse laden
    // ------------------------------------------------------------
    $rows = $db->fetchAll(
        "SELECT domainID
         FROM domain_class
         WHERE classID = :cid
         ORDER BY domainID",
        [':cid' => $classID]
    );

    $domains = [];
    foreach ($rows as $r) {
        if (isset($r['domainID']) && is_numeric($r['domainID'])) {
            $domains[] = (int)$r['domainID'];
        }
    }

    $domains = array_values(array_unique($domains));

    // ------------------------------------------------------------
    // Dateisystem-Ordner prüfen
    // ------------------------------------------------------------
    $webBase = '/img/Cards/Domains';
    $dirFs   = $root . $webBase;

    if (!is_dir($dirFs)) {
        json_out([
            'ok'    => false,
            'error' => 'Domains folder not found: ' . $dirFs
        ], 500);
    }

    $cards = [];

    foreach ($domains as $did) {

        // Format: DomainID_Level_Name.jpg
        $prefix = $did . '_' . $level . '_';

        $files = glob(
            $dirFs . '/' . $prefix . '*.{jpg,JPG,jpeg,JPEG,png,PNG,webp,WEBP}',
            GLOB_BRACE
        ) ?: [];

        foreach ($files as $path) {

            $filename = basename($path);

            $m = [];
            if (preg_match('/^(\d+)_(\d+)_(.+)\.(jpg|jpeg|png|webp)$/i', $filename, $m)) {

                $cards[] = [
                    'filename'   => $filename,

                    // ✅ absoluter Web-Pfad (wichtig!)
                    'src'        => $webBase . '/' . rawurlencode($filename),

                    'domainID'   => (int)$m[1],
                    'spellLevel' => (int)$m[2],
                    'name'       => $m[3],
                ];
            }
        }
    }

    // ------------------------------------------------------------
    // Sortierung
    // ------------------------------------------------------------
    usort($cards, function ($a, $b) {
        return [$a['domainID'], $a['spellLevel'], $a['filename']]
             <=> [$b['domainID'], $b['spellLevel'], $b['filename']];
    });

    json_out([
        'ok'      => true,
        'level'   => $level,
        'domains' => $domains,
        'cards'   => $cards
    ]);

} catch (Throwable $e) {

    json_out([
        'ok'    => false,
        'error' => $e->getMessage()
    ], 500);
}