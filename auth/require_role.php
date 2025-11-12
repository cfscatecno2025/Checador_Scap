<?php
// auth/require_role.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/app.php';

/**
 * Permite el acceso si el rol del usuario está en la lista.
 * Ejemplo:
 *   require_once 'auth/require_role.php';
 *   require_role(['admin']);
 */
function require_role(array $allowed) {
  $rol = $_SESSION['rol'] ?? '';
  if (!in_array($rol, $allowed, true)) {
    // Sin permiso → redirige (puedes mandar a un 403.php)
    header('Location: ' . url('no-autorizado.php'));
    exit;
  }
}
