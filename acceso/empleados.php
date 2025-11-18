<?php
/**
 * /Checador_Scap/acceso/empleados.php
 * Kiosko: ENTRADA/SALIDA por huella, identificación automática (sin escribir enlace).
 */
session_start();
$ROOT = dirname(__DIR__);
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Checador | Empleados</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --accent:#2563eb;
      --ok:#16a34a;
      --danger:#dc2626;
      --ink:#0f172a;
      --muted:#6b7280;
    }
    body{background:#f5f7fb;}
    .kiosk-nav{height:64px;background:#ffffff;box-shadow:0 2px 8px rgba(15,23,42,.05);display:flex;align-items:center;justify-content:space-between;padding:0 16px;}
    .brand{font-size:28px;font-weight:800;letter-spacing:.4px;color:var(--ink)}
    .clockCard{background:#fff;border-radius:14px;padding:16px 18px;box-shadow:0 1px 6px rgba(15,23,42,.06)}
    #bigClock{letter-spacing:1px;font-variant-numeric:tabular-nums}
    .status-pill{border-radius:999px;padding:6px 10px;font-size:12px}
    .status-pill-text{border-radius:999px;padding:3px 5px;font-size:12px; margin-left: 50px; margin-right: 50px; text-align: center; align-items: center}
    .tiles{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-top:24px}
    .tile{
      background:#fff;border-radius:18px;box-shadow:0 4px 20px rgba(15,23,42,.08);
      padding:28px;display:grid;place-items:center;cursor:pointer;user-select:none;
      transition:transform .08s ease, box-shadow .12s ease;
      min-height:320px;
    }
    .tile:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(15,23,42,.12)}
    .tile h2{font-size:38px;margin-bottom:14px}
    .tile svg{width:140px;height:140px}
    .tile.in{border:2px solid #22c55e20}
    .tile.out{border:2px solid #ef444420}
    .muted{color:var(--muted)}

    /* Modal escaneo */
    .sheet-back{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:1060}
    .sheet{width:min(740px,95vw);background:#fff;border-radius:32px;padding:28px 26px 24px;box-shadow:0 10px 36px rgba(0,0,0,.18);text-align:center}
    .sheet .x{position:absolute;right:18px;top:12px;font-size:24px;cursor:pointer}
    .fp-ico{width:180px;height:180px;margin:16px auto 10px}
    .err{color:var(--danger);font-weight:600}

    /* Tarjeta resultado */
    .profile-back{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:1061;background:rgba(0,0,0,.35)}
    .profile{
      width:min(820px,96vw); background:#fff; border-radius:32px; padding:26px;
      text-align:center; box-shadow:0 14px 44px rgba(0,0,0,.2);
    }
    .profile .title{font-size:32px;font-weight:800;letter-spacing:.5px;margin-bottom:10px}
    .avatar{width:132px;height:132px;border-radius:999px;object-fit:cover;border:4px solid #e5e7eb;background:#f2f4f7;margin:10px auto 14px}
    .kv{font-size:22px;font-weight:800}
    .kv small{display:block;color:var(--muted);font-weight:600;margin-top:-2px}
  </style>
</head>
<body>
  <nav class="kiosk-nav">
    <div class="brand">Checador</div>
    <div>
      <span id="fpStatusBadge" class="status-pill bg-secondary text-white">Lector: verificando…</span>
      <a href="../index.php" class="ms-3" title="Iniciar sesión (admin)">
        <!-- ícono perfil -->
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-5.33 0-8 2.667-8 5v1h16v-1c0-2.333-2.67-5-8-5Z" fill="#0f172a"/></svg>
      </a>
    </div>
  </nav>

  <div class="container my-4">
    <div class="clockCard d-flex align-items-center justify-content-between">
      <div>
        <div class="fs-1 fw-bold" id="bigClock">--:--:--</div>
        <div class="text-muted" id="bigDate">--</div>
      </div>
    </div>

    <div class="tiles">
      <!-- ENTRADA -->
      <div class="tile in" id="btnEntrada" role="button" aria-label="Registrar entrada">
        <div class="text-center">
          <h2 class="text-success">Entrada</h2>
          <svg viewBox="0 0 24 24"><path d="M4 5a2 2 0 0 1 2-2h9a1 1 0 0 1 0 2H6v14h9a1 1 0 0 1 0 2H6a2 2 0 0 1-2-2z" fill="#10b981"/><path d="M21 12a1 1 0 0 1-.29.71l-4 4a1 1 0 1 1-1.42-1.42L17.59 13H10a1 1 0 0 1 0-2h7.59l-2.3-2.29A1 1 0 0 1 16.7 7.3l4 4A1 1 0 0 1 21 12z" fill="#10b981"/></svg>
          <p class="mt-2 muted">Toca para escanear tu huella</p>
        </div>
      </div>

      <!-- SALIDA -->
      <div class="tile out" id="btnSalida" role="button" aria-label="Registrar salida">
        <div class="text-center">
          <h2 class="text-danger">Salida</h2>
          <svg viewBox="0 0 24 24"><path d="M20 5a2 2 0 0 0-2-2H9a1 1 0 1 0 0 2h9v14H9a1 1 0 1 0 0 2h9a2 2 0 0 0 2-2z" fill="#ef4444"/><path d="M3 12a1 1 0 0 0 .29.71l4 4a1 1 0 0 0 1.42-1.42L6.41 13H14a1 1 0 0 0 0-2H6.41l2.3-2.29A1 1 0 0 0 7.3 7.3l-4 4A1 1 0 0 0 3 12z" fill="#ef4444"/></svg>
          <p class="mt-2 muted">Toca para escanear tu huella</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de escaneo -->
  <div class="sheet-back" id="scanBack" aria-hidden="true">
    <div class="sheet position-relative">
      <div class="x" id="scanClose" aria-label="Cerrar">×</div>
      <div id="scanTitle" class="h3 fw-bold">ENTRADA</div>
      <div class="h5 text-muted mb-2">Escanea tu huella</div>
      <svg class="fp-ico" viewBox="0 0 24 24" fill="none"><path d="M7.5 8.5C8 7 9.7 6 12 6c3 0 4.5 2 4.5 4.5 0 1.8-.6 3.6-1.4 5.1" stroke="#3b82f6" stroke-width="1.5"/><path d="M9 12c0-1.7 1-3 3-3s3 1.3 3 3c0 2.7-1 5.5-2.8 7.5" stroke="#3b82f6" stroke-width="1.5"/><path d="M5.5 14c.7-3.5 3.2-6 6.5-6 3.9 0 6.5 3 6.5 7.5 0 2.3-.7 4.3-1.8 6" stroke="#3b82f6" stroke-width="1.5"/></svg>
      <div id="scanMsg" class="err"></div>
    </div>
  </div>

  <!-- Tarjeta de resultado -->
  <div class="profile-back" id="profBack" aria-hidden="true">
    <div class="profile">
      <div id="profTitle" class="title">BIENVENIDO</div>
      <img id="profAvatar" class="avatar" src="/assets/profiles-img/perfil_default.jpg" alt="Foto del empleado">
      <div class="kv" id="profName">NOMBRE EMPLEADO</div>
      <div class="mt-2"><span class="fw-semibold">Enlace:</span> <span id="profEnlace"></span></div>
      <div class="mt-1"><span class="fw-semibold">Unidad:</span> <span id="profUnidad"></span></div>
      <div class="mt-1"><span class="fw-semibold">Cargo:</span> <span id="profCargo"></span></div>
      <div class="mt-1"><span class="fw-semibold">Turno:</span> <span id="profTurno"></span></div>
      <div class="mt-3 fs-5"><span id="profLblHora">Hora entrada:</span> <span class="fw-bold" id="profHora"></span></div>
      <div class="text-muted mt-2" id="profNote">Se cerrará en 5 segundos…</div>
    </div>
  </div>

<script>
/* === Endpoints === */
const API  = '<?= htmlspecialchars(rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"), ENT_QUOTES) ?>/api.php';
const CSRF = '<?= $csrf ?>';
const FP_BASE = "http://127.0.0.1:8787";

/* ==== Reloj ==== */
function tickClock(){
  const now = new Date();
  const hh = String(now.getHours()).padStart(2,'0');
  const mm = String(now.getMinutes()).padStart(2,'0');
  const ss = String(now.getSeconds()).padStart(2,'0');
  document.getElementById('bigClock').textContent = `${hh}:${mm}:${ss}`;
  document.getElementById('bigDate').textContent =
    now.toLocaleDateString('es-MX',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
}
setInterval(tickClock, 1000); tickClock();

/* ==== Estado del lector ==== */
async function updateFpStatus(){
  const badge = document.getElementById('fpStatusBadge');
  try {
    const r = await fetch(FP_BASE + "/api/device/open"); // baja latencia
    if (!r.ok) throw new Error();
    badge.className = "status-pill bg-success text-white";
    badge.textContent = "Lector: listo";
  } catch {
    badge.className = "status-pill bg-danger text-white";
    badge.textContent = "Lector: no disponible";
  }
}
updateFpStatus();

/* Helper fetch → JSON con buen diagnóstico */
async function jfetch(url, opts={}) {
  const r   = await fetch(url, opts);
  const txt = await r.text();
  try { return JSON.parse(txt); }
  catch(e){ console.error('Respuesta no-JSON', url, r.status, txt); throw new Error(txt); }
}

async function fpOpen(){ try{ await fetch(FP_BASE+"/api/device/open"); }catch{} }

/* ==== UI helpers ==== */
const scanBack = document.getElementById('scanBack');
const scanMsg  = document.getElementById('scanMsg');
const scanTitle= document.getElementById('scanTitle');
document.getElementById('scanClose').onclick = ()=>{ scanBack.style.display='none'; };

const profBack = document.getElementById('profBack');
function showProfile({tipo, emp, hora}){
  document.getElementById('profTitle').textContent = (tipo==='ENTRADA') ? 'BIENVENIDO' : 'HASTA LUEGO!!';
  document.getElementById('profLblHora').textContent = (tipo==='ENTRADA') ? 'Hora entrada:' : 'Hora salida:';
  document.getElementById('profName').textContent   = (emp.nombre||'') + ' ' + (emp.apellido||'');
  document.getElementById('profEnlace').textContent = emp.id_empleado;
  document.getElementById('profUnidad').textContent = emp.unidad_medica || '—';
  document.getElementById('profCargo').textContent  = emp.cargo || '—';
  document.getElementById('profTurno').textContent  = emp.turno || '—';
  document.getElementById('profHora').textContent   = hora || '--:--:--';
  document.getElementById('profAvatar').src         = emp.foto_url || '';
  profBack.style.display='flex';
  setTimeout(()=>{ profBack.style.display='none'; }, 5000);
}

/* ==== Flujo: click → identificar → marcar ==== */
async function runFlow(tipo){
  await fpOpen();
  scanMsg.textContent = '';
  scanTitle.textContent = (tipo==='ENTRADA')?'ENTRADA':'SALIDA';
  scanBack.style.display='flex';

  try{
    // 1) IDENTIFICAR (el servicio Java adquiere y el backend compara)
    const idr = await jfetch(API+'?action=identify', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF':CSRF},
      body: JSON.stringify({})
    });
    if (!idr.ok) { scanMsg.textContent = idr.msg || 'No se identificó la huella'; return; }
    const emp = idr.empleado;

    // 2) MARCAR
    const mj = await jfetch(API+'?action=mark', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF':CSRF},
      body: JSON.stringify({ id_empleado: emp.id_empleado, tipo, justificacion: "" })
    });
    if (!mj.ok){ scanMsg.textContent = mj.msg || 'No se pudo marcar'; return; }

    // 3) Mostrar tarjeta bonita por 5s
    scanBack.style.display='none';
    showProfile({ tipo, emp, hora: mj.hora_str });
  }catch(e){
    console.error(e);
    scanMsg.textContent = 'Error de red. Intenta de nuevo.';
  }
}

document.getElementById('btnEntrada').addEventListener('click', ()=> runFlow('ENTRADA'));
document.getElementById('btnSalida').addEventListener('click',  ()=> runFlow('SALIDA'));

/* Cerrar overlays con ESC */
document.addEventListener('keydown', (e)=>{
  if(e.key==='Escape'){ scanBack.style.display='none'; profBack.style.display='none'; }
});
</script>
</body>
</html>
