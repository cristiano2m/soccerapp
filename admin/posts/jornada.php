<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer']);

$db     = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) redirect('/admin/dashboard.php');

$jornadas = $db->query(
    "SELECT * FROM jornadas WHERE torneo_id = ? ORDER BY numero ASC",
    [(int) $torneo['id']]
);

$jornada_id = (int) ($_GET['jornada_id'] ?? ($jornadas[0]['id'] ?? 0));
$jornada    = $jornada_id
    ? $db->queryOne("SELECT * FROM jornadas WHERE id = ? AND torneo_id = ?", [$jornada_id, $torneo['id']])
    : null;

$partidos = $jornada ? $db->query(
    "SELECT p.*,
            el.nombre AS local_nombre,  el.logo_url AS local_logo,  el.color_hex AS local_color,
            ev.nombre AS visita_nombre, ev.logo_url AS visita_logo, ev.color_hex AS visita_color
     FROM   partidos p
     JOIN   equipos el ON el.id = p.equipo_local_id
     JOIN   equipos ev ON ev.id = p.equipo_visita_id
     WHERE  p.jornada_id = ?
     ORDER  BY p.hora ASC, p.id ASC",
    [(int) $jornada['id']]
) : [];

function fmt_hora(?string $t): string {
    if (!$t) return '—';
    [$h, $m] = explode(':', $t);
    $h = (int) $h;
    return ($h % 12 ?: 12) . ':' . $m . ' ' . ($h >= 12 ? 'PM' : 'AM');
}

function initials(string $name): string {
    $w = preg_split('/\s+/', strtoupper(trim($name)));
    return count($w) >= 2 ? $w[0][0] . $w[1][0] : substr($w[0], 0, 2);
}

$accent = $torneo['color_primario'] ?? '#FFD600';

// Contrast: is the accent color dark or light? Simple luminance check
[$r, $g, $b] = sscanf($accent, '#%02x%02x%02x');
$lum         = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
$onAccent    = $lum > 0.5 ? '#000000' : '#ffffff';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Post Jornada <?= $jornada ? (int) $jornada['numero'] : '' ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, Helvetica, sans-serif;
    background: #0f1923;
    padding: 24px;
    min-height: 100vh;
}

/* ── toolbar ── */
.toolbar {
    max-width: 820px;
    margin: 0 auto 18px;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.toolbar select {
    padding: 9px 14px;
    border-radius: 6px;
    border: none;
    font-size: 0.9rem;
    font-weight: 600;
    flex: 1;
    min-width: 180px;
    cursor: pointer;
}
.btn-dl {
    padding: 9px 20px;
    border-radius: 6px;
    border: none;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    background: <?= h($accent) ?>;
    color: <?= h($onAccent) ?>;
    white-space: nowrap;
}
.btn-back {
    padding: 9px 16px;
    border-radius: 6px;
    background: rgba(255,255,255,0.08);
    color: #ccc;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    white-space: nowrap;
}
.btn-back:hover { background: rgba(255,255,255,0.14); }

/* ── post card ── */
#post-card {
    width: 800px;
    margin: 0 auto;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 24px 80px rgba(0,0,0,0.7);
}

/* ── header ── */
.ph {
    background: linear-gradient(150deg, #071526 0%, #0e2d5a 45%, #081f3f 100%);
    padding: 22px 28px 18px;
    display: flex;
    align-items: center;
    gap: 18px;
    position: relative;
    overflow: hidden;
}
/* dot grid overlay */
.ph::before {
    content: '';
    position: absolute; inset: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,0.045) 1px, transparent 1px);
    background-size: 22px 22px;
    pointer-events: none;
}
/* glow top-right */
.ph::after {
    content: '';
    position: absolute;
    top: -40px; right: 60px;
    width: 220px; height: 280px;
    background: radial-gradient(ellipse, <?= h($accent) ?>22 0%, transparent 65%);
    pointer-events: none;
}

.ph-logo {
    flex-shrink: 0;
    z-index: 1;
}
.ph-logo img {
    width: 82px; height: 82px;
    object-fit: contain;
    filter: drop-shadow(0 2px 10px rgba(0,0,0,0.6));
}
.ph-logo .logo-fb {
    width: 82px; height: 82px;
    background: <?= h($accent) ?>;
    color: <?= h($onAccent) ?>;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.4rem; font-weight: 900;
    border: 3px solid rgba(255,255,255,0.25);
    box-shadow: 0 4px 16px rgba(0,0,0,0.4);
}

