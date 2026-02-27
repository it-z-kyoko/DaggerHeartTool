<?php
declare(strict_types=1);

session_start();

/**
 * api_register.php
 * - Expects JSON: { username, password }
 * - Creates user in SQLite
 * - Sets $_SESSION['userID'] (compatible with the rest of your app)
 *
 * Debug:
 *   /Login/api_register.php?debug=1
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$debug = (int)($_GET['debug'] ?? 0) === 1;

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

set_exception_handler(function(Throwable $e) use ($debug) {
  error_log("[api_register] " . $e->getMessage() . "\n" . $e->getTraceAsString());
  jsonResponse(500, [
    'ok' => false,
    'message' => $debug ? ('Serverfehler: ' . $e->getMessage()) : 'Serverfehler.',
  ]);
});

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  jsonResponse(405, ['ok' => false, 'message' => 'Method not allowed']);
}

require_once __DIR__ . '/../Database/Database.php';

$dbFile = __DIR__ . '/../Database/Daggerheart.db';
if (!file_exists($dbFile)) {
  jsonResponse(500, [
    'ok' => false,
    'message' => $debug ? ('DB-Datei nicht gefunden: ' . $dbFile) : 'DB nicht gefunden.',
  ]);
}

$db = Database::getInstance($dbFile);

// Recommended for SQLite
$db->execute("PRAGMA foreign_keys = ON;");

// --- Ensure expected table exists (compatible with your project schema) ---
$db->execute('
  CREATE TABLE IF NOT EXISTS "user" (
    userID   INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT    NOT NULL UNIQUE,
    password TEXT    NOT NULL,
    picture  TEXT
  );
');

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

// Check existing
$existing = $db->fetch(
  'SELECT userID, username FROM "user" WHERE username = :u LIMIT 1',
  [':u' => $username]
);
if ($existing) {
  jsonResponse(409, ['ok' => false, 'message' => 'Benutzername ist bereits vergeben.']);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
  jsonResponse(500, ['ok' => false, 'message' => 'Serverfehler beim Passwort-Hash.']);
}

// Insert
$db->execute(
  'INSERT INTO "user" (username, password) VALUES (:u, :p)',
  [':u' => $username, ':p' => $hash]
);

// Get created userID safely
$row = $db->fetch(
  'SELECT userID, username FROM "user" WHERE username = :u LIMIT 1',
  [':u' => $username]
);

if (!$row || !isset($row['userID'])) {
  jsonResponse(500, ['ok' => false, 'message' => $debug ? 'User insert ok, but cannot read userID.' : 'Serverfehler.']);
}

$userID = (int)$row['userID'];

// Auto-login (compatible with the rest of your app)
session_regenerate_id(true);
$_SESSION['userID'] = $userID;

// optional: keep your auth array too (harmless)
$_SESSION['auth'] = [
  'userID' => $userID,
  'username' => $username,
  'logged_in_at' => time()
];

jsonResponse(200, [
  'ok' => true,
  'message' => 'Account erstellt!',
  // adjust to your real dashboard path if needed:
  'redirect' => '/Dashboard/dashboard.php'
]);