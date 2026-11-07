<?php
// Rebuilds the public leaderboard file from the private check-ins.
// Run by cron (for example once a day). Writes initials and park names only,
// never emails.
//
// Usage: php tools/build_leaderboard.php OUTPUT_FILE
//   e.g. php ~/llamasystem-repo/tools/build_leaderboard.php ~/llamasystem.org/checkin/leaderboard.json
declare(strict_types=1);
require dirname(__DIR__) . '/site/checkin/lib.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
if ($argc !== 2) {
    fwrite(STDERR, "Usage: php build_leaderboard.php OUTPUT_FILE\n");
    exit(2);
}
$out = $argv[1];

$records = llama_read_all();
$data = [
    'updated' => gmdate('Y-m-d\TH:i:s\Z'),
    'total_parks' => count(LLAMA_CHALLENGE_PARKS),
    'finish_count' => LLAMA_FINISH_COUNT,
    'rows' => llama_leaderboard($records),
];
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Write to a temporary file and rename, so visitors never see a half-written file.
$tmp = $out . '.tmp';
if (file_put_contents($tmp, $json . "\n") === false || !rename($tmp, $out)) {
    fwrite(STDERR, "Could not write $out\n");
    exit(1);
}
chmod($out, 0644);
echo date('c') . ' leaderboard: ' . count($data['rows']) . ' people from ' . count($records) . " check-ins\n";
