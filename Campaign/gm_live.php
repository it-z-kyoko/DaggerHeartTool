<?php
declare(strict_types=1);
session_start();

$root = dirname(__DIR__);
require_once $root . '/Database/Database.php';

if (!isset($_SESSION['userID'])) {
    header('Location: ' . $root . '/Login/login.php');
    exit;
}

$uid = (int)$_SESSION['userID'];
$cid = (int)($_GET['campaignID'] ?? 0);

if ($cid <= 0) {
    http_response_code(400);
    exit('campaignID missing.');
}

$db = Database::getInstance($root . '/Database/Daggerheart.db');

/* Owner Check */
$camp = $db->fetch(
    "SELECT campaignID, name, userID
     FROM campaign
     WHERE campaignID = :cid",
    [':cid' => $cid]
);

if (!$camp) {
    http_response_code(404);
    exit('Campaign not found.');
}

if ((int)$camp['userID'] !== $uid) {
    http_response_code(403);
    exit('No access.');
}

/* Load characters */
$characters = $db->fetchAll(
    "SELECT ch.characterID, ch.name
     FROM campaign_character cc
     JOIN character ch ON ch.characterID = cc.characterID
     WHERE cc.campaignID = :cid
     ORDER BY ch.name COLLATE NOCASE",
    [':cid' => $cid]
);

/* Load rolls (for all campaign characters) */
$rolls = [];
try {
    $limit = 50;

    $rolls = $db->fetchAll(
        "SELECT
            r.rollID,
            r.characterID,
            ch.name AS characterName,
            r.dice,
            r.total,
            r.fear
         FROM rolls r
         JOIN campaign_character cc ON cc.characterID = r.characterID
         JOIN character ch ON ch.characterID = r.characterID
         WHERE cc.campaignID = :cid
         ORDER BY r.rollID DESC
         LIMIT {$limit}",
        [':cid' => $cid]
    );

    if (!is_array($rolls)) $rolls = [];
} catch (Throwable $e) {
    $rolls = [];
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <title>GM Live View – <?= htmlspecialchars((string)$camp['name'], ENT_QUOTES, 'UTF-8') ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../Global/styles.css" rel="stylesheet">

    <style>
        .character-frame {
            height: 650px;
            width: 100%;
            border: none;
            display: block;
        }
        .accordion-button { font-weight: 600; }

        /* --- Rolls Table Styling --- */
        .rolls-card {
            border: 1px solid rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .04);
            border-radius: 1rem;
            overflow: hidden;
        }
        .rolls-card .card-h {
            padding: .85rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, .10);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
        }
        .rolls-card .card-h h5 { margin: 0; font-weight: 800; letter-spacing: -0.02em; }
        .rolls-table { width: 100%; border-collapse: collapse; }
        .rolls-table th, .rolls-table td {
            padding: .65rem .75rem;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
            vertical-align: middle;
        }
        .rolls-table th {
            font-size: .78rem;
            letter-spacing: .10em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .70);
            background: rgba(255, 255, 255, .03);
        }
        .rolls-table tr:last-child td { border-bottom: none; }
        .rolls-dice { font-weight: 800; white-space: nowrap; }
        .rolls-total { text-align: right; font-weight: 900; }

        .roll-hope { background: rgba(245, 158, 11, .12) !important; }
        .roll-fear { background: rgba(59, 130, 246, .12) !important; }
        .roll-neutral { background: transparent !important; }

        .badge-hope {
            border: 1px solid rgba(245, 158, 11, .55);
            background: rgba(245, 158, 11, .16);
            color: rgba(245, 158, 11, .95);
            font-weight: 800;
        }
        .badge-fear {
            border: 1px solid rgba(59, 130, 246, .55);
            background: rgba(59, 130, 246, .16);
            color: rgba(59, 130, 246, .95);
            font-weight: 800;
        }
        .badge-neutral {
            border: 1px solid rgba(255, 255, 255, .20);
            background: rgba(255, 255, 255, .06);
            color: rgba(255, 255, 255, .80);
            font-weight: 800;
        }

        @media (max-width: 720px) { .hide-sm { display: none; } }
    </style>
</head>

<body>
<?php include __DIR__ . "/../Global/nav.html"; ?>

