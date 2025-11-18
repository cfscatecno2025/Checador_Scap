<?php
/**
 * /Checador_Scap/acceso/api.php
 * API: preview | identify | mark
 * - identify: escanea y encuentra al empleado por su huella (usa FP Service /api/verify)
 * - mark: valida ventana (hoy/ayer) + tolerancia y guarda checada
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/conection/conexion.php';

/* ===== Config ===== */
$TZ_ID   = 'America/Mexico_City';
$TZ      = new DateTimeZone($TZ_ID);
@date_default_timezone_set($TZ_ID);

$FP_BASE = 'http://127.0.0.1:8787'; // servicio Java local

/* ===== Util ===== */
function ok($d = []) { echo json_encode(['ok'=>true] + $d); exit; }
function bad($m='Error') { echo json_encode(['ok'=>false,'msg'=>$m]); exit; }

$pdo = DB::conn();

/* Leer body JSON (si lo hay) */
$raw = file_get_contents('php://input');
$body = null;
if ($raw !== '' && $raw !== false) {
  $tmp = json_decode($raw, true);
  if (is_array($tmp)) $body = $tmp;
}

/* Resolver action (query > body > heurística) */
$action = $_GET['action'] ?? '';
if ($action === '' && isset($body['action'])) $action = $body['action'];
if ($action === '' && is_array($body)) {
  if (isset($body['id_empleado'], $body['tipo'])) $action = 'mark';
  elseif (isset($body['id']))                    $action = 'preview';
}

/* ===== Helpers de horario ===== */
function get_day_schedule(PDO $pdo, int $idEmp, int $dow): ?array {
  $st = $pdo->prepare("
    SELECT dia_semana, entrada, salida, tolerancia_minutos, activo
      FROM horarios
     WHERE id_empleado=? AND dia_semana=? AND activo=TRUE
     LIMIT 1
  ");
  $st->execute([$idEmp, $dow]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}
function build_window(array $sch, string $ymd, DateTimeZone $tz): array {
  $start = new DateTimeImmutable($ymd.' '.$sch['entrada'], $tz);
  $end   = new DateTimeImmutable($ymd.' '.$sch['salida'],  $tz);
  if ($end <= $start) $end = $end->modify('+1 day'); // cruza medianoche
  return [$start,$end];
}
function hm(DateTimeImmutable $d): string { return $d->format('H:i'); }

/* ===== HTTP helper para FP Service ===== */
function fp_verify(string $fpBase, string $template, int $timeoutMs = 8000): array {
  $ch = curl_init($fpBase . '/api/verify');
  $payload = json_encode(['template'=>$template], JSON_UNESCAPED_UNICODE);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT_MS     => $timeoutMs,
  ]);
  $resp = curl_exec($ch);
  if ($resp === false) {
    $err = curl_error($ch);
    curl_close($ch);
    return ['ok'=>false,'error'=>"FP Service: $err"];
  }
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $json = json_decode($resp, true);
  if (!is_array($json)) return ['ok'=>false,'error'=>"FP Service HTTP $code: $resp"];
  // Se espera { ok:bool, match:bool, score:int?, error?:string }
  return $json + ['ok'=>false,'match'=>false];
}

