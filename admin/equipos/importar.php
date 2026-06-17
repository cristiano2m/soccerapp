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

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token de seguridad inválido. Intenta de nuevo.');
        redirect('/admin/equipos/importar.php');
    }

    if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Debes seleccionar un archivo CSV.');
        redirect('/admin/equipos/importar.php');
    }

    $handle = fopen($_FILES['archivo']['tmp_name'], 'r');
    if ($handle === false) {
        set_flash('error', 'No se pudo leer el archivo.');
        redirect('/admin/equipos/importar.php');
    }

    $filas = [];
    $errores = [];
    $numFila = 0;
    $esEncabezado = true;

    while (($datos = fgetcsv($handle)) !== false) {
        $numFila++;

        if ($esEncabezado) {
            $esEncabezado = false;
            continue; // primera línea = encabezado, se ignora
        }

        if (count($datos) === 1 && trim((string) $datos[0]) === '') {
            continue; // línea vacía
        }

        $nombre = trim($datos[0] ?? '');
        $abreviatura = mb_strtoupper(trim($datos[1] ?? ''));
        $colorHex = trim($datos[2] ?? '');
        $colorHex = $colorHex === '' ? '#3B9EFF' : $colorHex;
        $delegado = trim($datos[3] ?? '');
        $telefono = trim($datos[4] ?? '');
        $activoRaw = trim($datos[5] ?? '');
        $activo = $activoRaw === '0' ? 0 : 1;

        if ($nombre === '') {
            $errores[] = "Fila $numFila: el nombre del equipo es obligatorio.";
            continue;
        }
        if (mb_strlen($abreviatura) > 4) {
            $errores[] = "Fila $numFila: la abreviatura \"$abreviatura\" supera los 4 caracteres.";
            continue;
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colorHex)) {
            $errores[] = "Fila $numFila: color \"$colorHex\" inválido (usa formato #RRGGBB).";
            continue;
        }

        $filas[] = compact('nombre', 'abreviatura', 'colorHex', 'delegado', 'telefono', 'activo');
    }
    fclose($handle);

    if (empty($filas) && empty($errores)) {
        $errores[] = 'El archivo está vacío o no contiene filas de datos.';
    }

    if (!empty($errores)) {
        $resultado = ['errores' => $errores, 'creados' => 0, 'actualizados' => 0];
    } else {
        $creados = 0;
        $actualizados = 0;
        try {
            $db->beginTransaction();
            foreach ($filas as $f) {
                $existente = $db->queryOne(
                    "SELECT id FROM equipos WHERE torneo_id = ? AND nombre = ?",
                    [$torneo['id'], $f['nombre']]
                );
                if ($existente) {
                    $db->execute(
                        "UPDATE equipos SET abreviatura=?, color_hex=?, delegado=?, telefono=?, activo=? WHERE id=?",
                        [$f['abreviatura'], $f['colorHex'], $f['delegado'], $f['telefono'], $f['activo'], $existente['id']]
                    );
                    $actualizados++;
                } else {
                    $db->insert(
                        "INSERT INTO equipos (torneo_id, nombre, abreviatura, color_hex, delegado, telefono, activo) VALUES (?,?,?,?,?,?,?)",
                        [$torneo['id'], $f['nombre'], $f['abreviatura'], $f['colorHex'], $f['delegado'], $f['telefono'], $f['activo']]
                    );
                    $creados++;
                }
            }
            $db->commit();
            set_flash('success', "Importación completa: $creados equipo(s) creado(s), $actualizados actualizado(s).");
            redirect('/admin/equipos/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            $resultado = ['errores' => ['Error al guardar en la base de datos: ' . $e->getMessage()], 'creados' => 0, 'actualizados' => 0];
        }
    }
}

$pageTitle = 'Importar equipos';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1>📥 Importar equipos (CSV)</h1>
    <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/equipos/index.php">Volver a equipos</a>
</div>

<?php if ($resultado && !empty($resultado['errores'])): ?>
    <div class="alert alert-error">
        <strong>No se importó nada.</strong> Corrige los siguientes errores y vuelve a subir el archivo:
        <ul style="margin:8px 0 0 20px;">
            <?php foreach ($resultado['errores'] as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <h3 class="section-title">Subir archivo CSV</h3>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="archivo">Archivo CSV</label>
                <input type="file" id="archivo" name="archivo" accept=".csv,text/csv" required>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">Importar</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3 class="section-title">Formato esperado</h3>
        <p class="text-muted">La primera línea es el encabezado (se ignora). Columnas, en este orden:</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>#</th><th>Columna</th><th>Obligatorio</th><th>Notas</th></tr>
                </thead>
                <tbody>
                    <tr><td>1</td><td>nombre</td><td>Sí</td><td>Nombre del equipo</td></tr>
                    <tr><td>2</td><td>abreviatura</td><td>No</td><td>Máx. 4 caracteres</td></tr>
                    <tr><td>3</td><td>color_hex</td><td>No</td><td>Formato #RRGGBB (por defecto #3B9EFF)</td></tr>
                    <tr><td>4</td><td>delegado</td><td>No</td><td></td></tr>
                    <tr><td>5</td><td>telefono</td><td>No</td><td></td></tr>
                    <tr><td>6</td><td>activo</td><td>No</td><td>1 o 0 (por defecto 1)</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-muted" style="margin-top:12px;">Ejemplo:</p>
        <pre style="background:#f5f5f5; padding:10px; border-radius:6px; overflow-x:auto; font-size:0.85rem;">nombre,abreviatura,color_hex,delegado,telefono,activo
Tigres FC,TIG,#FF6B00,Carlos Pérez,0991234567,1
Cóndores FC,CON,#1E90FF,,,1</pre>
        <p class="text-muted">Si ya existe un equipo con el mismo nombre en el torneo, se actualizan sus datos en lugar de crear uno duplicado.</p>
    </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
