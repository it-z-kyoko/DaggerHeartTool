<?php
/* ============================================================
   FILE: /Login/api_login.php  (API ONLY)
   - POST JSON: { username, password }
   - Validates against user(userID, username, password)
   - Sets session on success: $_SESSION['userID'] + $_SESSION['auth']
   - Returns JSON
   ============================================================ */

declare(strict_types=1);
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',          // wichtig
  'httponly' => true,
  'samesite' => 'Lax',
  'secure' => false,      // lokal http
]);
session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);

function jsonResponse(int $status, array $payload): never {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function readRequestData(): array {
  $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
  if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
  }
  return $_POST ?? [];
}

set_exception_handler(function(Throwable $e) {
  error_log("[api_login] " . $e->getMessage() . "\n" . $e->getTraceAsString());
  jsonResponse(500, ['ok' => false, 'message' => 'Serverfehler: ' . $e->getMessage()]); // DEV message
});

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  jsonResponse(405, ['ok' => false, 'message' => 'Method not allowed']);
}

require_once __DIR__ . '/../Database/Database.php';

$dbFile = __DIR__ . '/../Database/Daggerheart.db';
if (!file_exists($dbFile)) {
  jsonResponse(500, ['ok' => false, 'message' => 'DB-Datei nicht gefunden unter: ' . $dbFile]);
}

$db = Database::getInstance($dbFile);

/**
 * WICHTIG:
 * Du hast bereits eine user-Tabelle mit:
 *   userID (INTEGER, AUTOINCREMENT), username, password, picture
 *
 * Daher NICHT mehr die alte Tabelle (username PRIMARY KEY) erzeugen!
 * Wir lassen optional ein "CREATE TABLE IF NOT EXISTS" drin, aber passend zu deinem Schema.
 */
$db->execute("
  CREATE TABLE IF NOT EXISTS user (
    userID   INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    picture  TEXT
  );
");

$data = readRequestData();
$username = isset($data['username']) ? trim((string)$data['username']) : '';
$password = isset($data['password']) ? (string)$data['password'] : '';

if ($username === '' || $password === '') {
  jsonResponse(400, ['ok' => false, 'message' => 'Bitte Benutzername und Passwort angeben.']);
}

$row = $db->fetch(
  "SELECT userID, username, password
   FROM user
   WHERE username = :u
   LIMIT 1",
  [':u' => $username]
);

// Uniform error message (don’t leak whether user exists)
if (
  !$row
  || !isset($row['password'])
  || !password_verify($password, (string)$row['password'])
) {
  usleep(120000); // 120ms
  jsonResponse(401, ['ok' => false, 'message' => 'Login fehlgeschlagen.']);
}

// Rehash if needed
if (password_needs_rehash((string)$row['password'], PASSWORD_DEFAULT)) {
  $newHash = password_hash($password, PASSWORD_DEFAULT);
  if ($newHash !== false) {
    $db->execute(
      "UPDATE user SET password = :p WHERE userID = :id",
      [':p' => $newHash, ':id' => (int)$row['userID']]
    );
  }
}

// Session (WICHTIG: userID setzen, damit creator.php nicht redirectet)
session_regenerate_id(true);

$_SESSION['userID'] = (int)$row['userID'];
$_SESSION['auth'] = [
  'userID' => (int)$row['userID'],   // <-- WICHTIG
  'username' => (string)$row['username'],
  'logged_in_at' => time(),
];

jsonResponse(200, [
  'ok' => true,
  'redirect' => '/Dashboard/index.php',
  'user' => [
    'userID' => (int)$row['userID'],
    'username' => (string)$row['username']
  ]
]);