<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);   // geht von partials → Dashboard → ROOT

require_once $root . '/Database/Database.php';

$dbFile = $root . '/Database/Daggerheart.db';

$db = Database::getInstance($dbFile);