<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

$id = (int) ($_GET['id'] ?? 0);
$jugador = null;
$equipo  = null;

if ($torneo && $id) {
    $jugador = $db->queryOne(
        "SELECT j.*, e.nombre AS equipo_nombre, e.id AS equipo_id,
                e.color_hex, e.logo_url AS equipo_logo, e.abreviatura
         FROM jugadores j
         JOIN equipos e ON e.id = j.equipo_id
         WHERE j.id = ? AND j.activo = 1 AND e.torneo_id = ?",
        [$id, $torneo['id']]
    );
}

if (!$jugador) {
    set_flash('error', 'Jugador no encontrado.');
    redirect('/public/equipos.php');
}

// Estadísticas del jugador en este torneo
$goles = (int) ($db->queryOne(
    "SELECT COUNT(*) AS c FROM goles g
     JOIN partidos p ON p.id = g.partido_id
     WHERE g.jugador_id = ? AND p.torneo_id = ? AND g.tipo != 'autogol'",
    [$id, $torneo['id']]
)['c'] ?? 0);

$amarillas = (int) ($db->queryOne(
    "SELECT COUNT(*) AS c FROM tarjetas t
     JOIN partidos p ON p.id = t.partido_id
     WHERE t.jugador_id = ? AND p.torneo_id = ? AND t.tipo IN ('amarilla','doble_amarilla')",
    [$id, $torneo['id']]
)['c'] ?? 0);

$rojas = (int) ($db->queryOne(
    "SELECT COUNT(*) AS c FROM tarjetas t
     JOIN partidos p ON p.id = t.partido_id
     WHERE t.jugador_id = ? AND p.torneo_id = ? AND t.tipo IN ('roja','doble_amarilla')",
    [$id, $torneo['id']]
)['c'] ?? 0);

$pageTitle = $jugador['nombre'];
$layout = 'public';
require __DIR__ . '/../views/layout/header.php';
?>
<section class="section">
    <div class="container" style="max-width:640px;">
        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/public/equipo.php?id=<?= (int) $jugador['equipo_id'] ?>">← Volver a <?= h($jugador['equipo_nombre']) ?></a>

        <div style="display:flex;gap:28px;align-items:flex-start;margin-top:24px;flex-wrap:wrap;">

            <!-- Foto -->
            <div style="flex-shrink:0;">
                <?php if (!empty($jugador['foto_url'])): ?>
                    <img src="<?= h($jugador['foto_url']) ?>" alt="<?= h($jugador['nombre']) ?>"
                         style="width:140px;height:140px;object-fit:cover;border-radius:var(--radius);box-shadow:var(--shadow);">
                <?php else: ?>
                    <div style="width:140px;height:140px;border-radius:var(--radius);background:var(--color-light);
                                display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow);">
                        <span class="ms" style="font-size:4rem;color:var(--color-gray);">person</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px;">
                    <span style="background:var(--color-primary);color:var(--color-dark);border-radius:var(--radius);
                                 padding:4px 14px;font-size:1.4rem;font-weight:900;">#<?= (int) $jugador['numero'] ?></span>
                    <h1 style="margin:0;font-size:1.5rem;font-weight:900;"><?= h($jugador['nombre']) ?></h1>
                </div>

                <p style="color:var(--color-gray);margin-bottom:6px;">
                    <span class="ms" style="vertical-align:-4px;font-size:1rem;">sports_soccer</span>
                    <?= h($jugador['posicion']) ?>
                </p>

                <a href="<?= BASE_URL ?>/public/equipo.php?id=<?= (int) $jugador['equipo_id'] ?>"
                   style="display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:inherit;
                          background:var(--color-light);border-radius:var(--radius);padding:6px 12px;margin-bottom:20px;">
                    <?= team_badge($jugador['equipo_nombre'], $jugador['abreviatura'], $jugador['color_hex'], $jugador['equipo_logo'], 28) ?>
                    <span style="font-weight:700;"><?= h($jugador['equipo_nombre']) ?></span>
                </a>

                <!-- Estadísticas -->
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);
                                padding:12px 20px;text-align:center;min-width:70px;">
                        <div style="font-size:1.8rem;font-weight:900;line-height:1;"><?= $goles ?></div>
                        <div style="font-size:0.75rem;color:var(--color-gray);margin-top:4px;">Goles</div>
                    </div>
                    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);
                                padding:12px 20px;text-align:center;min-width:70px;">
                        <div style="font-size:1.8rem;font-weight:900;line-height:1;">🟡</div>
                        <div style="font-size:1rem;font-weight:900;"><?= $amarillas ?></div>
                        <div style="font-size:0.75rem;color:var(--color-gray);margin-top:2px;">Amarillas</div>
                    </div>
                    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);
                                padding:12px 20px;text-align:center;min-width:70px;">
                        <div style="font-size:1.8rem;font-weight:900;line-height:1;">🔴</div>
                        <div style="font-size:1rem;font-weight:900;"><?= $rojas ?></div>
                        <div style="font-size:0.75rem;color:var(--color-gray);margin-top:2px;">Rojas</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
