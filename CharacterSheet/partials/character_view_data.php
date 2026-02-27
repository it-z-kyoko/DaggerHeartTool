<?php
declare(strict_types=1);

/**
 * All DB loader functions for character_view
 * IMPORTANT:
 * - Keep ALL load_* functions here (only once), to avoid redeclare errors.
 */

if (!function_exists('load_character_profile')) {
  function load_character_profile(Database $db, int $characterId): ?array
  {
    $sql = "
      SELECT
        c.characterID,
        c.userID,
        c.name,
        c.pronouns,
        c.classID,
        c.subclassID,
        c.heritageID,
        c.communityID,
        c.armor AS armorID,
        c.armor AS armor_tracker,
        c.Level AS level,

        h.name  AS heritage_name,
        co.name AS community_name,
        cl.name AS class_name,
        sc.name AS subclass_name,

        cl.starting_evasion_score AS starting_evasion_score
      FROM character c
      LEFT JOIN heritage  h  ON h.heritageID   = c.heritageID
      LEFT JOIN community co ON co.communityID = c.communityID
      LEFT JOIN class     cl ON cl.classID     = c.classID
      LEFT JOIN subclass  sc ON sc.subclassID  = c.subclassID
      WHERE c.characterID = :id
      LIMIT 1
    ";
    return $db->fetch($sql, [':id' => $characterId]);
  }
}

if (!function_exists('load_character_stats')) {
  function load_character_stats(Database $db, int $characterId): array
  {
    $row = $db->fetch("
      SELECT Agility, Strength, Finesse, Instinct, Presence, Knowledge, HP, Stress, Hope
      FROM character_stats
      WHERE characterID = :id
      LIMIT 1
    ", [':id' => $characterId]);

    $defaults = [
      'Agility' => 0,
      'Strength' => 0,
      'Finesse' => 0,
      'Instinct' => 0,
      'Presence' => 0,
      'Knowledge' => 0,
      'HP' => 0,
      'Stress' => 0,
      'Hope' => 0
    ];

    if (!$row) return $defaults;

    foreach ($defaults as $k => $_) {
      $defaults[$k] = (int)($row[$k] ?? 0);
    }
    return $defaults;
  }
}

if (!function_exists('load_hope_feature_description')) {
  function load_hope_feature_description(Database $db, int $classId): string
  {
    if ($classId <= 0) return '';

    $row = $db->fetch("
      SELECT description, hope
      FROM feature
      WHERE classID = :cid AND hope = 3
      LIMIT 1
    ", [':cid' => $classId]);

    if (!$row) return '';
    $hope = (int)($row['hope'] ?? 0);
    $desc = trim((string)($row['description'] ?? ''));
    if ($desc === '') return '';

    // Schreibweise wie vorher in deinem character_view.php:
    // "Spend 3 <desc>"
    return "Spend {$hope} {$desc}";
  }
}

if (!function_exists('load_character_experiences')) {
  function load_character_experiences(Database $db, int $characterId): array
  {
    $rows = $db->fetchAll("
      SELECT experienceID, experience, mod
      FROM character_experience
      WHERE characterID = :cid
      ORDER BY experienceID ASC
    ", [':cid' => $characterId]);

    return is_array($rows) ? $rows : [];
  }
}

if (!function_exists('load_inventory')) {
  function load_inventory(Database $db, int $characterId): array
  {
    $rows = $db->fetchAll("
      SELECT itemID, Item, Description, Amount
      FROM character_inventory
      WHERE characterID = :cid
      ORDER BY itemID DESC
    ", [':cid' => $characterId]);

    return is_array($rows) ? $rows : [];
  }
}

if (!function_exists('load_armor_by_id')) {
  function load_armor_by_id(Database $db, int $armorId): ?array
  {
    if ($armorId <= 0) return null;

    return $db->fetch("
      SELECT armorID, name, base_score, major_threshold, severe_threshold, feature
      FROM armor
      WHERE armorID = :aid
      LIMIT 1
    ", [':aid' => $armorId]);
  }
}

if (!function_exists('load_character_weapon')) {
  function load_character_weapon(Database $db, int $characterId, int $primaryFlag): ?array
  {
    return $db->fetch('
      SELECT
        cw.weaponID,
        cw."primary",
        w.name    AS weapon_name,
        w.trait   AS weapon_trait,
        w.range   AS weapon_range,
        w.damage  AS weapon_damage,
        w.feature AS weapon_feature
      FROM character_weapon cw
      JOIN weapon w ON w.weaponID = cw.weaponID
      WHERE cw.characterID = :cid
        AND cw."primary" = :p
      LIMIT 1
    ', [':cid' => $characterId, ':p' => $primaryFlag]);
  }
}

if (!function_exists('load_roll_history')) {
  function load_roll_history(Database $db, int $userId, int $characterId, int $limit = 10): array
  {
    if ($userId <= 0) return [];
    $limit = max(1, min(50, $limit));

    $rows = $db->fetchAll("
      SELECT rollID, dice, total, fear
      FROM rolls
      WHERE userID = :uid
        AND characterID = :cid
      ORDER BY rollID DESC
      LIMIT {$limit}
    ", [':uid' => $userId, ':cid' => $characterId]);

    return is_array($rows) ? $rows : [];
  }
}