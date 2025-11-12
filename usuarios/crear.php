<?php
/**
 * Checador_Scap/usuarios/crear.php
 * Vista unificada: listado de usuarios + búsqueda + paginación + modal de alta/edición
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
  <title>Gestión de usuarios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* ============================
       TEMA CLARO + BORDES MARCADOS
       ============================ */
    :root{
      --bg:#f6f8fb;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;
      --bd:#cbd5e1;               /* borde base */
      --bd-strong:#94a3b8;        /* borde más notorio */

      --input:#ffffff;
      --chip:#f1f5f9;
      --chipbd:#e2e8f0;

      --primary:#2563eb;
      --primary-700:#1d4ed8;
      --danger:#dc2626;
      --success:#16a34a;

      /* imagen de fondo (opcional) */
      --bg-image: url('/Checador_Scap/assets/img/logo_login_scap.jpg');
      --bg-size: clamp(260px, 32vw, 520px); /* controla tamaño del fondo */
    }

    *{box-sizing:border-box}
    body{
      margin:0;
      font-family:system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      background:var(--bg);
      color:var(--text);
    }
    /* Fondo centrado, no ocupa toda la pantalla */
    body::before{
      content:"";
      position:fixed; inset:0; z-index:-1;
      background-image: var(--bg-image);
      background-repeat:no-repeat;
      background-position:center center;
      background-size: var(--bg-size) auto;
      background-attachment:fixed;
      opacity:.10;
      filter:saturate(.95) brightness(1.02) contrast(1.03);
      pointer-events:none;
    }

    .container{max-width:1100px;margin:24px auto;padding:0 16px}
    h1{margin:6px 0 18px;font-size:28px}

    .actions-bar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px}

    .btn{border:1px solid var(--bd-strong); border-radius:10px; padding:10px 14px; font-weight:700; cursor:pointer}
    .btn-primary{background:var(--primary);color:#fff;border-color:transparent}
    .btn-primary:hover{background:var(--primary-700)}
    .btn-light{background:#f8fafc;color:#0f172a;border:1px solid var(--bd-strong)}
    .btn-danger{background:var(--danger);color:#fff;border-color:transparent}
    .btn-ghost{background:#fff;color:var(--text);border:1px solid var(--bd-strong)}

    .input{
      background:var(--input);
      border:2px solid var(--bd-strong);  /* borde notorio */
      color:var(--text);
      border-radius:10px;
      padding:10px 12px;
      outline:none;
      transition: box-shadow .15s ease, border-color .15s ease;
      box-shadow: 0 0 0 3px rgba(37,99,235,0); /* halo neutro */
    }
    .input:focus{
      border-color:var(--primary);
      box-shadow: 0 0 0 3px rgba(37,99,235,.18); /* halo de enfoque */
    }
    .input::placeholder{color:var(--muted)}

    .table-wrap{overflow:auto;border:2px solid var(--bd-strong);border-radius:14px;background:var(--card)}
    table{width:100%;border-collapse:collapse;min-width:760px}
    th,td{padding:12px;border-bottom:1px solid var(--bd);text-align:left;vertical-align:middle}
    thead th{background:#f8fafc;position:sticky;top:0;z-index:1;color:#0f172a;border-bottom:2px solid var(--bd-strong)}

    .tag{
      display:inline-flex;gap:6px;align-items:center;
      background:var(--chip);border:1px solid var(--chipbd);color:var(--muted);
      font-size:12px;padding:3px 8px;border-radius:999px
    }
    .row-actions{display:flex;gap:6px;flex-wrap:wrap}
    .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;border:1px solid var(--bd-strong);background:#fff;color:#0f172a}

    .pager{display:flex;gap:6px;align-items:center;justify-content:center;margin-top:14px;flex-wrap:wrap}
    .page-btn{
      border:1px solid var(--bd-strong);
      background:#ffffff;
      color:#0f172a;
      padding:8px 12px;border-radius:10px;cursor:pointer
    }
    .page-btn[aria-current="true"]{background:var(--primary);color:#fff;border-color:transparent}
    .ellipsis{color:var(--muted);padding:0 4px}

    /* ===== Panel modal propio ===== */
    .sheet-back{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;padding:16px;z-index:1065}
    .sheet{width:100%;max-width:820px;background:var(--card);border:2px solid var(--bd-strong);border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(15,23,42,.25)}
    .sheet-h{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:2px solid var(--bd-strong);background:#ffffffa8;backdrop-filter:saturate(1.2) blur(2px)}
    .sheet-c{padding:16px;background:#fff}

    .grid{display:grid;gap:12px}
    @media (min-width:760px){ .grid-2{grid-template-columns:1fr 1fr} .grid-3{grid-template-columns:1fr 1fr 1fr} }
    label{font-weight:600;margin-bottom:6px;display:block}
    .help{color:var(--muted);font-size:12px}

    .hr{height:1px;background:var(--bd);margin:14px 0}
    .right{display:flex;gap:8px;justify-content:flex-end}
    .muted{color:var(--muted)}

    /* ===== Modal Bootstrap confirmación (tema claro) ===== */
    #confirmModal .modal-content{
      background:var(--card) !important;
      color:var(--text) !important;
      border:2px solid var(--bd-strong) !important;
    }
    #confirmModal .modal-header,
    #confirmModal .modal-footer{ border-color:var(--bd-strong) !important; }
    #confirmModal .btn-outline-light{
      color:var(--text) !important;
      border-color:var(--bd-strong) !important;
      background:#fff !important;
    }
    #confirmModal .btn-outline-light:hover{
      background:#f1f5f9 !important;
    }

    .toast-container .toast{
      border-radius:12px;
      box-shadow:0 10px 30px rgba(15,23,42,.2);
    }
  </style>
</head>

<body>
<?php if (is_file($NAVBAR)) include $NAVBAR; ?>

<!-- Contenedor global de toasts -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastArea" style="z-index:1086"></div>

<div class="container">
  <h1>Gestión de usuarios</h1>

  <div class="actions-bar">
    <button class="btn btn-primary" id="btnAdd">Agregar usuario</button>
    <div style="flex:1 1 260px;display:flex;gap:8px">
      <input class="input" id="q" placeholder="Buscar por clave, rol o id_empleado…" style="flex:1 1 auto">
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
          <th>Clave acceso</th>
          <th>Rol</th>
          <th>Creado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="6" class="muted" style="text-align:center;padding:22px">Cargando…</td></tr>
      </tbody>
    </table>
  </div>

  <div class="pager" id="pager"></div>
</div>

<!-- ====== PANEL-MODAL USUARIO ====== -->
<div class="sheet-back" id="modalBack" aria-hidden="true">
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="sheet-h">
      <strong id="modalTitle">Nuevo usuario</strong>
      <button class="btn btn-ghost" id="btnClose">X</button>
    </div>
    <div class="sheet-c">
      <form id="frmUsr" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="id_usuario" id="f_id">

        <div class="grid grid-2">
          <div>
            <label for="f_id_empleado">Enlace</label>
            <input class="input" id="f_id_empleado" name="id_empleado" type="number" min="1" list="empList" required>
            <datalist id="empList"></datalist>
            <span class="help">Selecciona o escribe el codigo de enlace para el empleado.</span>
          </div>
          <div>
            <label for="f_clave">Clave de acceso</label>
            <input class="input" id="f_clave" name="clave_acceso" maxlength="50" required>
          </div>
        </div>

        <div class="grid grid-2">
          <div>
            <label for="f_rol">Rol</label>
            <input class="input" id="f_rol" name="rol" list="rolesList" maxlength="50" required>
            <datalist id="rolesList">
              <option value="Administrador"></option>
              <!--AGREGAR MAS OPCIONES SI SE REQUIERE-->
            </datalist>
          </div>
          <div>
            <label for="f_pass">Contraseña</label>
            <input class="input" id="f_pass" name="contrasena" type="password" autocomplete="new-password">
          </div>
        </div>

        <div class="right">
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
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmación</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="confirmMessage">¿Seguro?</div>
      <div class="modal-footer">
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
const API  = '<?= rtrim(dirname($_SERVER["REQUEST_URI"]), "/") ?>/api.php'; // /usuarios/api.php
const EMP_API = '<?= rtrim(dirname(dirname($_SERVER["REQUEST_URI"])), "/") ?>/empleados/api.php'; // /empleados/api.php
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
const $frm       = document.getElementById('frmUsr');
const $title     = document.getElementById('modalTitle');
const $btnDelete = document.getElementById('btnDelete');

let state = { page:1, limit:5, q:'', total:0, pages:1 };

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
const toastOk  = (m)=>showToast(m,'success','Éxito');
const toastErr = (m)=>showToast(m,'error','Error');

/* ---------- CONFIRM (Bootstrap) ---------- */
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

/* ---------- Abrir / Cerrar modal ---------- */
function openModal(mode, data){
  $frm.reset();
  $btnDelete.hidden = true;

  if (mode === 'create'){
    $title.textContent = 'Nuevo usuario';

  } else if (mode === 'edit'){
    $title.textContent = `Editar usuario #${data.id_usuario}`;
    $frm.f_id.value = data.id_usuario;
    $frm.f_id_empleado.value = data.id_empleado || '';
    $frm.f_clave.value = data.clave_acceso || '';
    $frm.f_rol.value   = data.rol || '';
    // contraseña vacía => no cambia
    $btnDelete.hidden = true;
  }

  $modalBack.style.display = 'flex';
  $modalBack.setAttribute('aria-hidden','false');
}
function closeModal(){
  $modalBack.style.display = 'none';
  $modalBack.setAttribute('aria-hidden','true');
}

/* ---------- Recolectar formulario ---------- */
function gatherForm(){
  const fd = new FormData($frm);
  const payload = {
    csrf: CSRF,
    id_usuario: ($frm.querySelector('#f_id').value || null),
    id_empleado: parseInt(fd.get('id_empleado') || '0', 10) || null,
    clave_acceso: fd.get('clave_acceso').trim(),
    rol: fd.get('rol').trim()
  };
  const pass = fd.get('contrasena').trim();
  if (pass) payload.contrasena = pass; // sólo enviar si se capturó
  return payload;
}

/* ---------- Listado / Búsqueda / Paginación ---------- */
async function load(page=1){
  state.page = page;
  const params = new URLSearchParams({action:'list', page:state.page, limit:state.limit, q:state.q});
  try{
    const r = await fetch(`${API}?`+params, {headers:{'X-CSRF':CSRF}});
    const j = await r.json();
    if(!j.ok){ $tbody.innerHTML = `<tr><td colspan="6">${j.msg||'Error'}</td></tr>`; toastErr(j.msg||'Error al cargar'); return; }
    state.total = j.total; state.pages = j.pages;
    $totalTag.textContent = `Total: ${j.total}`;

    if(j.rows.length===0){
      $tbody.innerHTML = `<tr><td colspan="6" class="muted" style="text-align:center;padding:18px">Sin resultados</td></tr>`;
    }else{
      $tbody.innerHTML = j.rows.map(r => `
        <tr>
          <td><code>${r.id_empleado??r.id_usuario}</code></td>
          <td>${r.clave_acceso??''}</td>
          <td><span class="pill">${r.rol??''}</span></td>
          <td>${(r.fecha_creacion??'').toString().replace('T',' ').substring(0,19)}</td>
          <td>
            <div class="row-actions">
              <button class="btn btn-light"  data-act="edit" data-id="${r.id_usuario}">Editar</button>
              <button class="btn btn-danger" data-act="del"  data-id="${r.id_usuario}">Eliminar</button>
            </div>
          </td>
        </tr>
      `).join('');
    }
    renderPager();
  }catch(e){
    $tbody.innerHTML = `<tr><td colspan="6" class="text-danger">Error al cargar registros</td></tr>`;
    toastErr('No se pudo cargar el listado');
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

/* ---------- Eventos ---------- */
$pager.addEventListener('click',e=>{
  const b = e.target.closest('.page-btn'); if(!b) return;
  load(+b.dataset.page);
});

document.addEventListener('click', async e=>{
  const b = e.target.closest('button[data-act]'); if(!b) return;
  const id = +b.dataset.id;
  const act = b.dataset.act;

  if(act==='edit'){
    try{
      const r = await fetch(`${API}?action=get&id=${id}`, {headers:{'X-CSRF':CSRF}});
      const j = await r.json();
      if(!j.ok){ toastErr(j.msg||'No se pudo obtener el usuario'); return; }
      openModal('edit', j.data);
    }catch(_){ toastErr('Error de red'); }
  }

  if(act==='del'){
    const ok = await confirmBS('¿Eliminar usuario?'); if(!ok) return;
    try{
      const r = await fetch(API+'?action=delete', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF},
        body: JSON.stringify({csrf:CSRF, id})
      });
      const j = await r.json();
      if(!j.ok){ toastErr(j.msg||'No se pudo eliminar'); return; }
      toastOk('Usuario eliminado correctamente');
      load(state.page);
    }catch(_){ toastErr('Error de red'); }
  }
});

document.getElementById('btnAdd').addEventListener('click', ()=> openModal('create', null));
$btnClose.addEventListener('click', closeModal);
$modalBack.addEventListener('click', e=>{ if(e.target===$modalBack) closeModal(); });

$btnSearch.addEventListener('click', ()=>{ state.q=$q.value.trim(); load(1); });
$btnClear.addEventListener('click', ()=>{ $q.value=''; state.q=''; load(1); });

/* ---------- Guardar (crear/editar) ---------- */
$frm.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const payload = gatherForm();
  const isEdit = !!payload.id_usuario;
  const action = isEdit ? 'update' : 'create';
  try{
    const r = await fetch(API+`?action=${action}`, {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF':CSRF},
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    if(!j.ok){ toastErr(j.msg||'No se pudo guardar'); return; }
    closeModal();
    toastOk(isEdit ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente');
    load(isEdit ? state.page : 1);
  }catch(_){ toastErr('Error de red'); }
});

/* ---------- Datalist de empleados (opcional) ---------- */
async function preloadEmpleados(){
  try{
    const r = await fetch(`${EMP_API}?action=list&limit=100`, {headers:{'X-CSRF':CSRF}});
    const j = await r.json();
    if(!j.ok || !Array.isArray(j.rows)) return;
    const $dl = document.getElementById('empList');
    $dl.innerHTML = j.rows.map(e => `<option value="${e.id_empleado}">${(e.codigo_empleado||'')+' '+(e.nombre||'')+' '+(e.apellido||'')}</option>`).join('');
  }catch(_){}
}

/* ---------- Inicial ---------- */
preloadEmpleados();
load(1);
</script>
</body>
</html>
