<?php
/**
 * Checador_Scap/empleados/crear.php
 * Vista unificada: listado de empleados + búsqueda + paginación + modal de alta/edición
 * Requiere: auth, rol admin y DB::conn() a PostgreSQL
 */
session_start();
$ROOT = dirname(__DIR__);
require_once $ROOT . '/auth/require_login.php';
require_once $ROOT . '/auth/require_role.php';
require_role(['admin']);
require_once $ROOT . '/conection/conexion.php';

// CSRF simple para el API
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// (Opcional) navbar del proyecto
$NAVBAR = $ROOT . '/components/navbar.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Empleados | Administración</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* ============================
       TEMA CLARO E IMAGEN DE FONDO
       ============================ */
    :root{
      /* Paleta clara neutra */
      --bg:#f6f8fb;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --bd:#e5e7eb;
      
      --bg-size: clamp(520px, 52vw, 720px);

      --input:#ffffff;
      --chip:#f1f5f9;
      --chipbd:#e2e8f0;

      --primary:#2563eb;
      --primary-700:#1d4ed8;
      --danger:#dc2626;
      --success:#16a34a;
      --warning:#f59e0b;

      --bg-image: url('/Checador_Scap/assets/img/logo_login_scap.jpg');

      /* Sombras */
      --shadow-xs: 0 1px 0 rgba(2,6,23,.02), 0 1px 6px rgba(2,6,23,.03);
      --shadow-sm: 0 2px 10px rgba(2,6,23,.06);
      --shadow-md: 0 6px 16px rgba(2,6,23,.10);
      --shadow-lg: 0 20px 60px rgba(2,6,23,.18);
    }

    *{box-sizing:border-box}
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

    .container{max-width:1100px;margin:24px auto;padding:0 16px}
    h1{margin:6px 0 18px;font-size:28px}

    .actions-bar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px}

    /* ===== Botones (resaltados) ===== */
    .btn{
      border:2px solid var(--bd);
      border-radius:12px;
      padding:10px 14px;
      font-weight:700;
      cursor:pointer;
      box-shadow: var(--shadow-xs);
      transition: background .18s ease, border-color .18s ease, box-shadow .18s ease, transform .02s ease;
    }
    .btn:active{ transform: translateY(1px); }

    .btn-primary{
      background:var(--primary);
      color:#fff;
      border-color:var(--primary-700);
      box-shadow: 0 6px 14px rgba(37,99,235,.18);
    }
    .btn-primary:hover{
      background:var(--primary-700);
      box-shadow: 0 10px 20px rgba(37,99,235,.22);
    }
    .btn-primary:focus-visible{
      outline:3px solid rgba(37,99,235,.28);
      outline-offset:2px;
    }

    .btn-light{
      background:#f8fafc;
      color:#0f172a;
      border-color:#cbd5e1; /* slate-300 */
      box-shadow: var(--shadow-sm);
    }
    .btn-light:hover{
      border-color:var(--primary);
      box-shadow: var(--shadow-md);
    }

    .btn-danger{
      background:var(--danger);
      color:#fff;
      border-color:#b91c1c;
      box-shadow: 0 6px 14px rgba(220,38,38,.18);
    }
    .btn-danger:hover{
      background:#b91c1c;
      box-shadow: 0 10px 20px rgba(220,38,38,.22);
    }

    .btn-ghost{
      background:#ffffff;
      color:var(--text);
      border-color:#cbd5e1;
      box-shadow: var(--shadow-xs);
    }
    .btn-ghost:hover{
      border-color:var(--primary);
      box-shadow: var(--shadow-sm);
    }

    /* ===== Inputs (resaltados) ===== */
    .input{
      background:var(--input);
      border:2px solid #cbd5e1; /* slate-300 */
      color:var(--text);
      border-radius:12px;
      padding:10px 12px;
      outline:none;
      box-shadow: var(--shadow-xs);
      transition: border-color .18s ease, box-shadow .18s ease;
    }
    .input:hover{ border-color:#94a3b8; /* slate-400 */ }
    .input:focus{
      border-color:var(--primary);
      box-shadow: 0 0 0 3px rgba(37,99,235,.16), var(--shadow-sm);
    }
    .input::placeholder{color:var(--muted)}
    .input:disabled{ background:#f1f5f9; color:#94a3b8; }
    
    /* Cualquier input en readonly */
    .input[readonly]{
        background:#ABABAB;
        color:#000;
    }

    /* SOLO Enlace (código) en modo readonly, más marcado */
    #f_codigo[readonly]{
        background:#ABABAB;           
        border-color:#000;         
        color:#000;
        box-shadow:0 0 0 3px rgba(245,158,11,.18);
    }

    /* ===== Tabla ===== */
    .table-wrap{
      overflow:auto;
      border:2px solid #cbd5e1;
      border-radius:16px;
      background:var(--card);
      box-shadow: var(--shadow-sm);
    }
    table{width:100%;border-collapse:collapse;min-width:760px}
    th,td{
      padding:12px;
      border-bottom:1.5px solid #cbd5e1;
      text-align:left;vertical-align:middle
    }
    thead th{
      background:#f8fafc;position:sticky;top:0;z-index:1;color:#0f172a;
      border-bottom:2px solid #94a3b8;
    }
    tbody tr:hover{ background:#f1f5f9; }

    .tag{
      display:inline-flex;gap:6px;align-items:center;
      background:var(--chip);border:1px solid var(--chipbd);color:var(--muted);
      font-size:12px;padding:3px 8px;border-radius:999px
    }
    .row-actions{display:flex;gap:8px;flex-wrap:wrap}
    .pill{
      display:inline-block;padding:4px 10px;border-radius:999px;
      font-size:12px;border:2px solid #cbd5e1;background:#fff;color:#0f172a
    }

    /* ===== Paginación (resaltada) ===== */
    .pager{display:flex;gap:8px;align-items:center;justify-content:center;margin-top:14px;flex-wrap:wrap}
    .page-btn{
      border:2px solid #cbd5e1;
      background:#ffffff;
      color:#0f172a;
      padding:8px 12px;border-radius:12px;cursor:pointer;
      box-shadow: var(--shadow-xs);
      transition: border-color .18s, box-shadow .18s, transform .02s;
    }
    .page-btn:hover{ border-color:var(--primary); box-shadow: var(--shadow-sm); }
    .page-btn:active{ transform: translateY(1px); }
    .page-btn[aria-current="true"]{
      background:var(--primary);color:#fff;border-color:transparent;
      box-shadow: 0 6px 14px rgba(37,99,235,.18);
    }
    .ellipsis{color:var(--muted);padding:0 4px}

    /* ===== Panel modal propio ===== */
    /* Fondo del modal */
.sheet-back{
  position:fixed; inset:0; z-index:1065;
  background:rgba(15,23,42,.45);
  display:none; align-items:center; justify-content:center;
  padding:16px;
}

/* Contenedor del modal */
.sheet{
  width:min(920px, 96vw);
  max-height:calc(100dvh - 40px);   /* <= clave: no rebase la pantalla */
  display:flex;                     /* header arriba, contenido scroll adentro */
  flex-direction:column;
  background:#fff;
  border:2px solid #94a3b8;
  border-radius:16px;
  overflow:hidden;                  /* el scroll va en .sheet-c */
  box-shadow:0 20px 60px rgba(2,6,23,.20);
}

/* Header pegajoso para que no se pierda al hacer scroll */
.sheet-h{
  position:sticky; top:0; z-index:1;
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 16px;
  background:#ffffffe6;             /* ligero blur si quieres */
  backdrop-filter:saturate(1.1) blur(2px);
  border-bottom:2px solid #94a3b8;
}

/* Cuerpo con scroll */
.sheet-c{
  flex:1 1 auto;
  overflow:auto;                    /* <= aquí vive el scroll */
  padding:16px;
}

/* Footer "pegajoso" dentro del contenido, para que el botón Guardar no se corte */
.sheet-c .right{
  position:sticky; bottom:-1px;
  background:linear-gradient(180deg, transparent, #fff 40%);
  padding-top:8px; margin-top:12px;
  border-top:1px solid #e5e7eb;
}

/* Responsive */
@media (max-width: 720px){
  .sheet{ width:100vw; max-height:100dvh; border-radius:0; }
}


    .grid{display:grid;gap:12px}
    @media (min-width:760px){ .grid-2{grid-template-columns:1fr 1fr} .grid-3{grid-template-columns:1fr 1fr 1fr} }
    label{font-weight:600;margin-bottom:6px;display:block}
    .help{color:var(--muted);font-size:12px}

    .day-row{display:grid;grid-template-columns:110px 1fr 1fr 140px 90px;gap:8px;align-items:center;margin-bottom:8px}
    .day-row .check{display:flex;gap:6px;align-items:center}

    .hr{height:2px;background:#94a3b8;margin:14px 0}
    .right{display:flex;gap:8px;justify-content:flex-end}
    .muted{color:var(--muted)}

    /* ===== Modal Bootstrap confirmación (tema claro) ===== */
    #confirmModal .modal-content{
      background:var(--card) !important;
      color:var(--text) !important;
      border:2px solid #94a3b8 !important;
    }
    #confirmModal .modal-header,
    #confirmModal .modal-footer{ border-color:#94a3b8 !important; }
    #confirmModal .btn-outline-light{
      color:var(--text) !important;
      border-color:#cbd5e1 !important;
      background:#fff !important;
      box-shadow: var(--shadow-xs);
    }
    #confirmModal .btn-outline-light:hover{
      background:#f1f5f9 !important;
      border-color:var(--primary) !important;
      box-shadow: var(--shadow-sm);
    }

    /* ===== Toasts ===== */
    .toast-container .toast{
      border-radius:12px;
      box-shadow: var(--shadow-md);
    }
  </style>
</head>

<body>
<?php if (file_exists($NAVBAR)) {
  include $NAVBAR;
} else {
  echo "<p style='color:#b91c1c;background:#fee2e2;padding:8px 12px;border:1px solid #fecaca;border-radius:10px;margin:12px'>
          Aviso: no se encontró navbar en <code>".htmlspecialchars($NAVBAR, ENT_QUOTES, 'UTF-8')."</code>
        </p>";
} ?>

<!-- Contenedor global de toasts -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastArea" style="z-index:1086"></div>

<div class="container">
  <h1>Gestión de empleados</h1>

  <div class="actions-bar">
    <button class="btn btn-primary" id="btnAdd">Agregar empleado</button>
    <div style="flex:1 1 260px;display:flex;gap:8px">
      <input class="input" id="q" placeholder="Buscar por nombre o enlace…" style="flex:1 1 auto">
      <button class="btn btn-ghost" id="btnSearch">Buscar</button>
      <button class="btn btn-ghost" id="btnClear">Limpiar</button>
    </div>
    <span class="tag" id="totalTag">Total: 0</span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:120px">Enlace</th>
          <th>Nombres</th>
          <th>Apellidos</th>
          <th>Cargo</th>
          <th>Turno</th>
          <th style="width:240px">Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="6" class="muted" style="text-align:center;padding:22px">Cargando…</td></tr>
      </tbody>
    </table>
  </div>

  <div class="pager" id="pager"></div>
</div>

<!-- ====== PANEL-MODAL AGREGAR/EDITAR/VER ====== -->
<div class="sheet-back" id="modalBack" aria-hidden="true">
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="sheet-h">
      <strong id="modalTitle">Nuevo empleado</strong>
      <button class="btn btn-ghost" id="btnClose">X</button>
    </div>
    <div class="sheet-c">
      <form id="frmEmp" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="id_empleado" id="f_id">

        <div class="grid grid-3">
          <div>
              <!--EL CODIGO DE EMPLEADO ES EL CODIGO DE ENLACE DEL TRABAJADOR-->
            <label for="f_codigo">Enlace</label>
            <input class="input" id="f_codigo" name="codigo_empleado" maxlength="20" required>
          </div>
          <div>
            <label for="f_nombre">Nombres</label>
            <input class="input" id="f_nombre" name="nombre" maxlength="50" required>
          </div>
          <div>
            <label for="f_apellido">Apellidos</label>
            <input class="input" id="f_apellido" name="apellido" maxlength="50" required>
          </div>
          <div>
            <label for="f_cargo">Cargo</label>
            <input class="input" id="f_cargo" name="cargo" maxlength="50" required>
          </div>
          <div>
            <label for="f_turno">Turno</label>
            <input class="input" id="f_turno" name="turno" maxlength="50" required>
          </div>
        </div>

        <div class="hr"></div>
        <div style="margin-bottom:8px;display:flex;align-items:center;gap:8px">
          <strong>Horario laboral</strong>
          <span class="help">Define entrada/salida por día. “Activo” indica que ese día trabaja. “Tol.” = tolerancia (minutos).</span>
        </div>

        <div id="days"></div>

        <div class="right">
          <button type="button" class="btn btn-ghost" id="btnEditToggle" hidden>Editar</button>
          <button type="button" class="btn btn-danger" id="btnDelete" hidden>Eliminar</button>
          <button type="submit" class="btn btn-primary" id="btnSave">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ====== MODAL Bootstrap para confirmaciones ====== -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">Confirmación</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body border-secondary" id="confirmMessage">¿Seguro?</div>
      <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmOk">Sí, eliminar</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ==== REFERENCIAS API ==== */
const API  = '<?= rtrim(dirname($_SERVER["REQUEST_URI"]), "/") ?>/api.php';
const CSRF = '<?= $csrf ?>';

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
let viewMode = 'create'; // create | edit | view

/* ---------- TOASTS ---------- */
function showToast(message, variant='success', title='Listo') {
  const area = document.getElementById('toastArea');
  const el = document.createElement('div');
  const bg = variant==='success' ? 'text-bg-success' :
             variant==='error'   ? 'text-bg-danger'  :
             variant==='info'    ? 'text-bg-primary' : 'text-bg-secondary';
  el.className = `toast align-items-center ${bg} border-0`;
  el.setAttribute('role','alert');
  el.setAttribute('aria-live','assertive');
  el.setAttribute('aria-atomic','true');
  el.innerHTML = `
    <div class="d-flex">
      <div class="toast-body"><strong>${title}:</strong> ${message}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>`;
  area.appendChild(el);
  new bootstrap.Toast(el, { delay: 3000 }).show();
}
const showToastSuccess = (m)=>showToast(m,'success','Éxito');
const showToastError   = (m)=>showToast(m,'error','Error');

/* ---------- CONFIRMACIÓN (Bootstrap) ---------- */
function confirmBS(message) {
  return new Promise(resolve=>{
    const modalEl = document.getElementById('confirmModal');
    document.getElementById('confirmMessage').textContent = message;
    const okBtn = document.getElementById('confirmOk');

    const modal = new bootstrap.Modal(modalEl, {backdrop:'static'});
    const cleanup = () => {
      okBtn.removeEventListener('click', onOk);
      modalEl.removeEventListener('hidden.bs.modal', onHide);
    };
    const onOk = () => { cleanup(); modal.hide(); resolve(true); };
    const onHide = () => { cleanup(); resolve(false); };

    okBtn.addEventListener('click', onOk);
    modalEl.addEventListener('hidden.bs.modal', onHide);
    modal.show();
  });
}

/* ---------- DÍAS / HORARIOS ---------- */
const DAYS = [
  {n:1, label:'Lunes'}, {n:2, label:'Martes'}, {n:3, label:'Miércoles'},
  {n:4, label:'Jueves'}, {n:5, label:'Viernes'}, {n:6, label:'Sábado'},
  {n:7, label:'Domingo'},
];
function dayRow(d){
  const id = d.n;
  return `
  <div class="day-row" data-day="${id}">
    <div class="check">
      <input type="checkbox" id="chk_${id}" ${d.activo ? 'checked' : ''}>
      <label for="chk_${id}" style="margin:0">${d.label}</label>
    </div>
    <input class="input" type="time" id="in_${id}"  value="${d.entrada || ''}">
    <input class="input" type="time" id="out_${id}" value="${d.salida  || ''}">
    <input class="input" type="number" min="0" max="120" id="tol_${id}" value="${d.tolerancia_min ?? 10}" placeholder="Tolerancia (min)">
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
  const payload = {
    csrf: CSRF,
    id_empleado: ($frm.querySelector('#f_id').value || null),
    codigo_empleado: fd.get('codigo_empleado').trim(),
    nombre: fd.get('nombre').trim(),
    apellido: fd.get('apellido').trim(),
    turno: fd.get('turno').trim(),
    cargo: fd.get('cargo').trim(),
    horario: []
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

/* ---------- PANEL (abrir/cerrar/lectura) ---------- */
function setReadOnly(ro){
  $frm.querySelectorAll('input,select,button').forEach(el=>{
    if (el === $btnClose || el === $btnEditToggle) return;
    if (el.type === 'submit') { el.hidden = ro; return; }
    el.disabled = ro;
  });
}
function openModal(mode, data){
  viewMode = mode;
  $frm.reset();
  renderDays([]);
  $btnDelete.hidden = true;
  $btnEditToggle.hidden = true;

  if (mode === 'create'){
    $title.textContent = 'Nuevo empleado';
    $frm.f_codigo.readOnly = false;  // editable al crear
    setReadOnly(false);

  } else if (mode === 'edit'){
    $title.textContent = `Editar empleado #${data.id_empleado}`;
    $frm.f_id.value = data.id_empleado;
    $frm.f_codigo.value = data.codigo_empleado || '';
    $frm.f_nombre.value = data.nombre || '';
    $frm.f_apellido.value = data.apellido || '';
    $frm.f_cargo.value = data.cargo || '';
    $frm.f_turno.value = data.turno || '';
    $frm.f_codigo.readOnly = true; 
    renderDays(data.horario||[]);
    setReadOnly(false);

  } else if (mode === 'view'){
    $title.textContent = `Horario de ${data.nombre} ${data.apellido}`;
    $frm.f_id.value = data.id_empleado;
    /*El codigo de empleado es el ENLACE*/
    $frm.f_codigo.value = data.codigo_empleado || '';
    $frm.f_nombre.value = data.nombre || '';
    $frm.f_apellido.value = data.apellido || '';
    $frm.f_cargo.value = data.cargo || '';
    $frm.f_turno.value = data.turno || '';
    renderDays(data.horario||[]);
    setReadOnly(true);
  }

  $modalBack.style.display = 'flex';
  $modalBack.setAttribute('aria-hidden','false');
}
function closeModal(){
  $modalBack.style.display = 'none';
  $modalBack.setAttribute('aria-hidden','true');
}

