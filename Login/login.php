<?php
/* ============================================================
   FILE 1: /Login/signin.php  (PAGE ONLY)
   - Shows Bootstrap login UI
   - Sends credentials to /Login/api_login.php via fetch()
   ============================================================ */
?>
<!doctype html>
<html lang="de" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login</title>

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
  <link rel="stylesheet" href="../Global/styles.css" />
</head>

<body>
  <header id="top" class="py-5">
    <div class="container py-4">
      <div class="col-lg-4 mx-auto text-center mb-5">
        <div class="glass rounded-4 p-4">
          <h2>Login</h2>

          <form id="loginForm" class="mt-4">
            <div class="mb-3 text-start">
              <label for="username" class="form-label">Benutzername</label>
              <input type="text" class="form-control form-control-lg glass" id="username" name="username"
                     placeholder="Benutzername" required>
            </div>

            <div class="mb-3 text-start">
              <label for="password" class="form-label">Passwort</label>
              <input type="password" class="form-control form-control-lg glass" id="password" name="password"
                     placeholder="Passwort" required>
            </div>

            <div id="loginError" class="alert alert-danger d-none" role="alert"></div>

            <button type="submit" class="btn btn-brand btn-lg w-100">Login</button>
          </form>

          <div class="mt-3 text-center">
            <a href="signup.php" class="small-link">Noch keinen Account? Registrieren</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <script>
    const form = document.getElementById("loginForm");
    const errorBox = document.getElementById("loginError");

    function showError(msg) {
      errorBox.textContent = msg;
      errorBox.classList.remove("d-none");
    }

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      errorBox.classList.add("d-none");

      const username = document.getElementById("username").value.trim();
      const password = document.getElementById("password").value;

      try {
        const res = await fetch("/Login/api_login.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ username, password }),
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok || !data.ok) {
          return showError(data.message || "Login fehlgeschlagen.");
        }

        window.location.href = data.redirect || "/dashboard.php";
      } catch (err) {
        console.error(err);
        showError("Netzwerkfehler. Bitte erneut versuchen.");
      }
    });
  </script>
</body>
</html>
