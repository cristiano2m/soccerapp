<?php
// Componente: marcador estilo estadio
// Espera: $partido (datos + local_nombre/visita_nombre), $resultado (o null), $goles (array), $tarjetas (array)
// Opcional: $live = true para activar polling (requiere live-data.php en el mismo directorio)
$live = $live ?? false;
$golesLocal = $resultado['goles_local'] ?? 0;
$golesVisita = $resultado['goles_visita'] ?? 0;
$estado = $partido['estado'] ?? 'programado';
?>
<div class="scoreboard">
    <?php if ($estado === 'en_curso'): ?>
        <div class="live-indicator" id="live-indicator"><span class="live-dot"></span> EN VIVO</div>
    <?php else: ?>
        <div class="live-indicator" style="color:#fff; visibility:hidden;" id="live-indicator"><span class="live-dot"></span> EN VIVO</div>
    <?php endif; ?>
    <div class="teams">
        <span id="local-name"><?= h($partido['local_nombre']) ?></span>
        <span id="estado-label" style="color:var(--color-primary);"><?= h(strtoupper(str_replace('_', ' ', $estado))) ?></span>
        <span id="visita-name"><?= h($partido['visita_nombre']) ?></span>
    </div>
    <div class="score" id="score"><?= (int) $golesLocal ?> — <?= (int) $golesVisita ?></div>
</div>

<div class="grid grid-2" style="margin-top:16px;">
    <div class="card">
        <h3 class="section-title"><span class="ms">sports_soccer</span> Goles</h3>
        <ul id="goles-list">
            <?php if (empty($goles)): ?>
                <li class="text-muted">Sin goles registrados</li>
            <?php else: foreach ($goles as $g): ?>
                <li><span class="ms" style="font-size:14px;color:var(--color-success);">sports_soccer</span> <?= h($g['jugador']) ?> — min. <?= (int) $g['minuto'] ?>' (<?= $g['equipo'] === 'local' ? h($partido['local_nombre']) : h($partido['visita_nombre']) ?>)</li>
            <?php endforeach; endif; ?>
        </ul>
    </div>
    <div class="card">
        <h3 class="section-title"><span class="ms">style</span> Tarjetas</h3>
        <ul id="tarjetas-list">
            <?php if (empty($tarjetas)): ?>
                <li class="text-muted">Sin tarjetas</li>
            <?php else: foreach ($tarjetas as $t): ?>
                <li>
                    <span class="ms" style="font-size:14px;color:<?= $t['tipo'] === 'roja' ? 'var(--color-danger)' : 'var(--color-warning)' ?>;">style</span>
                    <?= h($t['jugador']) ?> — min. <?= (int) $t['minuto'] ?>'
                </li>
            <?php endforeach; endif; ?>
        </ul>
    </div>
</div>

<?php if ($live): ?>
    <input type="hidden" id="partido-id" value="<?= (int) $partido['id'] ?>">
    <script src="<?= BASE_URL ?>/assets/js/live-score.js"></script>
<?php endif; ?>
