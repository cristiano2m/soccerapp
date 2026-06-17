<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['referee']);

$db     = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) redirect('/admin/dashboard.php');

$pageTitle = 'Información del torneo';
$layout    = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-arbitro.php';
?>
<h1><span class="ms ms-lg">emoji_events</span> <?= h($torneo['nombre']) ?></h1>

<div class="grid grid-2" style="margin-top:18px;">
    <div class="card">
        <?php if (!empty($torneo['logo_url'])): ?>
            <div style="text-align:center;margin-bottom:18px;">
                <img src="<?= h($torneo['logo_url']) ?>" alt="Logo" style="width:100px;height:100px;object-fit:cover;border-radius:50%;">
            </div>
        <?php endif; ?>

        <table class="table-simple" style="width:100%;border-collapse:collapse;">
            <?php
            $filas = [
                'Nombre'     => $torneo['nombre'],
                'Año'        => $torneo['anio'] ?? '—',
                'Categoría'  => $torneo['categoria'] ?? '—',
                'Estado'     => ucfirst($torneo['estado'] ?? '—'),
                'Cancha ppal'=> $torneo['cancha_principal'] ?? '—',
                'Descripción'=> $torneo['descripcion'] ?? '—',
            ];
            foreach ($filas as $label => $valor): ?>
            <tr style="border-bottom:1px solid var(--color-gray-light);">
                <td style="padding:10px 12px 10px 0;font-weight:700;color:var(--color-gray);font-size:0.85rem;white-space:nowrap;width:130px;">
                    <?= h($label) ?>
                </td>
                <td style="padding:10px 0;"><?= h((string) $valor) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="card">
        <h2 class="section-title">Sistema de puntos</h2>
        <div class="grid grid-3" style="margin-top:12px;gap:12px;">
            <div class="stat-card">
                <span class="stat-value"><?= (int) ($torneo['pts_victoria'] ?? 3) ?></span>
                <span class="stat-label">Victoria</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= (int) ($torneo['pts_empate'] ?? 1) ?></span>
                <span class="stat-label">Empate</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?= (int) ($torneo['pts_derrota'] ?? 0) ?></span>
                <span class="stat-label">Derrota</span>
            </div>
        </div>

        <h2 class="section-title" style="margin-top:24px;">Tabla de posiciones</h2>
        <?php
        $posiciones = calcular_posiciones((int) $torneo['id']);
        $limit      = count($posiciones);
        require __DIR__ . '/../../views/components/tabla-posiciones.php';
        ?>
    </div>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
