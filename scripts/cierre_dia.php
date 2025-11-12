<?php
// C:\xampp\htdocs\Checador_Scap\scripts\cierre_dia.php
declare(strict_types=1);
session_start();

date_default_timezone_set('America/Mexico_City');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/conection/conexion.php';

$pdo = DB::conn();

// --------- Parámetros de forzado ---------
// Puedes llamarlo así por navegador:
//   /Checador_Scap/scripts/cierre_dia.php?hoy=1
//   /Checador_Scap/scripts/cierre_dia.php?fecha=2025-09-20
//
// CLI (Programador de Tareas):
//   php cierre_dia.php hoy
//   php cierre_dia.php 2025-09-20
//
$hoyFlag  = isset($_GET['hoy']) || (isset($argv[1]) && $argv[1] === 'hoy');
$fechaArg = $_GET['fecha'] ?? ($argv[1] ?? '');

// Fecha a cerrar:
if ($hoyFlag) {
  $aCerrar = new DateTime('today');
} elseif ($fechaArg) {
  $aCerrar = new DateTime($fechaArg);
} else {
  // Por defecto: AYER
  $aCerrar = (new DateTime('today'))->modify('-1 day');
}

$fechaCierre = $aCerrar->format('Y-m-d');
$dow = (int)$aCerrar->format('w'); // 0..6

// Carpetas/paths
$reportDir = $ROOT . '/reportes';
$reportRel = '/Checador_Scap/reportes';
if (!is_dir($reportDir)) { @mkdir($reportDir, 0777, true); }

$logFile = $ROOT . '/scripts/cierre.log';
function logmsg(string $m){ global $logFile; @file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] $m\n", FILE_APPEND); echo $m . PHP_EOL; }

logmsg("=== CIERRE INICIADO para fecha $fechaCierre ===");

// 1) Trae empleados con horas cargadas (siempre lee el estado *actual*)
$stmt = $pdo->query("
  SELECT e.id_empleado, e.codigo_empleado, e.nombre, e.apellido,
         e.hora_entrada, e.hora_salida
  FROM empleados e
  WHERE e.hora_entrada IS NOT NULL OR e.hora_salida IS NOT NULL
  ORDER BY e.codigo_empleado ASC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
logmsg('Empleados con marca: ' . count($rows));

// 2) Si hay registros, crea CSV e inserta histórico
if (!empty($rows)) {
  $csvName = "reporte_asistencias_{$fechaCierre}.csv";
  $csvPath = $reportDir . '/' . $csvName;
  $csvRel  = $reportRel . '/' . $csvName;

  $fp = fopen($csvPath, 'w');
  if ($fp === false) { logmsg('ERROR creando CSV'); http_response_code(500); exit('No se pudo crear CSV'); }
  fputcsv($fp, ['fecha','id_empleado','codigo_empleado','nombre','apellido','hora_entrada','hora_salida','retardo_minutos','estado']);

  // hist + horario
  $insHist = $pdo->prepare("
    INSERT INTO asistencias_historico (id_empleado, fecha, hora_entrada, hora_salida, retardo_minutos, estado)
    VALUES (:emp, :fecha, :ent, :sal, :ret, :est)
  ");
  $qh = $pdo->prepare("
    SELECT entrada, tolerancia_minutos, activo
    FROM horarios
    WHERE id_empleado = :emp AND dia_semana = :d
    LIMIT 1
  ");

  foreach ($rows as $r) {
    $idEmp    = (int)$r['id_empleado'];
    $entrada  = $r['hora_entrada'] ?? null;
    $salida   = $r['hora_salida'] ?? null;

    $qh->execute([':emp'=>$idEmp, ':d'=>$dow]);
    $h = $qh->fetch(PDO::FETCH_ASSOC);

    $retardoMin = 0; $estado = 'normal';
    if ($h && (int)$h['activo'] === 1 && !empty($h['entrada']) && !empty($entrada)) {
      $prog = DateTime::createFromFormat('H:i:s', $h['entrada']) ?: DateTime::createFromFormat('H:i', $h['entrada']);
      $real = DateTime::createFromFormat('H:i:s', $entrada)      ?: DateTime::createFromFormat('H:i', $entrada);
      if ($prog && $real) {
        $diff = ($real->getTimestamp() - $prog->getTimestamp()) / 60; // mins
        $tol  = (int)$h['tolerancia_minutos'];
        $retardoMin = max(0, (int)ceil($diff) - $tol);
        if ($retardoMin > 0) $estado = 'retardo';
      }
    } else {
      $estado = 'sin_horario';
    }

    // insert hist
    $insHist->execute([
      ':emp'=>$idEmp, ':fecha'=>$fechaCierre, ':ent'=>$entrada, ':sal'=>$salida,
      ':ret'=>$retardoMin, ':est'=>$estado
    ]);

    fputcsv($fp, [$fechaCierre,$idEmp,$r['codigo_empleado'],$r['nombre'],$r['apellido'],$entrada ?: '',$salida ?: '',$retardoMin,$estado]);
  }
  fclose($fp);
  logmsg("CSV generado: $csvPath");

  // registra en reportes
  $insRep = $pdo->prepare("
    INSERT INTO reportes (tipo_reporte, fecha_inicio, fecha_fin, generado_por, fecha_generacion, archivo_reporte)
    VALUES ('diario', :fi, :ff, :uid, NOW(), :archivo)
  ");
  $uid = (int)($_SESSION['uid'] ?? 0);
  $insRep->execute([':fi'=>$fechaCierre, ':ff'=>$fechaCierre, ':uid'=>$uid, ':archivo'=>$csvRel]);
  logmsg("Fila creada en reportes para $fechaCierre");
} else {
  logmsg('No había marcas; no se creó CSV ni histórico.');
}

// 3) Limpia horas para nuevo día
$pdo->exec("UPDATE empleados SET hora_entrada = NULL, hora_salida = NULL");
logmsg('Horas limpiadas en empleados.');

// 4) Sella última ejecución
@file_put_contents($ROOT . '/scripts/.last_close', (new DateTime())->format('Y-m-d H:i:s'));
logmsg('=== CIERRE FINALIZADO ===');

echo "OK";
