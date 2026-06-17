<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin']);

$db = Database::getInstance();

$id = (int) ($_GET['id'] ?? 0);
$usuario = null;
if ($id > 0) {
    $usuario = $db->queryOne("SELECT id, nombre, email, rol, activo FROM usuarios WHERE id = ?", [$id]);
    if (!$usuario) {
        set_flash('error', 'Usuario no encontrado.');
        redirect('/admin/usuarios/index.php');
    }
}

$roles = ['super_admin', 'organizer', 'referee'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de seguridad inválido. Intenta de nuevo.';
    } else {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = $_POST['rol'] ?? 'referee';
        $activo = isset($_POST['activo']) ? 1 : 0;
        $password = $_POST['password'] ?? '';

        if ($nombre === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido.';
        }
        if (!in_array($rol, $roles, true)) {
            $errors[] = 'Rol no válido.';
        }
        if (!$usuario && $password === '') {
            $errors[] = 'La contraseña es obligatoria para un nuevo usuario.';
        }
        if ($password !== '' && strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($usuario && (int) $usuario['id'] === (int) current_user()['id'] && $rol !== 'super_admin') {
            $errors[] = 'No puedes cambiar tu propio rol de super_admin.';
        }

        if (empty($errors)) {
            $existente = $db->queryOne("SELECT id FROM usuarios WHERE email = ? AND id != ?", [$email, $usuario['id'] ?? 0]);
            if ($existente) {
                $errors[] = 'Ya existe otro usuario con ese email.';
            }
        }

        if (empty($errors)) {
            if ($usuario) {
                if ($password !== '') {
                    $db->execute(
                        "UPDATE usuarios SET nombre=?, email=?, rol=?, activo=?, password=? WHERE id=?",
                        [$nombre, $email, $rol, $activo, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $usuario['id']]
                    );
                } else {
                    $db->execute(
                        "UPDATE usuarios SET nombre=?, email=?, rol=?, activo=? WHERE id=?",
                        [$nombre, $email, $rol, $activo, $usuario['id']]
                    );
                }
                set_flash('success', 'Usuario actualizado correctamente.');
            } else {
                $db->insert(
                    "INSERT INTO usuarios (nombre, email, password, rol, activo) VALUES (?,?,?,?,?)",
                    [$nombre, $email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $rol, $activo]
                );
                set_flash('success', 'Usuario creado correctamente.');
            }
            redirect('/admin/usuarios/index.php');
        }

        $usuario = ['id' => $usuario['id'] ?? null, 'nombre' => $nombre, 'email' => $email, 'rol' => $rol, 'activo' => $activo];
    }
}

$pageTitle = $usuario ? 'Editar usuario' : 'Nuevo usuario';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<h1><?= $usuario ? '✏️ Editar usuario' : '➕ Nuevo usuario' ?></h1>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:520px;">
    <form method="post">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" value="<?= h($usuario['nombre'] ?? '') ?>" required maxlength="100">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= h($usuario['email'] ?? '') ?>" required maxlength="150">
        </div>

        <div class="form-group">
            <label for="password">Contraseña<?= $usuario ? ' (dejar en blanco para no cambiar)' : '' ?></label>
            <input type="password" id="password" name="password" minlength="8" <?= $usuario ? '' : 'required' ?>>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="rol">Rol</label>
                <select id="rol" name="rol" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r ?>" <?= ($usuario['rol'] ?? 'referee') === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group checkbox-row" style="align-self:center;">
                <input type="checkbox" id="activo" name="activo" value="1" <?= ($usuario['activo'] ?? 1) ? 'checked' : '' ?>>
                <label for="activo" style="margin-bottom:0;">Activo</label>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/usuarios/index.php">Cancelar</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
