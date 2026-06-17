<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer']);

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) {
    redirect('/admin/torneo/index.php');
}

$id = (int) ($_GET['id'] ?? 0);
$partido = $db->queryOne(
    "SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visita_nombre, j.numero AS jornada_numero
     FROM partidos p
     JOIN equipos el ON el.id = p.equipo_local_id
     JOIN equipos ev ON ev.id = p.equipo_visita_id
     JOIN jornadas j ON j.id = p.jornada_id
     WHERE p.id = ? AND p.torneo_id = ?",
    [$id, $torneo['id']]
);

if (!$partido) {
    set_flash('error', 'Partido no encontrado.');
    redirect('/admin/calendario/index.php');
}

$errors = [];
$estados = ['programado', 'en_curso', 'finalizado', 'suspendido', 'wo'];
$arbitros = $db->query("SELECT id, nombre FROM usuarios WHERE rol = 'referee' AND activo = 1 ORDER BY nombre ASC");
$canchas = $db->query("SELECT * FROM canchas WHERE torneo_id = ? AND activo = 1 ORDER BY nombre ASC", [$torneo['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de seguridad inválido. Intenta de nuevo.';
    } else {
        $cancha = trim($_POST['cancha'] ?? '');
        $hora = trim($_POST['hora'] ?? '');
        $estado = $_POST['estado'] ?? 'programado';
        $arbitroId = $_POST['arbitro_id'] !== '' ? (int) $_POST['arbitro_id'] : null;
        $arbitroId2 = $_POST['arbitro_id2'] !== '' ? (int) $_POST['arbitro_id2'] : null;
        $arbitroId3 = $_POST['arbitro_id3'] !== '' ? (int) $_POST['arbitro_id3'] : null;

        if (!in_array($estado, $estados, true)) {
            $errors[] = 'Estado no válido.';
        }
        if ($hora !== '' && !preg_match('/^\d{2}:\d{2}$/', $hora)) {
            $errors[] = 'Formato de hora no válido.';
        }
        $seleccionados = array_filter([$arbitroId, $arbitroId2, $arbitroId3]);
        if (count($seleccionados) !== count(array_unique($seleccionados))) {
            $errors[] = 'No puedes asignar el mismo árbitro más de una vez en el mismo partido.';
        }

        if (empty($errors)) {
            $db->execute(
                "UPDATE partidos SET cancha=?, hora=?, estado=?, arbitro_id=?, arbitro_id2=?, arbitro_id3=? WHERE id=?",
                [$cancha !== '' ? $cancha : null, $hora !== '' ? $hora : null, $estado, $arbitroId, $arbitroId2, $arbitroId3, $partido['id']]
            );
            set_flash('success', 'Partido actualizado correctamente.');
            redirect('/admin/calendario/index.php');
        }

        $partido['cancha'] = $cancha;
        $partido['hora'] = $hora;
        $partido['estado'] = $estado;
        $partido['arbitro_id'] = $arbitroId;
        $partido['arbitro_id2'] = $arbitroId2;
        $partido['arbitro_id3'] = $arbitroId3;
    }
}

$pageTitle = 'Editar partido';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<h1>✏️ Editar partido</h1>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:720px;">
    <p class="text-muted" style="margin-bottom:16px;">Jornada <?= (int) $partido['jornada_numero'] ?> · <?= h($partido['local_nombre']) ?> vs <?= h($partido['visita_nombre']) ?></p>
    <form method="post">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-group">
                <label for="cancha">Cancha</label>
                <select id="cancha" name="cancha">
                    <option value="">-- Sin asignar --</option>
                    <?php
                    $canchaActual = $partido['cancha'] ?? '';
                    $canchaEnLista = false;
                    foreach ($canchas as $c) {
                        if ($c['nombre'] === $canchaActual) {
                            $canchaEnLista = true;
                        }
                    }
                    ?>
                    <?php if ($canchaActual !== '' && !$canchaEnLista): ?>
                        <option value="<?= h($canchaActual) ?>" selected><?= h($canchaActual) ?></option>
                    <?php endif; ?>
                    <?php foreach ($canchas as $c): ?>
                        <option value="<?= h($c['nombre']) ?>" <?= $canchaActual === $c['nombre'] ? 'selected' : '' ?>><?= h($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="max-width:140px;">
                <label for="hora">Hora</label>
                <input type="time" id="hora" name="hora" value="<?= h($partido['hora'] ? substr($partido['hora'], 0, 5) : '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="estado">Estado</label>
            <select id="estado" name="estado" required>
                <?php foreach ($estados as $e): ?>
                    <option value="<?= $e ?>" <?= $partido['estado'] === $e ? 'selected' : '' ?>><?= str_replace('_', ' ', $e) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Árbitros</label>
            <p class="text-muted" style="margin-bottom:8px;">Se pueden asignar hasta 3 árbitros por partido.</p>
            <div class="form-row">
                <div class="form-group">
                    <label for="arbitro_id">Árbitro principal</label>
                    <select id="arbitro_id" name="arbitro_id">
                        <option value="">-- Sin asignar --</option>
                        <?php foreach ($arbitros as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) ($partido['arbitro_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= h($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="arbitro_id2">Árbitro asistente 1</label>
                    <select id="arbitro_id2" name="arbitro_id2">
                        <option value="">-- Sin asignar --</option>
                        <?php foreach ($arbitros as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) ($partido['arbitro_id2'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= h($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="arbitro_id3">Árbitro asistente 2</label>
                    <select id="arbitro_id3" name="arbitro_id3">
                        <option value="">-- Sin asignar --</option>
                        <?php foreach ($arbitros as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= (int) ($partido['arbitro_id3'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= h($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <?php if ($partido['estado'] === 'finalizado'): ?>
        <div class="alert alert-info">Para registrar el marcador, goles y tarjetas de este partido, usa <a href="<?= BASE_URL ?>/admin/resultados/partido.php?id=<?= (int) $partido['id'] ?>">Resultados</a>.</div>
        <?php endif; ?>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/calendario/index.php">Cancelar</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
