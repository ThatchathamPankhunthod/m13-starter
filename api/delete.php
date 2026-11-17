<?php
require 'storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'POST required'], 405);
}

// Simple delete password for this demo. Change this value to your own code.
$deletePassword = '13112026';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) sendJson(['error' => 'Invalid request body'], 400);
$password = $input['password'] ?? '';
$kind = $input['kind'] ?? '';
$portfolioId = $input['portfolioId'] ?? '';
$itemId = $input['itemId'] ?? '';

if (!is_string($password) || $password !== $deletePassword) {
    sendJson(['error' => 'Incorrect delete password. Nothing was deleted.'], 403);
}
if (!in_array($kind, ['portfolio', 'item'], true) || !is_string($portfolioId) || $portfolioId === '') {
    sendJson(['error' => 'Invalid delete request'], 400);
}

$portfolios = readPortfolios();
$portfolioIndex = null;
foreach ($portfolios as $i => $portfolio) {
    if (($portfolio['id'] ?? '') === $portfolioId) { $portfolioIndex = $i; break; }
}
if ($portfolioIndex === null) sendJson(['error' => 'Portfolio not found'], 404);

if ($kind === 'portfolio') {
    array_splice($portfolios, $portfolioIndex, 1);
    writePortfolios($portfolios);
    sendJson(['success' => true, 'deleted' => 'portfolio']);
}

if (!is_string($itemId) || $itemId === '') sendJson(['error' => 'Item ID is required'], 400);
$items = $portfolios[$portfolioIndex]['items'] ?? [];
$itemIndex = null;
foreach ($items as $i => $item) {
    if (($item['id'] ?? '') === $itemId) { $itemIndex = $i; break; }
}
if ($itemIndex === null) sendJson(['error' => 'Portfolio Item not found'], 404);
array_splice($items, $itemIndex, 1);
$portfolios[$portfolioIndex]['items'] = $items;
writePortfolios($portfolios);
sendJson(['success' => true, 'deleted' => 'item']);
