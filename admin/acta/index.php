<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer', 'referee']);

$db     = Database::getInstance();
$torneo = obtener_torneo_activo();

$id      = (int) ($_GET['id'] ?? 0);
$partido = $db->queryOne(
    "SELECT p.*, j.numero AS jornada_numero, j.fecha AS jornada_fecha,
            el.id AS local_id, el.nombre AS local_nombre, el.delegado AS local_delegado,
            ev.id AS visita_id, ev.nombre AS visita_nombre, ev.delegado AS visita_delegado,
            r.goles_local, r.goles_visita, r.wo_local, r.wo_visita, r.observaciones,
            a.nombre  AS arbitro_nombre,
            a2.nombre AS arbitro2_nombre,
            a3.nombre AS arbitro3_nombre
     FROM partidos p
     JOIN jornadas j  ON j.id  = p.jornada_id
     JOIN equipos  el ON el.id = p.equipo_local_id
     JOIN equipos  ev ON ev.id = p.equipo_visita_id
     LEFT JOIN resultados r ON r.partido_id = p.id
     LEFT JOIN usuarios a  ON a.id  = p.arbitro_id
     LEFT JOIN usuarios a2 ON a2.id = p.arbitro_id2
     LEFT JOIN usuarios a3 ON a3.id = p.arbitro_id3
     WHERE p.id = ?",
    [$id]
);

if (!$partido) {
    set_flash('error', 'Partido no encontrado.');
    redirect('/admin/resultados/index.php');
}

// Los árbitros solo pueden ver el acta de partidos finalizados
$rolActivo = $_SESSION['torneo_rol'] ?? $_SESSION['rol'] ?? '';
if ($rolActivo === 'referee' && $partido['estado'] !== 'finalizado') {
    set_flash('error', 'El acta solo está disponible cuando el partido ha finalizado.');
    redirect('/admin/arbitro/dashboard.php');
}

// Jugadores de cada equipo
$jugadoresLocal  = $db->query("SELECT * FROM jugadores WHERE equipo_id = ? ORDER BY numero ASC", [$partido['local_id']]);
$jugadoresVisita = $db->query("SELECT * FROM jugadores WHERE equipo_id = ? ORDER BY numero ASC", [$partido['visita_id']]);

// Estadísticas G/A/R por jugador
$golesPartido    = $db->query("SELECT jugador_id, tipo FROM goles   WHERE partido_id = ?", [$id]);
$tarjetasPartido = $db->query("SELECT jugador_id, tipo FROM tarjetas WHERE partido_id = ?", [$id]);

$statsJugador = [];
foreach ($golesPartido as $g) {
    if ($g['tipo'] !== 'autogol') {
        $statsJugador[$g['jugador_id']]['g'] = ($statsJugador[$g['jugador_id']]['g'] ?? 0) + 1;
    }
}
foreach ($tarjetasPartido as $t) {
    $jid = $t['jugador_id'];
    if (in_array($t['tipo'], ['amarilla', 'doble_amarilla'], true)) {
        $statsJugador[$jid]['a'] = ($statsJugador[$jid]['a'] ?? 0) + 1;
    }
    if (in_array($t['tipo'], ['roja', 'doble_amarilla'], true)) {
        $statsJugador[$jid]['r'] = ($statsJugador[$jid]['r'] ?? 0) + 1;
    }
}

$urlPublica   = url_publica_torneo((int) $torneo['id']);
$MIN_FILAS    = 20; // mínimo de filas en la tabla de jugadores
$filasLocal   = max($MIN_FILAS, count($jugadoresLocal));
$filasVisita  = max($MIN_FILAS, count($jugadoresVisita));
$totalFilas   = max($filasLocal, $filasVisita);

$pageTitle = 'Acta del partido';
$layout    = 'admin';
require __DIR__ . '/../../views/layout/header.php';

$rolActivo = $_SESSION['torneo_rol'] ?? $_SESSION['rol'] ?? '';
if ($rolActivo === 'referee') {
    require __DIR__ . '/../../views/layout/sidebar-arbitro.php';
} else {
    require __DIR__ . '/../../views/layout/sidebar-admin.php';
}
?>
<div class="toolbar no-print">
    <h1>📄 Acta del partido</h1>
    <div class="actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Imprimir</button>
        <a class="btn btn-outline" href="<?= BASE_URL ?><?= $rolActivo === 'referee' ? '/admin/arbitro/dashboard.php' : '/admin/resultados/index.php' ?>">← Volver</a>
    </div>
</div>

<style>
/* ── Acta imprimible ─────────────────────────────── */
.acta {
    font-family: Arial, sans-serif;
    font-size: 9pt;
    color: #000;
    max-width: 780px;
    margin: 0 auto;
    background: #fff;
}

