<?php
// Rebuilds the public leaderboard file from the private check-ins, and
// optionally emails a notice for each new finisher.
// Run by tools/cron.sh every few minutes. The public file contains
// initials, park names and dates only, never emails.
//
// Usage: php tools/build_leaderboard.php OUTPUT_FILE [--notify ADDRESS]
//   e.g. php tools/build_leaderboard.php ~/llamasystem.org/checkin/leaderboard.json \
//          --notify llamasystem@gmail.com
//
// Prints nothing when there is nothing new, so cron logs stay small.
declare(strict_types=1);
require dirname(__DIR__) . '/site/checkin/lib.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$args = array_slice($argv, 1);
$notify = null;
$i = array_search('--notify', $args, true);
if ($i !== false) {
    $notify = $args[$i + 1] ?? null;
    array_splice($args, $i, 2);
    if (!$notify || !filter_var($notify, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "--notify needs an email address\n");
        exit(2);
    }
}
if (count($args) !== 1) {
    fwrite(STDERR, "Usage: php build_leaderboard.php OUTPUT_FILE [--notify ADDRESS]\n");
    exit(2);
}
$out = $args[0];

$records = llama_read_all();
$finishers = llama_finishers($records);

$data = [
    'updated' => gmdate('Y-m-d\TH:i:s\Z'),
    'total_parks' => count(LLAMA_CHALLENGE_PARKS),
    'finish_count' => LLAMA_FINISH_COUNT,
    // Newest first, initials only.
    'finishers' => array_reverse(array_map(function ($f) {
        return ['initials' => $f['initials'] !== '' ? $f['initials'] : '?', 'mode' => $f['mode'], 'date' => $f['date']];
    }, $finishers)),
    'rows' => llama_leaderboard($records),
];
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

// Only rewrite the file when the content changed (ignoring the timestamp),
// so the "last updated" date means the last real change.
$old = is_file($out) ? json_decode((string)file_get_contents($out), true) : null;
$changed = true;
if (is_array($old)) {
    $cmpOld = $old;
    $cmpNew = $data;
    unset($cmpOld['updated'], $cmpNew['updated']);
    $changed = $cmpOld != $cmpNew;
}
if ($changed) {
    // Write to a temporary file and rename, so visitors never see a half-written file.
    $tmp = $out . '.tmp';
    if (file_put_contents($tmp, $json) === false || !rename($tmp, $out)) {
        fwrite(STDERR, "Could not write $out\n");
        exit(1);
    }
    chmod($out, 0644);
    echo date('c') . ' leaderboard updated: ' . count($data['rows']) . ' people, '
        . count($finishers) . ' website finishers, ' . count($records) . " check-ins\n";
}

if ($notify === null) {
    exit(0);
}

// Email one notice per new finisher. Remember who was announced in the
// private data folder so nobody is announced twice.
$stateFile = llama_data_dir() . '/notified.json';
$notified = is_file($stateFile) ? (json_decode((string)file_get_contents($stateFile), true) ?: []) : [];
$first = !is_file($stateFile);
foreach ($finishers as $f) {
    if (isset($notified[$f['key']])) {
        continue;
    }
    if ($first) {
        // First run: record existing finishers without sending a flood of email.
        $notified[$f['key']] = 'existing';
        continue;
    }
    $challenge = $f['mode'] === 'ultra' ? 'Ultra LLAMA Challenge' : 'LLAMA Challenge';
    $subject = "New $challenge finisher: " . ($f['initials'] ?: $f['email']);
    $body = "Someone just completed the $challenge on llamasystem.org.\n\n"
        . "Initials: {$f['initials']}\n"
        . "Email: {$f['email']}\n"
        . "Completed: {$f['date']}\n"
        . "Challenge parks: {$f['parks']} of " . count(LLAMA_CHALLENGE_PARKS) . "\n\n"
        . "They now appear on the Finishers list on https://www.llamasystem.org/challenge.html\n"
        . "Full details: https://www.llamasystem.org/checkin/admin/report.php\n\n"
        . "To send their patch, email them for their mailing address and the \$5 payment.\n";
    $headers = "From: LLAMA System <noreply@llamasystem.org>\r\n"
        . "Content-Type: text/plain; charset=utf-8";
    if (mail($notify, $subject, $body, $headers)) {
        $notified[$f['key']] = gmdate('Y-m-d\TH:i:s\Z');
        echo date('c') . " emailed $notify about {$f['key']}\n";
    } else {
        fwrite(STDERR, date('c') . " could not email $notify about {$f['key']}; will retry\n");
    }
}
if (file_put_contents($stateFile, json_encode($notified, JSON_PRETTY_PRINT) . "\n") === false) {
    fwrite(STDERR, "Could not write $stateFile\n");
    exit(1);
}
chmod($stateFile, 0600);
