<?php
require_once __DIR__ . '/config/bootstrap.php';

$db = Database::getInstance();

// ── Sin torneo específico: mostrar directorio de torneos activos ───────────
if (empty($_GET['t'])) {
    $torneosActivos = $db->query(
        "SELECT * FROM torneos WHERE estado = 'activo' ORDER BY id DESC"
    );
    $torneo          = null;
    $noTorneoContext = true;   // impide que header.php llame obtener_torneo_activo()
    $pageTitle       = APP_NAME;
    $layout          = 'public';

    // Estadísticas globales para el hero
    $totalEquipos   = (int) ($db->queryOne("SELECT COUNT(*) AS c FROM equipos WHERE activo = 1")['c'] ?? 0);
    $totalPartidos  = (int) ($db->queryOne("SELECT COUNT(*) AS c FROM partidos WHERE estado = 'finalizado'")['c'] ?? 0);

    require __DIR__ . '/views/layout/header.php';
    ?>

    <!-- ══════════════════════════════════════════════════════════
         HERO — Fondo foto configurable
         Para cambiar la imagen: edita la URL en lp-hero-bg style
    ══════════════════════════════════════════════════════════ -->
    <section class="lp-hero">
        <!-- IMAGEN DE FONDO: reemplaza la URL por tu foto de estadio/equipo -->
        <div class="lp-hero-bg" style="background-image: url('<?= BASE_URL ?>/assets/img/header.jpg');"></div>
        <div class="lp-hero-overlay"></div>

        <div class="lp-hero-content">
            <img src="<?= BASE_URL ?>/assets/img/logoSoccerApp.png" alt="SoccerAPP" class="lp-hero-logo">
            <h1 class="lp-hero-title">
                La plataforma de<br>
                <span class="lp-hero-accent-word">torneos deportivos</span><br>
                de tu comunidad
            </h1>
            <p class="lp-hero-subtitle">
                Resultados en tiempo real · Tabla de posiciones · Estadísticas de jugadores
            </p>
            <div class="lp-hero-ctas">
                <a href="#campeonatos" class="lp-btn-primary">
                    <span class="ms">sports_soccer</span> Ver campeonatos
                </a>
                <?php if (!is_logged_in()): ?>
                <a href="<?= BASE_URL ?>/login.php" class="lp-btn-secondary">
                    <span class="ms">login</span> Acceder al panel
                </a>
                <?php else: ?>
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="lp-btn-secondary">
                    <span class="ms">dashboard</span> Panel Admin
                </a>
                <?php endif; ?>
            </div>
        </div>

        <a href="#stats" class="lp-hero-scroll" aria-label="Bajar">
            <span class="ms" style="font-size:28px;">expand_more</span>
        </a>
    </section>

    <!-- ══ BARRA DE ESTADÍSTICAS ══ -->
    <div class="lp-stats-bar" id="stats">
        <div class="container">
            <div class="lp-stats-grid">
                <div class="lp-stat">
                    <strong><?= count($torneosActivos) ?></strong>
                    <span>Campeonatos activos</span>
                </div>
                <div class="lp-stat-div"></div>
                <div class="lp-stat">
                    <strong><?= $totalEquipos ?></strong>
                    <span>Equipos registrados</span>
                </div>
                <div class="lp-stat-div"></div>
                <div class="lp-stat">
                    <strong><?= $totalPartidos ?></strong>
                    <span>Partidos jugados</span>
                </div>
                <div class="lp-stat-div"></div>
                <div class="lp-stat">
                    <strong>100%</strong>
                    <span>Resultados en tiempo real</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ FEATURES ══ -->
    <section class="lp-features">
        <div class="container">
            <div class="lp-section-header">
                <span class="lp-eyebrow">¿Qué ofrece SoccerAPP?</span>
                <h2 class="lp-section-title">Todo lo que necesitas para<br>gestionar tu torneo</h2>
            </div>
            <div class="lp-features-grid">
                <div class="lp-feature">
                    <div class="lp-feature-icon"><span class="ms">calendar_month</span></div>
                    <h3>Organiza jornadas</h3>
                    <p>Genera el calendario round-robin automáticamente. Programa fechas, canchas y horarios para cada partido.</p>
                </div>
                <div class="lp-feature lp-feature--accent">
                    <div class="lp-feature-icon"><span class="ms">sports_soccer</span></div>
                    <h3>Registra resultados</h3>
                    <p>Carga goles, tarjetas y eventos de cada partido en tiempo real directamente desde el campo.</p>
                </div>
                <div class="lp-feature">
                    <div class="lp-feature-icon"><span class="ms">leaderboard</span></div>
                    <h3>Estadísticas completas</h3>
                    <p>Tabla de posiciones, goleadores, tarjetas y rendimiento por equipo siempre actualizados.</p>
                </div>
                <div class="lp-feature">
                    <div class="lp-feature-icon"><span class="ms">groups</span></div>
                    <h3>Gestión de equipos</h3>
                    <p>Administra nóminas, fotos de jugadores y datos de cada equipo participante en el torneo.</p>
                </div>
                <div class="lp-feature">
                    <div class="lp-feature-icon"><span class="ms">stadium</span></div>
                    <h3>Pantalla de estadio</h3>
                    <p>Proyecta el marcador en pantalla grande con actualización automática durante los partidos en vivo.</p>
                </div>
                <div class="lp-feature">
                    <div class="lp-feature-icon"><span class="ms">assignment</span></div>
                    <h3>Actas digitales</h3>
                    <p>Genera e imprime el acta oficial de cada partido con todos los eventos registrados.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ CAMPEONATOS ACTIVOS ══ -->
    <section class="lp-torneos" id="campeonatos">
        <div class="container">
            <div class="lp-section-header">
                <span class="lp-eyebrow">Temporada actual</span>
                <h2 class="lp-section-title">Campeonatos en curso</h2>
            </div>

            <?php if (empty($torneosActivos)): ?>
                <div class="lp-empty">
                    <span class="ms" style="font-size:56px;opacity:0.15;">emoji_events</span>
                    <p>No hay campeonatos activos en este momento.</p>
                </div>
            <?php else: ?>
            <div class="torneos-grid">
                <?php foreach ($torneosActivos as $t):
                    $urlT    = url_publica_torneo((int) $t['id']);
                    $color   = !empty($t['color_primario']) ? $t['color_primario'] : '#1a3a5c';
                    $letra   = mb_strtoupper(mb_substr($t['nombre'], 0, 1));
                    $hasLogo = !empty($t['logo_url']);
                    $eqCount = (int) ($db->queryOne(
                        "SELECT COUNT(*) AS c FROM equipos WHERE torneo_id = ? AND activo = 1",
                        [(int) $t['id']]
                    )['c'] ?? 0);
                    $ptCount = (int) ($db->queryOne(
                        "SELECT COUNT(*) AS c FROM partidos WHERE torneo_id = ? AND estado = 'finalizado'",
                        [(int) $t['id']]
                    )['c'] ?? 0);
                ?>
                <a class="torneo-card" href="<?= h($urlT) ?>">
                    <?php if ($hasLogo): ?>
                    <div class="torneo-card-bg has-img"
                         style="background-image:url('<?= h($t['logo_url']) ?>');background-color:<?= h($color) ?>;"></div>
                    <?php else: ?>
                    <div class="torneo-card-bg"
                         style="background:linear-gradient(145deg,<?= h($color) ?> 0%,<?= h($color) ?>bb 100%);">
                        <span class="torneo-card-letra"><?= h($letra) ?></span>
                    </div>
                    <?php endif; ?>
                    <span class="torneo-card-badge">Activo</span>
                    <span class="torneo-card-arrow ms" style="font-size:18px;color:#fff;">arrow_forward</span>
                    <div class="torneo-card-info">
                        <?php if ($hasLogo): ?>
                        <div style="margin-bottom:8px;">
                            <img src="<?= h($t['logo_url']) ?>" alt=""
                                 style="height:38px;width:38px;object-fit:contain;filter:drop-shadow(0 2px 8px rgba(0,0,0,0.6));">
                        </div>
                        <?php endif; ?>
                        <div class="torneo-card-nombre"><?= h($t['nombre']) ?></div>
                        <?php if (!empty($t['categoria']) || !empty($t['anio'])): ?>
                        <div class="torneo-card-meta">
                            <?= h($t['categoria'] ?? '') ?><?= !empty($t['anio']) ? ' · ' . h((string) $t['anio']) : '' ?>
                        </div>
                        <?php endif; ?>
                        <div class="torneo-card-chips">
                            <?php if ($eqCount > 0): ?>
                            <span class="torneo-chip"><span class="ms" style="font-size:10px;">groups</span> <?= $eqCount ?> equipos</span>
                            <?php endif; ?>
                            <?php if ($ptCount > 0): ?>
                            <span class="torneo-chip"><span class="ms" style="font-size:10px;">sports_soccer</span> <?= $ptCount ?> partidos</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════════════════
         GALERÍA / BANNER FOTO
         Cambia las URLs de background-image por tus propias fotos
    ══════════════════════════════════════════════════════════ -->
    <section class="lp-gallery">
        <!-- FOTO GRANDE (izquierda): cambia la URL por tu imagen -->
        <div class="lp-gallery-main"
             style="background-image: url('<?= BASE_URL ?>/assets/img/landing.jpg');">
            <div class="lp-gallery-overlay"></div>
            <div class="lp-gallery-text">
                <span class="lp-eyebrow" style="color:var(--color-primary);">Nuestros torneos</span>
                <h3>Competencia de alto nivel<br>para toda la comunidad</h3>
                <a href="#campeonatos" class="lp-btn-primary" style="margin-top:16px;">Ver campeonatos</a>
            </div>
        </div>
        <!-- FOTOS PEQUEÑAS (derecha): cambia las URLs por tus imágenes -->
        <div class="lp-gallery-aside">
            <div class="lp-gallery-thumb"
                 style="background-image: url('<?= BASE_URL ?>/assets/img/home.jpg');">
                <div class="lp-gallery-overlay"></div>
                <div class="lp-gallery-thumb-label">
                    <span class="ms" style="font-size:14px;">groups</span> Equipos
                </div>
            </div>
            <div class="lp-gallery-thumb"
                 style="background-image: url('<?= BASE_URL ?>/assets/img/header.jpg');">
                <div class="lp-gallery-overlay"></div>
                <div class="lp-gallery-thumb-label">
                    <span class="ms" style="font-size:14px;">sports_soccer</span> Partidos
                </div>
            </div>
        </div>
    </section>

    <?php
    require __DIR__ . '/views/layout/footer.php';
    exit;
}

