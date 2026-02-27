<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

$readonly = (int)($_GET['readonly'] ?? 0) === 1;

// -------------------------------
// Bootstrap / App wiring
// -------------------------------
$root = dirname(__DIR__);
require_once $root . '/Database/Database.php';

$dbFile = $root . '/Database/Daggerheart.db';
$db = Database::getInstance($dbFile);

$userId = null;
if (isset($_SESSION['userID']) && is_numeric($_SESSION['userID'])) {
  $userId = (int)$_SESSION['userID'];
}

// Helpers needed by API + view
function h(?string $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function abort_page(int $status, string $title, string $message): never {
  http_response_code($status);
  echo "<!doctype html><html><head><meta charset='utf-8'><title>" . h($title) . "</title></head><body style='font-family:system-ui;padding:24px;'><h2>"
    . h($title) . "</h2><p>" . h($message) . "</p></body></html>";
  exit;
}
function get_int_query(string $key, ?int $fallback = null): ?int {
  if (!isset($_GET[$key])) return $fallback;
  $v = filter_var($_GET[$key], FILTER_VALIDATE_INT);
  return ($v === false) ? $fallback : (int)$v;
}

// -------------------------------
// API handler (POST exits early)
// -------------------------------
require_once __DIR__ . '/partials/character_view_api.php';

// -------------------------------
// Load page data (GET)
// -------------------------------
$characterId = get_int_query('characterID', get_int_query('id'));
if ($characterId === null || $characterId <= 0) abort_page(400, 'Missing characterID', 'Use ?characterID=123');

/* Ownership check (server-side view render)
   - readonly=1 => darf immer angezeigt werden (ohne Login/Ownership)
   - readonly=0 => Login + Ownership required
*/
if (!$readonly) {
  if ($userId === null) abort_page(401, 'Not logged in', 'Please log in first.');

  $own = $db->fetch(
    'SELECT characterID, userID
     FROM character
     WHERE characterID = :id',
    [':id' => $characterId]
  );
  if (!$own) abort_page(404, 'Character not found', "No character for characterID={$characterId}");
  if ((int)$own['userID'] !== $userId) abort_page(403, 'Forbidden', 'You do not own this character.');
}

function format_mod(int $v): string {
  return ($v >= 0 ? '+' : '') . (string)$v;
}
function wv(?array $w, string $key, string $fallback = ''): string {
  if (!$w) return $fallback;
  return (string)($w[$key] ?? $fallback);
}

function load_hope_feature_description(Database $db, int $classId): string {
  if ($classId <= 0) return '';
  $sql = "
    SELECT description, hope
    FROM feature
    WHERE classID = :cid
      AND hope = 3
    LIMIT 1
  ";
  $row = $db->fetch($sql, [':cid' => $classId]);
  if (!$row) return '';
  $hope = (int)($row['hope'] ?? 0);
  $desc = (string)($row['description'] ?? '');
  if ($desc === '') return '';
  return "Spend {$hope} {$desc}";
}

function load_armor_by_id(Database $db, int $armorId): ?array {
  if ($armorId <= 0) return null;
  return $db->fetch(
    "
      SELECT armorID, name, base_score, major_threshold, severe_threshold, feature
      FROM armor
      WHERE armorID = :aid
      LIMIT 1
    ",
    [':aid' => $armorId]
  );
}

function load_character_weapon(Database $db, int $characterId, int $primary): ?array {
  return $db->fetch(
    '
      SELECT
        cw.weaponID,
        cw."primary",
        w.name    AS weapon_name,
        w.trait   AS weapon_trait,
        w.range   AS weapon_range,
        w.damage  AS weapon_damage,
        w.feature AS weapon_feature
      FROM character_weapon cw
      JOIN weapon w ON w.weaponID = cw.weaponID
      WHERE cw.characterID = :cid
        AND cw."primary" = :p
      LIMIT 1
    ',
    [':cid' => $characterId, ':p' => $primary]
  );
}

function load_character_experiences(Database $db, int $characterId): array {
  return $db->fetchAll(
    "
      SELECT experienceID, experience, mod
      FROM character_experience
      WHERE characterID = :cid
      ORDER BY experienceID ASC
    ",
    [':cid' => $characterId]
  );
}

function load_inventory(Database $db, int $characterId): array {
  return $db->fetchAll(
    "
      SELECT itemID, Item, Description, Amount
      FROM character_inventory
      WHERE characterID = :cid
      ORDER BY itemID DESC
    ",
    [':cid' => $characterId]
  );
}

function load_roll_history(Database $db, int $userId, int $characterId, int $limit = 10): array {
  return $db->fetchAll(
    "
      SELECT rollID, dice, total, fear
      FROM rolls
      WHERE userID = :uid
        AND characterID = :cid
      ORDER BY rollID DESC
      LIMIT {$limit}
    ",
    [':uid' => $userId, ':cid' => $characterId]
  );
}

/* -------- Profile (with joins) -------- */
$profile = $db->fetch(
  "
    SELECT
      c.characterID,
      c.userID,
      c.name,
      c.pronouns,
      c.classID,
      c.subclassID,
      c.heritageID,
      c.communityID,
      c.armor AS armor_tracker,
      c.Level AS level,

      h.name  AS heritage_name,
      co.name AS community_name,
      cl.name AS class_name,
      sc.name AS subclass_name,

      cl.starting_evasion_score AS starting_evasion_score
    FROM character c
    LEFT JOIN heritage  h  ON h.heritageID   = c.heritageID
    LEFT JOIN community co ON co.communityID = c.communityID
    LEFT JOIN class     cl ON cl.classID     = c.classID
    LEFT JOIN subclass  sc ON sc.subclassID  = c.subclassID
    WHERE c.characterID = :id
    LIMIT 1
  ",
  [':id' => $characterId]
);
if (!$profile) abort_page(404, 'Character not found', "No character for characterID={$characterId}");

/* -------- Stats (traits + trackers) -------- */
$stats = $db->fetch(
  "
    SELECT
      Agility, Strength, Finesse, Instinct, Presence, Knowledge,
      Hope, Stress, HP
    FROM character_stats
    WHERE characterID = :id
    LIMIT 1
  ",
  [':id' => $characterId]
) ?? [
  'Agility' => 0, 'Strength' => 0, 'Finesse' => 0, 'Instinct' => 0, 'Presence' => 0, 'Knowledge' => 0,
  'Hope' => 0, 'Stress' => 0, 'HP' => 0
];

/* -------- Derived/Extras -------- */
$classId      = (int)($profile['classID'] ?? 0);
$armorTracker = (int)($profile['armor_tracker'] ?? 0);
$armorId      = (int)($profile['armorID'] ?? 0);

$hopeFeatureDesc = load_hope_feature_description($db, $classId);

$evasion = 0;
if (isset($profile['starting_evasion_score']) && is_numeric($profile['starting_evasion_score'])) {
  $evasion = (int)$profile['starting_evasion_score'];
}

$equippedArmor = load_armor_by_id($db, $armorId);
$armorName        = (string)($equippedArmor['name'] ?? '—');
$armorBaseScore   = (int)($equippedArmor['base_score'] ?? 0);
$armorMajorTh     = (int)($equippedArmor['major_threshold'] ?? 0);
$armorSevereTh    = (int)($equippedArmor['severe_threshold'] ?? 0);
$armorFeatureText = (string)($equippedArmor['feature'] ?? '');

$primaryWeapon   = load_character_weapon($db, $characterId, 1);
$secondaryWeapon = load_character_weapon($db, $characterId, 0);

$experienceRows = load_character_experiences($db, $characterId);
$inventoryRows  = load_inventory($db, $characterId);

/* Roll History: nur wenn eingeloggt (sonst leer) */
$rollHistory = ($userId !== null)
  ? load_roll_history($db, $userId, $characterId, 10)
  : [];

function render_stat_card(string $bindKey, string $name, int $value, array $tags): void {
?>
  <div class="stat" data-bind-group="stat" data-bind-key="<?= h($bindKey) ?>">
    <p class="name"><?= h($name) ?></p>
    <div class="value">
      <button type="button"
        class="score-btn roll-stat"
        data-stat="<?= h($name) ?>"
        data-mod="<?= (int)$value ?>"
        title="Roll 2d12 <?= h(format_mod($value)) ?> (Duality Dice)"><?= h(format_mod($value)) ?></button>
      <div class="tags">
        <span><?= h($tags[0] ?? '') ?></span><br />
        <span><?= h($tags[1] ?? '') ?></span><br />
        <span><?= h($tags[2] ?? '') ?></span>
      </div>
    </div>
  </div>
<?php
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Character Sheet</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/CharacterSheet/assets/character_view.css" />

  <?php if ($readonly): ?>
    <style>
      body { caret-color: transparent; }
      a, button, input, textarea, [role="button"], [onclick] {
        pointer-events: none !important;
        cursor: default !important;
      }
      *:focus { outline: none !important; box-shadow: none !important; }
    </style>
  <?php endif; ?>
</head>

<body>
  <header class="py-4">
    <div class="container">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <a class="btn btn-outline-light" href="/Dashboard/index.php">
          <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
        </a>

        <div class="d-flex gap-2">
          <button class="btn btn-outline-light" id="btnDualityDice" type="button" title="Roll Duality Dice (2d12)">
            <i class="bi bi-dice-5 me-2"></i>Duality Dice
          </button>
          <button class="btn btn-outline-light" id="btnToggleTheme" type="button" title="Toggle theme">
            <i class="bi bi-sun"></i>
          </button>
        </div>
      </div>
    </div>
  </header>

  <main class="container pb-5">
    <section class="glass sheet">

      <div class="sheet-topbar">
        <input type="hidden" id="characterID" value="<?= (int)$characterId ?>">

        <div class="topbar-grid">
          <div class="field"><label>Name</label><input type="text" value="<?= h((string)($profile['name'] ?? '')) ?>" readonly /></div>
          <div class="field"><label>Pronouns</label><input type="text" value="<?= h((string)($profile['pronouns'] ?? '')) ?>" readonly /></div>
          <div class="field"><label>Heritage</label><input type="text" value="<?= h((string)($profile['heritage_name'] ?? '—')) ?>" readonly /></div>
          <div class="field"><label>Community</label><input type="text" value="<?= h((string)($profile['community_name'] ?? '—')) ?>" readonly /></div>
          <div class="field"><label>Class</label><input type="text" value="<?= h((string)($profile['class_name'] ?? '—')) ?>" readonly /></div>
          <div class="field"><label>Subclass</label><input type="text" value="<?= h((string)($profile['subclass_name'] ?? '—')) ?>" readonly /></div>
          <div class="field"><label>Level</label><input type="number" value="<?= (int)($profile['level'] ?? 1) ?>" readonly /></div>
        </div>

        <div class="divider"></div>

        <div class="stat-grid">
          <?php
          render_stat_card('agility',   'Agility',   (int)($stats['Agility'] ?? 0),   ['Sprint', 'Leap', 'Maneuver']);
          render_stat_card('strength',  'Strength',  (int)($stats['Strength'] ?? 0),  ['Lift', 'Smash', 'Grapple']);
          render_stat_card('finesse',   'Finesse',   (int)($stats['Finesse'] ?? 0),   ['Control', 'Hide', 'Tinker']);
          render_stat_card('instinct',  'Instinct',  (int)($stats['Instinct'] ?? 0),  ['Perceive', 'Sense', 'Navigate']);
          render_stat_card('presence',  'Presence',  (int)($stats['Presence'] ?? 0),  ['Charm', 'Perform', 'Deceive']);
          render_stat_card('knowledge', 'Knowledge', (int)($stats['Knowledge'] ?? 0), ['Recall', 'Analyze', 'Comprehend']);
          ?>
        </div>
      </div>

      <div class="sheet-grid">
        <div class="row g-3">

          <!-- LEFT -->
          <div class="col-12 col-lg-6">
            <div class="sheet-block">
              <div class="text-uppercase small muted" style="letter-spacing:.10em;">Defense / Trackers</div>
              <div class="divider"></div>

              <div class="track-row mb-2">
                <div class="track-label">HP</div>
                <div class="dots" data-max="9" data-track="HP" data-value="<?= (int)($stats['HP'] ?? 0) ?>"></div>
              </div>
              <div class="track-row mb-2">
                <div class="track-label">Stress</div>
                <div class="dots" data-max="6" data-track="Stress" data-value="<?= (int)($stats['Stress'] ?? 0) ?>"></div>
              </div>
              <div class="track-row mb-2">
                <div class="track-label">Hope</div>
                <div class="dots" data-max="6" data-track="Hope" data-value="<?= (int)($stats['Hope'] ?? 0) ?>"></div>
              </div>
              <div class="track-row">
                <div class="track-label">Armor</div>
                <div class="dots" data-max="9" data-track="Armor" data-value="<?= (int)$armorTracker ?>"></div>
              </div>

              <div class="field mt-3">
                <label>Hope Feature</label>
                <textarea readonly id="hopeFeatureText"><?= h($hopeFeatureDesc) ?></textarea>
              </div>

              <div class="divider"></div>

              <div class="block-title">
                <h3>Experiences</h3>
                <span class="chip"><i class="bi bi-journal-text"></i> Read Only</span>
              </div>

              <?php if (!$experienceRows): ?>
                <div class="inv-empty">No experiences available.</div>
              <?php else: ?>
                <div class="exp-list">
                  <?php foreach ($experienceRows as $r): ?>
                    <?php $m = (int)($r['mod'] ?? 0); ?>
                    <div class="exp-row">
                      <div class="exp-name"><?= h((string)($r['experience'] ?? '')) ?></div>
                      <button type="button" class="exp-mod" title="Modifier (no function yet)">
                        <?= $m >= 0 ? '+' : '' ?><?= $m ?>
                      </button>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <div class="divider"></div>

              <div class="def-grid">
                <div class="field">
                  <label>Evasion</label>
                  <input class="pill-input" type="number" value="<?= (int)$evasion ?>" readonly />
                </div>
                <div class="field">
                  <label>Armor</label>
                  <input class="pill-input" type="number" value="<?= (int)$armorBaseScore ?>" readonly />
                </div>
              </div>

              <div class="divider"></div>

              <div class="block-title">
                <h3>Armor</h3>
                <span class="chip"><i class="bi bi-shield"></i> Loadout</span>
              </div>

              <div class="field">
                <label>Active Armor</label>
                <input type="text" value="<?= h($armorName) ?>" readonly />
              </div>

              <div class="text-uppercase small muted" style="letter-spacing:.10em;">Thresholds</div>

              <div class="thresholds">
                <div class="threshold-row">
                  <input class="pill-input" type="text" value="Minor Damage" readonly />
                  <div class="arrow">→</div>
                  <input class="pill-input" type="text" value="<?= $armorMajorTh ?: '—' ?>" readonly />
                </div>
                <div class="threshold-row">
                  <input class="pill-input" type="text" value="Major Damage" readonly />
                  <div class="arrow">→</div>
                  <input class="pill-input" type="text" value="<?= $armorSevereTh ?: '—' ?>" readonly />
                </div>
              </div>

              <div class="field mt-3">
                <label>Armor Feature</label>
                <textarea readonly><?= h($armorFeatureText) ?></textarea>
              </div>
            </div>
          </div>

          <!-- RIGHT -->
          <div class="col-12 col-lg-6">
            <div class="sheet-block">
              <div class="block-title">
                <h3>Weapons</h3>
                <span class="chip"><i class="bi bi-bullseye"></i> Loadout</span>
              </div>

              <div class="inner-card">
                <div class="section-h">Primary</div>
                <div class="field">
                  <label>Weapon</label>
                  <input type="text" value="<?= h($primaryWeapon ? wv($primaryWeapon, 'weapon_name', '—') : '—') ?>" readonly />
                </div>
                <div class="two-col">
                  <div class="field"><label>Trait</label><input type="text" value="<?= h($primaryWeapon ? wv($primaryWeapon, 'weapon_trait', '') : '') ?>" readonly /></div>
                  <div class="field"><label>Range</label><input type="text" value="<?= h($primaryWeapon ? wv($primaryWeapon, 'weapon_range', '') : '') ?>" readonly /></div>

                  <div class="field">
                    <label>Damage</label>
                    <?php $dmg1 = trim($primaryWeapon ? wv($primaryWeapon, 'weapon_damage', '') : ''); ?>
                    <button type="button" class="dice-btn roll-dice" data-dice="<?= h($dmg1) ?>" <?= $dmg1 === '' ? 'disabled' : '' ?>>
                      <span class="dice-text"><?= h($dmg1) ?></span>
                      <span class="dice-ico"><i class="bi bi-dice-5"></i></span>
                    </button>
                  </div>

                  <div class="field"><label>Feature</label><input type="text" value="<?= h($primaryWeapon ? wv($primaryWeapon, 'weapon_feature', '') : '') ?>" readonly /></div>
                </div>

                <div class="divider"></div>

                <div class="section-h">Secondary</div>
                <div class="field">
                  <label>Weapon</label>
                  <input type="text" value="<?= h($secondaryWeapon ? wv($secondaryWeapon, 'weapon_name', '—') : '—') ?>" readonly />
                </div>
                <div class="two-col">
                  <div class="field"><label>Trait</label><input type="text" value="<?= h($secondaryWeapon ? wv($secondaryWeapon, 'weapon_trait', '') : '') ?>" readonly /></div>
                  <div class="field"><label>Range</label><input type="text" value="<?= h($secondaryWeapon ? wv($secondaryWeapon, 'weapon_range', '') : '') ?>" readonly /></div>

                  <div class="field">
                    <label>Damage</label>
                    <?php $dmg2 = trim($secondaryWeapon ? wv($secondaryWeapon, 'weapon_damage', '') : ''); ?>
                    <button type="button" class="dice-btn roll-dice" data-dice="<?= h($dmg2) ?>" <?= $dmg2 === '' ? 'disabled' : '' ?>>
                      <span class="dice-text"><?= h($dmg2) ?></span>
                      <span class="dice-ico"><i class="bi bi-dice-5"></i></span>
                    </button>
                  </div>

                  <div class="field"><label>Feature</label><input type="text" value="<?= h($secondaryWeapon ? wv($secondaryWeapon, 'weapon_feature', '') : '') ?>" readonly /></div>
                </div>

                <div class="divider"></div>

                <!-- Inventory (CRUD) -->
                <div class="block-title">
                  <h3>Inventory</h3>
                  <span class="chip"><i class="bi bi-box-seam"></i> Items</span>
                </div>

                <!-- (dein Inventory bleibt wie gehabt) -->

                <div class="divider"></div>

                <!-- Roll History -->
                <div class="block-title">
                  <h3>Roll History</h3>
                  <span class="chip"><i class="bi bi-clock-history"></i> Last 10</span>
                </div>

                <div id="rollHistoryWrap">
                  <?php if (!$rollHistory): ?>
                    <div class="inv-empty">No rolls yet.</div>
                  <?php else: ?>
                    <div class="roll-list" id="rollList">
                      <?php foreach ($rollHistory as $rh): ?>
                        <?php
                        $rid  = (int)($rh['rollID'] ?? 0);
                        $dice = (string)($rh['dice'] ?? '');
                        $tot  = (int)($rh['total'] ?? 0);
                        $fearRaw = $rh['fear'] ?? null;
                        $fear = ($fearRaw === null) ? null : (int)$fearRaw;
                        $cls = ($fear === 1) ? 'fear' : (($fear === 0) ? 'hope' : 'neutral');
                        $label = ($fear === 1) ? 'FEAR' : (($fear === 0) ? 'HOPE' : 'ROLL');
                        ?>
                        <div class="roll-item">
                          <div class="roll-left">
                            <div class="roll-dice" title="<?= h($dice) ?>"><?= h($dice) ?></div>
                            <div class="roll-id">#<?= $rid ?></div>
                          </div>
                          <span class="roll-badge <?= $cls ?>"><?= $label ?></span>
                          <div class="roll-total"><?= $tot ?></div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>

              </div>
            </div>
          </div>

        </div>
      </div>

    </section>
  </main>

  <div class="roll-toast" id="rollToast" aria-live="polite" aria-atomic="true">
    <div class="rt-top">
      <p class="rt-title mb-0" id="rtTitle">Roll</p>
      <span class="rt-chip" id="rtChip">ROLL</span>
    </div>
    <div class="rt-body" id="rtBody"></div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script>
    window.CHAR_VIEW = {
      readonly: <?= $readonly ? 'true' : 'false' ?>,
      stateApi: '/CharacterSheet/api_character_view_state.php'
    };
  </script>
  <script src="/CharacterSheet/assets/character_view.js"></script>

  <script>
  // ✅ GM Live Refresh Hook:
  // Parent (gm_live.php) kann alle 5s __gmRefresh() aufrufen.
  // Diese Funktion macht einen API-Call und aktualisiert Trackers + Roll History im DOM.
  (function () {
    function buildDots(el, value, max) {
      const v = Math.max(0, Math.min(Number(value || 0), Number(max || 0)));
      const m = Math.max(0, Number(max || 0));

      // rebuild
      el.innerHTML = '';
      for (let i = 1; i <= m; i++) {
        const s = document.createElement('span');
        s.className = 'dot' + (i <= v ? ' filled' : '');
        el.appendChild(s);
      }
      el.setAttribute('data-value', String(v));
    }

    function renderRollHistory(rows) {
      const wrap = document.getElementById('rollHistoryWrap');
      if (!wrap) return;

      if (!Array.isArray(rows) || rows.length === 0) {
        wrap.innerHTML = `<div class="inv-empty">No rolls yet.</div>`;
        return;
      }

      wrap.innerHTML = `
        <div class="roll-list" id="rollList">
          ${rows.map(r => {
            const rid = Number(r.rollID || 0);
            const dice = (r.dice ?? '').toString();
            const tot = Number(r.total || 0);
            const fear = (r.fear === null || r.fear === undefined) ? null : Number(r.fear);
            const cls = (fear === 1) ? 'fear' : ((fear === 0) ? 'hope' : 'neutral');
            const label = (fear === 1) ? 'FEAR' : ((fear === 0) ? 'HOPE' : 'ROLL');
            return `
              <div class="roll-item">
                <div class="roll-left">
                  <div class="roll-dice" title="${dice.replaceAll('"','&quot;')}">${dice.replaceAll('<','&lt;').replaceAll('>','&gt;')}</div>
                  <div class="roll-id">#${rid}</div>
                </div>
                <span class="roll-badge ${cls}">${label}</span>
                <div class="roll-total">${tot}</div>
              </div>
            `;
          }).join('')}
        </div>
      `;
    }

    window.__gmRefresh = async function () {
      const id = Number(document.getElementById('characterID')?.value || 0);
      if (!id || !window.CHAR_VIEW?.stateApi) return;

      const url = `${window.CHAR_VIEW.stateApi}?characterID=${encodeURIComponent(String(id))}&t=${Date.now()}`;

      try {
        const res = await fetch(url, { method: 'GET', cache: 'no-store' });
        const j = await res.json();
        if (!j || !j.ok) return;

        // Trackers
        const hp     = Number(j.stats?.HP ?? 0);
        const stress = Number(j.stats?.Stress ?? 0);
        const hope   = Number(j.stats?.Hope ?? 0);
        const armor  = Number(j.character?.armor_tracker ?? 0);

        document.querySelectorAll('.dots').forEach(el => {
          const track = el.getAttribute('data-track');
          const max = Number(el.getAttribute('data-max') || 0);

          if (track === 'HP') buildDots(el, hp, max);
          else if (track === 'Stress') buildDots(el, stress, max);
          else if (track === 'Hope') buildDots(el, hope, max);
          else if (track === 'Armor') buildDots(el, armor, max);
        });

        // Hope Feature text (falls sich mal ändert)
        const hf = document.getElementById('hopeFeatureText');
        if (hf && typeof j.hope_feature?.text === 'string') {
          hf.value = j.hope_feature.text;
        }

        // Roll History
        if (Array.isArray(j.roll_history)) {
          renderRollHistory(j.roll_history);
        }
      } catch (e) {
        // ignore
      }
    };
  })();
  </script>
</body>
</html>