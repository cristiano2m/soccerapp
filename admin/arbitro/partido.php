<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['referee']);

$db     = Database::getInstance();
$torneo = obtener_torneo_activo();
$user   = current_user();

if (!$torneo) redirect('/admin/dashboard.php');

$id = (int) ($_GET['id'] ?? 0);

$partido = $db->queryOne(
    "SELECT p.*, j.numero AS jornada_numero, j.fecha AS jornada_fecha,
            el.id AS local_id, el.nombre AS local_nombre, el.color_hex AS local_color,
            el.logo_url AS local_logo, el.abreviatura AS local_abrev,
            ev.id AS visita_id, ev.nombre AS visita_nombre, ev.color_hex AS visita_color,
            ev.logo_url AS visita_logo, ev.abreviatura AS visita_abrev,
            ua.nombre AS arbitro_nombre, ua2.nombre AS arbitro2_nombre, ua3.nombre AS arbitro3_nombre
     FROM partidos p
     JOIN jornadas j   ON j.id   = p.jornada_id
     JOIN equipos  el  ON el.id  = p.equipo_local_id
     JOIN equipos  ev  ON ev.id  = p.equipo_visita_id
     LEFT JOIN usuarios ua  ON ua.id  = p.arbitro_id
     LEFT JOIN usuarios ua2 ON ua2.id = p.arbitro_id2
     LEFT JOIN usuarios ua3 ON ua3.id = p.arbitro_id3
     WHERE p.id = ? AND p.torneo_id = ?",
    [$id, $torneo['id']]
);

if (!$partido) {
    set_flash('error', 'Partido no encontrado.');
    redirect('/admin/arbitro/dashboard.php');
}

$estado      = $partido['estado'];
$puedeEditar = $estado === 'en_curso';
$finalizado  = $estado === 'finalizado';

// ── Jugadores (siempre los cargamos para vista y form) ─────────────────────
$jugadoresLocal  = $db->query("SELECT id, nombre, numero FROM jugadores WHERE equipo_id = ? AND activo = 1 ORDER BY numero ASC", [$partido['local_id']]);
$jugadoresVisita = $db->query("SELECT id, nombre, numero FROM jugadores WHERE equipo_id = ? AND activo = 1 ORDER BY numero ASC", [$partido['visita_id']]);
$jugadorEquipo   = [];
foreach ($jugadoresLocal  as $j) { $jugadorEquipo[$j['id']] = (int) $partido['local_id']; }
foreach ($jugadoresVisita as $j) { $jugadorEquipo[$j['id']] = (int) $partido['visita_id']; }

$opcionesLocal  = array_map(fn($j) => ['id' => $j['id'], 'label' => '#' . $j['numero'] . ' ' . $j['nombre']], $jugadoresLocal);
$opcionesVisita = array_map(fn($j) => ['id' => $j['id'], 'label' => '#' . $j['numero'] . ' ' . $j['nombre']], $jugadoresVisita);

$resultado  = $db->queryOne("SELECT * FROM resultados WHERE partido_id = ?", [$partido['id']]);
$golesExist = $db->query("SELECT * FROM goles    WHERE partido_id = ? ORDER BY id ASC", [$partido['id']]);
$tarjExist  = $db->query("SELECT * FROM tarjetas WHERE partido_id = ? ORDER BY id ASC", [$partido['id']]);

// Separar por equipo para las columnas
$golesLocalEx = $golesVisitaEx = $tarjLocalEx = $tarjVisitaEx = [];
foreach ($golesExist as $g) {
    $entry = ['jugador_id' => (int) $g['jugador_id'], 'tipo' => $g['tipo'], 'minuto' => $g['minuto']];
    if (($jugadorEquipo[$g['jugador_id']] ?? null) === (int) $partido['local_id'])
        $golesLocalEx[] = $entry;
    else
        $golesVisitaEx[] = $entry;
}
foreach ($tarjExist as $t) {
    $entry = ['jugador_id' => (int) $t['jugador_id'], 'tipo' => $t['tipo'], 'minuto' => $t['minuto']];
    if (($jugadorEquipo[$t['jugador_id']] ?? null) === (int) $partido['local_id'])
        $tarjLocalEx[] = $entry;
    else
        $tarjVisitaEx[] = $entry;
}

