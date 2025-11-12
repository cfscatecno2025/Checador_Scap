<?php
// C:\xampp\htdocs\Checador_Scap\agents\zk\enroll.php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
  exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$ip       = trim($in['deviceIp']  ?? '');
$commKey  = trim($in['commKey']   ?? '0');
$userCode = trim($in['userCode']  ?? '');
$simulate = !empty($in['simulate']); // <= si viene true, simulamos

try {
  if ($userCode === '') throw new Exception('Falta userCode.');
  if (!$simulate && $ip === '') throw new Exception('Falta deviceIp (modo real).');

  // ====== MODO SIMULACIÓN (sin hardware) ======
  if ($simulate) {
    // Plantilla “estable” por empleado: base64(sha256(userCode))
    $bin = hash('sha256', $userCode, true);
    $template = base64_encode($bin);
    echo json_encode(['ok' => true, 'template' => $template, 'mode' => 'simulate']);
    exit;
  }

  // ====== TODO REAL (COM/SDK ZKTeco) ======
  // 1) Habilita extension=php_com_dotnet.dll en php.ini
  // 2) regsvr32 ZKEMKeeper.dll
  // 3) Conectar y leer plantilla:
  /*
  $zk = new COM("zkemkeeper.ZKEM.1");
  if (!$zk->Connect_Net($ip, 4370)) throw new Exception("No conecta a $ip:4370");
  // if ((int)$commKey > 0) $zk->SetCommPassword((int)$commKey);

  $templateStr = ''; $found = false; $flag = 1;
  for ($i=0; $i<=9; $i++) {
    $tmp=''; $flg=$flag;
    if ($zk->SSR_GetUserTmpExStr(1, $userCode, $i, $flg, $tmp) && !empty($tmp)) {
      $templateStr = $tmp; $found=true; break;
    }
  }
  $zk->Disconnect();
  if (!$found) throw new Exception('No se encontró plantilla en el MB160 para ese usuario.');

  echo json_encode(['ok' => true, 'template' => base64_encode($templateStr), 'mode' => 'real']);
  */
} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
