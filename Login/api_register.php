<?php
declare(strict_types=1);

session_start();

/**
 * DEV DEBUG (remove later):
 * Shows errors as JSON and logs them.
 */
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
  error_log("[api_register] " . $e->getMessage() . "\n" . $e->getTraceAsString());
  jsonResponse(500, [
    'ok' => false,
    'message' => 'Serverfehler: ' . $e->getMessage(), // DEV: shows the real reason
  ]);
});

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  jsonResponse(405, ['ok' => false, 'message' => 'Method not allowed']);
}

require_once __DIR__ . '/../Database/Database.php';

/**
 * IMPORTANT:
 * Your Database class throws if the DB file does not exist.
 * So ensure the path is correct AND the file exists.
 */
$dbFile = __DIR__ . '/../Database/Daggerheart.db';

// Helpful: return path in error if missing
if (!file_exists($dbFile)) {
  jsonResponse(500, [
    'ok' => false,
    'message' => 'DB-Datei nicht gefunden unter: ' . $dbFile,
  ]);
}

$db = Database::getInstance($dbFile);

// If you want to auto-create the user table (fine for dev):
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

if (strlen($username) < 3 || strlen($username) > 32) {
  jsonResponse(400, ['ok' => false, 'message' => 'Benutzername muss 3–32 Zeichen lang sein.']);
}
if (strlen($password) < 8) {
  jsonResponse(400, ['ok' => false, 'message' => 'Passwort muss mindestens 8 Zeichen lang sein.']);
}
if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
  jsonResponse(400, ['ok' => false, 'message' => 'Benutzername darf nur Buchstaben, Zahlen und _ . - enthalten.']);
}

$existing = $db->fetch(
  "SELECT username FROM user WHERE username = :u LIMIT 1",
  [':u' => $username]
);
if ($existing) {
  jsonResponse(409, ['ok' => false, 'message' => 'Benutzername ist bereits vergeben.']);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
  jsonResponse(500, ['ok' => false, 'message' => 'Serverfehler beim Passwort-Hash.']);
}

$db->execute(
  "INSERT INTO user (username, password) VALUES (:u, :p)",
  [':u' => $username, ':p' => $hash]
);

// Optional: auto-login
session_regenerate_id(true);
$_SESSION['auth'] = ['username' => $username, 'logged_in_at' => time()];

jsonResponse(200, [
  'ok' => true,
  'message' => 'Account erstellt!',
  'redirect' => '/dashboard.php'
]);
