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

$id = (int) ($_GET['id'] ?? 0);
$equipo = null;
if ($id > 0) {
    $equipo = $db->queryOne("SELECT * FROM equipos WHERE id = ? AND torneo_id = ?", [$id, $torneo['id']]);
    if (!$equipo) {
        set_flash('error', 'Equipo no encontrado.');
        redirect('/admin/equipos/index.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de seguridad inválido. Intenta de nuevo.';
    } else {
        $nombre = trim($_POST['nombre'] ?? '');
        $abreviatura = mb_strtoupper(trim($_POST['abreviatura'] ?? ''));
        $colorHex = $_POST['color_hex'] ?? '#3B9EFF';
        $delegado = trim($_POST['delegado'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            $errors[] = 'El nombre del equipo es obligatorio.';
        }
        if ($abreviatura !== '' && mb_strlen($abreviatura) > 4) {
            $errors[] = 'La abreviatura no puede tener más de 4 caracteres.';
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colorHex)) {
            $errors[] = 'El color debe ser un código hexadecimal (#RRGGBB).';
        }

        $logoUrl = $equipo['logo_url'] ?? null;
        if (empty($errors)) {
            try {
                $logoUrl = handle_image_upload('logo', 'equipos', $logoUrl);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (empty($errors)) {
            if ($equipo) {
                $db->execute(
                    "UPDATE equipos SET nombre=?, abreviatura=?, color_hex=?, delegado=?, telefono=?, logo_url=?, activo=? WHERE id=?",
                    [$nombre, $abreviatura, $colorHex, $delegado, $telefono, $logoUrl, $activo, $equipo['id']]
                );
                set_flash('success', 'Equipo actualizado correctamente.');
            } else {
                $db->insert(
                    "INSERT INTO equipos (torneo_id, nombre, abreviatura, color_hex, delegado, telefono, logo_url, activo) VALUES (?,?,?,?,?,?,?,?)",
                    [$torneo['id'], $nombre, $abreviatura, $colorHex, $delegado, $telefono, $logoUrl, $activo]
                );
                set_flash('success', 'Equipo creado correctamente.');
            }
            redirect('/admin/equipos/index.php');
        }

        $equipo = [
            'id' => $equipo['id'] ?? null,
            'nombre' => $nombre,
            'abreviatura' => $abreviatura,
            'color_hex' => $colorHex,
            'delegado' => $delegado,
            'telefono' => $telefono,
            'logo_url' => $logoUrl,
            'activo' => $activo,
        ];
    }
}

$pageTitle = $equipo ? 'Editar equipo' : 'Nuevo equipo';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<h1><?= $equipo ? '✏️ Editar equipo' : '➕ Nuevo equipo' ?></h1>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:600px;">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="nombre">Nombre del equipo</label>
            <input type="text" id="nombre" name="nombre" value="<?= h($equipo['nombre'] ?? '') ?>" required maxlength="100">
        </div>

        <div class="form-row">
            <div class="form-group" style="max-width:140px;">
                <label for="abreviatura">Abreviatura</label>
                <input type="text" id="abreviatura" name="abreviatura" value="<?= h($equipo['abreviatura'] ?? '') ?>" maxlength="4">
            </div>
            <div class="form-group" style="max-width:140px;">
                <label for="color_hex">Color</label>
                <input type="color" id="color_hex" name="color_hex" value="<?= h($equipo['color_hex'] ?? '#3B9EFF') ?>" style="height:42px;padding:4px;">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="delegado">Delegado</label>
                <input type="text" id="delegado" name="delegado" value="<?= h($equipo['delegado'] ?? '') ?>" maxlength="100">
            </div>
            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" value="<?= h($equipo['telefono'] ?? '') ?>" maxlength="20">
            </div>
        </div>

        <div class="form-group">
            <label for="logo">Logo del equipo</label>
            <?php if (!empty($equipo['logo_url'])): ?>
                <img src="<?= h($equipo['logo_url']) ?>" alt="Logo actual" style="width:56px;height:56px;object-fit:cover;border-radius:50%;margin-bottom:8px;">
            <?php endif; ?>
            <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>

        <div class="form-group checkbox-row">
            <input type="checkbox" id="activo" name="activo" value="1" <?= ($equipo['activo'] ?? 1) ? 'checked' : '' ?>>
            <label for="activo" style="margin-bottom:0;">Activo (participa en el torneo)</label>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/equipos/index.php">Cancelar</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
