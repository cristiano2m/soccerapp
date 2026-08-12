<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer', 'referee']);

$db     = Database::getInstance();
$torneo = obtener_torneo_activo();

$jornadaId = (int) ($_GET['jornada_id'] ?? 0);
$jornada   = $jornadaId ? $db->queryOne(
    "SELECT * FROM jornadas WHERE id = ? AND torneo_id = ?",
    [$jornadaId, $torneo['id'] ?? 0]
) : null;

if (!$torneo || !$jornada) {
    set_flash('error', 'Jornada no encontrada.');
    redirect('/admin/resultados/index.php');
}

$rolActivo = $_SESSION['torneo_rol'] ?? $_SESSION['rol'] ?? '';

$partidos = $db->query(
    "SELECT p.*,
            el.id AS local_id,   el.nombre AS local_nombre,   el.delegado AS local_delegado,
            ev.id AS visita_id,  ev.nombre AS visita_nombre,  ev.delegado AS visita_delegado,
            r.goles_local, r.goles_visita, r.wo_local, r.wo_visita, r.observaciones,
            a.nombre  AS arbitro_nombre,
            a2.nombre AS arbitro2_nombre,
            a3.nombre AS arbitro3_nombre
     FROM partidos p
     JOIN equipos el  ON el.id  = p.equipo_local_id
     JOIN equipos ev  ON ev.id  = p.equipo_visita_id
     LEFT JOIN resultados r ON r.partido_id = p.id
     LEFT JOIN usuarios a   ON a.id   = p.arbitro_id
     LEFT JOIN usuarios a2  ON a2.id  = p.arbitro_id2
     LEFT JOIN usuarios a3  ON a3.id  = p.arbitro_id3
     WHERE p.jornada_id = ?
     ORDER BY p.hora ASC, p.cancha IS NULL ASC, p.cancha ASC, p.id ASC",
    [$jornada['id']]
);

if (empty($partidos)) {
    set_flash('error', 'No hay partidos en esta jornada.');
    redirect('/admin/resultados/index.php');
}

// Jugadores suspendidos:
// - doble_amarilla o roja de jornada anterior  → suspendido (partido 1)
// - roja directa de hace dos jornadas          → suspendido (partido 2 de 2)
$suspendidos = [];
$jornadaNum  = (int) $jornada['numero'];
$condiciones = [];
$paramsSusp  = [$torneo['id']];

if ($jornadaNum - 1 > 0) {
    $condiciones[] = "(t.tipo IN ('roja','doble_amarilla') AND j.numero = ?)";
    $paramsSusp[]  = $jornadaNum - 1;
}
if ($jornadaNum - 2 > 0) {
    $condiciones[] = "(t.tipo = 'roja' AND j.numero = ?)";
    $paramsSusp[]  = $jornadaNum - 2;
}
if (!empty($condiciones)) {
    $filasSusp = $db->query(
        "SELECT DISTINCT t.jugador_id
         FROM tarjetas t
         JOIN partidos p ON p.id = t.partido_id
         JOIN jornadas j ON j.id = p.jornada_id
         WHERE j.torneo_id = ? AND (" . implode(' OR ', $condiciones) . ")",
        $paramsSusp
    );
    foreach ($filasSusp as $s) {
        $suspendidos[$s['jugador_id']] = true;
    }
}

// Pre-cargar datos de cada partido
$actasData = [];
foreach ($partidos as $p) {
    $MIN_FILAS   = 20;
    $jugL = $db->query("SELECT * FROM jugadores WHERE equipo_id = ? AND activo = 1 ORDER BY numero ASC", [$p['local_id']]);
    $jugV = $db->query("SELECT * FROM jugadores WHERE equipo_id = ? AND activo = 1 ORDER BY numero ASC", [$p['visita_id']]);
    $goles    = $db->query("SELECT jugador_id, tipo FROM goles    WHERE partido_id = ?", [$p['id']]);
    $tarjetas = $db->query("SELECT jugador_id, tipo FROM tarjetas WHERE partido_id = ?", [$p['id']]);
    $stats    = [];
    foreach ($goles as $g) {
        if ($g['tipo'] !== 'autogol') $stats[$g['jugador_id']]['g'] = ($stats[$g['jugador_id']]['g'] ?? 0) + 1;
    }
    foreach ($tarjetas as $t) {
        $jid = $t['jugador_id'];
        if (in_array($t['tipo'], ['amarilla', 'doble_amarilla'], true)) $stats[$jid]['a'] = ($stats[$jid]['a'] ?? 0) + 1;
        if (in_array($t['tipo'], ['roja',     'doble_amarilla'], true)) $stats[$jid]['r'] = ($stats[$jid]['r'] ?? 0) + 1;
    }
    $actasData[] = [
        'partido'       => $p,
        'jugadoresLocal'  => $jugL,
        'jugadoresVisita' => $jugV,
        'stats'         => $stats,
        'totalFilas'    => max($MIN_FILAS, count($jugL), count($jugV)),
    ];
}

