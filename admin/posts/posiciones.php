<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer']);

$db     = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) redirect('/admin/dashboard.php');

$posiciones     = calcular_posiciones((int) $torneo['id']);
$patrocinadores = $db->query(
    "SELECT * FROM patrocinadores WHERE torneo_id = ? AND activo = 1 ORDER BY orden ASC, id ASC",
    [(int) $torneo['id']]
);

// Última jornada jugada
$ultimaJornada = $db->queryOne(
    "SELECT j.numero FROM jornadas j
     JOIN partidos p ON p.jornada_id = j.id
     WHERE j.torneo_id = ? AND p.estado = 'finalizado'
     ORDER BY j.numero DESC LIMIT 1",
    [(int) $torneo['id']]
);

$accent   = $torneo['color_primario'] ?? '#FFD600';
[$r, $g, $b] = sscanf($accent, '#%02x%02x%02x');
$lum      = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
$onAccent = $lum > 0.5 ? '#000000' : '#ffffff';

function initials_p(string $name): string {
    $w = preg_split('/\s+/', strtoupper(trim($name)));
    return count($w) >= 2 ? $w[0][0] . $w[1][0] : substr($w[0], 0, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Post Posiciones</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; background: #0f1923; padding: 24px; min-height: 100vh; }

.toolbar { max-width: 820px; margin: 0 auto 18px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.btn-dl { padding: 9px 20px; border-radius: 6px; border: none; font-size: 0.9rem; font-weight: 700; cursor: pointer; background: <?= h($accent) ?>; color: <?= h($onAccent) ?>; white-space: nowrap; }
.btn-back { padding: 9px 16px; border-radius: 6px; background: rgba(255,255,255,0.08); color: #ccc; text-decoration: none; font-size: 0.88rem; font-weight: 600; white-space: nowrap; }
.btn-back:hover { background: rgba(255,255,255,0.14); }

#post-card { width: 800px; margin: 0 auto; border-radius: 14px; overflow: hidden; box-shadow: 0 24px 80px rgba(0,0,0,0.7); }

/* ── header ── */
.ph { background: linear-gradient(150deg, #071526 0%, #0e2d5a 45%, #081f3f 100%); padding: 22px 28px 18px; display: flex; align-items: center; gap: 18px; position: relative; overflow: hidden; }
.ph::before { content: ''; position: absolute; inset: 0; background-image: radial-gradient(circle, rgba(255,255,255,0.045) 1px, transparent 1px); background-size: 22px 22px; pointer-events: none; }
.ph::after { content: ''; position: absolute; top: -40px; right: 60px; width: 220px; height: 280px; background: radial-gradient(ellipse, <?= h($accent) ?>22 0%, transparent 65%); pointer-events: none; }

.ph-logo { flex-shrink: 0; z-index: 1; }
.ph-logo img { width: 82px; height: 82px; object-fit: contain; filter: drop-shadow(0 2px 10px rgba(0,0,0,0.6)); }
.ph-logo .logo-fb { width: 82px; height: 82px; background: <?= h($accent) ?>; color: <?= h($onAccent) ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.4rem; font-weight: 900; border: 3px solid rgba(255,255,255,0.25); box-shadow: 0 4px 16px rgba(0,0,0,0.4); }

.ph-center { flex: 1; text-align: center; z-index: 1; }
.ph-temporada { font-size: 2.05rem; font-weight: 900; color: #fff; text-transform: uppercase; letter-spacing: 0.04em; line-height: 1.1; text-shadow: 0 2px 10px rgba(0,0,0,0.6); }
.ph-temporada em { font-style: normal; color: <?= h($accent) ?>; }
.ph-ribbon { display: inline-block; background: linear-gradient(90deg, transparent 0%, <?= h($accent) ?> 18%, <?= h($accent) ?> 82%, transparent 100%); color: <?= h($onAccent) ?>; font-size: 0.88rem; font-weight: 900; letter-spacing: 0.14em; padding: 4px 36px; margin: 8px 0 10px; text-transform: uppercase; }
.ph-fecha { display: inline-flex; align-items: center; gap: 7px; background: rgba(0,0,0,0.45); border: 1px solid <?= h($accent) ?>66; color: #fff; font-size: 0.92rem; font-weight: 700; padding: 5px 18px; border-radius: 22px; letter-spacing: 0.06em; }

.ph-deco { flex-shrink: 0; text-align: center; z-index: 1; line-height: 1; }
.ph-deco-tag { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.18em; color: rgba(255,255,255,0.55); text-transform: uppercase; display: block; margin-bottom: 2px; }
.ph-deco-icon { font-size: 4.5rem; display: block; line-height: 1; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.5)); }

/* ── tabla posiciones ── */
.pp { background: #fff; }
.pp-head { display: grid; grid-template-columns: 36px 1fr 42px 42px 42px 42px 42px 42px 52px; align-items: center; padding: 8px 14px; background: <?= h($accent) ?>; gap: 2px; }
.pp-head span { font-size: 0.72rem; font-weight: 900; color: <?= h($onAccent) ?>; text-align: center; text-transform: uppercase; letter-spacing: 0.05em; }
.pp-head span:nth-child(2) { text-align: left; }

.pp-row { display: grid; grid-template-columns: 36px 1fr 42px 42px 42px 42px 42px 42px 52px; align-items: center; padding: 7px 14px; border-bottom: 1px solid #f0f3f8; gap: 2px; }
.pp-row:last-child { border-bottom: none; }
.pp-row:nth-child(even) { background: #f8fafc; }
.pp-row.top1 { background: <?= h($accent) ?>18; }
.pp-row.top3 { background: #f0f4ff; }

.pp-pos { font-size: 0.82rem; font-weight: 800; color: #bbb; text-align: center; }
.pp-row.top1 .pp-pos { color: <?= h($accent) ?>; font-size: 1rem; }
.pp-row.top3 .pp-pos { color: #5c7cfa; }

.pp-team { display: flex; align-items: center; gap: 8px; }
.pp-logo { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.pp-init { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 900; color: #fff; flex-shrink: 0; }
.pp-name { font-weight: 700; font-size: 0.82rem; color: #0e1f36; text-transform: uppercase; letter-spacing: 0.02em; }

.pp-stat { font-size: 0.82rem; color: #444; text-align: center; }
.pp-pts { font-size: 0.92rem; font-weight: 900; color: #0e1f36; text-align: center; background: <?= h($accent) ?>22; border-radius: 6px; padding: 3px 0; }
.pp-row.top1 .pp-pts { background: <?= h($accent) ?>; color: <?= h($onAccent) ?>; }

/* ── address / sponsors ── */
.post-address { background: <?= h($accent) ?>; color: <?= h($onAccent) ?>; text-align: center; padding: 10px 16px; font-size: 0.8rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 7px; }
.post-address .ms { font-family: 'Material Symbols Outlined'; font-style: normal; font-size: 18px; }

.post-sponsors { background: #ffffff; padding: 10px 20px 14px; display: flex; flex-direction: column; gap: 10px; border-top: 3px solid <?= h($accent) ?>; }
.post-sponsors-label { display: flex; align-items: center; gap: 6px; font-size: 0.65rem; font-weight: 900; letter-spacing: 0.16em; text-transform: uppercase; color: #555; }
.post-sponsors-label::after { content: ''; flex: 1; height: 1px; background: #e0e0e0; margin-left: 6px; }
.post-sponsors-label .ms { font-family: 'Material Symbols Outlined'; font-style: normal; font-size: 16px; color: <?= h($accent) ?>; }
.post-sponsors-grid { display: flex; align-items: center; gap: 14px; width: 100%; }
.sp-card { flex: 1; display: flex; align-items: center; justify-content: center; min-width: 0; }
.sp-img { width: 100%; min-height: 100px; max-height: 100px; object-fit: contain; }
.sp-ph { flex: 1; min-width: 0; height: 100px; background: #f5f5f5; border: 1px dashed #ccc; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 700; color: #bbb; text-transform: uppercase; }

.post-footer-bar { background: <?= h($accent) ?>; color: <?= h($onAccent) ?>; text-align: center; padding: 5px; font-size: 0.65rem; font-weight: 900; letter-spacing: 0.14em; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 5px; }
.post-footer-bar .ms { font-family: 'Material Symbols Outlined'; font-style: normal; font-size: 13px; }
</style>
</head>
<body>

<div class="toolbar">
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn-back">← Volver</a>
    <button class="btn-dl" onclick="descargar()">📥 Descargar PNG</button>
</div>

<div id="post-card">

    <!-- HEADER -->
    <div class="ph">
        <div class="ph-logo">
            <?php if (!empty($torneo['logo_url'])): ?>
                <img src="<?= h($torneo['logo_url']) ?>" alt="">
            <?php else: ?>
                <div class="logo-fb">⚽</div>
            <?php endif; ?>
        </div>
        <div class="ph-center">
            <div class="ph-temporada">TABLA DE <em>POSICIONES</em></div>
            <div class="ph-ribbon">━ <?= h(strtoupper($torneo['nombre'])) ?> ━</div>
            <div class="ph-fecha">
                📅 Temporada <?= (int) $torneo['anio'] ?>
                <?php if ($ultimaJornada): ?>
                  · Tras jornada <?= (int) $ultimaJornada['numero'] ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="ph-deco">
            <span class="ph-deco-tag">Ranking</span>
            <span class="ph-deco-icon">🏆</span>
        </div>
    </div>

    <!-- POSICIONES -->
    <div class="pp">
        <div class="pp-head">
            <span>#</span>
            <span>Equipo</span>
            <span>PJ</span>
            <span>PG</span>
            <span>PE</span>
            <span>PP</span>
            <span>GD</span>
            <span>GF</span>
            <span>Pts</span>
        </div>
        <?php if (empty($posiciones)): ?>
            <div style="padding:28px;text-align:center;color:#aaa;font-size:0.88rem;">Sin resultados registrados aún.</div>
        <?php else: ?>
            <?php foreach ($posiciones as $i => $e):
                $pos = $i + 1;
                $cls = $pos === 1 ? 'top1' : ($pos <= 3 ? 'top3' : '');
                $dg  = (int)$e['gf'] - (int)$e['gc'];
            ?>
            <div class="pp-row <?= $cls ?>">
                <span class="pp-pos"><?= $pos ?></span>
                <div class="pp-team">
                    <?php if (!empty($e['logo_url'])): ?>
                        <img class="pp-logo" src="<?= h($e['logo_url']) ?>" alt="">
                    <?php else: ?>
                        <div class="pp-init" style="background:<?= h($e['color_hex'] ?: '#1a3a5c') ?>;"><?= initials_p($e['nombre']) ?></div>
                    <?php endif; ?>
                    <span class="pp-name"><?= h($e['nombre']) ?></span>
                </div>
                <span class="pp-stat"><?= (int)$e['pj'] ?></span>
                <span class="pp-stat"><?= (int)$e['pg'] ?></span>
                <span class="pp-stat"><?= (int)$e['pe'] ?></span>
                <span class="pp-stat"><?= (int)$e['pp'] ?></span>
                <span class="pp-stat"><?= ($dg >= 0 ? '+' : '') . $dg ?></span>
                <span class="pp-stat"><?= (int)$e['gf'] ?></span>
                <span class="pp-pts"><?= (int)$e['pts'] ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- DIRECCIÓN -->
    <?php if (!empty($torneo['cancha_principal'])): ?>
    <div class="post-address">
        <span class="ms">location_on</span> <?= h($torneo['cancha_principal']) ?>
    </div>
    <?php endif; ?>

    <!-- AUSPICIANTES -->
    <div class="post-sponsors">
        <div class="post-sponsors-label">
            <span class="ms">verified</span> Auspiciantes
        </div>
        <div class="post-sponsors-grid">
            <?php if (!empty($patrocinadores)): ?>
                <?php foreach ($patrocinadores as $sp): ?>
                <div class="sp-card">
                    <?php if (!empty($sp['logo_url'])): ?>
                        <img class="sp-img" src="<?= h($sp['logo_url']) ?>" alt="<?= h($sp['nombre']) ?>">
                    <?php else: ?>
                        <div class="sp-ph"><?= h(mb_strtoupper(mb_substr($sp['nombre'], 0, 10))) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="sp-ph">Sponsor 1</div>
                <div class="sp-ph">Sponsor 2</div>
                <div class="sp-ph">Sponsor 3</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="post-footer-bar">
        <span class="ms">leaderboard</span>
        <?= h(strtoupper($torneo['nombre'])) ?> · <?= (int) $torneo['anio'] ?>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function descargar() {
    var btn = document.querySelector('.btn-dl');
    btn.textContent = '⏳ Generando...';
    btn.disabled = true;
    html2canvas(document.getElementById('post-card'), { scale: 2, useCORS: true, allowTaint: true, logging: false }).then(function(canvas) {
        var a = document.createElement('a');
        a.download = 'posiciones-<?= (int) $torneo['anio'] ?>.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
        btn.textContent = '📥 Descargar PNG';
        btn.disabled = false;
    });
}
</script>
</body>
</html>
