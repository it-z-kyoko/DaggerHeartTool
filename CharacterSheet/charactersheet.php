<?php

declare(strict_types=1);

/**
 * character_view.php
 * Character sheet (basic profile + stats + trackers) – loads from SQLite via Database.php
 * URL: character_view.php?characterID=123
 *
 * Trackers:
 *  - HP    -> character_stats.HP (max = HP value)
 *  - Stress-> character_stats.Stress (max = 6)
 *  - Hope  -> character_stats.Hope (max = 6)
 *  - Armor -> character.armor (max = armor value)
 *
 * Click dot to set value and save immediately.
 */

session_start();

$readonly = (int)($_GET['readonly'] ?? 0) === 1;
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
function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_int_query(string $key, ?int $fallback = null): ?int
{
    if (!isset($_GET[$key])) return $fallback;
    $v = filter_var($_GET[$key], FILTER_VALIDATE_INT);
    return ($v === false) ? $fallback : (int)$v;
}

function abort_page(int $status, string $title, string $message): never
{
    http_response_code($status);
    echo "<!doctype html><html><head><meta charset='utf-8'><title>" . h($title) . "</title></head><body style='font-family:system-ui;padding:24px;'><h2>"
        . h($title) . "</h2><p>" . h($message) . "</p></body></html>";
    exit;
}