/* ---------- Listado / Búsqueda / Paginación ---------- */
async function load(page=1){
  state.page = page;
  const params = new URLSearchParams({action:'list', page:state.page, limit:state.limit, q:state.q});
  try{
    const r = await fetch(`${API}?`+params, {headers:{'X-CSRF':CSRF}});
    const j = await r.json();
    if(!j.ok){ $tbody.innerHTML = `<tr><td colspan="5">${j.msg||'Error'}</td></tr>`; showToastError(j.msg||'Error al cargar'); return; }
    state.total = j.total; state.pages = j.pages;
    $totalTag.textContent = `Total: ${j.total}`;

    if(j.rows.length===0){
      $tbody.innerHTML = `<tr><td colspan="6" class="muted" style="text-align:center;padding:18px">Sin resultados</td></tr>`;
    }else{
      $tbody.innerHTML = j.rows.map(r => `
        <tr>
          <td><code>${r.codigo_empleado||r.id_empleado}</code></td>
          <td>${r.nombre||''}</td>
          <td>${r.apellido||''}</td>
          <td>${r.cargo||''}</td>
          <td>${r.turno||''}</td>
          <td>
            <div class="row-actions">
              <button class="btn btn-ghost" data-act="view" data-id="${r.id_empleado}">Ver horario</button>
              <button class="btn btn-light" data-act="edit" data-id="${r.id_empleado}">Editar</button>
              <button class="btn btn-danger" data-act="del"  data-id="${r.id_empleado}">Eliminar</button>
            </div>
          </td>
        </tr>
      `).join('');
    }
    renderPager();
  }catch(e){
    $tbody.innerHTML = `<tr><td colspan="6" class="text-danger">Error de red</td></tr>`;
    showToastError('No se pudo cargar el listado');
  }
}
function renderPager(){
  const p = state.page, P = state.pages;
  const btn = (i, label=i, current=false) => `<button class="page-btn" ${current?'aria-current="true"':''} data-page="${i}">${label}</button>`;
  const parts = [];
  if(P<=1){ $pager.innerHTML=''; return; }
  const push = i => parts.push(btn(i, i, i===p));
  push(1);
  if(p>3) parts.push('<span class="ellipsis">…</span>');
  for(let i=Math.max(2,p-1); i<=Math.min(P-1,p+1); i++) push(i);
  if(p<P-2) parts.push('<span class="ellipsis">…</span>');
  if(P>1) push(P);
  $pager.innerHTML = parts.join('');
}

