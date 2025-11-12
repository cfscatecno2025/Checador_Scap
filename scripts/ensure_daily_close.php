<?php
// C:\xampp\htdocs\Checador_Scap\scripts\ensure_daily_close.php
date_default_timezone_set('America/Mexico_City');

$ROOT = dirname(__DIR__);
$flag = $ROOT . '/scripts/.last_close';
$hoy  = (new DateTime('today'))->format('Y-m-d');

$ultima = is_file($flag) ? trim((string)@file_get_contents($flag)) : '';
$ultimaFecha = $ultima ? substr($ultima, 0, 10) : '';

if ($ultimaFecha !== $hoy) {
  $php    = 'C:\xampp\php\php.exe'; // fuerza la ruta de php.exe de XAMPP
  $script = $ROOT . '/scripts/cierre_dia.php';

  // Ejecuta cierre de AYER (por defecto del script)
  $cmd = "\"$php\" \"$script\"";
  @exec($cmd, $out, $code);

  @file_put_contents($flag, (new DateTime())->format('Y-m-d H:i:s'));
}