<div class="container py-4">
    <a class="btn btn-outline-light" href="/Dashboard/index.php">
        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
    </a>
    <h3 class="mb-3">GM Live View: <?= htmlspecialchars((string)$camp['name'], ENT_QUOTES, 'UTF-8') ?></h3>

    <!-- ROLLS TOP TABLE -->
    <div class="rolls-card mb-4" id="rollsCard" data-campaign-id="<?= (int)$cid ?>">
        <div class="card-h">
            <h5 class="mb-0">Rolls (Campaign)</h5>
            <div class="text-secondary small">
                <span id="rollsStatus">Auto refresh</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="rolls-table">
                <thead>
                <tr>
                    <th style="width: 34%;">Character</th>
                    <th>Dice</th>
                    <th class="hide-sm" style="width: 110px;">Type</th>
                    <th style="width: 110px; text-align:right;">Total</th>
                </tr>
                </thead>
                <tbody id="rollsTbody">
                <?php if (!$rolls): ?>
                    <tr>
                        <td colspan="4" class="text-secondary p-3">No rolls yet for this campaign.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rolls as $r): ?>
                        <?php
                        $fearRaw = $r['fear'] ?? null;
                        $fear = ($fearRaw === null) ? null : (int)$fearRaw;

                        $rowClass = ($fear === 0) ? 'roll-hope' : (($fear === 1) ? 'roll-fear' : 'roll-neutral');
                        $badgeClass = ($fear === 0) ? 'badge-hope' : (($fear === 1) ? 'badge-fear' : 'badge-neutral');
                        $label = ($fear === 0) ? 'HOPE' : (($fear === 1) ? 'FEAR' : 'ROLL');
                        ?>
                        <tr class="<?= $rowClass ?>" data-rollid="<?= (int)($r['rollID'] ?? 0) ?>">
                            <td style="font-weight:800;"><?= htmlspecialchars((string)($r['characterName'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="rolls-dice"><?= htmlspecialchars((string)($r['dice'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="hide-sm"><span class="badge rounded-pill <?= $badgeClass ?>"><?= $label ?></span></td>
                            <td class="rolls-total"><?= (int)($r['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!$characters): ?>
        <div class="text-secondary">No characters assigned.</div>
    <?php else: ?>

        <div class="accordion" id="gmAccordion">
            <?php foreach ($characters as $i => $ch): ?>
                <div class="accordion-item bg-dark border-secondary mb-3">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-light"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#collapse<?= $i ?>">
                            <?= htmlspecialchars((string)$ch['name'], ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </h2>

                    <div id="collapse<?= $i ?>"
                         class="accordion-collapse collapse"
                         data-bs-parent="#gmAccordion">

                        <div class="accordion-body p-0">

                            <div class="frame-wrap position-relative"
                                 data-character-id="<?= (int)$ch['characterID'] ?>">

                                <iframe
                                    class="character-frame frame-a"
                                    src="../CharacterSheet/character_view.php?characterID=<?= (int)$ch['characterID'] ?>&readonly=1">
                                </iframe>

                                <iframe
                                    class="character-frame frame-b d-none"
                                    src="about:blank">
                                </iframe>

                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* -------- Live refresh without flicker + without scroll jump -------- */

function refreshVisibleFrames() {
    document.querySelectorAll('.accordion-collapse.show .frame-wrap')
        .forEach(wrap => {

            const cid = wrap.dataset.characterId;
            const a = wrap.querySelector('.frame-a');
            const b = wrap.querySelector('.frame-b');

            const aVisible = !a.classList.contains('d-none');
            const visible = aVisible ? a : b;
            const hidden  = aVisible ? b : a;

            // Save scroll position
            let scrollTop = 0;
            try {
                scrollTop = visible.contentWindow.document.documentElement.scrollTop ||
                    visible.contentWindow.document.body.scrollTop ||
                    0;
            } catch (e) {}

            const url = `../CharacterSheet/character_view.php?characterID=${encodeURIComponent(cid)}&readonly=1&t=${Date.now()}`;

            hidden.onload = () => {
                try {
                    hidden.contentWindow.document.documentElement.scrollTop = scrollTop;
                    hidden.contentWindow.document.body.scrollTop = scrollTop;
                } catch (e) {}

                visible.classList.add('d-none');
                hidden.classList.remove('d-none');
                hidden.onload = null;
            };

            hidden.src = url;
        });
}

// When a panel opens: refresh once immediately (so it’s current)
document.getElementById('gmAccordion')?.addEventListener('shown.bs.collapse', () => {
    refreshVisibleFrames();
});

// Interval: refresh visible frames
setInterval(() => {
    refreshVisibleFrames();
}, 5000);
</script>

<script>
// --- helpers ---
function escHtml(s) {
    return (s ?? '').toString()
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function rowMeta(fearVal) {
    if (fearVal === 0) return { rowClass: 'roll-hope', badgeClass: 'badge-hope', label: 'HOPE' };
    if (fearVal === 1) return { rowClass: 'roll-fear', badgeClass: 'badge-fear', label: 'FEAR' };
    return { rowClass: 'roll-neutral', badgeClass: 'badge-neutral', label: 'ROLL' };
}

function renderRollRows(rows) {
    const tbody = document.getElementById('rollsTbody');
    if (!tbody) return;

    if (!Array.isArray(rows) || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-secondary p-3">No rolls yet for this campaign.</td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const rollID = Number(r.rollID || 0);
        const fearRaw = (r.fear === undefined ? null : r.fear);
        const fear = (fearRaw === null ? null : Number(fearRaw));
        const meta = rowMeta((fear === 0 || fear === 1) ? fear : null);

        return `
          <tr class="${meta.rowClass}" data-rollid="${rollID}">
            <td style="font-weight:800;">${escHtml(r.characterName || '—')}</td>
            <td class="rolls-dice">${escHtml(r.dice || '')}</td>
            <td class="hide-sm"><span class="badge rounded-pill ${meta.badgeClass}">${meta.label}</span></td>
            <td class="rolls-total">${Number(r.total || 0)}</td>
          </tr>
        `;
    }).join('');
}

let lastTopRollId = 0;

async function refreshRolls() {
    const card = document.getElementById('rollsCard');
    const status = document.getElementById('rollsStatus');
    if (!card) return;

    const campaignID = card.dataset.campaignId || '';
    if (!campaignID) return;

    try {
        status && (status.textContent = 'Refreshing…');

        const url = `./api_gm_live.php?action=rolls_list&campaignID=${encodeURIComponent(campaignID)}&limit=50&t=${Date.now()}`;
        const res = await fetch(url, { method: 'GET' });
        const j = await res.json();

        if (!j || !j.ok) {
            status && (status.textContent = j?.error ? `Error: ${j.error}` : 'Error');
            return;
        }

        const rows = Array.isArray(j.rows) ? j.rows : [];
        const topId = rows.length ? Number(rows[0].rollID || 0) : 0;

        if (topId !== lastTopRollId) {
            renderRollRows(rows);
            lastTopRollId = topId;
        }

        status && (status.textContent = 'Auto refresh');
    } catch (e) {
        status && (status.textContent = 'Offline / Error');
    }
}

refreshRolls();
setInterval(refreshRolls, 5000);
</script>
</body>
</html>