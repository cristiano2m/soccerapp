<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer']);

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) {
    set_flash('error', 'Primero debes configurar el torneo.');
    redirect('/admin/torneo/index.php');
}

$jornadaFiltro = (int) ($_GET['jornada'] ?? 0);
$estadoFiltro = $_GET['estado'] ?? '';


$sql = "SELECT p.*, j.numero AS jornada_numero,
               el.nombre AS local_nombre, el.color_hex AS local_color, el.logo_url AS local_logo, el.abreviatura AS local_abrev,
               ev.nombre AS visita_nombre, ev.color_hex AS visita_color, ev.logo_url AS visita_logo, ev.abreviatura AS visita_abrev,
               r.goles_local, r.goles_visita, r.wo_local, r.wo_visita,
               ua.nombre AS arbitro_nombre
        FROM partidos p
        JOIN jornadas j ON j.id = p.jornada_id
        JOIN equipos el ON el.id = p.equipo_local_id
        JOIN equipos ev ON ev.id = p.equipo_visita_id
        LEFT JOIN resultados r  ON r.partido_id = p.id
        LEFT JOIN usuarios  ua ON ua.id = p.arbitro_id
        WHERE p.torneo_id = ?";
$params = [$torneo['id']];

if ($jornadaFiltro > 0) {
    $sql .= " AND j.numero = ?";
    $params[] = $jornadaFiltro;
}
if (in_array($estadoFiltro, ['programado', 'en_curso', 'finalizado', 'suspendido', 'wo'], true)) {
    $sql .= " AND p.estado = ?";
    $params[] = $estadoFiltro;
}
$sql .= " ORDER BY j.numero ASC, p.hora ASC, p.id ASC";

$partidos = $db->query($sql, $params);
$jornadas = $db->query("SELECT numero FROM jornadas WHERE torneo_id = ? ORDER BY numero DESC", [$torneo['id']]);

$pageTitle = 'Resultados';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<h1><span class="ms ms-lg">sports_soccer</span> Resultados</h1>

<div class="card" style="margin-bottom:18px;">
    <form method="get" class="form-row" style="align-items:flex-end;">
        <div class="form-group" style="max-width:160px;">
            <label for="jornada">Jornada</label>
            <select id="jornada" name="jornada">
                <option value="0">Todas</option>
                <?php foreach ($jornadas as $j): ?>
                    <option value="<?= (int) $j['numero'] ?>" <?= $jornadaFiltro === (int) $j['numero'] ? 'selected' : '' ?>>Jornada <?= (int) $j['numero'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="max-width:200px;">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach (['programado', 'en_curso', 'finalizado', 'suspendido', 'wo'] as $e): ?>
                    <option value="<?= $e ?>" <?= $estadoFiltro === $e ? 'selected' : '' ?>><?= str_replace('_', ' ', $e) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="max-width:140px;">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Jornada</th>
                    <th>Local</th>
                    <th></th>
                    <th>Visita</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($partidos)): ?>
                <tr><td colspan="6" class="text-muted">No hay partidos que coincidan con el filtro.</td></tr>
                <?php endif; ?>
                <?php foreach ($partidos as $p): ?>
                <tr>
                    <td>J<?= (int) $p['jornada_numero'] ?></td>
                    <td><div class="team-row"><?= team_badge($p['local_nombre'], $p['local_abrev'], $p['local_color'], $p['local_logo'], 24) ?> <?= h($p['local_nombre']) ?></div></td>
                    <td class="pts">
                        <?php if ($p['goles_local'] !== null): ?>
                            <?= (int) $p['goles_local'] ?> - <?= (int) $p['goles_visita'] ?>
                        <?php else: ?>
                            vs
                        <?php endif; ?>
                    </td>
                    <td><div class="team-row"><?= team_badge($p['visita_nombre'], $p['visita_abrev'], $p['visita_color'], $p['visita_logo'], 24) ?> <?= h($p['visita_nombre']) ?></div></td>
                    <td>
                        <span class="badge badge-<?= h($p['estado']) ?>"><?= h(str_replace('_', ' ', $p['estado'])) ?></span>
                        <?php if (!empty($p['wo_local']) || !empty($p['wo_visita'])): ?> <span class="badge badge-wo">W.O.</span><?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/resultados/partido.php?id=<?= (int) $p['id'] ?>"><span class="ms">edit_note</span> Registrar</a>
                        <?php if ($p['estado'] === 'finalizado'): ?>
                            <a class="btn btn-dark btn-sm" href="<?= BASE_URL ?>/admin/acta/index.php?id=<?= (int) $p['id'] ?>" target="_blank"><span class="ms">description</span> Acta</a>
                        <?php endif; ?>

                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
