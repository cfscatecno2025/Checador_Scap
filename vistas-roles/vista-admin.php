<?php
// vistas-roles/vista-admin.php

$ROOT = dirname(__DIR__);

$REQ_LOGIN = $ROOT . '/auth/require_login.php';
$REQ_ROLE  = $ROOT . '/auth/require_role.php';
$NAVBAR    = $ROOT . '/components/navbar-admin.php';
$DB_FILE   = $ROOT . '/conection/conexion.php';

if (!file_exists($REQ_LOGIN)) die("Falta el archivo: $REQ_LOGIN");
if (!file_exists($REQ_ROLE))  die("Falta el archivo: $REQ_ROLE");
if (!file_exists($DB_FILE))   die("Falta el archivo: $DB_FILE");

require_once $REQ_LOGIN;
require_once $REQ_ROLE;
require_role(['admin']);
require_once $DB_FILE;

// === Conteos ===
$totalEmpleados = null;

try {
  $pdo = DB::conn();
  $totalEmpleados = (int)$pdo->query("SELECT COUNT(*) FROM empleados")->fetchColumn();
} catch (Throwable $e) {
  // Silencioso: mantenemos $totalEmpleados = null para mostrar —
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel Administrador</title>
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

    .container{max-width:1200px;margin:24px auto;padding:0 16px}
    .page-title{font-size:32px;margin:10px 0 6px}
    .subtitle{color:var(--muted);margin:0 0 18px}

    .grid{display:grid;gap:14px}
    @media (min-width: 700px){ .grid-3{grid-template-columns:repeat(3,1fr)} .grid-2{grid-template-columns:repeat(2,1fr)} }

    .card{
      background:var(--card);
      border:1px solid var(--bd-strong);
      border-radius:16px;
      padding:16px;
      box-shadow: var(--shadow);
    }
    .card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
    .card-title{font-size:14px;color:var(--muted);font-weight:600;margin:0}
    .card-value{font-size:28px;font-weight:800;margin:6px 0 0;color:var(--text)}

    .quick-actions{display:flex;flex-wrap:wrap;gap:10px}
    .btn{
      border:0;border-radius:12px;padding:12px 16px;
      font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;gap:10px;align-items:center
    }
    .btn-primary{background:var(--primary);color:#fff}
    .btn-primary:hover{background:var(--primary-700)}
    .btn-ghost{
      background:#fff;color:var(--text);
      border:1px solid var(--bd-strong)
    }
    .btn-ghost:hover{background:#f1f5f9}

    .tag{
      display:inline-flex;gap:6px;align-items:center;
      background:var(--chip);border:1px solid var(--chipbd);color:var(--muted);
      font-size:12px;padding:3px 8px;border-radius:999px
    }

    .hr{height:1px;background:var(--bd-strong);margin:18px 0}
    .icon{width:18px;height:18px;display:inline-block}
  </style>
</head>
<body>

<?php
if (file_exists($NAVBAR)) {
  include $NAVBAR;
} else {
  echo "<p style='color:#b91c1c;background:#fee2e2;padding:8px 12px;border:1px solid #fecaca;border-radius:10px;margin:12px'>
          Aviso: no se encontró navbar en <code>".htmlspecialchars($NAVBAR, ENT_QUOTES, 'UTF-8')."</code>
        </p>";
}
?>

<main class="container">
  <h1 class="page-title">Panel de Administración</h1>
  <p class="subtitle">
    Bienvenido, <strong><?= htmlspecialchars($_SESSION['usuario'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?></strong>.
    <span class="tag">Admin</span>
  </p>

  <!-- Accesos rápidos -->
  <section class="card">
    <div class="card-head">
      <h3 class="card-title">Accesos rápidos</h3>
    </div>
    <div class="quick-actions">
      <a class="btn btn-primary" href="/Checador_Scap/empleados/crear.php">
        <svg class="icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 12c2.761 0 5-2.686 5-6s-2.239-6-5-6-5 2.686-5 6 2.239 6 5 6Zm0 2c-4.418 0-8 2.239-8 5v1a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-1c0-2.761-3.582-5-8-5Z"/></svg>
        Empleados
      </a>

      <a class="btn btn-ghost" href="/Checador_Scap/asistencias/">
        <svg class="icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1a11 11 0 1 0 11 11A11.013 11.013 0 0 0 12 1Zm1 11h5a1 1 0 0 1 0 2h-6a1 1 0 0 1-1-1V6a1 1 0 0 1 2 0Z"/></svg>
        Asistencias
      </a>

      <a class="btn btn-ghost" href="/Checador_Scap/reportes/">
        <svg class="icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 3h2v18H3zM9 10h2v11H9zM15 6h2v15h-2zM21 14h-2v7h2z"/></svg>
        Reportes
      </a>
    </div>
  </section>

  <div class="hr"></div>

  <!-- Tarjetas con datos -->
  <section class="grid grid-3">
    <article class="card">
      <div class="card-head">
        <h4 class="card-title">Empleados</h4>
        <span class="tag">Resumen</span>
      </div>
      <p class="card-value">
        <?= $totalEmpleados === null ? '—' : number_format($totalEmpleados) ?>
      </p>
      <p class="subtitle" style="margin-top:4px">Total registrados</p>
    </article>

    <article class="card">
      <div class="card-head">
        <h4 class="card-title">Asistencias hoy</h4>
        <span class="tag">Últimas 24h</span>
      </div>
      <p class="card-value">—</p>
      <p class="subtitle" style="margin-top:4px">Entradas registradas</p>
    </article>

    <article class="card">
      <div class="card-head">
        <h4 class="card-title">Incidencias</h4>
        <span class="tag">Monitoreo</span>
      </div>
      <p class="card-value">—</p>
      <p class="subtitle" style="margin-top:4px">Retardos / faltas</p>
    </article>
  </section>

  <div class="hr"></div>

  <section class="card">
    <h3 class="card-title">Notas</h3>
    <p class="subtitle" style="margin-top:6px">
      Desde aquí puedes navegar a <strong>Empleados</strong> para alta/edición de personal y horarios,
      consultar <strong>Asistencias</strong> o generar <strong>Reportes</strong>.
    </p>
  </section>
</main>

</body>
</html>
