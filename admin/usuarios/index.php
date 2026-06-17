<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin']);

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token de seguridad inválido.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) current_user()['id']) {
            set_flash('error', 'No puedes eliminar tu propio usuario.');
        } else {
            $db->execute("DELETE FROM usuarios WHERE id = ?", [$id]);
            set_flash('success', 'Usuario eliminado.');
        }
    }
    redirect('/admin/usuarios/index.php');
}

$usuarios = $db->query("SELECT id, nombre, email, rol, activo, created_at FROM usuarios ORDER BY nombre ASC");

$pageTitle = 'Usuarios';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1><span class="ms ms-lg">manage_accounts</span> Usuarios</h1>
    <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/usuarios/form.php">+ Nuevo usuario</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Activo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                <tr><td colspan="5" class="text-muted">No hay usuarios registrados.</td></tr>
                <?php endif; ?>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= h($u['nombre']) ?></td>
                    <td><?= h($u['email']) ?></td>
                    <td><?= h($u['rol']) ?></td>
                    <td><?= $u['activo'] ? 'Sí' : 'No' ?></td>
                    <td class="actions">
                        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin/usuarios/form.php?id=<?= (int) $u['id'] ?>">Editar</a>
                        <?php if ((int) $u['id'] !== (int) current_user()['id']): ?>
                        <form method="post" onsubmit="return confirm('¿Eliminar este usuario?');" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
