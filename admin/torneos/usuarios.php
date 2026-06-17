<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin']);

$db = Database::getInstance();

$tid = (int) ($_GET['torneo_id'] ?? 0);
$torneo = $db->queryOne("SELECT * FROM torneos WHERE id = ?", [$tid]);
if (!$torneo) {
    set_flash('error', 'Torneo no encontrado.');
    redirect('/admin/torneos/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'quitar') {
            $uid = (int) ($_POST['usuario_id'] ?? 0);
            $db->execute(
                "DELETE FROM torneo_usuarios WHERE torneo_id = ? AND usuario_id = ?",
                [$tid, $uid]
            );
            set_flash('success', 'Usuario removido del torneo.');
            redirect('/admin/torneos/usuarios.php?torneo_id=' . $tid);
        }

        if ($accion === 'asignar') {
            $uid = (int) ($_POST['usuario_id'] ?? 0);
            $rol = $_POST['rol'] ?? '';

            if (!in_array($rol, ['organizer', 'referee'], true)) {
                $errors[] = 'Rol no válido.';
            }

            $usuarioExiste = $db->queryOne(
                "SELECT id FROM usuarios WHERE id = ? AND activo = 1",
                [$uid]
            );
            if (!$usuarioExiste) {
                $errors[] = 'Usuario no encontrado o inactivo.';
            }

            if (empty($errors)) {
                try {
                    $db->execute(
                        "INSERT INTO torneo_usuarios (torneo_id, usuario_id, rol) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE rol = VALUES(rol)",
                        [$tid, $uid, $rol]
                    );
                    set_flash('success', 'Usuario asignado al torneo.');
                    redirect('/admin/torneos/usuarios.php?torneo_id=' . $tid);
                } catch (PDOException $e) {
                    $errors[] = 'Error al asignar el usuario.';
                }
            }
        }
    }
}

// Usuarios ya asignados a este torneo
$asignados = $db->query(
    "SELECT u.id, u.nombre, u.email, u.rol AS rol_global, tu.rol AS rol_torneo, tu.created_at
     FROM torneo_usuarios tu
     JOIN usuarios u ON u.id = tu.usuario_id
     WHERE tu.torneo_id = ?
     ORDER BY tu.rol ASC, u.nombre ASC",
    [$tid]
);

// Usuarios disponibles para asignar (no super_admin, no ya asignados)
$asignadosIds = array_column($asignados, 'id');
$disponibles  = $db->query(
    "SELECT id, nombre, email, rol FROM usuarios
     WHERE rol != 'super_admin' AND activo = 1
     ORDER BY nombre ASC"
);
$disponibles = array_filter($disponibles, fn($u) => !in_array((int) $u['id'], $asignadosIds, true));

$pageTitle = 'Usuarios — ' . ($torneo['nombre'] ?? '');
$layout    = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1><span class="ms ms-lg">groups</span> Usuarios: <?= h($torneo['nombre']) ?></h1>
    <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/torneos/index.php">← Volver a torneos</a>
</div>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= h($e) ?></div>
<?php endforeach; ?>
<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= h($flash['tipo']) ?>"><?= h($flash['mensaje']) ?></div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <h2 class="section-title"><span class="ms">person_add</span> Asignar usuario</h2>
        <?php if (empty($disponibles)): ?>
            <p class="text-muted">No hay usuarios disponibles para asignar. <a href="<?= BASE_URL ?>/admin/usuarios/index.php">Crear usuarios</a></p>
        <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="asignar">
            <div class="form-group">
                <label for="usuario_id">Usuario</label>
                <select id="usuario_id" name="usuario_id" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($disponibles as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= h($u['nombre']) ?> (<?= h($u['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="rol">Rol en este torneo</label>
                <select id="rol" name="rol" required>
                    <option value="organizer">Organizador</option>
                    <option value="referee">Árbitro</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Asignar</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="section-title"><span class="ms">badge</span> Asignados (<?= count($asignados) ?>)</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Usuario</th><th>Rol en torneo</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($asignados)): ?>
                    <tr><td colspan="3" class="text-muted">Sin usuarios asignados.</td></tr>
                <?php endif; ?>
                <?php foreach ($asignados as $u): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><?= h($u['nombre']) ?></div>
                            <div class="text-muted" style="font-size:0.82rem;"><?= h($u['email']) ?></div>
                        </td>
                        <td><span class="badge badge-<?= $u['rol_torneo'] === 'organizer' ? 'activo' : 'programado' ?>"><?= $u['rol_torneo'] === 'organizer' ? 'Organizador' : 'Árbitro' ?></span></td>
                        <td>
                            <form method="post" onsubmit="return confirm('¿Quitar a este usuario del torneo?');" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="quitar">
                                <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Quitar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
