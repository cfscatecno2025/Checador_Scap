<?php
/**
 * /Checador_Scap/acceso/api.php
 * API para previsualizar horario del día y marcar checada.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/auth/require_login.php';
require_once $ROOT . '/auth/require_role.php';
require_role(['admin','supervisor']); // ajusta según tu app
require_once $ROOT . '/conection/conexion.php';

function ok($d = []) { echo json_encode(['ok' => true] + $d); exit; }
function bad($m = 'Error') { echo json_encode(['ok' => false, 'msg' => $m]); exit; }

$pdo = DB::conn();
$action = $_GET['action'] ?? '';

/* ==== PREVIEW DEL DÍA ==== */
if ($action === 'preview') {
  $id = (int)($_GET['id'] ?? 0);
  if (!$id) bad('ID inválido');

  $st = $pdo->prepare("SELECT id_empleado, codigo_empleado, nombre, apellido, cargo, turno, firma FROM empleados WHERE id_empleado=?");
  $st->execute([$id]);
  $emp = $st->fetch(PDO::FETCH_ASSOC);
  if (!$emp) bad('Empleado no encontrado');

  $dow = (int)date('N'); // 1=Lunes..7=Domingo
  $h = $pdo->prepare("
  SELECT dia_semana, entrada, salida, tolerancia_minutos AS tolerancia_min, activo
  FROM horarios
  WHERE id_empleado = ? AND dia_semana = ? AND activo = TRUE
");

  $h->execute([$id, $dow]);
  $hor = $h->fetch(PDO::FETCH_ASSOC);

  ok(['empleado'=>$emp,'hoy'=>$hor]);
}

/* ==== MARCAR ENTRADA/SALIDA ==== */
elseif ($action === 'mark') {
  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data)) bad('JSON inválido');

  $id   = (int)($data['id_empleado'] ?? 0);
  $tipo = strtoupper(trim($data['tipo'] ?? ''));
  $just = trim($data['justificacion'] ?? '');

  if (!$id) bad('ID inválido');
  if (!in_array($tipo, ['ENTRADA','SALIDA'], true)) bad('Tipo inválido');

  $st = $pdo->prepare("SELECT id_empleado FROM empleados WHERE id_empleado=?");
  $st->execute([$id]);
  if (!$st->fetchColumn()) bad('Empleado no encontrado');

  $dow = (int)date('N');
  $h = $pdo->prepare("
  SELECT entrada, salida, tolerancia_minutos
  FROM horarios
  WHERE id_empleado = ? AND dia_semana = ? AND activo = TRUE
");

  $h->execute([$id, $dow]);
  $hor = $h->fetch(PDO::FETCH_ASSOC);

  $retardo = 0;
  if ($tipo === 'ENTRADA' && $hor) {
    $entrada = $hor['entrada'];        // HH:MM:SS
    $tol = (int)$hor['tolerancia_minutos'];
    $hoy = date('Y-m-d');
    $prog = strtotime("$hoy {$entrada}");
    $now  = time();
    $mins = (int)floor(($now - $prog) / 60);
    if ($mins > $tol) $retardo = ($mins - $tol);
  }

  $ins = $pdo->prepare("INSERT INTO checadas (id_empleado, tipo, retardo_minutos, justificacion) VALUES (?,?,?,?)");
  $ins->execute([$id, $tipo, $retardo, ($just===''?null:$just)]);

  ok(['retardo_minutos'=>$retardo]);
}

else { bad('Acción no soportada'); }
