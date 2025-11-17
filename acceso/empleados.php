<?php
/**
 * /Checador_Scap/acceso/empleados.php
 * UI simple de marcaje con verificación de huella local (Java).
 */
session_start();
$ROOT = dirname(__DIR__);
require_once $ROOT . '/auth/require_login.php';
require_once $ROOT . '/auth/require_role.php';
require_role(['admin','supervisor']);
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
  #bigClock { letter-spacing: 1px; font-variant-numeric: tabular-nums; }
</style>

</head>
<body class="bg-light">
<div class="container py-4">
  <h1>Checador</h1>
  <div class="d-flex align-items-center justify-content-between p-3 mb-3 bg-white rounded shadow-sm">
  <div>
    <div class="fs-1 fw-bold" id="bigClock">--:--:--</div>
    <div class="text-muted" id="bigDate">--</div>
  </div>
  <div>
    <span class="badge rounded-pill bg-secondary" id="fpStatusBadge">Lector: verificando…</span>
  </div>
</div>

  <div class="card">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-sm-3">
          <label class="form-label">Enlace (id_empleado)</label>
          <input id="f_id" class="form-control" placeholder="1001">
        </div>
        <div class="col-sm-2">
          <label class="form-label">Tipo</label>
          <select id="f_tipo" class="form-select">
            <option value="ENTRADA">ENTRADA</option>
            <option value="SALIDA">SALIDA</option>
          </select>
        </div>
        <div class="col-sm-5">
          <label class="form-label">Justificación (si hay retardo)</label>
          <input id="f_just" class="form-control" placeholder="Motivo del retardo">
        </div>
        <div class="col-sm-2 d-grid">
          <button class="btn btn-primary" id="btnPreview">Cargar</button>
        </div>
      </div>

      <hr>
      <div id="empBox" class="text-muted">Sin datos</div>
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-success" id="btnVerificar" disabled>Verificar huella y marcar</button>
      </div>
    </div>
  </div>
</div>

<script>
/* === API base: usa SCRIPT_NAME para evitar problemas de path === */
const API  = '<?= htmlspecialchars(rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"), ENT_QUOTES) ?>/api.php';
const CSRF = '<?= $csrf ?>';
const FP_BASE = "http://127.0.0.1:8787";

let current = { id:0, firma:null, nombre:'', apellido:'' };

function tickClock(){
  const now = new Date();
  const hh = String(now.getHours()).padStart(2,'0');
  const mm = String(now.getMinutes()).padStart(2,'0');
  const ss = String(now.getSeconds()).padStart(2,'0');
  document.getElementById('bigClock').textContent = `${hh}:${mm}:${ss}`;
  document.getElementById('bigDate').textContent = now.toLocaleDateString('es-MX', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
}
setInterval(tickClock, 1000); tickClock();

async function updateFpStatus(){
  const badge = document.getElementById('fpStatusBadge');
  try {
    // Abrimos para bajar latencia y confirmar dispositivo
    const r = await fetch(FP_BASE + "/api/device/open");
    if (!r.ok) throw new Error();
    badge.className = "badge rounded-pill bg-success";
    badge.textContent = "Lector: listo";
  } catch {
    badge.className = "badge rounded-pill bg-danger";
    badge.textContent = "Lector: no disponible";
  }
}
updateFpStatus();

/* === Helper: fetch que siempre intenta parsear JSON y, si no, muestra el texto === */
async function jfetch(url, opts={}) {
  const r   = await fetch(url, opts);
  const txt = await r.text();     // leemos como texto siempre
  let data;
  try { data = JSON.parse(txt); } // intentamos JSON
  catch (e) {
    // Muestra el error real en consola y lanza una excepción con detalle
    console.error('Respuesta no-JSON de', url, 'HTTP', r.status, txt);
    throw new Error(`HTTP ${r.status}. Cuerpo: ${txt.slice(0, 400)}`);
  }
  return data;
}

async function fpOpen(){ try{ await fetch(FP_BASE+"/api/device/open"); }catch(e){ /* no bloquear UI */ } }

/* ====== UI ====== */
document.getElementById('btnPreview').addEventListener('click', async ()=>{
  const id = +document.getElementById('f_id').value;
  if (!id) { alert('Enlace inválido'); return; }
  try{
    // 🔧 antes: fetch + r.json(); ahora: jfetch con mejor diagnóstico
    const j = await jfetch(`${API}?action=preview&id=${id}`);
    if(!j.ok){ alert(j.msg||'No encontrado'); return; }

    current.id      = j.empleado.id_empleado;
    current.firma   = j.empleado.firma || null;
    current.nombre  = j.empleado.nombre||'';
    current.apellido= j.empleado.apellido||'';

    const hoy = j.hoy ? `Entrada: ${j.hoy.entrada} / Salida: ${j.hoy.salida} / Tol: ${j.hoy.tolerancia_min} min` : 'Sin horario hoy';
    document.getElementById('empBox').innerHTML = `
      <div><strong>${current.nombre} ${current.apellido}</strong> — Enlace <code>${current.id}</code></div>
      <div>${hoy}</div>
      <div>Huella: ${current.firma ? 'registrada ✔' : 'no registrada ✖'}</div>`;

    document.getElementById('btnVerificar').disabled = !current.firma;
    fpOpen();
  }catch(e){
    // Ahora verás el HTTP code o el HTML que te llegó (login/error) en consola
    console.error(e);
    alert('Error de red (detalles en consola).');
  }
});

document.getElementById('btnVerificar').addEventListener('click', async ()=>{
  if (!current.firma) { alert('Sin huella registrada'); return; }
  try{
    await fpOpen();

    // Verificación local con el servicio Java
    const v = await jfetch(FP_BASE + "/api/verify", {
      method:"POST", headers:{ "Content-Type":"application/json" },
      body: JSON.stringify({ template: current.firma })
    });
    if (!v.ok) { alert(v.error||'Error de verificación'); return; }
    if (!v.match) { alert('No coincide la huella'); return; }

    const tipo = document.getElementById('f_tipo').value;
    const just = document.getElementById('f_just').value.trim();

    const mj = await jfetch(API+'?action=mark', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF':CSRF},
      body: JSON.stringify({ id_empleado: current.id, tipo, justificacion: just })
    });
    if (!mj.ok) { alert(mj.msg||'No se pudo marcar'); return; }

    alert(`Marcado ${tipo} OK. Retardo: ${mj.retardo_minutos} min`);
  }catch(e){
    console.error(e);
    alert('Error de red (detalles en consola).');
  }
});
</script>

</body>
</html>
