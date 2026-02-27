<?php

declare(strict_types=1);

session_start();

$root = dirname(__DIR__);

if (!isset($_SESSION['userID'])) {
  header('Location: ' . $root . '/Login/login.php');
  exit;
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Campaigns</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../Global/styles.css" rel="stylesheet">

  <style>
    .glass {
      background: rgba(255, 255, 255, .06);
      border: 1px solid rgba(255, 255, 255, .10);
      backdrop-filter: blur(10px);
      border-radius: 18px;
      box-shadow: 0 12px 30px rgba(0, 0, 0, .35);
    }

    .muted {
      opacity: .85;
    }

    .code-pill {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      letter-spacing: .06em;
    }

    tr.row-link {
      cursor: pointer;
    }

    tr.row-link:hover td {
      background: rgba(255, 255, 255, .03);
    }
  </style>
</head>

<body>

  <?php
  $nav = $root . '/Global/nav.html';
  if (file_exists($nav)) readfile($nav);
  ?>

  <div class="container py-4">
    <a class="btn btn-outline-light" href="/Dashboard/index.php">
      <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
    </a>

    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
      <div>
        <h3 class="mb-0">Campaigns</h3>
        <div class="text-secondary">Owner → GM Live. Member → Character Sheet of the assigned character.</div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-light" id="btnReload">
          <i class="bi bi-arrow-repeat"></i>
        </button>
        <button class="btn btn-primary" id="btnNew">
          <i class="bi bi-plus-lg"></i> New Campaign
        </button>
      </div>
    </div>

    <div class="glass p-3">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 26%">Name</th>
              <th>Description</th>
              <th style="width: 16%">Owner</th>
              <th style="width: 18%">Your Character</th>
              <th class="text-end" style="width: 320px">Actions</th>
            </tr>
          </thead>
          <tbody id="tblBody">
            <tr>
              <td colspan="5" class="text-secondary">Loading…</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Modal: Create/Edit -->
  <div class="modal fade" id="modalCampaign" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content glass">
        <div class="modal-header border-0">
          <h5 class="modal-title" id="modalTitle">Campaign</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="campaignID">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input class="form-control" id="name" maxlength="80" placeholder="e.g. The Shattered Crown">
          </div>
          <div class="mb-2">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="description" rows="5" maxlength="2000" placeholder="Short description / Notes"></textarea>
            <div class="text-secondary small mt-1"><span id="descCount">0</span>/2000</div>
          </div>
          <div class="alert alert-danger d-none" id="errBox"></div>
        </div>
        <div class="modal-footer border-0">
          <button class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" id="btnSave"><i class="bi bi-check2"></i> Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Share -->
  <div class="modal fade" id="modalShare" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content glass">
        <div class="modal-header border-0">
          <h5 class="modal-title">Share Campaign</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="shareCampaignID">
          <div class="mb-2 text-secondary">This code can be used to join:</div>

          <div class="d-flex gap-2 align-items-center">
            <input class="form-control code-pill" id="shareCode" readonly value="">
            <button class="btn btn-outline-light" id="btnCopyCode" type="button" title="Copy">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>

          <div class="form-text muted mt-2">Share this code with your players.</div>

          <hr style="border-top:1px solid rgba(255,255,255,.10);" class="my-3" />

          <button class="btn btn-outline-warning w-100" id="btnRegenerate" type="button">
            <i class="bi bi-arrow-clockwise me-2"></i>Generate New Code
          </button>

          <div class="alert alert-danger d-none mt-3" id="shareErr"></div>
        </div>
        <div class="modal-footer border-0">
          <button class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    (() => {
      const api = './api_campaign.php';

      const tblBody = document.getElementById('tblBody');
      const btnReload = document.getElementById('btnReload');
      const btnNew = document.getElementById('btnNew');

      const modalEl = document.getElementById('modalCampaign');
      const modal = new bootstrap.Modal(modalEl);

      const modalTitle = document.getElementById('modalTitle');
      const inpID = document.getElementById('campaignID');
      const inpName = document.getElementById('name');
      const inpDesc = document.getElementById('description');
      const descCount = document.getElementById('descCount');
      const errBox = document.getElementById('errBox');
      const btnSave = document.getElementById('btnSave');

      const shareEl = document.getElementById('modalShare');
      const shareModal = new bootstrap.Modal(shareEl);
      const shareCampaignID = document.getElementById('shareCampaignID');
      const shareCode = document.getElementById('shareCode');
      const btnCopyCode = document.getElementById('btnCopyCode');
      const btnRegenerate = document.getElementById('btnRegenerate');
      const shareErr = document.getElementById('shareErr');

      inpDesc.addEventListener('input', () => {
        descCount.textContent = String(inpDesc.value.length);
      });

      function showError(msg) {
        errBox.textContent = msg || 'Unknown error';
        errBox.classList.remove('d-none');
      }

      function clearError() {
        errBox.classList.add('d-none');
        errBox.textContent = '';
      }

      function showShareError(msg) {
        shareErr.textContent = msg || 'Unknown error';
        shareErr.classList.remove('d-none');
      }

      function clearShareError() {
        shareErr.classList.add('d-none');
        shareErr.textContent = '';
      }

      function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, m => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;'
        })[m]);
      }

      async function call(action, data = null) {
        const url = api + '?action=' + encodeURIComponent(action);

        if (!data) {
          const r = await fetch(url, { credentials: 'same-origin' });
          return r.json();
        }

        const form = new URLSearchParams();
        for (const [k, v] of Object.entries(data)) form.append(k, v);

        const r = await fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: form.toString()
        });

        return r.json();
      }

      function render(rows) {
        if (!rows || rows.length === 0) {
          tblBody.innerHTML = `<tr><td colspan="5" class="text-secondary">No campaigns yet.</td></tr>`;
          return;
        }

        tblBody.innerHTML = rows.map(r => {
          const isOwner = Number(r.isOwner) === 1;

          const name = esc(r.name || '');
          const desc = esc(r.description || '');
          const owner = esc(r.ownerName || '');

          const memberCharId = r.memberCharacterID ? Number(r.memberCharacterID) : 0;
          const memberCharName = esc(r.memberCharacterName || '');
          const memberCount = r.memberCharacterCount ? Number(r.memberCharacterCount) : 0;

          const code = esc(r.code || '');

          const badge = isOwner ?
            `<span class="badge text-bg-primary ms-2">Owner</span>` :
            `<span class="badge text-bg-secondary ms-2">Member</span>`;

          let charCell = `<span class="muted">–</span>`;
          if (memberCharId > 0) {
            charCell = `<span class="fw-semibold">${memberCharName || ('#' + memberCharId)}</span>`;
            if (memberCount > 1) {
              charCell += ` <span class="badge text-bg-dark border ms-2">+${memberCount - 1}</span>`;
            }
          }

          let actions = '';

          if (isOwner) {
            actions += `
              <button class="btn btn-sm btn-outline-light me-1" data-act="share" data-id="${r.campaignID}" data-code="${code}" title="Share (Code)">
                <i class="bi bi-share"></i>
              </button>
              <button class="btn btn-sm btn-success me-1" data-act="live" data-id="${r.campaignID}" title="Open GM Live">
                <i class="bi bi-play-fill"></i>
              </button>
              <button class="btn btn-sm btn-outline-light me-1" data-act="edit" data-id="${r.campaignID}" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger me-1" data-act="del" data-id="${r.campaignID}" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            `;
          } else {
            actions += (memberCharId > 0) ?
              `<a class="btn btn-sm btn-primary me-1" href="/CharacterSheet/character_view.php?characterID=${encodeURIComponent(memberCharId)}" title="Open Character Sheet">
                <i class="bi bi-person-lines-fill me-1"></i>Sheet
              </a>` :
              `<button class="btn btn-sm btn-outline-secondary me-1" disabled title="No character assigned">
                <i class="bi bi-person-lines-fill me-1"></i>Sheet
              </button>`;

            actions += `
              <button class="btn btn-sm btn-outline-warning" data-act="leave" data-id="${r.campaignID}" title="Leave Campaign">
                <i class="bi bi-box-arrow-right"></i>
              </button>
            `;
          }

          const rowTarget = isOwner ?
            ('./gm_live.php?campaignID=' + encodeURIComponent(r.campaignID)) :
            (memberCharId > 0 ?
              ('/CharacterSheet/character_view.php?characterID=' + encodeURIComponent(memberCharId)) :
              ''
            );

          return `
            <tr class="row-link"
                data-row="${r.campaignID}"
                data-name="${esc(r.name)}"
                data-desc="${esc(r.description || '')}"
                data-isowner="${isOwner ? 1 : 0}"
                data-memberchar="${memberCharId}"
                data-rowtarget="${esc(rowTarget)}">
              <td class="fw-semibold">${name}${badge}</td>
              <td class="text-secondary">${desc ? desc : '<span class="muted">–</span>'}</td>
              <td class="text-secondary">${owner}</td>
              <td>${charCell}</td>
              <td class="text-end">${actions}</td>
            </tr>
          `;
        }).join('');
      }

      async function load() {
        tblBody.innerHTML = `<tr><td colspan="5" class="text-secondary">Loading…</td></tr>`;
        const res = await call('list');
        if (!res.ok) {
          render([]);
          alert(res.error || 'Error while loading');
          return;
        }
        render(res.rows);
      }

      function openCreate() {
        clearError();
        modalTitle.textContent = 'New Campaign';
        inpID.value = '';
        inpName.value = '';
        inpDesc.value = '';
        descCount.textContent = '0';
        modal.show();
        setTimeout(() => inpName.focus(), 150);
      }

      function openEdit(tr) {
        clearError();
        modalTitle.textContent = 'Edit Campaign';
        inpID.value = tr.getAttribute('data-row');
        inpName.value = tr.getAttribute('data-name') || '';
        inpDesc.value = tr.getAttribute('data-desc') || '';
        descCount.textContent = String(inpDesc.value.length);
        modal.show();
        setTimeout(() => inpName.focus(), 150);
      }

      btnNew.addEventListener('click', openCreate);
      btnReload.addEventListener('click', load);

      btnSave.addEventListener('click', async () => {
        clearError();

        const campaignID = inpID.value.trim();
        const name = inpName.value.trim();
        const description = inpDesc.value.trim();

        if (!name) {
          showError('Name must not be empty.');
          return;
        }

        btnSave.disabled = true;

        const res = campaignID ?
          await call('update', { campaignID, name, description }) :
          await call('create', { name, description });

        btnSave.disabled = false;

        if (!res.ok) {
          showError(res.error || 'Error while saving');
          return;
        }

        modal.hide();
        await load();
      });

      tblBody.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('button[data-act]');
        if (!btn) return;

        const act = btn.getAttribute('data-act');
        const id = btn.getAttribute('data-id');

        if (act === 'del') {
          if (!confirm('Delete campaign permanently? (All assignments will be removed)')) return;
          const res = await call('delete', { campaignID: id });
          if (!res.ok) return alert(res.error || 'Delete failed');
          await load();
        }

        if (act === 'leave') {
          if (!confirm('Leave campaign? (Your character assignment will be removed)')) return;
          const res = await call('leave', { campaignID: id });
          if (!res.ok) return alert(res.error || 'Leaving failed');
          await load();
        }
      });

      load();
    })();
  </script>

  <?php
  $footer = $root . '/Global/footer.html';
  if (file_exists($footer)) readfile($footer);
  ?>

</body>
</html>