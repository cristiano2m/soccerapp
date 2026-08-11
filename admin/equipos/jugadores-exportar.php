<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin', 'organizer']);

$db = Database::getInstance();
$torneo = obtener_torneo_activo();

if (!$torneo) {
    set_flash('error', 'Primero debes configurar el torneo.');
    redirect('/admin/torneo/index.php');
}

$soloActivos = ($_GET['activos'] ?? '1') !== '0';
$equipoFiltro = (int) ($_GET['equipo_id'] ?? 0);

$sql = "SELECT j.id, j.nombre, j.numero, j.posicion, j.cedula, j.activo, j.foto_url,
               e.nombre AS equipo_nombre
        FROM jugadores j
        JOIN equipos e ON e.id = j.equipo_id
        WHERE e.torneo_id = ?";
$params = [$torneo['id']];

if ($soloActivos) {
    $sql .= " AND j.activo = 1";
}
if ($equipoFiltro > 0) {
    $sql .= " AND e.id = ?";
    $params[] = $equipoFiltro;
}

$sql .= " ORDER BY e.nombre ASC, j.numero ASC";

$jugadores = $db->query($sql, $params);

$nombreArchivo = 'jugadores_' . preg_replace('/[^a-z0-9]+/i', '_', $torneo['nombre']) . '_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');
// BOM para compatibilidad con Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['equipo', 'nombre', 'numero', 'posicion', 'cedula', 'activo', 'foto', 'url_publica']);

foreach ($jugadores as $j) {
    fputcsv($out, [
        $j['equipo_nombre'],
        $j['nombre'],
        $j['numero'],
        $j['posicion'],
        $j['cedula'],
        $j['activo'] ? 1 : 0,
        $j['foto_url'] ? basename($j['foto_url']) : '',
        BASE_URL . '/public/jugador.php?id=' . (int) $j['id'],
    ]);
}

fclose($out);
exit;