$torneo = obtener_torneo_activo();

$equipos = [];
$posiciones = [];
$proximaJornada = null;
$ultimosResultados = [];
$goleadores = [];
$stats = ['equipos' => 0, 'jornadas' => 0, 'partidos' => 0];

if ($torneo) {
    $equipos = $db->query("SELECT * FROM equipos WHERE torneo_id = ? AND activo = 1 ORDER BY nombre ASC", [$torneo['id']]);
    $posiciones = calcular_posiciones($torneo['id']);
    $proximaJornada = obtener_proxima_jornada($torneo['id']);
    $ultimosResultados = obtener_ultimos_resultados($torneo['id'], 3);
    $goleadores = obtener_goleadores($torneo['id'], 5);

    $stats['equipos'] = count($equipos);
    $stats['jornadas'] = (int) ($db->queryOne("SELECT COUNT(*) AS c FROM jornadas WHERE torneo_id = ?", [$torneo['id']])['c'] ?? 0);
    $stats['partidos'] = (int) ($db->queryOne("SELECT COUNT(*) AS c FROM partidos WHERE torneo_id = ? AND estado = 'finalizado'", [$torneo['id']])['c'] ?? 0);
}

$pageTitle = 'Inicio';
$layout = 'public';
require __DIR__ . '/views/layout/header.php';
?>

