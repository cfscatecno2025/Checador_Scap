<?php
// config/app.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Ajusta a tu ruta base real
define('BASE_URL', '/Checador_Scap');

// helper simple
function url(string $path = ''): string {
  $path = ltrim($path, '/');
  return BASE_URL . ($path ? "/$path" : '');
}
