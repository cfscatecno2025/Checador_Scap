<?php
/**
 * /Checador_Scap/empleados/api.php
 * API JSON Empleados (list/get/create/update/delete/clear_firma)
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

function norm_time($t) {
  $t = trim((string)$t);
  if ($t === '') return null;
  if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
  if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) return $t;
  return null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
  if (($_SERVER['HTTP_X_CSRF'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    bad('CSRF inválido');
  }
}

$pdo = DB::conn();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* ========================= LIST ========================= */
if ($action === 'list') {
  $q     = trim($_GET['q'] ?? '');
  $page  = max(1, (int)($_GET['page'] ?? 1));
  $limit = max(1, min(50, (int)($_GET['limit'] ?? 5)));
  $offset = ($page - 1) * $limit;

  $params = [];
  $where = '';
  if ($q !== '') {
    $where = "WHERE (CAST(id_empleado AS TEXT) ILIKE :q OR codigo_empleado ILIKE :q OR nombre ILIKE :q OR apellido ILIKE :q OR turno ILIKE :q OR cargo ILIKE :q OR foto ILIKE :q OR unidad_medica ILIKE :q)";
    $params[':q'] = "%{$q}%";
  }

  $st = $pdo->prepare("SELECT COUNT(*) FROM empleados $where");
  $st->execute($params);
  $total = (int)$st->fetchColumn();
  $pages = max(1, (int)ceil($total / $limit));

  $st = $pdo->prepare("
    SELECT id_empleado, codigo_empleado, nombre, apellido, cargo, turno, foto, unidad_medica
    FROM empleados
    $where
    ORDER BY id_empleado DESC
    LIMIT :limit OFFSET :offset
  ");
  foreach ($params as $k => $v) $st->bindValue($k, $v);
  $st->bindValue(':limit', $limit, PDO::PARAM_INT);
  $st->bindValue(':offset', $offset, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  ok(['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages]);
}

/* ========================= GET ========================= */
elseif ($action === 'get') {
  $id = (int)($_GET['id'] ?? 0); // id_empleado
  if (!$id) bad('ID inválido');

  // Incluye 'firma' para poder verificar/editar desde el modal (admin)
  $st = $pdo->prepare("SELECT id_empleado, codigo_empleado, nombre, apellido, cargo, turno, firma, foto, unidad_medica FROM empleados WHERE id_empleado = ?");
  $st->execute([$id]);
  $emp = $st->fetch(PDO::FETCH_ASSOC);
  if (!$emp) bad('No encontrado');

  $s = $pdo->prepare("
    SELECT id_horario, dia_semana, entrada, salida, tolerancia_minutos AS tolerancia_min, activo
    FROM horarios
    WHERE id_empleado = ?
    ORDER BY dia_semana
  ");
  $s->execute([$id]);
  $emp['horario'] = $s->fetchAll(PDO::FETCH_ASSOC);

  ok(['data' => $emp]);
}

/* ===================== CREATE/UPDATE ==================== */
elseif ($action === 'create' || $action === 'update') {
  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data)) bad('JSON inválido');
  if (($data['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) bad('CSRF inválido');

  $codigo   = trim($data['codigo_empleado'] ?? '');
  $nombre   = trim($data['nombre'] ?? '');
  $apellido = trim($data['apellido'] ?? '');
  $turno    = trim($data['turno'] ?? '');
  $cargo    = trim($data['cargo'] ?? '');
  $foto = trim($data['foto'] ?? '');
  $unidad_medica = trim($data['unidad_medica'] ?? '');
  $horario  = $data['horario'] ?? [];
  $firma    = trim($data['firma'] ?? '');   // Base64 del template

  if ($codigo === '' || mb_strlen($codigo) > 20) bad('Código obligatorio (máx 20)');
  if (!preg_match('/^\d+$/', $codigo)) bad('El código debe ser numérico');
  if ($nombre === '' || mb_strlen($nombre) > 50) bad('Nombre obligatorio');
  if ($apellido === '' || mb_strlen($apellido) > 50) bad('Apellido obligatorio');
  if ($cargo === '' || mb_strlen($cargo) > 50) bad('Cargo obligatorio');
  if ($turno === '' || mb_strlen($turno) > 50) bad('Turno obligatorio');
  if ($foto === '' || mb_strlen($foto) > 100) bad('La foto es invalida') ;
  if($unidad_medica === '' || mb_strlen($unidad_medica) > 50) bad('La unidad medica es obligatoria');
  if ($firma === '') { $firma = null; }

  $idEmp = (int)$codigo;

  try {
    $pdo->beginTransaction();

    if ($action === 'create') {
      $q = $pdo->prepare("SELECT 1 FROM empleados WHERE id_empleado = ? OR codigo_empleado = ? LIMIT 1");
      $q->execute([$idEmp, $codigo]);
      if ($q->fetchColumn()) { $pdo->rollBack(); bad('El código ya existe'); }

      $ins = $pdo->prepare("
        INSERT INTO empleados (id_empleado, codigo_empleado, nombre, apellido, cargo, turno, firma, foto, unidad_medica)
        VALUES (?,?,?,?,?,?,?,?,?)
      ");
      $ins->execute([$idEmp, $codigo, $nombre, $apellido, $cargo, $turno, $firma, $foto, $unidad_medica]);
      $id = $idEmp;

    } else {
      $idBefore = (int)($data['id_empleado'] ?? 0);
      if (!$idBefore) { $pdo->rollBack(); bad('ID requerido'); }

      $q = $pdo->prepare("
        SELECT 1
          FROM empleados
         WHERE (id_empleado = ? OR codigo_empleado = ?)
           AND id_empleado <> ?
         LIMIT 1
      ");
      $q->execute([$idEmp, $codigo, $idBefore]);
      if ($q->fetchColumn()) { $pdo->rollBack(); bad('El código ya existe'); }

      $pdo->prepare("DELETE FROM horarios WHERE id_empleado = ?")->execute([$idBefore]);

      $up = $pdo->prepare("
        UPDATE empleados
           SET id_empleado = ?, codigo_empleado = ?, nombre = ?, apellido = ?, cargo = ?, turno = ?, foto = ?, unidad_medica = ?, 
               firma = COALESCE(?, firma)
         WHERE id_empleado = ?
      ");
      $up->execute([$idEmp, $codigo, $nombre, $apellido, $cargo, $turno, $foto, $unidad_medica, $firma, $idBefore]);

      $id = $idEmp;
    }

    if (is_array($horario)) {
      $hins = $pdo->prepare("
        INSERT INTO horarios (id_empleado, dia_semana, entrada, salida, tolerancia_minutos, activo)
        VALUES (?,?,?,?,?,?)
      ");
      foreach ($horario as $h) {
        $dia = (int)($h['dia_semana'] ?? 0);
        if ($dia < 1 || $dia > 7) continue;
        $activo = !empty($h['activo']);
        if (!$activo) continue;

        $ent = norm_time($h['entrada'] ?? '');
        $sal = norm_time($h['salida']  ?? '');
        if ($ent === null || $sal === null) {
          $pdo->rollBack();
          bad("Faltan horas válidas para el día {$dia} (formato HH:MM).");
        }
        $tol = (int)($h['tolerancia_minutos'] ?? $h['tolerancia_min'] ?? 10);
        $hins->execute([$id, $dia, $ent, $sal, $tol, true]);
      }
    }

    $pdo->commit();
    ok();

  } catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    bad($e->getMessage());
  }
}

/* ======================== DELETE ======================== */
elseif ($action === 'delete') {
  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data)) bad('JSON inválido');
  if (($data['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) bad('CSRF inválido');

  $id = (int)($data['id'] ?? 0);
  if (!$id) bad('ID inválido');

  try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM horarios  WHERE id_empleado = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM empleados WHERE id_empleado = ?")->execute([$id]);
    $pdo->commit();
    ok();
  } catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    bad($e->getMessage());
  }
}

/* ==================== CLEAR_FIRMA (solo BD) ==================== */
elseif ($action === 'clear_firma') {
  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data)) bad('JSON inválido');
  if (($data['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) bad('CSRF inválido');

  $id = (int)($data['id'] ?? 0);
  if (!$id) bad('ID inválido');

  $st = $pdo->prepare("UPDATE empleados SET firma = NULL WHERE id_empleado = ?");
  $st->execute([$id]);
  ok();
}

else { bad('Acción no soportada'); }
