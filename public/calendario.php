<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

$jornadas = [];
$partidosPorJornada = [];

if ($torneo) {
    $jornadas = $db->query("SELECT * FROM jornadas WHERE torneo_id = ? ORDER BY numero ASC", [$torneo['id']]);
    if (!empty($jornadas)) {
        $partidos = $db->query(
            "SELECT p.*, j.numero AS jornada_numero,
                    el.nombre AS local_nombre, el.color_hex AS local_color, el.logo_url AS local_logo, el.abreviatura AS local_abrev,
                    ev.nombre AS visita_nombre, ev.color_hex AS visita_color, ev.logo_url AS visita_logo, ev.abreviatura AS visita_abrev,
                    r.goles_local, r.goles_visita, r.wo_local, r.wo_visita
             FROM partidos p
             JOIN jornadas j ON j.id = p.jornada_id
             JOIN equipos el ON el.id = p.equipo_local_id
             JOIN equipos ev ON ev.id = p.equipo_visita_id
             LEFT JOIN resultados r ON r.partido_id = p.id
             WHERE p.torneo_id = ?
             ORDER BY p.jornada_id ASC, p.hora ASC, p.id ASC",
            [$torneo['id']]
        );
        foreach ($partidos as $p) {
            $partidosPorJornada[$p['jornada_id']][] = $p;
        }
    }
}

$pageTitle = 'Calendario';
$layout = 'public';
require __DIR__ . '/../views/layout/header.php';
?>
<section class="section">
    <div class="container">
        <h1 class="section-title"><span class="ms ms-lg">calendar_month</span> Calendario</h1>
        <?php if (!$torneo || empty($jornadas)): ?>
            <p class="text-muted">El calendario todavía no está disponible.</p>
        <?php else: ?>
            <?php foreach ($jornadas as $jornada): ?>
            <h2 class="section-title" style="margin-top:24px;">Jornada <?= (int) $jornada['numero'] ?><?= !empty($jornada['fecha']) ? ' · ' . h($jornada['fecha']) : '' ?></h2>
            <div class="grid grid-3">
                <?php foreach (($partidosPorJornada[$jornada['id']] ?? []) as $p): ?>
                    <?php $partido = $p; require __DIR__ . '/../views/components/partido-card.php'; ?>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
