<?php
// Sidebar + topbar del panel admin. Requiere $layout='admin' y haber incluido header.php
$user    = current_user();
$current = $_SERVER['SCRIPT_NAME'] ?? '';

function nav_active(string $current, string $needle): string
{
    return str_contains($current, $needle) ? 'active' : '';
}

$torneoActivo = !empty($_SESSION['torneo_id'])
    ? ($torneo ?? obtener_torneo_activo())
    : null;
?>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="brand">
            <?php if (!empty($torneoActivo['logo_url'])): ?>
                <img src="<?= h($torneoActivo['logo_url']) ?>" alt="<?= h($torneoActivo['nombre']) ?>" class="brand-logo">
            <?php else: ?>
                <span class="ms" style="font-size:26px;color:var(--color-primary);">sports_soccer</span>
            <?php endif; ?>
            <span>Soccer</span>APP
        </div>

        <?php if ($torneoActivo): ?>
        <div class="torneo-activo-label">
            <span><?= h($torneoActivo['nombre']) ?></span>
            <a href="<?= BASE_URL ?>/admin/dashboard.php?cambiar=1" title="Cambiar torneo"><span class="ms">swap_horiz</span></a>
        </div>
        <div class="torneo-url-publica">
            <?php $urlPublica = url_publica_torneo((int) $torneoActivo['id']); ?>
            <a href="<?= h($urlPublica) ?>" target="_blank" title="Ver sitio público"><span class="ms">link</span> URL pública</a>
            <button type="button"
                    onclick="navigator.clipboard.writeText('<?= h($urlPublica) ?>').then(function(){ var b=this; b.textContent='✓'; setTimeout(function(){ b.textContent='Copiar'; },1500); }.bind(this))"
                    class="btn-copy-url">Copiar</button>
        </div>
        <?php endif; ?>

        <nav>
            <a class="<?= nav_active($current, '/admin/dashboard.php') ?>" href="<?= BASE_URL ?>/admin/dashboard.php"><span class="ms">dashboard</span> Dashboard</a>
            <?php if ($torneoActivo): ?>
            <a class="<?= nav_active($current, '/admin/torneo/') ?>" href="<?= BASE_URL ?>/admin/torneo/index.php"><span class="ms">emoji_events</span> Torneo</a>
            <a class="<?= nav_active($current, '/admin/equipos/') ?>" href="<?= BASE_URL ?>/admin/equipos/index.php"><span class="ms">groups</span> Equipos</a>
            <a class="<?= nav_active($current, '/admin/calendario/') ?>" href="<?= BASE_URL ?>/admin/calendario/index.php"><span class="ms">calendar_month</span> Calendario</a>
            <a class="<?= nav_active($current, '/admin/canchas/') ?>" href="<?= BASE_URL ?>/admin/canchas/index.php"><span class="ms">stadium</span> Canchas</a>
            <a class="<?= nav_active($current, '/admin/resultados/') ?>" href="<?= BASE_URL ?>/admin/resultados/index.php"><span class="ms">sports_soccer</span> Resultados</a>
            <div class="section-label" style="font-size:0.65rem;padding-left:16px;margin-top:4px;">Posts Redes</div>
            <a class="<?= nav_active($current, '/admin/posts/jornada') ?>" href="<?= BASE_URL ?>/admin/posts/jornada.php"><span class="ms">calendar_month</span> Jornada</a>
            <a class="<?= nav_active($current, '/admin/posts/resultados') ?>" href="<?= BASE_URL ?>/admin/posts/resultados.php"><span class="ms">scoreboard</span> Resultados</a>
            <a class="<?= nav_active($current, '/admin/posts/posiciones') ?>" href="<?= BASE_URL ?>/admin/posts/posiciones.php"><span class="ms">leaderboard</span> Posiciones</a>
            <a class="<?= nav_active($current, '/admin/patrocinadores/') ?>" href="<?= BASE_URL ?>/admin/patrocinadores/index.php"><span class="ms">verified</span> Patrocinadores</a>
            <?php endif; ?>
            <?php if ($user['rol'] === 'super_admin'): ?>
            <div class="section-label">Sistema</div>
            <a class="<?= nav_active($current, '/admin/torneos/') ?>" href="<?= BASE_URL ?>/admin/torneos/index.php"><span class="ms">emoji_events</span> Torneos</a>
            <a class="<?= nav_active($current, '/admin/usuarios/') ?>" href="<?= BASE_URL ?>/admin/usuarios/index.php"><span class="ms">manage_accounts</span> Usuarios</a>
            <a class="<?= nav_active($current, '/admin/api-tokens/') ?>" href="<?= BASE_URL ?>/admin/api-tokens/index.php"><span class="ms">api</span> API Tokens</a>
            <a class="<?= nav_active($current, '/admin/settings/') ?>" href="<?= BASE_URL ?>/admin/settings/index.php"><span class="ms">settings</span> Configuración</a>
            <?php endif; ?>
            <div class="section-label">Sitio</div>
            <a href="<?= BASE_URL ?>/index.php" target="_blank"><span class="ms">language</span> Ver sitio público</a>
        </nav>
    </aside>
    <div class="admin-content">
        <header class="admin-topbar">
            <button class="admin-menu-toggle" aria-label="Abrir menú"><span class="ms" style="font-size:22px;">menu</span></button>
            <div class="user-info">
                <span><?= h($user['nombre']) ?></span>
                <span class="user-role"><?= h($user['rol']) ?></span>
                <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-outline btn-sm">Salir</a>
            </div>
        </header>
        <main class="admin-main">
