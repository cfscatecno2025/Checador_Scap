<?php
/**
 * /Checador_Scap/asistencias/registro.php
 * Admin: tabla de asistencias con faltas, justificaciones y carga de documentos.
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
  <title>Asistencias | Administración</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f6f8fb}
    .container{max-width:1200px}
    .card-soft{border-radius:14px; box-shadow:0 2px 12px rgba(15,23,42,.06)}
    .badge-pill{border-radius:999px}
    .state-ok{background:#e6f9ed;color:#0c6b3e}
    .state-ret{background:#fff4e6;color:#b35c00}
    .state-inc{background:#e8f1ff;color:#0b4fb3}
    .state-fal{background:#ffebee;color:#b00020}
    .state-jus{background:#eefbf7;color:#0b8063}
    .table thead th{position:sticky; top:0; background:#fff; z-index:1}
    .toast-container{z-index:1086}
  </style>
</head>
<body>
<?php if (file_exists($NAVBAR)) include $NAVBAR; ?>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastArea"></div>

<div class="container my-4">
  <div class="card card-soft p-3 mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Desde</label>
        <input type="date" class="form-control" id="f_start">
      </div>
      <div class="col-md-3">
        <label class="form-label">Hasta</label>
        <input type="date" class="form-control" id="f_end">
      </div>
      <div class="col-md-3">
        <label class="form-label">Enlace (opcional)</label>
        <input type="text" class="form-control" id="f_emp" placeholder="1001">
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1" id="btnLoad">Buscar</button>
        <button class="btn btn-outline-secondary" id="btnClear">Limpiar</button>
      </div>
    </div>
    <div class="mt-3 d-flex gap-2 flex-wrap">
      <span class="badge text-bg-light">Registros: <span id="statRows">0</span></span>
      <span class="badge state-fal">Faltas: <span id="statFal">0</span></span>
      <span class="badge state-jus">Justificadas: <span id="statJus">0</span></span>
      <span class="badge state-inc">Incompletas: <span id="statInc">0</span></span>
    </div>
  </div>

  <div class="table-responsive card card-soft p-2">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th style="width:110px">Fecha</th>
          <th>Empleado</th>
          <th>Unidad</th>
          <th>Horario</th>
          <th>Entrada</th>
          <th>Salida</th>
          <th style="width:160px">Estado</th>
          <th style="width:140px">Justificante</th>
          <th style="width:120px">Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="9" class="text-center text-muted py-3">Seleccione un rango de fechas y busque…</td></tr>
      </tbody>
    </table>
  </div>

  <div class="d-flex gap-2 justify-content-center mt-3 flex-wrap" id="pager"></div>
</div>

<!-- Modal Justificar -->
<div class="modal fade" id="mdlJust" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content" id="frmJust" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">Justificar faltas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="id_empleado" id="j_id">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Empleado</label>
            <input class="form-control" id="j_emp_name" disabled>
          </div>
          <div class="col-md-3">
            <label class="form-label">Desde</label>
            <input type="date" class="form-control" id="j_start" name="fecha_inicio" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Hasta</label>
            <input type="date" class="form-control" id="j_end" name="fecha_fin" required>
          </div>
          <div class="col-12">
            <label class="form-label">Motivo (opcional)</label>
            <input class="form-control" id="j_motivo" name="motivo" placeholder="Justificante médico, comisión, etc.">
          </div>
          <div class="col-12">
            <label class="form-label">Documento (PDF/JPG/PNG)</label>
            <input type="file" class="form-control" id="j_file" name="archivo" accept=".pdf,.jpg,.jpeg,.png" required>
            <div class="form-text">Se guardará en <code>/assets/justificantes</code></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar justificante</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API  = '<?= htmlspecialchars(rtrim(dirname($_SERVER["REQUEST_URI"]), "/"), ENT_QUOTES) ?>/api.php';
const CSRF = '<?= $csrf ?>';

const $tbody = document.getElementById('tbody');
const $pager = document.getElementById('pager');

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

let state = {page:1, pages:1, limit:20, start:'', end:'', emp:''};

function renderPager(){
  const p = state.page, P = state.pages;
  if(P<=1){ $pager.innerHTML=''; return; }
  const btn = (i, label=i, cur=false)=> `<button class="btn btn-sm ${cur?'btn-primary':'btn-outline-secondary'}" data-page="${i}">${label}</button>`;
  const parts = [];
  parts.push(btn(1,1,p===1));
  if(p>3) parts.push('<span class="mx-1">…</span>');
  for(let i=Math.max(2,p-1); i<=Math.min(P-1,p+1); i++) parts.push(btn(i,i,p===i));
  if(p<P-2) parts.push('<span class="mx-1">…</span>');
  if(P>1) parts.push(btn(P,P,p===P));
  $pager.innerHTML = parts.join('');
}
$pager.addEventListener('click', (e)=>{
  const b = e.target.closest('button[data-page]'); if(!b) return;
  load(+b.dataset.page);
});

function stateBadge(s){
  const map = {
    OK: 'state-ok', RETARDO:'state-ret', INCOMPLETA:'state-inc',
    FALTA:'state-fal', JUSTIFICADA:'state-jus'
  };
  return `<span class="badge ${map[s]||'text-bg-light'} badge-pill">${s}</span>`;
}

async function load(page=1){
  state.page = page;
  const params = new URLSearchParams({
    action:'list',
    page: state.page, limit: state.limit,
    start: state.start, end: state.end, emp: state.emp
  });
  $tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-3">Cargando…</td></tr>`;
  try{
    const r = await fetch(`${API}?${params}`, {headers:{'X-CSRF':CSRF}});
    const j = await r.json();
    if(!j.ok){ $tbody.innerHTML = `<tr><td colspan="9" class="text-danger">${j.msg||'Error'}</td></tr>`; return; }

    document.getElementById('statRows').textContent = j.total_rows ?? 0;
    document.getElementById('statFal').textContent  = j.stats?.faltas ?? 0;
    document.getElementById('statJus').textContent  = j.stats?.justificadas ?? 0;
    document.getElementById('statInc').textContent  = j.stats?.incompletas ?? 0;

    state.pages = j.pages ?? 1; renderPager();

    if((j.rows||[]).length===0){
      $tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-3">Sin datos</td></tr>`;
      return;
    }
    $tbody.innerHTML = j.rows.map(r => `
      <tr>
        <td><span class="fw-semibold">${r.fecha}</span><div class="text-muted small">${r.dow||''}</div></td>
        <td>
          <div class="fw-semibold">${r.empleado?.nombre||''} ${r.empleado?.apellido||''}</div>
          <div class="text-muted small">Enlace ${r.empleado?.id}</div>
        </td>
        <td>${r.empleado?.unidad_medica||'—'}</td>
        <td>${r.horario || '—'}</td>
        <td>${r.entrada || '—'}</td>
        <td>${r.salida  || '—'}</td>
        <td>${stateBadge(r.estado)}</td>
        <td>
          ${r.justificante_url ? `<a href="${r.justificante_url}" target="_blank" class="btn btn-sm btn-outline-secondary">Ver</a>` : '—'}
        </td>
        <td>
          <button class="btn btn-sm btn-primary" data-act="just" data-id="${r.empleado?.id}" data-name="${(r.empleado?.nombre||'')+' '+(r.empleado?.apellido||'')}" data-date="${r.fecha}">Justificar</button>
        </td>
      </tr>
    `).join('');
  }catch(_){
    $tbody.innerHTML = `<tr><td colspan="9" class="text-danger">Error de red</td></tr>`;
  }
}

document.getElementById('btnLoad').addEventListener('click', ()=>{
  const s = document.getElementById('f_start').value;
  const e = document.getElementById('f_end').value;
  if(!s || !e){ toast('Selecciona rango de fechas','info'); return; }
  state.start = s; state.end = e; state.emp = document.getElementById('f_emp').value.trim();
  load(1);
});
document.getElementById('btnClear').addEventListener('click', ()=>{
  document.getElementById('f_start').value='';
  document.getElementById('f_end').value='';
  document.getElementById('f_emp').value='';
  state = {page:1,pages:1,limit:20,start:'',end:'',emp:''};
  $tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-3">Seleccione un rango de fechas y busque…</td></tr>`;
  document.getElementById('statRows').textContent='0';
  document.getElementById('statFal').textContent='0';
  document.getElementById('statJus').textContent='0';
  document.getElementById('statInc').textContent='0';
});

/* Abrir modal de justificante desde una fila */
document.addEventListener('click', (e)=>{
  const b = e.target.closest('button[data-act="just"]'); if(!b) return;
  const id   = b.dataset.id;
  const name = b.dataset.name || '';
  const date = b.dataset.date;
  document.getElementById('j_id').value = id;
  document.getElementById('j_emp_name').value = name;
  document.getElementById('j_start').value = state.start || date;
  document.getElementById('j_end').value   = state.end   || date;
  document.getElementById('j_motivo').value = '';
  document.getElementById('j_file').value = '';
  new bootstrap.Modal(document.getElementById('mdlJust')).show();
});

/* Enviar justificante (multipart) */
document.getElementById('frmJust').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.set('action','justify_upload');
  fd.set('id_empleado', document.getElementById('j_id').value);
  try{
    const r = await fetch(API, {method:'POST', body:fd, headers:{'X-CSRF':CSRF}});
    const j = await r.json();
    if(!j.ok){ toast(j.msg||'No se pudo guardar justificante','error'); return; }
    toast('Justificante guardado','success');
    bootstrap.Modal.getInstance(document.getElementById('mdlJust')).hide();
    load(state.page);
  }catch(_){ toast('Error de red','error'); }
});

/* Init: sugerimos último 7 días */
(function preset(){
  const today = new Date();
  const end = today.toISOString().slice(0,10);
  const start = new Date(today.getTime()-6*86400000).toISOString().slice(0,10);
  document.getElementById('f_start').value = start;
  document.getElementById('f_end').value = end;
})();
</script>
</body>
</html>
