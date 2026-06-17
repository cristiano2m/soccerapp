<?php
// Página no encontrada
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/bootstrap.php';
}
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 · No encontrado</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body>
    <div class="container" style="padding-top:80px; text-align:center;">
        <h1 style="font-size:3rem;">404</h1>
        <p class="text-muted">La página que buscas no existe.</p>
        <p style="margin-top:20px;"><a class="btn btn-primary" href="<?= BASE_URL ?>/index.php">Volver al inicio</a></p>
    </div>
</body>
</html>
