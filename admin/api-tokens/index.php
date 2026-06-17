<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin']);

$db = Database::getInstance();

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token de seguridad inválido.');
        redirect('/admin/api-tokens/index.php');
    }

    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            set_flash('error', 'El nombre del token es obligatorio.');
            redirect('/admin/api-tokens/index.php');
        }
        $token = bin2hex(random_bytes(32)); // 64 chars hex
        $db->insert(
            "INSERT INTO api_tokens (token, nombre, activo) VALUES (?, ?, 1)",
            [$token, $nombre]
        );
        // Guardar en sesión para mostrarlo una sola vez
        $_SESSION['nuevo_token'] = $token;
        set_flash('success', "Token «{$nombre}» generado. Cópialo ahora, no se mostrará de nuevo.");
        redirect('/admin/api-tokens/index.php');
    }

    if ($accion === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->execute("UPDATE api_tokens SET activo = NOT activo WHERE id = ?", [$id]);
        set_flash('success', 'Estado del token actualizado.');
        redirect('/admin/api-tokens/index.php');
    }

    if ($accion === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->execute("DELETE FROM api_tokens WHERE id = ?", [$id]);
        set_flash('success', 'Token eliminado.');
        redirect('/admin/api-tokens/index.php');
    }
}

// ── GET ──────────────────────────────────────────────────────────────────────
$tokens = $db->query("SELECT * FROM api_tokens ORDER BY id DESC");

// Token nuevo (mostrar solo una vez)
$nuevoToken = $_SESSION['nuevo_token'] ?? null;
unset($_SESSION['nuevo_token']);

$apiBase = BASE_URL . '/api/v1';

$pageTitle = 'API Tokens';
$layout    = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>

<div class="toolbar">
    <h1><span class="ms ms-lg">api</span> API Tokens</h1>
</div>

<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= h($flash['tipo']) ?>"><?= h($flash['mensaje']) ?></div>
<?php endif; ?>

