<div class="cc-step-panel" data-step-panel="4">
  <div class="cc-block-title">
    <h3>Experience</h3>
    <span class="cc-chip"><i class="bi bi-book"></i> Notes</span>
  </div>

  <div class="cc-sheet-block">
    <div class="cc-muted mb-2">Each entry is stored as its own row (mod=2).</div>

    <div class="cc-field">
      <label for="expInput">Add Experience</label>
      <div class="cc-exp-row">
        <input id="expInput" type="text" placeholder="e.g. Veteran of the Mistroads" />
        <button class="btn btn-outline-light" type="button" id="btnAddExp">
          <i class="bi bi-plus-lg me-2"></i>Add
        </button>
      </div>
    </div>

    <div class="cc-field mb-0">
      <label>Current List</label>
      <div id="expList" class="cc-exp-list"></div>
    </div>
  </div>
</div>