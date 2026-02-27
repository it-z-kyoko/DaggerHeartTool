<div class="cc-step-panel" data-step-panel="3">
  <div class="cc-block-title">
    <h3>Defense, Health & Hope</h3>
    <span class="cc-chip"><i class="bi bi-shield-heart"></i> Survival</span>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="cc-sheet-block">
        <div class="cc-block-title">
          <h3>Defense</h3>
          <span class="cc-chip"><i class="bi bi-shield-check"></i> Values</span>
        </div>

        <div class="row g-3">
          <div class="col-6">
            <div class="cc-field mb-0">
              <label for="cEvasion">Evasion</label>
              <input id="cEvasion" type="number" readonly />
            </div>
          </div>
          <div class="col-6">
            <div class="cc-field mb-0">
              <label for="cArmor">Armor</label>
              <input id="cArmor" type="number" readonly />
            </div>
          </div>
        </div>

        <div class="cc-divider"></div>

        <div class="cc-block-title">
          <h3>Armor</h3>
          <span class="cc-chip"><i class="bi bi-shield"></i> Loadout</span>
        </div>

        <div class="cc-field">
          <label for="aName">Active Armor</label>
          <select class="form-select glass-select"> id="aName">
            <option value="">Select…</option>
          </select>
          <div class="cc-muted small mt-1" id="armorLevelHint"></div>
        </div>

        <div class="cc-field">
          <label>Thresholds</label>

          <div class="cc-th-row">
            <div class="cc-th-label">Minor Damage</div>
            <div class="cc-th-arrow"><i class="bi bi-arrow-right"></i></div>
            <div class="cc-th-mid" id="thMajor">—</div>
            <div class="cc-th-arrow"><i class="bi bi-arrow-right"></i></div>
            <div class="cc-th-label">Major Damage</div>
          </div>

          <div class="cc-th-row mt-2">
            <div class="cc-th-label">Major Damage</div>
            <div class="cc-th-arrow"><i class="bi bi-arrow-right"></i></div>
            <div class="cc-th-mid" id="thSevere">—</div>
            <div class="cc-th-arrow"><i class="bi bi-arrow-right"></i></div>
            <div class="cc-th-label">Severe Damage</div>
          </div>
        </div>

        <div class="cc-field mb-0">
          <label for="aFeat">Armor Feature</label>
          <textarea id="aFeat" readonly></textarea>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="cc-sheet-block">
        <div class="cc-block-title">
          <h3>Health</h3>
          <span class="cc-chip"><i class="bi bi-heart-pulse"></i> Tracks</span>
        </div>

        <div class="row g-3">
          <div class="col-6">
            <div class="cc-field mb-0">
              <label for="cHPMax">HP</label>
              <input id="cHPMax" type="number" readonly />
            </div>
          </div>
        </div>

        <div class="cc-divider"></div>

        <div class="cc-block-title">
          <h3>Hope Feature</h3>
          <span class="cc-chip"><i class="bi bi-stars"></i> Feature</span>
        </div>

        <div class="cc-field mb-0">
          <label for="cHopeFeature">Hope Feature</label>
          <textarea id="cHopeFeature" readonly></textarea>
        </div>

        <input id="hopeMax" type="hidden" value="6" />
      </div>
    </div>
  </div>
</div>