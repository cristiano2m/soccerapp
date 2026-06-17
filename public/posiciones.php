<?php
require_once __DIR__ . '/../config/bootstrap.php';

$torneo = obtener_torneo_activo();
$posiciones = $torneo ? calcular_posiciones($torneo['id']) : [];

$pageTitle = 'Tabla de posiciones';
$layout = 'public';
require __DIR__ . '/../views/layout/header.php';
?>
<section class="section">
    <div class="container">
        <h1 class="section-title"><span class="ms ms-lg">leaderboard</span> Tabla de posiciones</h1>
        <?php if (!$torneo): ?>
            <p class="text-muted">No hay un torneo activo.</p>
        <?php else: ?>
            <?php require __DIR__ . '/../views/components/tabla-posiciones.php'; ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
