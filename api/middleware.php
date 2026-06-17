<?php
// ══════════════════════════════════════════════
// API — Helpers de respuesta y autenticación
// ══════════════════════════════════════════════

function api_json(mixed $data, array $meta = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'success'   => true,
        'data'      => $data,
        'meta'      => array_merge(['timestamp' => date('c')], $meta),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function api_error(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error'   => $message,
        'code'    => $status,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function api_authenticate(): array
{
    $db    = Database::getInstance();
    $token = null;

    // 1. Header: Authorization: Bearer <token>
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(\S+)$/i', trim($auth), $m)) {
        $token = $m[1];
    }

    // 2. Fallback: ?token=... (útil para WordPress shortcodes)
    if (!$token && !empty($_GET['token'])) {
        $token = trim($_GET['token']);
    }

    if (!$token) {
        api_error(401, 'Se requiere token de acceso. Usa el header Authorization: Bearer <token> o el parámetro ?token=');
    }

    $row = $db->queryOne(
        "SELECT * FROM api_tokens WHERE token = ? AND activo = 1",
        [$token]
    );

    if (!$row) {
        api_error(401, 'Token inválido o inactivo.');
    }

    // Actualizar last_used sin frenar la respuesta
    $db->execute("UPDATE api_tokens SET last_used = NOW() WHERE id = ?", [$row['id']]);

    return $row;
}

function api_require_torneo(Database $db, int $id): array
{
    $torneo = $db->queryOne("SELECT * FROM torneos WHERE id = ?", [$id]);
    if (!$torneo) {
        api_error(404, "Torneo #{$id} no encontrado.");
    }
    return $torneo;
}

function api_require_partido(Database $db, int $id): array
{
    $partido = $db->queryOne(
        "SELECT p.*,
                el.nombre AS local_nombre, el.abreviatura AS local_abrev, el.color_hex AS local_color, el.logo_url AS local_logo,
                ev.nombre AS visita_nombre, ev.abreviatura AS visita_abrev, ev.color_hex AS visita_color, ev.logo_url AS visita_logo,
                j.numero AS jornada_numero
         FROM partidos p
         JOIN equipos el ON el.id = p.equipo_local_id
         JOIN equipos ev ON ev.id = p.equipo_visita_id
         LEFT JOIN jornadas j ON j.id = p.jornada_id
         WHERE p.id = ?",
        [$id]
    );
    if (!$partido) {
        api_error(404, "Partido #{$id} no encontrado.");
    }
    return $partido;
}
