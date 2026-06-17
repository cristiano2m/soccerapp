<?php
require_once __DIR__ . '/../config/bootstrap.php';

$db = Database::getInstance();
$torneo = obtener_torneo_activo();
$resultados = $torneo ? obtener_ultimos_resultados($torneo['id'], 50) : [];

$pageTitle = 'Resultados';
$layout = 'public';
require __DIR__ . '/../views/layout/header.php';
?>
<section class="section">
    <div class="container">
        <h1 class="section-title"><span class="ms ms-lg">sports_soccer</span> Resultados</h1>
        <?php if (!$torneo || empty($resultados)): ?>
            <p class="text-muted">Todavía no hay resultados registrados.</p>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($resultados as $r): ?>
                    <?php $partido = $r; require __DIR__ . '/../views/components/partido-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
