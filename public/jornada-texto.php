<?php
require_once __DIR__ . '/../config/bootstrap.php';

$torneo  = obtener_torneo_activo();
$jornada = $torneo ? obtener_proxima_jornada((int) $torneo['id']) : null;

// Construir el texto plano
$texto = '';
if ($jornada) {
    $titulo = strtoupper($torneo['nombre'] ?? 'TORNEO');
    $texto .= $titulo . "\n";
    $texto .= 'JORNADA ' . (int) $jornada['numero'];
    if (!empty($jornada['fecha'])) {
        $texto .= ' — ' . $jornada['fecha'];
    }
    $texto .= "\n" . str_repeat('─', 50) . "\n\n";

    foreach ($jornada['partidos'] as $p) {
        $hora   = $p['hora']   ? substr($p['hora'], 0, 5) : '     ';
        $cancha = $p['cancha'] ? $p['cancha']             : 'Sin cancha';
        $local  = strtoupper($p['local_nombre']);
        $visita = strtoupper($p['visita_nombre']);
        $texto .= sprintf("%-7s %-15s %s vs %s\n", $hora, $cancha, $local, $visita);
    }
} else {
    $texto = 'No hay jornada activa o próxima en este momento.';
}

$tQs = !empty($torneo['id']) ? '?t=' . (int) $torneo['id'] : '';

$pageTitle = 'Jornada ' . (int) ($jornada['numero'] ?? 0);
$layout    = 'public';
require __DIR__ . '/../views/layout/header.php';
?>

<div class="container" style="padding-top: 24px; padding-bottom: 40px; max-width: 680px;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
        <h1 style="margin:0; font-size:1.4rem;">
            <?php if ($jornada): ?>
                Jornada <?= (int) $jornada['numero'] ?>
                <?php if (!empty($jornada['fecha'])): ?>
                    <span style="font-size:0.9rem; font-weight:400; color:var(--text-muted);">— <?= h($jornada['fecha']) ?></span>
                <?php endif; ?>
            <?php else: ?>
                Próxima jornada
            <?php endif; ?>
        </h1>
        <?php if ($jornada): ?>
        <button id="btn-copiar" onclick="copiarTexto()"
                style="display:flex; align-items:center; gap:6px; padding:8px 16px;
                       background:var(--color-primary); color:#fff; border:none;
                       border-radius:var(--radius); cursor:pointer; font-size:0.9rem; font-weight:600;">
            <span class="ms" style="font-size:18px;">content_copy</span> Copiar
        </button>
        <?php endif; ?>
    </div>

    <?php if ($jornada): ?>
    <textarea id="texto-jornada" readonly
              style="width:100%; min-height:320px; padding:16px; font-family:monospace;
                     font-size:0.92rem; line-height:1.7; border:1px solid var(--border);
                     border-radius:var(--radius); background:var(--surface);
                     color:var(--text); resize:vertical; box-sizing:border-box;"
              onclick="this.select()"><?= h($texto) ?></textarea>

    <p style="margin-top:10px; font-size:0.8rem; color:var(--text-muted);">
        Haz clic en el texto para seleccionarlo todo, o usa el botón Copiar.
    </p>

    <script>
    function copiarTexto() {
        var ta = document.getElementById('texto-jornada');
        ta.select();
        ta.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(ta.value).then(function() {
            var btn = document.getElementById('btn-copiar');
            btn.innerHTML = '<span class="ms" style="font-size:18px;">check</span> Copiado';
            setTimeout(function() {
                btn.innerHTML = '<span class="ms" style="font-size:18px;">content_copy</span> Copiar';
            }, 2000);
        }).catch(function() {
            document.execCommand('copy');
        });
    }
    </script>

    <?php else: ?>
    <div class="card">
        <p class="text-muted">No hay jornada activa o próxima registrada en este momento.</p>
    </div>
    <?php endif; ?>

</div>

<?php require __DIR__ . '/../views/layout/footer.php'; ?>
