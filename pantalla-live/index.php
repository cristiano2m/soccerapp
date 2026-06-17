<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = Database::getInstance();

$id = (int) ($_GET['id'] ?? 0);
$partido = $db->queryOne(
    "SELECT p.*, j.numero AS jornada_numero,
            el.nombre AS local_nombre, ev.nombre AS visita_nombre
     FROM partidos p
     JOIN jornadas j ON j.id = p.jornada_id
     JOIN equipos el ON el.id = p.equipo_local_id
     JOIN equipos ev ON ev.id = p.equipo_visita_id
     WHERE p.id = ?",
    [$id]
);

if (!$partido) {
    http_response_code(404);
    require __DIR__ . '/../views/errors/404.php';
    exit;
}

$torneo = obtener_torneo_activo();
$resultado = $db->queryOne("SELECT * FROM resultados WHERE partido_id = ?", [$partido['id']]);

$golesRaw = $db->query(
    "SELECT g.*, j.nombre AS jugador_nombre FROM goles g JOIN jugadores j ON j.id = g.jugador_id WHERE g.partido_id = ? ORDER BY g.minuto ASC, g.id ASC",
    [$partido['id']]
);
$goles = array_map(fn($g) => [
    'jugador' => $g['jugador_nombre'],
    'minuto' => $g['minuto'] ?? 0,
    'equipo' => (int) $g['equipo_id'] === (int) $partido['equipo_local_id'] ? 'local' : 'visita',
], $golesRaw);

$tarjetasRaw = $db->query(
    "SELECT t.*, j.nombre AS jugador_nombre FROM tarjetas t JOIN jugadores j ON j.id = t.jugador_id WHERE t.partido_id = ? ORDER BY t.minuto ASC, t.id ASC",
    [$partido['id']]
);
$tarjetas = array_map(fn($t) => [
    'jugador' => $t['jugador_nombre'],
    'minuto' => $t['minuto'] ?? 0,
    'tipo' => $t['tipo'],
], $tarjetasRaw);

$pageTitle = 'En vivo · ' . $partido['local_nombre'] . ' vs ' . $partido['visita_nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
    <style>
        body { background: var(--color-dark); padding: 24px; }
        .live-wrap { max-width: 720px; margin: 0 auto; }
        .live-wrap h1 { color: #fff; text-align: center; margin-bottom: 16px; font-size: 1.2rem; }
        .scoreboard .score { font-size: 5rem; }
    </style>
</head>
<body>
<div class="live-wrap">
    <h1><?= h($torneo['nombre'] ?? APP_NAME) ?> — Jornada <?= (int) $partido['jornada_numero'] ?></h1>
    <?php $live = true; require __DIR__ . '/../views/components/score-board.php'; ?>
</div>
</body>
</html>
