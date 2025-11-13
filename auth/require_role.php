<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/app.php';

function require_role(array $allowed) {
  // normaliza lista permitida y el rol en sesión
  $allowed = array_map(fn($r)=> strtolower(trim($r)), $allowed);
  $role    = strtolower(trim($_SESSION['rol'] ?? ''));

  if (!in_array($role, $allowed, true)) {
    header('Location: ' . url('no-autorizado.php'));
    exit;
  }
}
