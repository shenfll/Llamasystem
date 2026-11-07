<?php
// Shared code for the LLAMA check-in: park list, data location, storage.
// Used by submit.php, admin/report.php and the scripts in tools/.
// Direct web access to this file is blocked by checkin/.htaccess.
//
// Check-ins are stored one JSON object per line in checkins.jsonl, in a
// private folder outside the public website (default: ~/llama-data).
// Each record: {"t": ISO time or null, "mode": "challenge"|"ultra",
//               "park": key, "email": ..., "initials": ..., "src": ...}

// Every location that has a QR code. Keys match ?place= / ?point= in the
// site URLs and the keys in scripts/data.js.
const LLAMA_PARKS = [
    'clifton_commons' => 'Clifton Commons',
    'boyack' => 'Boyack Road Park',
    'kinns' => 'Kinns Road Park',
    'dwaas' => 'Dwaas Kill Nature Preserve',
    'garnsey' => 'Garnsey Park',
    'george_smith' => 'George T. Smith Park',
    'longkill' => 'Jewett Park',
    'ushers' => "Usher's Road State Park",
    'ushers_zim' => "Usher's Road State Park at Zim Smith Trail",
    'skano' => 'Skano Elementary',
    'hayes_liberty' => 'Hayes Nature Park at Liberty Way',
    'hayes_moe' => 'Hayes Nature Park at Moe Road',
    'northwoods_north' => 'North Woods Preserve at Shadow Wood Way',
    'northwoods_water_tower' => 'North Woods Preserve - near the water tower',
    'veterans' => 'Veterans Memorial Park',
    'woodcock' => 'Woodcock Preserve',
    'west_sky' => 'West Sky Natural Area',
    'chango' => 'Chango Elementary School',
    'zim_coons' => 'Zim Smith Trail at Coons Crossing',
    'whipple' => 'Vischer Ferry Preserve - Whipple Truss Bridge',
    'ferry_road' => 'Vischer Ferry Preserve - Ferry Road',
    'clutes' => "Vischer Ferry Preserve - Clute's Dry Dock",
    'towpathLock7Lookout' => 'Towpath - Lock 7 Lookout',
    'collins' => 'Collins Park',
    'karigon' => 'Karigon Elementary School',
    'okte' => 'Okte Elementary School',
    'orenda' => 'Orenda Elementary School',
    'shatekon' => 'Shatekon Elementary School',
    'tesago' => 'Tesago Elementary School',
    'balsam_moe' => 'Balsam Way Natural Area at Moe Road',
    'balsam_south' => 'Balsam Way Natural Area at South Entrance',
    'vanpatten_west' => 'Robert Van Patten Trail west end',
    'vanpatten_mid' => 'Robert Van Patten Trail at Carleton Road',
    'vanpatten_mid2' => 'Robert Van Patten Trail at LaCosta Drive',
    'vanpatten_east' => 'Robert Van Patten Trail East End',
    'barney' => 'Barney Road Park',
    'halfmoon_town' => 'Halfmoon Town Park',
    'harris' => 'Harris Park',
    'locust_lane' => 'Locust Lane/Clifton Knoll Park',
    'shenantaha' => 'Shenantaha Creek Park',
    'abele' => 'Abele Memorial Park',
    'burning_bush' => 'Burning Bush Park',
    'desrochers' => 'Peter Desrochers Memorial Trails',
    'clifton_gardens' => 'Clifton Gardens Park',
    'mohawk_landing' => 'Mohawk Landing Nature Preserve',
    'indian_mortars' => 'Indian Mortars Trail',
    'ballston_creek' => 'Ballston Creek Nature Preserve',
    'maryjane_row' => 'Mary Jane Row Dog Park',
    'settlers_hill' => 'Settlers Hill Natural Area',
    'summer_hill' => 'Summer Hill Natural Area',
    'calico' => 'Calico Colony Park',
    'bernini' => 'Bernini Drive Park',
    'kimberly' => 'Kimberly Court Park',
    'spirit' => 'Spirit Park',
    'arongen' => 'Arongen Elementary School',
];

