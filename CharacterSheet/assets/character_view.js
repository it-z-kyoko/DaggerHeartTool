// character_view.js
(() => {
  // Theme toggle
  const btnToggleTheme = document.getElementById('btnToggleTheme');
  btnToggleTheme?.addEventListener('click', () => {
    const html = document.documentElement;
    const cur = html.getAttribute('data-bs-theme') || 'dark';
    html.setAttribute('data-bs-theme', cur === 'dark' ? 'light' : 'dark');
    btnToggleTheme.innerHTML = cur === 'dark' ? '<i class="bi bi-moon"></i>' : '<i class="bi bi-sun"></i>';
  });

  function encodeForm(obj) {
    return Object.keys(obj).map(k => encodeURIComponent(k) + '=' + encodeURIComponent(obj[k] ?? '')).join('&');
  }

  // ----------------------------
  // Dot trackers
  // ----------------------------
  function buildDots(el, max, currentValue, track) {
    el.innerHTML = '';
    for (let i = 1; i <= max; i++) {
      const d = document.createElement('span');
      d.className = 'dot';
      if (i <= currentValue) d.classList.add('filled');

      const setValue = (newValue) => {
        const cid = document.getElementById('characterID')?.value || '';
        fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: 'action=saveTracker'
            + '&characterID=' + encodeURIComponent(cid)
            + '&track=' + encodeURIComponent(track)
            + '&value=' + encodeURIComponent(newValue)
        })
        .then(r => r.json())
        .then(j => { if (j?.ok) buildDots(el, max, newValue, track); })
        .catch(()=>{});
      };

      d.addEventListener('click', () => {
        const newValue = (i === currentValue) ? Math.max(0, i - 1) : i;
        setValue(newValue);
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

  // ----------------------------
  // Dice rolling
  // ----------------------------
  function randInt(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }
  function normalizeDiceExpr(expr) { return (expr ?? '').toString().trim().replace(/\s+/g, ''); }

  function parseDice(expr) {
    const raw = normalizeDiceExpr(expr);
    const m = raw.match(/^(\d*)d(\d+)([+-]\d+)?$/i);
    if (!m) return null;
    const count = m[1] === '' ? 1 : parseInt(m[1], 10);
    const sides = parseInt(m[2], 10);
    const mod   = m[3] ? parseInt(m[3], 10) : 0;
    if (!Number.isFinite(count) || !Number.isFinite(sides) || !Number.isFinite(mod)) return null;
    if (count <= 0 || count > 50) return null;
    if (sides <= 0 || sides > 1000) return null;
    return { count, sides, mod, raw };
  }

  // DAMAGE roll -> fear: null (neutral)
  function rollStandardDice(expr) {
    const p = parseDice(expr);
    if (!p) return { ok:false, error:'Invalid dice format. Use e.g. d8+3, 2d6, d10-1.' };

    const rolls = [];
    for (let i = 0; i < p.count; i++) rolls.push(randInt(1, p.sides));
    const sum = rolls.reduce((a,b)=>a+b,0);
    const total = sum + p.mod;

    return { ok:true, kind:'standard', dice:p.raw, rolls, sum, mod:p.mod, total, fear:null };
  }

  // Duality roll -> fear 0/1
  function rollDuality(mod = 0) {
    const die1 = randInt(1,12);
    const die2 = randInt(1,12);
    let fear = 0;
    if (die2 > die1) fear = 1;
    const total = die1 + die2 + (mod || 0);
    return {
      ok:true, kind:'duality',
      dice: '2d12' + (mod ? (mod >= 0 ? `+${mod}` : `${mod}`) : ''),
      rolls:[die1,die2], sum: die1+die2, mod: mod||0, total, fear
    };
  }

    async function saveRollToDb(dice, total, fearNullable) {
    const characterID = document.getElementById('characterID')?.value || '';

    const payload = { action:'roll_save', characterID, dice, total };

    // only send fear if duality; for damage keep NULL by NOT sending it
    if (fearNullable === 0 || fearNullable === 1) payload.fear = fearNullable;

    try {
        const res = await fetch('', {
        method:'POST',
        headers:{ 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
        body: encodeForm(payload)
        });
        const j = await res.json();
        if (j?.ok && Array.isArray(j.history)) renderRollHistory(j.history);
    } catch {}
    }

  // ----------------------------
  // Toast (chip only for duality)
  // ----------------------------
  function showRollToast(title, fearNullable) {
    const el = document.getElementById('rollToast');
    const t  = document.getElementById('rtTitle');
    const c  = document.getElementById('rtChip');
    const b  = document.getElementById('rtBody');
    if (!el || !t || !c || !b) return;

    t.textContent = title || 'Roll';

    const isFear = (fearNullable === 1);
    const isHope = (fearNullable === 0);
    const isNeutral = !(isFear || isHope);

    c.textContent = isFear ? 'FEAR' : (isHope ? 'HOPE' : 'ROLL');
    c.classList.toggle('fear', isFear);
    c.classList.toggle('hope', isHope);
    c.classList.toggle('neutral', isNeutral);

    // IMPORTANT: Damage shouldn't show Hope/Fear logic -> neutral chip is fine, or hide if you prefer:
    // If you want to HIDE chip on damage, uncomment:
    // c.style.display = isNeutral ? 'none' : 'inline-flex';
    c.style.display = 'inline-flex';

    el.style.display = 'block';
    clearTimeout(showRollToast._timer);
    showRollToast._timer = setTimeout(() => { el.style.display = 'none'; }, 5500);
  }

  function setRollToastBody(text) {
    const b = document.getElementById('rtBody');
    if (b) b.textContent = text || '';
  }

  async function handleRollResult(result, titlePrefix='') {
    if (!result?.ok) {
      showRollToast('Roll', null);
      setRollToastBody(result?.error || 'Roll failed.');
      return;
    }

    const title = (titlePrefix ? `${titlePrefix} — ` : '') + result.dice;
    showRollToast(title, result.fear);

    let body = '';
    if (result.kind === 'duality') {
      const [a,b] = result.rolls;
      body =
        `Dice: ${result.dice}\n` +
        `d12#1: ${a}\n` +
        `d12#2: ${b}\n` +
        `Sum: ${result.sum}\n` +
        `Mod: ${(result.mod>=0?'+':'')}${result.mod}\n` +
        `Total: ${result.total}`;
    } else {
      body =
        `Dice: ${result.dice}\n` +
        `Rolls: [${result.rolls.join(', ')}]\n` +
        `Sum: ${result.sum}\n` +
        `Mod: ${(result.mod>=0?'+':'')}${result.mod}\n` +
        `Total: ${result.total}`;
    }
    setRollToastBody(body);

    await saveRollToDb(titlePrefix ? `${titlePrefix}: ${result.dice}` : result.dice, result.total, result.fear);
  }

  // ----------------------------
  // Roll history rendering
  // fear: 1=fear, 0=hope, null=neutral
  // ----------------------------
  function escHtml(s){
    return (s ?? '').toString()
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;');
  }

  function renderRollHistory(history) {
    const wrap = document.getElementById('rollHistoryWrap');
    if (!wrap) return;

    if (!Array.isArray(history) || history.length === 0) {
      wrap.innerHTML = '<div class="inv-empty">No rolls yet.</div>';
      return;
    }

    const rows = history.map(r => {
      const rollID = Number(r.rollID || 0);
      const dice   = String(r.dice || '');
      const total  = Number(r.total || 0);

      const fearRaw = (r.fear === undefined) ? null : r.fear;
      const fear = (fearRaw === null) ? null : Number(fearRaw);

      const cls   = (fear === 1) ? 'fear' : ((fear === 0) ? 'hope' : 'neutral');
      const label = (fear === 1) ? 'FEAR' : ((fear === 0) ? 'HOPE' : 'ROLL');

      return `
        <div class="roll-item">
          <div class="roll-left">
            <div class="roll-dice" title="${escHtml(dice)}">${escHtml(dice)}</div>
            <div class="roll-id">#${rollID}</div>
          </div>
          <span class="roll-badge ${cls}">${label}</span>
          <div class="roll-total">${total}</div>
        </div>
      `;
    }).join('');

    wrap.innerHTML = `<div class="roll-list">${rows}</div>`;
  }
  window.renderRollHistory = renderRollHistory;

  // ----------------------------
  // Wire dice buttons
  // ----------------------------
  document.querySelectorAll('.roll-stat').forEach(btn => {
    btn.addEventListener('click', async () => {
      const stat = (btn.dataset.stat || 'Stat').trim();
      const mod  = parseInt(btn.dataset.mod || '0', 10) || 0;
      await handleRollResult(rollDuality(mod), stat);
    });
  });

  document.querySelectorAll('.roll-dice').forEach(btn => {
    btn.addEventListener('click', async () => {
      const expr = (btn.dataset.dice || '').trim();
      if (!expr) return;
      // Damage -> neutral
      await handleRollResult(rollStandardDice(expr), 'Damage');
    });
  });

  document.getElementById('btnDualityDice')?.addEventListener('click', async () => {
    await handleRollResult(rollDuality(0), 'Duality Dice');
  });

  // ----------------------------
  // Inventory CRUD
  // ----------------------------
  const inv = {
    wrap: document.getElementById('invListWrap'),
    itemID: document.getElementById('invItemID'),
    name: document.getElementById('invName'),
    desc: document.getElementById('invDesc'),
    amt: document.getElementById('invAmt'),
    btnSave: document.getElementById('btnInvSave'),
    btnLabel: document.getElementById('invBtnLabel'),
    cid: () => (document.getElementById('characterID')?.value || ''),
  };

  function resetInvForm() {
    inv.itemID.value = '0';
    inv.name.value = '';
    inv.desc.value = '';
    inv.amt.value = '1';
    inv.btnLabel.textContent = 'Add';
  }

  function renderInv(items) {
    if (!Array.isArray(items) || items.length === 0) {
      inv.wrap.innerHTML = '<div class="inv-empty">No items added yet.</div>';
      return;
    }

    const esc = (s) => (s ?? '').toString()
      .replaceAll('&','&amp;').replaceAll('"','&quot;')
      .replaceAll('<','&lt;').replaceAll('>','&gt;');

    const rows = items.map(r => `
      <tr data-itemid="${Number(r.itemID||0)}"
          data-item="${esc(r.Item||'')}"
          data-description="${esc(r.Description||'')}"
          data-amount="${Number(r.Amount||0)}">
        <td>${esc(r.Item||'')}</td>
        <td class="muted">${esc(r.Description||'')}</td>
        <td style="text-align:right;font-weight:900;">${Number(r.Amount||0)}</td>
        <td style="text-align:right;">
          <div class="inv-actions">
            <button type="button" class="btn btn-soft btn-icon inv-edit"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-danger-soft btn-icon inv-del"><i class="bi bi-trash"></i></button>
          </div>
        </td>
      </tr>
    `).join('');

    inv.wrap.innerHTML = `
      <div class="table-responsive">
        <table class="inv-table" id="invTable">
          <thead>
            <tr>
              <th style="width:22%;">Name</th>
              <th>Description</th>
              <th style="width:10%;text-align:right;">Amount</th>
              <th style="width:120px;text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    `;
    wireInvTable();
  }

  function wireInvTable() {
    const tbl = document.getElementById('invTable');
    if (!tbl) return;

    tbl.querySelectorAll('.inv-edit').forEach(btn => {
      btn.addEventListener('click', () => {
        const tr = btn.closest('tr'); if (!tr) return;
        inv.itemID.value = tr.dataset.itemid || '0';
        inv.name.value = tr.dataset.item || '';
        inv.desc.value = tr.dataset.description || '';
        inv.amt.value  = tr.dataset.amount || '0';
        inv.btnLabel.textContent = 'Update';
      });
    });

    tbl.querySelectorAll('.inv-del').forEach(btn => {
      btn.addEventListener('click', async () => {
        const tr = btn.closest('tr'); if (!tr) return;
        const id = parseInt(tr.dataset.itemid || '0', 10);
        if (!id) return;
        if (!confirm('Delete this item?')) return;

        try {
          const res = await fetch('', {
            method:'POST',
            headers:{ 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
            body: encodeForm({ action:'inv_delete', characterID: inv.cid(), itemID: id })
          });
          const j = await res.json();
          if (j?.ok) { renderInv(j.items || []); resetInvForm(); }
          else alert(j?.error || 'Delete failed.');
        } catch {}
      });
    });
  }

  inv.btnSave?.addEventListener('click', async () => {
    const itemID = parseInt(inv.itemID.value || '0', 10);
    const Item = (inv.name.value || '').trim();
    const Description = (inv.desc.value || '').trim();
    const Amount = parseInt(inv.amt.value || '0', 10);

    if (!Item) return;

    try {
      const res = await fetch('', {
        method:'POST',
        headers:{ 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
        body: encodeForm({
          action:'inv_upsert',
          characterID: inv.cid(),
          itemID: itemID > 0 ? itemID : 0,
          Item, Description,
          Amount: isNaN(Amount) ? 0 : Amount
        })
      });
      const j = await res.json();
      if (j?.ok) { renderInv(j.items || []); resetInvForm(); }
      else alert(j?.error || 'Save failed.');
    } catch {}
  });

  wireInvTable();
})();