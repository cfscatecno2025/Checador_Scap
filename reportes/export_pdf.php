<?php
// C:\xampp\htdocs\Checador_Scap\reportes\export_pdf.php
require_once __DIR__ . '/_common.php';
$fi = $_GET['fi'] ?? (new DateTime('today'))->format('Y-m-d');
$ff = $_GET['ff'] ?? $fi;

[$rows, ] = obtener_asistencias($fi, $ff);

ob_start(); ?>
<!doctype html>
<html><head><meta charset="utf-8">
<style>
  body{font-family:DejaVu Sans, Arial, sans-serif; font-size:12px}
  h2{margin:0 0 8px}
  .muted{color:#666}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid #ddd;padding:6px;text-align:left}
  th{background:#f0f0f0}
</style>
</head><body>
  <h2>Reporte de Asistencias</h2>
  <div class="muted">Rango: <?= h($fi) ?> a <?= h($ff) ?></div><br>
  <table>
    <thead><tr>
      <th>Fecha</th><th>Código</th><th>Nombre</th><th>Apellido</th>
      <th>Entrada</th><th>Salida</th><th>Retardo (min)</th><th>Estado</th>
    </tr></thead>
    <tbody>
    <?php if ($rows): foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['fecha']) ?></td>
        <td><?= h($r['codigo_empleado']) ?></td>
        <td><?= h($r['nombre']) ?></td>
        <td><?= h($r['apellido']) ?></td>
        <td><?= h($r['hora_entrada']) ?></td>
        <td><?= h($r['hora_salida']) ?></td>
        <td><?= (int)$r['retardo_minutos'] ?></td>
        <td><?= h($r['estado']) ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="8">Sin datos</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</body></html>
<?php
$html = ob_get_clean();

$autoload = $ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
  require_once $autoload;  // composer require dompdf/dompdf
  $dompdf = new Dompdf\Dompdf(['isRemoteEnabled'=>true]);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4','portrait');
  $dompdf->render();
  $dompdf->stream('reporte_asistencias_'.$fi.'_a_'.$ff.'.pdf', ['Attachment'=>false]);
  exit;
} else {
  header('Content-Type: text/html; charset=utf-8');
  echo $html; // fallback imprimible
}
