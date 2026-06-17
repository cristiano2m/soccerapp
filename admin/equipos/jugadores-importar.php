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
$posiciones = ['Portero', 'Defensa', 'Mediocampista', 'Delantero'];

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token de seguridad inválido. Intenta de nuevo.');
        redirect('/admin/equipos/jugadores-importar.php');
    }

    if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Debes seleccionar un archivo CSV.');
        redirect('/admin/equipos/jugadores-importar.php');
    }

    $handle = fopen($_FILES['archivo']['tmp_name'], 'r');
    if ($handle === false) {
        set_flash('error', 'No se pudo leer el archivo.');
        redirect('/admin/equipos/jugadores-importar.php');
    }

    // Mapa nombre de equipo (en minúsculas) -> id
    $equiposPorNombre = [];
    foreach ($equipos as $eq) {
        $equiposPorNombre[mb_strtolower($eq['nombre'])] = (int) $eq['id'];
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

        $nombreEquipo = trim($datos[0] ?? '');
        $nombre = trim($datos[1] ?? '');
        $numero = trim($datos[2] ?? '');
        $posicion = trim($datos[3] ?? '');
        $cedula = trim($datos[4] ?? '');
        $activoRaw = trim($datos[5] ?? '');
        $activo = $activoRaw === '0' ? 0 : 1;

        $equipoId = $equiposPorNombre[mb_strtolower($nombreEquipo)] ?? null;
        if ($equipoId === null) {
            $errores[] = "Fila $numFila: no existe un equipo llamado \"$nombreEquipo\".";
            continue;
        }
        if ($nombre === '') {
            $errores[] = "Fila $numFila: el nombre del jugador es obligatorio.";
            continue;
        }
        if (!ctype_digit($numero) || (int) $numero < 1 || (int) $numero > 99) {
            $errores[] = "Fila $numFila: el número \"$numero\" debe ser un valor entre 1 y 99.";
            continue;
        }
        if (!in_array($posicion, $posiciones, true)) {
            $errores[] = "Fila $numFila: posición \"$posicion\" inválida (usa Portero, Defensa, Mediocampista o Delantero).";
            continue;
        }

        $filas[] = [
            'equipoId' => $equipoId,
            'nombre' => $nombre,
            'numero' => (int) $numero,
            'posicion' => $posicion,
            'cedula' => $cedula,
            'activo' => $activo,
        ];
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
                    "SELECT id FROM jugadores WHERE equipo_id = ? AND numero = ?",
                    [$f['equipoId'], $f['numero']]
                );
                if ($existente) {
                    $db->execute(
                        "UPDATE jugadores SET nombre=?, posicion=?, cedula=?, activo=? WHERE id=?",
                        [$f['nombre'], $f['posicion'], $f['cedula'], $f['activo'], $existente['id']]
                    );
                    $actualizados++;
                } else {
                    $db->insert(
                        "INSERT INTO jugadores (equipo_id, nombre, numero, posicion, cedula, activo) VALUES (?,?,?,?,?,?)",
                        [$f['equipoId'], $f['nombre'], $f['numero'], $f['posicion'], $f['cedula'], $f['activo']]
                    );
                    $creados++;
                }
            }
            $db->commit();
            set_flash('success', "Importación completa: $creados jugador(es) creado(s), $actualizados actualizado(s).");
            redirect('/admin/equipos/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            $resultado = ['errores' => ['Error al guardar en la base de datos: ' . $e->getMessage()], 'creados' => 0, 'actualizados' => 0];
        }
    }
}

$pageTitle = 'Importar jugadores';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1>📥 Importar jugadores (CSV)</h1>
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

        <h3 class="section-title" style="margin-top:24px;">Equipos disponibles</h3>
        <?php if (empty($equipos)): ?>
            <p class="text-muted">Aún no hay equipos registrados. Primero crea los equipos en <a href="<?= BASE_URL ?>/admin/equipos/index.php">Equipos</a>.</p>
        <?php else: ?>
            <p class="text-muted">La columna equipo debe coincidir (sin importar mayúsculas) con:</p>
            <ul style="margin:8px 0 0 20px; columns:2;">
                <?php foreach ($equipos as $eq): ?>
                    <li><?= h($eq['nombre']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
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
                    <tr><td>1</td><td>equipo</td><td>Sí</td><td>Debe existir en Equipos</td></tr>
                    <tr><td>2</td><td>nombre</td><td>Sí</td><td>Nombre del jugador</td></tr>
                    <tr><td>3</td><td>numero</td><td>Sí</td><td>Entre 1 y 99</td></tr>
                    <tr><td>4</td><td>posicion</td><td>Sí</td><td>Portero, Defensa, Mediocampista o Delantero</td></tr>
                    <tr><td>5</td><td>cedula</td><td>No</td><td>ID del jugador</td></tr>
                    <tr><td>6</td><td>activo</td><td>No</td><td>1 o 0 (por defecto 1)</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-muted" style="margin-top:12px;">Ejemplo:</p>
        <pre style="background:#f5f5f5; padding:10px; border-radius:6px; overflow-x:auto; font-size:0.85rem;">equipo,nombre,numero,posicion,cedula,activo
Tigres FC,Juan Pérez,7,Delantero,0102030405,1
Cóndores FC,Pedro Gómez,3,Defensa,,1</pre>
        <p class="text-muted">Si ya existe un jugador con ese número en ese equipo, se actualizan sus datos en lugar de crear uno duplicado.</p>
    </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
