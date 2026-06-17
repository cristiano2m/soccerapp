<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin']);

$db = Database::getInstance();

// Tabla de settings si no existe
$db->execute("CREATE TABLE IF NOT EXISTS app_settings (
    `key`   VARCHAR(80)  NOT NULL PRIMARY KEY,
    `value` TEXT         NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function get_setting(Database $db, string $key, string $default = ''): string
{
    $row = $db->queryOne("SELECT `value` FROM app_settings WHERE `key` = ?", [$key]);
    return $row ? ($row['value'] ?? $default) : $default;
}

function save_setting(Database $db, string $key, string $value): void
{
    $db->execute(
        "INSERT INTO app_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?",
        [$key, $value, $value]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Token inválido.');
    } else {
        save_setting($db, 'claude_api_key', trim($_POST['claude_api_key'] ?? ''));
        set_flash('success', 'Configuración guardada.');
    }
    redirect('/admin/settings/index.php');
}

$claudeKey = get_setting($db, 'claude_api_key');

// Token de prueba para el plugin WP (primer token activo)
$apiToken = $db->queryOne("SELECT token, nombre FROM api_tokens WHERE activo = 1 ORDER BY id ASC LIMIT 1");

$pageTitle = 'Configuración';
$layout    = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>

<div class="toolbar">
    <h1><span class="ms ms-lg">settings</span> Configuración del sistema</h1>
</div>

<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= h($flash['tipo']) ?>"><?= h($flash['mensaje']) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

    <!-- Claude API -->
    <div class="card">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:8px;">
            <span class="ms">psychology</span> Claude AI — Generador de texto
        </h2>
        <p class="text-muted" style="font-size:0.85rem;margin-bottom:20px;">
            Necesario para generar captions inteligentes para redes sociales desde cada jornada.
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="claude_api_key">Clave API de Anthropic (Claude)</label>
                <input type="password" id="claude_api_key" name="claude_api_key"
                       value="<?= h($claudeKey) ?>"
                       placeholder="sk-ant-api03-..."
                       autocomplete="off">
                <small class="text-muted">
                    Obtenla en <strong>console.anthropic.com</strong> → API Keys.
                    Se guarda cifrada en la base de datos.
                </small>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <button type="submit" class="btn btn-primary">
                    <span class="ms">save</span> Guardar
                </button>
                <?php if ($claudeKey): ?>
                    <span style="color:var(--color-success);font-size:0.85rem;display:flex;align-items:center;gap:4px;">
                        <span class="ms" style="font-size:16px;">check_circle</span> Clave configurada
                    </span>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Plugin WordPress -->
    <div class="card">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:8px;">
            <span class="ms">extension</span> Plugin WordPress
        </h2>
        <p class="text-muted" style="font-size:0.85rem;margin-bottom:20px;">
            Instala el plugin en tu sitio WordPress para mostrar datos del torneo con shortcodes.
        </p>
        <div style="background:var(--color-gray-light);border-radius:8px;padding:16px;margin-bottom:16px;">
            <div style="font-size:0.78rem;font-weight:700;color:var(--color-gray);margin-bottom:8px;">DATOS DE CONEXIÓN PARA EL PLUGIN</div>
            <table style="font-size:0.85rem;width:100%;border-collapse:collapse;">
                <tr>
                    <td style="padding:4px 0;color:var(--color-gray);width:80px;">API URL</td>
                    <td><code style="font-size:0.8rem;"><?= BASE_URL ?>/api/v1</code></td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:var(--color-gray);">Token</td>
                    <td>
                        <?php if ($apiToken): ?>
                            <code id="wp-token" style="font-size:0.78rem;"><?= h($apiToken['token']) ?></code>
                            <button type="button" class="btn btn-outline btn-sm" style="margin-left:6px;"
                                    onclick="navigator.clipboard.writeText(document.getElementById('wp-token').textContent).then(function(){this.textContent='✓';}.bind(this))">
                                <span class="ms" style="font-size:13px;">content_copy</span>
                            </button>
                        <?php else: ?>
                            <span class="text-muted">Sin tokens — crea uno en <a href="<?= BASE_URL ?>/admin/api-tokens/">API Tokens</a></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        <a href="<?= BASE_URL ?>/admin/settings/download-plugin.php"
           class="btn btn-primary" style="width:100%;justify-content:center;">
            <span class="ms">download</span> Descargar plugin WordPress (.zip)
        </a>
        <p class="text-muted" style="font-size:0.8rem;margin-top:10px;">
            Sube el .zip en tu WordPress: Plugins → Añadir nuevo → Subir plugin
        </p>
    </div>

    <!-- Shortcodes referencia -->
    <div class="card" style="grid-column:1/-1;">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
            <span class="ms">code</span> Shortcodes disponibles para WordPress
        </h2>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
            <?php
            $shortcodes = [
                ['[soccerapp_posiciones torneo_id="1"]',   'Tabla de posiciones completa'],
                ['[soccerapp_goleadores torneo_id="1" limit="5"]', 'Top goleadores'],
                ['[soccerapp_calendario torneo_id="1"]',   'Próximos partidos'],
                ['[soccerapp_resultados torneo_id="1"]',   'Últimos resultados'],
                ['[soccerapp_equipos torneo_id="1"]',      'Grid de equipos'],
                ['[soccerapp_marcador partido_id="5"]',    'Marcador en vivo'],
            ];
            foreach ($shortcodes as [$code, $desc]):
            ?>
            <div style="background:var(--color-gray-light);border-radius:8px;padding:14px;">
                <code style="font-size:0.76rem;display:block;margin-bottom:6px;color:var(--color-dark);word-break:break-all;"><?= h($code) ?></code>
                <span style="font-size:0.8rem;color:var(--color-gray);"><?= h($desc) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
