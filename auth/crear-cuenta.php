<?php
// auth/crear-cuenta.php
$ROOT = dirname(__DIR__);
require_once $ROOT . '/conection/conexion.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$u = (object)[
  'id_empleado'  => '',
  'clave_acceso' => '',
  'rol'          => '',
];

$errores = [];
$exito   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // 1) Recibir los datos y enviarlos
  $u->id_empleado  = trim($_POST['id_empleado']  ?? '');
  $u->clave_acceso = trim($_POST['clave_acceso'] ?? '');
  $u->rol          = trim($_POST['rol']          ?? '');
  $password        = (string)($_POST['password'] ?? '');

  // 2) Validar los datos del usuario
  if ($u->id_empleado === '' || !ctype_digit($u->id_empleado)) {
    $errores[] = 'id_empleado es obligatorio y debe ser número entero.';
  }
  if ($u->clave_acceso === '') { $errores[] = 'clave_acceso es obligatoria.'; }
  if ($u->rol === '')          { $errores[] = 'rol es obligatorio.'; }
  if (strlen($password) < 6)   { $errores[] = 'El password debe tener al menos 6 caracteres.'; }

  // 3) Guardar los datos en la DB
  if (!$errores) {
    try {
      $pdo = DB::conn();

      // ¿Existe ya ese usuario (por clave_acceso) entonces te obliga a escribir otra clave
      $stmt = $pdo->prepare("SELECT 1 FROM usuarios WHERE clave_acceso = :c LIMIT 1");
      $stmt->execute([':c' => $u->clave_acceso]);
      if ($stmt->fetch()) {
        $errores[] = 'Ya existe un usuario con esa clave_acceso.';
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // consulta insert a la DB para almacenar los datos del formulario
        $sql = 'INSERT INTO usuarios (id_empleado, clave_acceso, rol, "contrasena", fecha_creacion)
                VALUES (:id_empleado, :clave_acceso, :rol, :pwd, NOW())';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          ':id_empleado'  => (int)$u->id_empleado,
          ':clave_acceso' => $u->clave_acceso,
          ':rol'          => $u->rol,
          ':pwd'          => $hash,
        ]);

        $exito = 'Usuario creado correctamente.';
        $u = (object)['id_empleado'=>'','clave_acceso'=>'','rol'=>''];
      }
    } catch (Throwable $e) {
      $errores[] = 'Error al intentar guardar el usuario: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crear Cuenta</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,sans-serif;margin:24px}
    .formulario{max-width:520px}
    .campo{margin-bottom:12px}
    label{display:block;font-weight:600;margin-bottom:6px}
    input, select{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px}
    .boton{padding:10px 16px;border:none;border-radius:8px;background:#331ED4;color:#fff;cursor:pointer}
    .alert{padding:10px 12px;border-radius:8px;margin:12px 0}
    .alert-error{background:#ffe6e6;border:1px solid #f5bcbc;color:#a50000}
    .alert-ok{background:#e6ffef;border:1px solid #b6f5ca;color:#0a7c3a}
  </style>
</head>
<body>

<h1>Crear Cuenta de Usuario</h1>
<p>Llena el siguiente formulario para crear una cuenta de usuario</p>

<?php if ($errores): ?>
  <div class="alert alert-error">
    <ul style="margin:0;padding-left:18px">
      <?php foreach($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($exito): ?>
  <div class="alert alert-ok"><?= e($exito) ?></div>
<?php endif; ?>

<form class="formulario" method="POST" action="crear-cuenta.php">
  <div class="campo">
    <label for="id_empleado">ID Empleado</label>
    <input type="number" id="id_empleado" name="id_empleado" placeholder="Escribe tu ID de empleado"
           value="<?= e($u->id_empleado) ?>" required>
  </div>

  <div class="campo">
    <label for="clave_acceso">Clave de acceso (usuario)</label>
    <input type="text" id="clave_acceso" name="clave_acceso" placeholder="Escribe tu clave de acceso"
           value="<?= e($u->clave_acceso) ?>" required>
  </div>

  <div class="campo">
    <label for="rol">Rol</label>
    <select id="rol" name="rol" required>
      <option value="">-- Selecciona un rol--</option>
      <option value="empleado" <?= $u->rol==='empleado'?'selected':'' ?>>Empledo</option>
      <option value="admin"   <?= $u->rol==='admin'  ?'selected':'' ?>>Administrador</option>
    </select>
  </div>

  <div class="campo">
    <label for="password">Contraseña</label>
    <input type="password" id="password" name="password" placeholder="Escribe una contraseña" required>
  </div>

  <input type="submit" class="boton" value="Crear Cuenta">
</form>

<div class="acciones" style="margin-top:12px">
  <!-- ajusta el prefijo si tu carpeta se llama distinto -->
  <a href="/Checador_Scap/">Volver al inicio</a>
</div>

</body>
</html>
