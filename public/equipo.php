<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

$id = (int) ($_GET['id'] ?? 0);
$equipo = null;
$jugadores = [];

if ($torneo) {
    $equipo = $db->queryOne("SELECT * FROM equipos WHERE id = ? AND torneo_id = ? AND activo = 1", [$id, $torneo['id']]);
    if ($equipo) {
        $jugadores = $db->query(
            "SELECT id, nombre, numero, foto_url FROM jugadores WHERE equipo_id = ? AND activo = 1 ORDER BY numero ASC",
            [$equipo['id']]
        );
    }
}

if (!$equipo) {
    set_flash('error', 'Equipo no encontrado.');
    redirect('/public/equipos.php');
}

$pageTitle = 'Nómina · ' . $equipo['nombre'];
$layout = 'public';
require __DIR__ . '/../views/layout/header.php';
?>
<section class="section">
    <div class="container">
        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/public/equipos.php">← Volver a equipos</a>
        <h1 class="section-title" style="margin-top:16px;">
            <?= team_badge($equipo['nombre'], $equipo['abreviatura'], $equipo['color_hex'], $equipo['logo_url'], 48) ?>
            <?= h($equipo['nombre']) ?>
        </h1>
        <?php if (!empty($equipo['delegado'])): ?>
            <p class="text-muted">Delegado: <?= h($equipo['delegado']) ?></p>
        <?php endif; ?>

        <h2 class="section-title" style="margin-top:24px;"><span class="ms">person</span> Nómina (<?= count($jugadores) ?>)</h2>
        <?php if (empty($jugadores)): ?>
            <p class="text-muted">Este equipo todavía no tiene jugadores registrados.</p>
        <?php else: ?>
            <div class="grid grid-4">
                <?php foreach ($jugadores as $j): ?>
                <a class="player-card" href="<?= BASE_URL ?>/public/jugador.php?id=<?= (int) $j['id'] ?>" style="text-decoration:none;color:inherit;display:block;">
                    <?= jugador_avatar($j['foto_url'], $j['nombre']) ?>
                    <span class="player-numero"><?= (int) $j['numero'] ?></span>
                    <p class="player-nombre"><?= h($j['nombre']) ?></p>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
