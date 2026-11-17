<?php
require 'storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'POST required'], 405);
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) sendJson(['error' => 'Invalid request body'], 400);
$portfolioId = $input['portfolioId'] ?? '';
$data = $input['data'] ?? null;
$fileHash = $input['fileHash'] ?? '';
if (!is_string($portfolioId) || $portfolioId === '' || !is_array($data)) {
    sendJson(['error' => 'Portfolio and valid JSON data are required'], 400);
}
if (!is_string($fileHash) || !preg_match('/^(?:[a-f0-9]{64}|fnv-[a-f0-9]+)$/', $fileHash)) {
    sendJson(['error' => 'Invalid file fingerprint'], 400);
}

$portfolios = readPortfolios();
$targetIndex = null;
foreach ($portfolios as $i => $portfolio) {
    if (($portfolio['id'] ?? '') === $portfolioId) { $targetIndex = $i; break; }
}
if ($targetIndex === null) sendJson(['error' => 'Portfolio not found'], 404);

// A persistent fingerprint prevents the exact same source file being imported again.
$importsFile = DATA_DIR . '/imports.json';
$imports = file_exists($importsFile) ? (json_decode(file_get_contents($importsFile), true) ?: []) : [];
$scopedFileHash = $portfolioId . ':' . $fileHash;
if (in_array($scopedFileHash, $imports, true)) {
    sendJson(['duplicateFile' => true, 'imported' => 0, 'skipped' => 0]);
}

// Accept a single Item, an array of Items, or an exported Portfolio containing items.
if (isset($data['items']) && is_array($data['items'])) $items = $data['items'];
elseif (array_is_list($data)) $items = $data;
else $items = [$data];

$existing = $portfolios[$targetIndex]['items'] ?? [];
$existingKeys = [];
foreach ($existing as $item) $existingKeys[] = itemKey($item);
$imported = 0;
$skipped = 0;
foreach ($items as $item) {
    if (!is_array($item) || trim((string)($item['title'] ?? '')) === '') {
        $skipped++;
        continue;
    }
    $key = itemKey($item);
    if (in_array($key, $existingKeys, true)) {
        $skipped++;
        continue;
    }
    $item['id'] = uniqid('item');
    $item['title'] = trim((string)$item['title']);
    $item['type'] = (string)($item['type'] ?? '');
    $item['date'] = (string)($item['date'] ?? '');
    $item['role'] = (string)($item['role'] ?? '');
    $item['description'] = (string)($item['description'] ?? '');
    $item['skills'] = is_array($item['skills'] ?? null) ? $item['skills'] : [];
    $item['reflection'] = is_array($item['reflection'] ?? null) ? $item['reflection'] : [];
    $item['evidence'] = is_array($item['evidence'] ?? null) ? $item['evidence'] : [];
    $existing[] = $item;
    $existingKeys[] = $key;
    $imported++;
}
$portfolios[$targetIndex]['items'] = $existing;
writePortfolios($portfolios);
{
    $imports[] = $scopedFileHash;
    file_put_contents($importsFile, json_encode(array_values(array_unique($imports)), JSON_PRETTY_PRINT));
}
sendJson(['duplicateFile' => false, 'imported' => $imported, 'skipped' => $skipped], 200);

function itemKey($item) {
    $normalized = [
        strtolower(trim((string)($item['title'] ?? ''))),
        strtolower(trim((string)($item['type'] ?? ''))),
        trim((string)($item['date'] ?? '')),
        strtolower(trim((string)($item['description'] ?? ''))),
    ];
    return hash('sha256', json_encode($normalized));
}
