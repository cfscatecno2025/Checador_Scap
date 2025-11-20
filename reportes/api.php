<?php
/**
 * /Checador_Scap/reportes/api.php
 * Endpoints: list, generate (PDF con Dompdf) y pdf (stream visor).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/auth/require_login.php';
require_once $ROOT . '/auth/require_role.php';
require_role(['admin']);
require_once $ROOT . '/conection/conexion.php';

function ok($d = []) { echo json_encode(['ok' => true] + $d); exit; }
function bad($m = 'Error') { echo json_encode(['ok' => false, 'msg' => $m]); exit; }

/* --- Parámetros y CSRF --- */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
  if (($_SERVER['HTTP_X_CSRF'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    bad('CSRF inválido');
  }
}

$pdo = DB::conn();
$action = $_GET['action'] ?? '' ;

/* === Helpers === */
function toDate($s){ $s = trim((string)$s); return preg_match('/^\d{4}-\d{2}-\d{2}$/',$s)?$s:null; }
function safe_join($base, $path){
  $real_base = realpath($base);
  $user_path = realpath($base . '/' . ltrim($path,'/'));
  if (!$real_base || !$user_path || strpos($user_path, $real_base) !== 0) return null;
  return $user_path;
}
function between_dates_iter($start, $end){
  $a = []; $t = strtotime($start); $u = strtotime($end);
  for($x=$t; $x<=$u; $x+=86400) $a[] = date('Y-m-d',$x);
  return $a;
}

/* ================= LIST ================= */
if ($action === 'list') {
  $page  = max(1, (int)($_GET['page'] ?? 1));
  $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
  $offset = ($page - 1) * $limit;

  $st = $pdo->query("SELECT COUNT(*) FROM reportes");
  $total = (int)$st->fetchColumn();
  $pages = max(1, (int)ceil($total / $limit));

  $st = $pdo->prepare("
    SELECT r.id_reporte, r.tipo_reporte, r.fecha_inicio, r.fecha_fin,
           r.generado_por, r.fecha_generacion, r.archivo_reporte,
           e.nombre, e.apellido, e.id_empleado AS enlace
      FROM reportes r
      LEFT JOIN empleados e ON e.id_empleado = r.generado_por
     ORDER BY r.id_reporte DESC
     LIMIT :limit OFFSET :offset
  ");
  $st->bindValue(':limit', $limit, PDO::PARAM_INT);
  $st->bindValue(':offset', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  ok(['rows'=>$rows,'total'=>$total,'pages'=>$pages,'page'=>$page]);
}

/* ================= GENERATE ================= */
elseif ($action === 'generate' && $method === 'POST') {
  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data)) bad('JSON inválido');

  $tipo   = strtolower(trim($data['tipo_reporte'] ?? ''));
  $start  = toDate($data['start'] ?? '');
  $end    = toDate($data['end'] ?? '');
  $enlace = $data['enlace'] !== null ? trim($data['enlace']) : null; // opcional

  if (!in_array($tipo, ['diario','semanal','quincenal','mensual'], true)) bad('Tipo inválido');
  if (!$start || !$end) bad('Rango inválido');
  if (strtotime($end) < strtotime($start)) bad('Rango invertido');

  // Generado por
  $generado_por = null;
  if (!empty($_SESSION['empleado_enlace'])) $generado_por = (int)$_SESSION['empleado_enlace'];
  if (!$generado_por && $enlace) $generado_por = (int)$enlace;

  // === 1) Empleados (filtrar por enlace si aplica)
  if ($enlace) {
    $qe = $pdo->prepare("SELECT id_empleado, nombre, apellido, unidad_medica, cargo, turno, hora_entrada, hora_salida
                           FROM empleados WHERE id_empleado = ?");
    $qe->execute([(int)$enlace]);
  } else {
    $qe = $pdo->query("SELECT id_empleado, nombre, apellido, unidad_medica, cargo, turno, hora_entrada, hora_salida
                         FROM empleados ORDER BY id_empleado");
  }
  $empleados = $qe->fetchAll(PDO::FETCH_ASSOC);
  if (!$empleados) bad('No hay empleados para ese filtro');

  // ID list para IN (...)
  $ids_in = implode(',', array_map(fn($e) => (int)$e['id_empleado'], $empleados));

  // === 2) Horario REAL desde tabla HORARIOS (HH:MM - HH:MM). Fallback a empleados si no hubiera.
  $horariosMap = [];
  $qh = $pdo->query("
    SELECT id_empleado,
           to_char(MIN(entrada), 'HH24:MI') AS he,
           to_char(MAX(salida) , 'HH24:MI') AS hs
      FROM horarios
     WHERE activo IS TRUE
       AND id_empleado IN ($ids_in)
     GROUP BY id_empleado
  ");
  foreach ($qh->fetchAll(PDO::FETCH_ASSOC) as $h) {
    $horariosMap[(int)$h['id_empleado']] = ['he'=>$h['he'], 'hs'=>$h['hs']];
  }

  // === 3) Checadas por día (para métricas asistencia/incompleta/retardo)
  $qch = $pdo->prepare("
    SELECT id_empleado, tipo, fecha_hora::date AS f,
           MIN(fecha_hora) AS primera,
           MAX(fecha_hora) AS ultima,
           MIN(retardo_minutos) AS min_ret
      FROM checadas
     WHERE fecha_hora::date BETWEEN ? AND ?
       AND id_empleado IN ($ids_in)
     GROUP BY id_empleado, tipo, f
  ");
  $qch->execute([$start, $end]);
  $rowsCh = $qch->fetchAll(PDO::FETCH_ASSOC);

  // Mapa: emp -> dia -> {entrada, salida, retardo}
  $byEmpDay = [];
  foreach ($rowsCh as $r) {
    $id = (int)$r['id_empleado']; $d = $r['f'];
    if (!isset($byEmpDay[$id][$d])) $byEmpDay[$id][$d] = ['entrada'=>null,'salida'=>null,'retardo'=>null];
    if ($r['tipo'] === 'ENTRADA') {
      $byEmpDay[$id][$d]['entrada'] = $r['primera'];
      $byEmpDay[$id][$d]['retardo'] = is_null($r['min_ret']) ? 0 : (int)$r['min_ret'];
    } elseif ($r['tipo'] === 'SALIDA') {
      $byEmpDay[$id][$d]['salida'] = $r['ultima'];
    }
  }

  // === 4) Primer ENTRADA y última SALIDA del rango (por empleado) para mostrar en el PDF
  $qfl = $pdo->prepare("
    SELECT id_empleado,
           to_char(MIN(CASE WHEN tipo='ENTRADA' THEN fecha_hora END),'YYYY-MM-DD HH24:MI') AS first_in,
           to_char(MAX(CASE WHEN tipo='SALIDA'  THEN fecha_hora END),'YYYY-MM-DD HH24:MI') AS last_out
      FROM checadas
     WHERE fecha_hora::date BETWEEN ? AND ?
       AND id_empleado IN ($ids_in)
     GROUP BY id_empleado
  ");
  $qfl->execute([$start, $end]);
  $firstLastMap = [];
  foreach ($qfl->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $firstLastMap[(int)$r['id_empleado']] = [
      'first_in' => $r['first_in'],
      'last_out' => $r['last_out'],
    ];
  }

  // === 5) Justificantes en rango
  $qj = $pdo->prepare("
    SELECT id_empleado, fecha_inicio, fecha_fin
      FROM justificantes
     WHERE (fecha_fin >= ? AND fecha_inicio <= ?)
       AND id_empleado IN ($ids_in)
  ");
  $qj->execute([$start, $end]);
  $just = $qj->fetchAll(PDO::FETCH_ASSOC);

  $justMap = [];
  foreach ($just as $j) {
    $id = (int)$j['id_empleado'];
    $justMap[$id][] = ['ini'=>$j['fecha_inicio'], 'fin'=>$j['fecha_fin']];
  }
  $isJust = function($id,$d) use($justMap){
    if (empty($justMap[$id])) return false;
    foreach($justMap[$id] as $p){
      if ($d >= $p['ini'] && $d <= $p['fin']) return true;
    }
    return false;
  };

  // === 6) Consolidar métricas por empleado
  $days = between_dates_iter($start,$end);
  $summary = [];
  foreach ($empleados as $e) {
    $id = (int)$e['id_empleado'];
    $asist=0; $retardos=0; $incomp=0; $faltas=0; $jus=0;

    foreach ($days as $d){
      $rec = $byEmpDay[$id][$d] ?? null;
      if ($rec){
        if ($rec['entrada'] && $rec['salida']) {
          $asist++;
          if ((int)$rec['retardo'] > 0) $retardos++;
        } else {
          $incomp++;
        }
      } else {
        if ($isJust($id,$d)) $jus++;
        else $faltas++;
      }
    }

    // Horario preferente desde HORARIOS; fallback a columnas de empleados si no hay
    $he = $horariosMap[$id]['he'] ?? null;
    $hs = $horariosMap[$id]['hs'] ?? null;
    if (!$he && !empty($e['hora_entrada'])) $he = substr((string)$e['hora_entrada'], 0, 5);
    if (!$hs && !empty($e['hora_salida']))  $hs = substr((string)$e['hora_salida'] , 0, 5);
    $horario = ($he && $hs) ? ($he.' - '.$hs) : '—';

    $summary[] = [
      'id'            => $id,
      'nombre'        => $e['nombre'] ?? '',
      'apellido'      => $e['apellido'] ?? '',
      'unidad_medica' => $e['unidad_medica'] ?? '',
      'turno'         => $e['turno'] ?? '',
      'horario'       => $horario,
      'dias'          => count($days),
      'asistencias'   => $asist,
      'retardos'      => $retardos,
      'incompletas'   => $incomp,
      'faltas'        => $faltas,
      'justificadas'  => $jus,
      'first_in'      => $firstLastMap[$id]['first_in'] ?? '—',
      'last_out'      => $firstLastMap[$id]['last_out'] ?? '—',
    ];
  }

  // ==== Render PDF con Dompdf ====
  require_once $ROOT . '/vendor/autoload.php';
  $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

  $DOC_NAME = 'REPORTE DE ASISTENCIAS';
  $DOC_CODE = 'PA-DAP-GRH_FR_14';
  $DOC_VER  = 'V02';
  $ORG_STR  = 'SISTEMA DE GESTIÓN DE LA CALIDAD - ISSTECH';

  $logoLeft  = $ROOT . '/assets/logos/logo_gob.png';
  $logoRight = $ROOT . '/assets/logos/logo_isstech.png';
  $logoLeftURL  = file_exists($logoLeft)  ? ('file://'.$logoLeft)  : null;
  $logoRightURL = file_exists($logoRight) ? ('file://'.$logoRight) : null;

  $genName = '';
  if ($generado_por){
    $qe = $pdo->prepare("SELECT nombre, apellido FROM empleados WHERE id_empleado=?");
    $qe->execute([$generado_por]); $g = $qe->fetch(PDO::FETCH_ASSOC);
    if ($g) $genName = trim(($g['nombre']??'').' '.($g['apellido']??''));
  }

  ob_start();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    /* Márgenes más compactos y página horizontal */
    @page { margin: 14mm 10mm 16mm 10mm; size: A4 landscape; }

    body{ font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color:#111; }
    .enc{ border:1px solid #000; }
    .enc td{ border:1px solid #000; padding:4px 6px; }
    .cen{ text-align:center; }
    .mono{ font-variant-numeric: tabular-nums; }
    .title{ font-size:15px; font-weight:700; margin:10px 0 6px 0; }
    .meta{ margin: 6px 0 12px; font-size: 10px; }
    .muted{ color:#555; }

    table { border-collapse: collapse; width:100%; table-layout: fixed; }
    th, td { border: 1px solid #bbb; padding: 4px 6px; line-height: 1.25; }
    th { background: #f3f5f9; }
    .nowrap{ white-space:nowrap; }
    .wrap{ white-space: normal; word-break: break-word; overflow-wrap: anywhere; }
  </style>
</head>
<body>

<!-- Encabezado -->
<table class="enc" width="100%" cellspacing="0" cellpadding="0">
  <tr>
    <td style="width:18%;text-align:center">
      <?php if ($logoLeftURL): ?><img src="<?= htmlspecialchars($logoLeftURL) ?>" style="height:54px"><?php endif; ?>
    </td>
    <td class="cen" style="width:64%; font-weight:700;"><?= htmlspecialchars($ORG_STR) ?></td>
    <td style="width:18%;text-align:center">
      <?php if ($logoRightURL): ?><img src="<?= htmlspecialchars($logoRightURL) ?>" style="height:54px"><?php endif; ?>
    </td>
  </tr>
  <tr>
    <td class="cen" style="font-weight:700">Nombre del documento</td>
    <td class="cen" rowspan="2" style="font-size:13px; font-weight:700">
      <?= htmlspecialchars($DOC_NAME) ?>
    </td>
    <td class="cen" style="font-weight:700">Versión</td>
  </tr>
  <tr>
    <td class="cen" style="font-size:12px"><?= htmlspecialchars($DOC_CODE) ?></td>
    <td class="cen" style="font-size:12px"><?= htmlspecialchars($DOC_VER) ?></td>
  </tr>
</table>

<div class="title"><?= htmlspecialchars(strtoupper($DOC_NAME . ' — ' . $tipo)) ?></div>
<div class="meta">
  <strong>Rango:</strong> <span class="mono"><?= htmlspecialchars($start) ?> — <?= htmlspecialchars($end) ?></span>
  &nbsp; | &nbsp; <strong>Generado:</strong> <span class="mono"><?= htmlspecialchars($now) ?></span>
  <?php if ($generado_por): ?>&nbsp; | &nbsp; <strong>Por:</strong> <?= htmlspecialchars($genName?:('#'.$generado_por)) ?><?php endif; ?>
</div>

<table>
  <!-- Ancho fijo por columna para que no se desborde -->
  <colgroup>
    <col style="width:6%">
    <col style="width:17%">
    <col style="width:10%">
    <col style="width:9%">
    <col style="width:9%">
    <col style="width:9%">
    <col style="width:9%">
    <col style="width:4%">
    <col style="width:5%">
    <col style="width:6%">
    <col style="width:6%">
    <col style="width:5%">
    <col style="width:6%">
  </colgroup>
  <thead>
    <tr>
      <th class="nowrap">Enlace</th>
      <th>Empleado</th>
      <th>Unidad</th>
      <th>Turno</th>
      <th>Horario</th>
      <th class="mono nowrap">1ª Entrada</th>
      <th class="mono nowrap">Últ. Salida</th>
      <th class="mono">Días</th>
      <th class="mono">Asist.</th>
      <th class="mono">Retardos</th>
      <th class="mono">Incompl.</th>
      <th class="mono">Faltas</th>
      <th class="mono">Justif.</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($summary as $s): ?>
    <tr>
      <td class="mono nowrap"><?= (int)$s['id'] ?></td>
      <td class="wrap"><?= htmlspecialchars(trim($s['nombre'].' '.$s['apellido'])) ?></td>
      <td class="wrap"><?= htmlspecialchars($s['unidad_medica']) ?></td>
      <td class="wrap"><?= htmlspecialchars($s['turno']) ?></td>
      <td class="mono nowrap"><?= htmlspecialchars($s['horario']) ?></td>
      <td class="mono nowrap"><?= htmlspecialchars($s['first_in']) ?></td>
      <td class="mono nowrap"><?= htmlspecialchars($s['last_out']) ?></td>
      <td class="mono" style="text-align:right"><?= (int)$s['dias'] ?></td>
      <td class="mono" style="text-align:right"><?= (int)$s['asistencias'] ?></td>
      <td class="mono" style="text-align:right"><?= (int)$s['retardos'] ?></td>
      <td class="mono" style="text-align:right"><?= (int)$s['incompletas'] ?></td>
      <td class="mono" style="text-align:right"><?= (int)$s['faltas'] ?></td>
      <td class="mono" style="text-align:right"><?= (int)$s['justificadas'] ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<p class="muted" style="margin-top:8px">
  Nota: “Incompleta” indica marca parcial (solo entrada o solo salida) en el día.
  Las faltas justificadas se determinan por los rangos cargados en <em>justificantes</em>.
</p>

</body>
</html>
<?php
$html = ob_get_clean();

  $dompdf = new Dompdf\Dompdf([
    'isRemoteEnabled' => true,
    'defaultFont' => 'DejaVu Sans',
  ]);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'landscape');
  $dompdf->render();

  // Guardar archivo
  $dir = $ROOT . '/assets/reportes';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  $fname = sprintf('reporte_%s_%s_%s_%s.pdf',
    $tipo, $start, $end, substr(bin2hex(random_bytes(4)),0,8)
  );
  $path = $dir . '/' . $fname;
  file_put_contents($path, $dompdf->output());

  // Registrar en BD
  $ins = $pdo->prepare("
    INSERT INTO reportes (tipo_reporte, fecha_inicio, fecha_fin, generado_por, fecha_generacion, archivo_reporte)
    VALUES (?,?,?,?, NOW(), ?)
    RETURNING id_reporte
  ");
  $ins->execute([$tipo, $start, $end, $generado_por, '/assets/reportes/'.$fname]);
  $id = (int)$ins->fetchColumn();

  ok(['id_reporte'=>$id, 'url'=>'/assets/reportes/'.$fname]);
}

/* ================= PDF STREAM ================= */
elseif ($action === 'pdf') {
  $id = (int)($_GET['id'] ?? 0);
  if (!$id) { http_response_code(400); echo 'ID inválido'; exit; }

  $st = $pdo->prepare("SELECT archivo_reporte FROM reportes WHERE id_reporte=?");
  $st->execute([$id]);
  $rel = $st->fetchColumn();
  if (!$rel) { http_response_code(404); echo 'No encontrado'; exit; }

  $file = safe_join($ROOT, $rel);
  if (!$file || !file_exists($file)) { http_response_code(404); echo 'Archivo no disponible'; exit; }

  // Stream como PDF para visor del navegador
  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="'.basename($file).'"');
  header('Content-Length: ' . filesize($file));
  readfile($file);
  exit;
}

else { bad('Acción no soportada'); }
