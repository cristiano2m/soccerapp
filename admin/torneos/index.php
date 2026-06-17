<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin']);

$db   = Database::getInstance();
$user = current_user();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'eliminar') {
            $tid = (int) ($_POST['torneo_id'] ?? 0);
            // Si el torneo eliminado era el activo en sesión, limpiar sesión
            if ((int) ($_SESSION['torneo_id'] ?? 0) === $tid) {
                limpiar_torneo_activo();
            }
            $db->execute("DELETE FROM torneos WHERE id = ?", [$tid]);
            set_flash('success', 'Torneo eliminado.');
            redirect('/admin/torneos/index.php');
        }

        if ($accion === 'seleccionar') {
            $tid = (int) ($_POST['torneo_id'] ?? 0);
            $torneoExiste = $db->queryOne("SELECT id FROM torneos WHERE id = ?", [$tid]);
            if ($torneoExiste) {
                seleccionar_torneo($tid, 'super_admin');
                redirect('/admin/dashboard.php');
            }
        }
    }
}

$torneos = $db->query(
    "SELECT t.*,
            (SELECT COUNT(*) FROM equipos e WHERE e.torneo_id = t.id AND e.activo = 1) AS num_equipos,
            (SELECT COUNT(*) FROM partidos p WHERE p.torneo_id = t.id) AS num_partidos,
            (SELECT COUNT(*) FROM torneo_usuarios tu WHERE tu.torneo_id = t.id) AS num_usuarios
     FROM torneos t
     ORDER BY t.id DESC"
);

$pageTitle = 'Gestión de torneos';
$layout    = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1><span class="ms ms-lg">emoji_events</span> Torneos</h1>
    <div class="actions">
        <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/torneo/index.php">+ Nuevo torneo</a>
    </div>
</div>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= h($e) ?></div>
<?php endforeach; ?>
<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= h($flash['tipo']) ?>"><?= h($flash['mensaje']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Torneo</th>
                    <th>Año</th>
                    <th>Estado</th>
                    <th>Equipos</th>
                    <th>Partidos</th>
                    <th>Usuarios</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($torneos)): ?>
                <tr><td colspan="7" class="text-muted">No hay torneos registrados.</td></tr>
            <?php endif; ?>
            <?php foreach ($torneos as $t): ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <?php if (!empty($t['logo_url'])): ?>
                                <img src="<?= h($t['logo_url']) ?>" alt="" style="width:32px;height:32px;object-fit:cover;border-radius:50%;">
                            <?php else: ?>
                                <span style="width:32px;height:32px;border-radius:50%;background:var(--color-primary);display:inline-flex;align-items:center;justify-content:center;"><span class="ms" style="font-size:18px;color:var(--color-dark);">emoji_events</span></span>
                            <?php endif; ?>
                            <strong><?= h($t['nombre']) ?></strong>
                            <?php if ((int) ($_SESSION['torneo_id'] ?? 0) === (int) $t['id']): ?>
                                <span class="badge badge-activo">activo</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?= h((string) ($t['anio'] ?? '')) ?></td>
                    <td><span class="badge badge-<?= h($t['estado'] ?? 'borrador') ?>"><?= h($t['estado'] ?? 'borrador') ?></span></td>
                    <td><?= (int) $t['num_equipos'] ?></td>
                    <td><?= (int) $t['num_partidos'] ?></td>
                    <td><?= (int) $t['num_usuarios'] ?></td>
                    <td class="actions">
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="seleccionar">
                            <input type="hidden" name="torneo_id" value="<?= (int) $t['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm">Entrar</button>
                        </form>
                        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin/torneos/usuarios.php?torneo_id=<?= (int) $t['id'] ?>">Usuarios</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este torneo y todos sus datos? Esta acción no se puede deshacer.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="torneo_id" value="<?= (int) $t['id'] ?>">
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