// The 24 locations listed on challenge.html. Only these count toward the
// LLAMA Challenge (20 of 24 earns a patch).
const LLAMA_CHALLENGE_PARKS = [
    'abele', 'barney', 'burning_bush', 'clifton_commons', 'collins', 'dwaas',
    'garnsey', 'george_smith', 'hayes_moe', 'halfmoon_town', 'harris', 'kinns',
    'longkill', 'locust_lane', 'mohawk_landing', 'shenantaha', 'ushers',
    'veterans', 'towpathLock7Lookout', 'ferry_road', 'whipple', 'clutes',
    'woodcock', 'zim_coons',
];

const LLAMA_FINISH_COUNT = 20;
const LLAMA_MODES = ['challenge', 'ultra'];

function llama_home_dir(): string
{
    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $pw = posix_getpwuid(posix_geteuid());
        if ($pw && !empty($pw['dir'])) {
            return $pw['dir'];
        }
    }
    $home = getenv('HOME');
    if ($home) {
        return $home;
    }
    // DreamHost paths look like /home/<user>/llamasystem.org/checkin
    if (preg_match('#^(/home/[^/]+)#', __DIR__, $m)) {
        return $m[1];
    }
    throw new RuntimeException('Cannot find the home folder; set LLAMA_DATA_DIR.');
}

// Private folder for check-in data. Never inside the public website.
function llama_data_dir(): string
{
    $dir = getenv('LLAMA_DATA_DIR');
    if (!$dir) {
        $dir = llama_home_dir() . '/llama-data';
    }
    return rtrim($dir, '/');
}

function llama_checkins_file(): string
{
    return llama_data_dir() . '/checkins.jsonl';
}

// Append one record. Uses an exclusive lock so simultaneous check-ins
// cannot interleave.
function llama_append(array $record): void
{
    $dir = llama_data_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create data folder');
    }
    $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    $fh = fopen(llama_checkins_file(), 'a');
    if ($fh === false) {
        throw new RuntimeException('Cannot open check-in file');
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            throw new RuntimeException('Cannot lock check-in file');
        }
        fwrite($fh, $line);
        fflush($fh);
        flock($fh, LOCK_UN);
    } finally {
        fclose($fh);
    }
    @chmod(llama_checkins_file(), 0600);
}

// Turn a park as written in old data into its key. The old report page let
// people type parks by hand, so the old data has entries like "Abele",
// "Garnsey" or "Halfmoon Town Park" instead of "abele", "garnsey",
// "halfmoon_town". Matches keys and park names, ignoring capitalization.
// Unknown values are returned unchanged.
function llama_normalize_park(string $park): string
{
    static $lookup = null;
    if ($lookup === null) {
        $lookup = [];
        foreach (LLAMA_PARKS as $key => $name) {
            $lookup[strtolower($name)] = $key;
        }
        foreach (array_keys(LLAMA_PARKS) as $key) {
            $lookup[strtolower($key)] = $key;
        }
    }
    if (array_key_exists($park, LLAMA_PARKS)) {
        return $park;
    }
    $k = strtolower(trim($park));
    return $lookup[$k] ?? $park;
}

// Private settings about participants, kept in the data folder (never in
// the repository because it contains emails): ~/llama-data/people.json
// {
//   "same_person":   {"other@address": "main@address", ...},
//                    one person who checked in with more than one email
//   "finished":      {"main@address": "YYYY-MM-DD", ...},
//                    finishers verified by hand (photos, before the new
//                    check-in); listed in challenge.html, left off the leaderboard
//   "display_names": {"main@address": "Naomi", ...}
//                    shown on the leaderboard instead of initials
// }
function llama_people(): array
{
    static $people = null;
    if ($people === null) {
        $people = ['same_person' => [], 'finished' => [], 'display_names' => []];
        $file = llama_data_dir() . '/people.json';
        if (is_file($file)) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) {
                foreach (array_keys($people) as $k) {
                    if (isset($data[$k]) && is_array($data[$k])) {
                        $people[$k] = array_change_key_case($data[$k], CASE_LOWER);
                    }
                }
            } else {
                error_log('LLAMA: people.json is not valid JSON; ignoring it');
            }
        }
    }
    return $people;
}

