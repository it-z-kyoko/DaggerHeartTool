<?php
declare(strict_types=1);

session_start();

$root = dirname(__DIR__);

// Wenn du schon ein auth-Partial hast, nimm das.
// Beispiel: require_once $root . '/Dashboard/partials/auth.php';
// Ich mache hier minimal:
if (!isset($_SESSION['userID'])) {
    header('Location: ' . $root . '/Login/login.php');
    exit;
}
?>
<!doctype html>
<html lang="de" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kampagnen</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- deine globalen Styles -->
  <link href="../Global/styles.css" rel="stylesheet">

  <style>
    /* kleines Glass-Card Fallback, falls du es nicht schon global hast */
    .glass {
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.10);
      backdrop-filter: blur(10px);
      border-radius: 18px;
      box-shadow: 0 12px 30px rgba(0,0,0,.35);
    }
    .muted { opacity: .85; }
  </style>
</head>
<body>

<?php
// Optional: Navbar einbinden, falls vorhanden
$nav = $root . '/Global/nav.html';
if (file_exists($nav)) {
    readfile($nav);
}
?>

<div class="container py-4">
  <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div>
      <h3 class="mb-0">Kampagnen</h3>
      <div class="text-secondary">Erstellen, bearbeiten, verlassen.</div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-light" id="btnReload">
        <i class="bi bi-arrow-repeat"></i>
      </button>
      <button class="btn btn-primary" id="btnNew">
        <i class="bi bi-plus-lg"></i> Neue Kampagne
      </button>
    </div>
  </div>

  <div class="glass p-3">
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width: 34%">Name</th>
            <th>Beschreibung</th>
            <th style="width: 18%">Owner</th>
            <th class="text-end" style="width: 180px">Aktionen</th>
          </tr>
        </thead>
        <tbody id="tblBody">
          <tr><td colspan="4" class="text-secondary">Lade…</td></tr>
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
        <h5 class="modal-title" id="modalTitle">Kampagne</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="campaignID">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input class="form-control" id="name" maxlength="80" placeholder="z.B. The Shattered Crown">
        </div>
        <div class="mb-2">
          <label class="form-label">Beschreibung</label>
          <textarea class="form-control" id="description" rows="5" maxlength="2000" placeholder="Kurzbeschreibung / Notizen"></textarea>
          <div class="text-secondary small mt-1"><span id="descCount">0</span>/2000</div>
        </div>
        <div class="alert alert-danger d-none" id="errBox"></div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-light" data-bs-dismiss="modal">Abbrechen</button>
        <button class="btn btn-primary" id="btnSave"><i class="bi bi-check2"></i> Speichern</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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

  inpDesc.addEventListener('input', () => {
    descCount.textContent = String(inpDesc.value.length);
  });

  function showError(msg) {
    errBox.textContent = msg || 'Unbekannter Fehler';
    errBox.classList.remove('d-none');
  }
  function clearError() {
    errBox.classList.add('d-none');
    errBox.textContent = '';
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    })[m]);
  }

  async function call(action, data = null) {
    const url = api + '?action=' + encodeURIComponent(action);

    if (!data) {
      const r = await fetch(url, { credentials: 'same-origin' });
      return r.json();
    }

    const form = new URLSearchParams();
    for (const [k,v] of Object.entries(data)) form.append(k, v);

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
      tblBody.innerHTML = `<tr><td colspan="4" class="text-secondary">Noch keine Kampagnen.</td></tr>`;
      return;
    }

    tblBody.innerHTML = rows.map(r => {
      const isOwner = Number(r.isOwner) === 1;
      const badge = isOwner ? `<span class="badge text-bg-primary ms-2">Owner</span>` : '';
      const desc = esc(r.description || '');
      const owner = esc(r.ownerName || '');
      const name = esc(r.name || '');

      // NEU: Live Session Button nur für Owner
      const btnLive = isOwner
        ? `<button class="btn btn-sm btn-success me-1" data-act="live" data-id="${r.campaignID}" title="Live Session starten">
             <i class="bi bi-play-fill"></i>
           </button>`
        : '';

      const btnEdit = isOwner
        ? `<button class="btn btn-sm btn-outline-light me-1" data-act="edit" data-id="${r.campaignID}"><i class="bi bi-pencil"></i></button>`
        : '';

      const btnDel = isOwner
        ? `<button class="btn btn-sm btn-outline-danger me-1" data-act="del" data-id="${r.campaignID}"><i class="bi bi-trash"></i></button>`
        : `<button class="btn btn-sm btn-outline-warning me-1" data-act="leave" data-id="${r.campaignID}"><i class="bi bi-box-arrow-right"></i></button>`;

      return `
        <tr data-row="${r.campaignID}"
            data-name="${esc(r.name)}"
            data-desc="${esc(r.description || '')}"
            data-isowner="${isOwner ? 1 : 0}">
          <td class="fw-semibold">${name}${badge}</td>
          <td class="text-secondary">${desc ? desc : '<span class="muted">–</span>'}</td>
          <td class="text-secondary">${owner}</td>
          <td class="text-end">
            ${btnLive}
            ${btnEdit}
            ${btnDel}
          </td>
        </tr>
      `;
    }).join('');
  }

  async function load() {
    tblBody.innerHTML = `<tr><td colspan="4" class="text-secondary">Lade…</td></tr>`;
    const res = await call('list');
    if (!res.ok) {
      render([]);
      alert(res.error || 'Fehler beim Laden');
      return;
    }
    render(res.rows);
  }

  function openCreate() {
    clearError();
    modalTitle.textContent = 'Neue Kampagne';
    inpID.value = '';
    inpName.value = '';
    inpDesc.value = '';
    descCount.textContent = '0';
    modal.show();
    setTimeout(() => inpName.focus(), 150);
  }

  function openEdit(tr) {
    clearError();
    modalTitle.textContent = 'Kampagne bearbeiten';
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
      showError('Name darf nicht leer sein.');
      return;
    }

    btnSave.disabled = true;

    const res = campaignID
      ? await call('update', { campaignID, name, description })
      : await call('create', { name, description });

    btnSave.disabled = false;

    if (!res.ok) {
      showError(res.error || 'Fehler beim Speichern');
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

    const tr = btn.closest('tr');
    if (!id || !tr) return;

    // NEU: Live Session / GM Live View öffnen
    if (act === 'live') {
      window.location.href = './gm_live.php?campaignID=' + encodeURIComponent(id);
      return;
    }

    if (act === 'edit') {
      openEdit(tr);
      return;
    }

    if (act === 'del') {
      if (!confirm('Kampagne wirklich löschen? (Alle Zuordnungen werden entfernt)')) return;
      const res = await call('delete', { campaignID: id });
      if (!res.ok) return alert(res.error || 'Löschen fehlgeschlagen');
      await load();
      return;
    }

    if (act === 'leave') {
      if (!confirm('Kampagne verlassen?')) return;
      const res = await call('leave', { campaignID: id });
      if (!res.ok) return alert(res.error || 'Verlassen fehlgeschlagen');
      await load();
      return;
    }
  });

  // initial
  load();
})();
</script>

<?php
$footer = $root . '/Global/footer.html';
if (file_exists($footer)) {
    readfile($footer);
}
?>

</body>
</html>