/* ---------- Eventos de UI ---------- */
$pager.addEventListener('click',e=>{
  const b = e.target.closest('.page-btn'); if(!b) return;
  load(+b.dataset.page);
});

document.addEventListener('click', async e=>{
  const b = e.target.closest('button[data-act]'); if(!b) return;
  const id = +b.dataset.id;
  const act = b.dataset.act;

  if(act==='view' || act==='edit'){
    try{
      const r = await fetch(`${API}?action=get&id=${id}`, {headers:{'X-CSRF':CSRF}});
      const j = await r.json();
      if(!j.ok){ showToastError(j.msg||'No se pudo obtener el empleado'); return; }
      openModal(act==='view'?'view':'edit', j.data);
    }catch(_){ showToastError('Error de red'); }
  }

  if(act==='del'){
    const ok = await confirmBS('¿Eliminar empleado y su horario?');
    if(!ok) return;
    try{
      const r = await fetch(API+'?action=delete', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF},
        body: JSON.stringify({csrf:CSRF, id})
      });
      const j = await r.json();
      if(!j.ok){ showToastError(j.msg||'No se pudo eliminar'); return; }
      showToastSuccess('Empleado eliminado correctamente');
      load(state.page);
    }catch(_){ showToastError('Error de red'); }
  }
});

