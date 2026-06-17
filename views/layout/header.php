<?php
// Layout compartido — espera (opcionalmente) $pageTitle y $layout ('public'|'admin')
$torneo = (!empty($noTorneoContext)) ? null : ($torneo ?? obtener_torneo_activo());
$layout = $layout ?? 'public';
$pageTitle = $pageTitle ?? APP_NAME;
$flashMsg = get_flash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= h($torneo['nombre'] ?? APP_NAME) ?> — <?= h($torneo['categoria'] ?? '') ?> <?= h((string) ($torneo['anio'] ?? '')) ?>">
    <title><?= h($pageTitle) ?> · <?= h($torneo['nombre'] ?? APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logoSoccerApp.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/logoSoccerApp.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <?php if ($layout === 'admin'): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
    <?php else: ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public.css">
    <?php endif; ?>
    <?php if (!empty($torneo['color_primario'])): ?>
    <style>:root { --color-primary: <?= h($torneo['color_primario']) ?>; }</style>
    <?php endif; ?>
</head>
<body>
<?php if ($layout !== 'admin'):
    $tId  = (int) ($torneo['id'] ?? 0);
    $tQs  = $tId ? '?t=' . $tId : '';
    $home = BASE_URL . '/index.php' . $tQs;
?>
    <header class="site-header">
        <div class="container">
            <a href="<?= h($home) ?>" class="site-logo">
                <?php if ($torneo && !empty($torneo['logo_url'])): ?>
                    <img src="<?= h($torneo['logo_url']) ?>" alt="<?= h($torneo['nombre']) ?>" class="brand-logo brand-logo--torneo">
                    <span class="site-logo-torneo"><?= h($torneo['nombre']) ?></span>
                <?php elseif ($torneo): ?>
                    <img src="<?= BASE_URL ?>/assets/img/logoSoccerApp.png" alt="SoccerAPP" class="brand-logo">
                    <span class="site-logo-sep">|</span>
                    <span class="site-logo-torneo"><?= h($torneo['nombre']) ?></span>
                <?php else: ?>
                    <img src="<?= BASE_URL ?>/assets/img/logoSoccerApp.png" alt="SoccerAPP" class="brand-logo">
                    <span>SoccerAPP</span>
                <?php endif; ?>
            </a>
            <button class="nav-toggle" aria-label="Abrir menú"><span class="ms">menu</span></button>
            <nav class="site-nav">
                <?php if ($torneo): ?>
                    <a href="<?= BASE_URL ?>/index.php" class="nav-back" title="Todos los torneos"><span class="ms" style="font-size:18px;">grid_view</span></a>
                    <a href="<?= h($home) ?>">Inicio</a>
                    <a href="<?= BASE_URL ?>/public/equipos.php<?= $tQs ?>">Equipos</a>
                    <a href="<?= BASE_URL ?>/public/calendario.php<?= $tQs ?>">Calendario</a>
                    <a href="<?= BASE_URL ?>/public/resultados.php<?= $tQs ?>">Resultados</a>
                    <a href="<?= BASE_URL ?>/public/posiciones.php<?= $tQs ?>">Posiciones</a>
                <?php endif; ?>
                <?php if (is_logged_in()): ?>
                    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-primary btn-sm">Panel Admin</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-sm">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
<?php endif; ?>
<?php if ($flashMsg): ?>
    <div class="container" style="padding-top:16px;">
        <div class="alert alert-<?= h($flashMsg['tipo']) ?>" data-autohide><?= h($flashMsg['mensaje']) ?></div>
    </div>
<?php endif; ?>