.ph-center {
    flex: 1;
    text-align: center;
    z-index: 1;
}
.ph-temporada {
    font-size: 2.05rem;
    font-weight: 900;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1.1;
    text-shadow: 0 2px 10px rgba(0,0,0,0.6);
}
.ph-temporada em {
    font-style: normal;
    color: <?= h($accent) ?>;
}
.ph-ribbon {
    display: inline-block;
    background: linear-gradient(90deg, transparent 0%, <?= h($accent) ?> 18%, <?= h($accent) ?> 82%, transparent 100%);
    color: <?= h($onAccent) ?>;
    font-size: 0.88rem;
    font-weight: 900;
    letter-spacing: 0.14em;
    padding: 4px 36px;
    margin: 8px 0 10px;
    text-transform: uppercase;
}
.ph-fecha {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(0,0,0,0.45);
    border: 1px solid <?= h($accent) ?>66;
    color: #fff;
    font-size: 0.92rem;
    font-weight: 700;
    padding: 5px 18px;
    border-radius: 22px;
    letter-spacing: 0.06em;
}

.ph-deco {
    flex-shrink: 0;
    text-align: center;
    z-index: 1;
    line-height: 1;
}
.ph-deco-tag {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    color: rgba(255,255,255,0.55);
    text-transform: uppercase;
    display: block;
    margin-bottom: 2px;
}
.ph-deco-num {
    font-size: 5rem;
    font-weight: 900;
    color: #fff;
    text-shadow: 0 4px 20px rgba(0,0,0,0.7), 0 0 40px <?= h($accent) ?>44;
    display: block;
    line-height: 1;
}
.ph-deco-ball {
    font-size: 1.6rem;
    display: block;
    margin-top: 4px;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,0.5));
}

/* ── table ── */
.pt {
    background: #fff;
}
.pt table {
    width: 100%;
    border-collapse: collapse;
}
.pt thead th {
    background: <?= h($accent) ?>;
    color: <?= h($onAccent) ?>;
    font-size: 0.78rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    padding: 10px 8px;
    text-align: center;
}
.pt thead .th-equipo { text-align: left; padding-left: 12px; }

