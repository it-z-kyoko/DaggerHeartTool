<?php
/* ============================================================
   FILE 2: /Login/api_login.php  (API ONLY)
   - POST JSON: { username, password }
   - Validates against user(username, passwordHash)
   - Sets session on success
   - Returns JSON
   ============================================================ */

declare(strict_types=1);

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

// Ensure table exists (optional)
$db->execute("
  CREATE TABLE IF NOT EXISTS user (
    username TEXT PRIMARY KEY,
    password TEXT NOT NULL
  );
");

$data = readRequestData();
$username = isset($data['username']) ? trim((string)$data['username']) : '';
$password = isset($data['password']) ? (string)$data['password'] : '';

if ($username === '' || $password === '') {
  jsonResponse(400, ['ok' => false, 'message' => 'Bitte Benutzername und Passwort angeben.']);
}

// Optional username rules (same as register, keeps consistency)
if (strlen($username) < 3 || strlen($username) > 32) {
  jsonResponse(400, ['ok' => false, 'message' => 'Benutzername muss 3–32 Zeichen lang sein.']);
}
if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
  jsonResponse(400, ['ok' => false, 'message' => 'Ungültiger Benutzername.']);
}

$row = $db->fetch(
  "SELECT username, password FROM user WHERE username = :u LIMIT 1",
  [':u' => $username]
);

// Uniform error message (don’t leak whether user exists)
if (!$row || !isset($row['password']) || !password_verify($password, (string)$row['password'])) {
  usleep(120000); // 120ms
  jsonResponse(401, ['ok' => false, 'message' => 'Login fehlgeschlagen.']);
}

// Rehash if needed
if (password_needs_rehash((string)$row['password'], PASSWORD_DEFAULT)) {
  $newHash = password_hash($password, PASSWORD_DEFAULT);
  if ($newHash !== false) {
    $db->execute(
      "UPDATE user SET password = :p WHERE username = :u",
      [':p' => $newHash, ':u' => $username]
    );
  }
}

// Session
session_regenerate_id(true);
$_SESSION['auth'] = [
  'username' => $row['username'],
  'logged_in_at' => time(),
];

jsonResponse(200, [
  'ok' => true,
  'redirect' => '/dashboard.php',
  'user' => ['username' => $row['username']]
]);
