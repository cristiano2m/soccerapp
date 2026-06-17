<?php
// ══════════════════════════════════════════════
// SoccerAPP — API REST v1
// Base: /torneo/api/v1/{recurso}
// Auth: Authorization: Bearer <token>  |  ?token=<token>
// ══════════════════════════════════════════════

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/middleware.php';

// ── Cabeceras ────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Cache-Control: no-cache, no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error(405, 'Método no permitido. Solo GET.');
}

// ── Autenticar ───────────────────────────────────────────────────────────────
api_authenticate();

// ── Parsear ruta ─────────────────────────────────────────────────────────────
// URI: /torneo/api/v1/torneos/3/posiciones
$uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/v1'; // /torneo/api/v1
$path    = ltrim(substr($uri, strlen($base)), '/');
$segs    = $path !== '' ? explode('/', $path) : [];

$resource = $segs[0] ?? '';
$id       = isset($segs[1]) && is_numeric($segs[1]) ? (int) $segs[1] : null;
$sub      = $segs[2] ?? null;

$db = Database::getInstance();

// ── Router ───────────────────────────────────────────────────────────────────
switch ($resource) {

    // ── /torneos ─────────────────────────────────────────────────────────────
    case 'torneos':
        if ($id === null) {
            // GET /api/v1/torneos
            $torneos = $db->query(
                "SELECT t.*,
                    (SELECT COUNT(*) FROM equipos e WHERE e.torneo_id = t.id AND e.activo = 1) AS total_equipos,
                    (SELECT COUNT(*) FROM jornadas j WHERE j.torneo_id = t.id) AS total_jornadas,
                    (SELECT COUNT(*) FROM partidos p WHERE p.torneo_id = t.id AND p.estado = 'finalizado') AS partidos_jugados
                 FROM torneos t
                 ORDER BY t.id DESC"
            );
            api_json($torneos, ['total' => count($torneos)]);
        }

        $torneo = api_require_torneo($db, $id);

        switch ($sub) {

            case null:
                // GET /api/v1/torneos/{id}
                $torneo['total_equipos']  = (int)($db->queryOne("SELECT COUNT(*) c FROM equipos  WHERE torneo_id=? AND activo=1", [$id])['c'] ?? 0);
                $torneo['total_jornadas'] = (int)($db->queryOne("SELECT COUNT(*) c FROM jornadas WHERE torneo_id=?", [$id])['c'] ?? 0);
                $torneo['partidos_jugados'] = (int)($db->queryOne("SELECT COUNT(*) c FROM partidos WHERE torneo_id=? AND estado='finalizado'", [$id])['c'] ?? 0);
                $torneo['url_publica'] = url_publica_torneo($id);
                api_json($torneo);

            case 'posiciones':
                // GET /api/v1/torneos/{id}/posiciones
                $pos = calcular_posiciones($id);
                api_json($pos, ['torneo_id' => $id, 'total' => count($pos)]);

            case 'goleadores':
                // GET /api/v1/torneos/{id}/goleadores
                $limit = min((int) ($_GET['limit'] ?? 10), 50);
                $goleadores = obtener_goleadores($id, $limit);
                api_json($goleadores, ['torneo_id' => $id, 'total' => count($goleadores)]);

            case 'equipos':
                // GET /api/v1/torneos/{id}/equipos
                $equipos = $db->query(
                    "SELECT e.*,
                        (SELECT COUNT(*) FROM jugadores j WHERE j.equipo_id = e.id AND j.activo = 1) AS total_jugadores
                     FROM equipos e WHERE e.torneo_id = ? AND e.activo = 1 ORDER BY e.nombre ASC",
                    [$id]
                );
                api_json($equipos, ['torneo_id' => $id, 'total' => count($equipos)]);

            case 'jornadas':
                // GET /api/v1/torneos/{id}/jornadas
                $jornadas = $db->query(
                    "SELECT * FROM jornadas WHERE torneo_id = ? ORDER BY numero ASC",
                    [$id]
                );
                foreach ($jornadas as &$j) {
                    $j['partidos'] = $db->query(
                        "SELECT p.*,
                                el.nombre AS local_nombre, el.abreviatura AS local_abrev, el.logo_url AS local_logo,
                                ev.nombre AS visita_nombre, ev.abreviatura AS visita_abrev, ev.logo_url AS visita_logo,
                                r.goles_local, r.goles_visita, r.wo_local, r.wo_visita
                         FROM partidos p
                         JOIN equipos el ON el.id = p.equipo_local_id
                         JOIN equipos ev ON ev.id = p.equipo_visita_id
                         LEFT JOIN resultados r ON r.partido_id = p.id
                         WHERE p.jornada_id = ?
                         ORDER BY p.hora ASC, p.id ASC",
                        [$j['id']]
                    );
                }
                unset($j);
                api_json($jornadas, ['torneo_id' => $id, 'total' => count($jornadas)]);

            case 'partidos':
                // GET /api/v1/torneos/{id}/partidos[?estado=en_curso]
                $where  = "p.torneo_id = ?";
                $params = [$id];
                if (!empty($_GET['estado'])) {
                    $where  .= " AND p.estado = ?";
                    $params[] = $_GET['estado'];
                }
                if (!empty($_GET['jornada'])) {
                    $where  .= " AND j.numero = ?";
                    $params[] = (int) $_GET['jornada'];
                }
                $partidos = $db->query(
                    "SELECT p.*,
                            el.nombre AS local_nombre, el.abreviatura AS local_abrev, el.logo_url AS local_logo,
                            ev.nombre AS visita_nombre, ev.abreviatura AS visita_abrev, ev.logo_url AS visita_logo,
                            r.goles_local, r.goles_visita, r.wo_local, r.wo_visita,
                            j.numero AS jornada_numero
                     FROM partidos p
                     JOIN equipos el ON el.id = p.equipo_local_id
                     JOIN equipos ev ON ev.id = p.equipo_visita_id
                     LEFT JOIN resultados r ON r.partido_id = p.id
                     LEFT JOIN jornadas   j ON j.id = p.jornada_id
                     WHERE {$where}
                     ORDER BY p.hora ASC, p.id ASC",
                    $params
                );
                api_json($partidos, ['torneo_id' => $id, 'total' => count($partidos)]);

            case 'patrocinadores':
                // GET /api/v1/torneos/{id}/patrocinadores
                $sponsors = $db->query(
                    "SELECT * FROM patrocinadores WHERE torneo_id = ? AND activo = 1 ORDER BY orden ASC, id ASC",
                    [$id]
                );
                api_json($sponsors, ['torneo_id' => $id]);

            default:
                api_error(404, "Sub-recurso '{$sub}' no existe en /torneos.");
        }
        break;

    // ── /partidos ────────────────────────────────────────────────────────────
    case 'partidos':
        if ($id === null) {
            api_error(400, 'Se requiere el ID del partido: /api/v1/partidos/{id}');
        }

        $partido = api_require_partido($db, $id);

        if ($sub === 'live' || $sub === null) {
            $resultado = $db->queryOne("SELECT * FROM resultados WHERE partido_id = ?", [$id]);

            $goles = $db->query(
                "SELECT g.minuto, g.tipo,
                        j.nombre AS jugador,
                        CASE WHEN g.equipo_id = ? THEN 'local' ELSE 'visita' END AS equipo
                 FROM goles g
                 JOIN jugadores j ON j.id = g.jugador_id
                 WHERE g.partido_id = ?
                 ORDER BY g.minuto ASC, g.id ASC",
                [$partido['equipo_local_id'], $id]
            );

            $tarjetas = $db->query(
                "SELECT t.minuto, t.tipo,
                        j.nombre AS jugador,
                        CASE WHEN j.equipo_id = ? THEN 'local' ELSE 'visita' END AS equipo
                 FROM tarjetas t
                 JOIN jugadores j ON j.id = t.jugador_id
                 WHERE t.partido_id = ?
                 ORDER BY t.minuto ASC, t.id ASC",
                [$partido['equipo_local_id'], $id]
            );

            $data = [
                'id'      => (int) $partido['id'],
                'estado'  => $partido['estado'],
                'hora'    => $partido['hora'],
                'cancha'  => $partido['cancha'],
                'jornada' => (int) $partido['jornada_numero'],
                'local'   => [
                    'nombre'   => $partido['local_nombre'],
                    'abrev'    => $partido['local_abrev'],
                    'logo_url' => $partido['local_logo'],
                    'goles'    => (int) ($resultado['goles_local'] ?? 0),
                    'wo'       => (bool) ($resultado['wo_local'] ?? false),
                ],
                'visita'  => [
                    'nombre'   => $partido['visita_nombre'],
                    'abrev'    => $partido['visita_abrev'],
                    'logo_url' => $partido['visita_logo'],
                    'goles'    => (int) ($resultado['goles_visita'] ?? 0),
                    'wo'       => (bool) ($resultado['wo_visita'] ?? false),
                ],
                'goles'    => $goles,
                'tarjetas' => $tarjetas,
            ];
            api_json($data, ['live' => $partido['estado'] === 'en_curso']);
        }

        api_error(404, "Sub-recurso '{$sub}' no existe en /partidos.");
        break;

    // ── / (raíz) ─────────────────────────────────────────────────────────────
    case '':
        api_json([
            'name'    => 'SoccerAPP API',
            'version' => 'v1',
            'endpoints' => [
                'GET /api/v1/torneos'                        => 'Listar todos los torneos',
                'GET /api/v1/torneos/{id}'                   => 'Detalle de un torneo',
                'GET /api/v1/torneos/{id}/posiciones'        => 'Tabla de posiciones',
                'GET /api/v1/torneos/{id}/goleadores'        => 'Top goleadores (?limit=10)',
                'GET /api/v1/torneos/{id}/equipos'           => 'Equipos del torneo',
                'GET /api/v1/torneos/{id}/jornadas'          => 'Jornadas con partidos',
                'GET /api/v1/torneos/{id}/partidos'          => 'Partidos (?estado=&jornada=)',
                'GET /api/v1/torneos/{id}/patrocinadores'    => 'Patrocinadores',
                'GET /api/v1/partidos/{id}'                  => 'Detalle de partido',
                'GET /api/v1/partidos/{id}/live'             => 'Marcador en tiempo real',
            ],
        ]);
        break;

    default:
        api_error(404, "Recurso '{$resource}' no encontrado. Consulta GET /api/v1/ para ver los endpoints disponibles.");
}
