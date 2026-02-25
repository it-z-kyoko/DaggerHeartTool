(() => {
  // --------------------
  // Theme toggle
  // --------------------
  const btnToggleTheme = document.getElementById('btnToggleTheme');
  btnToggleTheme?.addEventListener('click', () => {
    const html = document.documentElement;
    const cur = html.getAttribute('data-bs-theme') || 'dark';
    html.setAttribute('data-bs-theme', cur === 'dark' ? 'light' : 'dark');
    btnToggleTheme.innerHTML = cur === 'dark'
      ? '<i class="bi bi-moon"></i>'
      : '<i class="bi bi-sun"></i>';
  });

  // --------------------
  // Wizard
  // --------------------
  const stepButtons = Array.from(document.querySelectorAll('.cc-step-btn'));
  const stepPanels  = Array.from(document.querySelectorAll('.cc-step-panel'));
  const btnPrev = document.getElementById('btnPrev');
  const btnNext = document.getElementById('btnNext');

  let currentStep = 1;
  const totalSteps = 7;

  function setStep(step) {
    currentStep = Math.max(1, Math.min(totalSteps, step));
    stepButtons.forEach(b => b.classList.toggle('active', parseInt(b.dataset.step, 10) === currentStep));
    stepPanels.forEach(p => p.classList.toggle('active', parseInt(p.dataset.stepPanel, 10) === currentStep));
    btnPrev.disabled = currentStep === 1;
    btnNext.disabled = currentStep === totalSteps;
    updateProgress();
    updateSummary();
  }

  stepButtons.forEach(b => b.addEventListener('click', () => setStep(parseInt(b.dataset.step, 10))));
  btnPrev?.addEventListener('click', () => setStep(currentStep - 1));
  btnNext?.addEventListener('click', () => setStep(currentStep + 1));

  // --------------------
  // Helpers / DOM
  // --------------------
  const liveName = document.getElementById('liveName');
  const progressBar = document.getElementById('progressBar');
  const saveState = document.getElementById('saveState');

  const sumName = document.getElementById('sumName');
  const sumPronouns = document.getElementById('sumPronouns');
  const sumLine2 = document.getElementById('sumLine2');
  const sumTraits = document.getElementById('sumTraits');
  const validationText = document.getElementById('validationText');

  // Fixed card DOM
  const cardAncestry = document.getElementById('cardAncestry');
  const cardClass = document.getElementById('cardClass');
  const cardCommunity = document.getElementById('cardCommunity');

  const cardAncestryHint = document.getElementById('cardAncestryHint');
  const cardClassHint = document.getElementById('cardClassHint');
  const cardCommunityHint = document.getElementById('cardCommunityHint');

  // Domain DOM
  const domainGrid = document.getElementById('domainGrid');
  const domainHint = document.getElementById('domainHint');
  const domainSelectedCount = document.getElementById('domainSelectedCount');
  const domainSaveState = document.getElementById('domainSaveState');

  const v = (id) => (document.getElementById(id)?.value ?? '').toString().trim();
  const i = (id, def = 0) => {
    const x = parseInt(document.getElementById(id)?.value ?? '', 10);
    return Number.isFinite(x) ? x : def;
  };
  const signed = (n) => (n >= 0 ? `+${n}` : `${n}`);

  function toFileNameFromLabel(label) {
    return (label || '')
      .trim()
      .replace(/\s+/g, '_')
      .replace(/[\\/:*?"<>|]+/g, '');
  }

  // --------------------
  // ✅ BASE PATHS (FIX)
  // --------------------
  // API relativ zur aktuellen Seite (/CharacterCreator/creator.php -> ./api/...)
  const API = './api';

  // Images liegen im Projekt-Root (/img/...), von /CharacterCreator/ aus -> ../img/...
  const IMG_BASE = '../img';

  function updateFixedCards() {
    const herOpt = document.querySelector('#cHeritage option:checked');
    const comOpt = document.querySelector('#cCommunity option:checked');
    const classID = parseInt(document.getElementById('cClass')?.value || '0', 10) || 0;
    const subID   = parseInt(document.getElementById('cSubClass')?.value || '0', 10) || 0;

    // Ancestry
    if (cardAncestry && cardAncestryHint) {
      if (herOpt && herOpt.value) {
        const f = toFileNameFromLabel(herOpt.textContent);
        cardAncestry.src = `${IMG_BASE}/Cards/Ancestries/${f}.jpg`;
        cardAncestryHint.textContent = `${herOpt.textContent.trim()}.jpg`;
      } else {
        cardAncestry.src = '';
        cardAncestryHint.textContent = 'Select Heritage first.';
      }
    }

    // Class Foundation
    if (cardClass && cardClassHint) {
      if (classID > 0 && subID > 0) {
        const f = `${classID}_${subID}_Foundation.jpg`;
        cardClass.src = `${IMG_BASE}/Cards/Classes/${f}`;
        cardClassHint.textContent = f;
      } else {
        cardClass.src = '';
        cardClassHint.textContent = 'Select Class + Subclass first.';
      }
    }

    // Community
    if (cardCommunity && cardCommunityHint) {
      if (comOpt && comOpt.value) {
        const f = toFileNameFromLabel(comOpt.textContent);
        cardCommunity.src = `${IMG_BASE}/Cards/Communities/${f}.jpg`;
        cardCommunityHint.textContent = `${comOpt.textContent.trim()}.jpg`;
      } else {
        cardCommunity.src = '';
        cardCommunityHint.textContent = 'Select Community first.';
      }
    }
  }

  // --------------------
  // Domain cards state + UI
  // --------------------
  let domainCards = [];
  let selectedDomainFiles = [];

  function setSelectedCount() {
    if (domainSelectedCount) domainSelectedCount.textContent = String(selectedDomainFiles.length);
  }

  function renderDomainGrid() {
    if (!domainGrid) return;

    domainGrid.innerHTML = '';

    if (!domainCards.length) {
      const empty = document.createElement('div');
      empty.className = 'muted small';
      empty.textContent = 'No domain cards available for current selection.';
      domainGrid.appendChild(empty);
      return;
    }

    for (const c of domainCards) {
      const wrap = document.createElement('div');
      wrap.style.border = '1px solid rgba(255,255,255,.12)';
      wrap.style.borderRadius = '1rem';
      wrap.style.background = 'rgba(255,255,255,.03)';
      wrap.style.padding = '.5rem';
      wrap.style.cursor = 'pointer';
      wrap.style.position = 'relative';

      const img = document.createElement('img');
      img.src = c.src;
      img.alt = c.filename;
      img.style.width = '100%';
      img.style.borderRadius = '.75rem';
      img.style.display = 'block';
      img.loading = 'lazy';

      const badge = document.createElement('div');
      badge.style.position = 'absolute';
      badge.style.top = '.55rem';
      badge.style.left = '.55rem';
      badge.style.padding = '.2rem .5rem';
      badge.style.borderRadius = '999px';
      badge.style.border = '1px solid rgba(255,255,255,.12)';
      badge.style.background = 'rgba(0,0,0,.25)';
      badge.style.fontSize = '.8rem';
      badge.style.color = 'rgba(255,255,255,.85)';
      badge.textContent = `${c.domainID}_${c.spellLevel}`;

      const isSel = selectedDomainFiles.includes(c.filename);
      if (isSel) {
        wrap.style.outline = '2px solid rgba(167, 139, 250, .65)';
        wrap.style.boxShadow = '0 0 0 .2rem rgba(167, 139, 250, .18)';
      }

      wrap.addEventListener('click', () => {
        const idx = selectedDomainFiles.indexOf(c.filename);
        if (idx >= 0) {
          selectedDomainFiles.splice(idx, 1);
        } else {
          if (selectedDomainFiles.length >= 2) return; // max 2
          selectedDomainFiles.push(c.filename);
        }
        setSelectedCount();
        renderDomainGrid();
        if (domainSaveState) domainSaveState.textContent = 'Pending save…';
      });

      wrap.appendChild(img);
      wrap.appendChild(badge);
      domainGrid.appendChild(wrap);
    }
  }

  async function loadDomainCardsForCurrent() {
    const classID = parseInt(document.getElementById('cClass')?.value || '0', 10) || 0;
    const lvl     = parseInt(document.getElementById('cLevel')?.value || '1', 10) || 1;

    if (!domainHint) return;

    if (!classID) {
      domainHint.textContent = 'Select Class first.';
      domainCards = [];
      selectedDomainFiles = [];
      setSelectedCount();
      renderDomainGrid();
      return;
    }

    domainHint.textContent = 'Loading Domain cards…';

    try {
      const res = await fetch(`${API}/get_domain_cards.php?classID=${encodeURIComponent(classID)}&level=${encodeURIComponent(lvl)}`, {
        headers: { 'Accept': 'application/json' }
      });

      const json = await res.json();

      if (!json.ok) {
        domainHint.textContent = 'Failed to load Domain cards.';
        domainCards = [];
        renderDomainGrid();
        return;
      }

      domainCards = json.cards || [];
      domainHint.textContent = `Showing Domain cards for Level ${json.level}. Domains: ${(json.domains || []).join(', ') || '—'}`;

      // drop selections that no longer exist
      selectedDomainFiles = selectedDomainFiles.filter(f => domainCards.some(c => c.filename === f));
      setSelectedCount();
      renderDomainGrid();
    } catch (e) {
      console.error(e);
      domainHint.textContent = 'Failed to load Domain cards (network).';
      domainCards = [];
      renderDomainGrid();
    }
  }

  // --------------------
  // Progress / Summary / Validation
  // --------------------
  function updateProgress() {
    const required = ['cName', 'cPronouns', 'cHeritage', 'cClass', 'cLevel'];
    const filled = required.filter(id => v(id)).length;
    const pct = Math.round((filled / required.length) * 100);
    if (progressBar) progressBar.style.width = `${pct}%`;
  }

  function updateSummary() {
    const name = v('cName') || 'Unnamed';
    if (liveName) liveName.textContent = name;

    if (sumName) sumName.textContent = v('cName') || '—';
    if (sumPronouns) sumPronouns.textContent = v('cPronouns') ? `(${v('cPronouns')})` : '';

    const herSel = document.querySelector('#cHeritage option:checked');
    const classSel = document.querySelector('#cClass option:checked');
    const subSel = document.querySelector('#cSubClass option:checked');
    const comSel = document.querySelector('#cCommunity option:checked');

    const parts = [];
    if (herSel && herSel.value) parts.push(`Heritage: ${herSel.textContent.trim()}`);
    if (classSel && classSel.value) parts.push(`Class: ${classSel.textContent.trim()}`);
    if (subSel && subSel.value) parts.push(`Subclass: ${subSel.textContent.trim()}`);
    if (comSel && comSel.value) parts.push(`Community: ${comSel.textContent.trim()}`);
    parts.push(`Level: ${v('cLevel') || '—'}`);
    if (sumLine2) sumLine2.textContent = parts.join(' • ');

    if (sumTraits) {
      const agi = i('tAgility', 0), str = i('tStrength', 0), fin = i('tFinesse', 0);
      const ins = i('tInstinct', 0), pre = i('tPresence', 0), kno = i('tKnowledge', 0);
      sumTraits.textContent =
        `AGI ${signed(agi)}, STR ${signed(str)}, FIN ${signed(fin)}, INS ${signed(ins)}, PRE ${signed(pre)}, KNO ${signed(kno)}`;
    }
  }

  function validateAll() {
    const missing = [];
    if (!v('cName')) missing.push('Name');
    if (!v('cPronouns')) missing.push('Pronouns');
    if (!v('cHeritage')) missing.push('Heritage');
    if (!v('cClass')) missing.push('Class');
    if (!v('cLevel')) missing.push('Level');

    const traitVals = [
      i('tStrength'), i('tAgility'), i('tFinesse'),
      i('tInstinct'), i('tPresence'), i('tKnowledge')
    ];
    const sorted = traitVals.slice().sort((a, b) => a - b);
    const requiredPool = [-1, 0, 0, 1, 1, 2];
    const traitOk = sorted.length === 6 && sorted.every((x, idx) => x === requiredPool[idx]);
    if (!traitOk) missing.push('Traits (must be +2,+1,+1,0,0,-1)');

    if (validationText) {
      validationText.textContent = missing.length
        ? `Missing/Invalid: ${missing.join(', ')}`
        : 'No validation errors.';
    }
    return missing.length === 0;
  }

  // --------------------
  // Subclasses
  // --------------------
  const cClass = document.getElementById('cClass');
  const cSubClass = document.getElementById('cSubClass');

  async function loadSubclasses(classID) {
    if (!cSubClass) return;

    cSubClass.innerHTML = '';
    if (!classID) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'Select class first…';
      cSubClass.appendChild(opt);
      return;
    }

    const res = await fetch(`${API}/get_subclasses.php?classID=${encodeURIComponent(classID)}`, {
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();

    const blank = document.createElement('option');
    blank.value = '';
    blank.textContent = 'Select…';
    cSubClass.appendChild(blank);

    if (!json.ok) return;

    json.rows.forEach(r => {
      const opt = document.createElement('option');
      opt.value = String(r.subclassID);
      opt.textContent = r.name;
      cSubClass.appendChild(opt);
    });
  }

  // --------------------
  // Derived from class (Evasion, HP, Hope Feature)
  // --------------------
  const cEvasion = document.getElementById('cEvasion');
  const cHPMax = document.getElementById('cHPMax');
  const cHopeFeature = document.getElementById('cHopeFeature');

  async function loadClassDerived() {
    const classID = parseInt(cClass?.value || '0', 10) || 0;
    if (!classID) {
      if (cEvasion) cEvasion.value = '';
      if (cHPMax) cHPMax.value = '';
      if (cHopeFeature) cHopeFeature.value = '';
      return;
    }

    const res = await fetch(`${API}/get_class_info.php?classID=${encodeURIComponent(classID)}`, {
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (!json.ok || !json.row) return;

    if (cEvasion) cEvasion.value = (json.row.starting_evasion_score ?? '').toString();
    if (cHPMax) cHPMax.value = (json.row.starting_hit_point ?? '').toString();
    if (cHopeFeature) cHopeFeature.value = (json.row.hope_feature ?? '').toString();
  }

  // --------------------
  // Armor list by level + armor info
  // --------------------
  const cLevel = document.getElementById('cLevel');
  const aName = document.getElementById('aName');
  const cArmor = document.getElementById('cArmor');
  const thMajor = document.getElementById('thMajor');
  const thSevere = document.getElementById('thSevere');
  const aFeat = document.getElementById('aFeat');
  const armorLevelHint = document.getElementById('armorLevelHint');

  async function loadArmorOptions() {
    if (!aName || !cLevel) return;

    const lvl = parseInt(cLevel.value || '1', 10) || 1;

    aName.innerHTML = '';
    const blank = document.createElement('option');
    blank.value = '';
    blank.textContent = 'Select…';
    aName.appendChild(blank);

    const res = await fetch(`${API}/get_armors.php?level=${encodeURIComponent(lvl)}`, {
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (!json.ok) return;

    json.rows.forEach(r => {
      const opt = document.createElement('option');
      opt.value = String(r.armorID);
      opt.textContent = r.name;
      aName.appendChild(opt);
    });

    if (armorLevelHint) armorLevelHint.textContent = `Showing armors with min_level ≤ ${lvl}.`;
    await loadArmorInfo();
  }

  async function loadArmorInfo() {
    if (!aName) return;

    const armorID = parseInt(aName.value || '0', 10) || 0;
    if (!armorID) {
      if (cArmor) cArmor.value = '';
      if (thMajor) thMajor.textContent = '—';
      if (thSevere) thSevere.textContent = '—';
      if (aFeat) aFeat.value = '';
      return;
    }

    const res = await fetch(`${API}/get_armor_info.php?armorID=${encodeURIComponent(armorID)}`, {
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (!json.ok || !json.row) return;

    if (cArmor) cArmor.value = (json.row.base_score ?? '').toString();
    if (thMajor) thMajor.textContent = (json.row.major_threshold ?? '—').toString();
    if (thSevere) thSevere.textContent = (json.row.severe_threshold ?? '—').toString();
    if (aFeat) aFeat.value = (json.row.feature ?? '').toString();
  }

  // --------------------
  // Traits: integrity pool UI
  // --------------------
  const traitSelects = Array.from(document.querySelectorAll('.trait-select'));
  const TRAIT_POOL = new Map([[2, 1], [1, 2], [0, 2], [-1, 1]]);

  function initTraitSelects() {
    traitSelects.forEach(sel => {
      sel.innerHTML = '';
      const blank = document.createElement('option');
      blank.value = '';
      blank.textContent = 'Select…';
      sel.appendChild(blank);
    });
    refreshTraitOptions();
  }

  function countMap(arr) {
    const m = new Map();
    for (const x of arr) m.set(x, (m.get(x) || 0) + 1);
    return m;
  }

  function refreshTraitOptions() {
    traitSelects.forEach(sel => {
      const cur = sel.value === '' ? null : parseInt(sel.value, 10);

      const otherVals = traitSelects
        .filter(s => s !== sel)
        .map(s => s.value)
        .filter(x => x !== '')
        .map(x => parseInt(x, 10));

      const used = countMap(otherVals);

      sel.innerHTML = '';
      const blank = document.createElement('option');
      blank.value = '';
      blank.textContent = 'Select…';
      sel.appendChild(blank);

      const order = [2, 1, 0, -1];
      order.forEach(val => {
        const max = TRAIT_POOL.get(val) || 0;
        const usedCount = used.get(val) || 0;
        const remaining = max - usedCount;
        if (remaining > 0 || (cur !== null && cur === val)) {
          const opt = document.createElement('option');
          opt.value = String(val);
          opt.textContent = val >= 0 ? `+${val}` : `${val}`;
          sel.appendChild(opt);
        }
      });

      if (cur !== null) sel.value = String(cur);
    });

    validateAll();
    updateSummary();
  }

  traitSelects.forEach(sel => sel.addEventListener('change', refreshTraitOptions));

  // --------------------
  // Experiences list
  // --------------------
  const expInput = document.getElementById('expInput');
  const btnAddExp = document.getElementById('btnAddExp');
  const expList = document.getElementById('expList');
  let experiences = [];

  function norm(s) { return (s ?? '').toString().trim().replace(/\s+/g, ' '); }

  function renderExp() {
    if (!expList) return;

    expList.innerHTML = '';
    if (experiences.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'cc-muted small';
      empty.textContent = 'No experiences added yet.';
      expList.appendChild(empty);
      return;
    }

    experiences.forEach((txt, idx) => {
      const item = document.createElement('div');
      item.className = 'cc-exp-item';

      const span = document.createElement('span');
      span.className = 'cc-exp-text';
      span.textContent = txt;
      span.title = txt;

      const del = document.createElement('button');
      del.className = 'cc-exp-del';
      del.type = 'button';
      del.innerHTML = '<i class="bi bi-x-lg"></i>';
      del.addEventListener('click', () => {
        experiences.splice(idx, 1);
        renderExp();
      });

      item.appendChild(span);
      item.appendChild(del);
      expList.appendChild(item);
    });
  }

  function addExp() {
    const t = norm(expInput?.value);
    if (!t) return;
    const capped = t.length > 200 ? t.slice(0, 200) : t;
    if (!experiences.includes(capped)) experiences.push(capped);
    if (expInput) expInput.value = '';
    renderExp();
  }

  btnAddExp?.addEventListener('click', addExp);
  expInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); addExp(); }
  });

  // --------------------
  // Weapons
  // --------------------
  const w1Select = document.getElementById('w1Select');
  const w2Select = document.getElementById('w2Select');

  const w1Trait = document.getElementById('w1Trait');
  const w1Range = document.getElementById('w1Range');
  const w1Dmg = document.getElementById('w1Dmg');
  const w1Feat = document.getElementById('w1Feat');

  const w2Trait = document.getElementById('w2Trait');
  const w2Range = document.getElementById('w2Range');
  const w2Dmg = document.getElementById('w2Dmg');
  const w2Feat = document.getElementById('w2Feat');

  async function loadWeapons(selectEl, primaryFlag) {
    if (!selectEl) return;

    selectEl.innerHTML = '';
    const blank = document.createElement('option');
    blank.value = '';
    blank.textContent = 'Select…';
    selectEl.appendChild(blank);

    const res = await fetch(`${API}/get_weapons.php?primary=${encodeURIComponent(primaryFlag)}`, {
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    if (!json.ok) return;

    json.rows.forEach(r => {
      const opt = document.createElement('option');
      opt.value = String(r.weaponID);
      opt.textContent = r.name;
      selectEl.appendChild(opt);
    });
  }

  async function loadWeaponInfo(weaponID) {
    if (!weaponID) return null;
    const res = await fetch(`${API}/get_weapon_info.php?weaponID=${encodeURIComponent(weaponID)}`, {
      headers: { 'Accept': 'application/json' }
    });
    const json = await res.json();
    return json.ok ? (json.row || null) : null;
  }

  async function applyWeapon(which, weaponID) {
    const row = weaponID ? await loadWeaponInfo(weaponID) : null;
    const t = row ? (row.trait ?? '') : '';
    const r = row ? (row.range ?? '') : '';
    const d = row ? (row.damage ?? '') : '';
    const f = row ? (row.feature ?? '') : '';

    if (which === 1) {
      if (w1Trait) w1Trait.value = t;
      if (w1Range) w1Range.value = r;
      if (w1Dmg) w1Dmg.value = d;
      if (w1Feat) w1Feat.value = f;
    } else {
      if (w2Trait) w2Trait.value = t;
      if (w2Range) w2Range.value = r;
      if (w2Dmg) w2Dmg.value = d;
      if (w2Feat) w2Feat.value = f;
    }
  }

  w1Select?.addEventListener('change', () => applyWeapon(1, parseInt(w1Select.value || '0', 10) || 0));
  w2Select?.addEventListener('change', () => applyWeapon(2, parseInt(w2Select.value || '0', 10) || 0));

  // --------------------
  // Inventory list
  // --------------------
  const invName = document.getElementById('invName');
  const invDesc = document.getElementById('invDesc');
  const invAmt  = document.getElementById('invAmt');
  const btnAddInv = document.getElementById('btnAddInv');
  const invList = document.getElementById('invList');

  let inventory = [];

  function clampInt(x, def = 1) {
    const n = parseInt(x, 10);
    return Number.isFinite(n) ? n : def;
  }

  function renderInv() {
    if (!invList) return;

    invList.innerHTML = '';
    if (inventory.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'cc-muted small';
      empty.textContent = 'No items added yet.';
      invList.appendChild(empty);
      return;
    }

    inventory.forEach((row, idx) => {
      const item = document.createElement('div');
      item.className = 'cc-exp-item';

      const label = document.createElement('span');
      label.className = 'cc-exp-text';
      const text = `${row.item}${row.description ? ' — ' + row.description : ''} (x${row.amount})`;
      label.textContent = text;
      label.title = text;

      const del = document.createElement('button');
      del.className = 'cc-exp-del';
      del.type = 'button';
      del.innerHTML = '<i class="bi bi-x-lg"></i>';
      del.addEventListener('click', () => {
        inventory.splice(idx, 1);
        renderInv();
      });

      item.appendChild(label);
      item.appendChild(del);
      invList.appendChild(item);
    });
  }

  function addInv() {
    const name = norm(invName?.value);
    if (!name) return;

    const desc = norm(invDesc?.value);
    let amt = clampInt(invAmt?.value, 1);
    if (amt < 0) amt = 0;

    const cappedName = name.length > 120 ? name.slice(0, 120) : name;
    const cappedDesc = desc.length > 500 ? desc.slice(0, 500) : desc;

    const key = (cappedName + '|' + cappedDesc).toLowerCase();
    const existing = inventory.findIndex(r => ((r.item + '|' + r.description).toLowerCase() === key));

    if (existing >= 0) inventory[existing].amount = clampInt(inventory[existing].amount, 0) + amt;
    else inventory.push({ item: cappedName, description: cappedDesc, amount: amt });

    if (invName) invName.value = '';
    if (invDesc) invDesc.value = '';
    if (invAmt) invAmt.value = '1';
    renderInv();
  }

  btnAddInv?.addEventListener('click', addInv);
  [invName, invDesc, invAmt].forEach(el => el?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); addInv(); }
  }));

  // --------------------
  // Bind changes (UI updates + Cards)
  // --------------------
  ['cName', 'cPronouns', 'cHeritage', 'cCommunity', 'cLevel', 'cSubClass'].forEach(id => {
    const el = document.getElementById(id);
    el?.addEventListener('input', () => { updateProgress(); updateSummary(); validateAll(); });
    el?.addEventListener('change', () => { updateProgress(); updateSummary(); validateAll(); });
  });

  // Class change: subclasses + derived + fixed cards + domain cards
  cClass?.addEventListener('change', async () => {
    await loadSubclasses(cClass.value);
    await loadClassDerived();
    updateFixedCards();
    await loadDomainCardsForCurrent();
    validateAll();
    updateSummary();
  });

  // Subclass change: fixed cards
  cSubClass?.addEventListener('change', () => {
    updateFixedCards();
    validateAll();
    updateSummary();
  });

  // Heritage + Community change: fixed cards
  ['cHeritage', 'cCommunity'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', () => {
      updateFixedCards();
    });
  });

  // Level change: armor list + domain cards
  cLevel?.addEventListener('change', async () => {
    await loadArmorOptions();
    await loadDomainCardsForCurrent();
  });

  aName?.addEventListener('change', loadArmorInfo);

  // --------------------
  // SAVE ALL
  // --------------------
  const btnSaveAll = document.getElementById('btnSaveAll');

  function collectPayload() {
    return {
      basics: {
        name: v('cName'),
        pronouns: v('cPronouns'),
        level: i('cLevel', 1),
        heritageID: i('cHeritage', 0),
        classID: i('cClass', 0),
        subclassID: i('cSubClass', 0),
        communityID: i('cCommunity', 0),
      },
      traits: {
        strength: i('tStrength', 0),
        agility: i('tAgility', 0),
        finesse: i('tFinesse', 0),
        instinct: i('tInstinct', 0),
        presence: i('tPresence', 0),
        knowledge: i('tKnowledge', 0),
        hp: i('cHPMax', 0),
      },
      defense: {
        evasion: i('cEvasion', 0),
        armor: i('cArmor', 0),
        armorID: parseInt(aName?.value || '0', 10) || 0
      },
      experiences: experiences.slice(),
      gear: {
        primaryWeaponID: parseInt(w1Select?.value || '0', 10) || 0,
        secondaryWeaponID: parseInt(w2Select?.value || '0', 10) || 0,
        gold: {
          handfuls: i('gHandfuls', 0),
          bags: i('gBags', 0),
          chest: i('gChest', 0),
        }
      },
      inventory: inventory.slice(),
      domainCards: selectedDomainFiles.slice()
    };
  }

  btnSaveAll?.addEventListener('click', async () => {
    const ok = validateAll();
    if (!ok) {
      setStep(7);
      alert('Fix validation errors before saving.');
      return;
    }

    if (saveState) saveState.textContent = 'Saving…';

    const payload = collectPayload();
    try {
      const res = await fetch(`${API}/save_all.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
      });
      const json = await res.json();
      if (!json.ok) {
        if (saveState) saveState.textContent = 'Save failed';
        alert(json.error || 'Save failed');
        return;
      }
      if (saveState) saveState.textContent = `Saved (ID ${json.characterID})`;
      alert(`Saved! characterID=${json.characterID}`);
    } catch (e) {
      console.error(e);
      if (saveState) saveState.textContent = 'Save failed';
      alert('Network/Server error');
    }
  });

  // --------------------
  // Init loads
  // --------------------
  initTraitSelects();
  renderExp();
  renderInv();
  updateFixedCards();

  (async () => {
    try {
      await loadSubclasses(cClass?.value || '');
      await loadClassDerived();
      await loadArmorOptions();
      await loadWeapons(w1Select, 1);
      await loadWeapons(w2Select, 0);

      updateFixedCards();
      await loadDomainCardsForCurrent();
      setSelectedCount();
      renderDomainGrid();
    } catch (e) {
      console.error('Init failed:', e);
    } finally {
      setStep(1);
      updateProgress();
      updateSummary();
      validateAll();
    }
  })();
})();