// The address a person's check-ins are grouped under.
function llama_main_email(string $email): string
{
    $email = strtolower($email);
    return strtolower(llama_people()['same_person'][$email] ?? $email);
}

// Date (YYYY-MM-DD) for a finisher verified by hand, or null.
function llama_verified_finish(string $email): ?string
{
    return llama_people()['finished'][strtolower($email)] ?? null;
}

// Read every record. Skips blank or damaged lines rather than failing.
// 'email' is the person's main address (see same_person in people.json);
// 'email_raw' is the address they actually typed.
function llama_read_all(): array
{
    $file = llama_checkins_file();
    if (!is_file($file)) {
        return [];
    }
    $records = [];
    $fh = fopen($file, 'r');
    if ($fh === false) {
        return [];
    }
    flock($fh, LOCK_SH);
    while (($line = fgets($fh)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $rec = json_decode($line, true);
        if (is_array($rec) && isset($rec['email'], $rec['park'], $rec['mode'])) {
            $rec['park'] = llama_normalize_park((string)$rec['park']);
            $rec['email_raw'] = (string)$rec['email'];
            $rec['email'] = llama_main_email((string)$rec['email']);
            $records[] = $rec;
        }
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return $records;
}

function llama_park_name(string $key): string
{
    return LLAMA_PARKS[$key] ?? $key;
}

// Per-person totals for the regular challenge, keyed by main email.
// Returns: email => ['initials', 'parks' (challenge parks, sorted by name),
//                     'other' (non-challenge parks), 'last' (ISO time or null),
//                     'emails' (every address used)]
function llama_challenge_people(array $records): array
{
    $people = [];
    foreach ($records as $r) {
        if ($r['mode'] !== 'challenge') {
            continue;
        }
        $email = $r['email'];
        if (!isset($people[$email])) {
            $people[$email] = ['initials' => '', 'parks' => [], 'other' => [], 'last' => null, 'emails' => []];
        }
        $p =& $people[$email];
        $p['emails'][$r['email_raw'] ?? $email] = true;
        if (!empty($r['initials'])) {
            $p['initials'] = $r['initials'];
        }
        if (in_array($r['park'], LLAMA_CHALLENGE_PARKS, true)) {
            $p['parks'][$r['park']] = true;
        } else {
            $p['other'][$r['park']] = true;
        }
        if (!empty($r['t']) && ($p['last'] === null || $r['t'] > $p['last'])) {
            $p['last'] = $r['t'];
        }
        unset($p);
    }
    foreach ($people as &$p) {
        $p['parks'] = array_keys($p['parks']);
        $p['other'] = array_keys($p['other']);
        $p['emails'] = array_keys($p['emails']);
        usort($p['parks'], function ($a, $b) {
            return strcmp(llama_park_name($a), llama_park_name($b));
        });
    }
    unset($p);
    return $people;
}

// Public leaderboard rows: initials only, never emails.
// Shows people still working on the challenge: finishers (verified by hand
// in people.json, or through the website check-in) are listed in the
// Finishers table instead.
// Sorted by number of challenge parks (most first), then initials.
// Rank is shared by people with the same count, as on the old leaderboard.
function llama_leaderboard(array $records): array
{
    $webFinished = [];
    foreach (llama_finishers($records) as $f) {
        if ($f['mode'] === 'challenge') {
            $webFinished[$f['email']] = true;
        }
    }
    $names = llama_people()['display_names'];
    $rows = [];
    foreach (llama_challenge_people($records) as $email => $p) {
        if (count($p['parks']) === 0 || isset($webFinished[$email]) || llama_verified_finish($email) !== null) {
            continue;
        }
        $shown = $names[$email] ?? ($p['initials'] !== '' ? $p['initials'] : '?');
        $rows[] = [
            'initials' => $shown,
            'count' => count($p['parks']),
            'parks' => array_map('llama_park_name', $p['parks']),
            'last' => $p['last'] ? substr($p['last'], 0, 10) : null,
        ];
    }
    usort($rows, function ($a, $b) {
        return [$b['count'], $a['initials']] <=> [$a['count'], $b['initials']];
    });
    $rank = 0;
    $prev = null;
    foreach ($rows as &$row) {
        if ($row['count'] !== $prev) {
            $rank++;
            $prev = $row['count'];
        }
        $row['rank'] = $rank;
    }
    unset($row);
    return $rows;
}

// Ultra challenge: the most distinct challenge parks each person visited
// on a single day (America/New_York). 20 in one day completes it.
function llama_ultra_people(array $records): array
{
    $tz = new DateTimeZone('America/New_York');
    $byDay = [];
    $initials = [];
    foreach ($records as $r) {
        if ($r['mode'] !== 'ultra' || empty($r['t'])) {
            continue;
        }
        if (!in_array($r['park'], LLAMA_CHALLENGE_PARKS, true)) {
            continue;
        }
        try {
            $day = (new DateTime($r['t']))->setTimezone($tz)->format('Y-m-d');
        } catch (Exception $e) {
            continue;
        }
        $byDay[$r['email']][$day][$r['park']] = true;
        if (!empty($r['initials'])) {
            $initials[$r['email']] = $r['initials'];
        }
    }
    $people = [];
    foreach ($byDay as $email => $days) {
        $bestDay = null;
        $best = 0;
        foreach ($days as $day => $parks) {
            if (count($parks) > $best) {
                $best = count($parks);
                $bestDay = $day;
            }
        }
        $people[$email] = [
            'initials' => $initials[$email] ?? '',
            'best_day' => $bestDay,
            'best_count' => $best,
            'days' => array_map('count', $days),
        ];
    }
    uasort($people, function ($a, $b) {
        return $b['best_count'] <=> $a['best_count'];
    });
    return $people;
}

// Local (America/New_York) date for an ISO timestamp, as YYYY-MM-DD.
function llama_local_date(?string $t): ?string
{
    if (!$t) {
        return null;
    }
    try {
        return (new DateTime($t))->setTimezone(new DateTimeZone('America/New_York'))->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

// People who completed a challenge through the website check-in.
// Regular challenge: the check-in that brought them to 20 distinct challenge
// parks. Ultra: 20 distinct challenge parks on one day.
// Only completions made with the new check-in (src "web") count here; earlier
// finishers were verified by hand and are listed in challenge.html already.
// Returns a list of ['key', 'mode', 'email', 'initials', 'date', 'parks'],
// oldest first. 'key' is stable, for remembering who was already announced.
function llama_finishers(array $records): array
{
    $finishers = [];
    $seen = [];      // email => [park => true], regular challenge
    $done = [];      // email => true once finished, regular challenge
    $ultraDay = [];  // email => day => [park => true], web check-ins only
    $ultraDone = [];
    foreach ($records as $r) {
        if (!in_array($r['park'], LLAMA_CHALLENGE_PARKS, true)) {
            continue;
        }
        $email = $r['email'];
        $isWeb = ($r['src'] ?? '') === 'web' && !empty($r['t']);
        if ($r['mode'] === 'challenge') {
            $seen[$email][$r['park']] = true;
            if (!isset($done[$email]) && count($seen[$email]) >= LLAMA_FINISH_COUNT) {
                $done[$email] = true;
                if ($isWeb && llama_verified_finish($email) === null) {
                    $finishers[] = [
                        'key' => 'challenge:' . $email,
                        'mode' => 'challenge',
                        'email' => $email,
                        'initials' => $r['initials'] ?? '',
                        'date' => llama_local_date($r['t']),
                        'parks' => count($seen[$email]),
                    ];
                }
            }
        } elseif ($r['mode'] === 'ultra' && $isWeb) {
            $day = llama_local_date($r['t']);
            $ultraDay[$email][$day][$r['park']] = true;
            if (!isset($ultraDone[$email]) && count($ultraDay[$email][$day]) >= LLAMA_FINISH_COUNT) {
                $ultraDone[$email] = true;
                $finishers[] = [
                    'key' => 'ultra:' . $email,
                    'mode' => 'ultra',
                    'email' => $email,
                    'initials' => $r['initials'] ?? '',
                    'date' => $day,
                    'parks' => count($ultraDay[$email][$day]),
                ];
            }
        }
    }
    return $finishers;
}

function llama_h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
