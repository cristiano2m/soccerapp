<?php
// AJAX: Generar caption con Claude AI para una jornada
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$db         = Database::getInstance();
$jornadaId  = (int) ($_POST['jornada_id'] ?? 0);
$tipo       = $_POST['tipo'] ?? 'proxima_fecha'; // proxima_fecha | resultados | posiciones

if (!$jornadaId) {
    echo json_encode(['error' => 'jornada_id requerido']);
    exit;
}

// ── Obtener API key ──────────────────────────────────────────────────────────
$claudeKey = $db->queryOne("SELECT `value` FROM app_settings WHERE `key` = 'claude_api_key'")['value'] ?? CLAUDE_API_KEY;

if (empty($claudeKey)) {
    echo json_encode(['error' => 'Configura tu clave de Claude en Ajustes → Configuración.']);
    exit;
}

// ── Recopilar datos de la jornada ───────────────────────────────────────────
$jornada = $db->queryOne(
    "SELECT j.*, t.nombre AS torneo_nombre, t.categoria, t.anio
     FROM jornadas j JOIN torneos t ON t.id = j.torneo_id
     WHERE j.id = ?",
    [$jornadaId]
);

if (!$jornada) {
    echo json_encode(['error' => 'Jornada no encontrada']);
    exit;
}

$partidos = $db->query(
    "SELECT p.*,
            el.nombre AS local_nombre,
            ev.nombre AS visita_nombre,
            r.goles_local, r.goles_visita
     FROM partidos p
     JOIN equipos el ON el.id = p.equipo_local_id
     JOIN equipos ev ON ev.id = p.equipo_visita_id
     LEFT JOIN resultados r ON r.partido_id = p.id
     WHERE p.jornada_id = ?
     ORDER BY p.hora ASC, p.id ASC",
    [$jornadaId]
);

// ── Construir contexto para Claude ──────────────────────────────────────────
$torneoInfo = "{$jornada['torneo_nombre']} {$jornada['categoria']} {$jornada['anio']}";
$partidosTexto = '';
foreach ($partidos as $p) {
    if ($tipo === 'resultados' && isset($p['goles_local'])) {
        $partidosTexto .= "• {$p['local_nombre']} {$p['goles_local']} - {$p['goles_visita']} {$p['visita_nombre']}\n";
    } else {
        $hora = $p['hora'] ? substr($p['hora'], 0, 5) : 'Por confirmar';
        $cancha = $p['cancha'] ?: 'Por confirmar';
        $partidosTexto .= "• {$p['local_nombre']} vs {$p['visita_nombre']} — {$hora} | Cancha: {$cancha}\n";
    }
}

$prompts = [
    'proxima_fecha' => "Eres el community manager de un torneo de fútbol. Genera un post atractivo para redes sociales (Instagram/Facebook) anunciando los partidos de la JORNADA {$jornada['numero']} del torneo {$torneoInfo}.\n\nPartidos:\n{$partidosTexto}\nEl post debe ser emocionante, usar emojis de fútbol, llamar a la afición a asistir. Máximo 200 palabras. Solo el texto del post, sin explicaciones adicionales.",

    'resultados' => "Eres el community manager de un torneo de fútbol. Genera un post para redes sociales (Instagram/Facebook) con los RESULTADOS de la JORNADA {$jornada['numero']} del torneo {$torneoInfo}.\n\nResultados:\n{$partidosTexto}\nEl post debe celebrar los ganadores, mencionar momentos destacados y animar para la siguiente jornada. Usa emojis. Máximo 200 palabras. Solo el texto del post.",

    'posiciones' => "Eres el community manager de un torneo de fútbol. Genera un post motivador para redes sociales recordando a los seguidores que pueden ver la tabla de posiciones actualizada del torneo {$torneoInfo}, jornada {$jornada['numero']} completada. Invítalos a seguir el campeonato. Usa emojis. Máximo 150 palabras. Solo el texto del post.",
];

$userPrompt = $prompts[$tipo] ?? $prompts['proxima_fecha'];

// ── Llamar a Claude API ──────────────────────────────────────────────────────
$payload = json_encode([
    'model'      => CLAUDE_MODEL,
    'max_tokens' => 512,
    'messages'   => [
        ['role' => 'user', 'content' => $userPrompt],
    ],
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $claudeKey,
        'anthropic-version: 2023-06-01',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo json_encode(['error' => 'Error de conexión con Claude: ' . $curlErr]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200 || empty($data['content'][0]['text'])) {
    $msg = $data['error']['message'] ?? 'Error desconocido de la API';
    echo json_encode(['error' => "Claude API ({$httpCode}): {$msg}"]);
    exit;
}

$caption = trim($data['content'][0]['text']);

// ── Guardar en imagenes_sociales ─────────────────────────────────────────────
$user = current_user();
$db->insert(
    "INSERT INTO imagenes_sociales (tipo, torneo_id, jornada_id, prompt_ia, generado_por)
     VALUES (?, ?, ?, ?, ?)",
    [$tipo, $jornada['torneo_id'], $jornadaId, $caption, $user['id']]
);

echo json_encode(['success' => true, 'caption' => $caption]);
