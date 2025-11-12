<?php
// Checador_Scap/usuarios/api.php
declare(strict_types=1);
session_start();

$ROOT = dirname(__DIR__);
require_once $ROOT . '/auth/require_login.php';
require_once $ROOT . '/auth/require_role.php';
require_role(['admin']);
require_once $ROOT . '/conection/conexion.php';

header('Content-Type: application/json; charset=utf-8');

function json_out($arr, int $code = 200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

function same_csrf(): bool {
  $h = $_SERVER['HTTP_X_CSRF'] ?? '';
  $b = null;
  // Si viene JSON, léelo sólo para CSRF (las rutas POST lo volverán a leer)
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
      $tmp = json_decode($raw, true);
      if (is_array($tmp)) $b = $tmp['csrf'] ?? null;
    }
  }
  return isset($_SESSION['csrf']) && ($_SESSION['csrf'] === $h || $_SESSION['csrf'] === $b);
}

if (!same_csrf() && ($_GET['action'] ?? '') !== 'list' && ($_GET['action'] ?? '') !== 'get') {
  json_out(['ok'=>false,'msg'=>'CSRF inválido'], 403);
}

$action = $_GET['action'] ?? '';
$pdo = DB::conn(); // Debe devolver un PDO (PostgreSQL)

try {
  if ($action === 'list') {
    // Parámetros
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 5);
    if ($limit < 1 || $limit > 100) $limit = 5;
    $q     = trim((string)($_GET['q'] ?? ''));

    $where = '';
    $params = [];
    if ($q !== '') {
      $where = "WHERE (clave_acceso ILIKE :q OR rol ILIKE :q
                 OR CAST(id_empleado AS TEXT) ILIKE :q
                 OR CAST(id_usuario  AS TEXT) ILIKE :q)";
      $params[':q'] = '%'.$q.'%';
    }

    $sqlCount = "SELECT COUNT(*) FROM usuarios $where";
    $st = $pdo->prepare($sqlCount);
    foreach($params as $k=>$v){ $st->bindValue($k, $v, PDO::PARAM_STR); }
    $st->execute();
    $total = (int)$st->fetchColumn();

    $pages  = max(1, (int)ceil($total / $limit));
    $offset = ($page - 1) * $limit;

    $sql = "SELECT id_usuario, id_empleado, clave_acceso, rol, fecha_creacion
            FROM usuarios
            $where
            ORDER BY id_usuario DESC
            LIMIT :limit OFFSET :offset";
    $st = $pdo->prepare($sql);
    foreach($params as $k=>$v){ $st->bindValue($k, $v, PDO::PARAM_STR); }
    $st->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    json_out(['ok'=>true, 'rows'=>$rows, 'total'=>$total, 'pages'=>$pages]);
  }

  if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID inválido'], 400);

    $st = $pdo->prepare("SELECT id_usuario, id_empleado, clave_acceso, rol, fecha_creacion
                         FROM usuarios WHERE id_usuario = :id");
    $st->execute([':id'=>$id]);
    $data = $st->fetch(PDO::FETCH_ASSOC);
    if (!$data) json_out(['ok'=>false,'msg'=>'No encontrado'], 404);

    json_out(['ok'=>true,'data'=>$data]);
  }

  if ($action === 'create') {
    $raw = file_get_contents('php://input');
    $p = json_decode($raw, true) ?: [];

    $id_empleado  = isset($p['id_empleado']) ? (int)$p['id_empleado'] : 0;
    $clave        = trim((string)($p['clave_acceso'] ?? ''));
    $rol          = trim((string)($p['rol'] ?? ''));
    $passPlain    = trim((string)($p['contrasena'] ?? ''));

    if ($id_empleado <= 0)          json_out(['ok'=>false,'msg'=>'id_empleado inválido'], 400);
    if ($clave === '')               json_out(['ok'=>false,'msg'=>'clave_acceso requerido'], 400);
    if ($rol === '')                 json_out(['ok'=>false,'msg'=>'rol requerido'], 400);
    if ($passPlain === '' || strlen($passPlain) < 6)
      json_out(['ok'=>false,'msg'=>'contraseña requerida (mín. 6 caracteres)'], 400);

    $hash = password_hash($passPlain, PASSWORD_BCRYPT, ['cost'=>12]);

    $sql = "INSERT INTO usuarios (id_empleado, clave_acceso, rol, contrasena)
            VALUES (:id_empleado, :clave, :rol, :pass)
            RETURNING id_usuario";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':id_empleado'=>$id_empleado,
      ':clave'=>$clave,
      ':rol'=>$rol,
      ':pass'=>$hash
    ]);
    $newId = (int)$st->fetchColumn();

    json_out(['ok'=>true,'id'=>$newId]);
  }

  if ($action === 'update') {
    $raw = file_get_contents('php://input');
    $p = json_decode($raw, true) ?: [];

    $id_usuario   = isset($p['id_usuario']) ? (int)$p['id_usuario'] : 0;
    $id_empleado  = isset($p['id_empleado']) ? (int)$p['id_empleado'] : 0;
    $clave        = trim((string)($p['clave_acceso'] ?? ''));
    $rol          = trim((string)($p['rol'] ?? ''));
    $passPlain    = trim((string)($p['contrasena'] ?? ''));

    if ($id_usuario <= 0)           json_out(['ok'=>false,'msg'=>'ID inválido'], 400);
    if ($id_empleado <= 0)          json_out(['ok'=>false,'msg'=>'id_empleado inválido'], 400);
    if ($clave === '')               json_out(['ok'=>false,'msg'=>'clave_acceso requerido'], 400);
    if ($rol === '')                 json_out(['ok'=>false,'msg'=>'rol requerido'], 400);

    // Armado dinámico si cambia contraseña
    $set = "id_empleado = :id_empleado, clave_acceso = :clave, rol = :rol";
    $params = [
      ':id_empleado'=>$id_empleado,
      ':clave'=>$clave,
      ':rol'=>$rol,
      ':id'=>$id_usuario
    ];
    if ($passPlain !== '') {
      if (strlen($passPlain) < 6) json_out(['ok'=>false,'msg'=>'La contraseña debe tener al menos 6 caracteres'], 400);
      $set .= ", contrasena = :pass";
      $params[':pass'] = password_hash($passPlain, PASSWORD_BCRYPT, ['cost'=>12]);
    }

    $sql = "UPDATE usuarios SET $set WHERE id_usuario = :id";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    json_out(['ok'=>true,'updated'=>$st->rowCount()]);
  }

  if ($action === 'delete') {
    $raw = file_get_contents('php://input');
    $p = json_decode($raw, true) ?: [];
    $id = isset($p['id']) ? (int)$p['id'] : 0;
    if ($id <= 0) json_out(['ok'=>false,'msg'=>'ID inválido'], 400);

    $st = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id");
    $st->execute([':id'=>$id]);

    if ($st->rowCount() === 0) json_out(['ok'=>false,'msg'=>'No encontrado'], 404);
    json_out(['ok'=>true,'deleted'=>1]);
  }

  json_out(['ok'=>false,'msg'=>'Acción no soportada'], 400);

} catch (PDOException $e) {
  // Errores de base de datos (incluye violaciones de FK/unique)
  json_out(['ok'=>false,'msg'=>'DB error','detail'=>$e->getMessage()], 500);
} catch (Throwable $e) {
  json_out(['ok'=>false,'msg'=>'Error interno','detail'=>$e->getMessage()], 500);
}
