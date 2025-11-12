<?php
$ROOT = dirname(__DIR__);
require_once $ROOT . '/scripts/ensure_daily_close.php';
?>

<!-- Navbar: guarda como partials/navbar.php o inclúyelo en tu layout -->
<nav class="nav">
  <div class="nav__inner">
    <!--LOGO 1-->
    <a class="nav__logo1" href="/Checador_Scap/vistas-roles/vista-admin.php">
      <img src="/Checador_Scap/assets/img/logo_gobierno_chiapas.png" alt="logo gop_chis" height="28">
    </a>
    <!-- LOGO 2-->
    <a class="nav__logo" href="/Checador_Scap/vistas-roles/vista-admin.php">
      <img src="/Checador_Scap/assets/img/logo_navbar.webp" alt="logo isstech" height="28">
      ISSTECH
    </a>

    <!-- Hamburger (móvil) -->
    <button class="nav__toggle" id="navToggle" aria-label="Abrir menú" aria-expanded="false">
      <span class="nav__bar"></span>
      <span class="nav__bar"></span>
      <span class="nav__bar"></span>
    </button>

    <!-- MENÚS -->
    <ul class="nav__menu" id="navMenu">
        <!-- Empleados -->
      <li class="nav__item">
        <a class="nav__link" href="/Checador_Scap/empleados/crear.php">Gestión de empleados</a>
      </li>
        
      <!-- Usuarios -->
      <li class="nav__item nav__item--has-sub">
          <a class="nav__link" href="/Checador_Scap/usuarios/crear.php">Gestión de usuarios</a>
      </li>

      <!-- Asistencias -->
      <li class="nav__item nav__item--has-sub">
         <a class="nav__link" href="/Checador_Scap/asistencias/registro.php">Gestión de Asistencias</a>
      </li>

      <!-- Reportes -->
      <li class="nav__item nav__item--has-sub">
         <a class="nav__link" href="/Checador_Scap/reportes/registro_reportes.php">Gestión de Reportes</a>
      </li>

      <!-- Cerrar sesión -->
      <li class="nav__item nav__item--logout">
        <form method="POST" action="/Checador_Scap/logout.php">
          <!-- <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?? '' ?>"> -->
          <button type="submit" class="nav__logout">Cerrar sesión</button>
        </form>
      </li>
    </ul>
  </div>
</nav>

