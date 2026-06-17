<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['referee']);

$db     = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) redirect('/admin/dashboard.php');

// Todos los partidos del torneo, agrupados por jornada
$jornadas = $db->query(
    "SELECT * FROM jornadas WHERE torneo_id = ? ORDER BY numero DESC",
    [(int) $torneo['id']]
);

$pageTitle = 'Mis partidos';
$layout    = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-arbitro.php';
?>
<div class="toolbar">
    <h1><span class="ms ms-lg">assignment</span> Partidos</h1>
</div>

<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= h($flash['tipo']) ?>"><?= h($flash['mensaje']) ?></div>
<?php endif; ?>

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
    <h2 class="section-title" style="margin-bottom:14px;">
        Jornada <?= (int) $jornada['numero'] ?>
        <?php if (!empty($jornada['fecha'])): ?>
            <span class="text-muted" style="font-weight:400;font-size:0.85rem;"> · <?= h($jornada['fecha']) ?></span>
        <?php endif; ?>
    </h2>
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
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($partidos as $p): ?>
                <tr <?= $p['estado'] === 'en_curso' ? 'style="background:rgba(37,99,235,0.05);"' : '' ?>>
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
                    <td class="text-muted" style="font-size:0.85rem;"><?= h($p['cancha'] ?? '—') ?></td>
                    <td>
                        <?php if ($p['estado'] === 'en_curso'): ?>
                            <span class="badge" style="background:#2563eb;color:#fff;">● En curso</span>
                        <?php elseif ($p['estado'] === 'finalizado'): ?>
                            <span class="badge" style="background:#16a34a;color:#fff;">✅ Finalizado</span>
                        <?php else: ?>
                            <span class="badge badge-<?= h($p['estado']) ?>"><?= h(str_replace('_', ' ', $p['estado'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?php if ($p['estado'] === 'en_curso'): ?>
                            <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/arbitro/partido.php?id=<?= (int) $p['id'] ?>">
                                <span class="ms">edit_note</span> Registrar
                            </a>
                        <?php else: ?>
                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin/arbitro/partido.php?id=<?= (int) $p['id'] ?>">
                                <span class="ms">visibility</span> Ver
                            </a>
                        <?php endif; ?>
                        <?php if ($p['estado'] === 'finalizado'): ?>
                            <a class="btn btn-dark btn-sm" href="<?= BASE_URL ?>/admin/acta/index.php?id=<?= (int) $p['id'] ?>">
                                <span class="ms">description</span> Acta
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($jornadas)): ?>
    <div class="card"><p class="text-muted">No hay jornadas generadas todavía.</p></div>
<?php endif; ?>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
