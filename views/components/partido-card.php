<?php
// Componente: tarjeta de partido
// Espera: $partido (fila con datos de equipos local/visita y opcionalmente resultado)
$tieneResultado = array_key_exists('goles_local', $partido) && $partido['goles_local'] !== null;
$estado = $partido['estado'] ?? 'programado';
?>
<a class="match-card" href="<?= BASE_URL ?>/public/partido.php?id=<?= (int) $partido['id'] ?>" style="text-decoration:none;">
    <div class="match-header">
        <span>Jornada <?= (int) ($partido['jornada_numero'] ?? 0) ?><?= !empty($partido['cancha']) ? ' · ' . h($partido['cancha']) : '' ?></span>
        <span class="badge badge-<?= h($estado) ?>"><?= h(str_replace('_', ' ', $estado)) ?></span>
    </div>
    <div class="match-body">
        <div class="team">
            <?= team_badge($partido['local_nombre'], null, $partido['local_color'], $partido['local_logo']) ?>
            <span><?= h($partido['local_nombre']) ?></span>
        </div>
        <div class="vs">
            <?php if ($tieneResultado): ?>
                <span class="score"><?= (int) $partido['goles_local'] ?> - <?= (int) $partido['goles_visita'] ?></span>
            <?php elseif (!empty($partido['hora'])): ?>
                <span><?= h(substr((string) $partido['hora'], 0, 5)) ?></span>
            <?php else: ?>
                <span>VS</span>
            <?php endif; ?>
        </div>
        <div class="team">
            <?= team_badge($partido['visita_nombre'], null, $partido['visita_color'], $partido['visita_logo']) ?>
            <span><?= h($partido['visita_nombre']) ?></span>
        </div>
    </div>
    <?php if (!empty($partido['wo_local']) || !empty($partido['wo_visita'])): ?>
    <div style="text-align:center; margin-top:8px;"><span class="badge badge-wo">W.O.</span></div>
    <?php endif; ?>
</a>