.pt tbody tr:nth-child(odd)  { background: #ffffff; }
.pt tbody tr:nth-child(even) { background: #f4f7fb; }
.pt tbody tr:hover           { background: <?= h($accent) ?>18; }

.pt td { padding: 7px 8px; vertical-align: middle; }

.td-hora {
    width: 95px;
    text-align: center;
    font-weight: 800;
    font-size: 0.82rem;
    color: #0e2d5a;
    background: <?= h($accent) ?>22 !important;
}
.td-hora-inner { display: flex; align-items: center; justify-content: center; gap: 4px; }

.td-cancha {
    width: 82px;
    text-align: center;
    font-size: 0.76rem;
    color: #555;
}
.td-cancha-inner { display: flex; align-items: center; justify-content: center; gap: 3px; }

.td-equipo { width: 238px; }
.eq-inner { display: flex; align-items: center; gap: 8px; }

.team-logo {
    width: 30px; height: 30px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
    box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}
.team-init {
    width: 30px; height: 30px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.6rem; font-weight: 900; color: #fff;
    flex-shrink: 0;
    box-shadow: 0 1px 4px rgba(0,0,0,0.25);
}
.team-name {
    font-weight: 700;
    font-size: 0.8rem;
    color: #0e1f36;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    line-height: 1.2;
}

.td-vs { width: 44px; text-align: center; }
.vs-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px;
    background: <?= h($accent) ?>;
    color: <?= h($onAccent) ?>;
    border-radius: 50%;
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.04em;
}

/* ── address ── */
.post-address {
    background: <?= h($accent) ?>;
    color: <?= h($onAccent) ?>;
    text-align: center;
    padding: 10px 16px;
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    display: flex; align-items: center; justify-content: center; gap: 7px;
}

/* ── sponsors ── */
.post-sponsors {
    background: #fff;
    padding: 10px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    min-height: 54px;
    border-top: 1px solid #e8ecf0;
}
.sp-img { height: 34px; object-fit: contain; }
.sp-ph {
    height: 32px; padding: 0 14px;
    background: #f0f2f5; border-radius: 4px;
    display: flex; align-items: center;
    font-size: 0.68rem; font-weight: 700; color: #aaa;
    text-transform: uppercase; letter-spacing: 0.07em;
}
</style>
</head>
<body>

<div class="toolbar">
    <a href="<?= BASE_URL ?>/admin/resultados/index.php" class="btn-back">← Volver</a>
    <form method="get" style="display:flex;flex:1;">
        <select name="jornada_id" onchange="this.form.submit()">
            <?php foreach ($jornadas as $j): ?>
                <option value="<?= (int) $j['id'] ?>" <?= (int) $j['id'] === $jornada_id ? 'selected' : '' ?>>
                    Jornada <?= (int) $j['numero'] ?> — <?= h($j['fecha']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <button class="btn-dl" onclick="descargar()">📥 Descargar PNG</button>
    <?php if ($jornada): ?>
    <button class="btn-dl" id="btn-ia" style="background:#6c47d4;color:#fff;" onclick="abrirIA()">✨ Texto para redes</button>
    <?php endif; ?>
</div>

<!-- Panel Claude IA -->
<?php if ($jornada): ?>
<div id="ia-panel" style="display:none;max-width:820px;margin:16px auto 0;background:#1e2c3a;border-radius:10px;padding:20px;border:1px solid #6c47d440;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <h3 style="color:#fff;font-size:0.95rem;font-weight:700;display:flex;align-items:center;gap:8px;">
            ✨ Generador de texto IA <span style="font-size:0.72rem;background:#6c47d4;padding:2px 8px;border-radius:10px;">Claude AI</span>
        </h3>
        <button onclick="document.getElementById('ia-panel').style.display='none'" style="background:none;border:none;color:#aaa;font-size:1.2rem;cursor:pointer;">✕</button>
    </div>
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
        <button class="ia-tipo-btn active" data-tipo="proxima_fecha" onclick="setTipo(this)">📅 Próxima fecha</button>
        <button class="ia-tipo-btn" data-tipo="resultados" onclick="setTipo(this)">🏆 Resultados</button>
        <button class="ia-tipo-btn" data-tipo="posiciones" onclick="setTipo(this)">📊 Posiciones</button>
    </div>
    <button id="btn-generar" onclick="generarCaption()" style="background:#6c47d4;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-size:0.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;margin-bottom:14px;">
        <span id="ia-spinner" style="display:none;">⏳</span> Generar con Claude
    </button>
    <div id="ia-error" style="display:none;background:#3a1c1c;color:#ff8a80;padding:10px 14px;border-radius:6px;font-size:0.83rem;margin-bottom:12px;"></div>
    <div id="ia-result" style="display:none;">
        <textarea id="ia-text" rows="8" style="width:100%;background:#0f1923;color:#e0e0e0;border:1px solid #334;border-radius:6px;padding:12px;font-size:0.88rem;resize:vertical;font-family:inherit;line-height:1.6;"></textarea>
        <div style="display:flex;gap:8px;margin-top:8px;">
            <button onclick="copiarCaption()" style="background:#26a69a;color:#fff;border:none;padding:7px 14px;border-radius:6px;font-size:0.82rem;font-weight:600;cursor:pointer;">📋 Copiar</button>
            <button onclick="generarCaption()" style="background:rgba(255,255,255,0.1);color:#ccc;border:none;padding:7px 14px;border-radius:6px;font-size:0.82rem;cursor:pointer;">🔄 Regenerar</button>
        </div>
    </div>
</div>
<style>
.ia-tipo-btn {
    padding: 6px 14px;
    border-radius: 20px;
    border: 1px solid #334;
    background: transparent;
    color: #aaa;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all .2s;
}
.ia-tipo-btn.active, .ia-tipo-btn:hover {
    background: #6c47d4;
    color: #fff;
    border-color: #6c47d4;
}
</style>
<script>
var iaTipo = 'proxima_fecha';
function setTipo(btn) {
    document.querySelectorAll('.ia-tipo-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    iaTipo = btn.dataset.tipo;
}
function abrirIA() {
    var p = document.getElementById('ia-panel');
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
function generarCaption() {
    var btn = document.getElementById('btn-generar');
    var spinner = document.getElementById('ia-spinner');
    var errDiv = document.getElementById('ia-error');
    var resDiv = document.getElementById('ia-result');
    btn.disabled = true;
    spinner.style.display = 'inline';
    errDiv.style.display = 'none';
    resDiv.style.display = 'none';
    var fd = new FormData();
    fd.append('jornada_id', '<?= (int) $jornada_id ?>');
    fd.append('tipo', iaTipo);
    fetch('<?= BASE_URL ?>/admin/ajax/claude-caption.php', { method: 'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.error) {
            errDiv.textContent = d.error;
            errDiv.style.display = 'block';
        } else {
            document.getElementById('ia-text').value = d.caption;
            resDiv.style.display = 'block';
        }
    })
    .catch(function(e){
        errDiv.textContent = 'Error de red: ' + e.message;
        errDiv.style.display = 'block';
    })
    .finally(function(){
        btn.disabled = false;
        spinner.style.display = 'none';
    });
}
function copiarCaption() {
    var txt = document.getElementById('ia-text').value;
    navigator.clipboard.writeText(txt).then(function(){
        var btn = event.target;
        btn.textContent = '✓ Copiado!';
        setTimeout(function(){ btn.textContent = '📋 Copiar'; }, 2000);
    });
}
</script>
<?php endif; ?>

<div id="post-card">

    <!-- ENCABEZADO -->
    <div class="ph">
        <div class="ph-logo">
            <?php if (!empty($torneo['logo_url'])): ?>
                <img src="<?= h($torneo['logo_url']) ?>" alt="<?= h($torneo['nombre']) ?>">
            <?php else: ?>
                <div class="logo-fb">⚽</div>
            <?php endif; ?>
        </div>

        <div class="ph-center">
            <div class="ph-temporada">
                TEMPORADA <em><?= (int) $torneo['anio'] ?></em>
            </div>
            <div class="ph-ribbon">
                ━ JORNADA <?= $jornada ? (int) $jornada['numero'] : '' ?> ━
            </div>
            <div class="ph-fecha">
                📅 <?= $jornada ? h($jornada['fecha']) : '' ?>
            </div>
        </div>

        <div class="ph-deco">
            <span class="ph-deco-tag"><?= h(strtoupper(substr($torneo['nombre'], 0, 4))) ?></span>
            <span class="ph-deco-num"><?= $jornada ? (int) $jornada['numero'] : '' ?></span>
            <span class="ph-deco-ball">⚽</span>
        </div>
    </div>

    <!-- TABLA -->
    <div class="pt">
        <table>
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Cancha</th>
                    <th class="th-equipo">Equipo 1</th>
                    <th>VS</th>
                    <th class="th-equipo">Equipo 2</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($partidos)): ?>
                <tr>
                    <td colspan="5" style="text-align:center;padding:28px;color:#aaa;font-size:0.88rem;">
                        No hay partidos registrados en esta jornada.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($partidos as $p):
                    $lc = $p['local_color']  ?: '#1a3a5c';
                    $vc = $p['visita_color'] ?: '#c0392b';
                ?>
                <tr>
                    <td class="td-hora">
                        <div class="td-hora-inner">
                            🕐 <?= fmt_hora($p['hora'] ? substr($p['hora'], 0, 5) : null) ?>
                        </div>
                    </td>
                    <td class="td-cancha">
                        <div class="td-cancha-inner">
                            🏟 <?= h($p['cancha'] ?: '—') ?>
                        </div>
                    </td>
                    <td class="td-equipo">
                        <div class="eq-inner">
                            <?php if ($p['local_logo']): ?>
                                <img class="team-logo" src="<?= h($p['local_logo']) ?>" alt="">
                            <?php else: ?>
                                <div class="team-init" style="background:<?= h($lc) ?>;"><?= initials($p['local_nombre']) ?></div>
                            <?php endif; ?>
                            <span class="team-name"><?= h($p['local_nombre']) ?></span>
                        </div>
                    </td>
                    <td class="td-vs">
                        <div class="vs-badge">VS</div>
                    </td>
                    <td class="td-equipo">
                        <div class="eq-inner">
                            <?php if ($p['visita_logo']): ?>
                                <img class="team-logo" src="<?= h($p['visita_logo']) ?>" alt="">
                            <?php else: ?>
                                <div class="team-init" style="background:<?= h($vc) ?>;"><?= initials($p['visita_nombre']) ?></div>
                            <?php endif; ?>
                            <span class="team-name"><?= h($p['visita_nombre']) ?></span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- DIRECCIÓN -->
    <?php if (!empty($torneo['cancha_principal'])): ?>
    <div class="post-address">
        📍 CAMPOS UBICADOS EN: <?= h($torneo['cancha_principal']) ?>
    </div>
    <?php endif; ?>

    <!-- SPONSORS -->
    <div class="post-sponsors">
        <div class="sp-ph">Patrocinador 1</div>
        <div class="sp-ph">Patrocinador 2</div>
        <div class="sp-ph">Patrocinador 3</div>
        <div class="sp-ph">Patrocinador 4</div>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function descargar() {
    var btn = document.querySelector('.btn-dl');
    btn.textContent = '⏳ Generando...';
    btn.disabled = true;
    html2canvas(document.getElementById('post-card'), {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        logging: false
    }).then(function(canvas) {
        var a = document.createElement('a');
        a.download = 'jornada-<?= $jornada ? (int)$jornada['numero'] : 'post' ?>.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
        btn.textContent = '📥 Descargar PNG';
        btn.disabled = false;
    });
}
</script>
</body>
</html>
