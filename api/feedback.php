<?php
require 'storage.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(['error' => 'POST required'], 405);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) sendJson(['error' => 'Invalid request body'], 400);
$from = trim((string)($input['from'] ?? ''));
$to = trim((string)($input['to'] ?? ''));
$positive = trim((string)($input['positive'] ?? ''));
$suggestion = trim((string)($input['suggestion'] ?? ''));
if ($from === '' || $to === '' || $positive === '' || $suggestion === '') sendJson(['error' => 'Please complete all required feedback fields'], 400);
$feedbackFile = DATA_DIR . '/feedback.json';
$feedback = file_exists($feedbackFile) ? (json_decode(file_get_contents($feedbackFile), true) ?: []) : [];
$entry = [
    'id' => uniqid('feedback'),
    'from' => $from,
    'to' => $to,
    'rating' => trim((string)($input['rating'] ?? 'Good')),
    'positive' => $positive,
    'suggestion' => $suggestion,
    'createdAt' => date(DATE_ATOM),
];
$feedback[] = $entry;
if (@file_put_contents($feedbackFile, json_encode($feedback, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) cannotSave($feedbackFile);
sendJson(['success' => true, 'feedback' => $entry], 201);