/* ================== PREVIEW ================== */
if ($action === 'preview') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($body['id'] ?? 0);
  if (!$id) bad('ID inválido');

  $st = $pdo->prepare("
    SELECT id_empleado, codigo_empleado, nombre, apellido, cargo, turno, firma, foto, unidad_medica
      FROM empleados WHERE id_empleado=?
  ");
  $st->execute([$id]);
  $emp = $st->fetch(PDO::FETCH_ASSOC);
  if (!$emp) bad('Empleado no encontrado');

  $now = new DateTimeImmutable('now', $TZ);
  $dow = (int)$now->format('N');

  $h = $pdo->prepare("
    SELECT dia_semana, entrada, salida, tolerancia_minutos AS tolerancia_min, activo
      FROM horarios
     WHERE id_empleado=? AND dia_semana=? AND activo=TRUE
     LIMIT 1
  ");
  $h->execute([$id, $dow]);
  $hor = $h->fetch(PDO::FETCH_ASSOC);

  ok(['empleado'=>$emp,'hoy'=>$hor]);
}

/* ================= IDENTIFY ================== */
elseif ($action === 'identify') {
  // Recorre plantillas y pide al servicio Java verificar contra la huella escaneada.
  // Mantén el dedo sobre el lector hasta que haga match o agote candidatos.
  // Para no saturar, limit opcional (puedes quitar el LIMIT si lo deseas).
  $q = $pdo->query("
    SELECT id_empleado, nombre, apellido, cargo, turno, firma, foto, unidad_medica
      FROM empleados
     WHERE firma IS NOT NULL AND firma <> ''
  ");
  $candidatos = $q->fetchAll(PDO::FETCH_ASSOC);
  if (!$candidatos) bad('No hay empleados con huella registrada');

  foreach ($candidatos as $e) {
    $tpl = (string)$e['firma'];
    $vr  = fp_verify($FP_BASE, $tpl);
    if (!$vr['ok']) bad($vr['error'] ?? 'Lector no disponible');
    if (!empty($vr['match'])) {
      // Éxito: devolver empleado (sin exponer la plantilla)
      unset($e['firma']);
      // Convierte foto a URL si ya guardas ruta absoluta/relativa en 'foto'
      $e['foto_url'] = $e['foto'] ?: '';
      ok(['empleado'=>$e, 'score'=>$vr['score'] ?? null]);
    }
    // Si no hay match, sigue con el siguiente candidato (mantener dedo)
  }
  bad('Huella no coincide con ningún empleado');
}

/* =================== MARK ==================== */
elseif ($action === 'mark') {
  if (!is_array($body)) bad('JSON inválido');

  $id   = (int)($body['id_empleado'] ?? 0);
  $tipo = strtoupper(trim($body['tipo'] ?? ''));
  $just = trim($body['justificacion'] ?? '');

  if (!$id) bad('ID inválido');
  if (!in_array($tipo, ['ENTRADA','SALIDA'], true)) bad('Tipo inválido');

  // Empleado existe
  $st = $pdo->prepare("SELECT id_empleado FROM empleados WHERE id_empleado=?");
  $st->execute([$id]);
  if (!$st->fetchColumn()) bad('Empleado no encontrado');

  $now       = new DateTimeImmutable('now', $TZ);
  $dowToday  = (int)$now->format('N');
  $dowYest   = $dowToday === 1 ? 7 : ($dowToday - 1);
  $todayYmd  = $now->format('Y-m-d');
  $yestYmd   = $now->sub(new DateInterval('P1D'))->format('Y-m-d');

  $schT = get_day_schedule($pdo, $id, $dowToday);
  $schY = get_day_schedule($pdo, $id, $dowYest);

  $winT = $schT ? build_window($schT, $todayYmd, $TZ) : [null,null];
  $winY = $schY ? build_window($schY,  $yestYmd,  $TZ) : [null,null];

  $inT  = $winT[0] && $now >= $winT[0] && $now <= $winT[1];
  $inY  = $winY[0] && $now >= $winY[0] && $now <= $winY[1];

  if ($tipo === 'ENTRADA') {
    if (!$inT) {
      if ($winT[0]) bad('Fuera de horario para ENTRADA. Turno de hoy: ' . hm($winT[0]) . '–' . hm($winT[1]));
      bad('Sin horario asignado para hoy.');
    }
    $tol     = (int)$schT['tolerancia_minutos'];
    $mins    = (int)floor(($now->getTimestamp() - $winT[0]->getTimestamp()) / 60);
    $retardo = max(0, $mins - $tol);
  } else { // SALIDA
    if (!($inT || $inY)) {
      if    ($winT[0]) bad('Fuera de horario para SALIDA. Ventana de hoy: ' . hm($winT[0]) . '–' . hm($winT[1]));
      elseif($winY[0]) bad('Fuera de horario para SALIDA. Ventana (ayer): ' . hm($winY[0]) . '–' . hm($winY[1]));
      else             bad('Sin horario reciente para SALIDA.');
    }
    $retardo = 0;
  }

  // Guarda checada
  $ins = $pdo->prepare("
    INSERT INTO checadas (id_empleado, tipo, retardo_minutos, justificacion)
    VALUES (?,?,?,?)
  ");
  $ins->execute([$id, $tipo, $retardo, ($just === '' ? null : $just)]);

  ok([
    'retardo_minutos' => $retardo,
    'hora_str'        => $now->format('H:i:s'),
  ]);
}

/* ============== DEFAULT ============== */
else {
  bad('Acción no soportada');
}