/* Encabezado */
.acta-header {
    display: grid;
    grid-template-columns: 70px 1fr 100px;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.acta-header-logo img {
    width: 64px;
    height: 64px;
    object-fit: contain;
}
.acta-header-logo .logo-placeholder {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    border: 1px solid #ccc;
}
.acta-header-title {
    text-align: center;
}
.acta-header-title h1 {
    font-size: 15pt;
    font-weight: 900;
    margin: 0 0 2px;
    text-transform: uppercase;
}
.acta-jornada-box {
    border: 2px solid #000;
    text-align: center;
    padding: 4px;
}
.acta-jornada-box .label {
    font-size: 7pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.acta-jornada-box .numero {
    font-size: 28pt;
    font-weight: 900;
    line-height: 1;
}

/* Tabla de info */
.acta-info {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 6px;
}
.acta-info td {
    border: 1px solid #000;
    padding: 3px 6px;
    font-size: 8.5pt;
}
.acta-info .lbl {
    font-weight: 700;
    white-space: nowrap;
    background: #f5f5f5;
    width: 90px;
}
.acta-info .val {
    width: 200px;
}

/* Cabecera de equipos */
.acta-equipos {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 0;
}
.acta-equipos td {
    border: 1px solid #000;
    padding: 3px 6px;
}
.acta-equipos .equipo-label {
    font-size: 7pt;
    font-weight: 700;
    text-transform: uppercase;
    width: 55px;
    background: #f5f5f5;
}
.acta-equipos .equipo-nombre {
    font-size: 13pt;
    font-weight: 900;
    text-transform: uppercase;
}
.acta-equipos .marcador-box {
    width: 38px;
    height: 28px;
    border: 2px solid #000;
    text-align: center;
    font-size: 14pt;
    font-weight: 900;
    vertical-align: middle;
}
.acta-equipos .separador {
    width: 10px;
    border: none;
    background: #fff;
}

/* Tabla de jugadores */
.acta-jugadores {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0;
}
.acta-jugadores th {
    border: 1px solid #000;
    padding: 2px 3px;
    font-size: 7.5pt;
    font-weight: 700;
    text-align: left;
    background: #f5f5f5;
}
.acta-jugadores td {
    border: 1px solid #000;
    padding: 1px 3px;
    font-size: 7.5pt;
    height: 16px;
}
.acta-jugadores .col-no   { width: 26px; text-align: center; }
.acta-jugadores .col-stat { width: 18px; text-align: center; }
.acta-jugadores .col-sep  { width: 8px;  border-left: 2px solid #000; border-right: 2px solid #000; background: #fff; padding: 0; }
.acta-jugadores .col-nombre { /* flexible */ }

/* Observaciones */
.acta-obs {
    margin-top: 6px;
}
.acta-obs .obs-label {
    font-size: 8pt;
    font-weight: 700;
    text-transform: uppercase;
    border: 1px solid #000;
    border-bottom: none;
    padding: 2px 6px;
    background: #f5f5f5;
}
.acta-obs .obs-lines {
    border: 1px solid #000;
}
.acta-obs .obs-line {
    border-bottom: 1px solid #ccc;
    height: 18px;
    padding: 0 6px;
}
.acta-obs .obs-line:last-child {
    border-bottom: none;
}
.acta-obs .obs-text {
    padding: 4px 6px;
    font-size: 8pt;
    white-space: pre-wrap;
}

/* Firmas */
.acta-firmas {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
}
.acta-firmas td {
    border: 1px solid #000;
    text-align: center;
    padding: 0;
}
.acta-firmas .firma-espacio {
    height: 38px;
    border-bottom: 1px solid #000;
}
.acta-firmas .firma-label {
    font-size: 7pt;
    font-weight: 700;
    text-transform: uppercase;
    padding: 3px 4px;
}
.acta-firmas .firma-nombre {
    font-size: 6.5pt;
    color: #444;
    padding: 1px 4px 3px;
    border-top: 1px solid #ddd;
}

/* Pie de página */
.acta-footer {
    margin-top: 6px;
    font-size: 7pt;
    color: #555;
    text-align: center;
}

/* Ocultar en pantalla lo que es solo para impresión y viceversa */
@media print {
    .no-print { display: none !important; }
    .admin-sidebar { display: none !important; }
    .admin-topbar  { display: none !important; }
    .admin-content { padding: 0 !important; }
    .admin-main    { padding: 0 !important; }
    body { background: #fff; }
    .acta { max-width: 100%; margin: 0; }
    @page { margin: 10mm; size: A4 portrait; }
}
</style>

<div class="acta">

    <!-- ENCABEZADO -->
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
            <div class="numero"><?= (int) $partido['jornada_numero'] ?></div>
        </div>
    </div>

    <!-- INFO PARTIDO -->
    <table class="acta-info">
        <tr>
            <td class="lbl">FECHA:</td>
            <td class="val"><?= h($partido['jornada_fecha'] ?? '') ?></td>
            <td class="lbl">HORA:</td>
            <td class="val"><?= $partido['hora'] ? h(substr($partido['hora'], 0, 5)) : '' ?></td>
        </tr>
        <tr>
            <td class="lbl">ÁRBITRO:</td>
            <td class="val"><?= h($partido['arbitro_nombre'] ?? '') ?></td>
            <td class="lbl">CANCHA:</td>
            <td class="val"><?= h($partido['cancha'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="lbl">ASISTENTE 1:</td>
            <td class="val"><?= h($partido['arbitro2_nombre'] ?? '') ?></td>
            <td class="lbl">ASISTENTE 2:</td>
            <td class="val"><?= h($partido['arbitro3_nombre'] ?? '') ?></td>
        </tr>
    </table>

    <!-- EQUIPOS Y MARCADOR -->
    <table class="acta-equipos">
        <tr>
            <td class="equipo-label">EQUIPO 1</td>
            <td class="equipo-nombre"><?= h($partido['local_nombre']) ?></td>
            <td class="marcador-box">
                <?= $partido['goles_local'] !== null ? (int) $partido['goles_local'] : '' ?>
            </td>
            <td class="separador"></td>
            <td class="equipo-label">EQUIPO 2</td>
            <td class="equipo-nombre"><?= h($partido['visita_nombre']) ?></td>
            <td class="marcador-box">
                <?= $partido['goles_visita'] !== null ? (int) $partido['goles_visita'] : '' ?>
            </td>
        </tr>
    </table>

    <!-- JUGADORES (dos columnas, G/A/R inline) -->
    <table class="acta-jugadores">
        <thead>
            <tr>
                <th class="col-no">No.</th>
                <th class="col-nombre">Nombres</th>
                <th class="col-stat">G</th>
                <th class="col-stat">A</th>
                <th class="col-stat">R</th>
                <th class="col-sep"></th>
                <th class="col-no">No.</th>
                <th class="col-nombre">Nombres</th>
                <th class="col-stat">G</th>
                <th class="col-stat">A</th>
                <th class="col-stat">R</th>
            </tr>
        </thead>
        <tbody>
        <?php for ($i = 0; $i < $totalFilas; $i++):
            $jl = $jugadoresLocal[$i]  ?? null;
            $jv = $jugadoresVisita[$i] ?? null;
            $sl = $jl ? ($statsJugador[$jl['id']] ?? []) : [];
            $sv = $jv ? ($statsJugador[$jv['id']] ?? []) : [];
        ?>
            <tr>
                <td class="col-no"><?= $jl ? (int) $jl['numero'] : '' ?></td>
                <td class="col-nombre"><?= $jl ? h($jl['nombre']) : '' ?></td>
                <td class="col-stat"><?= !empty($sl['g']) ? $sl['g'] : '' ?></td>
                <td class="col-stat"><?= !empty($sl['a']) ? $sl['a'] : '' ?></td>
                <td class="col-stat"><?= !empty($sl['r']) ? $sl['r'] : '' ?></td>
                <td class="col-sep"></td>
                <td class="col-no"><?= $jv ? (int) $jv['numero'] : '' ?></td>
                <td class="col-nombre"><?= $jv ? h($jv['nombre']) : '' ?></td>
                <td class="col-stat"><?= !empty($sv['g']) ? $sv['g'] : '' ?></td>
                <td class="col-stat"><?= !empty($sv['a']) ? $sv['a'] : '' ?></td>
                <td class="col-stat"><?= !empty($sv['r']) ? $sv['r'] : '' ?></td>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>

    <!-- OBSERVACIONES -->
    <div class="acta-obs">
        <div class="obs-label">Observaciones</div>
        <div class="obs-lines">
            <?php if (!empty($partido['observaciones'])): ?>
                <div class="obs-text"><?= nl2br(h($partido['observaciones'])) ?></div>
            <?php else: ?>
                <div class="obs-line"></div>
                <div class="obs-line"></div>
                <div class="obs-line"></div>
                <div class="obs-line"></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FIRMAS -->
    <table class="acta-firmas">
        <tr>
            <td><div class="firma-espacio"></div></td>
            <td><div class="firma-espacio"></div></td>
            <td><div class="firma-espacio"></div></td>
            <td><div class="firma-espacio"></div></td>
            <td><div class="firma-espacio"></div></td>
        </tr>
        <tr>
            <td>
                <div class="firma-label">Árbitro</div>
                <?php if (!empty($partido['arbitro_nombre'])): ?>
                    <div class="firma-nombre"><?= h($partido['arbitro_nombre']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <div class="firma-label">Capitán Equipo 1</div>
                <div class="firma-nombre"><?= h($partido['local_nombre']) ?></div>
            </td>
            <td>
                <div class="firma-label">Capitán Equipo 2</div>
                <div class="firma-nombre"><?= h($partido['visita_nombre']) ?></div>
            </td>
            <td>
                <div class="firma-label">Encargado Equipo 1</div>
                <?php if (!empty($partido['local_delegado'])): ?>
                    <div class="firma-nombre"><?= h($partido['local_delegado']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <div class="firma-label">Encargado Equipo 2</div>
                <?php if (!empty($partido['visita_delegado'])): ?>
                    <div class="firma-nombre"><?= h($partido['visita_delegado']) ?></div>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- PIE DE PÁGINA -->
    <div class="acta-footer">
        Toda la información del campeonato se encuentra en: <?= h($urlPublica) ?>
    </div>

</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
