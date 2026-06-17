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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token de seguridad inválido.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $db->execute("DELETE FROM equipos WHERE id = ? AND torneo_id = ?", [$id, $torneo['id']]);
        set_flash('success', 'Equipo eliminado.');
    }
    redirect('/admin/equipos/index.php');
}

$equipos = $db->query(
    "SELECT e.*, (SELECT COUNT(*) FROM jugadores j WHERE j.equipo_id = e.id) AS num_jugadores
     FROM equipos e WHERE e.torneo_id = ? ORDER BY e.nombre ASC",
    [$torneo['id']]
);

$pageTitle = 'Equipos';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1><span class="ms ms-lg">groups</span> Equipos</h1>
    <div class="actions">
        <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/equipos/importar.php"><span class="ms">upload_file</span> Importar equipos</a>
        <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/equipos/jugadores-importar.php"><span class="ms">upload_file</span> Importar jugadores</a>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/equipos/form.php">+ Nuevo equipo</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Equipo</th>
                    <th>Abrev.</th>
                    <th>Delegado</th>
                    <th>Teléfono</th>
                    <th>Jugadores</th>
                    <th>Activo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($equipos)): ?>
                <tr><td colspan="7" class="text-muted">Aún no hay equipos registrados.</td></tr>
                <?php endif; ?>
                <?php foreach ($equipos as $eq): ?>
                <tr>
                    <td>
                        <div class="team-row">
                            <?= team_badge($eq['nombre'], $eq['abreviatura'], $eq['color_hex'], $eq['logo_url'], 28) ?>
                            <?= h($eq['nombre']) ?>
                        </div>
                    </td>
                    <td><?= h($eq['abreviatura']) ?></td>
                    <td><?= h($eq['delegado']) ?></td>
                    <td><?= h($eq['telefono']) ?></td>
                    <td><?= (int) $eq['num_jugadores'] ?></td>
                    <td><?= $eq['activo'] ? 'Sí' : 'No' ?></td>
                    <td class="actions">
                        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin/equipos/jugadores.php?equipo_id=<?= (int) $eq['id'] ?>">Nómina</a>
                        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin/equipos/form.php?id=<?= (int) $eq['id'] ?>">Editar</a>
                        <form method="post" onsubmit="return confirm('¿Eliminar este equipo y toda su nómina?');" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= (int) $eq['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
