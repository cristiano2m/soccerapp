<?php
// Punto de entrada común: config, sesión y helpers para todas las páginas
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
