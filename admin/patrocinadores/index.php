<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer']);

$db     = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) {
    set_flash('error', 'Primero debes seleccionar un torneo.');
    redirect('/admin/dashboard.php');
}

$errors = [];

// ── POST: guardar / eliminar ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'eliminar') {
            $id  = (int) ($_POST['id'] ?? 0);
            $row = $db->queryOne("SELECT logo_url FROM patrocinadores WHERE id = ? AND torneo_id = ?", [$id, $torneo['id']]);
            if ($row) {
                // Borrar archivo físico si existe
                if (!empty($row['logo_url'])) {
                    $file = __DIR__ . '/../../' . ltrim(str_replace(BASE_URL, '', $row['logo_url']), '/');
                    if (file_exists($file)) @unlink($file);
                }
                $db->execute("DELETE FROM patrocinadores WHERE id = ? AND torneo_id = ?", [$id, $torneo['id']]);
                set_flash('success', 'Patrocinador eliminado.');
            }
            redirect('/admin/patrocinadores/index.php');
        }

        if (in_array($accion, ['crear', 'editar'], true)) {
            $id      = (int) ($_POST['id'] ?? 0);
            $nombre  = trim($_POST['nombre'] ?? '');
            $urlSitio = trim($_POST['url_sitio'] ?? '');
            $orden   = (int) ($_POST['orden'] ?? 0);
            $activo  = isset($_POST['activo']) ? 1 : 0;

            if ($nombre === '') $errors[] = 'El nombre es obligatorio.';

            $logoUrl = $_POST['logo_url_actual'] ?? null;
            if (empty($errors)) {
                try {
                    $logoUrl = handle_image_upload('logo', 'patrocinadores', $logoUrl ?: null);
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            if (empty($errors)) {
                if ($accion === 'crear') {
                    $db->insert(
                        "INSERT INTO patrocinadores (torneo_id, nombre, logo_url, url_sitio, orden, activo) VALUES (?,?,?,?,?,?)",
                        [$torneo['id'], $nombre, $logoUrl, $urlSitio ?: null, $orden, $activo]
                    );
                    set_flash('success', "Patrocinador «{$nombre}» agregado.");
                } else {
                    $db->execute(
                        "UPDATE patrocinadores SET nombre=?, logo_url=?, url_sitio=?, orden=?, activo=? WHERE id=? AND torneo_id=?",
                        [$nombre, $logoUrl, $urlSitio ?: null, $orden, $activo, $id, $torneo['id']]
                    );
                    set_flash('success', "Patrocinador «{$nombre}» actualizado.");
                }
                redirect('/admin/patrocinadores/index.php');
            }
        }
    }
}

// ── GET: ¿modo edición? ───────────────────────────────────────────────────────
$editando = null;
if (isset($_GET['editar'])) {
    $editando = $db->queryOne(
        "SELECT * FROM patrocinadores WHERE id = ? AND torneo_id = ?",
        [(int) $_GET['editar'], $torneo['id']]
    );
}

$patrocinadores = $db->query(
    "SELECT * FROM patrocinadores WHERE torneo_id = ? ORDER BY orden ASC, id ASC",
    [$torneo['id']]
);

$pageTitle = 'Patrocinadores';
$layout    = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>

<div class="toolbar">
    <h1><span class="ms ms-lg">verified</span> Patrocinadores</h1>