// ── POST: Guardar resultado (solo si en_curso) ─────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeEditar) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $woLocal       = isset($_POST['wo_local']);
        $woVisita      = isset($_POST['wo_visita']);
        $golesLocalIn  = (int) ($_POST['goles_local']  ?? 0);
        $golesVisitaIn = (int) ($_POST['goles_visita'] ?? 0);
        $observaciones = trim($_POST['observaciones'] ?? '');

        $tiposGol     = ['normal', 'penalti', 'autogol'];
        $tiposTarjeta = ['amarilla', 'roja', 'doble_amarilla'];

        $golesPost    = array_merge(array_values($_POST['gol_local']      ?? []), array_values($_POST['gol_visita']      ?? []));
        $tarjetasPost = array_merge(array_values($_POST['tarjetas_local'] ?? []), array_values($_POST['tarjetas_visita'] ?? []));

        $golesInput = [];
        foreach ($golesPost as $g) {
            $jid    = (int) ($g['jugador_id'] ?? 0);
            $tipo   = $g['tipo'] ?? 'normal';
            $minuto = ($g['minuto'] ?? '') !== '' ? (int) $g['minuto'] : null;
            if ($jid <= 0 || !isset($jugadorEquipo[$jid]) || !in_array($tipo, $tiposGol, true)) continue;
            $eqJug  = $jugadorEquipo[$jid];
            $eqGol  = $tipo === 'autogol'
                ? ($eqJug === (int) $partido['local_id'] ? (int) $partido['visita_id'] : (int) $partido['local_id'])
                : $eqJug;
            $golesInput[] = ['jugador_id' => $jid, 'equipo_id' => $eqGol, 'minuto' => $minuto, 'tipo' => $tipo];
        }

        $tarjetasInput = [];
        foreach ($tarjetasPost as $t) {
            $jid    = (int) ($t['jugador_id'] ?? 0);
            $tipo   = $t['tipo'] ?? 'amarilla';
            $minuto = ($t['minuto'] ?? '') !== '' ? (int) $t['minuto'] : null;
            if ($jid <= 0 || !isset($jugadorEquipo[$jid]) || !in_array($tipo, $tiposTarjeta, true)) continue;
            $tarjetasInput[] = ['jugador_id' => $jid, 'tipo' => $tipo, 'minuto' => $minuto];
        }

        if ($woLocal && $woVisita) {
            $golesLocal = $golesVisita = 0;
        } elseif ($woLocal || $woVisita) {
            $wo          = calcular_puntos_wo($woLocal, $woVisita);
            $golesLocal  = $wo['goles_local'];
            $golesVisita = $wo['goles_visita'];
        } else {
            // Calcular marcador desde los goles individuales registrados
            $golesLocal = $golesVisita = 0;
            foreach ($golesInput as $g) {
                if ((int) $g['equipo_id'] === (int) $partido['local_id']) $golesLocal++;
                else $golesVisita++;
            }
        }

        if (empty($errors)) {
            $accionForm = ($_POST['accion_form'] ?? '') === 'finalizar' ? 'finalizar' : 'borrador';

            try {
                $db->beginTransaction();

                $db->execute(
                    "INSERT INTO resultados (partido_id, goles_local, goles_visita, wo_local, wo_visita, observaciones, registrado_por)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                         goles_local=VALUES(goles_local), goles_visita=VALUES(goles_visita),
                         wo_local=VALUES(wo_local), wo_visita=VALUES(wo_visita),
                         observaciones=VALUES(observaciones), registrado_por=VALUES(registrado_por)",
                    [$partido['id'], $golesLocal, $golesVisita, (int)$woLocal, (int)$woVisita, $observaciones, $user['id']]
                );

                $db->execute("DELETE FROM goles WHERE partido_id = ?", [$partido['id']]);
                foreach ($golesInput as $g) {
                    $db->insert(
                        "INSERT INTO goles (partido_id, jugador_id, equipo_id, minuto, tipo) VALUES (?,?,?,?,?)",
                        [$partido['id'], $g['jugador_id'], $g['equipo_id'], $g['minuto'], $g['tipo']]
                    );
                }

                $db->execute("DELETE FROM tarjetas WHERE partido_id = ?", [$partido['id']]);
                foreach ($tarjetasInput as $t) {
                    $db->insert(
                        "INSERT INTO tarjetas (partido_id, jugador_id, tipo, minuto) VALUES (?,?,?,?)",
                        [$partido['id'], $t['jugador_id'], $t['tipo'], $t['minuto']]
                    );
                }

                if ($accionForm === 'finalizar') {
                    $db->execute("UPDATE partidos SET estado = 'finalizado' WHERE id = ?", [$partido['id']]);
                }

                $db->commit();

                if ($accionForm === 'finalizar') {
                    set_flash('success', 'Partido finalizado. Ya puedes descargar el acta.');
                } else {
                    set_flash('success', 'Borrador guardado. Puedes seguir modificando el acta.');
                }
                redirect('/admin/arbitro/partido.php?id=' . $partido['id']);
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Partido · Árbitro';
$layout    = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-arbitro.php';
?>

<div class="toolbar">
    <div>
        <h1>
            <?php if ($estado === 'en_curso'): ?>
                <span style="color:#2563eb;">● En Curso</span> —
            <?php elseif ($finalizado): ?>
                ✅ Finalizado —
            <?php endif; ?>
            Jornada <?= (int) $partido['jornada_numero'] ?>
        </h1>
        <p class="text-muted">
            <?= h($partido['local_nombre']) ?> vs <?= h($partido['visita_nombre']) ?>
            <?= !empty($partido['jornada_fecha']) ? ' · ' . h($partido['jornada_fecha']) : '' ?>
            <?= $partido['hora'] ? ' · ' . h(substr($partido['hora'], 0, 5)) : '' ?>
            <?= !empty($partido['cancha']) ? ' · ' . h($partido['cancha']) : '' ?>
        </p>
    </div>
    <div class="actions">
        <?php if ($finalizado): ?>
            <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/acta/index.php?id=<?= (int) $partido['id'] ?>">
                📄 Ver / Imprimir Acta
            </a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/arbitro/dashboard.php">← Volver</a>
    </div>
</div>

<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= h($flash['tipo']) ?>"><?= h($flash['mensaje']) ?></div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= h($e) ?></div>
<?php endforeach; ?>

<?php if ($finalizado): ?>
<!-- ══════════════ FINALIZADO ══════════════ -->
<div class="card" style="text-align:center;padding:32px;">
    <div style="font-size:2.8rem;font-weight:900;margin:12px 0;">
        <?= (int) ($resultado['goles_local'] ?? 0) ?> – <?= (int) ($resultado['goles_visita'] ?? 0) ?>
    </div>
    <p class="text-muted"><?= h($partido['local_nombre']) ?> vs <?= h($partido['visita_nombre']) ?></p>
    <a class="btn btn-primary" style="margin-top:20px;padding:12px 32px;"
       href="<?= BASE_URL ?>/admin/acta/index.php?id=<?= (int) $partido['id'] ?>">
        📄 Ver e imprimir Acta
    </a>
</div>

<?php if (!empty($golesExist) || !empty($tarjExist)): ?>
<div class="card" style="margin-top:18px;">
    <div class="grid grid-2">
        <?php if (!empty($golesExist)): ?>
        <div>
            <h3 class="section-title"><span class="ms">sports_soccer</span> Goles</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Jugador</th><th>Tipo</th><th>Min</th></tr></thead>
                    <tbody>
                    <?php foreach ($golesExist as $g):
                        $jug = $db->queryOne("SELECT nombre, numero FROM jugadores WHERE id = ?", [$g['jugador_id']]);
                    ?>
                        <tr>
                            <td><?= $jug ? h('#'.$jug['numero'].' '.$jug['nombre']) : '—' ?></td>
                            <td><?= h($g['tipo']) ?></td>
                            <td><?= $g['minuto'] !== null ? (int)$g['minuto'] . "'" : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($tarjExist)): ?>
        <div>
            <h3 class="section-title">🟨 Tarjetas</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Jugador</th><th>Tipo</th><th>Min</th></tr></thead>
                    <tbody>
                    <?php foreach ($tarjExist as $t):
                        $jug = $db->queryOne("SELECT nombre, numero FROM jugadores WHERE id = ?", [$t['jugador_id']]);
                    ?>
                        <tr>
                            <td><?= $jug ? h('#'.$jug['numero'].' '.$jug['nombre']) : '—' ?></td>
                            <td><?= h($t['tipo']) ?></td>
                            <td><?= $t['minuto'] !== null ? (int)$t['minuto'] . "'" : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($resultado['observaciones'])): ?>
    <div style="margin-top:14px;">
        <strong>Observaciones:</strong>
        <p class="text-muted"><?= nl2br(h($resultado['observaciones'])) ?></p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php elseif ($puedeEditar): ?>