$btnAdd.addEventListener('click', ()=> openModal('create', null));
$btnClose.addEventListener('click', closeModal);
$modalBack.addEventListener('click', e=>{ if(e.target===$modalBack) closeModal(); });

$btnSearch.addEventListener('click', ()=>{ state.q=$q.value.trim(); load(1); });
$btnClear.addEventListener('click', ()=>{ $q.value=''; state.q=''; load(1); });

$btnEditToggle.addEventListener('click', ()=>{
  setReadOnly(false);
  $btnEditToggle.hidden = true;
});

$btnDelete.addEventListener('click', async ()=>{
  const id = +($frm.f_id.value||0);
  if(!id) return;
  const ok = await confirmBS('¿Eliminar empleado y su horario?');
  if(!ok) return;
  try{
    const r = await fetch(API+'?action=delete', {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF},
      body: JSON.stringify({csrf:CSRF, id})
    });
    const j = await r.json();
    if(!j.ok){ showToastError(j.msg||'No se pudo eliminar'); return; }
    closeModal();
    showToastSuccess('Empleado eliminado correctamente');
    load(state.page);
  }catch(_){ showToastError('Error de red'); }
});

/* ---------- Guardar (crear/editar) ---------- */
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
    showToastSuccess(isEdit ? 'Empleado actualizado correctamente' : 'Empleado creado correctamente');
    load(isEdit ? state.page : 1);
  }catch(_){ showToastError('Error de red'); }
});

/* ---------- Inicial ---------- */
load(1);
</script>
</body>
</html>
