<?php
/**
 * /Checador_Scap/empleados/crear.php
 * Gestión de empleados + botón “Agregar huella” (modal kiosko) + upload de foto.
 */
session_start();
$ROOT = dirname(__DIR__);
require_once $ROOT . '/auth/require_login.php';
require_once $ROOT . '/auth/require_role.php';
require_role(['admin']);
require_once $ROOT . '/conection/conexion.php';

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];
$NAVBAR = $ROOT . '/components/navbar.php';
$DEFAULT_AVATAR = dirname($_SERVER['SCRIPT_NAME'], 2) . '/assets/profiles-img/perfil_default.jpg';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Empleados | Administración</title>
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

    /* fondo */
    --bg-image:url('/Checador_Scap/assets/img/logo_isstech.png');
    --bg-size:clamp(420px, 52vw, 420px);
  }

  /* Fondo con logo SIEMPRE visible */
  body{
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
  h1{font-size:clamp(1.25rem, .9rem + 1.5vw, 1.6rem); margin-bottom:.75rem}

  /* Tarjeta/controles */
  .card-soft{border-radius:14px; background:var(--card); box-shadow:var(--shadow)}
  .badge.text-bg-light{background:#eef2ff !important; color:#1e293b !important; border:1px solid #e2e8f0}

  /* Inputs coherentes (2px) */
  .form-control,.form-select{
    border:2px solid #cbd5e1; border-radius:10px; background:#fff;
    transition:border-color .18s, box-shadow .18s;
  }
  .form-control:focus,.form-select:focus{
    border-color:var(--primary); box-shadow:0 0 0 .2rem rgba(37,99,235,.15); outline:none;
  }

  /* Botones */
  .btn-primary{background:var(--primary); border-color:var(--primary)}
  .btn-primary:hover{background:var(--primary-700); border-color:var(--primary-700)}
  .btn-ghost{background:#fff;color:var(--text);border:1px solid #cbd5e1}
  .btn-ghost:hover{background:#f1f5f9}

  /* Tabla */
  .table-responsive{border-radius:14px; box-shadow:var(--shadow)}
  .table{--bs-table-bg:#fff}
  .table thead th{
    position:sticky; top:0; z-index:2;
    background:#f8fafc; border-bottom:1px solid var(--bd);
  }
  .table tbody tr:hover{background:#f8fafc}
  .table td,.table th{vertical-align:middle; padding:.75rem .85rem}
  /* Evita recortes visuales en columnas cortas y mejora lectura */
  .table td:nth-child(1),
  .table td:nth-child(4),
  .table td:nth-child(5),
  .table td:nth-child(6){white-space:nowrap}

  /* Avatares */
  .avatar-preview{width:56px;height:56px;border-radius:999px;object-fit:cover;border:3px solid #e5e7eb;background:#f8fafc}
  .tbl-avatar{width:40px;height:40px;border-radius:999px;object-fit:cover;border:2px solid #e5e7eb;background:#f8fafc}

  /* Acciones de fila */
  .row-actions{display:flex;gap:.5rem;flex-wrap:wrap}
  .row-actions .btn{border-radius:10px}

  /* Separadores */
  .hr{height:1px;background:#e5e7eb;margin:14px 0}

  /* Modal tipo “sheet” */
  .sheet-back{
    position:fixed; inset:0; display:none; z-index:1065;
    background:rgba(2,6,23,.45); backdrop-filter:blur(3px);
    align-items:center; justify-content:center; padding:20px;
  }
  .sheet{
    width:min(940px,96vw);
    max-height:calc(100dvh - 40px);
    margin:auto; background:#fff; border-radius:16px;
    display:flex; flex-direction:column;
    border:1px solid var(--bd); box-shadow:var(--shadow-lg);
    animation:pop .18s ease-out;
  }
  .sheet-h{
    padding:14px 16px; border-bottom:1px solid var(--bd);
    display:flex; justify-content:space-between; align-items:center;
  }
  .sheet-c{padding:16px; overflow:auto}
  .sheet .x{position:absolute; right:14px; top:8px; font-size:24px; cursor:pointer}

  @keyframes pop { from{ transform:translateY(6px); opacity:0 } to{ transform:translateY(0); opacity:1 } }

  /* Grid/ panel del horario */
  .day-row{
    display:grid; grid-template-columns:110px 1fr 1fr 140px 90px;
    gap:8px; align-items:center; margin-bottom:8px;
  }
  .pill{display:inline-block; padding:4px 10px; border-radius:999px; border:1px solid var(--bd); background:#f8fafc; color:#334155}

  /* Fingerprint (huella)) modal */
  .fp-ico{width:180px;height:180px;margin:10px auto 6px;display:block}
  .fp-modal-msg{min-height:22px;font-weight:600}
  .fp-modal-msg.error{color:#dc2626}
  .fp-modal-msg.ok{color:#16a34a}

  /* Toasts por encima del sheet */
  .toast-container{z-index:1086}
</style>
</head>
<body>
<?php if (file_exists($NAVBAR)) include $NAVBAR; ?>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastArea" style="z-index:1086"></div>

<div class="container my-3">
  <a href="/Checador_Scap/vistas-roles/vista-admin.php"
     class="btn btn-primary"
     style="border-radius:10px">
    ← Regresar
  </a>
</div>


<div class="container my-4">
    <div class="card card-soft-p-3 mb-3">
  <h1>Gestión de empleados</h1>

  <div class="d-flex gap-2 flex-wrap align-items-center mb-3">
    <button class="btn btn-primary" id="btnAdd">Agregar empleado</button>
    <div class="d-flex gap-2 flex-grow-1" style="min-width:260px;">
        <input class="form-control" style="background: #f6f8fb" id="q" placeholder="Buscar por nombre o enlace…">
      <button class="btn btn-ghost" id="btnSearch">Buscar</button>
      <button class="btn btn-ghost" id="btnClear">Limpiar</button>
    </div>
    <span class="badge text-bg-light" id="totalTag">Total: 0</span>
  </div>
 </div>

  <div class="table-responsive border rounded bg-white">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:120px">Enlace</th>
          <th>Nombres</th>
          <th>Apellidos</th>
          <th>Cargo</th>
          <th>Turno</th>
          <th>Foto</th>
          <th>Adscrito</th>
          <th style="width:240px">Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="6" class="text-muted text-center py-3">Cargando…</td></tr>
      </tbody>
    </table>
  </div>

  <div class="d-flex gap-2 justify-content-center mt-2 flex-wrap" id="pager"></div>
</div>

<!-- ====== PANEL-MODAL (CRUD) ====== -->
<div class="sheet-back" id="modalBack" aria-hidden="true">
  <div class="sheet position-relative" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="sheet-h">
      <strong id="modalTitle">Nuevo empleado</strong>
      <button class="btn btn-sm btn-outline-secondary" id="btnClose">X</button>
    </div>
    <div class="sheet-c">
      <form id="frmEmp" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="id_empleado" id="f_id">

        <div class="row g-3">
          <div class="col-md-4">
            <label for="f_codigo" class="form-label">Enlace</label>
            <input class="form-control" id="f_codigo" name="codigo_empleado" maxlength="20" required>
          </div>
          <div class="col-md-4">
            <label for="f_nombre" class="form-label">Nombres</label>
            <input class="form-control" id="f_nombre" name="nombre" maxlength="50" required>
          </div>
          <div class="col-md-4">
            <label for="f_apellido" class="form-label">Apellidos</label>
            <input class="form-control" id="f_apellido" name="apellido" maxlength="50" required>
          </div>
          <div class="col-md-4">
            <label for="f_cargo" class="form-label">Cargo</label>
            <input class="form-control" id="f_cargo" name="cargo" maxlength="50" required>
          </div>
          <div class="col-md-4">
            <label for="f_turno" class="form-label">Turno</label>
            <input class="form-control" id="f_turno" name="turno" maxlength="50" required>
          </div>

          <!-- FOTO: botón para abrir explorador + preview + hidden path -->
          <div class="col-md-4">
            <label class="form-label d-block">Foto perfil</label>
            <div class="d-flex align-items-center gap-3">
              <img id="fotoPreview" class="avatar-preview" src="<?= htmlspecialchars($DEFAULT_AVATAR) ?>" alt="Foto">
              <button type="button" class="btn btn-outline-primary" id="btnUploadFoto">Cargar foto de perfil</button>
            </div>
            <input type="file" id="fileFoto" accept="image/*" hidden>
            <input type="hidden" name="foto" id="f_foto_path" value="<?= htmlspecialchars($DEFAULT_AVATAR) ?>">
          </div>

          <!-- Adscrito + botón de huella -->
          <div class="col-md-8">
            <label for="f_unidad_medica" class="form-label">Adscrito</label>
            <input class="form-control" id="f_unidad_medica" name="unidad_medica" maxlength="50" required>
          </div>
          <div class="col-md-4">
            <label class="form-label d-block">Huella dactilar</label>
            <div class="d-flex align-items-center gap-2">
              <button type="button" class="btn btn-primary" id="btnOpenFP">Agregar huella</button>
              <span id="fpStatus" class="text-muted small">Sin huella capturada.</span>
            </div>
            <input type="hidden" name="firma" id="f_firma">
          </div>
        </div>

        <div class="hr"></div>
        <div class="mb-2 d-flex align-items-center gap-2">
          <strong>Horario laboral</strong>
          <span class="text-muted small">“Activo” indica que ese día trabaja. “Tol.” = tolerancia en minutos.</span>
        </div>
        <div id="days"></div>

        <div class="hr"></div>
        <div class="d-flex gap-2 justify-content-end mt-3">
          <button type="button" class="btn btn-outline-secondary" id="btnEditToggle" hidden>Editar</button>
          <button type="button" class="btn btn-outline-danger" id="btnDelete" hidden>Eliminar</button>
          <button type="submit" class="btn btn-primary" id="btnSave">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ====== MODAL DE HUELLA (tipo kiosko) ====== -->
<div class="sheet-back" id="fpBack" aria-hidden="true">
  <div class="sheet position-relative" style="width:min(740px,95vw)">
    <div class="x" id="fpClose" aria-label="Cerrar">×</div>
    <div class="h3 fw-bold text-center">REGISTRAR HUELLA</div>
    <div class="h5 text-muted mb-2 text-center">Por favor, escanea tu huella</div>

    <svg class="fp-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M7.5 8.5C8 7 9.7 6 12 6c3 0 4.5 2 4.5 4.5 0 1.8-.6 3.6-1.4 5.1" stroke="#2563eb" stroke-width="1.6" stroke-linecap="round"/>
      <path d="M9 12c0-1.7 1-3 3-3s3 1.3 3 3c0 2.7-1 5.5-2.8 7.5" stroke="#2563eb" stroke-width="1.6" stroke-linecap="round"/>
      <path d="M5.5 14c.7-3.5 3.2-6 6.5-6 3.9 0 6.5 3 6.5 7.5 0 2.3-.7 4.3-1.8 6" stroke="#2563eb" stroke-width="1.6" stroke-linecap="round"/>
    </svg>

    <div id="fpModalMsg" class="fp-modal-msg text-muted text-center">Listo para capturar</div>

    <div class="d-flex gap-2 justify-content-center mt-3 mb-2">
      <button type="button" class="btn btn-primary" id="btnModalEnroll">Capturar</button>
      <button type="button" class="btn btn-outline-secondary" id="btnModalVerify">Probar</button>
      <button type="button" class="btn btn-outline-danger" id="btnModalClear">Borrar</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API  = '<?= rtrim(dirname($_SERVER["REQUEST_URI"]), "/") ?>/api.php';
const CSRF = '<?= $csrf ?>';
const DEFAULT_AVATAR = '<?= $DEFAULT_AVATAR ?>';

const $tbody = document.getElementById('tbody');
const $pager = document.getElementById('pager');
const $q     = document.getElementById('q');
const $btnSearch = document.getElementById('btnSearch');
const $btnClear  = document.getElementById('btnClear');
const $totalTag  = document.getElementById('totalTag');

const $modalBack = document.getElementById('modalBack');
const $btnAdd    = document.getElementById('btnAdd');
const $btnClose  = document.getElementById('btnClose');
const $frm       = document.getElementById('frmEmp');
const $title     = document.getElementById('modalTitle');
const $btnDelete = document.getElementById('btnDelete');
const $btnEditToggle = document.getElementById('btnEditToggle');
const $daysWrap  = document.getElementById('days');

let state = { page:1, limit:5, q:'', total:0, pages:1 };
let viewMode = 'create';

function showToast(message, variant='success', title='Listo') {
  const area = document.getElementById('toastArea');
  const el = document.createElement('div');
  const bg = variant==='success' ? 'text-bg-success' :
             variant==='error'   ? 'text-bg-danger'  :
             variant==='info'    ? 'text-bg-primary' : 'text-bg-secondary';
  el.className = `toast align-items-center ${bg} border-0`;
  el.setAttribute('role','alert'); el.setAttribute('aria-live','assertive'); el.setAttribute('aria-atomic','true');
  el.innerHTML = `<div class="d-flex"><div class="toast-body"><strong>${title}:</strong> ${message}</div>
    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
  area.appendChild(el);
  new bootstrap.Toast(el, { delay: 2800 }).show();
}
const showToastSuccess = (m)=>showToast(m,'success','Éxito');
const showToastError   = (m)=>showToast(m,'error','Error');

const DAYS = [
  {n:1, label:'Lunes'}, {n:2, label:'Martes'}, {n:3, label:'Miércoles'},
  {n:4, label:'Jueves'}, {n:5, label:'Viernes'}, {n:6, label:'Sábado'},
  {n:7, label:'Domingo'},
];
function dayRow(d){
  const id = d.n;
  return `
  <div class="day-row" data-day="${id}">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="chk_${id}" ${d.activo ? 'checked' : ''}>
      <label class="form-check-label" for="chk_${id}">${d.label}</label>
    </div>
    <input class="form-control" type="time" id="in_${id}"  value="${d.entrada || ''}">
    <input class="form-control" type="time" id="out_${id}" value="${d.salida  || ''}">
    <input class="form-control" type="number" min="0" max="120" id="tol_${id}" value="${d.tolerancia_min ?? 10}" placeholder="Tol. (min)">
    <span class="pill">${d.label.substring(0,3).toUpperCase()}</span>
  </div>`;
}
function renderDays(schedule){
  const map = {};
  (schedule||[]).forEach(s => map[+s.dia_semana] = s);
  $daysWrap.innerHTML = DAYS.map(d => dayRow({
    ...d,
    activo: map[d.n]?.activo ?? true,
    entrada: (map[d.n]?.entrada||'').substring(0,5),
    salida:  (map[d.n]?.salida ||'').substring(0,5),
    tolerancia_min: map[d.n]?.tolerancia_min ?? 10
  })).join('');
}
function gatherForm(){
  const fd = new FormData($frm);
  const foto = (fd.get('foto') || '').toString().trim() || DEFAULT_AVATAR;
  const payload = {
    csrf: CSRF,
    id_empleado: ($frm.querySelector('#f_id').value || null),
    codigo_empleado: (fd.get('codigo_empleado')||'').toString().trim(),
    nombre: (fd.get('nombre')||'').toString().trim(),
    apellido: (fd.get('apellido')||'').toString().trim(),
    turno: (fd.get('turno')||'').toString().trim(),
    cargo: (fd.get('cargo')||'').toString().trim(),
    horario: [],
    foto,
    unidad_medica: (fd.get('unidad_medica')||'').toString().trim(),
    firma: (document.getElementById('f_firma').value || null)
  };
  DAYS.forEach(d=>{
    const row = $daysWrap.querySelector(`.day-row[data-day="${d.n}"]`);
    payload.horario.push({
      dia_semana: d.n,
      activo: row.querySelector(`#chk_${d.n}`).checked ? 1 : 0,
      entrada: row.querySelector(`#in_${d.n}`).value || null,
      salida:  row.querySelector(`#out_${d.n}`).value || null,
      tolerancia_min: parseInt(row.querySelector(`#tol_${d.n}`).value||'10',10)
    });
  });
  return payload;
}

function setReadOnly(ro){
  $frm.querySelectorAll('input,select,button').forEach(el=>{
    if (el === $btnClose || el === $btnEditToggle) return;
    if (el.type === 'submit') { el.hidden = ro; return; }
    el.disabled = ro;
  });
}
function openModal(mode, data){
  viewMode = mode;
  $frm.reset(); renderDays([]);
  $btnDelete.hidden = true; $btnEditToggle.hidden = true;

  const setFpStatus = (txt)=> document.getElementById('fpStatus').textContent = txt;
  const setFoto = (url)=>{ document.getElementById('f_foto_path').value = url || DEFAULT_AVATAR; document.getElementById('fotoPreview').src = url || DEFAULT_AVATAR; };

  if (mode === 'create'){
    $title.textContent = 'Nuevo empleado';
    $frm.f_codigo.readOnly = false;
    setFoto(DEFAULT_AVATAR);
    document.getElementById('f_firma').value = "";
    setFpStatus('Sin huella capturada.');
    setReadOnly(false);

  } else if (mode === 'edit'){
    $title.textContent = `Editar empleado: ${data.nombre} ${data.apellido}`;
    $frm.f_id.value = data.id_empleado;
    $frm.f_codigo.value = data.codigo_empleado || '';
    $frm.f_nombre.value = data.nombre || '';
    $frm.f_apellido.value = data.apellido || '';
    $frm.f_cargo.value = data.cargo || '';
    $frm.f_turno.value = data.turno || '';
    setFoto(data.foto || DEFAULT_AVATAR);
    $frm.f_unidad_medica.value = data.unidad_medica || '';
    $frm.f_codigo.readOnly = true;
    renderDays(data.horario||[]);
    document.getElementById('f_firma').value = (data.firma||"");
    setFpStatus(data.firma ? 'Huella cargada ✔' : 'Sin huella capturada.');
    setReadOnly(false);
    $btnDelete.hidden = true;

  } else {
    $title.textContent = `Horario de ${data.nombre} ${data.apellido}`;
    $frm.f_id.value = data.id_empleado;
    $frm.f_codigo.value = data.codigo_empleado || '';
    $frm.f_nombre.value = data.nombre || '';
    $frm.f_apellido.value = data.apellido || '';
    $frm.f_cargo.value = data.cargo || '';
    $frm.f_turno.value = data.turno || '';
    setFoto(data.foto || DEFAULT_AVATAR);
    $frm.f_unidad_medica.value = data.unidad_medica || '';
    renderDays(data.horario||[]);
    document.getElementById('f_firma').value = (data.firma||"");
    setFpStatus(data.firma ? 'Huella cargada ✔' : 'Sin huella capturada.');
    setReadOnly(true);
    $btnEditToggle.hidden = true;
  }

  $modalBack.style.display = 'flex';
  $modalBack.setAttribute('aria-hidden','false');
}
function closeModal(){
  $modalBack.style.display = 'none';
  $modalBack.setAttribute('aria-hidden','true');
}

/* ---------- Listado / Paginación ---------- */
async function load(page=1){
  state.page = page;
  const params = new URLSearchParams({action:'list', page:state.page, limit:state.limit, q:state.q});
  try{
    const r = await fetch(`${API}?`+params, {headers:{'X-CSRF':CSRF}});
    const j = await r.json();
    if(!j.ok){ $tbody.innerHTML = `<tr><td colspan="6">${j.msg||'Error'}</td></tr>`; showToastError(j.msg||'Error al cargar'); return; }
    state.total = j.total; state.pages = j.pages;
    $totalTag.textContent = `Total: ${j.total}`;
    if(j.rows.length===0){
      $tbody.innerHTML = `<tr><td colspan="6" class="text-muted text-center py-3">Sin resultados</td></tr>`;
    }else{
      $tbody.innerHTML = j.rows.map(r => `
        <tr>
          <td><code>${r.codigo_empleado||r.id_empleado}</code></td>
          <td>${r.nombre||''}</td>
          <td>${r.apellido||''}</td>
          <td>${r.cargo||''}</td>
          <td>${r.turno||''}</td>
          <td><img src="${r.foto || DEFAULT_AVATAR}" class="tbl-avatar" onerror="this.src='${DEFAULT_AVATAR}'" alt=""></td>
          <td>${r.unidad_medica||''}</td>
          <td>
            <div class="row-actions">
              <button class="btn btn-sm btn-outline-secondary" data-act="view" data-id="${r.id_empleado}">Ver horario</button>
              <button class="btn btn-sm btn-primary" data-act="edit" data-id="${r.id_empleado}">Editar</button>
              <button class="btn btn-sm btn-danger" data-act="del"  data-id="${r.id_empleado}">Eliminar</button>
            </div>
          </td>
        </tr>`).join('');
    }
    renderPager();
  }catch(_){
    $tbody.innerHTML = `<tr><td colspan="7" class="text-danger">Error de red</td></tr>`;
    showToastError('No se pudo cargar el listado');
  }
}
function renderPager(){
  const p = state.page, P = state.pages;
  const btn = (i, label=i, current=false) => `<button class="btn btn-sm ${current?'btn-primary':'btn-outline-secondary'}" data-page="${i}">${label}</button>`;
  const parts = [];
  if(P<=1){ $pager.innerHTML=''; return; }
  const push = i => parts.push(btn(i, i, i===p));
  push(1);
  if(p>3) parts.push('<span class="mx-1">…</span>');
  for(let i=Math.max(2,p-1); i<=Math.min(P-1,p+1); i++) push(i);
  if(p<P-2) parts.push('<span class="mx-1">…</span>');
  if(P>1) push(P);
  $pager.innerHTML = parts.join('');
}

$pager.addEventListener('click',e=>{
  const b = e.target.closest('button[data-page]'); if(!b) return;
  load(+b.dataset.page);
});
document.addEventListener('click', async e=>{
  const b = e.target.closest('button[data-act]'); if(!b) return;
  const id = +b.dataset.id, act = b.dataset.act;

  if(act==='view' || act==='edit'){
    try{
      const r = await fetch(`${API}?action=get&id=${id}`, {headers:{'X-CSRF':CSRF}});
      const j = await r.json();
      if(!j.ok){ showToastError(j.msg||'No se pudo obtener'); return; }
      openModal(act==='view'?'view':'edit', j.data);
    }catch(_){ showToastError('Error de red'); }
  }
  if(act==='del'){
    if (!confirm('¿Eliminar empleado y su horario?')) return;
    try{
      const r = await fetch(API+'?action=delete', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF},
        body: JSON.stringify({csrf:CSRF, id})
      });
      const j = await r.json();
      if(!j.ok){ showToastError(j.msg||'No se pudo eliminar'); return; }
      showToastSuccess('Empleado eliminado'); load(state.page);
    }catch(_){ showToastError('Error de red'); }
  }
});
document.getElementById('btnAdd').addEventListener('click', ()=> openModal('create', null));
document.getElementById('btnClose').addEventListener('click', closeModal);
document.getElementById('btnSearch').addEventListener('click', ()=>{ state.q=$q.value.trim(); load(1); });
document.getElementById('btnClear').addEventListener('click', ()=>{ $q.value=''; state.q=''; load(1); });
document.getElementById('btnEditToggle').addEventListener('click', ()=>{ setReadOnly(false); document.getElementById('btnEditToggle').hidden = true; });

document.getElementById('btnDelete').addEventListener('click', async ()=>{
  const id = +($frm.f_id.value||0);
  if(!id) return;
  if (!confirm('¿Eliminar empleado y su horario?')) return;
  try{
    const r = await fetch(API+'?action=delete', {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF},
      body: JSON.stringify({csrf:CSRF, id})
    });
    const j = await r.json();
    if(!j.ok){ showToastError(j.msg||'No se pudo eliminar'); return; }
    closeModal(); showToastSuccess('Empleado eliminado'); load(state.page);
  }catch(_){ showToastError('Error de red'); }
});

$frm.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const payload = gatherForm();
  const isEdit = !!payload.id_empleado;
  const action = isEdit ? 'update' : 'create';
  try{
    const r = await fetch(API+`?action=${action}`, {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF},
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    if(!j.ok){ showToastError(j.msg||'No se pudo guardar'); return; }
    closeModal();
    showToastSuccess(isEdit ? 'Empleado actualizado' : 'Empleado creado');
    load(isEdit ? state.page : 1);
  }catch(_){ showToastError('Error de red'); }
});

/* ===== Servicio de huella local (Java) ===== */
const FP_PROXY = '<?= rtrim(dirname($_SERVER["REQUEST_URI"]), "/") ?>/../fingerprint/proxy.php?p=';
async function fpFetch(path, opts={}) {
  const isHttps = location.protocol === 'https:';
  const url = isHttps ? (FP_PROXY + path) : ('http://127.0.0.1:8787/' + path);
  return fetch(url, opts);
}
async function fpOpen(){ try{ await fpFetch("api/device/open"); }catch{} }

/* ===== Modal de huella dactilar ===== */
const $fpBack  = document.getElementById('fpBack');
const $fpClose = document.getElementById('fpClose');
const $fpModalMsg = document.getElementById('fpModalMsg');
const $fpStatus = document.getElementById('fpStatus');

function setFpModalMsg(txt, mode='info'){
  $fpModalMsg.classList.remove('ok','error','text-muted');
  if(mode==='ok') $fpModalMsg.classList.add('ok');
  else if(mode==='error') $fpModalMsg.classList.add('error');
  else $fpModalMsg.classList.add('text-muted');
  $fpModalMsg.textContent = txt;
}
function updateSmallStatus(){
  const has = (document.getElementById('f_firma').value || '').trim() !== '';
  $fpStatus.textContent = has ? 'Huella cargada ✔' : 'Sin huella capturada.';
}

function openFpModal(){
  setFpModalMsg('Listo para capturar','info');
  $fpBack.style.display = 'flex';
  $fpBack.setAttribute('aria-hidden','false');
  fpOpen();
}
function closeFpModal(){
  $fpBack.style.display = 'none';
  $fpBack.setAttribute('aria-hidden','true');
  updateSmallStatus();
}

document.getElementById('btnOpenFP').addEventListener('click', openFpModal);
$fpClose.addEventListener('click', closeFpModal);
document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeFpModal(); });

/* Capturar huella (enroll) */
document.getElementById('btnModalEnroll').addEventListener('click', async ()=>{
  try{
    await fpOpen();
    setFpModalMsg('coloca tu huella en el lector 3 veces…','info');
    let template = null;
    for(let intento=1; intento<=3; intento++){
      const r = await fpFetch("api/enroll", { method:"POST" });
      const j = await r.json();
      if (j.ok && j.template){ template = j.template; break; }
      if (j.ok && typeof j.samples_remaining === 'number' && j.samples_remaining > 0){
        if (intento === 1) setFpModalMsg('Escanea otra vez…','info');
        else setFpModalMsg('Escanea una última vez…','info');
        continue;
      }
      if (!j.ok){ setFpModalMsg(j.error || 'No se pudo capturar. Intenta de nuevo.','error'); return; }
    }
    if (!template){ setFpModalMsg('No se obtuvo la plantilla. Intenta nuevamente.','error'); return; }
    document.getElementById('f_firma').value = template;
    setFpModalMsg('Huella registrada con éxito','ok');
    showToastSuccess('Huella capturada');
    updateSmallStatus();
  }catch(e){
    setFpModalMsg('Error con el lector. Revisa la conexión.','error');
    showToastError(e.message || 'No se pudo capturar la huella');
  }
});

/* Probar */
document.getElementById('btnModalVerify').addEventListener('click', async ()=>{
  const tpl = (document.getElementById('f_firma').value || '').trim();
  if (!tpl){ setFpModalMsg('No hay huella guardada','error'); return; }
  try{
    await fpOpen();
    const r = await fpFetch("api/verify", {
      method:"POST", headers:{ "Content-Type":"application/json" },
      body: JSON.stringify({ template: tpl })
    });
    const j = await r.json();
    if (!j.ok){ setFpModalMsg(j.error || 'Error en verificación','error'); return; }
    if (j.match){ setFpModalMsg(`Coincidencia${j.score ? ' (score '+j.score+')' : ''}`,'ok'); showToastSuccess('Coincide'); }
    else{ setFpModalMsg('No coincide, intenta de nuevo','error'); showToastError('No coincide'); }
  }catch(e){
    setFpModalMsg('No se pudo verificar','error');
    showToastError(e.message || 'No se pudo verificar');
  }
});

/* Borrar huella */
document.getElementById('btnModalClear').addEventListener('click', async ()=>{
  const id = +($frm.querySelector('#f_id').value || 0);
  document.getElementById('f_firma').value = "";
  updateSmallStatus();
  if (id){
    try{
      const r = await fetch(API+'?action=clear_firma', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF':CSRF},
        body: JSON.stringify({ csrf: CSRF, id })
      });
      const j = await r.json();
      if(!j.ok){ setFpModalMsg(j.msg || 'No se pudo borrar en BD','error'); return; }
      setFpModalMsg('Huella eliminada en BD. Puedes capturar una nueva.','ok');
      showToastSuccess('Huella eliminada en BD');
    }catch(_){ setFpModalMsg('Error de red al borrar en BD','error'); }
  }else{
    setFpModalMsg('Huella borrada (recuerda Guardar).','info');
    showToast('Huella borrada en el formulario.', 'info', 'Aviso');
  }
});

/* ====== Upload (carga de foto de perfil) ====== */
const $btnUploadFoto = document.getElementById('btnUploadFoto');
const $fileFoto = document.getElementById('fileFoto');
const $fotoPreview = document.getElementById('fotoPreview');
$btnUploadFoto.addEventListener('click', ()=> $fileFoto.click());
$fileFoto.addEventListener('change', async ()=>{
  const file = $fileFoto.files[0];
  if(!file) return;
  if(file.size > 5*1024*1024){ showToastError('La imagen debe ser ≤ 5 MB'); $fileFoto.value=''; return; }
  const fd = new FormData();
  fd.append('foto', file);
  // Cabecera CSRF va en header (la API la exige)
  try{
    const r = await fetch(API+'?action=upload_foto', { method:'POST', headers:{'X-CSRF':CSRF}, body: fd });
    const j = await r.json();
    if(!j.ok){ showToastError(j.msg||'No se pudo subir la foto'); return; }
    document.getElementById('f_foto_path').value = j.url;
    $fotoPreview.src = j.url;
    showToastSuccess('Foto cargada');
  }catch(_){ showToastError('Error de red al subir la foto'); }
});

/* Init */
function renderPager(){ const p=state.page,P=state.pages;const btn=(i,label=i,current=false)=>`<button class="btn btn-sm ${current?'btn-primary':'btn-outline-secondary'}" data-page="${i}">${label}</button>`;const parts=[];if(P<=1){$pager.innerHTML='';return;}const push=i=>parts.push(btn(i,i,i===p));push(1);if(p>3)parts.push('<span class="mx-1">…</span>');for(let i=Math.max(2,p-1);i<=Math.min(P-1,p+1);i++)push(i);if(p<P-2)parts.push('<span class="mx-1">…</span>');if(P>1)push(P);$pager.innerHTML=parts.join('');}
renderDays([]); load(1);
</script>
</body>
</html>
