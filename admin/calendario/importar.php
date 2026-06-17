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

$equipos = $db->query("SELECT id, nombre FROM equipos WHERE torneo_id = ?", [$torneo['id']]);

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token de seguridad inválido. Intenta de nuevo.');
        redirect('/admin/calendario/importar.php');
    }

    if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Debes seleccionar un archivo CSV.');
        redirect('/admin/calendario/importar.php');
    }

    $handle = fopen($_FILES['archivo']['tmp_name'], 'r');
    if ($handle === false) {
        set_flash('error', 'No se pudo leer el archivo.');
        redirect('/admin/calendario/importar.php');
    }

    // Mapa nombre de equipo (en minúsculas) -> id
    $equiposPorNombre = [];
    foreach ($equipos as $eq) {
        $equiposPorNombre[mb_strtolower($eq['nombre'])] = (int) $eq['id'];
    }

    // Jornadas existentes: numero -> id
    $jornadasExistentes = [];
    foreach ($db->query("SELECT id, numero FROM jornadas WHERE torneo_id = ?", [$torneo['id']]) as $j) {
        $jornadasExistentes[(int) $j['numero']] = (int) $j['id'];
    }

    $filas = [];
    $errores = [];
    $numFila = 0;
    $esEncabezado = true;
    $jornadasNuevas = []; // numero => fecha (o null)

    while (($datos = fgetcsv($handle)) !== false) {
        $numFila++;

        if ($esEncabezado) {
            $esEncabezado = false;
            continue; // primera línea = encabezado, se ignora
        }

        if (count($datos) === 1 && trim((string) $datos[0]) === '') {
            continue; // línea vacía
        }

        $jornadaNum = trim($datos[0] ?? '');
        $fecha = trim($datos[1] ?? '');
        $nombreLocal = trim($datos[2] ?? '');
        $nombreVisita = trim($datos[3] ?? '');
        $cancha = trim($datos[4] ?? '');
        $hora = trim($datos[5] ?? '');

        if (!ctype_digit($jornadaNum) || (int) $jornadaNum < 1 || (int) $jornadaNum > 255) {
            $errores[] = "Fila $numFila: la jornada \"$jornadaNum\" debe ser un número entre 1 y 255.";
            continue;
        }
        $jornadaNum = (int) $jornadaNum;

        if ($fecha !== '') {
            $partes = explode('-', $fecha);
            if (count($partes) !== 3 || !checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0])) {
                $errores[] = "Fila $numFila: fecha \"$fecha\" inválida (usa formato AAAA-MM-DD).";
                continue;
            }
        }

        if ($nombreLocal === '' || $nombreVisita === '') {
            $errores[] = "Fila $numFila: equipo local y equipo visitante son obligatorios.";
            continue;
        }

        $localId = $equiposPorNombre[mb_strtolower($nombreLocal)] ?? null;
        $visitaId = $equiposPorNombre[mb_strtolower($nombreVisita)] ?? null;

        if ($localId === null) {
            $errores[] = "Fila $numFila: no existe un equipo llamado \"$nombreLocal\".";
            continue;
        }
        if ($visitaId === null) {
            $errores[] = "Fila $numFila: no existe un equipo llamado \"$nombreVisita\".";
            continue;
        }
        if ($localId === $visitaId) {
            $errores[] = "Fila $numFila: el equipo local y el visitante no pueden ser el mismo (\"$nombreLocal\").";
            continue;
        }

        if ($hora !== '' && !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $hora)) {
            $errores[] = "Fila $numFila: hora \"$hora\" inválida (usa formato HH:MM).";
            continue;
        }

        if (!isset($jornadasExistentes[$jornadaNum]) && !isset($jornadasNuevas[$jornadaNum])) {
            $jornadasNuevas[$jornadaNum] = $fecha !== '' ? $fecha : null;
        } elseif (!isset($jornadasExistentes[$jornadaNum]) && $jornadasNuevas[$jornadaNum] === null && $fecha !== '') {
            $jornadasNuevas[$jornadaNum] = $fecha;
        }

        $filas[] = [
            'jornadaNum' => $jornadaNum,
            'localId' => $localId,
            'visitaId' => $visitaId,
            'cancha' => $cancha,
            'hora' => $hora,
        ];
    }
    fclose($handle);

    if (empty($filas) && empty($errores)) {
        $errores[] = 'El archivo está vacío o no contiene filas de datos.';
    }

    if (!empty($errores)) {
        $resultado = ['errores' => $errores, 'creados' => 0, 'jornadas' => 0];
    } else {
        $creados = 0;
        try {
            $db->beginTransaction();

            foreach ($jornadasNuevas as $numero => $fecha) {
                $id = $db->insert(
                    "INSERT INTO jornadas (torneo_id, numero, fecha, nombre) VALUES (?,?,?,?)",
                    [$torneo['id'], $numero, $fecha, "Jornada $numero"]
                );
                $jornadasExistentes[$numero] = $id;
            }

            foreach ($filas as $f) {
                $db->insert(
                    "INSERT INTO partidos (jornada_id, torneo_id, equipo_local_id, equipo_visita_id, cancha, hora, estado) VALUES (?,?,?,?,?,?,'programado')",
                    [
                        $jornadasExistentes[$f['jornadaNum']],
                        $torneo['id'],
                        $f['localId'],
                        $f['visitaId'],
                        $f['cancha'] !== '' ? $f['cancha'] : null,
                        $f['hora'] !== '' ? $f['hora'] : null,
                    ]
                );
                $creados++;
            }

            $db->commit();
            set_flash('success', "Importación completa: $creados partido(s) creado(s) en " . count($jornadasNuevas) . " jornada(s) nueva(s).");
            redirect('/admin/calendario/index.php');
        } catch (Exception $e) {
            $db->rollBack();
            $resultado = ['errores' => ['Error al guardar en la base de datos: ' . $e->getMessage()], 'creados' => 0, 'jornadas' => 0];
        }
    }
}

