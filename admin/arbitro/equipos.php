<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['referee']);

$db     = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) redirect('/admin/dashboard.php');

$equipos = $db->query(
    "SELECT * FROM equipos WHERE torneo_id = ? AND activo = 1 ORDER BY nombre ASC",
    [(int) $torneo['id']]
);

$pageTitle = 'Equipos';
$layout    = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-arbitro.php';
?>
<h1><span class="ms ms-lg">groups</span> Equipos</h1>

<?php foreach ($equipos as $equipo):
    $jugadores = $db->query(
        "SELECT * FROM jugadores WHERE equipo_id = ? AND activo = 1 ORDER BY numero ASC",
        [(int) $equipo['id']]
    );
?>
<div class="card" style="margin-bottom:18px;">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
        <?php if (!empty($equipo['logo_url'])): ?>
            <img src="<?= h($equipo['logo_url']) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:50%;">
        <?php else: ?>
            <?= team_badge($equipo['nombre'], $equipo['abreviatura'] ?? '', $equipo['color_hex'] ?? '#1a3a5c', null, 48) ?>
        <?php endif; ?>
        <div>
            <div style="font-weight:800;font-size:1.05rem;"><?= h($equipo['nombre']) ?></div>
            <?php if (!empty($equipo['delegado'])): ?>
                <div class="text-muted" style="font-size:0.85rem;">Delegado: <?= h($equipo['delegado']) ?></div>
            <?php endif; ?>
        </div>
        <div style="margin-left:auto;">
            <span class="badge" style="background:var(--color-gray-light);color:var(--color-dark);">
                <?= count($jugadores) ?> jugadores
            </span>
        </div>
    </div>

    <?php if (!empty($jugadores)): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Nombre</th>
                    <th>Posición</th>
                    <th>ID</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($jugadores as $j): ?>
                <tr>
                    <td class="pts"><?= (int) $j['numero'] ?></td>
                    <td><?= h($j['nombre']) ?></td>
                    <td><?= h($j['posicion'] ?? '—') ?></td>
                    <td class="text-muted" style="font-size:0.85rem;"><?= h($j['cedula'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-muted">Sin jugadores registrados.</p>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (empty($equipos)): ?>
    <div class="card"><p class="text-muted">No hay equipos activos en este torneo.</p></div>
<?php endif; ?>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
