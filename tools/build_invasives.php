<?php
declare(strict_types=1);

require dirname(__DIR__) . '/site/invasives/api/lib.php';

$webRoot = $argv[1] ?? (getenv('LLAMA_WEB') ?: ((getenv('HOME') ?: '') . '/llamasystem.org'));
if ($webRoot === '/llamasystem.org' || $webRoot === '') {
    fwrite(STDERR, "Set LLAMA_WEB or pass the DreamHost web root.\n");
    exit(1);
}
$targetDir = rtrim($webRoot, '/') . '/invasives';
$publicPhotos = $targetDir . '/photos/reports';
if (!is_dir($publicPhotos) && !mkdir($publicPhotos, 0755, true) && !is_dir($publicPhotos)) {
    fwrite(STDERR, "Cannot create public invasive photo folder.\n");
    exit(1);
}
@chmod($targetDir . '/photos', 0755);
@chmod($publicPhotos, 0755);

$dirs = invasives_dirs();
$approved = invasives_read_jsonl('approved.jsonl');
$seen = [];
$public = [];
foreach ($approved as $row) {
    $id = (string)($row['id'] ?? '');
    $species = (string)($row['species'] ?? '');
    $lat = filter_var($row['lat'] ?? null, FILTER_VALIDATE_FLOAT);
    $lng = filter_var($row['lng'] ?? null, FILTER_VALIDATE_FLOAT);
    if (!preg_match('/^[a-f0-9]{32}$/', $id) || isset($seen[$id]) || !in_array($species, INVASIVE_SPECIES, true)
        || $lat === false || $lng === false || $lat < 42.70 || $lat > 43.05 || $lng < -74.05 || $lng > -73.55) {
        continue;
    }
    $privatePhoto = $dirs['approved_photos'] . '/' . $id . '.jpg';
    if (!is_file($privatePhoto)) {
        continue;
    }
    $publicPhoto = $publicPhotos . '/' . $id . '.jpg';
    if (!is_file($publicPhoto) || filesize($publicPhoto) !== filesize($privatePhoto)) {
        $tmpPhoto = $publicPhoto . '.tmp';
        if (!copy($privatePhoto, $tmpPhoto)) {
            fwrite(STDERR, "Cannot copy approved photo $id.\n");
            exit(1);
        }
        @chmod($tmpPhoto, 0644);
        if (!rename($tmpPhoto, $publicPhoto)) {
            @unlink($tmpPhoto);
            fwrite(STDERR, "Cannot publish approved photo $id.\n");
            exit(1);
        }
    }
    $seen[$id] = true;
    $public[] = [
        'id' => $id,
        'date' => (string)($row['date'] ?? ''),
        'lat' => (float)$lat,
        'lng' => (float)$lng,
        'species' => $species,
        'notes' => (string)($row['notes'] ?? ''),
        'initials' => (string)($row['initials'] ?? ''),
        'photo' => 'photos/reports/' . $id . '.jpg',
    ];
}

$json = json_encode($public, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
$tmp = tempnam($targetDir, '.sightings-');
if ($tmp === false || file_put_contents($tmp, $json, LOCK_EX) !== strlen($json)) {
    if (is_string($tmp) && is_file($tmp)) { @unlink($tmp); }
    fwrite(STDERR, "Cannot write public invasive sightings list.\n");
    exit(1);
}
@chmod($tmp, 0644);
if (!rename($tmp, $targetDir . '/sightings.json')) {
    @unlink($tmp);
    fwrite(STDERR, "Cannot replace public invasive sightings list.\n");
    exit(1);
}

$approvedTips = invasives_read_jsonl('approved-tips.jsonl');
$seenTips = [];
$publicTips = [];
foreach ($approvedTips as $row) {
    $id = (string)($row['id'] ?? '');
    $species = (string)($row['species'] ?? '');
    $tip = trim((string)($row['tip'] ?? ''));
    $source = trim((string)($row['source'] ?? ''));
    $tipLength = preg_match_all('/./us', $tip);
    if (!preg_match('/^[a-f0-9]{32}$/', $id) || isset($seenTips[$id])
        || !in_array($species, INVASIVE_TIP_SPECIES, true) || $tip === '' || $tipLength === false || $tipLength > 800) {
        continue;
    }
    if ($source !== '') {
        $parts = parse_url($source);
        $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
        if (!is_array($parts) || !filter_var($source, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])) {
            $source = '';
        }
    }
    $seenTips[$id] = true;
    $publicTips[] = [
        'id' => $id,
        'species' => $species,
        'tip' => $tip,
        'source' => $source,
    ];
}

$tipsJson = json_encode($publicTips, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
$tipsTmp = tempnam($targetDir, '.tips-');
if ($tipsTmp === false || file_put_contents($tipsTmp, $tipsJson, LOCK_EX) !== strlen($tipsJson)) {
    if (is_string($tipsTmp) && is_file($tipsTmp)) { @unlink($tipsTmp); }
    fwrite(STDERR, "Cannot write public invasive tips list.\n");
    exit(1);
}
@chmod($tipsTmp, 0644);
if (!rename($tipsTmp, $targetDir . '/tips.json')) {
    @unlink($tipsTmp);
    fwrite(STDERR, "Cannot replace public invasive tips list.\n");
    exit(1);
}
