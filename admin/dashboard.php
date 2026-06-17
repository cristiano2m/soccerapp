<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../auth/middleware.php';

// Dashboard no usa require_role() porque es la pantalla de selección de torneo.
// Solo verificamos que el usuario esté autenticado.
if (!is_logged_in()) {
    redirect('/login.php');
}
if (($_SESSION['expires'] ?? 0) < time()) {
    session_destroy();
    redirect('/login.php?msg=session_expired');
}
$_SESSION['expires'] = time() + SESSION_LIFETIME;

$user = current_user();
$db   = Database::getInstance();

// ── Acción: limpiar torneo activo (cambiar torneo) ─────────────────────────
if (isset($_GET['cambiar'])) {
    limpiar_torneo_activo();
    redirect('/admin/dashboard.php');
}

// ── Acción: seleccionar torneo ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seleccionar_torneo'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token de seguridad inválido.');
        redirect('/admin/dashboard.php');
    }

    $tid = (int) ($_POST['torneo_id'] ?? 0);

    if ($user['rol'] === 'super_admin') {
        $torneoExiste = $db->queryOne("SELECT id FROM torneos WHERE id = ?", [$tid]);
        if ($torneoExiste) {
            seleccionar_torneo($tid, 'super_admin');
        }
    } else {
        $asignacion = $db->queryOne(
            "SELECT rol FROM torneo_usuarios WHERE torneo_id = ? AND usuario_id = ?",
            [$tid, $user['id']]
        );
        if ($asignacion) {
            seleccionar_torneo($tid, $asignacion['rol']);
        }
    }
    redirect('/admin/dashboard.php');
}

// ── Modo selector: mostrar lista de torneos ────────────────────────────────
if (empty($_SESSION['torneo_id'])) {
    $torneos = obtener_torneos_accesibles((int) $user['id'], $user['rol']);

    $pageTitle = 'Seleccionar torneo';
    $layout    = 'admin';
    require __DIR__ . '/../views/layout/header.php';
    require __DIR__ . '/../views/layout/sidebar-admin.php';
    ?>
    <div class="toolbar">
        <h1><span class="ms ms-lg">emoji_events</span> Seleccionar torneo</h1>
        <?php if ($user['rol'] === 'super_admin'): ?>
            <div class="actions">
                <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/torneo/index.php">+ Nuevo torneo</a>
            </div>
        <?php endif; ?>
    </div>

    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= h($flash['tipo']) ?>"><?= h($flash['mensaje']) ?></div>
    <?php endif; ?>

    <?php if (empty($torneos)): ?>
        <div class="card">
            <p class="text-muted">No tienes acceso a ningún torneo todavía.</p>
            <?php if ($user['rol'] === 'super_admin'): ?>
                <p style="margin-top:12px;"><a class="btn btn-primary" href="<?= BASE_URL ?>/admin/torneo/index.php">Crear primer torneo</a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-2" style="margin-top:8px;">
        <?php foreach ($torneos as $t): ?>
            <div class="card" style="display:flex; align-items:center; gap:20px;">
                <?php if (!empty($t['logo_url'])): ?>
                    <img src="<?= h($t['logo_url']) ?>" alt="<?= h($t['nombre']) ?>" style="width:64px;height:64px;object-fit:cover;border-radius:50%;flex-shrink:0;">
                <?php else: ?>
                    <div style="width:64px;height:64px;border-radius:50%;background:var(--color-primary);display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0;"><span class="ms" style="font-size:30px;color:var(--color-dark);">emoji_events</span></div>
                <?php endif; ?>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:800; font-size:1.05rem; margin-bottom:2px;"><?= h($t['nombre']) ?></div>
                    <div class="text-muted" style="font-size:0.85rem;">
                        <?= h($t['anio'] ?? '') ?>
                        <?= !empty($t['categoria']) ? ' · ' . h($t['categoria']) : '' ?>
                    </div>
                    <span class="badge badge-<?= h($t['estado'] ?? 'borrador') ?>" style="margin-top:4px; display:inline-block;"><?= h($t['estado'] ?? 'borrador') ?></span>
                </div>
                <div style="display:flex; flex-direction:column; gap:8px; flex-shrink:0;">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="seleccionar_torneo" value="1">
                        <input type="hidden" name="torneo_id" value="<?= (int) $t['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm"><span class="ms">login</span> Entrar</button>
                    </form>
                    <?php if ($user['rol'] === 'super_admin'): ?>
                        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin/torneos/index.php">Gestionar</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
    require __DIR__ . '/../views/layout/footer.php';
    exit;
}


// ── Árbitros van a su portal ───────────────────────────────────────────────
if (($_SESSION['torneo_rol'] ?? '') === 'referee' && ($user['rol'] ?? '') !== 'super_admin') {
    redirect('/admin/arbitro/dashboard.php');
}