<?php if ($nuevoToken): ?>
<!-- Token recién generado — mostrar solo una vez -->
<div class="alert alert-success" style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:16px 20px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;font-weight:700;color:#166534;">
        <span class="ms">check_circle</span> Token generado — cópialo ahora, no se volverá a mostrar
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <code id="nuevo-token" style="flex:1;background:#dcfce7;border:1px solid #86efac;border-radius:6px;padding:10px 14px;font-size:0.88rem;font-family:monospace;word-break:break-all;color:#14532d;">
            <?= h($nuevoToken) ?>
        </code>
        <button type="button" class="btn btn-outline btn-sm" style="white-space:nowrap;"
                onclick="navigator.clipboard.writeText(document.getElementById('nuevo-token').textContent.trim()).then(function(){ this.textContent='✓ Copiado'; }.bind(this))">
            <span class="ms">content_copy</span> Copiar
        </button>
    </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

    <!-- ── Tokens existentes ── -->
    <div class="card">
        <h2 style="font-size:0.95rem;font-weight:700;margin-bottom:16px;">
            <span class="ms">key</span> Tokens activos
        </h2>

        <?php if (empty($tokens)): ?>
            <p class="text-muted" style="text-align:center;padding:32px;">
                No hay tokens generados aún.
            </p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre / Cliente</th>
                        <th>Token</th>
                        <th>Último uso</th>
                        <th>Creado</th>
                        <th style="width:80px;">Estado</th>
                        <th style="width:110px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tokens as $t): ?>
                <tr style="<?= !$t['activo'] ? 'opacity:0.5;' : '' ?>">
                    <td><strong><?= h($t['nombre']) ?></strong></td>
                    <td>
                        <code style="font-size:0.78rem;background:var(--color-gray-light);padding:3px 8px;border-radius:4px;letter-spacing:0.05em;">
                            <?= h(substr($t['token'], 0, 8)) ?>••••<?= h(substr($t['token'], -4)) ?>
                        </code>
                    </td>
                    <td style="font-size:0.85rem;color:var(--color-gray);">
                        <?= $t['last_used'] ? date('d/m/Y H:i', strtotime($t['last_used'])) : '—' ?>
                    </td>
                    <td style="font-size:0.85rem;color:var(--color-gray);">
                        <?= date('d/m/Y', strtotime($t['created_at'])) ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $t['activo'] ? 'activo' : 'finalizado' ?>">
                            <?= $t['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td class="actions">
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm" title="<?= $t['activo'] ? 'Desactivar' : 'Activar' ?>">
                                <span class="ms"><?= $t['activo'] ? 'toggle_on' : 'toggle_off' ?></span>
                            </button>
                        </form>
                        <form method="post" style="display:inline;"
                              onsubmit="return confirm('¿Eliminar este token? Los clientes que lo usen perderán acceso.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <span class="ms">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Panel derecho ── -->
    <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Crear token -->
        <div class="card">
            <h2 style="font-size:0.95rem;font-weight:700;margin-bottom:16px;">
                <span class="ms">add_circle</span> Nuevo token
            </h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="crear">
                <div class="form-group">
                    <label for="nombre">Nombre del cliente</label>
                    <input type="text" id="nombre" name="nombre" required
                           placeholder="Ej: WordPress Sitio Principal" maxlength="100">
                    <small class="text-muted">Identifica para qué aplicación es este token.</small>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    <span class="ms">key</span> Generar token
                </button>
            </form>
        </div>

        <!-- Referencia de endpoints -->
        <div class="card">
            <h2 style="font-size:0.95rem;font-weight:700;margin-bottom:14px;">
                <span class="ms">code</span> Endpoints disponibles
            </h2>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <?php
                $endpoints = [
                    ['GET', '/torneos',                  'Listar torneos'],
                    ['GET', '/torneos/{id}',             'Detalle del torneo'],
                    ['GET', '/torneos/{id}/posiciones',  'Tabla de posiciones'],
                    ['GET', '/torneos/{id}/goleadores',  'Top goleadores'],
                    ['GET', '/torneos/{id}/equipos',     'Equipos'],
                    ['GET', '/torneos/{id}/jornadas',    'Jornadas + partidos'],
                    ['GET', '/torneos/{id}/partidos',    'Partidos (?estado=&jornada=)'],
                    ['GET', '/torneos/{id}/patrocinadores', 'Patrocinadores'],
                    ['GET', '/partidos/{id}',            'Detalle de partido'],
                    ['GET', '/partidos/{id}/live',       'Marcador en vivo'],
                ];
                foreach ($endpoints as [$method, $path, $desc]):
                ?>
                <div style="display:flex;align-items:flex-start;gap:8px;padding:7px 0;border-bottom:1px solid var(--color-gray-light);font-size:0.8rem;">
                    <span style="background:var(--color-primary);color:var(--color-dark);font-weight:800;font-size:0.65rem;padding:2px 6px;border-radius:3px;letter-spacing:0.06em;white-space:nowrap;margin-top:1px;"><?= $method ?></span>
                    <div>
                        <code style="font-size:0.77rem;color:var(--color-dark);">/api/v1<?= $path ?></code>
                        <div style="color:var(--color-gray);margin-top:1px;"><?= $desc ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:14px;padding:10px 12px;background:var(--color-gray-light);border-radius:6px;font-size:0.78rem;">
                <strong>Autenticación:</strong><br>
                <code style="font-size:0.75rem;">Authorization: Bearer &lt;token&gt;</code><br>
                <span style="color:var(--color-gray);">o bien</span><br>
                <code style="font-size:0.75rem;">?token=&lt;token&gt;</code>
            </div>
        </div>

        <!-- Ejemplo de uso -->
        <div class="card">
            <h2 style="font-size:0.95rem;font-weight:700;margin-bottom:10px;">
                <span class="ms">integration_instructions</span> Ejemplo cURL
            </h2>
            <pre style="background:#1a2035;color:#a9c4e4;border-radius:8px;padding:14px;font-size:0.75rem;overflow-x:auto;line-height:1.6;"><code>curl -H "Authorization: Bearer TU_TOKEN" \
  "<?= BASE_URL ?>/api/v1/torneos"</code></pre>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
