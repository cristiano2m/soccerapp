<?php
// Componente: tabla de posiciones
// Espera: $posiciones (array de calcular_posiciones), opcional $limit (int|null)
$filas = $limit ?? null ? array_slice($posiciones, 0, $limit) : $posiciones;
?>
<div class="table-wrap">
    <table class="posiciones-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Equipo</th>
                <th>PJ</th>
                <th>PG</th>
                <th>PE</th>
                <th>PP</th>
                <th>GF</th>
                <th>GC</th>
                <th>DG</th>
                <th>Pts</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($filas)): ?>
            <tr><td colspan="10" class="text-muted">Aún no hay partidos finalizados.</td></tr>
            <?php endif; ?>
            <?php foreach ($filas as $i => $eq): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td class="team-cell">
                    <div class="team-row">
                        <?= team_badge($eq['nombre'], $eq['abreviatura'], $eq['color_hex'], $eq['logo_url'], 26) ?>
                        <?= h($eq['nombre']) ?>
                    </div>
                </td>
                <td><?= $eq['pj'] ?></td>
                <td><?= $eq['pg'] ?></td>
                <td><?= $eq['pe'] ?></td>
                <td><?= $eq['pp'] ?></td>
                <td><?= $eq['gf'] ?></td>
                <td><?= $eq['gc'] ?></td>
                <td><?= $eq['dg'] ?></td>
                <td class="pts"><?= $eq['pts'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
