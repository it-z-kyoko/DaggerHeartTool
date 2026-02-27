<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/partials/auth.php';
require_once __DIR__ . '/partials/db.php';

$pageTitle = 'Dashboard';
$userId = (int)$_SESSION['auth']['userID'];

/**
 * Flash helper
 */
function flash_set(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function flash_get(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

try {
    // ------------------------------------------------------------
    // Optional: ensure campaign_character exists (if not present yet)
    // (You already have it, so you can keep this or remove it)
    // ------------------------------------------------------------
    $db->execute("
        CREATE TABLE IF NOT EXISTS campaign_character (
            characterID INTEGER NOT NULL,
            campaignID  INTEGER NOT NULL
        )
    ");

    // ------------------------------------------------------------
    // User characters for the join dropdown (all)
    // ------------------------------------------------------------
    $myCharactersForJoin = $db->fetchAll(
        "SELECT characterID, name
         FROM character
         WHERE userID = :uid
         ORDER BY characterID DESC",
        [':uid' => $userId]
    );

    // ------------------------------------------------------------
    // Handle join campaign (POST) -> campaign_character
    // ------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'join_campaign') {
        $code = trim((string)($_POST['campaign_code'] ?? ''));
        $characterId = (int)($_POST['characterID'] ?? 0);

        if ($code === '') {
            flash_set('danger', 'Please enter a code.');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        if ($characterId <= 0) {
            flash_set('danger', 'Please select a character.');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        // Character ownership check
        $char = $db->fetch(
            "SELECT characterID, userID, name
             FROM character
             WHERE characterID = :chid
             LIMIT 1",
            [':chid' => $characterId]
        );
        if (!$char || (int)$char['userID'] !== $userId) {
            flash_set('danger', 'Invalid character (not yours).');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        // Campaign by code
        $camp = $db->fetch(
            "SELECT campaignID, name, userID, code
             FROM campaign
             WHERE code = :code
             LIMIT 1",
            [':code' => $code]
        );

        if (!$camp) {
            flash_set('danger', 'No match: campaign code is invalid.');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        $campaignId = (int)$camp['campaignID'];

        // Owner should not join own campaign
        if ((int)$camp['userID'] === $userId) {
            flash_set('warning', 'You are already the owner of this campaign.');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        // Already joined? (same character in same campaign)
        $exists = $db->fetch(
            "SELECT 1
             FROM campaign_character
             WHERE campaignID = :cid AND characterID = :chid
             LIMIT 1",
            [':cid' => $campaignId, ':chid' => $characterId]
        );

        if ($exists) {
            flash_set('warning', 'This character is already assigned to this campaign.');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        // Insert mapping
        $db->execute(
            "INSERT INTO campaign_character (campaignID, characterID)
             VALUES (:cid, :chid)",
            [':cid' => $campaignId, ':chid' => $characterId]
        );

        flash_set(
            'success',
            'Joined: “' . (string)$camp['name'] . '” with character “' . (string)$char['name'] . '”.'
        );

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // ------------------------------------------------------------
    // Characters: short list for the dashboard
    // ------------------------------------------------------------
    $characters = $db->fetchAll(
        "SELECT
            c.characterID,
            c.name AS characterName,
            COALESCE(cl.name, '') AS className,
            COALESCE(sc.name, '') AS subclassName,
            COALESCE(c.Level, 1) AS level
         FROM character c
         LEFT JOIN class cl ON cl.classID = c.classID
         LEFT JOIN subclass sc ON sc.subclassID = c.subclassID
         WHERE c.userID = :uid
         ORDER BY c.characterID DESC
         LIMIT 6",
        [':uid' => $userId]
    );

    // ------------------------------------------------------------
    // Campaigns: Owner + Member via campaign_character
    // memberCharacterID = an assigned character of the user (MIN)
    // ------------------------------------------------------------
    $campaigns = $db->fetchAll(
        "SELECT
            cp.campaignID,
            cp.name AS campaignName,
            CASE WHEN cp.userID = :uid THEN 1 ELSE 0 END AS isOwner,
            MIN(CASE WHEN ch.userID = :uid THEN ch.characterID END) AS memberCharacterID
         FROM campaign cp
         LEFT JOIN campaign_character cc
                ON cc.campaignID = cp.campaignID
         LEFT JOIN character ch
                ON ch.characterID = cc.characterID
         WHERE cp.userID = :uid
            OR ch.userID = :uid
         GROUP BY cp.campaignID
         ORDER BY cp.campaignID DESC
         LIMIT 6",
        [':uid' => $userId]
    );

} catch (Throwable $e) {
    http_response_code(500);
    echo "<pre style='color:#ffb4b4;background:#200;padding:12px;border-radius:8px;'>";
    echo "Dashboard Error:\n" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo "\n</pre>";
    exit;
}

require_once __DIR__ . '/partials/header.php';

$flash = flash_get();
?>

<header class="py-5">
    <?php include __DIR__ . "/../Global/nav.html"; ?>

    <div class="container">
        <div class="glass panel">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="text-uppercase muted" style="letter-spacing:.14em;font-size:.78rem;">Dashboard</div>
                    <h1 class="mb-1" style="letter-spacing:-0.03em;line-height:1.05;">
                        Welcome back, <?= htmlspecialchars($_SESSION['auth']['username'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <div class="muted">Manage characters and campaigns in one place.</div>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="mt-3 alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> mb-0">
                    <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="container pb-5">
    <div class="row g-3 align-items-stretch">

        <!-- Your Characters -->
        <div class="col-12 col-lg-6">
            <section class="glass panel feature h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h3 class="panel-title mb-0">Your Characters</h3>
                        <div class="panel-subtitle mb-0">Quick access to your most recently used sheets.</div>
                    </div>
                    <span class="icon-pill" aria-hidden="true"><i class="bi bi-person-badge"></i></span>
                </div>

                <div class="d-grid gap-2">
                    <?php if (empty($characters)): ?>
                        <div class="muted">No characters yet.</div>
                    <?php else: ?>
                        <?php foreach ($characters as $ch):
                            $charId = (int)$ch['characterID'];
                            $name   = (string)$ch['characterName'];
                            $class  = trim((string)$ch['className']);
                            $sub    = trim((string)$ch['subclassName']);
                            $lvl    = (int)$ch['level'];

                            $parts = [];
                            if ($class !== '') $parts[] = $class;
                            if ($sub !== '')   $parts[] = $sub;
                            $parts[] = "Level $lvl";
                            $meta = implode(' • ', $parts);
                        ?>
                            <a class="char-card" href="/CharacterSheet/character_view.php?characterID=<?= $charId ?>">
                                <div class="flex-grow-1">
                                    <p class="char-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="char-meta"><?= htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <i class="bi bi-chevron-right muted"></i>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <hr class="my-4" style="border-top:1px solid rgba(255,255,255,.10);" />
                <div class="d-flex gap-2">
                    <a class="btn btn-brand flex-grow-1" href="/CharacterCreator/creator.php">
                        <i class="bi bi-person-plus me-2"></i>Create Character
                    </a>
                    <a class="btn btn-outline-light" href="/my_characters.php">
                        <i class="bi bi-collection me-2"></i>All
                    </a>
                </div>
            </section>
        </div>

        <!-- Your Campaigns -->
        <div class="col-12 col-lg-6">
            <section class="glass panel feature h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h3 class="panel-title mb-0">Your Campaigns</h3>
                        <div class="panel-subtitle mb-0">Owner → GM Live, Member → Character Sheet.</div>
                    </div>
                    <span class="icon-pill" aria-hidden="true"><i class="bi bi-journal-bookmark"></i></span>
                </div>

                <div class="d-grid gap-2">
                    <?php if (empty($campaigns)): ?>
                        <div class="muted">No campaigns yet.</div>
                    <?php else: ?>
                        <?php foreach ($campaigns as $cp):
                            $campaignId = (int)$cp['campaignID'];
                            $name = (string)$cp['campaignName'];
                            $isOwner = (int)($cp['isOwner'] ?? 0) === 1;
                            $memberCharId = (int)($cp['memberCharacterID'] ?? 0);

                            // Link: Owner -> gm_live, Member -> character_view of assigned char
                            if ($isOwner) {
                                $href = "/Campaign/gm_live.php?campaignID={$campaignId}";
                            } else {
                                $href = $memberCharId > 0
                                    ? "/CharacterSheet/character_view.php?characterID={$memberCharId}"
                                    : "#";
                            }
                        ?>
                            <a class="char-card" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                               <?= (!$isOwner && $memberCharId <= 0) ? 'onclick="return false;" style="opacity:.65;cursor:not-allowed;"' : '' ?>>
                                <div class="flex-grow-1">
                                    <p class="char-name mb-1"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="char-meta mb-0">
                                        <?= $isOwner ? 'Owner (GM Live)' : 'Member (Sheet)' ?>
                                    </p>
                                </div>
                                <i class="bi bi-chevron-right muted"></i>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <hr class="my-4" style="border-top:1px solid rgba(255,255,255,.10);" />

                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-brand flex-grow-1" href="/Campaign/campaign.php">
                        <i class="bi bi-journal-bookmark me-2"></i>Campaigns
                    </a>

                    <!-- JOIN BUTTON -->
                    <button class="btn btn-outline-light" type="button" data-bs-toggle="modal" data-bs-target="#joinCampaignModal">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Join
                    </button>
                </div>
            </section>
        </div>

    </div>
</main>

<!-- Join Campaign Modal -->
<div class="modal fade" id="joinCampaignModal" tabindex="-1" aria-labelledby="joinCampaignModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass panel">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="joinCampaignModalLabel">Join Campaign</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="modal-body">
          <input type="hidden" name="action" value="join_campaign">

          <div class="mb-3">
            <label class="form-label">Code</label>
            <input
              type="text"
              name="campaign_code"
              class="form-control"
              placeholder="e.g. DH-7K3P9Q"
              autocomplete="off"
              required
            >
          </div>

          <div class="mb-2">
            <label class="form-label">Select Character</label>
            <select class="form-select glass-select" name="characterID" class="form-select" required>
              <option value="" selected disabled>Please choose…</option>
              <?php foreach ($myCharactersForJoin as $row): ?>
                <option value="<?= (int)$row['characterID'] ?>">
                  <?= htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($myCharactersForJoin)): ?>
              <div class="form-text text-warning mt-2">
                You don’t have a character yet. Create a character first, then you can join a campaign.
              </div>
            <?php else: ?>
              <div class="form-text muted mt-2">
                Enter code + select character → mapping is stored in <code>campaign_character</code>.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-brand" <?= empty($myCharactersForJoin) ? 'disabled' : '' ?>>
            <i class="bi bi-check2 me-2"></i>Join
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>