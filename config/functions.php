<?php
require_once __DIR__ . '/database.php';

// ══════════════════════════════════════════════
// HELPERS GENERALES
// ══════════════════════════════════════════════

// Alias seguro de htmlspecialchars para salida HTML
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Redirige y termina la ejecución
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

// Mensajes flash via sesión
function set_flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// ══════════════════════════════════════════════
// CSRF
// ══════════════════════════════════════════════

function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(?string $token): bool
{
    return !empty($_SESSION['csrf_token']) && !empty($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(generate_csrf_token()) . '">';
}

// ══════════════════════════════════════════════
// SESIÓN / USUARIO ACTUAL
// ══════════════════════════════════════════════

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): array
{
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'rol' => $_SESSION['rol'] ?? null,
        'nombre' => $_SESSION['nombre'] ?? null,
    ];
}

// Renderiza el escudo de un equipo: imagen si tiene logo_url, o círculo de color con abreviatura
function team_badge(string $nombre, ?string $abreviatura, ?string $colorHex, ?string $logoUrl, int $size = 32): string
{
    if (!empty($logoUrl)) {
        return '<img src="' . h($logoUrl) . '" alt="' . h($nombre) . '" class="team-logo" style="width:' . $size . 'px;height:' . $size . 'px;">';
    }
    $sigla = $abreviatura ?: mb_substr($nombre, 0, 3);
    $color = $colorHex ?: '#3B9EFF';
    return '<span class="team-logo" style="width:' . $size . 'px;height:' . $size . 'px;background:' . h($color) . ';">' . h(mb_strtoupper($sigla)) . '</span>';
}

// ══════════════════════════════════════════════
// SUBIDA DE IMÁGENES (logos, fotos)
// ══════════════════════════════════════════════

// Procesa la subida de una imagen ($_FILES[$field]) hacia uploads/$subdir/.
// Retorna la URL pública nueva, la $oldUrl sin cambios si no se subió archivo,
// o null si no había archivo ni URL previa. Lanza Exception si el archivo no es válido.
function handle_image_upload(string $field, string $subdir, ?string $oldUrl = null): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return $oldUrl;
    }

    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new Exception('Formato de imagen no permitido (usa JPG, PNG, WEBP o GIF).');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('La imagen no debe superar 2 MB.');
    }

    $dir = UPLOADS_PATH . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new Exception('No se pudo guardar la imagen.');
    }

    // Eliminar la imagen anterior si pertenecía a uploads/
    if (!empty($oldUrl) && str_starts_with($oldUrl, UPLOADS_URL)) {
        $oldPath = UPLOADS_PATH . substr($oldUrl, strlen(UPLOADS_URL));
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
    }

    return UPLOADS_URL . '/' . $subdir . '/' . $filename;
}

// ══════════════════════════════════════════════
// TORNEO — SESIÓN MULTI-TORNEO
// ══════════════════════════════════════════════

// Retorna el torneo activo:
// - Panel admin: el seleccionado en sesión ($_SESSION['torneo_id'])
// - Sitio público: si llega ?t=ID carga ese torneo; si no, el más reciente con estado='activo'
function obtener_torneo_activo(): ?array
{
    $db = Database::getInstance();

    if (!empty($_SESSION['torneo_id'])) {
        return $db->queryOne("SELECT * FROM torneos WHERE id = ?", [(int) $_SESSION['torneo_id']]);
    }

    if (!empty($_GET['t'])) {
        $torneo = $db->queryOne("SELECT * FROM torneos WHERE id = ?", [(int) $_GET['t']]);
        if ($torneo) {
            return $torneo;
        }
    }

    return $db->queryOne("SELECT * FROM torneos WHERE estado = 'activo' ORDER BY id DESC LIMIT 1")
        ?? $db->queryOne("SELECT * FROM torneos ORDER BY id DESC LIMIT 1");
}

// Devuelve la URL pública de un torneo (con parámetro ?t=id para identificarlo)
function url_publica_torneo(int $torneoId): string
{
    return BASE_URL . '/index.php?t=' . $torneoId;
}

// Establece el torneo activo en sesión
function seleccionar_torneo(int $torneoId, string $torneoRol): void
{
    $_SESSION['torneo_id']  = $torneoId;
    $_SESSION['torneo_rol'] = $torneoRol;
}

// Limpia el torneo activo de sesión (vuelve al selector)
function limpiar_torneo_activo(): void
{
    unset($_SESSION['torneo_id'], $_SESSION['torneo_rol']);
}

// Devuelve los torneos accesibles para el usuario: todos si es super_admin,
// solo los asignados via torneo_usuarios si no lo es
function obtener_torneos_accesibles(int $userId, string $rolGlobal): array
{
    $db = Database::getInstance();
    if ($rolGlobal === 'super_admin') {
        return $db->query("SELECT * FROM torneos ORDER BY id DESC");
    }
    return $db->query(
        "SELECT t.*, tu.rol AS rol_usuario
         FROM torneos t
         JOIN torneo_usuarios tu ON tu.torneo_id = t.id AND tu.usuario_id = ?
         ORDER BY t.id DESC",
        [$userId]
    );
}

