<?php
// fingerprint/proxy.php
header('Content-Type: application/json; charset=utf-8');

$BASE = 'http://127.0.0.1:8787';               // servicio Java local
$path = ltrim($_GET['p'] ?? '', '/');          // ej: api/enroll
if ($path === '') { http_response_code(400); echo '{"ok":false,"error":"missing path"}'; exit; }

$url = $BASE . '/' . $path;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$hdrs = ['Content-Type: application/json'];
curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  $body = file_get_contents('php://input');
  curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$resp = curl_exec($ch);
if ($resp === false) {
  http_response_code(502);
  echo json_encode(['ok'=>false,'error'=>'proxy error: '.curl_error($ch)]);
  curl_close($ch); exit;
}
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($code ?: 200);
echo $resp;
