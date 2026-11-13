<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function inv_tip_reply(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    inv_tip_reply(405, ['ok' => false, 'error' => 'Use POST to send a tip.']);
}

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    $originHost = strtolower((string)parse_url($origin, PHP_URL_HOST));
    $allowedHosts = ['www.llamasystem.org', 'llamasystem.org', 'localhost', '127.0.0.1'];
    if (!in_array($originHost, $allowedHosts, true)) {
        inv_tip_reply(403, ['ok' => false, 'error' => 'This tip could not be accepted.']);
    }
}

// A filled honeypot is accepted but silently discarded.
if (!empty($_POST['website'])) {
    inv_tip_reply(200, ['ok' => true, 'message' => 'Thank you.']);
}

$species = (string)($_POST['species'] ?? '');
$tip = trim(strip_tags((string)($_POST['tip'] ?? '')));
$source = trim((string)($_POST['source'] ?? ''));

if (!in_array($species, INVASIVE_TIP_SPECIES, true)) {
    inv_tip_reply(400, ['ok' => false, 'error' => 'Choose a plant from the list.']);
}
$tipLength = preg_match_all('/./us', $tip);
if ($tip === '' || $tipLength === false || $tipLength > 800) {
    inv_tip_reply(400, ['ok' => false, 'error' => 'Enter a tip of 1 to 800 characters.']);
}
if (strlen($source) > 500) {
    inv_tip_reply(400, ['ok' => false, 'error' => 'Keep the source link under 500 characters.']);
}
if ($source !== '') {
    $parts = parse_url($source);
    $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
    if (!is_array($parts) || !filter_var($source, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)
        || isset($parts['user']) || isset($parts['pass'])) {
        inv_tip_reply(400, ['ok' => false, 'error' => 'Use a valid http:// or https:// source link.']);
    }
}

$id = bin2hex(random_bytes(16));
$record = [
    'id' => $id,
    'submitted_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'species' => $species,
    'tip' => $tip,
    'source' => $source,
];
try {
    invasives_append_jsonl('pending-tips.jsonl', $record);
} catch (Throwable $e) {
    error_log('LLAMA invasive tip save failed: ' . $e->getMessage());
    inv_tip_reply(500, ['ok' => false, 'error' => 'The tip could not be saved. Please try again later.']);
}

inv_tip_reply(200, ['ok' => true, 'message' => 'Tip saved for adult review. It will appear in the guide if approved.']);
