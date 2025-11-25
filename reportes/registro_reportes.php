<?php
/**
 * /Checador_Scap/reportes/gestion.php
 * Admin: Gestión de reportes (generar, listar y visualizar en modal).
 */
session_start();
$ROOT = dirname(__DIR__);
require_once $ROOT . '/auth/require_login.php';
require_once $ROOT . '/auth/require_role.php';
require_role(['admin']);

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];
$NAVBAR = $ROOT . '/components/navbar.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Gestión de reportes | Administración</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --bg:#f6f8fb;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --bd:#e5e7eb;
      --primary:#2563eb;
      --primary-700:#1d4ed8;
      --shadow:0 2px 12px rgba(15,23,42,.06);
      --shadow-lg:0 16px 40px rgba(2,6,23,.18);

      /* Fondo corporativo (SIEMPRE visible) */
      --bg-image:url('/Checador_Scap/assets/img/logo_isstech.png');
      --bg-size:clamp(420px, 52vw, 420px);
    }

    /* ===== Fondo y tipografía coherente ===== */
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
      background:var(--bg);
      color:var(--text);
    }
    body::before{
      content:"";
      position:fixed; inset:0; z-index:-1;
      background-image:var(--bg-image);
      background-repeat:no-repeat;
      background-position:center center;
      background-size:var(--bg-size) auto;
      opacity:var(--bg-opacity);
      pointer-events:none;
    }

    .container{max-width:1200px}
    .card-soft{border-radius:14px; box-shadow:0 2px 12px rgba(15,23,42,.06)}
    .table thead th{position:sticky; top:0; background:#fff; z-index:1}
    .badge-pill{border-radius:999px}
    .toast-container{z-index:1086}
    .mono{font-variant-numeric: tabular-nums}
  </style>
</head>
<body>
<?php if (file_exists($NAVBAR)) include $NAVBAR; ?>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastArea"></div>

<div class="container my-3">
  <a href="/Checador_Scap/vistas-roles/vista-admin.php"
     class="btn btn-primary"
     style="border-radius:10px">
    ← Regresar
  </a>
</div>

<div class="container my-4">

  <!-- Filtros / Generación -->
  <div class="card card-soft p-3 mb-3">
    <div class="row g-2 align-items-end">
      <h3 class="mb-3">Gestión de reportes</h3>
      <div class="col-md-3">
        <label class="form-label">Tipo de reporte</label>
        <select class="form-select" id="r_tipo">
          <option value="diario">Diario</option>
          <option value="semanal">Semanal</option>
          <option value="quincenal">Quincenal</option>
          <option value="mensual">Mensual</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control" id="r_start" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Hasta</label>
        <input type="date" class="form-control" id="r_end" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Enlace</label>
        <input type="text" class="form-control" id="r_emp" placeholder="(Todos)">
      </div>
    </div>
    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-primary" id="btnGen">Generar PDF</button>
      <button class="btn btn-outline-secondary" id="btnAuto">Generar rango automático</button>
    </div>
  </div>

  <!-- Listado de reportes -->
  <div class="card card-soft p-2">
    <div class="d-flex align-items-center justify-content-between px-2">
      <h5 class="mb-2 mt-2">Historial de reportes</h5>
      <span class="badge text-bg-light">Total: <span id="totalTag">0</span></span>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Tipo de reporte</th>
            <th>Rango del reporte</th>
            <th style="width:180px">Creado</th>
            <th style="width:120px">Acciones</th>
          </tr>
        </thead>
        <tbody id="tbody">
          <tr><td colspan="4" class="text-muted text-center py-3">Sin reportes aún.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div class="d-flex gap-2 justify-content-center mt-3 flex-wrap" id="pager"></div>
  </div>
</div>

<!-- Modal visor PDF -->
<div class="modal fade" id="mdlView" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 96vw;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Vista del reporte</h5>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnOpenNew">Abrir en pestaña</button>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" style="height: calc(100dvh - 220px);">
        <iframe id="pdfFrame" src="" style="width:100%;height:100%;border:0;" title="Reporte PDF"></iframe>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API  = '<?= htmlspecialchars(rtrim(dirname($_SERVER["REQUEST_URI"]), "/"), ENT_QUOTES) ?>/api.php';
const CSRF = '<?= $csrf ?>';

const $tbody = document.getElementById('tbody');
const $pager = document.getElementById('pager');
const $total = document.getElementById('totalTag');

function toast(msg, kind='success'){
  const area = document.getElementById('toastArea');
  const el = document.createElement('div');
  const bg = kind==='success' ? 'text-bg-success' :
             kind==='error'   ? 'text-bg-danger'  :
             kind==='info'    ? 'text-bg-primary' : 'text-bg-secondary';
  el.className = `toast ${bg} border-0`;
  el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div>
    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
  area.appendChild(el); new bootstrap.Toast(el, {delay:2600}).show();
}

/* ===== Estado de paginación ===== */
let state = { page: 1, pages: 1, limit: 5 };

/* ===== Render de paginación (igual estilo que empleados) ===== */
function renderPager(){
  const p = state.page, P = state.pages;
  if (P <= 1){ $pager.innerHTML = ''; return; }

  const btn = (i, label=i, cur=false) =>
    `<button class="btn btn-sm ${cur?'btn-primary':'btn-outline-secondary'}" data-page="${i}">${label}</button>`;

  const parts = [];
  parts.push(btn(1,1,p===1));
  if (p > 3) parts.push('<span class="mx-1">…</span>');
  for (let i = Math.max(2,p-1); i <= Math.min(P-1,p+1); i++) parts.push(btn(i,i,p===i));
  if (p < P-2) parts.push('<span class="mx-1">…</span>');
  if (P > 1) parts.push(btn(P,P,p===P));
  $pager.innerHTML = parts.join('');
}

/* ===== Click en paginación ===== */
$pager.addEventListener('click', (e)=>{
  const b = e.target.closest('button[data-page]'); if(!b) return;
  load(+b.dataset.page);
});

/* ===== Cargar listado con page/limit ===== */
async function load(page=1){
  state.page = page;
  const params = new URLSearchParams({
    action: 'list',
    page: state.page,
    limit: state.limit
  });
  $tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">Cargando…</td></tr>`;
  try{
    const r = await fetch(`${API}?${params}`, {headers:{'X-CSRF':CSRF}});
    const j = await r.json();
    if(!j.ok){
      $tbody.innerHTML = `<tr><td colspan="4" class="text-danger">${j.msg||'Error'}</td></tr>`;
      return;
    }

    $total.textContent = j.total ?? 0;
    state.pages = j.pages ?? 1;
    renderPager();

    if((j.rows||[]).length===0){
      $tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">Sin reportes</td></tr>`;
      return;
    }

    $tbody.innerHTML = j.rows.map(r => `
      <tr>
        <td class="text-capitalize">${r.tipo_reporte||'—'}</td>
        <td class="mono">${r.fecha_inicio} — ${r.fecha_fin}</td>
        <td class="mono">${r.fecha_generacion||'—'}</td>
        <td>
          <button class="btn btn-sm btn-outline-secondary" data-act="view" data-id="${r.id_reporte}">Ver</button>
        </td>
      </tr>
    `).join('');
  }catch(_){
    $tbody.innerHTML = `<tr><td colspan="4" class="text-danger">Error de red</td></tr>`;
  }
}

/* ===== Ver PDF en modal ===== */
document.addEventListener('click', (e)=>{
  const b = e.target.closest('button[data-act="view"]'); if(!b) return;
  const id = +b.dataset.id;
  const url = `${API}?action=pdf&id=${id}`;
  document.getElementById('pdfFrame').src = url;
  document.getElementById('btnOpenNew').onclick = ()=> window.open(url, '_blank');
  new bootstrap.Modal(document.getElementById('mdlView')).show();
});

/* ===== Generar reporte ===== */
document.getElementById('btnGen').addEventListener('click', async ()=>{
  const tipo = document.getElementById('r_tipo').value;
  const start = document.getElementById('r_start').value;
  const end   = document.getElementById('r_end').value;
  const emp   = document.getElementById('r_emp').value.trim(); // enlace opcional

  if(!start || !end){ toast('Selecciona el rango de fechas','info'); return; }
  const payload = { csrf: CSRF, tipo_reporte: tipo, start, end, enlace: emp||null };

  try{
    const r = await fetch(API + '?action=generate', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF':CSRF},
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    if(!j.ok){ toast(j.msg||'No se pudo generar el reporte','error'); return; }
    toast('Reporte generado');
    load(1);
    // Abrir visor directamente:
    setTimeout(()=>{
      const url = `${API}?action=pdf&id=${j.id_reporte}`;
      document.getElementById('pdfFrame').src = url;
      document.getElementById('btnOpenNew').onclick = ()=> window.open(url, '_blank');
      new bootstrap.Modal(document.getElementById('mdlView')).show();
    }, 400);
  }catch(_){ toast('Error de red','error'); }
});

/* ===== Rango automático por tipo ===== */
document.getElementById('btnAuto').addEventListener('click', ()=>{
  const tipo = document.getElementById('r_tipo').value;
  const today = new Date();
  let s, e;

  if (tipo==='diario'){
    s = e = today.toISOString().slice(0,10);
  } else if (tipo==='semanal'){
    const d = today.getDay(); // 0..6 (dom=0)
    const diffMon = (d===0?6:d-1);
    const mon = new Date(today.getTime() - diffMon*86400000);
    const sun = new Date(mon.getTime() + 6*86400000);
    s = mon.toISOString().slice(0,10);
    e = sun.toISOString().slice(0,10);
  } else if (tipo==='quincenal'){
    const y = today.getFullYear(), m = today.getMonth()+1;
    const day = today.getDate();
    if (day <= 15){ s = `${y}-${String(m).padStart(2,'0')}-01`; e = `${y}-${String(m).padStart(2,'0')}-15`; }
    else {
      s = `${y}-${String(m).padStart(2,'0')}-16`;
      const last = new Date(y, m, 0).getDate();
      e = `${y}-${String(m).padStart(2,'0')}-${String(last).padStart(2,'0')}`;
    }
  } else { // mensual
    const y = today.getFullYear(), m = today.getMonth()+1;
    s = `${y}-${String(m).padStart(2,'0')}-01`;
    const last = new Date(y, m, 0).getDate();
    e = `${y}-${String(m).padStart(2,'0')}-${String(last).padStart(2,'0')}`;
  }
  document.getElementById('r_start').value = s;
  document.getElementById('r_end').value = e;
});

/* Init */
load(1);
</script>
</body>
</html>