<style>
  /* ====== NAVBAR (profesional, responsive) ====== */
  :root{
    --nav-bg:#000033;      /* fondo */
    --nav-fg:#ffffff;      /* texto/iconos */
    --nav-fg-muted:#cbd5e1;/* texto tenue */
    --nav-accent:#8b7bff;  /* hover/focus ring (se mantiene para foco) */
    --sub-bg:#1f2937;      /* fondo submenu */
    --ring: rgba(139,123,255,.35);
    --radius:12px;
  }
  .nav{
    background:var(--nav-bg);
    color:var(--nav-fg);
    position:sticky; top:0; z-index:1000;
    box-shadow:0 6px 24px rgba(0,0,0,.15);
  }
  .nav__inner{
    max-width:1200px; margin:0 auto; padding:10px 16px;
    display:flex; align-items:center; justify-content:space-between;
    gap:12px;
  }
  .nav__logo{display:flex; align-items:center; gap:10px; text-decoration:none; color:var(--nav-fg)}
  .nav__brand{font-weight:800; letter-spacing:.2px; font-size:18px}

  /* Toggle (hamburger) */
  .nav__toggle{
    width:44px; height:44px; display:none; place-items:center;
    background:transparent; border:1px solid rgba(255,255,255,.15);
    border-radius:10px; color:var(--nav-fg);
  }
  .nav__bar{display:block; width:22px; height:2px; background:var(--nav-fg); margin:3px 0}

  /* Menú principal */
  .nav__menu{list-style:none; margin:0; padding:0; display:flex; align-items:center; gap:8px}
  .nav__item{position:relative}
  .nav__link{
    background:transparent; border:0; color:var(--nav-fg); cursor:pointer;
    padding:10px 12px; border-radius:10px; font-weight:600;
  }
  .nav__link:hover, .nav__link:focus{outline:none; box-shadow:0 0 0 3px var(--ring)}
  .nav__item a{color:var(--nav-fg); text-decoration:none; display:block; padding:10px 12px; border-radius:10px}
  .nav__item a:hover, .nav__item a:focus{outline:none; box-shadow:0 0 0 3px var(--ring)}

  /* Submenús */
  .nav__item--has-sub > .nav__sub{
    position:absolute; top:100%; left:0; min-width:280px;
    background:var(--sub-bg); padding:8px; margin-top:6px;
    border-radius:12px; box-shadow:0 16px 40px rgba(0,0,0,.35);
    display:none;
  }
  .nav__item--has-sub:hover > .nav__sub{display:block}
  .nav__sub li{list-style:none}
  .nav__sub a{color:var(--nav-fg-muted)}
  .nav__sub a:hover{color:#fff; background:rgba(255,255,255,.06)}

  /* Logout (base) */
  .nav__item--logout form{margin:0}
  .nav__logout{
    background:transparent; border:1px solid rgba(255,255,255,.25);
    color:var(--nav-fg); padding:8px 12px; border-radius:10px; cursor:pointer;
  }
  .nav__logout:hover{border-color:var(--nav-accent); box-shadow:0 0 0 3px var(--ring)}

  /* ===== Responsive ===== */
  @media (max-width: 900px){
    .nav__toggle{display:grid}
    .nav__menu{
      position:fixed; inset:auto 0 0 0; top:60px;
      background:var(--nav-bg); flex-direction:column; align-items:stretch;
      gap:0; padding:8px 12px 16px; display:none;
    }
    .nav__menu.is-open{display:flex}
    .nav__item--has-sub > .nav__sub{
      position:static; display:none; margin:6px 0 12px; box-shadow:none;
    }
    .nav__item--has-sub.open > .nav__sub{display:block}
    .nav__link, .nav__item a{padding:12px}
  }

  /* ===== Overrides mínimos solicitados ===== */
  /* Transiciones suaves */
  .nav__item a,
  .nav__link {
    transition: background-color .15s, color .15s, border-color .15s, box-shadow .15s;
  }

  /* Hover/Foco: enlaces y botones del menú principal -> fondo azul */
  .nav__item a:hover,
  .nav__item a:focus,
  .nav__link:hover,
  .nav__link:focus {
    background: #2563eb;        /* azul */
    color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37,99,235,.25);
    outline: none;
  }

  /* Hover en items del submenú -> azul */
  .nav__item--has-sub > .nav__sub a:hover,
  .nav__item--has-sub > .nav__sub a:focus {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    outline: none;
  }

  /* Botón Cerrar sesión rojo (base y hover) */
  .nav__logout {
    background: #dc2626;        /* rojo base */
    color: #ffffff;
    border: 1px solid #b91c1c;  /* borde rojo oscuro */
  }
  .nav__logout:hover,
  .nav__logout:focus {
    background: #b91c1c;        /* rojo más oscuro al hover */
    border-color: #991b1b;
    box-shadow: 0 0 0 2px rgba(220,38,38,.25);
    outline: none;
  }
</style>

<script>
  // Toggle menú en móvil
  const navToggle = document.getElementById('navToggle');
  const navMenu   = document.getElementById('navMenu');
  navToggle.addEventListener('click', () => {
    const open = navMenu.classList.toggle('is-open');
    navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // Abrir/cerrar submenús con click en móvil (y teclado en desktop)
  document.querySelectorAll('.nav__item--has-sub > .nav__link').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const li = e.currentTarget.parentElement;
      if (window.matchMedia('(max-width: 900px)').matches) {
        li.classList.toggle('open');
        const expanded = li.classList.contains('open');
        e.currentTarget.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      }
    });
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        e.currentTarget.click();
      }
    });
  });
</script>
