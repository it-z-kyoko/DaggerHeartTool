<div class="cc-step-panel" data-step-panel="5">
  <div class="cc-block-title">
    <h3>Gear</h3>
    <span class="cc-chip"><i class="bi bi-backpack"></i> Equipment</span>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-7">
      <div class="cc-sheet-block">
        <div class="cc-block-title">
          <h3>Weapons</h3>
          <span class="cc-chip"><i class="bi bi-crosshair"></i> Loadout</span>
        </div>

        <div class="cc-sheet-block" style="padding:.85rem;">
          <strong>Primary</strong>
          <div class="row g-2 mt-2">
            <div class="col-12">
              <div class="cc-field mb-0">
                <label for="w1Select">Weapon</label>
                <select class="form-select glass-select" id="w1Select"></select>
              </div>
            </div>

            <div class="col-6">
              <div class="cc-field mb-0">
                <label for="w1Trait">Trait</label>
                <input class="form-control glass-input" id="w1Trait" readonly />
              </div>
            </div>

            <div class="col-6">
              <div class="cc-field mb-0">
                <label for="w1Range">Range</label>
                <input class="form-control glass-input" id="w1Range" readonly />
              </div>
            </div>

            <div class="col-6">
              <div class="cc-field mb-0">
                <label for="w1Dmg">Damage</label>
                <input class="form-control glass-input" id="w1Dmg" readonly />
              </div>
            </div>

            <div class="col-6">
              <div class="cc-field mb-0">
                <label for="w1Feat">Feature</label>
                <input class="form-control glass-input" id="w1Feat" readonly />
              </div>
            </div>
          </div>

          <div class="cc-divider"></div>

          <strong>Secondary</strong>
          <div class="row g-2 mt-2">
            <div class="col-12">
              <div class="cc-field mb-0">
                <label for="w2Select">Weapon</label>
                <select class="form-select glass-select" id="w2Select"></select>
              </div>
            </div>

            <div class="col-6">
              <div class="cc-field mb-0">
                <label for="w2Trait">Trait</label>
                <input class="form-control glass-input" id="w2Trait" readonly />
              </div>
            </div>

            <div class="col-6">
              <div class="cc-field mb-0">
                <label for="w2Range">Range</label>
                <input class="form-control glass-input" id="w2Range" readonly />
              </div>
            </div>

            <div class="col-6">
              <div class="cc-field mb-0">
                <label for="w2Dmg">Damage</label>
                <input class="form-control glass-input" id="w2Dmg" readonly />
              </div>
            </div>

            <div class="col-6">
              <div class="cc-field mb-0">
                <label for="w2Feat">Feature</label>
                <input class="form-control glass-input" id="w2Feat" readonly />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-5">
      <div class="cc-sheet-block">
        <div class="cc-block-title">
          <h3>Inventory</h3>
          <span class="cc-chip"><i class="bi bi-box-seam"></i> Items</span>
        </div>

        <div class="row g-2">
          <div class="col-12">
            <div class="cc-field mb-0">
              <label for="invName">Name</label>
              <input class="form-control glass-input" id="invName" type="text" placeholder="e.g. Rope" />
            </div>
          </div>

          <div class="col-8">
            <div class="cc-field mb-0">
              <label for="invDesc">Description</label>
              <input class="form-control glass-input" id="invDesc" type="text" placeholder="e.g. 30ft hemp rope" />
            </div>
          </div>

          <div class="col-4">
            <div class="cc-field mb-0">
              <label for="invAmt">Amount</label>
              <input class="form-control glass-input" id="invAmt" type="number" min="0" value="1" />
            </div>
          </div>

          <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-outline-light" type="button" id="btnAddInv">
              <i class="bi bi-plus-lg me-2"></i>Add
            </button>
          </div>
        </div>

        <div class="cc-divider"></div>

        <div class="cc-field mb-0">
          <label>Current Inventory</label>
          <div id="invList" class="cc-exp-list"></div>
        </div>

        <div class="cc-divider"></div>

        <div class="d-flex align-items-center justify-content-between mb-2">
          <strong>Gold</strong>
          <span class="cc-muted small">Handfuls / Bags / Chest</span>
        </div>

        <div class="row g-3">
          <div class="col-4">
            <div class="cc-field mb-0">
              <label for="gHandfuls">Handfuls</label>
              <input class="form-control glass-input" id="gHandfuls" type="number" min="0" value="0" />
            </div>
          </div>

          <div class="col-4">
            <div class="cc-field mb-0">
              <label for="gBags">Bags</label>
              <input class="form-control glass-input" id="gBags" type="number" min="0" value="0" />
            </div>
          </div>

          <div class="col-4">
            <div class="cc-field mb-0">
              <label for="gChest">Chest</label>
              <input class="form-control glass-input" id="gChest" type="number" min="0" value="0" />
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>