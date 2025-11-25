<?php
// C:\xampp\htdocs\Checador_Scap\logout.php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// borrar variables de sesión
$_SESSION = [];

// destruir cookie de sesión (opcional pero recomendado)
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
  );
}

// destruir sesión
session_destroy();

// redirigir al login
header('Location: /Checador_Scap/acceso/empleados.php');
exit;
