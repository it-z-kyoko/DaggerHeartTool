(() => {
  // --------------------
  // Theme toggle
  // --------------------
  const btnToggleTheme = document.getElementById('btnToggleTheme');
  btnToggleTheme?.addEventListener('click', () => {
    const html = document.documentElement;
    const cur = html.getAttribute('data-bs-theme') || 'dark';
    html.setAttribute('data-bs-theme', cur === 'dark' ? 'light' : 'dark');
    btnToggleTheme.innerHTML = cur === 'dark' ? '<i class="bi bi-moon"></i>' : '<i class="bi bi-sun"></i>';
  });

  // --------------------
  // Wizard
  // --------------------
  const stepButtons = Array.from(document.querySelectorAll('.cc-step-btn'));
  const stepPanels = Array.from(document.querySelectorAll('.cc-step-panel'));
  const btnPrev = document.getElementById('btnPrev');
  const btnNext = document.getElementById('btnNext');

  let currentStep = 1;
  const totalSteps = 6;

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
  btnPrev.addEventListener('click', () => setStep(currentStep - 1));
  btnNext.addEventListener('click', () => setStep(currentStep + 1));

  // --------------------
  // Helpers
  // --------------------
  const liveName = document.getElementById('liveName');
  const progressBar = document.getElementById('progressBar');
  const saveState = document.getElementById('saveState');

  const sumName = document.getElementById('sumName');
  const sumPronouns = document.getElementById('sumPronouns');
  const sumLine2 = document.getElementById('sumLine2');
  const sumTraits = document.getElementById('sumTraits');
  const validationText = document.getElementById('validationText');

  const v = (id) => (document.getElementById(id)?.value ?? '').toString().trim();
  const i = (id, def=0) => {
    const x = parseInt(document.getElementById(id)?.value ?? '', 10);
    return Number.isFinite(x) ? x : def;
  };
  const signed = (n) => (n >= 0 ? `+${n}` : `${n}`);

  function updateProgress() {
    // Basics required count for UI only
    const required = ['cName','cPronouns','cHeritage','cClass','cLevel'];
    const filled = required.filter(id => v(id)).length;
    const pct = Math.round((filled / required.length) * 100);
    progressBar.style.width = `${pct}%`;
  }

  function updateSummary() {
    const name = v('cName') || 'Unnamed';
    liveName.textContent = name;
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
    const sorted = traitVals.slice().sort((a,b)=>a-b);
    const requiredPool = [-1,0,0,1,1,2];
    const traitOk = sorted.length === 6 && sorted.every((x, idx) => x === requiredPool[idx]);
    if (!traitOk) missing.push('Traits (must be +2,+1,+1,0,0,-1)');

    if (validationText) {
      validationText.textContent = missing.length ? `Missing/Invalid: ${missing.join(', ')}` : 'No validation errors.';
    }
    return missing.length === 0;
  }

  // --------------------
  // API base
  // --------------------
  const API = '/CharacterCreator/api';

  // --------------------
  // Subclasses
  // --------------------
  const cClass = document.getElementById('cClass');
  const cSubClass = document.getElementById('cSubClass');

  async function loadSubclasses(classID) {
    cSubClass.innerHTML = '';
    if (!classID) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'Select class first…';
      cSubClass.appendChild(opt);
      return;
    }
    const res = await fetch(`${API}/get_subclasses.php?classID=${encodeURIComponent(classID)}`, { headers: { 'Accept':'application/json' }});
    const json = await res.json();
    const blank = document.createElement('option');
    blank.value = ''; blank.textContent = 'Select…';
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
    const classID = parseInt(cClass.value || '0', 10) || 0;
    if (!classID) {
      cEvasion.value = '';
      cHPMax.value = '';
      cHopeFeature.value = '';
      return;
    }
    const res = await fetch(`${API}/get_class_info.php?classID=${encodeURIComponent(classID)}`, { headers: { 'Accept':'application/json' }});
    const json = await res.json();
    if (!json.ok || !json.row) return;
    cEvasion.value = (json.row.starting_evasion_score ?? '').toString();
    cHPMax.value = (json.row.starting_hit_point ?? '').toString();
    cHopeFeature.value = (json.row.hope_feature ?? '').toString();
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
    const lvl = parseInt(cLevel.value || '1', 10) || 1;
    aName.innerHTML = '';
    const blank = document.createElement('option');
    blank.value = ''; blank.textContent = 'Select…';
    aName.appendChild(blank);

    const res = await fetch(`${API}/get_armors.php?level=${encodeURIComponent(lvl)}`, { headers: { 'Accept':'application/json' }});
    const json = await res.json();
    if (!json.ok) return;

    json.rows.forEach(r => {
      const opt = document.createElement('option');
      opt.value = String(r.armorID);
      opt.textContent = r.name;
      aName.appendChild(opt);
    });

    armorLevelHint.textContent = `Showing armors with min_level ≤ ${lvl}.`;
    await loadArmorInfo();
  }

  async function loadArmorInfo() {
    const armorID = parseInt(aName.value || '0', 10) || 0;
    if (!armorID) {
      cArmor.value = '';
      thMajor.textContent = '—';
      thSevere.textContent = '—';
      aFeat.value = '';
      return;
    }
    const res = await fetch(`${API}/get_armor_info.php?armorID=${encodeURIComponent(armorID)}`, { headers: { 'Accept':'application/json' }});
    const json = await res.json();
    if (!json.ok || !json.row) return;
    cArmor.value = (json.row.base_score ?? '').toString();
    thMajor.textContent = (json.row.major_threshold ?? '—').toString();
    thSevere.textContent = (json.row.severe_threshold ?? '—').toString();
    aFeat.value = (json.row.feature ?? '').toString();
  }

  // --------------------
  // Traits: integrity pool UI
  // --------------------
  const traitSelects = Array.from(document.querySelectorAll('.trait-select'));
  const TRAIT_POOL = new Map([[2,1],[1,2],[0,2],[-1,1]]);

  function initTraitSelects() {
    traitSelects.forEach(sel => {
      sel.innerHTML = '';
      const blank = document.createElement('option');
      blank.value = ''; blank.textContent = 'Select…';
      sel.appendChild(blank);
    });
    refreshTraitOptions();
  }

  function countMap(arr) {
    const m = new Map();
    for (const x of arr) m.set(x, (m.get(x)||0)+1);
    return m;
  }

  function refreshTraitOptions() {
    traitSelects.forEach(sel => {
      const cur = sel.value === '' ? null : parseInt(sel.value, 10);
      const otherVals = traitSelects
        .filter(s => s !== sel)
        .map(s => s.value)
        .filter(x => x !== '')
        .map(x => parseInt(x,10));
      const used = countMap(otherVals);

      sel.innerHTML = '';
      const blank = document.createElement('option');
      blank.value=''; blank.textContent='Select…';
      sel.appendChild(blank);

      const order = [2,1,0,-1];
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
  // Experiences list (like before)
  // --------------------
  const expInput = document.getElementById('expInput');
  const btnAddExp = document.getElementById('btnAddExp');
  const expList = document.getElementById('expList');
  let experiences = [];

  function norm(s){ return (s ?? '').toString().trim().replace(/\s+/g,' '); }

  function renderExp() {
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
    const t = norm(expInput.value);
    if (!t) return;
    const capped = t.length > 200 ? t.slice(0,200) : t;
    if (!experiences.includes(capped)) experiences.push(capped);
    expInput.value = '';
    renderExp();
  }

  btnAddExp?.addEventListener('click', addExp);
  expInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addExp(); }});

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
    selectEl.innerHTML = '';
    const blank = document.createElement('option');
    blank.value = ''; blank.textContent = 'Select…';
    selectEl.appendChild(blank);

    const res = await fetch(`${API}/get_weapons.php?primary=${encodeURIComponent(primaryFlag)}`, { headers: { 'Accept':'application/json' }});
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
    const res = await fetch(`${API}/get_weapon_info.php?weaponID=${encodeURIComponent(weaponID)}`, { headers: { 'Accept':'application/json' }});
    const json = await res.json();
    if (!json.ok) return null;
    return json.row || null;
  }

  async function applyWeapon(prefix, weaponID) {
    const row = weaponID ? await loadWeaponInfo(weaponID) : null;
    const t = row ? (row.trait ?? '') : '';
    const r = row ? (row.range ?? '') : '';
    const d = row ? (row.damage ?? '') : '';
    const f = row ? (row.feature ?? '') : '';

    if (prefix === 1) { w1Trait.value=t; w1Range.value=r; w1Dmg.value=d; w1Feat.value=f; }
    else { w2Trait.value=t; w2Range.value=r; w2Dmg.value=d; w2Feat.value=f; }
  }

  w1Select?.addEventListener('change', () => applyWeapon(1, parseInt(w1Select.value||'0',10)||0));
  w2Select?.addEventListener('change', () => applyWeapon(2, parseInt(w2Select.value||'0',10)||0));

  // --------------------
  // Inventory list (name, description, amount)
  // --------------------
  const invName = document.getElementById('invName');
  const invDesc = document.getElementById('invDesc');
  const invAmt  = document.getElementById('invAmt');
  const btnAddInv = document.getElementById('btnAddInv');
  const invList = document.getElementById('invList');

  let inventory = [];

  function clampInt(x, def=1) {
    const n = parseInt(x, 10);
    return Number.isFinite(n) ? n : def;
  }

  function renderInv() {
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
    const name = norm(invName.value);
    if (!name) return;
    const desc = norm(invDesc.value);
    let amt = clampInt(invAmt.value, 1);
    if (amt < 0) amt = 0;

    const cappedName = name.length > 120 ? name.slice(0,120) : name;
    const cappedDesc = desc.length > 500 ? desc.slice(0,500) : desc;

    const key = (cappedName + '|' + cappedDesc).toLowerCase();
    const existing = inventory.findIndex(r => ((r.item+'|'+r.description).toLowerCase() === key));
    if (existing >= 0) inventory[existing].amount = clampInt(inventory[existing].amount, 0) + amt;
    else inventory.push({ item: cappedName, description: cappedDesc, amount: amt });

    invName.value=''; invDesc.value=''; invAmt.value='1';
    renderInv();
  }

  btnAddInv?.addEventListener('click', addInv);
  [invName, invDesc, invAmt].forEach(el => el?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); addInv(); }
  }));

  // --------------------
  // Bind changes to update UI only
  // --------------------
  ['cName','cPronouns','cHeritage','cCommunity','cLevel','cSubClass'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', () => { updateProgress(); updateSummary(); validateAll(); });
    document.getElementById(id)?.addEventListener('change', () => { updateProgress(); updateSummary(); validateAll(); });
  });

  cClass?.addEventListener('change', async () => {
    await loadSubclasses(cClass.value);
    await loadClassDerived();
    validateAll();
    updateSummary();
  });

  cLevel?.addEventListener('change', async () => {
    await loadArmorOptions();
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
        armorID: parseInt(aName.value || '0', 10) || 0
      },
      experiences: experiences.slice(),
      gear: {
        primaryWeaponID: parseInt(w1Select.value || '0', 10) || 0,
        secondaryWeaponID: parseInt(w2Select.value || '0', 10) || 0,
        gold: {
          handfuls: i('gHandfuls', 0),
          bags: i('gBags', 0),
          chest: i('gChest', 0),
        }
      },
      inventory: inventory.slice()
    };
  }

  btnSaveAll?.addEventListener('click', async () => {
    const ok = validateAll();
    if (!ok) {
      setStep(6);
      alert('Fix validation errors before saving.');
      return;
    }

    saveState.textContent = 'Saving…';

    const payload = collectPayload();
    try {
      const res = await fetch(`${API}/save_all.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept':'application/json' },
        body: JSON.stringify(payload)
      });
      const json = await res.json();
      if (!json.ok) {
        saveState.textContent = 'Save failed';
        alert(json.error || 'Save failed');
        return;
      }
      saveState.textContent = `Saved (ID ${json.characterID})`;
      alert(`Saved! characterID=${json.characterID}`);
    } catch (e) {
      console.error(e);
      saveState.textContent = 'Save failed';
      alert('Network/Server error');
    }
  });

  // --------------------
  // Init loads
  // --------------------
  initTraitSelects();
  renderExp();
  renderInv();
  (async () => {
    await loadSubclasses(cClass?.value || '');
    await loadClassDerived();
    await loadArmorOptions();
    await loadWeapons(w1Select, 1);
    await loadWeapons(w2Select, 0);
    setStep(1);
    updateProgress();
    updateSummary();
    validateAll();
  })();
})();