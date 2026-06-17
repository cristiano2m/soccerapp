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

$equipoId = (int) ($_GET['equipo_id'] ?? 0);
$equipo = $db->queryOne("SELECT * FROM equipos WHERE id = ? AND torneo_id = ?", [$equipoId, $torneo['id']]);
if (!$equipo) {
    set_flash('error', 'Equipo no encontrado.');
    redirect('/admin/equipos/index.php');
}

$posiciones = ['Portero', 'Defensa', 'Mediocampista', 'Delantero'];
$errors = [];
$editJugador = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de seguridad inválido. Intenta de nuevo.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'eliminar') {
            $jid = (int) ($_POST['id'] ?? 0);
            $db->execute("DELETE FROM jugadores WHERE id = ? AND equipo_id = ?", [$jid, $equipo['id']]);
            set_flash('success', 'Jugador eliminado.');
            redirect('/admin/equipos/jugadores.php?equipo_id=' . $equipo['id']);
        }

        if ($accion === 'guardar') {
            $jid = (int) ($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $numero = (int) ($_POST['numero'] ?? 0);
            $posicion = $_POST['posicion'] ?? '';
            $cedula = trim($_POST['cedula'] ?? '');
            $activo = isset($_POST['activo']) ? 1 : 0;

            if ($nombre === '') {
                $errors[] = 'El nombre del jugador es obligatorio.';
            }
            if ($numero < 1 || $numero > 99) {
                $errors[] = 'El número de camiseta debe estar entre 1 y 99.';
            }
            if (!in_array($posicion, $posiciones, true)) {
                $errors[] = 'Selecciona una posición válida.';
            }

            $fotoUrl = null;
            if ($jid > 0) {
                $actual = $db->queryOne("SELECT foto_url FROM jugadores WHERE id = ? AND equipo_id = ?", [$jid, $equipo['id']]);
                $fotoUrl = $actual['foto_url'] ?? null;
            }
            if (empty($errors)) {
                try {
                    $fotoUrl = handle_image_upload('foto', 'jugadores', $fotoUrl);
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            if (empty($errors)) {
                try {
                    if ($jid > 0) {
                        $db->execute(
                            "UPDATE jugadores SET nombre=?, numero=?, posicion=?, cedula=?, foto_url=?, activo=? WHERE id=? AND equipo_id=?",
                            [$nombre, $numero, $posicion, $cedula, $fotoUrl, $activo, $jid, $equipo['id']]
                        );
                        set_flash('success', 'Jugador actualizado.');
                    } else {
                        $db->insert(
                            "INSERT INTO jugadores (equipo_id, nombre, numero, posicion, cedula, foto_url, activo) VALUES (?,?,?,?,?,?,?)",
                            [$equipo['id'], $nombre, $numero, $posicion, $cedula, $fotoUrl, $activo]
                        );
                        set_flash('success', 'Jugador agregado a la nómina.');
                    }
                    redirect('/admin/equipos/jugadores.php?equipo_id=' . $equipo['id']);
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        $errors[] = 'Ya existe un jugador con ese número en este equipo.';
                    } else {
                        throw $e;
                    }
                }
            }

            $editJugador = [
                'id' => $jid,
                'nombre' => $nombre,
                'numero' => $numero,
                'posicion' => $posicion,
                'cedula' => $cedula,
                'foto_url' => $fotoUrl,
                'activo' => $activo,
            ];
        }
    }
}

if (!$editJugador && isset($_GET['edit'])) {
    $editJugador = $db->queryOne("SELECT * FROM jugadores WHERE id = ? AND equipo_id = ?", [(int) $_GET['edit'], $equipo['id']]);
}

$jugadores = $db->query("SELECT * FROM jugadores WHERE equipo_id = ? ORDER BY numero ASC", [$equipo['id']]);

$pageTitle = 'Nómina · ' . $equipo['nombre'];
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1><?= team_badge($equipo['nombre'], $equipo['abreviatura'], $equipo['color_hex'], $equipo['logo_url'], 28) ?> Nómina · <?= h($equipo['nombre']) ?></h1>
    <div class="actions">
        <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/equipos/jugadores-importar.php">📥 Importar CSV</a>
        <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/equipos/index.php">← Volver a equipos</a>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="grid grid-2">
    <div class="card">
        <h2 class="section-title"><?= $editJugador && !empty($editJugador['id']) ? '✏️ Editar jugador' : '➕ Agregar jugador' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int) ($editJugador['id'] ?? 0) ?>">

            <div class="form-group">
                <label for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" value="<?= h($editJugador['nombre'] ?? '') ?>" required maxlength="100">
            </div>

            <div class="form-row">
                <div class="form-group" style="max-width:120px;">
                    <label for="numero">Número</label>
                    <input type="number" id="numero" name="numero" value="<?= h((string) ($editJugador['numero'] ?? '')) ?>" required min="1" max="99">
                </div>
                <div class="form-group">
                    <label for="posicion">Posición</label>
                    <select id="posicion" name="posicion" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($posiciones as $pos): ?>
                            <option value="<?= $pos ?>" <?= ($editJugador['posicion'] ?? '') === $pos ? 'selected' : '' ?>><?= $pos ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="cedula">ID</label>
                <input type="text" id="cedula" name="cedula" value="<?= h($editJugador['cedula'] ?? '') ?>" maxlength="20">
            </div>

            <div class="form-group">
                <label for="foto">Foto</label>
                <?php if (!empty($editJugador['foto_url'])): ?>
                    <img src="<?= h($editJugador['foto_url']) ?>" alt="Foto actual" style="width:56px;height:56px;object-fit:cover;border-radius:50%;margin-bottom:8px;">
                <?php endif; ?>
                <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp,image/gif">
            </div>

            <div class="form-group checkbox-row">
                <input type="checkbox" id="activo" name="activo" value="1" <?= ($editJugador['activo'] ?? 1) ? 'checked' : '' ?>>
                <label for="activo" style="margin-bottom:0;">Activo</label>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <?php if (!empty($editJugador['id'])): ?>
                    <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/equipos/jugadores.php?equipo_id=<?= $equipo['id'] ?>">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 class="section-title"><span class="ms">person</span> Plantel (<?= count($jugadores) ?>)</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Nombre</th><th>Posición</th><th>Activo</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php if (empty($jugadores)): ?>
                    <tr><td colspan="5" class="text-muted">Sin jugadores registrados.</td></tr>
                <?php endif; ?>
                <?php foreach ($jugadores as $j): ?>
                    <tr>
                        <td><?= (int) $j['numero'] ?></td>
                        <td><?= h($j['nombre']) ?></td>
                        <td><?= h($j['posicion']) ?></td>
                        <td><?= $j['activo'] ? 'Sí' : 'No' ?></td>
                        <td class="actions">
                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/admin/equipos/jugadores.php?equipo_id=<?= $equipo['id'] ?>&edit=<?= (int) $j['id'] ?>">Editar</a>
                            <form method="post" onsubmit="return confirm('¿Eliminar este jugador?');" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
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
