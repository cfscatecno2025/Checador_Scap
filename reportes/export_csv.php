<?php
// C:\xampp\htdocs\Checador_Scap\reportes\export_csv.php
require_once __DIR__ . '/_common.php';
$fi = $_GET['fi'] ?? (new DateTime('today'))->format('Y-m-d');
$ff = $_GET['ff'] ?? $fi;

[$rows, ] = obtener_asistencias($fi, $ff);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="reporte_asistencias_'.$fi.'_a_'.$ff.'.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['fecha','codigo','nombre','apellido','entrada','salida','retardo_min','estado']);
foreach ($rows as $r) {
  fputcsv($out, [
    $r['fecha'],$r['codigo_empleado'],$r['nombre'],$r['apellido'],
    $r['hora_entrada'],$r['hora_salida'],(int)$r['retardo_minutos'],$r['estado']
  ]);
}
fclose($out);
