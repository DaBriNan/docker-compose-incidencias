<?php
header('Content-Type: application/json');

function out($c, $d = null) { http_response_code($c); echo json_encode($d); exit; }

function enviar_correo($asunto, $cuerpo) {
  $s = @fsockopen(getenv('SMTP_HOST'), (int)getenv('SMTP_PORT'), $en, $es, 3);
  if (!$s) return false;
  fgets($s);
  foreach (['HELO backend', 'MAIL FROM:<app@incidencias.local>', 'RCPT TO:<soporte@incidencias.local>', 'DATA'] as $c) {
    fwrite($s, "$c\r\n"); fgets($s);
  }
  fwrite($s, "From: app@incidencias.local\r\nTo: soporte@incidencias.local\r\n"
    . "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n"
    . "Content-Type: text/plain; charset=utf-8\r\n\r\n$cuerpo\r\n.\r\n");
  fgets($s); fwrite($s, "QUIT\r\n"); fclose($s);
  return true;
}

try {
  $db = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST'), getenv('DB_PORT'), getenv('MARIADB_DATABASE')),
    getenv('MARIADB_USER'), getenv('MARIADB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_FOUND_ROWS => true]);
  $r = new Redis();
  $r->connect(getenv('REDIS_HOST'), (int)getenv('REDIS_PORT'));
} catch (Throwable $e) { out(503, ['error' => 'Servicio no disponible']); }

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$m = $_SERVER['REQUEST_METHOD'];
$in = json_decode(file_get_contents('php://input'), true) ?? [];
[$res, $id] = array_pad(explode('/', $path), 2, null);

if ($path === 'health')
  out(200, ['status' => 'ok', 'db' => $db->query('SELECT 1')->fetchColumn() == 1, 'redis' => (bool)$r->ping()]);
if ($res !== 'incidencias') out(404, ['error' => 'No encontrado']);

if ($m === 'GET' && !$id) {
  $r->incr('contador_consultas');
  if ($c = $r->get('incidencias:lista')) { header('X-Cache: HIT'); echo $c; exit; }
  $j = json_encode($db->query('SELECT * FROM incidencias ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC));
  $r->setex('incidencias:lista', 30, $j);
  header('X-Cache: MISS'); echo $j; exit;
}

if ($m === 'POST' || $m === 'PUT') {
  $t = trim($in['titulo'] ?? ''); $d = $in['descripcion'] ?? null;
  $e = $in['estado'] ?? 'abierta'; $p = $in['prioridad'] ?? 'media';
  if (mb_strlen($t) < 3 || mb_strlen($t) > 100
      || !in_array($e, ['abierta', 'en_proceso', 'resuelta'])
      || !in_array($p, ['baja', 'media', 'alta']))
    out(422, ['error' => 'Datos inválidos']);
}

if ($m === 'POST') {
  $db->prepare('INSERT INTO incidencias (titulo,descripcion,estado,prioridad) VALUES (?,?,?,?)')->execute([$t, $d, $e, $p]);
  $r->del('incidencias:lista');
  $nid = (int)$db->lastInsertId();
  enviar_correo("Nueva incidencia #$nid: $t", "Título: $t\nPrioridad: $p\nEstado: $e\nDescripción: " . ($d ?? '(sin descripción)'));
  out(201, ['id' => $nid]);
}

if ($m === 'PUT' && $id) {
  $s = $db->prepare('UPDATE incidencias SET titulo=?,descripcion=?,estado=?,prioridad=? WHERE id=?');
  $s->execute([$t, $d, $e, $p, $id]);
  $r->del('incidencias:lista');
  out($s->rowCount() ? 200 : 404, ['ok' => (bool)$s->rowCount()]);
}

if ($m === 'DELETE' && $id) {
  $s = $db->prepare('DELETE FROM incidencias WHERE id=?');
  $s->execute([$id]);
  $r->del('incidencias:lista');
  out($s->rowCount() ? 200 : 404, ['ok' => (bool)$s->rowCount()]);
}

out(405, ['error' => 'Método no permitido']);
