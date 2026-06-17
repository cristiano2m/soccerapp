<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = Database::getInstance();

$id = (int) ($_GET['id'] ?? 0);
$partido = $db->queryOne(
    "SELECT p.*, j.numero AS jornada_numero, j.fecha AS jornada_fecha,
            el.id AS local_id, el.nombre AS local_nombre, el.color_hex AS local_color, el.logo_url AS local_logo, el.abreviatura AS local_abrev,
            ev.id AS visita_id, ev.nombre AS visita_nombre, ev.color_hex AS visita_color, ev.logo_url AS visita_logo, ev.abreviatura AS visita_abrev
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

$resultado = $db->queryOne("SELECT * FROM resultados WHERE partido_id = ?", [$partido['id']]);

$golesRaw = $db->query(
    "SELECT g.*, j.nombre AS jugador_nombre FROM goles g JOIN jugadores j ON j.id = g.jugador_id WHERE g.partido_id = ? ORDER BY g.minuto ASC, g.id ASC",
    [$partido['id']]
);
$goles = array_map(fn($g) => [
    'jugador' => $g['jugador_nombre'],
    'minuto' => $g['minuto'] ?? 0,
    'equipo' => (int) $g['equipo_id'] === (int) $partido['local_id'] ? 'local' : 'visita',
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

$pageTitle = $partido['local_nombre'] . ' vs ' . $partido['visita_nombre'];
$layout = 'public';
require __DIR__ . '/../views/layout/header.php';
?>
<section class="section">
    <div class="container">
        <p class="text-muted">Jornada <?= (int) $partido['jornada_numero'] ?><?= !empty($partido['jornada_fecha']) ? ' · ' . h($partido['jornada_fecha']) : '' ?><?= !empty($partido['cancha']) ? ' · ' . h($partido['cancha']) : '' ?><?= !empty($partido['hora']) ? ' · ' . h(substr($partido['hora'], 0, 5)) : '' ?></p>

        <div class="grid grid-2" style="align-items:center; margin:20px 0;">
            <div style="text-align:center;">
                <?= team_badge($partido['local_nombre'], $partido['local_abrev'], $partido['local_color'], $partido['local_logo'], 64) ?>
                <h2 style="margin-top:8px;"><?= h($partido['local_nombre']) ?></h2>
            </div>
            <div style="text-align:center;">
                <?= team_badge($partido['visita_nombre'], $partido['visita_abrev'], $partido['visita_color'], $partido['visita_logo'], 64) ?>
                <h2 style="margin-top:8px;"><?= h($partido['visita_nombre']) ?></h2>
            </div>
        </div>

        <?php if (!empty($partido['wo_local']) || !empty($partido['wo_visita'])): ?>
        <div style="text-align:center; margin-bottom:16px;"><span class="badge badge-wo">W.O.</span></div>
        <?php endif; ?>

        <?php require __DIR__ . '/../views/components/score-board.php'; ?>

        <?php if ($partido['estado'] === 'en_curso'): ?>
        <p style="margin-top:16px; text-align:center;">
            <a class="btn btn-primary" href="<?= BASE_URL ?>/pantalla-live/<?= (int) $partido['id'] ?>" target="_blank">📺 Ver pantalla en vivo</a>
        </p>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
