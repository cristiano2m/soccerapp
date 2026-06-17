<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

$equipos = [];
if ($torneo) {
    $equipos = $db->query("SELECT * FROM equipos WHERE torneo_id = ? AND activo = 1 ORDER BY nombre ASC", [$torneo['id']]);
    foreach ($equipos as &$eq) {
        $eq['jugadores'] = $db->query("SELECT nombre, numero, posicion FROM jugadores WHERE equipo_id = ? AND activo = 1 ORDER BY numero ASC", [$eq['id']]);
    }
    unset($eq);
}

$pageTitle = 'Equipos';
$layout = 'public';
require __DIR__ . '/../views/layout/header.php';
?>
<section class="section">
    <div class="container">
        <h1 class="section-title"><span class="ms ms-lg">groups</span> Equipos</h1>
        <?php if (!$torneo || empty($equipos)): ?>
            <p class="text-muted">Todavía no hay equipos registrados.</p>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($equipos as $eq): ?>
                <div class="team-card">
                    <?= team_badge($eq['nombre'], $eq['abreviatura'], $eq['color_hex'], $eq['logo_url'], 64) ?>
                    <h3><?= h($eq['nombre']) ?></h3>
                    <?php if (!empty($eq['delegado'])): ?><p class="text-muted" style="font-size:0.85rem;">Delegado: <?= h($eq['delegado']) ?></p><?php endif; ?>
                    <details style="margin-top:10px; text-align:left;">
                        <summary style="cursor:pointer; font-weight:700; text-align:center;">Plantel (<?= count($eq['jugadores']) ?>)</summary>
                        <table style="margin-top:10px;">
                            <thead><tr><th>#</th><th>Nombre</th><th>Posición</th></tr></thead>
                            <tbody>
                            <?php foreach ($eq['jugadores'] as $j): ?>
                                <tr><td><?= (int) $j['numero'] ?></td><td><?= h($j['nombre']) ?></td><td><?= h($j['posicion']) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