<?php
    $heroColor = !empty($torneo['color_primario']) ? $torneo['color_primario'] : '#FFD600';
?>
<section class="torneo-hero" style="--torneo-color: <?= h($heroColor) ?>;">
    <div class="torneo-hero-overlay"></div>
    <div class="container torneo-hero-inner">

        <?php if ($torneo): ?>
        <!-- Logo del campeonato -->
        <div class="torneo-hero-logo-wrap">
            <?php if (!empty($torneo['logo_url'])): ?>
                <img src="<?= h($torneo['logo_url']) ?>" alt="<?= h($torneo['nombre']) ?>" class="torneo-hero-logo">
            <?php else: ?>
                <div class="torneo-hero-logo-placeholder">
                    <?= h(mb_strtoupper(mb_substr($torneo['nombre'], 0, 2))) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Texto -->
        <div class="torneo-hero-text">
            <span class="torneo-hero-badge">
                <?= h($torneo['categoria'] ?? 'Torneo') ?>
                <?php if (!empty($torneo['anio'])): ?> · <?= h((string) $torneo['anio']) ?><?php endif; ?>
            </span>
            <h1 class="torneo-hero-title"><?= h($torneo['nombre']) ?></h1>
            <?php if (!empty($torneo['descripcion'])): ?>
                <p class="torneo-hero-desc"><?= h($torneo['descripcion']) ?></p>
            <?php endif; ?>
            <div class="torneo-hero-actions">
                <a href="<?= BASE_URL ?>/public/posiciones.php?t=<?= (int)$torneo['id'] ?>" class="lp-btn-primary" style="background:var(--torneo-color);">
                    <span class="ms">leaderboard</span> Posiciones
                </a>
                <a href="<?= BASE_URL ?>/public/calendario.php?t=<?= (int)$torneo['id'] ?>" class="lp-btn-secondary">
                    <span class="ms">calendar_month</span> Calendario
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="torneo-hero-stats">
            <div class="torneo-stat">
                <strong><?= $stats['equipos'] ?></strong>
                <span>Equipos</span>
            </div>
            <div class="torneo-stat">
                <strong><?= $stats['jornadas'] ?></strong>
                <span>Jornadas</span>
            </div>
            <div class="torneo-stat">
                <strong><?= $stats['partidos'] ?></strong>
                <span>Jugados</span>
            </div>
        </div>

        <?php else: ?>
        <div class="torneo-hero-text">
            <h1 class="torneo-hero-title"><?= APP_NAME ?></h1>
            <p class="torneo-hero-desc">Aún no hay un torneo configurado.</p>
        </div>
        <?php endif; ?>

    </div>
