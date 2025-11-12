<?php
// C:\xampp\htdocs\Checador_Scap\vistas-roles\vista-empleado.php
$ROOT = dirname(__DIR__);

require_once $ROOT . '/auth/require_login.php';
require_once $ROOT . '/auth/require_role.php';
require_role(['empleado']); // solo empleados (ajusta si quieres permitir más)

$NAVBAR = $ROOT . '/components/navbar.php'; // este decide qué navbar cargar según el rol
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel Empleado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<?php if (is_file($NAVBAR)) include $NAVBAR; ?>

<main style="max-width:1200px;margin:24px auto;padding:0 16px;">
  <h1>Panel de Empleado</h1>
  <p>Hola, <?= htmlspecialchars($_SESSION['usuario'] ?? 'empleado', ENT_QUOTES, 'UTF-8') ?>.</p>

  <section style="margin-top:16px; padding:16px; border:1px solid #e5e7eb; border-radius:12px;">
    <p>Contenido del empleado (pendiente de implementar).</p>
  </section>
</main>

</body>
</html>
