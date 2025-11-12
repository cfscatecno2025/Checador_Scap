<?php
// C:\xampp\htdocs\Checador_Scap\reportes\_common.php
session_start();
$ROOT = dirname(__DIR__);
require_once $ROOT . '/auth/require_login.php';
require_once $ROOT . '/auth/require_role.php';
require_role(['admin']);
require_once $ROOT . '/conection/conexion.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function db(): PDO { return DB::conn(); }

/** Calcula rango por tipo; deja sobreescribir con ?fi y ?ff */
function rango_por_tipo(string $tipo): array {
  $hoy = new DateTime('today');
  $fi = $_GET['fi'] ?? null;
  $ff = $_GET['ff'] ?? null;

  switch ($tipo) {
    case 'diario':
      $fi = $fi ?: $hoy->format('Y-m-d');
      $ff = $ff ?: $hoy->format('Y-m-d');
      break;

    case 'semanal':
      $m = (clone $hoy)->modify('monday this week');
      $fi = $fi ?: $m->format('Y-m-d');
      $ff = $ff ?: (clone $m)->modify('+6 days')->format('Y-m-d');
      break;

    case 'quincenal':
      $first = new DateTime($hoy->format('Y-m-01'));
      $mid   = (clone $first)->modify('+14 days');
      if ((int)$hoy->format('d') <= 15) {
        $fi = $fi ?: $first->format('Y-m-d');
        $ff = $ff ?: $mid->format('Y-m-d');
      } else {
        $fi = $fi ?: (clone $mid)->modify('+1 day')->format('Y-m-d');
        $ff = $ff ?: (clone $first)->modify('last day of this month')->format('Y-m-d');
      }
      break;

    case 'mensual':
      $first = new DateTime($hoy->format('Y-m-01'));
      $last  = (clone $first)->modify('last day of this month');
      $fi = $fi ?: $first->format('Y-m-d');
      $ff = $ff ?: $last->format('Y-m-d');
      break;

    default: // custom/fallback
      $fi = $fi ?: $hoy->format('Y-m-d');
      $ff = $ff ?: $hoy->format('Y-m-d');
  }

  return [$fi, $ff];
}

/** Trae filas de asistencias_historico en rango */
function obtener_asistencias(string $fi, string $ff): array {
  $pdo = db();
  $q = $pdo->prepare("
    SELECT ah.fecha, e.codigo_empleado, e.nombre, e.apellido,
           ah.hora_entrada, ah.hora_salida, ah.retardo_minutos, ah.estado
    FROM asistencias_historico ah
    JOIN empleados e ON e.id_empleado = ah.id_empleado
    WHERE ah.fecha BETWEEN :fi AND :ff
    ORDER BY ah.fecha ASC, e.codigo_empleado ASC
  ");
  $q->execute([':fi'=>$fi, ':ff'=>$ff]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  $tot = ['registros'=>count($rows), 'retardos'=>0];
  foreach ($rows as $r) if ((int)$r['retardo_minutos'] > 0) $tot['retardos']++;

  return [$rows, $tot];
}

/** Estilos básicos (compartidos) */
function estilos_reportes() {
  echo '<style>
    :root{--bd:#e5e7eb;--card:#fff;--txt:#111;--muted:#666;--p:#331ED4}
    body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;margin:24px;color:var(--txt);background:#fafafa}
    .container{max-width:1200px;margin:0 auto}
    h1{margin:0 0 12px}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:16px;margin-top:12px}
    .row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    select,input{padding:10px;border:1px solid #ccc;border-radius:8px;background:#fff}
    .btn{padding:10px 14px;border:none;border-radius:8px;background:var(--p);color:#fff;font-weight:700;cursor:pointer}
    .btn:hover{opacity:.95}
    table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid var(--bd);border-radius:12px;overflow:hidden}
    th,td{padding:10px 12px;border-bottom:1px solid var(--bd);text-align:left;font-size:14px}
    th{background:#f8fafc}
    tr:last-child td{border-bottom:none}
    .muted{color:var(--muted)}
  </style>';
}

/** Dibuja tabla */
function render_tabla(array $rows) {
  echo '<div style="overflow:auto"><table><thead><tr>
    <th>Fecha</th><th>Código</th><th>Nombre</th><th>Apellido</th>
    <th>Entrada</th><th>Salida</th><th>Retardo (min)</th><th>Estado</th>
  </tr></thead><tbody>';
  if ($rows) {
    foreach ($rows as $r) {
      echo '<tr>
        <td>'.h($r['fecha']).'</td>
        <td>'.h($r['codigo_empleado']).'</td>
        <td>'.h($r['nombre']).'</td>
        <td>'.h($r['apellido']).'</td>
        <td>'.h($r['hora_entrada']).'</td>
        <td>'.h($r['hora_salida']).'</td>
        <td>'.(int)$r['retardo_minutos'].'</td>
        <td>'.h($r['estado']).'</td>
      </tr>';
    }
  } else {
    echo '<tr><td colspan="8" class="muted">Sin datos</td></tr>';
  }
  echo '</tbody></table></div>';
}
