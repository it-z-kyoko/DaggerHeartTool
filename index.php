<!-- /index.php  (English rewrite + PHP includes for nav/footer + correct auth links)
     Assumes:
     - /Global/nav.html exists (your screenshot shows it)
     - /Global/footer.html exists (your screenshot shows it)
     - /Login/signup.php and /Login/login.php exist
-->

<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Daggerheart Nexus – Campaigns & Characters</title>

  <!-- Bootstrap 5.3 -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  />
  <!-- Icons -->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
  />
  <link rel="stylesheet" href="Global/styles.css" />
</head>

<body>

  <!-- NAV (PHP include) -->
  <?php include __DIR__ . "/Global/nav.html"; ?>

  <!-- Hero -->
  <header id="top" class="py-5">
    <div class="container py-4">
      <div class="row align-items-center g-4">
        <div class="col-lg-8">
          <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="chip"><i class="bi bi-journal-bookmark"></i> Track campaigns</span>
            <span class="chip"><i class="bi bi-person-gear"></i> Build characters</span>
            <span class="chip"><i class="bi bi-eye"></i> GM overview</span>
          </div>

          <div class="kicker mb-2">Built for Daggerheart groups</div>
          <h1 class="display-4 fw-bold hero-title mb-3">
            Campaign notes, character sheets, and a GM overview — clean, fast, table-ready.
          </h1>
          <p class="lead muted mb-4">
            Daggerheart Nexus is a lightweight web app for your table:
            keep campaign progress in one place, maintain character sheets, and give the GM a quick overview —
            without clutter, admin noise, or menu overload.
          </p>

          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-brand btn-lg" href="Login/signup.php">
              Get started for free <i class="bi bi-arrow-right ms-2"></i>
            </a>
            <a class="btn btn-ghost btn-lg" href="CharacterSheet/sheet.html">
              <i class="bi bi-person-plus me-2"></i> Create a character
            </a>
          </div>

          <div class="mt-3 muted small">
            Already have an account? <a class="small-link" href="Login/login.php">Sign in</a>
            <span class="mx-2">•</span>
            <a class="small-link" href="#features">Learn more</a>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="glass rounded-4 p-4">
            <div class="fw-semibold mb-2">What you get</div>
            <ul class="muted mb-0">
              <li class="mb-2">User-first flow: create, play, document.</li>
              <li class="mb-2">Clear separation: player views vs. GM tools.</li>
              <li class="mb-2">Mobile-friendly for in-session use.</li>
              <li>Designed to grow (import/export, sharing, roles).</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="glass rounded-4 p-3 p-lg-4 d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-check2-circle"></i>
          <span class="muted">Quick start — no setup headache</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-phone"></i>
          <span class="muted">Great for table & Discord</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-shield-check"></i>
          <span class="muted">Concept: private GM notes vs. player-visible info</span>
        </div>
      </div>
    </div>
  </header>

  <!-- Features -->
  <section id="features" class="py-5">
    <div class="container">
      <div class="text-center mb-4">
        <div class="kicker mb-2">Features</div>
        <h2 class="fw-bold mb-2">The three core areas</h2>
        <p class="muted mb-0">Built for players and GMs — not as an admin landing page.</p>
      </div>

      <div class="row g-3">
        <div class="col-md-6 col-xl-4">
          <div class="glass feature rounded-4 p-4">
            <div class="d-flex align-items-start gap-3">
              <span class="icon-pill"><i class="bi bi-journal-bookmark"></i></span>
              <div>
                <h5 class="mb-1">Track campaigns</h5>
                <p class="muted mb-3">
                  Session summaries, decisions, NPCs, locations, and open threads — so you can jump back in instantly.
                </p>
                <ul class="muted small mb-0">
                  <li>Sessions: date, summary, notes</li>
                  <li>Tags for plotlines, factions, locations</li>
                  <li>Open threads as a quick list</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-xl-4">
          <div class="glass feature rounded-4 p-4">
            <div class="d-flex align-items-start gap-3">
              <span class="icon-pill"><i class="bi bi-person-gear"></i></span>
              <div>
                <h5 class="mb-1">Create & edit characters</h5>
                <p class="muted mb-3">
                  A builder designed for actual play: stats, resources, and notes where you need them.
                </p>
                <ul class="muted small mb-0">
                  <li>Character list with search/filter</li>
                  <li>Fast edits (forms/drawers later)</li>
                  <li>Optional export/share</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-xl-4">
          <div class="glass feature rounded-4 p-4">
            <div class="d-flex align-items-start gap-3">
              <span class="icon-pill"><i class="bi bi-eye"></i></span>
              <div>
                <h5 class="mb-1">GM view across player sheets</h5>
                <p class="muted mb-3">
                  A campaign dashboard with the player info that matters — ideal for encounters and quick status checks.
                </p>
                <ul class="muted small mb-0">
                  <li>Per-campaign overview (grid/table)</li>
                  <li>Private GM notes separated</li>
                  <li>Quick comparison at a glance</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="glass rounded-4 p-4 mt-4">
        <div class="row align-items-center g-3">
          <div class="col-lg-8">
            <h5 class="mb-1">Start without friction</h5>
            <div class="muted">
              Create a character or start a campaign — the UI pushes you straight to the fun parts.
            </div>
          </div>
          <div class="col-lg-4 text-lg-end">
            <a class="btn btn-brand" href="Login/signup.php">
              Get started for free <i class="bi bi-arrow-right ms-2"></i>
            </a>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- How it works -->
  <section id="how" class="py-5">
    <div class="container">
      <div class="row g-4 align-items-stretch">
        <div class="col-lg-5">
          <div class="glass rounded-4 p-4 h-100">
            <div class="kicker mb-2">How it works</div>
            <h2 class="fw-bold mb-3">Three steps to a session-ready table</h2>
            <p class="muted mb-0">
              Create an account, start or join a campaign, maintain your character sheet — done.
              The structure is designed to later plug cleanly into real DB-backed data.
            </p>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="row g-3 h-100">
            <div class="col-md-4">
              <div class="glass rounded-4 p-4 h-100">
                <div class="icon-pill mb-3"><i class="bi bi-person-plus"></i></div>
                <h5>1) Account</h5>
                <p class="muted mb-0">Sign up or sign in, then start immediately.</p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="glass rounded-4 p-4 h-100">
                <div class="icon-pill mb-3"><i class="bi bi-journal-plus"></i></div>
                <h5>2) Campaign</h5>
                <p class="muted mb-0">Create or join, then track sessions and story beats.</p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="glass rounded-4 p-4 h-100">
                <div class="icon-pill mb-3"><i class="bi bi-card-checklist"></i></div>
                <h5>3) Character</h5>
                <p class="muted mb-0">Update sheets fast during play.</p>
              </div>
            </div>

            <div class="col-12">
              <div class="glass rounded-4 p-4">
                <div class="d-flex flex-wrap gap-2">
                  <a class="btn btn-ghost" href="campaigns.html"><i class="bi bi-journal-bookmark me-2"></i>Campaigns</a>
                  <a class="btn btn-ghost" href="characters.html"><i class="bi bi-person-gear me-2"></i>Characters</a>
                  <a class="btn btn-ghost" href="gm.html"><i class="bi bi-eye me-2"></i>GM View</a>
                </div>
                <div class="muted small mt-2">
                  These pages are stubs for now: create the HTML files next, then fill them later via API/DB.
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section id="faq" class="py-5">
    <div class="container">
      <div class="text-center mb-4">
        <div class="kicker mb-2">FAQ</div>
        <h2 class="fw-bold mb-2">Quick answers</h2>
        <p class="muted mb-0">Placeholder copy — adjust to your final feature set.</p>
      </div>

      <div class="row justify-content-center">
        <div class="col-lg-9">
          <div class="accordion" id="faqAcc">
            <div class="accordion-item mb-2">
              <h2 class="accordion-header" id="q1">
                <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#a1"
                        aria-expanded="false" aria-controls="a1">
                  Is this a VTT like Roll20?
                </button>
              </h2>
              <div id="a1" class="accordion-collapse collapse" aria-labelledby="q1" data-bs-parent="#faqAcc">
                <div class="accordion-body rounded-bottom-4">
                  The focus is campaign & character management plus a GM overview. A VTT integration could come later.
                </div>
              </div>
            </div>

            <div class="accordion-item mb-2">
              <h2 class="accordion-header" id="q2">
                <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#a2"
                        aria-expanded="false" aria-controls="a2">
                  Can GM notes stay private?
                </button>
              </h2>
              <div id="a2" class="accordion-collapse collapse" aria-labelledby="q2" data-bs-parent="#faqAcc">
                <div class="accordion-body rounded-bottom-4">
                  Yes. Private GM notes vs. player-visible info is a core concept.
                </div>
              </div>
            </div>

            <div class="accordion-item mb-2">
              <h2 class="accordion-header" id="q3">
                <button class="accordion-button collapsed rounded-4" type="button" data-bs-toggle="collapse" data-bs-target="#a3"
                        aria-expanded="false" aria-controls="a3">
                  Do I need a lot of setup?
                </button>
              </h2>
              <div id="a3" class="accordion-collapse collapse" aria-labelledby="q3" data-bs-parent="#faqAcc">
                <div class="accordion-body rounded-bottom-4">
                  No. The goal is a fast start: create/join a campaign, build a character, start playing.
                </div>
              </div>
            </div>
          </div>

          <div class="text-center mt-4">
            <a class="btn btn-brand btn-lg" href="Login/signup.php">
              Start now <i class="bi bi-arrow-right ms-2"></i>
            </a>
            <div class="muted small mt-2">
              Or <a class="small-link" href="Login/login.php">sign in</a> if you already have an account.
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- About -->
  <section id="about" class="py-5">
    <div class="container">
      <div class="glass rounded-4 p-4 p-lg-5">
        <div class="row g-4 align-items-center">
          <div class="col-lg-8">
            <div class="kicker mb-2">About</div>
            <h2 class="fw-bold mb-2">Hi — I’m the developer behind Daggerheart Nexus.</h2>
            <p class="muted mb-3">
              I’m building this tool because I’d rather play than juggle five documents mid-session.
              The goal is an interface that feels like a good table: clear, fast, and frictionless.
            </p>
            <div class="d-flex flex-wrap gap-2">
              <span class="badge text-bg-secondary"><i class="bi bi-code-slash me-1"></i> Web Dev</span>
              <span class="badge text-bg-secondary"><i class="bi bi-dice-5 me-1"></i> TTRPGs</span>
              <span class="badge text-bg-secondary"><i class="bi bi-lightning-charge me-1"></i> UX focus</span>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="glass rounded-4 p-4">
              <div class="fw-semibold mb-2">Contact / Links</div>
              <div class="muted small mb-3">
                Replace these buttons with your real links (GitHub, Discord, website).
              </div>
              <div class="d-grid gap-2">
                <a class="btn btn-ghost" href="contact.html"><i class="bi bi-envelope me-2"></i>Contact</a>
                <a class="btn btn-ghost" href="#"><i class="bi bi-github me-2"></i>GitHub</a>
                <a class="btn btn-ghost" href="#"><i class="bi bi-discord me-2"></i>Discord</a>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </section>

  <!-- FOOTER (PHP include) -->
  <?php include __DIR__ . "/Global/footer.html"; ?>

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
  ></script>
</body>
</html>
