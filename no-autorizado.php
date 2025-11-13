<?php http_response_code(403); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Acceso no autorizado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--bg:#0f172a;--card:#CCCCCC;--fg:#000;--mut:#000;--btn:#2563eb; --bg-image: url('/Checador_Scap/assets/img/logo_login_scap.jpg');
    --bg-size: clamp(520px, 52vw, 720px);}
    body{margin:0;background:linear-gradient(180deg,#FFFFFF,#FFFFFF);color:var(--fg);
         font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;display:grid;min-height:100dvh;place-items:center}
    .box{background:var(--card);border:1px solid #1f2937;border-radius:14px;padding:24px 26px;max-width:560px;box-shadow:0 12px 40px rgba(0,0,0,.35)}
    h1{margin:0 0 10px;font-size:22px}
    p{margin:0 0 16px;color:var(--mut)}
    a.btn{display:inline-block;background:var(--btn);color:#000;text-decoration:none;padding:10px 14px;border-radius:10px;font-weight:700}
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
      opacity:.10;
      filter:saturate(.95) brightness(1.02) contrast(1.03);
      pointer-events:none;
    }
  </style>
</head>
<body>
  <div class="box">
    <h1>Acceso no autorizado</h1>
    <p>No tienes permisos para ver esta página o tu sesión no incluye el rol requerido.</p>
    <a class="btn" href="/Checador_Scap/index.php">Volver al inicio de sesión</a>
  </div>
</body>
</html>