<!-- ══════════════ EN CURSO → LLENAR ACTA ══════════════ -->
<div class="card"
     x-data='{
        golesLocal:    <?= json_encode(array_values($golesLocalEx),  JSON_UNESCAPED_UNICODE) ?>,
        golesVisita:   <?= json_encode(array_values($golesVisitaEx), JSON_UNESCAPED_UNICODE) ?>,
        tarjetasLocal: <?= json_encode(array_values($tarjLocalEx),   JSON_UNESCAPED_UNICODE) ?>,
        tarjetasVisita:<?= json_encode(array_values($tarjVisitaEx),  JSON_UNESCAPED_UNICODE) ?>,
        wo_local:  <?= !empty($resultado['wo_local'])  ? 'true' : 'false' ?>,
        wo_visita: <?= !empty($resultado['wo_visita']) ? 'true' : 'false' ?>,
        get marcadorLocal() {
            if (this.wo_local || this.wo_visita) return 0;
            return this.golesLocal.filter(g => g.tipo !== "autogol").length
                 + this.golesVisita.filter(g => g.tipo === "autogol").length;
        },
        get marcadorVisita() {
            if (this.wo_local || this.wo_visita) return 0;
            return this.golesVisita.filter(g => g.tipo !== "autogol").length
                 + this.golesLocal.filter(g => g.tipo === "autogol").length;
        }
     }'>
    <form method="post">
        <?= csrf_field() ?>

        <h2 class="section-title">Marcador</h2>

        <!-- Marcador calculado automáticamente desde los goles registrados -->
        <div style="display:flex;align-items:center;justify-content:center;gap:32px;margin:16px 0 20px;">
            <div style="text-align:center;">
                <div class="text-muted" style="font-size:0.82rem;font-weight:600;margin-bottom:6px;text-transform:uppercase;">
                    <?= h($partido['local_nombre']) ?>
                </div>
                <div x-text="wo_local || wo_visita ? 'W.O.' : marcadorLocal"
                     style="font-size:3.5rem;font-weight:900;line-height:1;min-width:80px;text-align:center;"></div>
            </div>
            <div style="font-size:2rem;font-weight:700;color:var(--color-gray);">–</div>
            <div style="text-align:center;">
                <div class="text-muted" style="font-size:0.82rem;font-weight:600;margin-bottom:6px;text-transform:uppercase;">
                    <?= h($partido['visita_nombre']) ?>
                </div>
                <div x-text="wo_local || wo_visita ? 'W.O.' : marcadorVisita"
                     style="font-size:3.5rem;font-weight:900;line-height:1;min-width:80px;text-align:center;"></div>
            </div>
        </div>
        <p class="text-muted" style="text-align:center;font-size:0.82rem;margin-bottom:18px;">
            El marcador se actualiza automáticamente al agregar o quitar goles.
        </p>

        <!-- Inputs ocultos que envían el marcador calculado al servidor -->
        <input type="hidden" name="goles_local"  :value="marcadorLocal">
        <input type="hidden" name="goles_visita" :value="marcadorVisita">

        <div class="form-row" style="justify-content:center;">
            <div class="form-group checkbox-row">
                <input type="checkbox" id="wo_local" name="wo_local" value="1" x-model="wo_local">
                <label for="wo_local" style="margin-bottom:0;">W.O. — <?= h($partido['local_nombre']) ?> no se presentó</label>
            </div>
            <div class="form-group checkbox-row">
                <input type="checkbox" id="wo_visita" name="wo_visita" value="1" x-model="wo_visita">
                <label for="wo_visita" style="margin-bottom:0;">W.O. — <?= h($partido['visita_nombre']) ?> no se presentó</label>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--color-gray-light);margin:20px 0;">

        <h2 class="section-title"><span class="ms">sports_soccer</span> Goles</h2>
        <div class="grid grid-2">
            <div>
                <h3 class="section-title" style="font-size:0.92rem;"><?= h($partido['local_nombre']) ?></h3>
                <template x-for="(gol, index) in golesLocal" :key="index">
                    <div class="form-row" style="align-items:center;">
                        <div class="form-group">
                            <label>Jugador</label>
                            <select :name="'gol_local['+index+'][jugador_id]'" x-model.number="gol.jugador_id">
                                <option value="0">-- Selecciona --</option>
                                <?php foreach ($opcionesLocal as $j): ?>
                                    <option value="<?= (int) $j['id'] ?>"><?= h($j['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:120px;">
                            <label>Tipo</label>
                            <select :name="'gol_local['+index+'][tipo]'" x-model="gol.tipo">
                                <option value="normal">Normal</option>
                                <option value="penalti">Penal</option>
                                <option value="autogol">Autogol</option>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:76px;">
                            <label>Min</label>
                            <input type="number" min="0" max="130" :name="'gol_local['+index+'][minuto]'" x-model.number="gol.minuto">
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" style="margin-top:22px;" @click="golesLocal.splice(index,1)">✕</button>
                    </div>
                </template>
                <button type="button" class="btn btn-outline btn-sm"
                        @click="golesLocal.push({jugador_id:0,tipo:'normal',minuto:null})">+ Agregar gol</button>
            </div>
            <div>
                <h3 class="section-title" style="font-size:0.92rem;"><?= h($partido['visita_nombre']) ?></h3>
                <template x-for="(gol, index) in golesVisita" :key="index">
                    <div class="form-row" style="align-items:center;">
                        <div class="form-group">
                            <label>Jugador</label>
                            <select :name="'gol_visita['+index+'][jugador_id]'" x-model.number="gol.jugador_id">
                                <option value="0">-- Selecciona --</option>
                                <?php foreach ($opcionesVisita as $j): ?>
                                    <option value="<?= (int) $j['id'] ?>"><?= h($j['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:120px;">
                            <label>Tipo</label>
                            <select :name="'gol_visita['+index+'][tipo]'" x-model="gol.tipo">
                                <option value="normal">Normal</option>
                                <option value="penalti">Penal</option>
                                <option value="autogol">Autogol</option>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:76px;">
                            <label>Min</label>
                            <input type="number" min="0" max="130" :name="'gol_visita['+index+'][minuto]'" x-model.number="gol.minuto">
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" style="margin-top:22px;" @click="golesVisita.splice(index,1)">✕</button>
                    </div>
                </template>
                <button type="button" class="btn btn-outline btn-sm"
                        @click="golesVisita.push({jugador_id:0,tipo:'normal',minuto:null})">+ Agregar gol</button>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--color-gray-light);margin:20px 0;">

        <h2 class="section-title">🟨 Tarjetas</h2>
        <div class="grid grid-2">
            <div>
                <h3 class="section-title" style="font-size:0.92rem;"><?= h($partido['local_nombre']) ?></h3>
                <template x-for="(tarjeta, index) in tarjetasLocal" :key="index">
                    <div class="form-row" style="align-items:center;">
                        <div class="form-group">
                            <label>Jugador</label>
                            <select :name="'tarjetas_local['+index+'][jugador_id]'" x-model.number="tarjeta.jugador_id">
                                <option value="0">-- Selecciona --</option>
                                <?php foreach ($opcionesLocal as $j): ?>
                                    <option value="<?= (int) $j['id'] ?>"><?= h($j['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:120px;">
                            <label>Tipo</label>
                            <select :name="'tarjetas_local['+index+'][tipo]'" x-model="tarjeta.tipo">
                                <option value="amarilla">Amarilla</option>
                                <option value="roja">Roja</option>
                                <option value="doble_amarilla">Doble amarilla</option>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:76px;">
                            <label>Min</label>
                            <input type="number" min="0" max="130" :name="'tarjetas_local['+index+'][minuto]'" x-model.number="tarjeta.minuto">
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" style="margin-top:22px;" @click="tarjetasLocal.splice(index,1)">✕</button>
                    </div>
                </template>
                <button type="button" class="btn btn-outline btn-sm"
                        @click="tarjetasLocal.push({jugador_id:0,tipo:'amarilla',minuto:null})">+ Agregar tarjeta</button>
            </div>
            <div>
                <h3 class="section-title" style="font-size:0.92rem;"><?= h($partido['visita_nombre']) ?></h3>
                <template x-for="(tarjeta, index) in tarjetasVisita" :key="index">
                    <div class="form-row" style="align-items:center;">
                        <div class="form-group">
                            <label>Jugador</label>
                            <select :name="'tarjetas_visita['+index+'][jugador_id]'" x-model.number="tarjeta.jugador_id">
                                <option value="0">-- Selecciona --</option>
                                <?php foreach ($opcionesVisita as $j): ?>
                                    <option value="<?= (int) $j['id'] ?>"><?= h($j['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:120px;">
                            <label>Tipo</label>
                            <select :name="'tarjetas_visita['+index+'][tipo]'" x-model="tarjeta.tipo">
                                <option value="amarilla">Amarilla</option>
                                <option value="roja">Roja</option>
                                <option value="doble_amarilla">Doble amarilla</option>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:76px;">
                            <label>Min</label>
                            <input type="number" min="0" max="130" :name="'tarjetas_visita['+index+'][minuto]'" x-model.number="tarjeta.minuto">
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" style="margin-top:22px;" @click="tarjetasVisita.splice(index,1)">✕</button>
                    </div>
                </template>
                <button type="button" class="btn btn-outline btn-sm"
                        @click="tarjetasVisita.push({jugador_id:0,tipo:'amarilla',minuto:null})">+ Agregar tarjeta</button>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--color-gray-light);margin:20px 0;">

        <div class="form-group">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="3"><?= h($resultado['observaciones'] ?? '') ?></textarea>
        </div>

        <input type="hidden" name="accion_form" id="accion_form" value="borrador">
        <div class="actions">
            <button type="submit" class="btn btn-primary"
                    onclick="document.getElementById('accion_form').value='finalizar'"
                    style="font-size:1rem;padding:12px 32px;">
                ✅ Finalizar partido
            </button>
            <button type="submit" class="btn btn-outline"
                    onclick="document.getElementById('accion_form').value='borrador'">
                💾 Guardar borrador
            </button>
            <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/arbitro/dashboard.php">Cancelar</a>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ══════════════ SOLO LECTURA (programado / suspendido / wo) ══════════════ -->
<div class="card">
    <div class="grid grid-2">
        <div>
            <h2 class="section-title">Información del partido</h2>
            <table style="width:100%;border-collapse:collapse;">
                <?php foreach ([
                    'Jornada' => 'Jornada ' . (int) $partido['jornada_numero'],
                    'Fecha'   => $partido['jornada_fecha'] ?? '—',
                    'Hora'    => $partido['hora'] ? substr($partido['hora'], 0, 5) : '—',
                    'Cancha'  => $partido['cancha'] ?? '—',
                    'Estado'  => ucfirst(str_replace('_', ' ', $estado)),
                    'Árbitro' => $partido['arbitro_nombre'] ?? '—',
                ] as $label => $valor): ?>
                <tr style="border-bottom:1px solid var(--color-gray-light);">
                    <td style="padding:8px 12px 8px 0;font-weight:700;color:var(--color-gray);font-size:0.85rem;width:110px;"><?= h($label) ?></td>
                    <td style="padding:8px 0;"><?= h((string) $valor) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;">
            <?= team_badge($partido['local_nombre'], $partido['local_abrev'], $partido['local_color'], $partido['local_logo'], 48) ?>
            <span style="font-weight:800;font-size:1rem;"><?= h($partido['local_nombre']) ?></span>
            <span class="text-muted" style="font-size:1.1rem;font-weight:700;">vs</span>
            <?= team_badge($partido['visita_nombre'], $partido['visita_abrev'], $partido['visita_color'], $partido['visita_logo'], 48) ?>
            <span style="font-weight:800;font-size:1rem;"><?= h($partido['visita_nombre']) ?></span>
        </div>
    </div>

    <?php if ($estado !== 'en_curso'): ?>
    <div style="margin-top:20px;padding:14px 18px;background:#f9fafb;border-radius:8px;border:1px solid var(--color-gray-light);font-size:0.9rem;color:var(--color-gray);">
        Este partido está <strong><?= h(str_replace('_', ' ', $estado)) ?></strong>. Solo podrás registrar el resultado cuando el partido esté <strong>en curso</strong>.
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
