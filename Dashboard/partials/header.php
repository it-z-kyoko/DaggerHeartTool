<?php
// /Dashboard/partials/header.php
declare(strict_types=1);
?>
<!doctype html>
<html lang="de" data-bs-theme="dark">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

    <style>
        :root {
            --brand: #a78bfa;
            --brand2: #22c55e;
            --ink: rgba(255, 255, 255, .90);
            --muted: rgba(255, 255, 255, .70);
            --surface: rgba(255, 255, 255, .06);
            --surface2: rgba(255, 255, 255, .10);
            --border: rgba(255, 255, 255, .12);
            --navbg: rgba(0, 0, 0, .35);
        }

        body {
            color: var(--ink);
            background:
                radial-gradient(1100px 520px at 18% 10%, rgba(167, 139, 250, .22), transparent 60%),
                radial-gradient(900px 500px at 80% 25%, rgba(34, 197, 94, .12), transparent 60%),
                linear-gradient(0deg, rgba(255, 255, 255, .02), rgba(255, 255, 255, .02));
            min-height: 100vh;
        }

        .muted { color: var(--muted); }

        .glass {
            background: var(--surface);
            border: 1px solid var(--border);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .btn-brand {
            --bs-btn-bg: var(--brand);
            --bs-btn-border-color: var(--brand);
            --bs-btn-hover-bg: #8b5cf6;
            --bs-btn-hover-border-color: #8b5cf6;
            --bs-btn-focus-shadow-rgb: 167, 139, 250;
            --bs-btn-color: #0b0b0f;
            font-weight: 700;
        }

        .panel {
            border-radius: 1rem;
            padding: 1.25rem;
        }

        .panel-title {
            letter-spacing: -0.02em;
            margin-bottom: .75rem;
        }

        .panel-subtitle {
            color: var(--muted);
            margin-bottom: 1rem;
        }

        .feature {
            transition: transform .15s ease, background .15s ease, border-color .15s ease;
            height: 100%;
        }

        .feature:hover {
            transform: translateY(-2px);
            background: var(--surface2);
            border-color: rgba(255, 255, 255, .18);
        }

        .icon-pill {
            width: 44px;
            height: 44px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .12);
        }

        .char-card {
            display: flex;
            gap: 1rem;
            align-items: center;
            padding: .9rem;
            border-radius: 1rem;
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .12);
            text-decoration: none;
            color: var(--ink);
            transition: transform .15s ease, background .15s ease, border-color .15s ease;
        }

        .char-card:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, .08);
            border-color: rgba(255, 255, 255, .18);
            color: var(--ink);
        }

        .char-avatar {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, .14);
            background: rgba(255, 255, 255, .06);
        }

        .char-name {
            font-weight: 700;
            margin: 0;
            line-height: 1.1;
        }

        .char-meta {
            margin: .15rem 0 0 0;
            color: var(--muted);
            font-size: .9rem;
        }

        html[data-bs-theme="light"] {
            --ink: rgba(13, 15, 18, .92);
            --muted: rgba(13, 15, 18, .68);
            --surface: rgba(13, 15, 18, .04);
            --surface2: rgba(13, 15, 18, .07);
            --border: rgba(13, 15, 18, .12);
            --navbg: rgba(255, 255, 255, .70);
        }

        html[data-bs-theme="light"] body {
            color: var(--ink);
            background:
                radial-gradient(1100px 520px at 18% 10%, rgba(167, 139, 250, .20), transparent 62%),
                radial-gradient(900px 500px at 80% 25%, rgba(34, 197, 94, .10), transparent 62%),
                linear-gradient(0deg, rgba(0, 0, 0, .02), rgba(0, 0, 0, .02));
        }
    </style>
</head>

<body></body>