</div>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= h($e) ?></div>
<?php endforeach; ?>
<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= h($flash['tipo']) ?>"><?= h($flash['mensaje']) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;">

    <!-- ── Lista ── -->
    <div class="card">
        <?php if (empty($patrocinadores)): ?>
            <p class="text-muted" style="text-align:center;padding:40px;">
                Aún no hay patrocinadores. Agrégalos con el formulario.
            </p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:56px;">Logo</th>
                        <th>Nombre</th>
                        <th>Sitio web</th>
                        <th style="width:60px;">Orden</th>
                        <th style="width:70px;">Activo</th>
                        <th style="width:120px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($patrocinadores as $p): ?>
                    <tr>
                        <td>
                            <?php if (!empty($p['logo_url'])): ?>
                                <img src="<?= h($p['logo_url']) ?>" alt="<?= h($p['nombre']) ?>"
                                     style="width:44px;height:44px;object-fit:contain;border-radius:6px;background:#f5f6f8;padding:4px;">
                            <?php else: ?>
                                <div style="width:44px;height:44px;border-radius:6px;background:var(--color-gray-light);display:flex;align-items:center;justify-content:center;">
                                    <span class="ms" style="font-size:20px;color:var(--color-gray);">image</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= h($p['nombre']) ?></strong></td>
                        <td>
                            <?php if (!empty($p['url_sitio'])): ?>
                                <a href="<?= h($p['url_sitio']) ?>" target="_blank" style="font-size:0.85rem;">
                                    <span class="ms" style="font-size:13px;">open_in_new</span>
                                    <?= h(parse_url($p['url_sitio'], PHP_URL_HOST) ?: $p['url_sitio']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?= (int) $p['orden'] ?></td>
                        <td style="text-align:center;">
                            <span class="badge badge-<?= $p['activo'] ? 'activo' : 'finalizado' ?>">
                                <?= $p['activo'] ? 'Sí' : 'No' ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a class="btn btn-outline btn-sm"
                               href="<?= BASE_URL ?>/admin/patrocinadores/index.php?editar=<?= (int) $p['id'] ?>">
                                <span class="ms">edit_note</span>
                            </a>
                            <form method="post" style="display:inline;"
                                  onsubmit="return confirm('¿Eliminar «<?= h(addslashes($p['nombre'])) ?>»?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
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

    <!-- ── Formulario ── -->
    <div class="card" style="position:sticky;top:80px;">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:18px;">
            <span class="ms"><?= $editando ? 'edit_note' : 'add_circle' ?></span>
            <?= $editando ? 'Editar patrocinador' : 'Nuevo patrocinador' ?>
        </h2>

        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="<?= $editando ? 'editar' : 'crear' ?>">
            <?php if ($editando): ?>
            <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
            <input type="hidden" name="logo_url_actual" value="<?= h($editando['logo_url'] ?? '') ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="nombre">Nombre del patrocinador *</label>
                <input type="text" id="nombre" name="nombre" required maxlength="120"
                       value="<?= h($editando['nombre'] ?? '') ?>"
                       placeholder="Ej: Banco Nacional">
            </div>

            <div class="form-group">
                <label for="logo">Logo
                    <span class="text-muted" style="font-weight:400;"> — PNG, JPG o WEBP recomendado</span>
                </label>
                <?php if (!empty($editando['logo_url'])): ?>
                <div style="margin-bottom:10px;display:flex;align-items:center;gap:10px;">
                    <img src="<?= h($editando['logo_url']) ?>" alt=""
                         style="height:56px;max-width:120px;object-fit:contain;border-radius:6px;background:#f5f6f8;padding:4px;">
                    <span class="text-muted" style="font-size:0.82rem;">Logo actual · sube uno nuevo para reemplazar</span>
                </div>
                <?php endif; ?>
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp,image/gif">
            </div>

            <div class="form-group">
                <label for="url_sitio">Sitio web (opcional)</label>
                <input type="url" id="url_sitio" name="url_sitio"
                       value="<?= h($editando['url_sitio'] ?? '') ?>"
                       placeholder="https://www.ejemplo.com">
            </div>

            <div class="form-row">
                <div class="form-group" style="max-width:100px;">
                    <label for="orden">Orden</label>
                    <input type="number" id="orden" name="orden" min="0" max="999"
                           value="<?= (int) ($editando['orden'] ?? 0) ?>">
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:8px;margin-top:28px;">
                    <input type="checkbox" id="activo" name="activo" value="1"
                           <?= ($editando['activo'] ?? 1) ? 'checked' : '' ?>>
                    <label for="activo" style="margin:0;font-weight:600;">Visible en el sitio</label>
                </div>
            </div>

            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary">
                    <span class="ms"><?= $editando ? 'save' : 'add_circle' ?></span>
                    <?= $editando ? 'Guardar cambios' : 'Agregar patrocinador' ?>
                </button>
                <?php if ($editando): ?>
                <a href="<?= BASE_URL ?>/admin/patrocinadores/index.php" class="btn btn-outline">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

</div>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
