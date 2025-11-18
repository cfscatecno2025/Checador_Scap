<?php
// ====== FORMULARIO DE INICIO DE SESIÓN ======
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
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$row || !password_verify($password, $row['contrasena'])) {
        $error = 'Usuario o contraseña incorrectos.';
      } else {
        // ===== Sesión =====
        $_SESSION['uid']      = (int)$row['id_usuario'];
        $_SESSION['empleado'] = (int)$row['id_empleado'];
        $_SESSION['usuario']  = $row['clave_acceso'];

        // ===== Normalización de roles que vienen de la BD Agregar mas si se requiere =====
        $rolBD   = $row['rol'] ?? '';
        $rolNorm = strtolower(trim($rolBD));
        $map = [
          'administrador' => 'admin',
          'admin'         => 'admin',
          'empleado'      => 'empleado',
          'usuario'       => 'usuario',
        ];
        $_SESSION['rol'] = $map[$rolNorm] ?? $rolNorm;

        // ===== Redirección de vista por rol =====
        $destinos = [
          'admin'    => '/Checador_Scap/vistas-roles/vista-admin.php',
          'usuario'  => '/Checador_Scap/vistas-roles/vista-usuario.php',
          'empleado' => '/Checador_Scap/vistas-roles/vista-empleado.php',
        ];

        $destino = $destinos[$_SESSION['rol']] ?? '/Checador_Scap/no-autorizado.php';
        header('Location: ' . $destino);
        exit;
      }
    } catch (Throwable $e) {
      $error = 'Error interno: ' . $e->getMessage();
    }
  }
}

$NAVBAR = $ROOT . '/components/navbar.php';
?>

<?php if (file_exists($NAVBAR)) include $NAVBAR; ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    /* ===== Paleta clara (consistente con crear.php) ===== */
    :root{
      --bg:#fff;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --bd:#cbd5e1;
      --primary:#2563eb;
      --primary-700:#1d4ed8;

      --shadow: 0 10px 24px rgba(15,23,42,.08);

      --bg-image: url('/Checador_Scap/assets/img/logo_login_scap.jpg');
      --bg-size: clamp(520px, 52vw, 720px);
    }

    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
      background:var(--bg);
      color:var(--text);
      padding:24px;
    }
    /* Fondo con logo tenue */
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
      
      pointer-events:none;
    }

    .nombre-pagina{margin:0 0 6px;font-size:32px;line-height:1.2; text-align: center;}
    .descripcion-pagina{margin:0 0 18px;color:#475569; text-align: center;}
    .formulario{max-width:520px;}
    .campo{margin-bottom:12px;}
    label{display:block;font-weight:600;margin-bottom:6px}
    input[type=text],input[type=password]{
      width:100%;padding:10px;border:2px solid var(--bd);border-radius:10px;background:#fff;
      transition:border-color .18s, box-shadow .18s;
    }
    input[type=text]:focus, input[type=password]:focus{
      border-color:var(--primary);
      box-shadow:0 0 0 3px rgba(37,99,235,.16);
      outline:none;
    }
    .boton{
      padding:12px 16px;border:none;border-radius:10px;background:var(--primary);color:#fff;
      font-weight:700;cursor:pointer;box-shadow:var(--shadow)
    }
    .boton:hover{background:var(--primary-700)}
    .acciones a{display:inline-block;margin-right:12px;margin-top:8px;color:var(--primary);text-decoration:none}
    .acciones a:hover{text-decoration:underline}
    .alert{padding:10px 12px;border-radius:10px;margin:12px 0;background:#fee2e2;border:2px solid #fecaca;color:#991b1b}
    .row-inline{display:flex;align-items:center;gap:8px}
    .check{display:flex;align-items:center;gap:8px;user-select:none;color:#475569}
    .check input{width:16px;height:16px}
  </style>
</head>
<body>

<h1 class="nombre-pagina">Login</h1>
<p class="descripcion-pagina">Inicia sesión con tus datos</p>

<?php if (!empty($error)): ?>
  <div class="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
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
    <label class="check" for="showPwd">
      <input type="checkbox" id="showPwd" />
      Mostrar contraseña
    </label>
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
