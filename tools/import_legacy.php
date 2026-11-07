<?php
// One-time import of the check-ins saved by the old Node servers.
//
// Usage: php tools/import_legacy.php CHALLENGE_DATABASE_JSON [ULTRA_DATABASE_JSON]
//   e.g. php ~/llamasystem-repo/tools/import_legacy.php \
//          ~/database-server/database.json ~/ultra-database-server/database.json
//
// Old challenge format: {"users": {"places": {email: [park, ...]}, "initials": {email: "AB"}}}
//   (no dates were saved, so these records get "t": null)
// Old ultra format:     {"users": {"places": {email: {park: epoch_ms}}, "initials": {...}}}
//
// Refuses to run if records from the old system were already imported.
declare(strict_types=1);
require dirname(__DIR__) . '/site/checkin/lib.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "Usage: php import_legacy.php CHALLENGE_JSON [ULTRA_JSON]\n");
    exit(2);
}

foreach (llama_read_all() as $r) {
    if (($r['src'] ?? '') === 'legacy') {
        fwrite(STDERR, "Old check-ins were already imported into " . llama_checkins_file() . "; nothing done.\n");
        exit(1);
    }
}

function load_legacy(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        fwrite(STDERR, "Cannot read $path\n");
        exit(1);
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['users']['places'])) {
        fwrite(STDERR, "$path is not in the old database format\n");
        exit(1);
    }
    return $data['users'];
}

$count = ['challenge' => 0, 'ultra' => 0];

$users = load_legacy($argv[1]);
foreach ($users['places'] as $email => $parks) {
    foreach ((array)$parks as $park) {
        llama_append([
            't' => null,
            'mode' => 'challenge',
            'park' => (string)$park,
            'email' => strtolower(trim((string)$email)),
            'initials' => strtoupper(trim((string)($users['initials'][$email] ?? ''))),
            'src' => 'legacy',
        ]);
        $count['challenge']++;
    }
}

if ($argc === 3) {
    $users = load_legacy($argv[2]);
    foreach ($users['places'] as $email => $parks) {
        foreach ((array)$parks as $park => $ms) {
            llama_append([
                't' => is_numeric($ms) ? gmdate('Y-m-d\TH:i:s\Z', (int)floor($ms / 1000)) : null,
                'mode' => 'ultra',
                'park' => (string)$park,
                'email' => strtolower(trim((string)$email)),
                'initials' => strtoupper(trim((string)($users['initials'][$email] ?? ''))),
                'src' => 'legacy',
            ]);
            $count['ultra']++;
        }
    }
}

echo "Imported {$count['challenge']} challenge and {$count['ultra']} ultra check-ins into " . llama_checkins_file() . "\n";
