<?php
// auth/require_login.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/app.php';

// auth/require_login.php  (al inicio del archivo, después de session_start())
date_default_timezone_set('America/Mexico_City');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/scripts/ensure_daily_close.php';


if (empty($_SESSION['uid'])) {
  // No autenticado → vuelve al login
  header('Location: ' . url('index.php'));
  exit;
}
