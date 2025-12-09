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

        // ===== Normalización de roles =====
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

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      --bg:#fff;
      --text:#0f172a;
      --muted:#64748b;
      --bd:#cbd5e1;
      --primary:#2563eb;
      --primary-700:#1d4ed8;
      --shadow:0 2px 12px rgba(15,23,42,.06);

      /* ruta del fondo (ajusta si cambias la imagen) */
      --bg-image:url('/Checador_Scap/assets/img/logo_isstech.png');
      --bg-size:clamp(520px, 52vw, 520px);
    }

    body{ background:var(--bg); color:var(--text); }

    /* FONDO SIEMPRE VISIBLE */
    body::before{
      content:"";
      position:fixed;
      inset:0;
      z-index:-1;
      background-image:var(--bg-image);
      background-repeat:no-repeat;
      background-position:center center;
      background-size:var(--bg-size) auto;
      opacity:var(--bg-opacity);
      pointer-events:none;
      opacity: 0.3;
      filter: saturate(0.95) brightness(0.96) contrast(1.05);
      pointer-events: none;
    }

    .card-soft{ border-radius:14px; box-shadow:var(--shadow); background:#fff; }
    .brand-badge{ display:inline-flex; align-items:center; gap:.75rem; font-weight:700; color:var(--text); }
    .muted{ color:var(--muted); }

    .form-control{ border-width:2px; border-color:var(--bd); }
    .form-control:focus{
      border-color:var(--primary);
      box-shadow:0 0 0 .2rem rgba(37,99,235,.15);
    }
    .btn-primary{ background:var(--primary); border-color:var(--primary); }
    .btn-primary:hover{ background:var(--primary-700); border-color:var(--primary-700); }

    .login-wrapper{ min-height:calc(100vh - 64px); } /* aire bajo el navbar */
  </style>
</head>
<body>

<main class="login-wrapper d-flex align-items-center">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-sm-10 col-md-7 col-lg-5">
        <div class="card card-soft p-4 p-md-5">

          <!-- Marca / Encabezado -->
          <div class="text-center mb-3">
            <div class="brand-badge justify-content-center">
              <!-- <img src="/Checador_Scap/assets/logos/logo_isstech.png" alt="ISSTECH" height="40"> -->
              <span>Checador SCAP</span>
            </div>
            <div class="muted">Inicia sesión con tus datos</div>
          </div>

          <!-- Alertas -->
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
              <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <!-- Formulario -->
          <form method="POST" action="" novalidate>
            <div class="mb-3">
              <label for="clave_acceso" class="form-label fw-semibold">Clave de acceso (usuario o enlace)</label>
              <input
                type="text"
                class="form-control"
                id="clave_acceso"
                name="clave_acceso"
                placeholder="Tu usuario"
                autocomplete="username"
                required
                value="<?= isset($_POST['clave_acceso']) ? htmlspecialchars($_POST['clave_acceso'], ENT_QUOTES, 'UTF-8') : '' ?>"
              >
            </div>

            <div class="mb-2">
              <label for="password" class="form-label fw-semibold">Contraseña</label>
              <div class="input-group">
                <input
                  type="password"
                  class="form-control"
                  id="password"
                  name="password"
                  placeholder="Tu contraseña"
                  autocomplete="current-password"
                  minlength="6"
                  required
                >
                <button class="btn btn-outline-secondary" type="button" id="togglePwd">Mostrar</button>
              </div>
            </div>

            <div class="d-grid mt-3">
              <button type="submit" class="btn btn-primary btn-lg">Iniciar sesión</button>
            </div>
          </form>

          <div class="text-center mt-3">
            <a class="link-primary" href="/Checador_Scap/auth/olvide.php">¿Olvidaste tu contraseña?</a>
          </div>

        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const pwd = document.getElementById('password');
  const btn = document.getElementById('togglePwd');
  btn.addEventListener('click', () => {
    const show = pwd.type === 'password';
    pwd.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Ocultar' : 'Mostrar';
  });
</script>
</body>
</html>
