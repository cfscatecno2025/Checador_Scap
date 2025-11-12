<?php
// ====== FORMULARIO DE INICIO DE SESION ======
// Autor: Ailyn Cruz
// Archivo: index.php

session_start();

$ROOT = __DIR__;
require_once $ROOT . '/conection/conexion.php'; // PDO a PostgreSQL

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $usuario  = trim($_POST['clave_acceso'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  if ($usuario === '' || $password === '') {
    $error = 'Ingresa usuario y contraseña.';
  } else {
    try {
      $pdo = DB::conn();
      $stmt = $pdo->prepare('
        SELECT id_usuario, id_empleado, clave_acceso, rol, contrasena
        FROM usuarios
        WHERE clave_acceso = :u
        LIMIT 1
      ');
      $stmt->execute([':u' => $usuario]);
      $row = $stmt->fetch();

      if (!$row || !password_verify($password, $row['contrasena'])) {
        $error = 'Usuario o contraseña incorrectos.';
      } else {
        // ✅ Sesión
        $_SESSION['uid']      = (int)$row['id_usuario'];
        $_SESSION['empleado'] = (int)$row['id_empleado'];
        $_SESSION['usuario']  = $row['clave_acceso'];
        $_SESSION['rol']      = $row['rol'];

        // ✅ Redirección por rol
        $rol = strtolower(trim($_SESSION['rol'] ?? ''));

        // mapa rol → ruta
        $destinos = [
          'admin'    => '/Checador_Scap/vistas-roles/vista-admin.php',
          'usuario'  => '/Checador_Scap/vistas-roles/vista-usuario.php',
          'empleado' => '/Checador_Scap/vistas-roles/vista-empleado.php',
        ];

        $destino = $destinos[$rol] ?? '/Checador_Scap/no-autorizado.php';
        header('Location: ' . $destino);
        exit;
      }
    } catch (Throwable $e) {
      $error = 'Error interno: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
       /* ===== Paleta clara (consistente con crear.php) ===== */
    :root{
      --bg:#f6f8fb;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --bd:#cbd5e1;
      --bd-strong:#94a3b8;

      --primary:#2563eb;
      --primary-700:#1d4ed8;
      --danger:#dc2626;
      --success:#16a34a;
      --warning:#f59e0b;

      --chip:#f1f5f9;
      --chipbd:#e2e8f0;

      --shadow: 0 10px 24px rgba(15,23,42,.08);
      
      --bg-image: url('/Checador_Scap/assets/img/logo_login_scap.jpg');
      --bg-size: clamp(520px, 52vw, 720px);
    }
    
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family:system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      background:var(--bg);
      color:var(--text);
    }
    body::before{
        content:"";
        position:fixed;
        inset:0;
        z-index:-1;
        background-image: var(--bg-image);
        background-repeat: no-repeat;          
        background-position: center center;    
        background-size: var(--bg-size) auto;  
        background-attachment: fixed;

        opacity:.10;                            /* ajusta intensidad */
        filter:saturate(.95) brightness(1.02) contrast(1.03);
        pointer-events:none;
    }

    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;margin:24px;color:#111}
    .nombre-pagina{margin:0 0 6px;font-size:32px;line-height:1.2}
    .descripcion-pagina{margin:0 0 18px;color:#555}
    .formulario{max-width:520px}
    .campo{margin-bottom:12px}
    label{display:block;font-weight:600;margin-bottom:6px}
    input[type=text],input[type=password]{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;background:#fff}
    .boton{padding:12px 16px;border:none;border-radius:8px;background:#331ED4;color:#fff;font-weight:700;cursor:pointer}
    .boton:hover{opacity:.95}
    .acciones a{display:inline-block;margin-right:12px;margin-top:8px;color:#331ED4;text-decoration:none}
    .acciones a:hover{text-decoration:underline}
    .alert{padding:10px 12px;border-radius:8px;margin:12px 0}
    .alert-error{background:#ffe6e6;border:1px solid #f5bcbc;color:#a50000}
    .row-inline{display:flex;align-items:center;justify-content:space-between;gap:12px}
    .muted{color:#666;font-size:14px}
    .check{display:flex;align-items:center;gap:8px;user-select:none}
    .check input{width:16px;height:16px}
  </style>
</head>
<body>

<h1 class="nombre-pagina">Login</h1>
<p class="descripcion-pagina">Inicia sesión con tus datos</p>

<?php if (!empty($error)): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form class="formulario" method="POST" action="">
  <div class="campo">
    <label for="clave_acceso">Clave de acceso (usuario)</label>
    <input
      type="text"
      id="clave_acceso"
      name="clave_acceso"
      placeholder="Tu usuario"
      autocomplete="username"
      required
      value="<?= isset($_POST['clave_acceso']) ? htmlspecialchars($_POST['clave_acceso'], ENT_QUOTES, 'UTF-8') : '' ?>"
    />
  </div>

  <div class="campo">
    <label for="password">Contraseña</label>
    <input
      type="password"
      id="password"
      name="password"
      placeholder="Tu contraseña"
      autocomplete="current-password"
      minlength="6"
      required
    />
    <div class="row-inline" style="margin-top:8px">
      <label class="check muted" for="showPwd">
        <input type="checkbox" id="showPwd" />
        Mostrar contraseña
      </label>
    </div>
  </div>

  <input type="submit" class="boton" value="Iniciar Sesión">
</form>

<div class="acciones">
  <a href="/Checador_Scap/auth/crear-cuenta.php">¿No tienes una cuenta? Crear una</a>
  <a href="/Checador_Scap/auth/olvide.php">¿Olvidaste tu contraseña?</a>
</div>

<script>
  const pwd = document.getElementById('password');
  const chk = document.getElementById('showPwd');
  chk.addEventListener('change', () => { pwd.type = chk.checked ? 'text' : 'password'; });
</script>

</body>
</html>
