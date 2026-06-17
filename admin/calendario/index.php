<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer']);

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) {
    set_flash('error', 'Primero debes configurar el torneo.');
    redirect('/admin/torneo/index.php');
}

$jornadas = $db->query("SELECT * FROM jornadas WHERE torneo_id = ? ORDER BY numero DESC", [$torneo['id']]);

$partidosPorJornada = [];
if (!empty($jornadas)) {
    $partidos = $db->query(
        "SELECT p.*, el.nombre AS local_nombre, el.color_hex AS local_color, el.logo_url AS local_logo, el.abreviatura AS local_abrev,
                ev.nombre AS visita_nombre, ev.color_hex AS visita_color, ev.logo_url AS visita_logo, ev.abreviatura AS visita_abrev,
                r.goles_local, r.goles_visita, r.wo_local, r.wo_visita
         FROM partidos p
         JOIN equipos el ON el.id = p.equipo_local_id
         JOIN equipos ev ON ev.id = p.equipo_visita_id
         LEFT JOIN resultados r ON r.partido_id = p.id
         WHERE p.torneo_id = ?
         ORDER BY p.jornada_id ASC, p.hora ASC, p.id ASC",
        [$torneo['id']]
    );
    foreach ($partidos as $p) {
        $partidosPorJornada[$p['jornada_id']][] = $p;
    }
}

// ── Acción: activar jornada completa ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'activar_jornada') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token de seguridad inválido.');
        redirect('/admin/calendario/index.php');
    }
    $jornadaId = (int) ($_POST['jornada_id'] ?? 0);
    $jornadaOk = $db->queryOne(
        "SELECT id FROM jornadas WHERE id = ? AND torneo_id = ?",
        [$jornadaId, $torneo['id']]
    );
    if ($jornadaOk) {
        $db->execute(
            "UPDATE partidos SET estado = 'en_curso' WHERE jornada_id = ? AND estado = 'programado'",
            [$jornadaId]
        );
        set_flash('success', 'Partidos de la jornada puestos en curso.');
    }
    redirect('/admin/calendario/index.php');
}

$numEquipos = (int) ($db->queryOne("SELECT COUNT(*) AS c FROM equipos WHERE torneo_id = ? AND activo = 1", [$torneo['id']])['c'] ?? 0);

$pageTitle = 'Calendario';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1><span class="ms ms-lg">calendar_month</span> Calendario</h1>
    <div class="actions">
        <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/calendario/importar.php"><span class="ms">upload_file</span> Importar CSV</a>
        <?php if ($numEquipos >= 2): ?>
        <form method="post" action="<?= BASE_URL ?>/admin/calendario/generar.php" onsubmit="return confirm('<?= empty($jornadas) ? '¿Generar el calendario round-robin?' : '¿Regenerar el calendario? Se eliminarán las jornadas y partidos actuales (y sus resultados).' ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">
                <?php if (empty($jornadas)): ?>
                    <span class="ms">settings</span> Generar calendario
                <?php else: ?>
                    <span class="ms">refresh</span> Regenerar calendario
                <?php endif; ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($numEquipos < 2): ?>
    <div class="card">
        <p class="text-muted">Se necesitan al menos 2 equipos activos para generar el calendario. Actualmente hay <?= $numEquipos ?>.</p>
        <p style="margin-top:12px;"><a class="btn btn-outline" href="<?= BASE_URL ?>/admin/equipos/index.php">Ir a equipos</a></p>
    </div>
<?php elseif (empty($jornadas)): ?>
    <div class="card">
        <p class="text-muted">Todavía no se ha generado el calendario. Usa el botón "Generar calendario" para crear las jornadas y partidos (round-robin, todos contra todos).</p>
    </div>
<?php else: ?>
    <?php foreach ($jornadas as $jornada):
        $pJornada      = $partidosPorJornada[$jornada['id']] ?? [];
        $hayProgramado = count(array_filter($pJornada, fn($p) => $p['estado'] === 'programado')) > 0;
    ?>
    <div class="card" style="margin-bottom:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
            <h2 class="section-title" style="margin-bottom:0;">
                Jornada <?= (int) $jornada['numero'] ?>
                <?= !empty($jornada['fecha']) ? '<span class="text-muted" style="font-weight:400;font-size:0.85rem;"> · ' . h($jornada['fecha']) . '</span>' : '' ?>
            </h2>
            <?php if ($hayProgramado): ?>
            <form method="post" onsubmit="return confirm('¿Poner todos los partidos programados de la Jornada <?= (int) $jornada['numero'] ?> en curso?');" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="activar_jornada">
                <input type="hidden" name="jornada_id" value="<?= (int) $jornada['id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="ms">play_circle</span> Iniciar jornada
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Local</th>
                        <th></th>
                        <th>Visita</th>
                        <th>Cancha</th>
                        <th>Hora</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pJornada as $p): ?>
                    <tr>
                        <td><div class="team-row"><?= team_badge($p['local_nombre'], $p['local_abrev'], $p['local_color'], $p['local_logo'], 24) ?> <?= h($p['local_nombre']) ?></div></td>
                        <td class="pts">
                            <?php if ($p['goles_local'] !== null): ?>
                                <?= (int) $p['goles_local'] ?> - <?= (int) $p['goles_visita'] ?>
                            <?php else: ?>
                                vs
                            <?php endif; ?>
                        </td>
                        <td><div class="team-row"><?= team_badge($p['visita_nombre'], $p['visita_abrev'], $p['visita_color'], $p['visita_logo'], 24) ?> <?= h($p['visita_nombre']) ?></div></td>
                        <td><?= h($p['cancha'] ?? '') ?></td>
                        <td><?= $p['hora'] ? h(substr($p['hora'], 0, 5)) : '-' ?></td>
                        <td>
                            <span class="badge badge-<?= h($p['estado']) ?>"><?= h(str_replace('_', ' ', $p['estado'])) ?></span>
                            <?php if (!empty($p['wo_local']) || !empty($p['wo_visita'])): ?> <span class="badge badge-wo">W.O.</span><?php endif; ?>
                        </td>
                        <td class="actions">
                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin/calendario/partido-edit.php?id=<?= (int) $p['id'] ?>">Editar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
