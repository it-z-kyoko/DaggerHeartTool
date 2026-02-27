<?php
declare(strict_types=1);

/**
 * All DB loader functions for character_view
 */

function load_character_profile(Database $db, int $characterId): ?array
{
    $sql = "
        SELECT
            c.characterID,
            c.name,
            c.pronouns,
            c.level,
            c.armor AS armor_tracker,
            ca.armorID,
            c.evasion AS evasion_value,
            c.heritageID,
            c.communityID,
            c.classID,
            c.subclassID,
            h.name  AS heritage_name,
            co.name AS community_name,
            cl.name AS class_name,
            sc.name AS subclass_name,
            cl.starting_evasion_score
        FROM \"character\" c
        LEFT JOIN heritage  h  ON h.heritageID   = c.heritageID
        LEFT JOIN community co ON co.communityID = c.communityID
        LEFT JOIN class     cl ON cl.classID     = c.classID
        LEFT JOIN subclass  sc ON sc.subclassID  = c.subclassID
        LEFT JOIN character_armor ca ON ca.characterID = c.characterID
        WHERE c.characterID = :id
        LIMIT 1
    ";
    return $db->fetch($sql, [':id' => $characterId]);
}

function load_character_stats(Database $db, int $characterId): array
{
    $row = $db->fetch("
        SELECT Agility, Strength, Finesse, Instinct, Presence, Knowledge, HP, Stress, Hope
        FROM character_stats
        WHERE characterID = :id
        LIMIT 1
    ", [':id' => $characterId]);

    $defaults = [
        'Agility'=>0,'Strength'=>0,'Finesse'=>0,'Instinct'=>0,'Presence'=>0,'Knowledge'=>0,
        'HP'=>0,'Stress'=>0,'Hope'=>0
    ];

    if (!$row) return $defaults;
    foreach ($defaults as $k => $_) $defaults[$k] = isset($row[$k]) ? (int)$row[$k] : 0;
    return $defaults;
}

function load_hope_feature_description(Database $db, int $classId): string
{
    if ($classId <= 0) return '';
    $row = $db->fetch("
        SELECT description, hope
        FROM feature
        WHERE classID = :cid AND hope = 3
        LIMIT 1
    ", [':cid'=>$classId]);

    if (!$row) return '';
    $hope = (int)($row['hope'] ?? 0);
    $desc = trim((string)($row['description'] ?? ''));
    if ($hope <= 0 || $desc === '') return '';
    return "Spend {$hope} HOPE {$desc}";
}

function load_character_experiences(Database $db, int $characterId): array
{
    $rows = $db->fetchAll("
        SELECT experienceID, characterID, experience, mod
        FROM character_experience
        WHERE characterID = :cid
        ORDER BY experienceID ASC
    ", [':cid'=>$characterId]);

    return is_array($rows) ? $rows : [];
}

function load_inventory(Database $db, int $characterId): array
{
    $rows = $db->fetchAll("
        SELECT itemID, characterID, Item, Description, Amount
        FROM character_inventory
        WHERE characterID = :cid
        ORDER BY itemID ASC
    ", [':cid'=>$characterId]);

    return is_array($rows) ? $rows : [];
}

function load_armor_by_id(Database $db, ?int $armorId): ?array
{
    if (!$armorId || $armorId <= 0) return null;
    return $db->fetch("
        SELECT armorID, name, major_threshold, severe_threshold, base_score, feature, min_level
        FROM armor
        WHERE armorID = :aid
        LIMIT 1
    ", [':aid'=>$armorId]);
}

function load_character_weapon(Database $db, int $characterId, int $primaryFlag): ?array
{
    return $db->fetch('
        SELECT
            cw.weaponID,
            w.name     AS weapon_name,
            w.trait    AS weapon_trait,
            w.range    AS weapon_range,
            w.damage   AS weapon_damage,
            w.feature  AS weapon_feature
        FROM character_weapon cw
        JOIN weapon w ON w.weaponID = cw.weaponID
        WHERE cw.characterID = :cid
          AND cw."primary" = :p
        ORDER BY COALESCE(cw.sortID, 0) ASC
        LIMIT 1
    ', [':cid'=>$characterId, ':p'=>$primaryFlag]);
}

function load_roll_history(Database $db, ?int $userId, ?int $characterId, int $limit = 10): array
{
    if (!$userId || $userId <= 0) return [];
    $limit = max(1, min(50, $limit));

    $where = "WHERE userID = :uid";
    $params = [':uid' => $userId];

    // If characterId is provided -> filter to character
    if ($characterId !== null && $characterId > 0) {
        $where .= " AND characterID = :cid";
        $params[':cid'] = $characterId;
    }

    try {
        $rows = $db->fetchAll("
            SELECT rollID, characterID, dice, total, fear
            FROM rolls
            {$where}
            ORDER BY rollID DESC
            LIMIT {$limit}
        ", $params);

        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        return [];
    }
}