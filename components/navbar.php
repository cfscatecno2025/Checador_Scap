<?php
// components/navbar.php
if (session_status() === PHP_SESSION_NONE) session_start();

$ROOT = dirname(__DIR__);
$rol  = strtolower(trim((string)($_SESSION['rol'] ?? '')));

// Carga el navbar correspondiente
$map = [
  'admin'    => $ROOT . '/components/navbar-admin.php',
];

$target = $map[$rol] ?? ($ROOT . '/components/navbar-usuario.php');
if (is_file($target)) {
  include $target;
} // Si no existe, simplemente no muestra navbar.
