<?php
declare(strict_types=1);

/**
 * character_view.php
 * Character sheet (basic profile) – loads from SQLite via Database.php
 * URL: character_view.php?characterID=123
 */

session_start();

// -------------------------------
// Bootstrap / App wiring
// -------------------------------
$root = dirname(__DIR__); // assuming this file lives in /Dashboard (adjust if needed)
require_once $root . '/Database/Database.php';

$dbFile = $root . '/Database/Daggerheart.db';
$db = Database::getInstance($dbFile);

// -------------------------------
// Helpers
// -------------------------------
function h(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_int_query(string $key, ?int $fallback = null): ?int {
    if (!isset($_GET[$key])) return $fallback;
    $v = filter_var($_GET[$key], FILTER_VALIDATE_INT);
    return ($v === false) ? $fallback : (int)$v;
}

function abort_page(int $status, string $title, string $message): never {
    http_response_code($status);
    echo "<!doctype html><html><head><meta charset='utf-8'><title>" . h($title) . "</title></head><body style='font-family:system-ui;padding:24px;'><h2>"
        . h($title) . "</h2><p>" . h($message) . "</p></body></html>";
    exit;
}

// -------------------------------
// Data access: Basic profile
// -------------------------------
function load_character_profile(Database $db, int $characterId): ?array {
    // NOTE: table names "character" may require quoting in some contexts; SQLite allows it, but we quote to be safe.
    $sql = "
        SELECT
            c.characterID,
            c.name,
            c.pronouns,
            c.level,
            c.heritageID,
            c.classID,
            c.subclassID,
            h.name  AS heritage_name,
            cl.name AS class_name,
            sc.name AS subclass_name
        FROM \"character\" c
        LEFT JOIN heritage h ON h.heritageID = c.heritageID
        LEFT JOIN class    cl ON cl.classID = c.classID
        LEFT JOIN subclass sc ON sc.subclassID = c.subclassID
        WHERE c.characterID = :id
        LIMIT 1
    ";
    return $db->fetch($sql, [':id' => $characterId]);
}

// -------------------------------
// Input: characterID from URL
// -------------------------------
$characterId = get_int_query('characterID', get_int_query('id'));
if ($characterId === null || $characterId <= 0) {
    abort_page(400, 'Missing characterID', 'Please call this page with ?characterID=123');
}

$profile = load_character_profile($db, $characterId);
if (!$profile) {
    abort_page(404, 'Character not found', "No character found for characterID={$characterId}");
}

// Normalize display values
$charName   = (string)($profile['name'] ?? '');
$pronouns   = (string)($profile['pronouns'] ?? '');
$heritage   = (string)($profile['heritage_name'] ?? '—');
$className  = (string)($profile['class_name'] ?? '—');
$subclass   = (string)($profile['subclass_name'] ?? '—');
$level      = (int)($profile['level'] ?? 1);
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Character – View</title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

    <style>
        :root {
            --brand: #a78bfa;
            --brand2: #22c55e;
            --ink: rgba(255, 255, 255, .90);
            --muted: rgba(255, 255, 255, .70);
            --surface: rgba(255, 255, 255, .06);
            --surface2: rgba(255, 255, 255, .10);
            --border: rgba(255, 255, 255, .12);
            --navbg: rgba(0, 0, 0, .35);
        }

        body {
            color: var(--ink);
            background:
                radial-gradient(1100px 520px at 18% 10%, rgba(167, 139, 250, .22), transparent 60%),
                radial-gradient(900px 500px at 80% 25%, rgba(34, 197, 94, .12), transparent 60%),
                linear-gradient(0deg, rgba(255, 255, 255, .02), rgba(255, 255, 255, .02));
            min-height: 100vh;
        }

        .muted { color: var(--muted); }

        .glass {
            background: var(--surface);
            border: 1px solid var(--border);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .btn-brand {
            --bs-btn-bg: var(--brand);
            --bs-btn-border-color: var(--brand);
            --bs-btn-hover-bg: #8b5cf6;
            --bs-btn-hover-border-color: #8b5cf6;
            --bs-btn-focus-shadow-rgb: 167, 139, 250;
            --bs-btn-color: #0b0b0f;
            font-weight: 700;
        }

        .sheet { border-radius: 1.25rem; overflow: hidden; }
        .sheet-topbar {
            padding: 1.1rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, .10);
            background: linear-gradient(90deg, rgba(167, 139, 250, .10), rgba(34, 197, 94, .06));
        }

        .sheet-grid { padding: 1.25rem; overflow: hidden; }

        .sheet-block {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, .12);
            background: rgba(255, 255, 255, .04);
            padding: 1rem;
            height: 100%;
        }

        .sheet-block input:focus,
        .sheet-block textarea:focus,
        .sheet-block select:focus {
            outline: none;
            border-color: rgba(167, 139, 250, .55);
            box-shadow: 0 0 0 .18rem rgba(167, 139, 250, .22);
        }

        .divider { border-top: 1px solid rgba(255, 255, 255, .10); margin: .9rem 0; }

        .field { display: grid; gap: .35rem; margin-bottom: .75rem; }
        .field label {
            font-size: .78rem;
            letter-spacing: .10em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .70);
        }
        .field input, .field textarea, .field select {
            border-radius: .85rem;
            border: 1px solid rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .05);
            color: var(--ink);
            padding: .65rem .75rem;
            outline: none;
            width: 99%;
        }

        /* Bootstrap flex children should be allowed to shrink */
        .row>[class*="col-"] { min-width: 0; }

        /* Topbar: responsive grid */
        .topbar-grid {
            display: grid;
            gap: .75rem;
            grid-template-columns:
                minmax(260px, 2fr)  /* Name */
                minmax(140px, 1fr)  /* Pronouns */
                minmax(120px, 1fr)  /* Heritage */
                minmax(120px, 1fr)  /* Class */
                minmax(140px, 1fr)  /* Subclass */
                minmax(90px, .6fr); /* Level */
            align-items: end;
        }
        @media (max-width: 1200px) {
            .topbar-grid { grid-template-columns: minmax(260px,2fr) minmax(140px,1fr) minmax(120px,1fr) minmax(120px,1fr); }
            .topbar-grid .span-2 { grid-column: span 2; }
        }
        @media (max-width: 768px) {
            .topbar-grid { grid-template-columns: 1fr 1fr; }
            .topbar-grid .full { grid-column: 1 / -1; }
        }
        @media (max-width: 420px) { .topbar-grid { grid-template-columns: 1fr; } }

        /* Light theme fixes */
        html[data-bs-theme="light"] {
            --ink: rgba(13, 15, 18, .92);
            --muted: rgba(13, 15, 18, .68);
            --surface: rgba(13, 15, 18, .04);
            --surface2: rgba(13, 15, 18, .07);
            --border: rgba(13, 15, 18, .12);
            --navbg: rgba(255, 255, 255, .70);
        }
        html[data-bs-theme="light"] body {
            color: var(--ink);
            background:
                radial-gradient(1100px 520px at 18% 10%, rgba(167, 139, 250, .20), transparent 62%),
                radial-gradient(900px 500px at 80% 25%, rgba(34, 197, 94, .10), transparent 62%),
                linear-gradient(0deg, rgba(0, 0, 0, .02), rgba(0, 0, 0, .02));
        }
        html[data-bs-theme="light"] .field input,
        html[data-bs-theme="light"] .field textarea,
        html[data-bs-theme="light"] .field select {
            background: rgba(13, 15, 18, .03);
            color: rgba(13, 15, 18, .92);
            border-color: rgba(13, 15, 18, .14);
        }
    </style>