</section>

<?php if ($torneo): ?>

<?php if ($proximaJornada): ?>
<section class="section">
    <div class="container">
        <h2 class="section-title"><span class="ms">calendar_month</span> Próxima jornada — Jornada <?= (int) $proximaJornada['numero'] ?></h2>
        <div class="grid grid-3">
            <?php foreach ($proximaJornada['partidos'] as $p): $p['jornada_numero'] = $proximaJornada['numero']; ?>
                <?php $partido = $p; require __DIR__ . '/views/components/partido-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section section-light">
    <div class="container">
        <div class="grid grid-2">
            <div>
                <h2 class="section-title"><span class="ms">leaderboard</span> Tabla de posiciones</h2>
                <?php $limit = 8; require __DIR__ . '/views/components/tabla-posiciones.php'; ?>
                <p style="margin-top:12px;"><a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/public/posiciones.php">Ver tabla completa</a></p>
            </div>
            <div>
                <h2 class="section-title"><span class="ms">bolt</span> Goleadores</h2>
                <div class="card">
                    <?php if (empty($goleadores)): ?>
                        <p class="text-muted">Todavía no hay goles registrados.</p>
                    <?php else: ?>
                        <?php foreach ($goleadores as $i => $g): ?>
                        <div class="scorer-row">
                            <div class="team-row">
                                <span class="scorer-rank"><?= $i + 1 ?></span>
                                <?= team_badge($g['equipo_nombre'], $g['abreviatura'], $g['color_hex'], null, 28) ?>
                                <div>
                                    <div><?= h($g['jugador_nombre']) ?></div>
                                    <div class="text-muted" style="font-size:0.8rem;"><?= h($g['equipo_nombre']) ?></div>
                                </div>
                            </div>
                            <span class="scorer-goals"><?= (int) $g['goles'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($ultimosResultados)): ?>
