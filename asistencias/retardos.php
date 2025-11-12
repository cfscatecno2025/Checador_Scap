<?php
// C:\xampp\htdocs\Checador_Scap\asistencias\retardos.php
session_start();

$ROOT = dirname(__DIR__);
require_once $ROOT . '/auth/require_login.php';
require_once $ROOT . '/conection/conexion.php';

$idRetardo  = (int)($_GET['id'] ?? 0);
$idEmpleado = (int)($_GET['empleado'] ?? 0);

$ok = false;
$msg = '';
$detalle = null;

try {
  $pdo = DB::conn();

  // Si POST, guardar justificación
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idRetardo = (int)($_POST['id_retardo'] ?? 0);
    $just = trim($_POST['justificacion'] ?? '');
    if ($idRetardo <= 0) throw new Exception('Retardo inválido.');
    if ($just === '') throw new Exception('Escribe una justificación.');

    $up = $pdo->prepare("
      UPDATE retardos
      SET justificacion = :j, atendido = TRUE
      WHERE id_retardo = :id
      RETURNING id_empleado
    ");
    $up->execute([':j'=>$just, ':id'=>$idRetardo]);
    $r = $up->fetch(PDO::FETCH_ASSOC);
    if (!$r) throw new Exception('No se encontró el retardo.');

    $ok = true;
    $msg = 'Justificación registrada.';
    // Para mostrar detalles tras el POST
    $idEmpleado = (int)$r['id_empleado'];
  }

  // Carga detalle del retardo + empleado
  if ($idRetardo > 0) {
    $q = $pdo->prepare("
      SELECT r.id_retardo, r.fecha, r.minutos_retardo, r.penalizacion, r.justificacion, r.atendido,
             e.id_empleado, e.codigo_empleado, e.nombre, e.apellido
      FROM retardos r
      JOIN empleados e ON e.id_empleado = r.id_empleado
      WHERE r.id_retardo = :id
      LIMIT 1
    ");
    $q->execute([':id'=>$idRetardo]);
    $detalle = $q->fetch(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  $msg = $e->getMessage();
}

$NAVBAR = $ROOT . '/components/navbar.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Retardo y justificación</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;margin:24px;color:#111;background:#fafafa}
    .container{max-width:900px;margin:0 auto}
    h1{margin:0 0 6px;font-size:28px}
    .muted{color:#666}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-top:12px}
    .alert{padding:10px 12px;border-radius:8px;margin:12px 0}
    .alert-ok{background:#e7f6ec;border:1px solid #b5e2c4;color:#146c2e}
    .alert-err{background:#ffe6e6;border:1px solid #f5bcbc;color:#a50000}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    label{display:block;font-weight:600;margin-bottom:6px}
    textarea{width:100%;min-height:120px;padding:10px;border:1px solid #ccc;border-radius:8px;background:#fff}
    .btn{padding:12px 16px;border:none;border-radius:8px;background:#331ED4;color:#fff;font-weight:700;cursor:pointer}
    .btn:hover{opacity:.95}
    .badge{display:inline-block;background:#fef3c7;border:1px solid #fcd34d;padding:2px 8px;border-radius:999px;color:#92400e;font-size:12px}
    .row{display:flex;gap:12px;align-items:center}
  </style>
</head>
<body>

<?php if (is_file($NAVBAR)) include $NAVBAR; ?>

<main class="container">
  <h1>Retardo detectado</h1>
  <p class="muted">Se excedió la tolerancia de entrada. Completa la justificación.</p>

  <?php if ($msg): ?>
    <div class="alert <?= $ok ? 'alert-ok' : 'alert-err' ?>"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($detalle): ?>
    <div class="card">
      <div class="grid">
        <div><strong>Empleado:</strong> <?= htmlspecialchars($detalle['nombre'].' '.$detalle['apellido'], ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Código:</strong> <?= htmlspecialchars($detalle['codigo_empleado'], ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Fecha:</strong> <?= htmlspecialchars($detalle['fecha'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="row">
          <div><strong>Minutos de retardo:</strong> <?= (int)$detalle['minutos_retardo'] ?></div>
          <div class="badge"><strong>Penalización:</strong> <?= (float)$detalle['penalizacion'] ?></div>
        </div>
        <div><strong>Estado:</strong> <?= $detalle['atendido'] ? 'Atendido' : 'Pendiente' ?></div>
      </div>
    </div>

    <div class="card">
      <form method="POST" action="">
        <input type="hidden" name="id_retardo" value="<?= (int)$detalle['id_retardo'] ?>">
        <label for="justificacion">Justificación</label>
        <textarea id="justificacion" name="justificacion" placeholder="Escribe el motivo del retardo..." required><?= htmlspecialchars($detalle['justificacion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        <div class="row" style="margin-top:10px">
          <button class="btn" type="submit">Guardar justificación</button>
          <a class="btn" style="background:#0f172a" href="/Checador_Scap/empleados/lista.php">Volver a lista</a>
        </div>
      </form>
    </div>
  <?php else: ?>
    <div class="card">No se encontró el detalle del retardo.</div>
  <?php endif; ?>
</main>

</body>
</html>
