<?php
// C:\xampp\htdocs\Checador_Scap\reportes\quincenal.php
require_once __DIR__ . '/_common.php';
[$fi, $ff] = rango_por_tipo('quincenal');
[$rows, $tot] = obtener_asistencias($fi, $ff);

$NAV1 = $ROOT . '/components/navbar-admin.php';
$NAV2 = $ROOT . '/components/navbar.php';
?><!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><title>Reporte Quincenal</title><meta name="viewport" content="width=device-width,initial-scale=1">
<?php estilos_reportes(); ?>
</head><body>
<?php if (is_file($NAV1)) include $NAV1; elseif (is_file($NAV2)) include $NAV2; ?>
<main class="container">
  <h1>Reporte Quincenal</h1>

  <div class="card">
    <form class="row" method="GET" action="">
      <label>Desde</label><input type="date" name="fi" value="<?= h($fi) ?>">
      <label>Hasta</label><input type="date" name="ff" value="<?= h($ff) ?>">
      <button class="btn" type="submit">Aplicar</button>
      <a class="btn" href="export_csv.php?fi=<?= h($fi) ?>&ff=<?= h($ff) ?>">CSV</a>
      <a class="btn" href="export_pdf.php?fi=<?= h($fi) ?>&ff=<?= h($ff) ?>" target="_blank">PDF</a>
    </form>
  </div>

  <div class="card">
    <div class="muted" style="margin-bottom:8px">Registros: <b><?= $tot['registros'] ?></b> · Retardos: <b><?= $tot['retardos'] ?></b> · Rango: <?= h($fi) ?> a <?= h($ff) ?></div>
    <?php render_tabla($rows); ?>
  </div>
</main>
</body></html>