<section class="section">
    <div class="container">
        <h2 class="section-title"><span class="ms">sports_soccer</span> Últimos resultados</h2>
        <div class="grid grid-3">
            <?php foreach ($ultimosResultados as $r): ?>
                <?php $partido = $r; require __DIR__ . '/views/components/partido-card.php'; ?>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:16px;"><a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/public/resultados.php">Ver todos los resultados</a></p>
    </div>
</section>
<?php endif; ?>

<section class="section section-light">
    <div class="container">
        <h2 class="section-title"><span class="ms">groups</span> Equipos</h2>
        <div class="grid grid-4">
            <?php foreach ($equipos as $eq): ?>
            <div class="team-card">
                <?= team_badge($eq['nombre'], $eq['abreviatura'], $eq['color_hex'], $eq['logo_url'], 64) ?>
                <h3><?= h($eq['nombre']) ?></h3>
                <?php if (!empty($eq['delegado'])): ?><p class="text-muted" style="font-size:0.85rem;">Delegado: <?= h($eq['delegado']) ?></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:16px;"><a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/public/equipos.php">Ver todos los equipos</a></p>
        <p style="margin-top:8px;"><a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/public/calendario.php">Ver calendario completo</a></p>
    </div>
</section>

<?php
// ── Patrocinadores ─────────────────────────────────────────────────────────
$patrocinadores = $db->query(
    "SELECT * FROM patrocinadores WHERE torneo_id = ? AND activo = 1 ORDER BY orden ASC, id ASC",
    [$torneo['id']]
);
if (!empty($patrocinadores)):
?>
<section class="section sponsors-section">
    <div class="container">
        <h2 class="section-title" style="text-align:center;margin-bottom:6px;">
            <span class="ms">verified</span> Patrocinadores
        </h2>
        <p style="text-align:center;color:var(--color-gray);font-size:0.88rem;margin-bottom:32px;">
            Gracias a nuestros auspiciantes por hacer posible este campeonato
        </p>
        <div class="sponsors-grid">
            <?php foreach ($patrocinadores as $sp): ?>
            <div class="sponsor-card">
                <?php if (!empty($sp['url_sitio'])): ?>
                    <a href="<?= h($sp['url_sitio']) ?>" target="_blank" rel="noopener noreferrer" class="sponsor-link" title="<?= h($sp['nombre']) ?>">
                <?php else: ?>
                    <div class="sponsor-link" title="<?= h($sp['nombre']) ?>">
                <?php endif; ?>
                    <?php if (!empty($sp['logo_url'])): ?>
                        <img src="<?= h($sp['logo_url']) ?>" alt="<?= h($sp['nombre']) ?>" class="sponsor-logo">
                    <?php else: ?>
                        <span class="sponsor-nombre"><?= h($sp['nombre']) ?></span>
                    <?php endif; ?>
                <?php if (!empty($sp['url_sitio'])): ?>
                    </a>
                <?php else: ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/views/layout/footer.php'; ?>
