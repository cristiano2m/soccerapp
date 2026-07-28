<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer', 'referee']);

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) {
    set_flash('error', 'Primero debes configurar el torneo.');
    redirect('/admin/torneo/index.php');
}

$jornadas = $db->query(
    "SELECT id, numero FROM jornadas WHERE torneo_id = ? ORDER BY numero ASC",
    [$torneo['id']]
);

$jornadaFiltro = (int) ($_GET['jornada'] ?? 0);

$sql = "SELECT t.tipo, t.minuto,
               j.numero AS jugador_numero, j.nombre AS jugador_nombre,
               e.nombre AS equipo_nombre, e.color_hex, e.logo_url, e.abreviatura,
               jo.numero AS jornada_numero,
               p.id AS partido_id
        FROM tarjetas t
        JOIN jugadores j  ON j.id  = t.jugador_id
        JOIN equipos   e  ON e.id  = j.equipo_id
        JOIN partidos  p  ON p.id  = t.partido_id
        JOIN jornadas  jo ON jo.id = p.jornada_id
        WHERE p.torneo_id = ?
          AND t.tipo IN ('roja', 'doble_amarilla')";
$params = [$torneo['id']];

if ($jornadaFiltro > 0) {
    $sql .= " AND jo.id = ?";
    $params[] = $jornadaFiltro;
}

$sql .= " ORDER BY jo.numero ASC, e.nombre ASC, j.nombre ASC";

$tarjetas = $db->query($sql, $params);

// Totales para resumen
$totalRojas         = count(array_filter($tarjetas, fn($t) => $t['tipo'] === 'roja'));
$totalDobleAmarilla = count(array_filter($tarjetas, fn($t) => $t['tipo'] === 'doble_amarilla'));

$pageTitle = 'Tarjetas Rojas';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<div class="toolbar">
    <h1><span class="ms ms-lg">style</span> Tarjetas Rojas</h1>
</div>

<!-- Filtro por jornada -->
<div class="card" style="margin-bottom:16px;">
    <form method="get" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <label style="font-weight:600;">Jornada:</label>
        <select name="jornada" class="btn btn-outline btn-sm" style="min-width:160px;" onchange="this.form.submit()">
            <option value="0" <?= $jornadaFiltro === 0 ? 'selected' : '' ?>>Todas las jornadas</option>
            <?php foreach ($jornadas as $j): ?>
            <option value="<?= (int) $j['id'] ?>" <?= $jornadaFiltro === (int) $j['id'] ? 'selected' : '' ?>>
                Jornada <?= (int) $j['numero'] ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ($jornadaFiltro > 0): ?>
        <a href="<?= BASE_URL ?>/admin/reportes/tarjetas-rojas.php" class="btn btn-outline btn-sm">
            <span class="ms">close</span> Limpiar filtro
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Resumen -->
<?php if (!empty($tarjetas)): ?>
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <div class="card" style="flex:1;min-width:160px;text-align:center;padding:16px 20px;">
        <div style="font-size:2rem;font-weight:700;color:#e53935;">🔴 <?= $totalRojas ?></div>
        <div style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">Tarjetas Rojas</div>
    </div>
    <div class="card" style="flex:1;min-width:160px;text-align:center;padding:16px 20px;">
        <div style="font-size:2rem;font-weight:700;color:#f59e0b;">🟡🟡 <?= $totalDobleAmarilla ?></div>
        <div style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">Doble Amarilla</div>
    </div>
    <div class="card" style="flex:1;min-width:160px;text-align:center;padding:16px 20px;">
        <div style="font-size:2rem;font-weight:700;">📋 <?= count($tarjetas) ?></div>
        <div style="font-size:0.85rem;color:var(--text-muted);margin-top:4px;">Total expulsiones</div>
    </div>
</div>
<?php endif; ?>

<!-- Tabla -->
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Jornada</th>
                    <th>#</th>
                    <th>Jugador</th>
                    <th>Equipo</th>
                    <th>Tipo</th>
                    <th>Minuto</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tarjetas)): ?>
                <tr><td colspan="6" class="text-muted">No hay tarjetas rojas registradas<?= $jornadaFiltro > 0 ? ' en esta jornada' : '' ?>.</td></tr>
                <?php endif; ?>
                <?php
                $jornadaActual = null;
                foreach ($tarjetas as $t):
                    if ($jornadaActual !== $t['jornada_numero'] && $jornadaFiltro === 0):
                        $jornadaActual = $t['jornada_numero'];
                ?>
                <tr style="background:var(--surface-alt,#f5f5f5);">
                    <td colspan="6" style="font-weight:600;font-size:0.88rem;padding:6px 12px;color:var(--text-muted);">
                        Jornada <?= (int) $t['jornada_numero'] ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><?= $jornadaFiltro > 0 ? (int) $t['jornada_numero'] : '' ?></td>
                    <td style="font-weight:700;"><?= (int) $t['jugador_numero'] ?></td>
                    <td><?= h($t['jugador_nombre']) ?></td>
                    <td>
                        <div class="team-row">
                            <?= team_badge($t['equipo_nombre'], $t['abreviatura'], $t['color_hex'], $t['logo_url'], 22) ?>
                            <?= h($t['equipo_nombre']) ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($t['tipo'] === 'roja'): ?>
                            <span style="background:#e53935;color:#fff;padding:2px 8px;border-radius:4px;font-size:0.82rem;font-weight:600;">🔴 Roja</span>
                        <?php else: ?>
                            <span style="background:#f59e0b;color:#fff;padding:2px 8px;border-radius:4px;font-size:0.82rem;font-weight:600;">🟡🟡 Doble amarilla</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $t['minuto'] !== null ? (int) $t['minuto'] . "'" : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
