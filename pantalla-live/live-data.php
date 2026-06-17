<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();

$id = (int) ($_GET['id'] ?? 0);
$partido = $db->queryOne(
    "SELECT p.*, el.nombre AS local_nombre, ev.nombre AS visita_nombre
     FROM partidos p
     JOIN equipos el ON el.id = p.equipo_local_id
     JOIN equipos ev ON ev.id = p.equipo_visita_id
     WHERE p.id = ?",
    [$id]
);

if (!$partido) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Partido no encontrado']);
    exit;
}

$resultado = $db->queryOne("SELECT * FROM resultados WHERE partido_id = ?", [$partido['id']]);

$golesRaw = $db->query(
    "SELECT g.*, j.nombre AS jugador_nombre FROM goles g JOIN jugadores j ON j.id = g.jugador_id WHERE g.partido_id = ? ORDER BY g.minuto ASC, g.id ASC",
    [$partido['id']]
);
$goles = array_map(fn($g) => [
    'jugador' => $g['jugador_nombre'],
    'minuto' => (int) ($g['minuto'] ?? 0),
    'equipo' => (int) $g['equipo_id'] === (int) $partido['equipo_local_id'] ? 'local' : 'visita',
], $golesRaw);

$tarjetasRaw = $db->query(
    "SELECT t.*, j.nombre AS jugador_nombre FROM tarjetas t JOIN jugadores j ON j.id = t.jugador_id WHERE t.partido_id = ? ORDER BY t.minuto ASC, t.id ASC",
    [$partido['id']]
);
$tarjetas = array_map(fn($t) => [
    'jugador' => $t['jugador_nombre'],
    'minuto' => (int) ($t['minuto'] ?? 0),
    'tipo' => $t['tipo'],
], $tarjetasRaw);

echo json_encode([
    'success' => true,
    'data' => [
        'estado' => $partido['estado'],
        'local' => ['nombre' => $partido['local_nombre'], 'goles' => (int) ($resultado['goles_local'] ?? 0)],
        'visita' => ['nombre' => $partido['visita_nombre'], 'goles' => (int) ($resultado['goles_visita'] ?? 0)],
        'goles' => $goles,
        'tarjetas' => $tarjetas,
    ],
], JSON_UNESCAPED_UNICODE);
