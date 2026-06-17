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

$errors = [];
$editCancha = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de seguridad inválido. Intenta de nuevo.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'eliminar') {
            $cid = (int) ($_POST['id'] ?? 0);
            $db->execute("DELETE FROM canchas WHERE id = ? AND torneo_id = ?", [$cid, $torneo['id']]);
            set_flash('success', 'Cancha eliminada.');
            redirect('/admin/canchas/index.php');
        }

        if ($accion === 'guardar') {
            $cid = (int) ($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($nombre === '') {
                $errors[] = 'El nombre de la cancha es obligatorio.';
            }

            if (empty($errors)) {
                try {
                    if ($cid > 0) {
                        $db->execute(
                            "UPDATE canchas SET nombre=?, activo=? WHERE id=? AND torneo_id=?",
                            [$nombre, $activo, $cid, $torneo['id']]
                        );
                        set_flash('success', 'Cancha actualizada.');
                    } else {
                        $db->insert(
                            "INSERT INTO canchas (torneo_id, nombre, activo) VALUES (?,?,?)",
                            [$torneo['id'], $nombre, $activo]
                        );
                        set_flash('success', 'Cancha agregada.');
                    }
                    redirect('/admin/canchas/index.php');
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        $errors[] = 'Ya existe una cancha con ese nombre.';
                    } else {
                        throw $e;
                    }
                }
            }

            $editCancha = ['id' => $cid, 'nombre' => $nombre, 'activo' => $activo];
        }
    }
}

if (!$editCancha && isset($_GET['edit'])) {
    $editCancha = $db->queryOne("SELECT * FROM canchas WHERE id = ? AND torneo_id = ?", [(int) $_GET['edit'], $torneo['id']]);
}

$canchas = $db->query("SELECT * FROM canchas WHERE torneo_id = ? ORDER BY nombre ASC", [$torneo['id']]);

$pageTitle = 'Canchas';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<h1><span class="ms ms-lg">stadium</span> Canchas</h1>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="grid grid-2">
    <div class="card">
        <h2 class="section-title"><?= $editCancha && !empty($editCancha['id']) ? '✏️ Editar cancha' : '➕ Nueva cancha' ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int) ($editCancha['id'] ?? 0) ?>">

            <div class="form-group">
                <label for="nombre">Nombre de la cancha</label>
                <input type="text" id="nombre" name="nombre" value="<?= h($editCancha['nombre'] ?? '') ?>" required maxlength="100">
            </div>

            <div class="form-group checkbox-row">
                <input type="checkbox" id="activo" name="activo" value="1" <?= ($editCancha['activo'] ?? 1) ? 'checked' : '' ?>>
                <label for="activo" style="margin-bottom:0;">Activa (disponible para programar partidos)</label>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <?php if (!empty($editCancha['id'])): ?>
                    <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/canchas/index.php">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 class="section-title"><span class="ms">stadium</span> Canchas registradas (<?= count($canchas) ?>)</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Nombre</th><th>Activa</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php if (empty($canchas)): ?>
                    <tr><td colspan="3" class="text-muted">Sin canchas registradas.</td></tr>
                <?php endif; ?>
                <?php foreach ($canchas as $c): ?>
                    <tr>
                        <td><?= h($c['nombre']) ?></td>
                        <td><?= $c['activo'] ? 'Sí' : 'No' ?></td>
                        <td class="actions">
                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin/canchas/index.php?edit=<?= (int) $c['id'] ?>">Editar</a>
                            <form method="post" onsubmit="return confirm('¿Eliminar esta cancha?');" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
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
