<?php
require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    redirect('/admin/dashboard.php');
}

$pageTitle = 'Iniciar sesión';
$flashMsg  = get_flash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logoSoccerApp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <style>
        /* ── Login page standalone ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--color-dark);
            font-family: var(--font-body, 'Inter', sans-serif);
        }

        /* Fondo con patrón de campo igual al hero del index */
        .login-page {
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 40px 20px;
        }

        .login-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 50% 30%, rgba(255,214,0,0.07) 0%, transparent 70%),
                repeating-linear-gradient(0deg, transparent, transparent 79px, rgba(255,255,255,0.018) 80px),
                repeating-linear-gradient(90deg, transparent, transparent 79px, rgba(255,255,255,0.012) 80px);
            pointer-events: none;
        }

        /* Círculo decorativo */
        .login-page::after {
            content: '';
            position: absolute;
            bottom: -120px;
            right: -120px;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            border: 70px solid rgba(255,255,255,0.03);
            pointer-events: none;
        }

        .login-deco-circle {
            position: absolute;
            top: -100px;
            left: -100px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            border: 60px solid rgba(255,255,255,0.025);
            pointer-events: none;
        }

        /* Contenido centrado */
        .login-inner {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
        }

        /* Logo + nombre */
        .login-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 36px;
            text-decoration: none;
            gap: 10px;
        }

        .login-brand img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            filter: drop-shadow(0 8px 28px rgba(0,0,0,0.6));
            animation: login-pop 0.5s cubic-bezier(.34,1.56,.64,1) both;
        }

        @keyframes login-pop {
            from { opacity: 0; transform: scale(0.75); }
            to   { opacity: 1; transform: scale(1); }
        }

        .login-brand-name {
            font-size: 1.5rem;
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.02em;
            line-height: 1;
        }

        .login-brand-sub {
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--color-primary);
        }

        /* Tarjeta del formulario */
        .login-card {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(16px);
            border-radius: 16px;
            padding: 36px 36px 32px;
            animation: login-fade-up 0.5s 0.1s both;
        }

        @keyframes login-fade-up {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .login-card-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 4px;
        }

        .login-card-sub {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.4);
            margin-bottom: 28px;
        }

        .login-field {
            margin-bottom: 18px;
        }

        .login-field label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.55);
            margin-bottom: 7px;
        }

        .login-input-wrap {
            position: relative;
        }

        .login-input-wrap .ms {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: rgba(255,255,255,0.3);
            pointer-events: none;
        }

        .login-field input {
            width: 100%;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            padding: 13px 14px 13px 42px;
            font-size: 0.95rem;
            color: #fff;
            outline: none;
            transition: border-color 0.15s, background 0.15s;
        }

        .login-field input::placeholder { color: rgba(255,255,255,0.25); }

        .login-field input:focus {
            border-color: var(--color-primary);
            background: rgba(255,255,255,0.1);
        }

        .login-submit {
            width: 100%;
            padding: 14px;
            background: var(--color-primary);
            color: var(--color-dark);
            font-weight: 900;
            font-size: 0.9rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 8px;
            transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
            box-shadow: 0 4px 20px rgba(255,214,0,0.3);
        }

        .login-submit:hover {
            background: #ffe033;
            transform: translateY(-1px);
            box-shadow: 0 8px 28px rgba(255,214,0,0.4);
        }

        .login-submit:active { transform: translateY(0); }

        /* Alert */
        .login-alert {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.35);
            border-radius: 8px;
            color: #fca5a5;
            font-size: 0.85rem;
            padding: 11px 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .login-alert-success {
            background: rgba(34,197,94,0.12);
            border-color: rgba(34,197,94,0.3);
            color: #86efac;
        }

        /* Volver al inicio */
        .login-back {
            margin-top: 24px;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.35);
            text-align: center;
        }

        .login-back a {
            color: rgba(255,255,255,0.55);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.15s;
        }

        .login-back a:hover { color: var(--color-primary); }
    </style>
</head>
<body>

<div class="login-page">
    <div class="login-deco-circle"></div>

    <div class="login-inner">

        <!-- Logo / Marca -->
        <a href="<?= BASE_URL ?>/index.php" class="login-brand">
            <img src="<?= BASE_URL ?>/assets/img/logoSoccerApp.png" alt="SoccerAPP">
            <span class="login-brand-name">SoccerAPP</span>
            <span class="login-brand-sub">Torneos &amp; Estadísticas</span>
        </a>

        <!-- Formulario -->
        <div class="login-card">
            <div class="login-card-title">Bienvenido de vuelta</div>
            <div class="login-card-sub">Ingresa tus credenciales para continuar</div>

            <?php if ($flashMsg): ?>
            <div class="login-alert <?= $flashMsg['tipo'] === 'success' ? 'login-alert-success' : '' ?>">
                <span class="ms" style="font-size:16px;"><?= $flashMsg['tipo'] === 'success' ? 'check_circle' : 'error' ?></span>
                <?= h($flashMsg['mensaje']) ?>
            </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/auth/login.php" method="post">
                <?= csrf_field() ?>

                <div class="login-field">
                    <label for="email">Email</label>
                    <div class="login-input-wrap">
                        <span class="ms">person</span>
                        <input type="email" id="email" name="email" required autofocus
                               placeholder="tu@email.com">
                    </div>
                </div>

                <div class="login-field">
                    <label for="password">Contraseña</label>
                    <div class="login-input-wrap">
                        <span class="ms">lock</span>
                        <input type="password" id="password" name="password" required
                               placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="login-submit">
                    <span class="ms">login</span> Ingresar
                </button>
            </form>
        </div>

        <!-- Volver -->
        <div class="login-back">
            <a href="<?= BASE_URL ?>/index.php">
                <span class="ms" style="font-size:14px;">arrow_back</span>
                Volver al inicio
            </a>
        </div>

    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/alpine.min.js" defer></script>
</body>
</html>
