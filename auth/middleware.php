<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Verifica sesión activa y rol permitido; redirige o muestra 403 si no cumple.
// super_admin siempre pasa. Otros roles requieren un torneo activo en sesión
// y que su torneo_rol esté dentro del array $roles.
function require_role(array $roles): void
{
    if (!is_logged_in()) {
        redirect('/login.php?ref=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }

    if (($_SESSION['expires'] ?? 0) < time()) {
        session_destroy();
        redirect('/login.php?msg=session_expired');
    }

    $_SESSION['expires'] = time() + SESSION_LIFETIME;

    // super_admin siempre tiene acceso
    if (($_SESSION['rol'] ?? '') === 'super_admin') {
        return;
    }

    // Otros roles necesitan un torneo seleccionado en sesión
    if (empty($_SESSION['torneo_id'])) {
        redirect('/admin/dashboard.php');
    }

    // Verificar que el rol del usuario en este torneo sea el requerido
    if (!in_array($_SESSION['torneo_rol'] ?? '', $roles, true)) {
        http_response_code(403);
        require __DIR__ . '/../views/errors/403.php';
        exit;
    }
}
