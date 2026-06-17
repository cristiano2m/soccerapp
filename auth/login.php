<?php
require_once __DIR__ . '/../config/bootstrap.php';

const MAX_INTENTOS = 5;
const VENTANA_MINUTOS = 15;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/login.php');
}

$db = Database::getInstance();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$email = trim($_POST['email'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$csrf = $_POST['csrf_token'] ?? '';

if (!validate_csrf_token($csrf)) {
    set_flash('error', 'Sesión expirada, por favor intenta nuevamente.');
    redirect('/login.php');
}

// Rate limiting: máximo 5 intentos por IP en 15 minutos
$intentos = $db->queryOne(
    "SELECT COUNT(*) AS total FROM login_attempts WHERE ip = ? AND intentado_at > (NOW() - INTERVAL ? MINUTE)",
    [$ip, VENTANA_MINUTOS]
);

if ((int) ($intentos['total'] ?? 0) >= MAX_INTENTOS) {
    set_flash('error', 'Demasiados intentos de inicio de sesión. Intenta de nuevo en unos minutos.');
    redirect('/login.php');
}

$usuario = $db->queryOne("SELECT * FROM usuarios WHERE email = ? AND activo = 1", [$email]);

if (!$usuario || !password_verify($password, $usuario['password'])) {
    $db->insert("INSERT INTO login_attempts (ip, email) VALUES (?, ?)", [$ip, $email]);
    set_flash('error', 'Email o contraseña incorrectos.');
    redirect('/login.php');
}

session_regenerate_id(true);
$_SESSION['user_id'] = $usuario['id'];
$_SESSION['rol'] = $usuario['rol'];
$_SESSION['nombre'] = $usuario['nombre'];
$_SESSION['expires'] = time() + SESSION_LIFETIME;

redirect('/admin/dashboard.php');
