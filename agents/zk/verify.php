<?php
// C:\xampp\htdocs\Checador_Scap\agents\zk\verify.php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
  exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?? [];
$userCode = trim($in['userCode'] ?? '');
$simulate = !empty($in['simulate']);

try {
  if ($userCode === '') throw new Exception('Falta userCode.');

  // ====== SIMULADO: generamos un "template" determinístico por userCode ======
  if ($simulate) {
    // En el enrolamiento simulado guardaste base64(sha256(userCode))
    $bin = hash('sha256', $userCode, true);
    $templateSimulado = base64_encode($bin);
    // Para efectos de demo declaramos que "coincide"
    echo json_encode(['ok'=>true, 'matched'=>true, 'templateUsed'=>$templateSimulado, 'mode'=>'simulate']);
    exit;
  }

  // ====== REAL (cuando tengas SDK y lector) ======
  // 1) Capturas el dedo del usuario, obtienes el template con SDK
  // 2) Cotejas contra el almacenado y devuelves matched=true/false
  // Por ahora, hasta tener el hardware, no implementamos aquí.

  echo json_encode(['ok'=>false, 'msg'=>'Verificación real no implementada aún.']);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]);
}
