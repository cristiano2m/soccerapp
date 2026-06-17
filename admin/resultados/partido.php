<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer']);

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) {
    redirect('/admin/torneo/index.php');
}

$id = (int) ($_GET['id'] ?? 0);
$partido = $db->queryOne(
    "SELECT p.*, j.numero AS jornada_numero,
            el.nombre AS local_nombre, el.color_hex AS local_color, el.logo_url AS local_logo, el.abreviatura AS local_abrev,
            ev.nombre AS visita_nombre, ev.color_hex AS visita_color, ev.logo_url AS visita_logo, ev.abreviatura AS visita_abrev
     FROM partidos p
     JOIN jornadas j ON j.id = p.jornada_id
     JOIN equipos el ON el.id = p.equipo_local_id
     JOIN equipos ev ON ev.id = p.equipo_visita_id
     WHERE p.id = ? AND p.torneo_id = ?",
    [$id, $torneo['id']]
);

if (!$partido) {
    set_flash('error', 'Partido no encontrado.');
    redirect('/admin/resultados/index.php');
}

$jugadoresLocal = $db->query("SELECT id, nombre, numero FROM jugadores WHERE equipo_id = ? AND activo = 1 ORDER BY numero ASC", [$partido['equipo_local_id']]);
$jugadoresVisita = $db->query("SELECT id, nombre, numero FROM jugadores WHERE equipo_id = ? AND activo = 1 ORDER BY numero ASC", [$partido['equipo_visita_id']]);
$jugadorEquipo = [];
foreach ($jugadoresLocal as $j) { $jugadorEquipo[$j['id']] = $partido['equipo_local_id']; }
foreach ($jugadoresVisita as $j) { $jugadorEquipo[$j['id']] = $partido['equipo_visita_id']; }

