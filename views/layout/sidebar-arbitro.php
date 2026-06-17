<?php
$user    = current_user();
$current = $_SERVER['SCRIPT_NAME'] ?? '';

function arb_active(string $current, string $needle): string
{
    return str_contains($current, $needle) ? 'active' : '';
}

$torneoActivo = obtener_torneo_activo();
?>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="brand">
            <?php if (!empty($torneoActivo['logo_url'])): ?>
                <img src="<?= h($torneoActivo['logo_url']) ?>" alt="" class="brand-logo">
            <?php else: ?>
                <span class="ms" style="font-size:26px;color:var(--color-primary);">sports_soccer</span>
            <?php endif; ?>
            <span>Árbitro</span>
        </div>

        <?php if ($torneoActivo): ?>
        <div class="torneo-activo-label">
            <span><?= h($torneoActivo['nombre']) ?></span>
            <a href="<?= BASE_URL ?>/admin/dashboard.php?cambiar=1" title="Cambiar torneo"><span class="ms">swap_horiz</span></a>
        </div>
        <?php endif; ?>

        <nav>
            <a class="<?= arb_active($current, '/admin/arbitro/dashboard') ?>" href="<?= BASE_URL ?>/admin/arbitro/dashboard.php"><span class="ms">assignment</span> Mis partidos</a>
            <a class="<?= arb_active($current, '/admin/arbitro/torneo') ?>"    href="<?= BASE_URL ?>/admin/arbitro/torneo.php"><span class="ms">emoji_events</span> Torneo</a>
            <a class="<?= arb_active($current, '/admin/arbitro/equipos') ?>"   href="<?= BASE_URL ?>/admin/arbitro/equipos.php"><span class="ms">groups</span> Equipos</a>
            <a class="<?= arb_active($current, '/admin/arbitro/calendario') ?>" href="<?= BASE_URL ?>/admin/arbitro/calendario.php"><span class="ms">calendar_month</span> Calendario</a>
        </nav>
    </aside>

    <div class="admin-content">
        <header class="admin-topbar">
            <button class="admin-menu-toggle" aria-label="Abrir menú"><span class="ms" style="font-size:22px;">menu</span></button>
            <div class="user-info">
                <span><?= h($user['nombre']) ?></span>
                <span class="user-role">Árbitro</span>
                <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-outline btn-sm">Salir</a>
            </div>
        </header>
        <main class="admin-main">
