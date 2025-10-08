<?php
header('Content-Type: application/json; charset=utf-8');

$FILE = __DIR__ . '/rsvp.json';
if (!file_exists($FILE)) {
  @file_put_contents($FILE, "[]");
}

function read_list($FILE) {
  $fp = @fopen($FILE, 'r');
  if (!$fp) return [];
  @flock($fp, LOCK_SH);
  $data = stream_get_contents($fp);
  @flock($fp, LOCK_UN);
  fclose($fp);
  $arr = json_decode($data ?? '[]', true);
  return is_array($arr) ? $arr : [];
}

function write_list($FILE, $arr) {
  $fp = @fopen($FILE, 'c+');
  if (!$fp) return false;
  @flock($fp, LOCK_EX);
  ftruncate($fp, 0);
  rewind($fp);
  fwrite($fp, json_encode(array_values($arr), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  fflush($fp);
  @flock($fp, LOCK_UN);
  fclose($fp);
  return true;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  echo json_encode(read_list($FILE), JSON_UNESCAPED_UNICODE);
  exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?? '', true);
if (!is_array($input)) $input = [];

if ($method === 'POST') {
  $list = read_list($FILE);
  $name = trim((string)($input['name'] ?? ''));
  if ($name === '') { http_response_code(400); echo '[]'; exit; }
  $status = ((string)($input['status'] ?? '')) === 'yes' ? 'yes' : 'no';
  $emoji = (string)($input['emoji'] ?? '🙂');
  $photo = (string)($input['photo'] ?? '');
  $lname = mb_strtolower($name);

  $list = array_values(array_filter($list, function($x) use ($lname) {
    return mb_strtolower($x['name'] ?? '') !== $lname;
  }));
  $list[] = [
    'name' => $name,
    'status' => $status,
    'emoji' => $emoji,
    'photo' => $photo
  ];
  write_list($FILE, $list);
  echo json_encode($list, JSON_UNESCAPED_UNICODE);
  exit;
}

if ($method === 'DELETE') {
  $list = read_list($FILE);
  $name = trim((string)($input['name'] ?? ''));
  if ($name !== '') {
    $lname = mb_strtolower($name);
    $list = array_values(array_filter($list, function($x) use ($lname) {
      return mb_strtolower($x['name'] ?? '') !== $lname;
    }));
    write_list($FILE, $list);
  }
  echo json_encode($list, JSON_UNESCAPED_UNICODE);
  exit;
}

http_response_code(405);
echo '[]';