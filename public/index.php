<?php

declare(strict_types=1);

use RuschBot\QuestionBank;
use RuschBot\TelegramBot;

require dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "RUSH Telegram bot is running.\n";
    exit;
}

$token = getenv('TELEGRAM_BOT_TOKEN') ?: getenv('BOT_TOKEN') ?: '';

if ($token === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "TELEGRAM_BOT_TOKEN env qiymati berilmagan.\n";
    exit;
}

$bankPath = getenv('QUESTION_BANK_CSV') ?: dirname(__DIR__) . '/data/questions.csv';
$rawBody = file_get_contents('php://input') ?: '';
$update = json_decode($rawBody, true);

if (!is_array($update)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Telegram update JSON bo'lishi kerak.\n";
    exit;
}

try {
    $bot = new TelegramBot($token, QuestionBank::fromCsv($bankPath));
    $bot->handleUpdate($update);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'internal_error']);
}
