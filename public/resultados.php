<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

$grupos = [];
if ($torneo) {
    $partidos = $db->query(
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
         ORDER BY j.numero DESC, p.hora ASC, p.cancha IS NULL ASC, p.cancha ASC, p.id ASC",
        [$torneo['id']]
    );
    foreach ($partidos as $p) {
        $jid = $p['jornada_id'];
        if (!isset($grupos[$jid])) {
            $grupos[$jid] = ['numero' => $p['jornada_numero'], 'fecha' => $p['jornada_fecha'], 'partidos' => []];
        }
        $grupos[$jid]['partidos'][] = $p;
    }
}

$pageTitle = 'Resultados';
$layout = 'public';
require __DIR__ . '/../views/layout/header.php';
?>
<section class="section">
    <div class="container">
        <h1 class="section-title"><span class="ms ms-lg">sports_soccer</span> Resultados</h1>
        <?php if (!$torneo || empty($grupos)): ?>
            <p class="text-muted">Todavía no hay resultados registrados.</p>
        <?php else: ?>
            <?php foreach ($grupos as $grupo): ?>
            <h2 class="section-title" style="margin-top:24px;">Jornada <?= (int) $grupo['numero'] ?><?= !empty($grupo['fecha']) ? ' · ' . h($grupo['fecha']) : '' ?></h2>
            <div class="grid grid-3">
                <?php foreach ($grupo['partidos'] as $p): ?>
                    <?php $partido = $p; require __DIR__ . '/../views/components/partido-card.php'; ?>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
