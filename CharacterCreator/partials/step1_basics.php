<div class="cc-step-panel active" data-step-panel="1">
  <div class="cc-block-title">
    <h3>Basics</h3>
    <span class="cc-chip"><i class="bi bi-person"></i> Identity</span>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="cc-field">
        <label for="cName">Name</label>
        <input id="cName" type="text" placeholder="Character name" />
      </div>
    </div>

    <div class="col-12 col-lg-5">
      <div class="cc-field">
        <label for="cPronouns">Pronouns</label>
        <input id="cPronouns" type="text" placeholder="they/them" />
      </div>
    </div>

    <div class="col-12 col-lg-2">
      <div class="cc-field">
        <label for="cLevel">Level</label>
        <input id="cLevel" type="number" min="1" value="1" />
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="cc-field">
        <label for="cHeritage">Heritage</label>
        <select id="cHeritage">
          <option value="">Select…</option>
          <?php foreach (($heritages ?? []) as $row): ?>
            <option value="<?php echo (int)$row['heritageID']; ?>"><?php echo h($row['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="cc-field">
        <label for="cClass">Class</label>
        <select id="cClass">
          <option value="">Select…</option>
          <?php foreach (($classes ?? []) as $row): ?>
            <option value="<?php echo (int)$row['classID']; ?>"><?php echo h($row['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="cc-field">
        <label for="cSubClass">Subclass</label>
        <select id="cSubClass">
          <option value="">Select class first…</option>
        </select>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="cc-field">
        <label for="cCommunity">Community</label>
        <select id="cCommunity">
          <option value="">Select…</option>
          <?php foreach (($communities ?? []) as $row): ?>
            <option value="<?php echo (int)$row['communityID']; ?>"><?php echo h($row['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
</div>