// -------------------------------
// Data access: Basic profile
// -------------------------------
function load_character_profile(Database $db, int $characterId): ?array
{
    $sql = "
        SELECT
            c.characterID,
            c.name,
            c.pronouns,
            c.level,
            c.armor,
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
// Data access: Stats (character_stats)
// -------------------------------
function load_character_stats(Database $db, int $characterId): array
{
    $sql = "
        SELECT
            Agility, Strength, Finesse,
            Instinct, Presence, Knowledge,
            HP, Stress, Hope
        FROM character_stats
        WHERE characterID = :id
        LIMIT 1
    ";

    $row = $db->fetch($sql, [':id' => $characterId]);

    $defaults = [
        'Agility' => 0,
        'Strength' => 0,
        'Finesse' => 0,
        'Instinct' => 0,
        'Presence' => 0,
        'Knowledge' => 0,
        'HP' => 0,
        'Stress' => 0,
        'Hope' => 0,
    ];

    if (!$row) return $defaults;

    foreach ($defaults as $k => $_) {
        $defaults[$k] = isset($row[$k]) ? (int)$row[$k] : 0;
    }

    return $defaults;
}

function format_mod(int $v): string
{
    return ($v >= 0 ? '+' : '') . (string)$v;
}

function render_stat_card(string $name, int $value, array $tags): void
{
?>
    <div class="stat">
        <p class="name"><?= h($name) ?></p>
        <div class="value">
            <div class="score"><?= h(format_mod($value)) ?></div>
            <div class="tags">
                <?= h($tags[0] ?? '') ?><br />
                <?= h($tags[1] ?? '') ?><br />
                <?= h($tags[2] ?? '') ?>
            </div>
        </div>
    </div>
<?php
}

// -------------------------------
// AJAX: Save Tracker (do NOT remove)
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'saveTracker')) {
    header('Content-Type: application/json; charset=utf-8');

    $characterId = (int)($_POST['characterID'] ?? 0);
    $track       = (string)($_POST['track'] ?? '');
    $value       = (int)($_POST['value'] ?? 0);

    if ($characterId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid characterID']);
        exit;
    }

    // Guard: allow only known tracks
    $allowed = ['HP', 'Stress', 'Hope', 'Armor'];
    if (!in_array($track, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid track']);
        exit;
    }

    // Clamp values (basic sanity)
    if ($value < 0) $value = 0;
    if (in_array($track, ['Stress', 'Hope'], true) && $value > 6) $value = 6;

    try {
        if (in_array($track, ['HP', 'Stress', 'Hope'], true)) {
            // For HP: max is dynamic, but we still store whatever user sets
            $db->execute(
                "UPDATE character_stats SET {$track} = :val WHERE characterID = :id",
                [':val' => $value, ':id' => $characterId]
            );
        } elseif ($track === 'Armor') {
            $db->execute(
                "UPDATE \"character\" SET armor = :val WHERE characterID = :id",
                [':val' => $value, ':id' => $characterId]
            );
        }

        echo json_encode(['ok' => true]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
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

$stats = load_character_stats($db, $characterId);

// Normalize display values
$charName   = (string)($profile['name'] ?? '');
$pronouns   = (string)($profile['pronouns'] ?? '');
$heritage   = (string)($profile['heritage_name'] ?? '—');
$className  = (string)($profile['class_name'] ?? '—');
$subclass   = (string)($profile['subclass_name'] ?? '—');
$level      = (int)($profile['level'] ?? 1);
$armor      = (int)($profile['armor'] ?? 0);
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

        .muted {
            color: var(--muted);
        }

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

        /* Character view */
        .sheet {
            border-radius: 1.25rem;
            overflow: hidden;
        }

        .sheet-topbar {
            padding: 1.1rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, .10);
            background: linear-gradient(90deg, rgba(167, 139, 250, .10), rgba(34, 197, 94, .06));
        }

        .sheet-grid {
            padding: 1.25rem;
        }

        .sheet-block {
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, .12);
            background: rgba(255, 255, 255, .04);
            padding: 1rem;
            height: 100%;
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .sheet-block input:focus,
        .sheet-block textarea:focus,
        .sheet-block select:focus {
            outline: none;
            border-color: rgba(167, 139, 250, .55);
            box-shadow: 0 0 0 .18rem rgba(167, 139, 250, .22);
        }

        .block-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .75rem;
        }

        .block-title h3 {
            font-size: 1rem;
            margin: 0;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .85);
        }

        .block-title .chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .35rem .6rem;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, .12);
            background: rgba(255, 255, 255, .05);
            color: var(--muted);
            font-size: .85rem;
            white-space: nowrap;
        }

        .field {
            display: grid;
            gap: .35rem;
            margin-bottom: .75rem;
        }

        .field label {
            font-size: .78rem;
            letter-spacing: .10em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .70);
        }

        .field input,
        .field textarea,
        .field select {
            border-radius: .85rem;
            border: 1px solid rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .05);
            color: var(--ink);
            padding: .65rem .75rem;
            outline: none;
            width: 99%;
        }

        .field textarea {
            min-height: 110px;
            resize: vertical;
        }

        .divider {
            border-top: 1px solid rgba(255, 255, 255, .10);
            margin: .9rem 0;
        }

        /* Stat tokens */
        .stat-grid {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        @media (max-width: 992px) {
            .stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 420px) {
            .stat-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat {
            border-radius: 1rem;
            border: 1px solid rgba(255, 255, 255, .12);
            background: rgba(255, 255, 255, .04);
            padding: .75rem .75rem .65rem;
        }

        .stat .name {
            font-size: .78rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .70);
            margin: 0 0 .35rem 0;
        }

        .stat .value {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: .75rem;
        }

        .stat .score {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1;
        }

        .stat .tags {
            font-size: .82rem;
            color: rgba(255, 255, 255, .65);
            text-align: right;
        }

        /* Trackers (dots) */
        .dots {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }

        .dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, .22);
            background: rgba(255, 255, 255, .03);
            display: inline-block;
            cursor: pointer;
        }

        .dot.filled {
            background: rgba(167, 139, 250, .55);
            border-color: rgba(167, 139, 250, .75);
        }

        .track-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .track-row .track-label {
            font-weight: 700;
            letter-spacing: -0.02em;
        }

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

        html[data-bs-theme="light"] .dot.filled {
            background: rgba(167, 139, 250, .35);
            border-color: rgba(167, 139, 250, .55);
        }

        /* Bootstrap-Flex-Kinder dürfen wirklich schrumpfen */
        .row>[class*="col-"] {
            min-width: 0;
        }

        /* Topbar layout */
        .topbar-grid {
            display: grid;
            gap: .75rem;
            grid-template-columns:
                minmax(260px, 2fr) minmax(140px, 1fr) minmax(120px, 1fr) minmax(120px, 1fr) minmax(140px, 1fr) minmax(90px, .6fr);
            align-items: end;
        }

        @media (max-width: 1200px) {
            .topbar-grid {
                grid-template-columns:
                    minmax(260px, 2fr) minmax(140px, 1fr) minmax(120px, 1fr) minmax(120px, 1fr);
            }

            .topbar-grid .span-2 {
                grid-column: span 2;
            }
        }

        @media (max-width: 768px) {
            .topbar-grid {
                grid-template-columns: 1fr 1fr;
            }

            .topbar-grid .full {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 420px) {
            .topbar-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <?php if ($readonly): ?>
        <style>
            /* Visuell/UX: klar machen, dass es nicht interaktiv ist */
            body {
                caret-color: transparent;
            }

            /* Alles, was typischerweise klickbar ist, komplett deaktivieren */
            a,
            button,
            input,
            select,
            textarea,
            label,
            [role="button"],
            [contenteditable="true"],
            .btn,
            .button,
            .clickable,
            [onclick],
            [data-action],
            [data-click],
            [data-toggle],
            [data-bs-toggle] {
                pointer-events: none !important;
                cursor: default !important;
            }

            /* Fokus/Outline vermeiden */
            *:focus {
                outline: none !important;
                box-shadow: none !important;
            }
        </style>

        <script>
            (() => {
                const READONLY = true;

                function hardDisable(root = document) {
                    // Form-Controls hart deaktivieren
                    root.querySelectorAll('input, select, textarea, button').forEach(el => {
                        el.disabled = true;
                        el.tabIndex = -1;
                        el.setAttribute('aria-disabled', 'true');
                    });

                    // Links "entkernen"
                    root.querySelectorAll('a[href]').forEach(a => {
                        a.dataset.hrefReadonly = a.getAttribute('href') || '';
                        a.removeAttribute('href');
                        a.setAttribute('aria-disabled', 'true');
                        a.tabIndex = -1;
                    });

                    // Generische Klickflächen: tabindex raus
                    root.querySelectorAll('[role="button"], [onclick], [data-action], [data-click], [data-toggle], [data-bs-toggle]').forEach(el => {
                        el.setAttribute('aria-disabled', 'true');
                        el.tabIndex = -1;
                    });

                    // Contenteditable aus
                    root.querySelectorAll('[contenteditable="true"]').forEach(el => {
                        el.setAttribute('contenteditable', 'false');
                    });
                }

                function killInteraction(e) {
                    // Scrollen erlauben (Mausrad/Touchpad), aber keine Interaktion
                    const t = e.type;

                    // Diese Events NICHT blocken (sonst kein scroll)
                    if (t === 'wheel' || t === 'scroll' || t === 'touchmove') return;

                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation?.();
                    return false;
                }

                document.addEventListener('DOMContentLoaded', () => {
                    if (!READONLY) return;

                    // initial alles deaktivieren
                    hardDisable(document);

                    // ALLE Interaktionen abwürgen (auch Hope Buttons, +/- etc.)
                    [
                        'click', 'dblclick', 'mousedown', 'mouseup', 'pointerdown', 'pointerup',
                        'keydown', 'keyup', 'keypress',
                        'submit', 'change', 'input'
                    ].forEach(evt => document.addEventListener(evt, killInteraction, true));

                    // Falls dein Sheet dynamisch Inhalte nachlädt/neu rendert:
                    // MutationObserver deaktiviert neu hinzugekommene Buttons sofort
                    const mo = new MutationObserver((mutations) => {
                        for (const m of mutations) {
                            m.addedNodes && m.addedNodes.forEach(node => {
                                if (node.nodeType === 1) hardDisable(node);
                            });
                        }
                    });
                    mo.observe(document.documentElement, {
                        childList: true,
                        subtree: true
                    });
                });
            })();
        </script>
    <?php endif; ?>
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
                    <button class="btn btn-brand" type="button">
                        <i class="bi bi-check2-circle me-2"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="container pb-5">
        <section class="glass sheet">

            <div class="sheet-topbar">
                <input type="hidden" id="characterID" value="<?= (int)$characterId ?>">

                <!-- TOPBAR GRID -->
                <div class="topbar-grid">
                    <div class="field">
                        <label for="cName">Name</label>
                        <input id="cName" type="text" value="<?= h($charName) ?>" readonly />
                    </div>

                    <div class="field">
                        <label for="cPronouns">Pronouns</label>
                        <input id="cPronouns" type="text" value="<?= h($pronouns) ?>" readonly />
                    </div>

                    <div class="field">
                        <label for="cHeritage">Heritage</label>
                        <input id="cHeritage" type="text" value="<?= h($heritage) ?>" readonly />
                    </div>

                    <div class="field">
                        <label for="cClass">Class</label>
                        <input id="cClass" type="text" value="<?= h($className) ?>" readonly />
                    </div>

                    <div class="field">
                        <label for="cSubClass">Subclass</label>
                        <input id="cSubClass" type="text" value="<?= h($subclass) ?>" readonly />
                    </div>

                    <div class="field">
                        <label for="cLevel">Level</label>
                        <input id="cLevel" type="number" min="1" value="<?= (int)$level ?>" readonly />
                    </div>
                </div>

                <div class="divider"></div>

                <!-- STATS GRID -->
                <div class="stat-grid">
                    <?php
                    render_stat_card('Agility',   $stats['Agility'],   ['Sprint', 'Leap', 'Maneuver']);
                    render_stat_card('Strength',  $stats['Strength'],  ['Lift', 'Smash', 'Grapple']);
                    render_stat_card('Finesse',   $stats['Finesse'],   ['Control', 'Hide', 'Tinker']);
                    render_stat_card('Instinct',  $stats['Instinct'],  ['Perceive', 'Sense', 'Navigate']);
                    render_stat_card('Presence',  $stats['Presence'],  ['Charm', 'Perform', 'Deceive']);
                    render_stat_card('Knowledge', $stats['Knowledge'], ['Recall', 'Analyze', 'Comprehend']);
                    ?>
                </div>
            </div>

            <!-- SHEET BODY -->
            <div class="sheet-grid">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <div class="sheet-block">
                            <div class="text-uppercase small muted" style="letter-spacing:.10em;">Defense / Health / Hope</div>
                            <div class="mt-2 muted">Split into separate render functions later.</div>

                            <div class="divider"></div>

                            <div class="track-row mb-2">
                                <div class="track-label">HP</div>
                                <div class="dots"
                                    data-max="9"
                                    data-track="HP"
                                    data-value="<?= (int)$stats['HP'] ?>"></div>
                            </div>

                            <div class="track-row mb-2">
                                <div class="track-label">Stress</div>
                                <div class="dots"
                                    data-max="6"
                                    data-track="Stress"
                                    data-value="<?= (int)$stats['Stress'] ?>"></div>
                            </div>

                            <div class="track-row mb-2">
                                <div class="track-label">Hope</div>
                                <div class="dots"
                                    data-max="6"
                                    data-track="Hope"
                                    data-value="<?= (int)$stats['Hope'] ?>"></div>
                            </div>

                            <div class="track-row">
                                <div class="track-label">Armor</div>
                                <div class="dots"
                                    data-max="9"
                                    data-track="Armor"
                                    data-value="<?= $armor ?>"></div>
                            </div>

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
        // Theme toggle (optional)  <-- KEEP
        const btnToggleTheme = document.getElementById('btnToggleTheme');
        btnToggleTheme?.addEventListener('click', () => {
            const html = document.documentElement;
            const cur = html.getAttribute('data-bs-theme') || 'dark';
            html.setAttribute('data-bs-theme', cur === 'dark' ? 'light' : 'dark');
            btnToggleTheme.innerHTML = cur === 'dark' ? '<i class="bi bi-moon"></i>' : '<i class="bi bi-sun"></i>';
        });

        // Build dot trackers + click to fill/unfill like the paper sheet boxes  <-- KEEP (extended)
        function buildDots(el, max, currentValue, track) {
            el.innerHTML = '';

            for (let i = 1; i <= max; i++) {
                const d = document.createElement('span');
                d.className = 'dot';
                d.setAttribute('role', 'button');
                d.setAttribute('tabindex', '0');
                d.setAttribute('aria-label', 'Tracker');

                if (i <= currentValue) d.classList.add('filled');

                const setValue = (newValue) => {
                    const cid = document.getElementById('characterID')?.value || '';

                    fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                            },
                            body: 'action=saveTracker' +
                                '&characterID=' + encodeURIComponent(cid) +
                                '&track=' + encodeURIComponent(track) +
                                '&value=' + encodeURIComponent(newValue)
                        })
                        .then(r => r.json())
                        .then(j => {
                            if (!j || !j.ok) return;
                            buildDots(el, max, newValue, track);
                        })
                        .catch(() => {});
                };

                d.addEventListener('click', () => {
                    // Click same filled dot -> decrement by 1 (nice UX)
                    const newValue = (i === currentValue) ? Math.max(0, i - 1) : i;
                    setValue(newValue);
                });

                d.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        const newValue = (i === currentValue) ? Math.max(0, i - 1) : i;
                        setValue(newValue);
                    }
                });

                el.appendChild(d);
            }
        }

        document.querySelectorAll('.dots').forEach(dots => {
            const max = parseInt(dots.dataset.max || '0', 10);
            const value = parseInt(dots.dataset.value || '0', 10);
            const track = dots.dataset.track || '';
            if (max > 0) buildDots(dots, max, value, track);
        });
    </script>

    <?php if ((int)($_GET['readonly'] ?? 0) === 1): ?>
<script>
(function(){
  const params = new URLSearchParams(location.search);
  const characterID = params.get('characterID');

  async function refreshState(){
    try {
      const res = await fetch(`../CharacterSheet/api_sheet_state.php?characterID=${encodeURIComponent(characterID)}`, {
        cache: 'no-store',
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!data.ok) return;

      const map = {
        hope: data.stats.hope,
        stress: data.stats.stress,
        hp_cur: data.stats.hp_cur,
        hp_max: data.stats.hp_max,
      };

      for (const [key,val] of Object.entries(map)) {
        const el = document.querySelector(`[data-bind="${key}"]`);
        if (el && val !== null && val !== undefined) {
          // update text only -> no layout jump, no scroll change
          el.textContent = String(val);
        }
      }
    } catch (e) {
      // silent fail in readonly view
    }
  }

  // Expose for parent (GM page) to call without reloading:
  window.__gmRefresh = refreshState;

  // Optional: also self-poll every 5s (or remove if parent controls)
  // setInterval(refreshState, 5000);

})();
</script>
<?php endif; ?>
</body>

</html>