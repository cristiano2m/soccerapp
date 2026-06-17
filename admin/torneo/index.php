<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer']);

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de seguridad inválido. Intenta de nuevo.';
    } else {
        $nombre = trim($_POST['nombre'] ?? '');
        $anio = (int) ($_POST['anio'] ?? 0);
        $categoria = trim($_POST['categoria'] ?? '');
        $cancha = trim($_POST['cancha_principal'] ?? '');
        $ptsVictoria = (int) ($_POST['pts_victoria'] ?? 3);
        $ptsEmpate = (int) ($_POST['pts_empate'] ?? 1);
        $ptsDerrota = (int) ($_POST['pts_derrota'] ?? 0);
        $estado = $_POST['estado'] ?? 'borrador';
        $descripcion = trim($_POST['descripcion'] ?? '');
        $colorPrimario = $_POST['color_primario'] ?? '#FFD600';

        if ($nombre === '') {
            $errors[] = 'El nombre del torneo es obligatorio.';
        }
        if ($anio < 2000 || $anio > 2100) {
            $errors[] = 'El año no es válido.';
        }
        if (!in_array($estado, ['borrador', 'activo', 'finalizado'], true)) {
            $errors[] = 'Estado no válido.';
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colorPrimario)) {
            $errors[] = 'El color primario debe ser un código hexadecimal (#RRGGBB).';
        }

        $logoUrl = $torneo['logo_url'] ?? null;
        if (empty($errors)) {
            try {
                $logoUrl = handle_image_upload('logo', 'torneo', $logoUrl);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (empty($errors)) {
            if ($torneo) {
                $db->execute(
                    "UPDATE torneos SET nombre=?, anio=?, categoria=?, cancha_principal=?, pts_victoria=?, pts_empate=?, pts_derrota=?, estado=?, descripcion=?, logo_url=?, color_primario=? WHERE id=?",
                    [$nombre, $anio, $categoria, $cancha, $ptsVictoria, $ptsEmpate, $ptsDerrota, $estado, $descripcion, $logoUrl, $colorPrimario, $torneo['id']]
                );
                set_flash('success', 'Configuración del torneo guardada correctamente.');
                redirect('/admin/torneo/index.php');
            } else {
                $newId = $db->insert(
                    "INSERT INTO torneos (nombre, anio, categoria, cancha_principal, pts_victoria, pts_empate, pts_derrota, estado, descripcion, logo_url, color_primario) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                    [$nombre, $anio, $categoria, $cancha, $ptsVictoria, $ptsEmpate, $ptsDerrota, $estado, $descripcion, $logoUrl, $colorPrimario]
                );
                // Auto-seleccionar el torneo recién creado
                seleccionar_torneo((int) $newId, 'super_admin');
                set_flash('success', 'Torneo creado. Ya estás trabajando en él.');
                redirect('/admin/dashboard.php');
            }
        }

        // Mantener los valores ingresados para re-render en caso de error
        $torneo = [
            'id' => $torneo['id'] ?? null,
            'nombre' => $nombre,
            'anio' => $anio,
            'categoria' => $categoria,
            'cancha_principal' => $cancha,
            'pts_victoria' => $ptsVictoria,
            'pts_empate' => $ptsEmpate,
            'pts_derrota' => $ptsDerrota,
            'estado' => $estado,
            'descripcion' => $descripcion,
            'logo_url' => $logoUrl,
            'color_primario' => $colorPrimario,
        ];
    }
}

$pageTitle = 'Configuración del torneo';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<h1><span class="ms ms-lg">emoji_events</span> Configuración del torneo</h1>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:700px;">
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-group">
                <label for="nombre">Nombre del torneo</label>
                <input type="text" id="nombre" name="nombre" value="<?= h($torneo['nombre'] ?? '') ?>" required maxlength="120">
            </div>
            <div class="form-group" style="max-width:140px;">
                <label for="anio">Año</label>
                <input type="number" id="anio" name="anio" value="<?= h((string) ($torneo['anio'] ?? date('Y'))) ?>" required min="2000" max="2100">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="categoria">Categoría</label>
                <input type="text" id="categoria" name="categoria" value="<?= h($torneo['categoria'] ?? 'Mayor') ?>" maxlength="60">
            </div>
            <div class="form-group">
                <label for="cancha_principal">Cancha principal</label>
                <input type="text" id="cancha_principal" name="cancha_principal" value="<?= h($torneo['cancha_principal'] ?? '') ?>" maxlength="100">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="pts_victoria">Puntos por victoria</label>
                <input type="number" id="pts_victoria" name="pts_victoria" value="<?= h((string) ($torneo['pts_victoria'] ?? 3)) ?>" required min="0" max="10">
            </div>
            <div class="form-group">
                <label for="pts_empate">Puntos por empate</label>
                <input type="number" id="pts_empate" name="pts_empate" value="<?= h((string) ($torneo['pts_empate'] ?? 1)) ?>" required min="0" max="10">
            </div>
            <div class="form-group">
                <label for="pts_derrota">Puntos por derrota</label>
                <input type="number" id="pts_derrota" name="pts_derrota" value="<?= h((string) ($torneo['pts_derrota'] ?? 0)) ?>" required min="0" max="10">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="estado">Estado</label>
                <select id="estado" name="estado" required>
                    <?php foreach (['borrador' => 'Borrador', 'activo' => 'Activo', 'finalizado' => 'Finalizado'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($torneo['estado'] ?? 'borrador') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="max-width:140px;">
                <label for="color_primario">Color primario</label>
                <input type="color" id="color_primario" name="color_primario" value="<?= h($torneo['color_primario'] ?? '#FFD600') ?>" style="height:42px;padding:4px;">
            </div>
        </div>

        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="3"><?= h($torneo['descripcion'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="logo">Logo del torneo</label>
            <?php if (!empty($torneo['logo_url'])): ?>
                <img src="<?= h($torneo['logo_url']) ?>" alt="Logo actual" style="width:64px;height:64px;object-fit:cover;border-radius:50%;margin-bottom:8px;">
            <?php endif; ?>
            <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>

        <button type="submit" class="btn btn-primary">Guardar cambios</button>
    </form>

    <?php if ($torneo && !empty($torneo['id'])): ?>
    <hr style="border:none;border-top:1px solid var(--color-gray-light);margin:24px 0;">
    <div>
        <label style="display:block;margin-bottom:6px;font-weight:600;display:flex;align-items:center;gap:6px;"><span class="ms">link</span> URL pública del torneo</label>
        <p class="text-muted" style="font-size:0.85rem;margin-bottom:10px;">Comparte este enlace para que los participantes y el público puedan ver el torneo.</p>
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="text" id="url-publica-torneo"
                   value="<?= h(url_publica_torneo((int) $torneo['id'])) ?>"
                   readonly
                   style="background:var(--color-gray-light);cursor:default;color:var(--color-dark);">
            <button type="button" class="btn btn-outline" style="white-space:nowrap;"
                    onclick="var i=document.getElementById('url-publica-torneo'); navigator.clipboard.writeText(i.value).then(function(){ var b=event.currentTarget; b.textContent='✓ Copiado'; setTimeout(function(){ b.textContent='Copiar URL'; },1800); })">
                Copiar URL
            </button>
            <a class="btn btn-outline" href="<?= h(url_publica_torneo((int) $torneo['id'])) ?>" target="_blank">Abrir ↗</a>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
