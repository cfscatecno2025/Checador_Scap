<?php
/**
 * /Checador_Scap/asistencias/api.php
 * API JSON para listado de asistencias + subida de justificantes.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/auth/require_login.php';
require_once $ROOT . '/auth/require_role.php';
require_role(['admin']);
require_once $ROOT . '/conection/conexion.php';

/* === Zona horaria consistente con el resto del sistema === */
$TZ_ID = 'America/Mexico_City';
$TZ    = new DateTimeZone($TZ_ID);
@date_default_timezone_set($TZ_ID);

function ok($d = []) { echo json_encode(['ok' => true] + $d); exit; }
function bad($m = 'Error') { echo json_encode(['ok' => false, 'msg' => $m]); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
  if (($_SERVER['HTTP_X_CSRF'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    bad('CSRF inválido');
  }
}

$pdo = DB::conn();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* -------- helpers -------- */

function col_exists(PDO $pdo, string $table, string $col): bool {
  $st = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1");
  $st->execute([$table, $col]);
  return (bool)$st->fetchColumn();
}

/** Elige la columna timestamp de 'checadas' de forma segura */
function pick_ts_col(PDO $pdo): string {
  $cands = ['created_at','fecha','fecha_hora','marcado_en','ts','timestamp','fecha_marcaje'];
  foreach ($cands as $c) if (col_exists($pdo,'checadas',$c)) return $c;
  // si no hay coincidencia, intentamos obtener cualquier columna timestamp
  $st = $pdo->query("SELECT column_name FROM information_schema.columns
                      WHERE table_name='checadas'
                        AND data_type LIKE 'timestamp%' LIMIT 1");
  $c = $st ? $st->fetchColumn() : null;
  return $c ?: 'created_at';
}

function get_day_schedule(PDO $pdo, int $idEmp, int $dow): ?array {
  $st = $pdo->prepare("
    SELECT dia_semana, entrada, salida, tolerancia_minutos AS tolerancia_min, activo
    FROM horarios
    WHERE id_empleado = ? AND dia_semana = ? AND activo = TRUE
    LIMIT 1
  ");
  $st->execute([$idEmp, $dow]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}
function build_window(array $sch, string $ymd, DateTimeZone $tz): array {
  $start = new DateTimeImmutable($ymd . ' ' . $sch['entrada'], $tz);
  $end   = new DateTimeImmutable($ymd . ' ' . $sch['salida'],  $tz);
  if ($end <= $start) { $end = $end->modify('+1 day'); } // cruza medianoche
  return [$start, $end];
}
function hm(DateTimeImmutable $d): string { return $d->format('H:i'); }
function dowName(int $n): string {
  return ['LUN','MAR','MIÉ','JUE','VIE','SÁB','DOM'][$n-1] ?? '';
}

/* ---- bootstrap tabla de justificantes ---- */
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS justificantes (
      id_justificante SERIAL PRIMARY KEY,
      id_empleado     INTEGER NOT NULL,
      fecha_inicio    DATE NOT NULL,
      fecha_fin       DATE NOT NULL,
      motivo          TEXT NULL,
      archivo_url     TEXT NULL,
      created_at      TIMESTAMP NOT NULL DEFAULT NOW()
    );
    CREATE INDEX IF NOT EXISTS idx_just_emp_range ON justificantes(id_empleado, fecha_inicio, fecha_fin);
  ");
} catch (\Throwable $e) {
  // no bloqueamos, pero reportamos si falla
}

/* ======================= LIST ======================= */
if ($action === 'list') {
  $start = trim($_GET['start'] ?? '');
  $end   = trim($_GET['end'] ?? '');
  $empQ  = trim($_GET['emp'] ?? '');
  if ($start === '' || $end === '') bad('Rango de fechas requerido');

  // seguridad: rango máximo 62 días
  $sd = new DateTimeImmutable($start, $TZ);
  $ed = new DateTimeImmutable($end,   $TZ);
  if ($ed < $sd) bad('Rango inválido');
  if ($ed->getTimestamp() - $sd->getTimestamp() > 62*86400) {
    bad('Rango demasiado grande (máximo 62 días)');
  }

  $page  = max(1, (int)($_GET['page'] ?? 1));
  $limit = max(5, min(200, (int)($_GET['limit'] ?? 20)));
  $rows  = [];

  // 1) Empleados (filtrados por enlace si se indicó)
  $params = [];
  $where = '';
  if ($empQ !== '') {
    $where = "WHERE (CAST(id_empleado AS TEXT) = :emp OR codigo_empleado = :emp)";
    $params[':emp'] = $empQ;
  }
  $empSt = $pdo->prepare("
    SELECT id_empleado, codigo_empleado, nombre, apellido, unidad_medica, turno, cargo
    FROM empleados
    $where
    ORDER BY id_empleado
  ");
  foreach($params as $k=>$v) $empSt->bindValue($k,$v);
  $empSt->execute();
  $emps = $empSt->fetchAll(PDO::FETCH_ASSOC);

  if (!$emps) ok(['rows'=>[], 'total_rows'=>0, 'pages'=>1, 'stats'=>['faltas'=>0,'justificadas'=>0,'incompletas'=>0]]);

  $tsCol = pick_ts_col($pdo);

  // 2) Traemos checadas del rango ampliado (un día antes/después por cruces)
  $startPad = (new DateTimeImmutable($start, $TZ))->modify('-1 day')->format('Y-m-d 00:00:00');
  $endPad   = (new DateTimeImmutable($end,   $TZ))->modify('+1 day')->format('Y-m-d 23:59:59');

  $empIds = array_column($emps,'id_empleado');
  $inList = implode(',', array_fill(0,count($empIds),'?'));
  $chSt = $pdo->prepare("
    SELECT id_empleado, tipo, $tsCol AS ts
    FROM checadas
    WHERE id_empleado IN ($inList) AND $tsCol BETWEEN ? AND ?
    ORDER BY id_empleado, $tsCol
  ");
  $chSt->execute([...$empIds, $startPad, $endPad]);
  $chRows = $chSt->fetchAll(PDO::FETCH_ASSOC);

  // agrupamos checadas por empleado
  $byEmp = [];
  foreach ($chRows as $r) {
    $byEmp[(int)$r['id_empleado']][] = $r;
  }

  // 3) Cargamos justificantes del rango
  $jusSt = $pdo->prepare("
    SELECT id_empleado, fecha_inicio, fecha_fin, archivo_url
    FROM justificantes
    WHERE (fecha_inicio <= ? AND fecha_fin >= ?)
      AND id_empleado IN ($inList)
  ");
  $jusSt->execute([$end, $start, ...$empIds]);
  $jusRows = $jusSt->fetchAll(PDO::FETCH_ASSOC);
  $jusMap = []; // id_empleado => [ [start,end,url], ... ]
  foreach($jusRows as $j){
    $eid = (int)$j['id_empleado'];
    $jusMap[$eid][] = [
      's' => new DateTimeImmutable($j['fecha_inicio'].' 00:00:00', $TZ),
      'e' => new DateTimeImmutable($j['fecha_fin'].' 23:59:59',   $TZ),
      'url'=> $j['archivo_url']
    ];
  }

  // 4) Construimos día por día
  $stats = ['faltas'=>0,'justificadas'=>0,'incompletas'=>0];
  $cursor = $sd;
  while ($cursor <= $ed) {
    $dow = (int)$cursor->format('N');  // 1..7
    $ymd = $cursor->format('Y-m-d');
    foreach ($emps as $emp) {
      $sid = (int)$emp['id_empleado'];
      $sch = get_day_schedule($pdo, $sid, $dow);
      if (!$sch) continue; // día no laboral, no se incluye

      [$winStart, $winEnd] = build_window($sch, $ymd, $TZ);

      // checadas del empleado dentro de la ventana
      $checks = array_filter($byEmp[$sid] ?? [], function($r) use ($winStart, $winEnd, $TZ) {
        $t = new DateTimeImmutable($r['ts'], $TZ);
        return $t >= $winStart && $t <= $winEnd;
      });


      // tomamos primera ENTRADA y última SALIDA si existen
      $entrada = null; $salida = null; $retardoMin = 0;
      foreach ($checks as $c) {
        if ($c['tipo']==='ENTRADA' && $entrada===null) {
          $entrada = new DateTimeImmutable($c['ts'], $TZ);
          // retardo vs tolerancia
          $mins = (int)floor(($entrada->getTimestamp() - $winStart->getTimestamp())/60);
          $retardoMin = max(0, $mins - (int)$sch['tolerancia_min']);
        }
        if ($c['tipo']==='SALIDA') {
          $salida = new DateTimeImmutable($c['ts'], $TZ); // la última que quede
        }
      }

      // estado base
      $estado = 'OK';
      if (!$entrada && !$salida) $estado = 'FALTA';
      elseif (!$entrada || !$salida) $estado = 'INCOMPLETA';
      elseif ($retardoMin > 0) $estado = 'RETARDO';

      // ¿justificado?
      $jusUrl = null;
      if (!empty($jusMap[$sid])) {
        foreach ($jusMap[$sid] as $j) {
          if ($cursor >= $j['s'] && $cursor <= $j['e']) { $estado = 'JUSTIFICADA'; $jusUrl = $j['url']; break; }
        }
      }

      // stats
      if ($estado==='FALTA') $stats['faltas']++;
      if ($estado==='JUSTIFICADA') $stats['justificadas']++;
      if ($estado==='INCOMPLETA') $stats['incompletas']++;

      $rows[] = [
        'fecha' => $ymd,
        'dow'   => dowName($dow),
        'empleado' => [
          'id' => $sid,
          'codigo' => $emp['codigo_empleado'],
          'nombre' => $emp['nombre'],
          'apellido' => $emp['apellido'],
          'unidad_medica' => $emp['unidad_medica'],
          'turno' => $emp['turno'],
          'cargo' => $emp['cargo'],
        ],
        'horario' => hm($winStart).'–'.hm($winEnd),
        'entrada' => $entrada ? $entrada->format('H:i:s') : null,
        'salida'  => $salida  ? $salida->format('H:i:s') : null,
        'estado'  => $estado,
        'justificante_url' => $jusUrl
      ];
    }
    $cursor = $cursor->modify('+1 day');
  }

  // paginar del lado servidor
  $total = count($rows);
  $pages = max(1, (int)ceil($total/$limit));
  $page = min($page,$pages);
  $offset = ($page-1)*$limit;
  $slice = array_slice($rows, $offset, $limit);

  ok(['rows'=>$slice, 'total_rows'=>$total, 'pages'=>$pages, 'stats'=>$stats]);
}

/* ================== subir justificante ================== */
elseif ($action === 'justify_upload') {
  // multipart/form-data
  $id   = (int)($_POST['id_empleado'] ?? 0);
  $s    = trim($_POST['fecha_inicio'] ?? '');
  $e    = trim($_POST['fecha_fin'] ?? '');
  $mot  = trim($_POST['motivo'] ?? '');
  if (!$id) bad('Empleado requerido');
  if ($s==='' || $e==='') bad('Rango requerido');
  if (!isset($_FILES['archivo'])) bad('Archivo requerido');

  // validar archivo
  $f = $_FILES['archivo'];
  if ($f['error'] !== UPLOAD_ERR_OK) bad('Error al subir archivo');
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  $allowed = ['pdf','jpg','jpeg','png'];
  if (!in_array($ext, $allowed, true)) bad('Formato no permitido');

  $destDir = $ROOT . '/assets/justificantes';
  if (!is_dir($destDir)) @mkdir($destDir, 0775, true);

  $safeName = 'just_'.$id.'_'.$s.'_'.$e.'_'.bin2hex(random_bytes(4)).'.'.$ext;
  $destAbs  = $destDir . '/' . $safeName;
  if (!move_uploaded_file($f['tmp_name'], $destAbs)) bad('No se pudo guardar el archivo');

  // URL relativa desde web root
  $destRel = dirname($_SERVER['SCRIPT_NAME']) . '/../assets/justificantes/' . $safeName;
  $destRel = preg_replace('#/+#','/',$destRel);

  $st = $pdo->prepare("INSERT INTO justificantes (id_empleado, fecha_inicio, fecha_fin, motivo, archivo_url)
                       VALUES (?,?,?,?,?)");
  $st->execute([$id, $s, $e, ($mot===''?null:$mot), $destRel]);

  ok(['url'=>$destRel]);
}

else { bad('Acción no soportada'); }
