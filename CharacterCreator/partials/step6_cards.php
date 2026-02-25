<div class="cc-step-panel" data-step-panel="6">
  <div class="block-title">
    <h3>Cards</h3>
    <span class="chip"><i class="bi bi-card-image"></i> Abilities</span>
  </div>

  <div class="muted mb-3">
    Fixed cards are derived from your Basics. Then select exactly <strong>2 Domain cards</strong> for your current Level.
  </div>

  <div class="row g-3">
    <!-- Fixed cards -->
    <div class="col-12 col-lg-5">
      <div class="sheet-block">
        <div class="block-title">
          <h3>Fixed Cards</h3>
          <span class="chip"><i class="bi bi-lock"></i> Auto</span>
        </div>

        <div class="field">
          <label>Heritage / Ancestry</label>
          <div class="d-flex gap-3 align-items-start flex-wrap">
            <img id="cardAncestry" src="" alt="Ancestry Card" style="max-width: 100%; width: 240px; border-radius: .85rem; border:1px solid rgba(255,255,255,.12);" />
            <div class="muted small" id="cardAncestryHint">—</div>
          </div>
        </div>

        <div class="field">
          <label>Class Foundation</label>
          <div class="d-flex gap-3 align-items-start flex-wrap">
            <img id="cardClass" src="" alt="Class Card" style="max-width: 100%; width: 240px; border-radius: .85rem; border:1px solid rgba(255,255,255,.12);" />
            <div class="muted small" id="cardClassHint">—</div>
          </div>
        </div>

        <div class="field mb-0">
          <label>Community</label>
          <div class="d-flex gap-3 align-items-start flex-wrap">
            <img id="cardCommunity" src="" alt="Community Card" style="max-width: 100%; width: 240px; border-radius: .85rem; border:1px solid rgba(255,255,255,.12);" />
            <div class="muted small" id="cardCommunityHint">—</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Domain cards -->
    <div class="col-12 col-lg-7">
      <div class="sheet-block">
        <div class="block-title">
          <h3>Domain Cards</h3>
          <span class="chip"><i class="bi bi-stars"></i> Pick 2</span>
        </div>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
          <div class="muted">
            Available cards are determined by your Class Domains and current Level.
          </div>
          <div class="pill">
            Selected: <strong class="ms-1" id="domainSelectedCount">0</strong>/2
          </div>
        </div>

        <div class="muted small mb-2" id="domainHint">Select Class + Subclass + Level to load Domain cards.</div>

        <div id="domainGrid"
             style="display:grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: .75rem;">
          <!-- cards injected by JS -->
        </div>

        <div class="divider"></div>

        <div class="muted small" id="domainSaveState">Not saved yet.</div>
      </div>
    </div>
  </div>
</div>