// ── Modo dashboard: torneo ya seleccionado ─────────────────────────────────
$torneo = obtener_torneo_activo();

if (!$torneo) {
    limpiar_torneo_activo();
    redirect('/admin/dashboard.php');
}

$pageTitle = 'Dashboard';
$layout    = 'admin';

$stats = ['equipos' => 0, 'partidos' => 0, 'finalizados' => 0, 'jornadas' => 0];
$stats['equipos']    = (int) ($db->queryOne("SELECT COUNT(*) AS c FROM equipos WHERE torneo_id = ? AND activo = 1", [$torneo['id']])['c'] ?? 0);
$stats['partidos']   = (int) ($db->queryOne("SELECT COUNT(*) AS c FROM partidos WHERE torneo_id = ?", [$torneo['id']])['c'] ?? 0);
$stats['finalizados']= (int) ($db->queryOne("SELECT COUNT(*) AS c FROM partidos WHERE torneo_id = ? AND estado = 'finalizado'", [$torneo['id']])['c'] ?? 0);
$stats['jornadas']   = (int) ($db->queryOne("SELECT COUNT(*) AS c FROM jornadas WHERE torneo_id = ?", [$torneo['id']])['c'] ?? 0);

$posiciones       = calcular_posiciones($torneo['id']);
$proximaJornada   = obtener_proxima_jornada($torneo['id']);
$ultimosResultados = obtener_ultimos_resultados($torneo['id'], 5);

require __DIR__ . '/../views/layout/header.php';
require __DIR__ . '/../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1><span class="ms ms-lg">dashboard</span> Dashboard</h1>
    <?php if (!empty($torneo['logo_url'])): ?>
        <div style="display:flex; align-items:center; gap:10px;">
            <img src="<?= h($torneo['logo_url']) ?>" alt="<?= h($torneo['nombre']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:50%;">
            <strong><?= h($torneo['nombre']) ?></strong>
        </div>
    <?php endif; ?>
</div>

<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= h($flash['tipo']) ?>"><?= h($flash['mensaje']) ?></div>
<?php endif; ?>

<div class="grid grid-4" style="margin-bottom:24px;">
    <div class="stat-card"><span class="stat-value"><?= $stats['equipos'] ?></span><span class="stat-label">Equipos activos</span></div>
    <div class="stat-card"><span class="stat-value"><?= $stats['jornadas'] ?></span><span class="stat-label">Jornadas</span></div>
    <div class="stat-card"><span class="stat-value"><?= $stats['partidos'] ?></span><span class="stat-label">Partidos totales</span></div>
    <div class="stat-card"><span class="stat-value"><?= $stats['finalizados'] ?></span><span class="stat-label">Partidos finalizados</span></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2 class="section-title"><span class="ms">leaderboard</span> Tabla de posiciones</h2>
        <?php $limit = 5; require __DIR__ . '/../views/components/tabla-posiciones.php'; ?>
        <p style="margin-top:12px;"><a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/public/posiciones.php" target="_blank">Ver tabla completa</a></p>
    </div>
    <div class="card">
        <h2 class="section-title"><span class="ms">calendar_month</span> Próxima jornada</h2>
        <?php if (!$proximaJornada): ?>
            <p class="text-muted">No hay jornadas pendientes.</p>
        <?php else: ?>
            <p class="text-muted" style="margin-bottom:12px;">Jornada <?= (int) $proximaJornada['numero'] ?><?= !empty($proximaJornada['fecha']) ? ' · ' . h($proximaJornada['fecha']) : '' ?></p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Local</th><th>Visita</th><th>Hora</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($proximaJornada['partidos'] as $p): ?>
                        <tr>
                            <td><?= h($p['local_nombre']) ?></td>
                            <td><?= h($p['visita_nombre']) ?></td>
                            <td><?= $p['hora'] ? h(substr($p['hora'], 0, 5)) : '-' ?></td>
                            <td><span class="badge badge-<?= h($p['estado']) ?>"><?= h(str_replace('_', ' ', $p['estado'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:24px;">
    <h2 class="section-title"><span class="ms">sports_soccer</span> Últimos resultados</h2>
    <?php if (empty($ultimosResultados)): ?>
        <p class="text-muted">Todavía no hay resultados registrados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Jornada</th><th>Local</th><th></th><th>Visita</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($ultimosResultados as $r): ?>
                    <tr>
                        <td>J<?= (int) $r['jornada_numero'] ?></td>
                        <td><?= h($r['local_nombre']) ?></td>
                        <td class="pts"><?= (int) $r['goles_local'] ?> - <?= (int) $r['goles_visita'] ?></td>
                        <td><?= h($r['visita_nombre']) ?></td>
                        <td><?= (!empty($r['wo_local']) || !empty($r['wo_visita'])) ? '<span class="badge badge-wo">W.O.</span>' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
