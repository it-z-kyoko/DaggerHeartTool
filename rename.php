<?php
declare(strict_types=1);

/**
 * rename_domain_cards.php
 * Liegt direkt unter /htdocs
 * Bearbeitet: /img/Cards/Domains
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* -------------------------------------------------
   ROOT + TARGET (fix für htdocs direkt)
------------------------------------------------- */

$root = realpath(__DIR__); // <-- da Datei in htdocs liegt
$targetRel = '/img/Cards/Domains';
$targetDir = realpath($root . $targetRel);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalize_name(
  string $name,
  bool $spacesToUnderscore,
  bool $collapse,
  bool $lowercase
): string {

  $new = $name;

  if ($spacesToUnderscore) {
    $new = preg_replace('/\s+/', '_', $new) ?? $new;
  }

  if ($collapse) {
    $new = preg_replace('/_+/', '_', $new) ?? $new;
    $new = trim($new, '_');
  }

  if ($lowercase) {
    $new = mb_strtolower($new, 'UTF-8');
  }

  return $new;
}

function is_safe_filename(string $name): bool {
  if ($name === '' || $name === '.' || $name === '..') return false;
  if (str_contains($name, '/') || str_contains($name, '\\')) return false;
  if (str_contains($name, "\0")) return false;
  return true;
}

function unique_target(string $dir, string $filename): string {

  $path = $dir . DIRECTORY_SEPARATOR . $filename;
  if (!file_exists($path)) return $filename;

  $info = pathinfo($filename);
  $base = $info['filename'] ?? $filename;
  $ext  = isset($info['extension']) ? '.' . $info['extension'] : '';

  $i = 1;
  do {
    $candidate = $base . '__' . $i . $ext;
    $path = $dir . DIRECTORY_SEPARATOR . $candidate;
    $i++;
  } while (file_exists($path));

  return $candidate;
}

/* -------------------------------------------------
   VALIDATION
------------------------------------------------- */

if ($targetDir === false || !is_dir($targetDir)) {
  http_response_code(500);
  echo "Target directory not found: " . h($root . $targetRel);
  exit;
}

/* -------------------------------------------------
   OPTIONS
------------------------------------------------- */

$spacesToUnderscore = (int)($_POST['spaces_to_underscore'] ?? 1) === 1;
$collapseUnderscore = (int)($_POST['collapse_underscore'] ?? 1) === 1;
$lowercase          = (int)($_POST['lowercase'] ?? 0) === 1;

$mode = $_POST['mode'] ?? 'preview'; // preview | run

/* -------------------------------------------------
   READ FILES
------------------------------------------------- */

$files = [];
$dh = opendir($targetDir);

if ($dh !== false) {
  while (($entry = readdir($dh)) !== false) {
    if ($entry === '.' || $entry === '..') continue;

    $full = $targetDir . DIRECTORY_SEPARATOR . $entry;
    if (!is_file($full)) continue;

    $files[] = $entry;
  }
  closedir($dh);
}

sort($files, SORT_NATURAL | SORT_FLAG_CASE);

/* -------------------------------------------------
   BUILD MAPPING
------------------------------------------------- */

$mapping = [];

foreach ($files as $old) {

  if (!is_safe_filename($old)) continue;

  $new = normalize_name(
    $old,
    $spacesToUnderscore,
    $collapseUnderscore,
    $lowercase
  );

  if ($new === $old) continue;
  if (!is_safe_filename($new)) continue;

  $mapping[$old] = $new;
}

$results = [];
$errors  = [];

/* -------------------------------------------------
   EXECUTE RENAME
------------------------------------------------- */

if ($mode === 'run') {

  foreach ($mapping as $old => $desiredNew) {

    $oldPath = $targetDir . DIRECTORY_SEPARATOR . $old;

    if (!file_exists($oldPath)) {
      $errors[] = "Missing: $old";
      continue;
    }

    $finalNew = unique_target($targetDir, $desiredNew);
    $newPath  = $targetDir . DIRECTORY_SEPARATOR . $finalNew;

    if (@rename($oldPath, $newPath)) {
      $results[] = [$old, $finalNew];
    } else {
      $errors[] = "Failed: $old → $finalNew";
    }
  }

  $mapping = [];
}
?>
<!doctype html>
<html lang="de" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rename Domain Cards</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background:#0b1020; }
.glass {
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.12);
  border-radius:16px;
  backdrop-filter:blur(10px);
  box-shadow:0 10px 30px rgba(0,0,0,.25);
}
code { color:#e9ecef; }
</style>
</head>
<body>
<div class="container py-4">

<h2 class="mb-3">
<i class="bi bi-fonts"></i> Rename Domain Cards
</h2>

<p class="text-muted">
Ordner: <code><?=h($targetRel)?></code>
</p>

<form method="post" class="glass p-3 mb-4">
<input type="hidden" name="mode" value="preview">

<div class="form-check">
<input class="form-check-input" type="checkbox" name="spaces_to_underscore" value="1" <?= $spacesToUnderscore ? 'checked' : '' ?>>
<label class="form-check-label">Leerzeichen → Unterstrich</label>
</div>

<div class="form-check">
<input class="form-check-input" type="checkbox" name="collapse_underscore" value="1" <?= $collapseUnderscore ? 'checked' : '' ?>>
<label class="form-check-label">Mehrere Unterstriche zusammenfassen</label>
</div>

<div class="form-check">
<input class="form-check-input" type="checkbox" name="lowercase" value="1" <?= $lowercase ? 'checked' : '' ?>>
<label class="form-check-label">Lowercase</label>
</div>

<div class="mt-3 d-flex gap-2">
<button class="btn btn-primary" type="submit">
Preview
</button>

<button class="btn btn-danger"
onclick="this.form.mode.value='run'; return confirm('Wirklich umbenennen?');">
Umbenennen
</button>
</div>
</form>

<?php if ($mode === 'run'): ?>
<div class="glass p-3 mb-4">
<h5>Ergebnis</h5>

<?php if ($results): ?>
<div class="alert alert-success">
Umbenannt: <?=count($results)?>
</div>
<ul>
<?php foreach ($results as [$o,$n]): ?>
<li><code><?=h($o)?></code> → <code><?=h($n)?></code></li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<div class="alert alert-warning">Keine Änderungen.</div>
<?php endif; ?>

<?php if ($errors): ?>
<div class="alert alert-danger">
<ul>
<?php foreach ($errors as $e): ?>
<li><?=h($e)?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

</div>
<?php endif; ?>

<div class="glass p-3">
<h5>Vorschau</h5>

<?php if (!$mapping): ?>
<div class="alert alert-info">Keine Änderungen vorgeschlagen.</div>
<?php else: ?>
<table class="table table-dark table-sm">
<thead>
<tr><th>Alt</th><th>Neu</th></tr>
</thead>
<tbody>
<?php foreach ($mapping as $old=>$new): ?>
<tr>
<td><code><?=h($old)?></code></td>
<td><code><?=h($new)?></code></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

</div>
</body>
</html>