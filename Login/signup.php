<?php
/* /Login/signup.php */
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sign Up</title>

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
          <h2>Sign Up</h2>

          <form id="registerForm" class="mt-4">
            <div class="mb-3 text-start">
              <label for="username" class="form-label">Username</label>
              <input type="text" class="form-control form-control-lg glass" id="username" name="username"
                     placeholder="Username" minlength="3" maxlength="32" required>
            </div>

            <div class="mb-3 text-start">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control form-control-lg glass" id="password" name="password"
                     placeholder="Password" minlength="8" required>
              <div class="form-text text-muted">
                At least 8 characters.
              </div>
            </div>

            <div class="mb-3 text-start">
              <label for="password2" class="form-label">Repeat Password</label>
              <input type="password" class="form-control form-control-lg glass" id="password2" name="password2"
                     placeholder="Repeat password" minlength="8" required>
            </div>

            <div id="registerError" class="alert alert-danger d-none" role="alert"></div>
            <div id="registerOk" class="alert alert-success d-none" role="alert"></div>

            <button type="submit" class="btn btn-brand btn-lg w-100">Create Account</button>
          </form>

          <div class="mt-3 text-center">
            <a href="login.php" class="small-link">Already have an account?</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <script>
    const form = document.getElementById("registerForm");
    const errorBox = document.getElementById("registerError");
    const okBox = document.getElementById("registerOk");

    function showError(msg) {
      okBox.classList.add("d-none");
      errorBox.textContent = msg;
      errorBox.classList.remove("d-none");
    }

    function showOk(msg) {
      errorBox.classList.add("d-none");
      okBox.textContent = msg;
      okBox.classList.remove("d-none");
    }

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      errorBox.classList.add("d-none");
      okBox.classList.add("d-none");

      const username = document.getElementById("username").value.trim();
      const password = document.getElementById("password").value;
      const password2 = document.getElementById("password2").value;

      if (password !== password2) return showError("Passwords do not match.");

      try {
        const res = await fetch("/Login/api_register.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ username, password }),
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok || !data.ok) {
          return showError(data.message || "Registration failed.");
        }

        showOk(data.message || "Account created! Redirecting…");
        setTimeout(() => {
          window.location.href = data.redirect || "/Dashboard/index.php";
        }, 600);

      } catch (err) {
        console.error(err);
        showError("Network error. Please try again.");
      }
    });
  </script>
</body>
</html>