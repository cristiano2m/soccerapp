<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer']);

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) {
    redirect('/admin/torneo/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Token de seguridad inválido.');
    redirect('/admin/calendario/index.php');
}

try {
    $db->beginTransaction();
    // Regenerar: borrar jornadas existentes (cascada elimina partidos -> resultados/goles/tarjetas)
    $db->execute("DELETE FROM jornadas WHERE torneo_id = ?", [$torneo['id']]);
    $total = generar_round_robin($torneo['id']);
    $db->commit();
    set_flash('success', "Calendario generado: $total partidos creados.");
} catch (Exception $e) {
    $db->rollBack();
    set_flash('error', 'No se pudo generar el calendario: ' . $e->getMessage());
}

redirect('/admin/calendario/index.php');