// Próxima jornada con partidos pendientes (estado != finalizado)
function obtener_proxima_jornada(int $torneo_id): ?array
{
    $db = Database::getInstance();
    $jornada = $db->queryOne(
        "SELECT j.* FROM jornadas j
         WHERE j.torneo_id = ?
           AND EXISTS (SELECT 1 FROM partidos p WHERE p.jornada_id = j.id AND p.estado != 'finalizado')
         ORDER BY j.numero ASC LIMIT 1",
        [$torneo_id]
    );
    if ($jornada === null) {
        return null;
    }

    $jornada['partidos'] = $db->query(
        "SELECT p.*, el.nombre AS local_nombre, el.color_hex AS local_color, el.logo_url AS local_logo,
                ev.nombre AS visita_nombre, ev.color_hex AS visita_color, ev.logo_url AS visita_logo
         FROM partidos p
         JOIN equipos el ON el.id = p.equipo_local_id
         JOIN equipos ev ON ev.id = p.equipo_visita_id
         WHERE p.jornada_id = ?
         ORDER BY p.hora ASC, p.id ASC",
        [$jornada['id']]
    );

    return $jornada;
}

// Últimos resultados (partidos finalizados), más recientes primero
function obtener_ultimos_resultados(int $torneo_id, int $limit = 10): array
{
    $db = Database::getInstance();
    return $db->query(
        "SELECT p.*, j.numero AS jornada_numero, j.fecha AS jornada_fecha,
                el.nombre AS local_nombre, el.color_hex AS local_color, el.logo_url AS local_logo,
                ev.nombre AS visita_nombre, ev.color_hex AS visita_color, ev.logo_url AS visita_logo,
                r.goles_local, r.goles_visita, r.wo_local, r.wo_visita
         FROM partidos p
         JOIN jornadas j ON j.id = p.jornada_id
         JOIN equipos el ON el.id = p.equipo_local_id
         JOIN equipos ev ON ev.id = p.equipo_visita_id
         JOIN resultados r ON r.partido_id = p.id
         WHERE p.torneo_id = ? AND p.estado = 'finalizado'
         ORDER BY j.numero DESC, p.id DESC
         LIMIT " . (int) $limit,
        [$torneo_id]
    );
}

// Ranking de goleadores
function obtener_goleadores(int $torneo_id, int $limit = 10): array
{
    $db = Database::getInstance();
    return $db->query(
        "SELECT j.id AS jugador_id, j.nombre AS jugador_nombre, j.numero,
                e.id AS equipo_id, e.nombre AS equipo_nombre, e.abreviatura, e.color_hex,
                COUNT(g.id) AS goles
         FROM goles g
         JOIN jugadores j ON j.id = g.jugador_id
         JOIN equipos e ON e.id = g.equipo_id
         JOIN partidos p ON p.id = g.partido_id
         WHERE p.torneo_id = ? AND g.tipo != 'autogol'
         GROUP BY j.id, j.nombre, j.numero, e.id, e.nombre, e.abreviatura, e.color_hex
         ORDER BY goles DESC, j.nombre ASC
         LIMIT " . (int) $limit,
        [$torneo_id]
    );
}

