<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$BASE = '/Checador_Scap';
?>
<style>
  .nav{background:#0b1220;color:#fff;padding:10px 16px;position:sticky;top:0;z-index:50}
  .nav__wrap{max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:18px}
  .nav__brand a{color:#fff;text-decoration:none;font-weight:800;padding:8px 12px;border-radius:10px;background:#0f1a37}
  .nav__spacer{flex:1}
  .nav__menu{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
  .nav__item{position:relative}
  .nav__link{display:inline-block;padding:8px 12px;border-radius:8px;color:#e5e7eb;text-decoration:none}
  .nav__link:hover{background:#1f2a44;color:#fff}
  .nav__dd{display:none;position:absolute;top:100%;left:0;background:#0f1a37;border:1px solid #233154;border-radius:10px;min-width:240px;padding:8px;margin-top:6px}
  .nav__item:hover .nav__dd{display:block}
  .nav__dd a{display:block;padding:8px 12px;color:#e5e7eb;text-decoration:none;border-radius:8px}
  .nav__dd a:hover{background:#1f2a44;color:#fff}
  .nav__logout{padding:8px 12px;border:none;border-radius:8px;background:#2d39a6;color:#fff;cursor:pointer}
  .nav__logout:hover{opacity:.95}
</style>

<nav class="nav">
  <div class="nav__wrap">
    <div class="nav__brand">
      <a href="<?= $BASE ?>/vistas-roles/vista-usuario.php">Mi Empresa</a>
    </div>
    <div class="nav__spacer"></div>
    <div class="nav__menu">
      <!-- Solo lo necesario para usuario -->
      <div class="nav__item">
        <a class="nav__link" href="#">Empleados</a>
        <div class="nav__dd">
          <a href="<?= $BASE ?>/horario-empleados/horario-emplados.php">Hora de Entrada / Salida</a>
          <a href="<?= $BASE ?>/justifiacion-empleados/justificacion-empleados">Justificación de faltas</a>
          <a href="<?= $BASE ?>/lista-empleados/Lista-empleados.php">Lista de empleados</a>
        </div>
      </div>

      <form method="POST" action="<?= $BASE ?>/logout.php">
        <button type="submit" class="nav__logout">Cerrar sesión</button>
      </form>
    </div>
  </div>
</nav>