$pageTitle = 'Importar calendario';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1>📥 Importar calendario (CSV)</h1>
    <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/calendario/index.php">Volver a calendario</a>
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
            <p class="text-muted">Aún no hay equipos registrados. Los nombres deben coincidir exactamente con los registrados en <a href="<?= BASE_URL ?>/admin/equipos/index.php">Equipos</a>.</p>
        <?php else: ?>
            <p class="text-muted">Los nombres de equipo_local / equipo_visita deben coincidir (sin importar mayúsculas) con:</p>
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
                    <tr><td>1</td><td>jornada</td><td>Sí</td><td>Número de jornada (1-255)</td></tr>
                    <tr><td>2</td><td>fecha</td><td>No</td><td>AAAA-MM-DD (solo se usa si la jornada es nueva)</td></tr>
                    <tr><td>3</td><td>equipo_local</td><td>Sí</td><td>Debe existir en Equipos</td></tr>
                    <tr><td>4</td><td>equipo_visita</td><td>Sí</td><td>Debe existir en Equipos</td></tr>
                    <tr><td>5</td><td>cancha</td><td>No</td><td></td></tr>
                    <tr><td>6</td><td>hora</td><td>No</td><td>HH:MM</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-muted" style="margin-top:12px;">Ejemplo:</p>
        <pre style="background:#f5f5f5; padding:10px; border-radius:6px; overflow-x:auto; font-size:0.85rem;">jornada,fecha,equipo_local,equipo_visita,cancha,hora
1,2026-07-04,Tigres FC,Cóndores FC,Cancha 1,15:00
1,2026-07-04,Águilas FC,Panteras FC,Cancha 2,17:00</pre>
        <p class="text-muted">Si la jornada ya existe, los partidos se agregan a ella (su fecha no se modifica). Cada fila crea un partido nuevo: no reemplaza al calendario actual.</p>
    </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