// Tabla de posiciones calculada en PHP (PJ, PG, PE, PP, GF, GC, DG, Pts)
function calcular_posiciones(int $torneo_id): array
{
    $db = Database::getInstance();

    $torneo = $db->queryOne("SELECT pts_victoria, pts_empate, pts_derrota FROM torneos WHERE id = ?", [$torneo_id]);
    $ptsVictoria = (int) ($torneo['pts_victoria'] ?? 3);
    $ptsEmpate = (int) ($torneo['pts_empate'] ?? 1);
    $ptsDerrota = (int) ($torneo['pts_derrota'] ?? 0);

    $equipos = $db->query(
        "SELECT id, nombre, abreviatura, color_hex, logo_url FROM equipos WHERE torneo_id = ? AND activo = 1",
        [$torneo_id]
    );

    $tabla = [];
    foreach ($equipos as $equipo) {
        $tabla[$equipo['id']] = [
            'id' => $equipo['id'],
            'nombre' => $equipo['nombre'],
            'abreviatura' => $equipo['abreviatura'],
            'color_hex' => $equipo['color_hex'],
            'logo_url' => $equipo['logo_url'],
            'pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0,
            'gf' => 0, 'gc' => 0, 'pts' => 0,
        ];
    }

    $partidos = $db->query(
        "SELECT p.equipo_local_id, p.equipo_visita_id,
                r.goles_local, r.goles_visita, r.wo_local, r.wo_visita
         FROM partidos p
         JOIN resultados r ON r.partido_id = p.id
         WHERE p.torneo_id = ? AND p.estado = 'finalizado'",
        [$torneo_id]
    );

    foreach ($partidos as $partido) {
        $localId = $partido['equipo_local_id'];
        $visitaId = $partido['equipo_visita_id'];
        if (!isset($tabla[$localId]) || !isset($tabla[$visitaId])) {
            continue;
        }

        $woLocal = (bool) $partido['wo_local'];
        $woVisita = (bool) $partido['wo_visita'];

        $tabla[$localId]['pj']++;
        $tabla[$visitaId]['pj']++;

        // Ambos no se presentaron: ambos pierden, sin goles ni puntos
        if ($woLocal && $woVisita) {
            $tabla[$localId]['pp']++;
            $tabla[$visitaId]['pp']++;
            continue;
        }

        if ($woLocal || $woVisita) {
            $golesLocal = $woLocal ? 0 : 1;
            $golesVisita = $woVisita ? 0 : 1;
        } else {
            $golesLocal = (int) $partido['goles_local'];
            $golesVisita = (int) $partido['goles_visita'];
        }

        $tabla[$localId]['gf'] += $golesLocal;
        $tabla[$localId]['gc'] += $golesVisita;
        $tabla[$visitaId]['gf'] += $golesVisita;
        $tabla[$visitaId]['gc'] += $golesLocal;

        if ($golesLocal > $golesVisita) {
            $tabla[$localId]['pg']++;
            $tabla[$localId]['pts'] += $ptsVictoria;
            $tabla[$visitaId]['pp']++;
            $tabla[$visitaId]['pts'] += $ptsDerrota;
        } elseif ($golesLocal < $golesVisita) {
            $tabla[$visitaId]['pg']++;
            $tabla[$visitaId]['pts'] += $ptsVictoria;
            $tabla[$localId]['pp']++;
            $tabla[$localId]['pts'] += $ptsDerrota;
        } else {
            $tabla[$localId]['pe']++;
            $tabla[$localId]['pts'] += $ptsEmpate;
            $tabla[$visitaId]['pe']++;
            $tabla[$visitaId]['pts'] += $ptsEmpate;
        }
    }

    foreach ($tabla as &$fila) {
        $fila['dg'] = $fila['gf'] - $fila['gc'];
    }
    unset($fila);

    $tabla = array_values($tabla);
    usort($tabla, function (array $a, array $b): int {
        if ($a['pts'] !== $b['pts']) {
            return $b['pts'] - $a['pts'];
        }
        if ($a['dg'] !== $b['dg']) {
            return $b['dg'] - $a['dg'];
        }
        return $b['gf'] - $a['gf'];
    });

    return $tabla;
}

// ══════════════════════════════════════════════
// CALENDARIO — ROUND ROBIN
// ══════════════════════════════════════════════

// Genera el calendario round robin (todos contra todos, ida) para un torneo
function generar_round_robin(int $torneo_id): int
{
    $db = Database::getInstance();

    $equipos = $db->query("SELECT id FROM equipos WHERE torneo_id = ? AND activo = 1", [$torneo_id]);
    $teams = array_column($equipos, 'id');
    $n = count($teams);

    if ($n < 2) {
        throw new Exception('Se necesitan al menos 2 equipos activos para generar el calendario.');
    }

    if ($n % 2 !== 0) {
        $teams[] = null; // BYE para número impar de equipos
    }
    $n = count($teams);
    $rounds = $n - 1;
    $half = (int) ($n / 2);
    $fixed = array_shift($teams);

    $fechaBase = new DateTime();
    $total = 0;

    for ($r = 0; $r < $rounds; $r++) {
        $current = array_merge([$fixed], $teams);
        $fecha = clone $fechaBase;
        $fecha->modify("+{$r} weeks");

        $jornadaId = $db->insert(
            "INSERT INTO jornadas (torneo_id, numero, fecha) VALUES (?, ?, ?)",
            [$torneo_id, $r + 1, $fecha->format('Y-m-d')]
        );

        for ($i = 0; $i < $half; $i++) {
            $local = $current[$i];
            $visita = $current[$n - 1 - $i];
            if (!$local || !$visita) {
                continue; // BYE
            }
            $db->insert(
                "INSERT INTO partidos (jornada_id, torneo_id, equipo_local_id, equipo_visita_id) VALUES (?, ?, ?, ?)",
                [$jornadaId, $torneo_id, $local, $visita]
            );
            $total++;
        }

        array_push($teams, array_shift($teams));
    }

    return $total;
}

// ══════════════════════════════════════════════
// RESULTADOS — W.O.
// ══════════════════════════════════════════════

// Calcula goles y puntos resultantes de una incidencia de W.O.
function calcular_puntos_wo(bool $woLocal, bool $woVisita, int $ptsVictoria = 3, int $ptsDerrota = 0): array
{
    if ($woLocal && $woVisita) {
        return ['goles_local' => 0, 'goles_visita' => 0, 'pts_local' => 0, 'pts_visita' => 0];
    }
    if ($woLocal) {
        // Local no se presentó → visita gana 1-0
        return ['goles_local' => 0, 'goles_visita' => 1, 'pts_local' => $ptsDerrota, 'pts_visita' => $ptsVictoria];
    }
    // Visita no se presentó → local gana 1-0
    return ['goles_local' => 1, 'goles_visita' => 0, 'pts_local' => $ptsVictoria, 'pts_visita' => $ptsDerrota];
}
