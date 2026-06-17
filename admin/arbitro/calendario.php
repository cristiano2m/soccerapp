<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['referee']);

$db     = Database::getInstance();
$torneo = obtener_torneo_activo();
$user   = current_user();

if (!$torneo) redirect('/admin/dashboard.php');

$jornadas = $db->query(
    "SELECT * FROM jornadas WHERE torneo_id = ? ORDER BY numero DESC",
    [(int) $torneo['id']]
);

$pageTitle = 'Calendario';
$layout    = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-arbitro.php';
?>
<h1><span class="ms ms-lg">calendar_month</span> Calendario — <?= h($torneo['nombre']) ?></h1>

<?php foreach ($jornadas as $jornada):
    $partidos = $db->query(
        "SELECT p.*,
                el.nombre AS local_nombre,  el.logo_url AS local_logo,  el.color_hex AS local_color, el.abreviatura AS local_abrev,
                ev.nombre AS visita_nombre, ev.logo_url AS visita_logo, ev.color_hex AS visita_color, ev.abreviatura AS visita_abrev,
                r.goles_local, r.goles_visita
         FROM partidos p
         JOIN equipos el ON el.id = p.equipo_local_id
         JOIN equipos ev ON ev.id = p.equipo_visita_id
         LEFT JOIN resultados r ON r.partido_id = p.id
         WHERE p.jornada_id = ? AND p.estado IN ('en_curso', 'finalizado')
         ORDER BY p.hora ASC, p.id ASC",
        [(int) $jornada['id']]
    );
    if (empty($partidos)) continue;
?>
<div class="card" style="margin-bottom:18px;">
    <h2 class="section-title">
        Jornada <?= (int) $jornada['numero'] ?>
        <?= !empty($jornada['fecha']) ? '<span class="text-muted" style="font-weight:400;font-size:0.85rem;"> · ' . h($jornada['fecha']) . '</span>' : '' ?>
    </h2>
    <?php if (empty($partidos)): ?>
        <p class="text-muted">Sin partidos en esta jornada.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Local</th>
                    <th></th>
                    <th>Visita</th>
                    <th>Cancha</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($partidos as $p):
                $esMiPartido = in_array((int) $user['id'], array_filter([
                    (int) $p['arbitro_id'],
                    (int) $p['arbitro_id2'],
                    (int) $p['arbitro_id3'],
                ]));
            ?>
                <tr <?= $esMiPartido ? 'style="background:rgba(255,214,0,0.06);font-weight:600;"' : '' ?>>
                    <td><?= $p['hora'] ? h(substr($p['hora'], 0, 5)) : '—' ?></td>
                    <td>
                        <div class="team-row">
                            <?= team_badge($p['local_nombre'], $p['local_abrev'], $p['local_color'], $p['local_logo'], 22) ?>
                            <?= h($p['local_nombre']) ?>
                        </div>
                    </td>
                    <td class="pts">
                        <?php if ($p['goles_local'] !== null): ?>
                            <?= (int) $p['goles_local'] ?> – <?= (int) $p['goles_visita'] ?>
                        <?php else: ?>
                            vs
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="team-row">
                            <?= team_badge($p['visita_nombre'], $p['visita_abrev'], $p['visita_color'], $p['visita_logo'], 22) ?>
                            <?= h($p['visita_nombre']) ?>
                        </div>
                    </td>
                    <td class="text-muted"><?= h($p['cancha'] ?? '—') ?></td>
                    <td>
                        <span class="badge badge-<?= h($p['estado']) ?>"><?= h(str_replace('_', ' ', $p['estado'])) ?></span>
                        <?php if ($esMiPartido): ?>
                            <span title="Tu partido" style="font-size:0.8rem;">👤</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (empty($jornadas)): ?>
    <div class="card"><p class="text-muted">No hay jornadas generadas todavía.</p></div>
<?php endif; ?>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
