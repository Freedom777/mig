<?php

// --- Шаг 1: подтверждение Webhook при настройке в Meta ---
$verify_token = "secretgardenparameter1@2!3"; // придумай сам и укажи тот же в Meta

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (
        isset($_GET['hub_mode']) &&
        $_GET['hub_mode'] === 'subscribe' &&
        isset($_GET['hub_verify_token']) &&
        $_GET['hub_verify_token'] === $verify_token
    ) {
        echo $_GET['hub_challenge']; // вернуть challenge
        exit;
    }
    http_response_code(403);
    exit;
}

// --- Шаг 2: обработка входящих сообщений ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . ' ' . $input . PHP_EOL, FILE_APPEND);

    // Пример: вытащим текст сообщения
    $message = $data['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'] ?? null;
    $from    = $data['entry'][0]['changes'][0]['value']['messages'][0]['from'] ?? null;

    if ($message && $from) {
        // Допустим, просто логируем
        file_put_contents('whatsapp_messages.txt', 'From ' . $from . ': '. $message . PHP_EOL, FILE_APPEND);
    }

    echo 'EVENT_RECEIVED';
    exit;
}

http_response_code(404);
