<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function inv_reply(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    inv_reply(405, ['ok' => false, 'error' => 'Use POST to send a report.']);
}

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
    $allowedHosts = ['www.llamasystem.org', 'llamasystem.org', 'localhost', '127.0.0.1'];
    if (!in_array($originHost, $allowedHosts, true)) {
        inv_reply(403, ['ok' => false, 'error' => 'This report could not be accepted.']);
    }
}

// Hidden honeypot field. A filled field is treated as an accepted but discarded report.
if (!empty($_POST['website'])) {
    inv_reply(200, ['ok' => true, 'message' => 'Thank you.']);
}

$species = (string)($_POST['species'] ?? '');
$initials = strtoupper(trim((string)($_POST['initials'] ?? '')));
$notes = trim(strip_tags((string)($_POST['notes'] ?? '')));
$date = trim((string)($_POST['date'] ?? ''));
$latRaw = (string)($_POST['lat'] ?? '');
$lngRaw = (string)($_POST['lng'] ?? '');

if (!in_array($species, INVASIVE_SPECIES, true)) {
    inv_reply(400, ['ok' => false, 'error' => 'Choose a plant from the list.']);
}
if (!preg_match('/^[A-Z][A-Z.\- ]{0,9}$/', $initials)) {
    inv_reply(400, ['ok' => false, 'error' => 'Enter initials only (letters, up to 10).']);
}
if (strlen($notes) > 2000) {
    inv_reply(400, ['ok' => false, 'error' => 'Keep the description under 2,000 characters.']);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    inv_reply(400, ['ok' => false, 'error' => 'Choose the date you saw the plant.']);
}
$dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$dateValue || $dateValue->format('Y-m-d') !== $date || $dateValue > new DateTimeImmutable('today')) {
    inv_reply(400, ['ok' => false, 'error' => 'Enter a valid date that is not in the future.']);
}
if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
    inv_reply(400, ['ok' => false, 'error' => 'Pick the sighting location on the map.']);
}
$lat = (float)$latRaw;
$lng = (float)$lngRaw;
// Broad LLAMA-area bounds. The page also warns when a point is far from the parks.
if ($lat < 42.70 || $lat > 43.05 || $lng < -74.05 || $lng > -73.55) {
    inv_reply(400, ['ok' => false, 'error' => 'That location appears outside the LLAMA area. Please check the map pin.']);
}

if (!isset($_FILES['photo']) || !is_array($_FILES['photo']) || (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    inv_reply(400, ['ok' => false, 'error' => 'Add a clear plant photo before submitting.']);
}
$maxBytes = 5 * 1024 * 1024;
if ((int)($_FILES['photo']['size'] ?? 0) < 1 || (int)$_FILES['photo']['size'] > $maxBytes) {
    inv_reply(413, ['ok' => false, 'error' => 'The photo is too large. Choose a smaller photo and try again.']);
}
$raw = file_get_contents((string)$_FILES['photo']['tmp_name']);
if (!is_string($raw) || $raw === '') {
    inv_reply(400, ['ok' => false, 'error' => 'The photo could not be read. Please choose it again.']);
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->buffer($raw);
$imageInfo = @getimagesizefromstring($raw);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !is_array($imageInfo)) {
    inv_reply(400, ['ok' => false, 'error' => 'Use a JPEG, PNG, or WebP photo.']);
}
$width = (int)$imageInfo[0];
$height = (int)$imageInfo[1];
if ($width < 1 || $height < 1 || $width * $height > 20000000) {
    inv_reply(400, ['ok' => false, 'error' => 'The photo dimensions are too large. Choose a smaller photo.']);
}
if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
    error_log('LLAMA invasive submit requires PHP GD for safe photo re-encoding.');
    inv_reply(500, ['ok' => false, 'error' => 'Photo saving is temporarily unavailable. Please try again later.']);
}
$source = @imagecreatefromstring($raw);
if ($source === false) {
    inv_reply(400, ['ok' => false, 'error' => 'The photo could not be decoded. Please choose another image.']);
}
$scale = min(1.0, 900 / max($width, $height));
$outWidth = max(1, (int)round($width * $scale));
$outHeight = max(1, (int)round($height * $scale));
$destination = imagecreatetruecolor($outWidth, $outHeight);
$white = imagecolorallocate($destination, 255, 255, 255);
imagefill($destination, 0, 0, $white);
imagecopyresampled($destination, $source, 0, 0, 0, 0, $outWidth, $outHeight, $width, $height);
ob_start();
$encoded = imagejpeg($destination, null, 78);
$jpeg = ob_get_clean();
imagedestroy($source);
imagedestroy($destination);
if (!$encoded || !is_string($jpeg) || $jpeg === '') {
    inv_reply(500, ['ok' => false, 'error' => 'The photo could not be prepared. Please try again.']);
}

$id = bin2hex(random_bytes(16));
try {
    $dirs = invasives_dirs();
    $photoPath = $dirs['pending_photos'] . '/' . $id . '.jpg';
    $tmpPath = tempnam($dirs['pending_photos'], '.upload-');
    if ($tmpPath === false || file_put_contents($tmpPath, $jpeg, LOCK_EX) !== strlen($jpeg)) {
        if (is_string($tmpPath) && is_file($tmpPath)) { @unlink($tmpPath); }
        throw new RuntimeException('Could not save private photo.');
    }
    @chmod($tmpPath, 0600);
    if (!rename($tmpPath, $photoPath)) {
        @unlink($tmpPath);
        throw new RuntimeException('Could not save private photo.');
    }
    $record = [
        'id' => $id,
        'submitted_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'date' => $date,
        'lat' => $lat,
        'lng' => $lng,
        'species' => $species,
        'notes' => $notes,
        'initials' => $initials,
        'photo' => $id . '.jpg',
    ];
    invasives_append_jsonl('pending.jsonl', $record);
} catch (Throwable $e) {
    if (isset($photoPath) && is_file($photoPath)) { @unlink($photoPath); }
    error_log('LLAMA invasive report save failed: ' . $e->getMessage());
    inv_reply(500, ['ok' => false, 'error' => 'The report could not be saved. Please try again later.']);
}

inv_reply(200, ['ok' => true, 'message' => 'Report saved for adult review. It will appear on the map if approved.']);
