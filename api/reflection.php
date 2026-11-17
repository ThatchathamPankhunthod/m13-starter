<?php
require 'storage.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(['error' => 'POST required'], 405);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) sendJson(['error' => 'Invalid request body'], 400);
$portfolioId = (string)($input['portfolioId'] ?? '');
$itemId = (string)($input['itemId'] ?? '');
$reflection = $input['reflection'] ?? null;
if ($portfolioId === '' || $itemId === '' || !is_array($reflection)) sendJson(['error' => 'Portfolio, Item and Reflection are required'], 400);
$portfolios = readPortfolios();
foreach ($portfolios as $pi => $portfolio) {
    if (($portfolio['id'] ?? '') !== $portfolioId) continue;
    foreach (($portfolio['items'] ?? []) as $ii => $item) {
        if (($item['id'] ?? '') !== $itemId) continue;
        $portfolios[$pi]['items'][$ii]['reflection'] = [
            'whatIDid' => trim((string)($reflection['whatIDid'] ?? '')),
            'whatILearned' => trim((string)($reflection['whatILearned'] ?? '')),
            'challenge' => trim((string)($reflection['challenge'] ?? '')),
            'nextStep' => trim((string)($reflection['nextStep'] ?? '')),
        ];
        writePortfolios($portfolios);
        sendJson(['success' => true, 'reflection' => $portfolios[$pi]['items'][$ii]['reflection']]);
    }
    sendJson(['error' => 'Item not found'], 404);
}
sendJson(['error' => 'Portfolio not found'], 404);
