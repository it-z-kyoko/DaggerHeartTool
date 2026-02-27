<?php
declare(strict_types=1);

session_start();

$root = __DIR__;
$uploadBase = $root . '/img/';

if (!isset($_SESSION['userID'])) {
    // For page load: redirect. For AJAX: JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Not logged in'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: /Login/login.php');
    exit;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_out(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedTypes = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

// ------------------------------------------
// API endpoint: batch upload
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_GET['mode'] ?? '') === 'batch')) {

    if (!is_dir($uploadBase)) {
        json_out(['ok' => false, 'error' => '/img/ does not exist on server filesystem.'], 500);
    }
    if (!isset($_FILES['files'])) {
        json_out(['ok' => false, 'error' => 'No files received.'], 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $names = $_FILES['files']['name'] ?? [];
    $tmp   = $_FILES['files']['tmp_name'] ?? [];
    $err   = $_FILES['files']['error'] ?? [];
    $size  = $_FILES['files']['size'] ?? [];
    $rel   = $_POST['relpath'] ?? [];

    $count = is_array($names) ? count($names) : 0;
    $out = [
        'ok' => true,
        'total' => $count,
        'saved' => 0,
        'failed' => 0,
        'errors' => [],   // keep only failures (better for 1000 files)
        'saved_paths' => [], // optional: comment out if you don't want to return many
    ];

    for ($i = 0; $i < $count; $i++) {

        $origName = (string)($names[$i] ?? '(unknown)');

        if (($err[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $out['failed']++;
            $out['errors'][] = ['file' => $origName, 'error' => 'Upload error code: ' . (string)($err[$i] ?? -1)];
            continue;
        }

        // per-file max (adjust)
        if (($size[$i] ?? 0) > 20_000_000) {
            $out['failed']++;
            $out['errors'][] = ['file' => $origName, 'error' => 'Too large (max 20MB per file).'];
            continue;
        }

        $mime = $finfo->file($tmp[$i]);
        if (!isset($allowedTypes[$mime])) {
            $out['failed']++;
            $out['errors'][] = ['file' => $origName, 'error' => 'Not an allowed image type.'];
            continue;
        }
        $ext = $allowedTypes[$mime];

        // ---------- secure relative path ----------
        $safeRel = (string)($rel[$i] ?? $origName);
        $safeRel = str_replace('\\', '/', $safeRel);
        $safeRel = preg_replace('~\0~', '', $safeRel); // null bytes
        $safeRel = preg_replace('~^\s+|\s+$~', '', $safeRel);
        $safeRel = preg_replace('~^/+~', '', $safeRel); // no leading slash

        // remove traversal
        while (str_contains($safeRel, '..')) {
            $safeRel = str_replace('..', '', $safeRel);
        }

        // allow only safe chars
        $safeRel = preg_replace('~[^a-zA-Z0-9/_\.\- ]~', '_', $safeRel);

        // if client path becomes empty -> generate
        if ($safeRel === '' || $safeRel === '.' || $safeRel === '/') {
            $safeRel = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        }

        // Ensure extension matches mime (avoid .php etc.)
        // If file already has any extension, we replace it with the correct one.
        $safeRel = preg_replace('~\.[A-Za-z0-9]{1,6}$~', '', $safeRel) . '.' . $ext;

        $targetPath = $uploadBase . $safeRel;
        $targetDir  = dirname($targetPath);

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            $out['failed']++;
            $out['errors'][] = ['file' => $origName, 'error' => 'Could not create directory.'];
            continue;
        }

        if (move_uploaded_file($tmp[$i], $targetPath)) {
            $out['saved']++;
            // Comment out next line if returning many paths is too heavy
            $out['saved_paths'][] = '/img/' . $safeRel;
        } else {
            $out['failed']++;
            $out['errors'][] = ['file' => $origName, 'error' => 'Move failed.'];
        }
    }

    json_out($out, 200);
}

// ------------------------------------------
// PAGE
// ------------------------------------------
?>
<!doctype html>
<html lang="de" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <title>Ordner Upload (Batch)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/Global/styles.css">
</head>
<body class="bg-body">

<div class="container py-5" style="max-width: 960px;">
  <div class="card border-0 shadow-lg bg-dark bg-opacity-75 p-4">

    <h2 class="mb-2">Ordner nach /img/ hochladen</h2>
    <p class="text-secondary mb-4">
      Optimiert für große Mengen (z.B. 1000 Dateien) via Batch-Upload.
      Unterordner werden automatisch angelegt.
    </p>

    <div class="mb-3">
      <label class="form-label">Ordner auswählen</label>
      <input id="folderInput"
             class="form-control"
             type="file"
             webkitdirectory
             directory
             multiple>
      <div class="form-text">Chrome/Edge am zuverlässigsten.</div>
    </div>

    <div class="row g-2 align-items-center">
      <div class="col-12 col-md-4">
        <label class="form-label mb-1">Batch-Größe</label>
        <input id="batchSize" type="number" class="form-control" value="50" min="10" max="200">
      </div>
      <div class="col-12 col-md-8 d-flex gap-2 align-items-end">
        <button id="startBtn" class="btn btn-primary flex-grow-1" type="button" disabled>
          Upload starten
        </button>
        <button id="cancelBtn" class="btn btn-outline-light" type="button" disabled>
          Abbrechen
        </button>
      </div>
    </div>

    <hr class="my-4">

    <div class="mb-2 d-flex justify-content-between">
      <div class="text-secondary">Fortschritt</div>
      <div id="progressText" class="text-secondary">0 / 0</div>
    </div>

    <div class="progress mb-3" style="height: 12px;">
      <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-lg-4">
        <div class="p-3 rounded border border-secondary bg-black bg-opacity-25">
          <div class="text-secondary small">Gespeichert</div>
          <div id="savedCount" class="fs-3">0</div>
        </div>
      </div>
      <div class="col-12 col-lg-4">
        <div class="p-3 rounded border border-secondary bg-black bg-opacity-25">
          <div class="text-secondary small">Fehlgeschlagen</div>
          <div id="failCount" class="fs-3">0</div>
        </div>
      </div>
      <div class="col-12 col-lg-4">
        <div class="p-3 rounded border border-secondary bg-black bg-opacity-25">
          <div class="text-secondary small">Status</div>
          <div id="statusText" class="fs-6">Bereit</div>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <h5 class="mb-2">Fehler (nur wenn vorhanden)</h5>
      <div id="errorBox" class="small text-secondary border border-secondary rounded p-3 bg-black bg-opacity-25" style="max-height: 240px; overflow:auto;">
        Keine
      </div>
    </div>

  </div>
</div>

<script>
const folderInput  = document.getElementById('folderInput');
const startBtn     = document.getElementById('startBtn');
const cancelBtn    = document.getElementById('cancelBtn');
const batchSizeInp = document.getElementById('batchSize');

const progressBar  = document.getElementById('progressBar');
const progressText = document.getElementById('progressText');
const savedCountEl = document.getElementById('savedCount');
const failCountEl  = document.getElementById('failCount');
const statusText   = document.getElementById('statusText');
const errorBox     = document.getElementById('errorBox');

let files = [];
let cancelled = false;

folderInput.addEventListener('change', () => {
  files = Array.from(folderInput.files || []);
  startBtn.disabled = files.length === 0;
  statusText.textContent = files.length ? `Ausgewählt: ${files.length} Dateien` : 'Bereit';
  progressText.textContent = `0 / ${files.length}`;
  progressBar.style.width = '0%';
  savedCountEl.textContent = '0';
  failCountEl.textContent = '0';
  errorBox.textContent = 'Keine';
});

cancelBtn.addEventListener('click', () => {
  cancelled = true;
  cancelBtn.disabled = true;
  statusText.textContent = 'Abbruch angefordert...';
});

startBtn.addEventListener('click', async () => {
  if (!files.length) return;

  cancelled = false;
  startBtn.disabled = true;
  cancelBtn.disabled = false;

  const total = files.length;
  const batchSize = Math.max(10, Math.min(200, parseInt(batchSizeInp.value || '50', 10)));

  let uploaded = 0;
  let saved = 0;
  let failed = 0;

  statusText.textContent = `Upload läuft (Batch ${batchSize})...`;

  for (let i = 0; i < files.length; i += batchSize) {
    if (cancelled) break;

    const batch = files.slice(i, i + batchSize);
    const fd = new FormData();

    batch.forEach((f, idx) => {
      fd.append('files[]', f, f.name);
      fd.append(`relpath[${idx}]`, f.webkitRelativePath || f.name);
    });

    try {
      const res = await fetch(`<?= h($_SERVER['PHP_SELF']) ?>?mode=batch`, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const data = await res.json();

      if (!res.ok || !data.ok) {
        // whole batch failed
        failed += batch.length;
        appendError(`Batch Fehler: ${data.error || 'Unknown error'}`);
      } else {
        saved += (data.saved || 0);
        failed += (data.failed || 0);

        // show only failures (best for 1000 files)
        if (Array.isArray(data.errors) && data.errors.length) {
          data.errors.forEach(e => appendError(`${e.file}: ${e.error}`));
        }
      }

    } catch (e) {
      failed += batch.length;
      appendError(`Batch Exception: ${e.message || e}`);
    }

    uploaded = Math.min(i + batch.length, total);

    // UI update
    const pct = total ? Math.round((uploaded / total) * 100) : 0;
    progressBar.style.width = pct + '%';
    progressText.textContent = `${uploaded} / ${total}`;
    savedCountEl.textContent = String(saved);
    failCountEl.textContent = String(failed);
  }

  cancelBtn.disabled = true;

  if (cancelled) {
    statusText.textContent = `Abgebrochen bei ${uploaded}/${total} (OK ${saved}, FAIL ${failed})`;
  } else {
    statusText.textContent = `Fertig (OK ${saved}, FAIL ${failed})`;
  }

  startBtn.disabled = false;
});

function appendError(line) {
  if (errorBox.textContent.trim() === 'Keine') errorBox.textContent = '';
  errorBox.textContent += (errorBox.textContent ? '\n' : '') + line;
}
</script>

</body>
</html>