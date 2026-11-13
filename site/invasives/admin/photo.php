<?php
declare(strict_types=1);
require __DIR__ . '/../api/lib.php';

$id = (string)($_GET['id'] ?? '');
$row = invasives_find_pending($id);
if ($row === null) {
    http_response_code(404);
    exit;
}
$dirs = invasives_dirs();
$path = $dirs['pending_photos'] . '/' . $id . '.jpg';
if (!is_file($path)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/jpeg');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . (string)filesize($path));
readfile($path);
