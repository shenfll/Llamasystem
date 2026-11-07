<?php
// Receives a QR check-in from the park popup (scripts/script.js).
// POST fields: mode (challenge|ultra), park, email, initials, website (spam trap; must be empty)
// Responds with JSON: {"ok": true} or {"ok": false, "error": "..."}
declare(strict_types=1);
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reply(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    reply(405, ['ok' => false, 'error' => 'Use POST.']);
}

// Bots tend to fill every field; people never see this one.
if (!empty($_POST['website'])) {
    reply(200, ['ok' => true]);
}

$mode = (string)($_POST['mode'] ?? 'challenge');
$park = (string)($_POST['park'] ?? '');
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$initials = strtoupper(trim((string)($_POST['initials'] ?? '')));

if (!in_array($mode, LLAMA_MODES, true)) {
    reply(400, ['ok' => false, 'error' => 'Unknown challenge type.']);
}
if (!array_key_exists($park, LLAMA_PARKS)) {
    reply(400, ['ok' => false, 'error' => 'Unknown location. Please scan the QR code again.']);
}
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    reply(400, ['ok' => false, 'error' => 'Please enter a valid email address.']);
}
if (!preg_match('/^[A-Z][A-Z.\- ]{0,9}$/', $initials)) {
    reply(400, ['ok' => false, 'error' => 'Please enter your initials (letters only, up to 10).']);
}

try {
    llama_append([
        't' => gmdate('Y-m-d\TH:i:s\Z'),
        'mode' => $mode,
        'park' => $park,
        'email' => $email,
        'initials' => $initials,
        'src' => 'web',
    ]);
} catch (Throwable $e) {
    error_log('LLAMA check-in save failed: ' . $e->getMessage());
    reply(500, ['ok' => false, 'error' => 'Your check-in could not be saved. Please email llamasystem@gmail.com.']);
}

reply(200, ['ok' => true, 'park' => llama_park_name($park)]);