</head>

<body>
<header class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <a class="btn btn-outline-light" href="dashboard.php">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>

            <div class="d-flex gap-2">
                <button class="btn btn-outline-light" id="btnToggleTheme" type="button" title="Toggle theme">
                    <i class="bi bi-sun"></i>
                </button>

                <!-- Save button wired later -->
                <button class="btn btn-brand" type="button">
                    <i class="bi bi-check2-circle me-2"></i>Save
                </button>
            </div>
        </div>
    </div>
</header>

<main class="container pb-5">
    <section class="glass sheet">

        <!-- =========================
             BASIC PROFILE (Topbar)
             ========================= -->
        <div class="sheet-topbar">

            <!-- Keep the characterID available for JS later -->
            <input type="hidden" id="characterID" value="<?= (int)$characterId ?>">

            <div class="topbar-grid">
                <div class="field">
                    <label for="cName">Name</label>
                    <input id="cName" type="text" value="<?= h($charName) ?>" readonly/>
                </div>

                <div class="field">
                    <label for="cPronouns">Pronouns</label>
                    <input id="cPronouns" type="text" value="<?= h($pronouns) ?>" readonly/>
                </div>

                <div class="field">
                    <label for="cHeritage">Heritage</label>
                    <input id="cHeritage" type="text" value="<?= h($heritage) ?>" readonly/>
                </div>

                <div class="field">
                    <label for="cClass">Class</label>
                    <input id="cClass" type="text" value="<?= h($className) ?>" readonly/>
                </div>

                <div class="field">
                    <label for="cSubClass">Subclass</label>
                    <input id="cSubClass" type="text" value="<?= h($subclass) ?>" readonly/>
                </div>

                <div class="field">
                    <label for="cLevel">Level</label>
                    <input id="cLevel" type="number" min="1" value="<?= (int)$level ?>" readonly/>
                </div>
            </div>

            <div class="divider"></div>

            <!-- =========================
                 STATS (placeholder for now)
                 Later you can load from character_stats and render dynamically
                 ========================= -->
            <div class="row g-3">
                <div class="col-12">
                    <div class="sheet-block">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div>
                                <div class="text-uppercase small muted" style="letter-spacing:.10em;">Stats</div>
                                <div class="fw-bold">Coming next</div>
                            </div>
                            <span class="badge text-bg-secondary">placeholder</span>
                        </div>
                        <div class="mt-2 muted">
                            This block is intentionally separated so you can add a dedicated
                            <code>load_character_stats()</code> later (e.g. from <code>character_stats</code>).
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =========================
             SHEET BODY (placeholder structure)
             ========================= -->
        <div class="sheet-grid">
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="sheet-block">
                        <div class="text-uppercase small muted" style="letter-spacing:.10em;">Defense / Health / Hope</div>
                        <div class="mt-2 muted">Split into separate render functions later.</div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="sheet-block">
                        <div class="text-uppercase small muted" style="letter-spacing:.10em;">Weapons / Armor / Inventory</div>
                        <div class="mt-2 muted">Split into separate render functions later.</div>
                    </div>
                </div>
            </div>
        </div>

    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

<script>
    // Theme toggle
    const btnToggleTheme = document.getElementById('btnToggleTheme');
    btnToggleTheme?.addEventListener('click', () => {
        const html = document.documentElement;
        const cur = html.getAttribute('data-bs-theme') || 'dark';
        html.setAttribute('data-bs-theme', cur === 'dark' ? 'light' : 'dark');
        btnToggleTheme.innerHTML = cur === 'dark' ? '<i class="bi bi-moon"></i>' : '<i class="bi bi-sun"></i>';
    });
</script>
</body>
</html>