$errors = [];
$resultado = $db->queryOne("SELECT * FROM resultados WHERE partido_id = ?", [$partido['id']]);
$golesExistentes = $db->query("SELECT * FROM goles WHERE partido_id = ? ORDER BY id ASC", [$partido['id']]);
$tarjetasExistentes = $db->query("SELECT * FROM tarjetas WHERE partido_id = ? ORDER BY id ASC", [$partido['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de seguridad inválido. Intenta de nuevo.';
    } else {
        $woLocal = isset($_POST['wo_local']);
        $woVisita = isset($_POST['wo_visita']);
        $golesLocalInput = (int) ($_POST['goles_local'] ?? 0);
        $golesVisitaInput = (int) ($_POST['goles_visita'] ?? 0);
        $observaciones = trim($_POST['observaciones'] ?? '');

        $tiposGol = ['normal', 'penalti', 'autogol'];
        $tiposTarjeta = ['amarilla', 'roja', 'doble_amarilla'];

        $golesPost    = [...array_values($_POST['gol_local']      ?? []), ...array_values($_POST['gol_visita']      ?? [])];
        $tarjetasPost = [...array_values($_POST['tarjetas_local'] ?? []), ...array_values($_POST['tarjetas_visita'] ?? [])];

        $golesInput = [];
        foreach ($golesPost as $g) {
            $jugadorId = (int) ($g['jugador_id'] ?? 0);
            $tipo = $g['tipo'] ?? 'normal';
            $minuto = $g['minuto'] !== '' ? (int) $g['minuto'] : null;
            if ($jugadorId <= 0) {
                continue;
            }
            if (!isset($jugadorEquipo[$jugadorId])) {
                $errors[] = 'Uno de los goles tiene un jugador no válido para este partido.';
                continue;
            }
            if (!in_array($tipo, $tiposGol, true)) {
                $errors[] = 'Tipo de gol no válido.';
                continue;
            }
            if ($minuto !== null && ($minuto < 0 || $minuto > 130)) {
                $errors[] = 'El minuto del gol debe estar entre 0 y 130.';
                continue;
            }
            $equipoJugador = $jugadorEquipo[$jugadorId];
            $equipoGol = $tipo === 'autogol'
                ? ($equipoJugador === (int) $partido['equipo_local_id'] ? (int) $partido['equipo_visita_id'] : (int) $partido['equipo_local_id'])
                : $equipoJugador;
            $golesInput[] = ['jugador_id' => $jugadorId, 'equipo_id' => $equipoGol, 'minuto' => $minuto, 'tipo' => $tipo];
        }

        $tarjetasInput = [];
        foreach ($tarjetasPost as $t) {
            $jugadorId = (int) ($t['jugador_id'] ?? 0);
            $tipo = $t['tipo'] ?? 'amarilla';
            $minuto = $t['minuto'] !== '' ? (int) $t['minuto'] : null;
            if ($jugadorId <= 0) {
                continue;
            }
            if (!isset($jugadorEquipo[$jugadorId])) {
                $errors[] = 'Una de las tarjetas tiene un jugador no válido para este partido.';
                continue;
            }
            if (!in_array($tipo, $tiposTarjeta, true)) {
                $errors[] = 'Tipo de tarjeta no válido.';
                continue;
            }
            if ($minuto !== null && ($minuto < 0 || $minuto > 130)) {
                $errors[] = 'El minuto de la tarjeta debe estar entre 0 y 130.';
                continue;
            }
            $tarjetasInput[] = ['jugador_id' => $jugadorId, 'tipo' => $tipo, 'minuto' => $minuto];
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
                if ((int) $g['equipo_id'] === (int) $partido['equipo_local_id']) $golesLocal++;
                else $golesVisita++;
            }
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $db->execute(
                    "INSERT INTO resultados (partido_id, goles_local, goles_visita, wo_local, wo_visita, observaciones, registrado_por)
                     VALUES (?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE goles_local=?, goles_visita=?, wo_local=?, wo_visita=?, observaciones=?, registrado_por=?",
                    [
                        $partido['id'], $golesLocal, $golesVisita, (int) $woLocal, (int) $woVisita, $observaciones, current_user()['id'],
                        $golesLocal, $golesVisita, (int) $woLocal, (int) $woVisita, $observaciones, current_user()['id'],
                    ]
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

                $db->execute("UPDATE partidos SET estado = 'finalizado' WHERE id = ?", [$partido['id']]);

                $db->commit();
                set_flash('success', 'Resultado registrado correctamente.');
                redirect('/admin/resultados/index.php');
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'No se pudo guardar el resultado: ' . $e->getMessage();
            }
        }

        // Re-render con los valores enviados
        $resultado = ['goles_local' => $golesLocal, 'goles_visita' => $golesVisita, 'wo_local' => (int) $woLocal, 'wo_visita' => (int) $woVisita, 'observaciones' => $observaciones];
        $golesExistentes = array_map(fn($g) => ['jugador_id' => $g['jugador_id'], 'minuto' => $g['minuto'], 'tipo' => $g['tipo']], $golesInput);
        $tarjetasExistentes = $tarjetasInput;
    }
}

// Opciones de jugador por equipo, para los selects de cada columna
$opcionesLocal = array_map(fn($j) => ['id' => $j['id'], 'label' => '#' . $j['numero'] . ' ' . $j['nombre']], $jugadoresLocal);
$opcionesVisita = array_map(fn($j) => ['id' => $j['id'], 'label' => '#' . $j['numero'] . ' ' . $j['nombre']], $jugadoresVisita);

// Separar goles/tarjetas existentes por equipo del jugador, para mostrarlos en su columna correspondiente
$golesLocalExistentes = [];
$golesVisitaExistentes = [];
foreach ($golesExistentes as $g) {
    $entry = ['jugador_id' => (int) $g['jugador_id'], 'tipo' => $g['tipo'], 'minuto' => $g['minuto']];
    if (($jugadorEquipo[$g['jugador_id']] ?? null) === (int) $partido['equipo_local_id']) {
        $golesLocalExistentes[] = $entry;
    } else {
        $golesVisitaExistentes[] = $entry;
    }
}

$tarjetasLocalExistentes = [];
$tarjetasVisitaExistentes = [];
foreach ($tarjetasExistentes as $t) {
    $entry = ['jugador_id' => (int) $t['jugador_id'], 'tipo' => $t['tipo'], 'minuto' => $t['minuto']];
    if (($jugadorEquipo[$t['jugador_id']] ?? null) === (int) $partido['equipo_local_id']) {
        $tarjetasLocalExistentes[] = $entry;
    } else {
        $tarjetasVisitaExistentes[] = $entry;
    }
}

$pageTitle = 'Registrar resultado';
$layout = 'admin';
require __DIR__ . '/../../views/layout/header.php';
require __DIR__ . '/../../views/layout/sidebar-admin.php';
?>
<h1><span class="ms ms-lg">sports_soccer</span> Registrar resultado</h1>
<p class="text-muted" style="margin-bottom:16px;">Jornada <?= (int) $partido['jornada_numero'] ?> · <?= h($partido['local_nombre']) ?> vs <?= h($partido['visita_nombre']) ?></p>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="card"
     x-data='{
        golesLocal: <?= json_encode(array_values($golesLocalExistentes), JSON_UNESCAPED_UNICODE) ?>,
        golesVisita: <?= json_encode(array_values($golesVisitaExistentes), JSON_UNESCAPED_UNICODE) ?>,
        tarjetasLocal: <?= json_encode(array_values($tarjetasLocalExistentes), JSON_UNESCAPED_UNICODE) ?>,
        tarjetasVisita: <?= json_encode(array_values($tarjetasVisitaExistentes), JSON_UNESCAPED_UNICODE) ?>,
        wo_local: <?= !empty($resultado['wo_local']) ? 'true' : 'false' ?>,
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
                <h3 class="section-title" style="font-size:0.95rem;"><?= h($partido['local_nombre']) ?></h3>
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
                        <div class="form-group" style="max-width:140px;">
                            <label>Tipo</label>
                            <select :name="'gol_local['+index+'][tipo]'" x-model="gol.tipo">
                                <option value="normal">Normal</option>
                                <option value="penalti">Penal</option>
                                <option value="autogol">Autogol</option>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:90px;">
                            <label>Minuto</label>
                            <input type="number" min="0" max="130" :name="'gol_local['+index+'][minuto]'" x-model.number="gol.minuto">
                        </div>
                        <div class="form-group" style="max-width:50px;">
                            <button type="button" class="btn btn-danger btn-sm" @click="golesLocal.splice(index, 1)">✕</button>
                        </div>
                    </div>
                </template>
                <button type="button" class="btn btn-outline btn-sm" @click="golesLocal.push({jugador_id: 0, tipo: 'normal', minuto: null})">+ Agregar gol</button>
            </div>
            <div>
                <h3 class="section-title" style="font-size:0.95rem;"><?= h($partido['visita_nombre']) ?></h3>
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
                        <div class="form-group" style="max-width:140px;">
                            <label>Tipo</label>
                            <select :name="'gol_visita['+index+'][tipo]'" x-model="gol.tipo">
                                <option value="normal">Normal</option>
                                <option value="penalti">Penal</option>
                                <option value="autogol">Autogol</option>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:90px;">
                            <label>Minuto</label>
                            <input type="number" min="0" max="130" :name="'gol_visita['+index+'][minuto]'" x-model.number="gol.minuto">
                        </div>
                        <div class="form-group" style="max-width:50px;">
                            <button type="button" class="btn btn-danger btn-sm" @click="golesVisita.splice(index, 1)">✕</button>
                        </div>
                    </div>
                </template>
                <button type="button" class="btn btn-outline btn-sm" @click="golesVisita.push({jugador_id: 0, tipo: 'normal', minuto: null})">+ Agregar gol</button>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--color-gray-light);margin:20px 0;">

        <h2 class="section-title">🟨 Tarjetas</h2>
        <div class="grid grid-2">
            <div>
                <h3 class="section-title" style="font-size:0.95rem;"><?= h($partido['local_nombre']) ?></h3>
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
                        <div class="form-group" style="max-width:140px;">
                            <label>Tipo</label>
                            <select :name="'tarjetas_local['+index+'][tipo]'" x-model="tarjeta.tipo">
                                <option value="amarilla">Amarilla</option>
                                <option value="roja">Roja</option>
                                <option value="doble_amarilla">Doble amarilla</option>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:90px;">
                            <label>Minuto</label>
                            <input type="number" min="0" max="130" :name="'tarjetas_local['+index+'][minuto]'" x-model.number="tarjeta.minuto">
                        </div>
                        <div class="form-group" style="max-width:50px;">
                            <button type="button" class="btn btn-danger btn-sm" @click="tarjetasLocal.splice(index, 1)">✕</button>
                        </div>
                    </div>
                </template>
                <button type="button" class="btn btn-outline btn-sm" @click="tarjetasLocal.push({jugador_id: 0, tipo: 'amarilla', minuto: null})">+ Agregar tarjeta</button>
            </div>
            <div>
                <h3 class="section-title" style="font-size:0.95rem;"><?= h($partido['visita_nombre']) ?></h3>
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
                        <div class="form-group" style="max-width:140px;">
                            <label>Tipo</label>
                            <select :name="'tarjetas_visita['+index+'][tipo]'" x-model="tarjeta.tipo">
                                <option value="amarilla">Amarilla</option>
                                <option value="roja">Roja</option>
                                <option value="doble_amarilla">Doble amarilla</option>
                            </select>
                        </div>
                        <div class="form-group" style="max-width:90px;">
                            <label>Minuto</label>
                            <input type="number" min="0" max="130" :name="'tarjetas_visita['+index+'][minuto]'" x-model.number="tarjeta.minuto">
                        </div>
                        <div class="form-group" style="max-width:50px;">
                            <button type="button" class="btn btn-danger btn-sm" @click="tarjetasVisita.splice(index, 1)">✕</button>
                        </div>
                    </div>
                </template>
                <button type="button" class="btn btn-outline btn-sm" @click="tarjetasVisita.push({jugador_id: 0, tipo: 'amarilla', minuto: null})">+ Agregar tarjeta</button>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--color-gray-light);margin:20px 0;">

        <div class="form-group">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" rows="3"><?= h($resultado['observaciones'] ?? '') ?></textarea>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Guardar resultado</button>
            <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/resultados/index.php">Cancelar</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../../views/layout/footer.php'; ?>