$urlPublica = url_publica_torneo((int) $torneo['id']);
$pageTitle  = 'Actas Jornada ' . (int) $jornada['numero'];
$layout     = 'admin';
require __DIR__ . '/../../views/layout/header.php';
if ($rolActivo === 'referee') {
    require __DIR__ . '/../../views/layout/sidebar-arbitro.php';
} else {
    require __DIR__ . '/../../views/layout/sidebar-admin.php';
}
?>
<div class="toolbar no-print">
    <h1>📄 Actas — Jornada <?= (int) $jornada['numero'] ?> (<?= count($partidos) ?> partidos)</h1>
    <div class="actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
        <a class="btn btn-outline" href="<?= BASE_URL ?><?= $rolActivo === 'referee' ? '/admin/arbitro/calendario.php' : '/admin/resultados/index.php' ?>">← Volver</a>
    </div>
</div>

<style>
/* ── Estilos de acta (idénticos al acta individual) ── */
.acta {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    color: #000;
    max-width: 780px;
    margin: 0 auto;
    background: #fff;
}
.acta-wrap + .acta-wrap { margin-top: 24px; }
.acta-header { display: grid; grid-template-columns: 70px 1fr 100px; align-items: center; gap: 8px; margin-bottom: 8px; }
.acta-header-logo img { width: 64px; height: 64px; object-fit: contain; }
.acta-header-logo .logo-placeholder { width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; font-size: 2rem; border: 1px solid #ccc; }
.acta-header-title { text-align: center; }
.acta-header-title h1 { font-size: 15pt; font-weight: 900; margin: 0 0 2px; text-transform: uppercase; }
.acta-jornada-box { border: 2px solid #000; text-align: center; padding: 4px; }
.acta-jornada-box .label { font-size: 7pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
.acta-jornada-box .numero { font-size: 28pt; font-weight: 900; line-height: 1; }
.acta-info { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.acta-info td { border: 1px solid #000; padding: 3px 6px; font-size: 8.5pt; }
.acta-info .lbl { font-weight: 700; white-space: nowrap; background: #f5f5f5; width: 90px; }
.acta-info .val { width: 200px; }
.acta-equipos { width: 100%; border-collapse: collapse; margin-bottom: 0; }
.acta-equipos td { border: 1px solid #000; padding: 3px 6px; }
.acta-equipos .equipo-label { font-size: 7pt; font-weight: 700; text-transform: uppercase; width: 55px; background: #f5f5f5; }
.acta-equipos .equipo-nombre { font-size: 13pt; font-weight: 900; text-transform: uppercase; }
.acta-equipos .marcador-box { width: 38px; height: 28px; border: 2px solid #000; text-align: center; font-size: 14pt; font-weight: 900; vertical-align: middle; }
.acta-equipos .separador { width: 10px; border: none; background: #fff; }
.acta-jugadores { width: 100%; border-collapse: collapse; margin-top: 0; }
.acta-jugadores th { border: 1px solid #000; padding: 2px 3px; font-size: 7.5pt; font-weight: 700; text-align: left; background: #f5f5f5; color: #000; }
.acta-jugadores td { border: 1px solid #000; padding: 1px 3px; font-size: 7.5pt; height: 16px; }
.acta-jugadores .col-no   { width: 26px; text-align: center; }
.acta-jugadores .col-stat { width: 18px; text-align: center; }
.acta-jugadores .col-sep  { width: 8px; border-left: 2px solid #000; border-right: 2px solid #000; background: #fff; padding: 0; }
.acta-obs .obs-label { font-size: 8pt; font-weight: 700; text-transform: uppercase; border: 1px solid #000; border-bottom: none; padding: 2px 6px; background: #f5f5f5; }
.acta-obs .obs-lines { border: 1px solid #000; }
.acta-obs .obs-line { border-bottom: 1px solid #ccc; height: 18px; padding: 0 6px; }
.acta-obs .obs-line:last-child { border-bottom: none; }
.acta-obs .obs-text { padding: 4px 6px; font-size: 8pt; white-space: pre-wrap; }
.acta-firmas { width: 100%; border-collapse: collapse; margin-top: 8px; }
.acta-firmas td { border: 1px solid #000; text-align: center; padding: 0; }
.acta-firmas .firma-espacio { height: 38px; border-bottom: 1px solid #000; }
.acta-firmas .firma-label { font-size: 7pt; font-weight: 700; text-transform: uppercase; padding: 3px 4px; }
.acta-firmas .firma-nombre { font-size: 6.5pt; color: #444; padding: 1px 4px 3px; border-top: 1px solid #ddd; }
.acta-footer { margin-top: 6px; font-size: 7pt; color: #555; text-align: center; }

@media print {
    .no-print { display: none !important; }
    .admin-sidebar, .admin-topbar { display: none !important; }
    .admin-content { padding: 0 !important; }
    .admin-main    { padding: 0 !important; }
    body { background: #fff; }
    .acta { max-width: 100%; margin: 0; }
    .acta-wrap { page-break-after: always; break-after: page; }
    .acta-wrap:last-child { page-break-after: avoid; break-after: avoid; }
    @page { margin: 10mm; size: A4 portrait; }
}
</style>

<?php foreach ($actasData as $data):
    $p     = $data['partido'];
    $jugL  = $data['jugadoresLocal'];
    $jugV  = $data['jugadoresVisita'];
    $stats = $data['stats'];
    $total = $data['totalFilas'];
?>
<div class="acta-wrap">
<div class="acta">

    <div class="acta-header">
        <div class="acta-header-logo">
            <?php if (!empty($torneo['logo_url'])): ?>
                <img src="<?= h($torneo['logo_url']) ?>" alt="<?= h($torneo['nombre']) ?>">
            <?php else: ?>
                <div class="logo-placeholder">⚽</div>
            <?php endif; ?>
        </div>
        <div class="acta-header-title">
            <h1><?= h($torneo['nombre'] ?? APP_NAME) ?></h1>
        </div>
        <div class="acta-jornada-box">
            <div class="label">Jornada</div>
            <div class="numero"><?= (int) $jornada['numero'] ?></div>
        </div>
    </div>

    <table class="acta-info">
        <tr>
            <td class="lbl">FECHA:</td>  <td class="val"><?= h($jornada['fecha'] ?? '') ?></td>
            <td class="lbl">HORA:</td>   <td class="val"><?= $p['hora'] ? h(substr($p['hora'], 0, 5)) : '' ?></td>
        </tr>
        <tr>
            <td class="lbl">ÁRBITRO:</td>    <td class="val"><?= h($p['arbitro_nombre'] ?? '') ?></td>
            <td class="lbl">CANCHA:</td>     <td class="val"><?= h($p['cancha'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="lbl">ASISTENTE 1:</td> <td class="val"><?= h($p['arbitro2_nombre'] ?? '') ?></td>
            <td class="lbl">ASISTENTE 2:</td> <td class="val"><?= h($p['arbitro3_nombre'] ?? '') ?></td>
        </tr>
    </table>

    <table class="acta-equipos">
        <tr>
            <td class="equipo-label">EQUIPO 1</td>
            <td class="equipo-nombre"><?= h($p['local_nombre']) ?> <span style="font-size:7.5pt;font-weight:400;color:#666;">(<?= count($jugL) ?>)</span></td>
            <td class="marcador-box"><?= $p['goles_local'] !== null ? (int) $p['goles_local'] : '' ?></td>
            <td class="separador"></td>
            <td class="equipo-label">EQUIPO 2</td>
            <td class="equipo-nombre"><?= h($p['visita_nombre']) ?> <span style="font-size:7.5pt;font-weight:400;color:#666;">(<?= count($jugV) ?>)</span></td>
            <td class="marcador-box"><?= $p['goles_visita'] !== null ? (int) $p['goles_visita'] : '' ?></td>
        </tr>
    </table>

    <table class="acta-jugadores">
        <thead>
            <tr>
                <th class="col-no">No.</th><th>Nombres</th><th class="col-stat">G</th><th class="col-stat">A</th><th class="col-stat">R</th>
                <th class="col-sep"></th>
                <th class="col-no">No.</th><th>Nombres</th><th class="col-stat">G</th><th class="col-stat">A</th><th class="col-stat">R</th>
            </tr>
        </thead>
        <tbody>
        <?php for ($i = 0; $i < $total; $i++):
            $jl = $jugL[$i] ?? null; $jv = $jugV[$i] ?? null;
            $sl = $jl ? ($stats[$jl['id']] ?? []) : [];
            $sv = $jv ? ($stats[$jv['id']] ?? []) : [];
        ?>
            <tr>
                <td class="col-no"><?= $jl ? (int) $jl['numero'] : '' ?></td>
                <td>
                    <?= $jl ? h($jl['nombre']) : '' ?>
                    <?php if ($jl && !empty($suspendidos[$jl['id']])): ?>
                        <span style="color:#c00;font-size:6pt;font-weight:700;display:block;">(con tarjeta roja)</span>
                    <?php endif; ?>
                </td>
                <td class="col-stat"><?= !empty($sl['g']) ? $sl['g'] : '' ?></td>
                <td class="col-stat"><?= !empty($sl['a']) ? $sl['a'] : '' ?></td>
                <td class="col-stat"><?= !empty($sl['r']) ? $sl['r'] : '' ?></td>
                <td class="col-sep"></td>
                <td class="col-no"><?= $jv ? (int) $jv['numero'] : '' ?></td>
                <td>
                    <?= $jv ? h($jv['nombre']) : '' ?>
                    <?php if ($jv && !empty($suspendidos[$jv['id']])): ?>
                        <span style="color:#c00;font-size:6pt;font-weight:700;display:block;">(con tarjeta roja)</span>
                    <?php endif; ?>
                </td>
                <td class="col-stat"><?= !empty($sv['g']) ? $sv['g'] : '' ?></td>
                <td class="col-stat"><?= !empty($sv['a']) ? $sv['a'] : '' ?></td>
                <td class="col-stat"><?= !empty($sv['r']) ? $sv['r'] : '' ?></td>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>

    <div class="acta-obs" style="margin-top:6px;">
        <div class="obs-label">Observaciones</div>
        <div class="obs-lines">
            <?php if (!empty($p['observaciones'])): ?>
                <div class="obs-text"><?= nl2br(h($p['observaciones'])) ?></div>
            <?php else: ?>
                <div class="obs-line"></div><div class="obs-line"></div>
                <div class="obs-line"></div><div class="obs-line"></div>
            <?php endif; ?>
        </div>
    </div>

    <table class="acta-firmas">
        <tr>
            <td><div class="firma-espacio"></div></td>
            <td><div class="firma-espacio"></div></td>
            <td><div class="firma-espacio"></div></td>
            <td><div class="firma-espacio"></div></td>
            <td><div class="firma-espacio"></div></td>
        </tr>
        <tr>
            <td><div class="firma-label">Árbitro</div><?php if (!empty($p['arbitro_nombre'])): ?><div class="firma-nombre"><?= h($p['arbitro_nombre']) ?></div><?php endif; ?></td>
            <td><div class="firma-label">Capitán Equipo 1</div><div class="firma-nombre"><?= h($p['local_nombre']) ?></div></td>
            <td><div class="firma-label">Capitán Equipo 2</div><div class="firma-nombre"><?= h($p['visita_nombre']) ?></div></td>
            <td><div class="firma-label">Encargado Equipo 1</div><?php if (!empty($p['local_delegado'])): ?><div class="firma-nombre"><?= h($p['local_delegado']) ?></div><?php endif; ?></td>
            <td><div class="firma-label">Encargado Equipo 2</div><?php if (!empty($p['visita_delegado'])): ?><div class="firma-nombre"><?= h($p['visita_delegado']) ?></div><?php endif; ?></td>
        </tr>
    </table>

    <div class="acta-footer">Toda la información del campeonato se encuentra en: <?= h($urlPublica) ?></div>

</div>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
