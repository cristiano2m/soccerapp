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

$equipos = $db->query("SELECT id, nombre FROM equipos WHERE torneo_id = ? ORDER BY nombre ASC", [$torneo['id']]);
$equipoIdSeleccionado = (int) ($_GET['equipo_id'] ?? $_POST['equipo_id'] ?? 0);

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token de seguridad inválido. Intenta de nuevo.');
        redirect('/admin/equipos/jugadores-fotos-importar.php');
    }

    $equipo = $db->queryOne("SELECT id, nombre FROM equipos WHERE id = ? AND torneo_id = ?", [$equipoIdSeleccionado, $torneo['id']]);
    if (!$equipo) {
        set_flash('error', 'Selecciona un equipo válido.');
        redirect('/admin/equipos/jugadores-fotos-importar.php');
    }

    if (empty($_FILES['archivo_zip']) || $_FILES['archivo_zip']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Debes seleccionar un archivo ZIP.');
        redirect('/admin/equipos/jugadores-fotos-importar.php?equipo_id=' . $equipo['id']);
    }

    $tmpZip = $_FILES['archivo_zip']['tmp_name'];
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) {
        $resultado = ['errores' => ['No se pudo abrir el archivo ZIP.'], 'asignadas' => 0, 'noEncontrados' => []];
    } elseif ($zip->numFiles > 300) {
        $zip->close();
        $resultado = ['errores' => ['El ZIP tiene demasiados archivos (máx. 300).'], 'asignadas' => 0, 'noEncontrados' => []];
    } else {
        $asignadas = 0;
        $errores = [];
        $noEncontrados = [];
        $maxBytes = 3 * 1024 * 1024;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $nombreEntrada = $stat['name'];
            if ($nombreEntrada === '' || str_ends_with($nombreEntrada, '/')) {
                continue; // carpeta
            }

            $base = basename($nombreEntrada);
            $numeroStr = pathinfo($base, PATHINFO_FILENAME);
            if ($base === '' || $base[0] === '.' || !ctype_digit($numeroStr)) {
                continue; // ignorar archivos que no siguen la convención numero.ext (ej. metadata de macOS)
            }
            $numero = (int) $numeroStr;

            $jugador = $db->queryOne("SELECT id, foto_url FROM jugadores WHERE equipo_id = ? AND numero = ?", [$equipo['id'], $numero]);
            if (!$jugador) {
                $noEncontrados[] = $base;
                continue;
            }

            $contenido = $zip->getFromIndex($i, $maxBytes);
            if ($contenido === false) {
                $errores[] = "$base: no se pudo leer dentro del ZIP.";
                continue;
            }
            if (strlen($contenido) >= $maxBytes) {
                $errores[] = "$base: el archivo es demasiado grande.";
                continue;
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'foto_');
            file_put_contents($tmpFile, $contenido);
            try {
                $url = guardar_imagen_validada($tmpFile, strlen($contenido), 'jugadores', $jugador['foto_url'], false);
                $db->execute("UPDATE jugadores SET foto_url = ? WHERE id = ?", [$url, $jugador['id']]);
                $asignadas++;
            } catch (Exception $e) {
                $errores[] = "$base: " . $e->getMessage();
            } finally {
                if (is_file($tmpFile)) {
                    @unlink($tmpFile);
                }
            }
        }
        $zip->close();

        $resultado = ['errores' => $errores, 'asignadas' => $asignadas, 'noEncontrados' => $noEncontrados];
        if ($asignadas > 0) {
            set_flash('success', "Fotos asignadas: $asignadas de {$equipo['nombre']}.");
        }
    }
}

$pageTitle = 'Importar fotos de jugadores';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1>🖼️ Importar fotos de jugadores (ZIP)</h1>
    <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/equipos/index.php">Volver a equipos</a>
</div>

<?php if ($resultado): ?>
    <?php if (!empty($resultado['errores'])): ?>
        <div class="alert alert-error">
            <strong>Hubo errores en algunas fotos:</strong>
            <ul style="margin:8px 0 0 20px;">
                <?php foreach ($resultado['errores'] as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($resultado['noEncontrados'])): ?>
        <div class="alert alert-error">
            <strong>No se encontró un jugador con ese número en el equipo para estos archivos:</strong>
            <?= h(implode(', ', $resultado['noEncontrados'])) ?>
        </div>
    <?php endif; ?>
    <?php if ($resultado['asignadas'] > 0): ?>
        <div class="alert alert-success"><?= (int) $resultado['asignadas'] ?> foto(s) asignada(s) correctamente.</div>
    <?php endif; ?>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <h3 class="section-title">Subir archivo ZIP</h3>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="equipo_id">Equipo</label>
                <select id="equipo_id" name="equipo_id" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($equipos as $eq): ?>
                        <option value="<?= $eq['id'] ?>" <?= $equipoIdSeleccionado === (int) $eq['id'] ? 'selected' : '' ?>><?= h($eq['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="archivo_zip">Archivo ZIP con las fotos</label>
                <input type="file" id="archivo_zip" name="archivo_zip" accept=".zip" required>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Importar fotos</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3 class="section-title">Formato esperado</h3>
        <p class="text-muted">El ZIP debe contener una imagen por jugador, nombrada con el <strong>número de camiseta</strong> del jugador dentro del equipo seleccionado:</p>
        <pre style="background:#f5f5f5; padding:10px; border-radius:6px; overflow-x:auto; font-size:0.85rem;">equipo.zip
├── 7.jpg
├── 10.png
└── 23.jpg</pre>
        <p class="text-muted">Formatos permitidos por imagen: JPG, PNG, WEBP o GIF. Máximo 2 MB por foto y 300 archivos por ZIP.</p>
        <p class="text-muted">Si el número no coincide con ningún jugador del equipo, esa foto se reporta como "no encontrada" y el resto se procesa igual.</p>
    </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
