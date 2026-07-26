<?php
// ============================================================
// api/chatbot.php
//
// Public endpoint (no login required) backing the AI chat widget shown on
// the landing page and the citizen dashboard. Conversation history and a
// simple per-session rate limit both live in $_SESSION — no new database
// table needed for this. Calls Claude via includes/ChatbotClient.php (raw
// cURL, matching this app's existing CimmClient.php pattern).
// ============================================================
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/ChatbotClient.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$userMessage = trim((string) ($body['message'] ?? ''));

if ($userMessage === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please type a message.']);
    exit;
}
if (mb_strlen($userMessage) > 1500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'That message is too long (max 1500 characters).']);
    exit;
}

// Per-browser-session rate limit — good enough to stop accidental/abusive
// bulk usage without standing up a dedicated table for a chat widget.
$rateLimit = 20;
$rateWindowSeconds = 600;
$now = time();
if (empty($_SESSION['chatbot_rl_start']) || ($now - $_SESSION['chatbot_rl_start']) > $rateWindowSeconds) {
    $_SESSION['chatbot_rl_start'] = $now;
    $_SESSION['chatbot_rl_count'] = 0;
}
if ($_SESSION['chatbot_rl_count'] >= $rateLimit) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'You\'ve sent a lot of messages recently — please wait a few minutes and try again.']);
    exit;
}
$_SESSION['chatbot_rl_count']++;

// Prior turns for this browser session, oldest first (no system message —
// ChatbotClient adds that itself on every call).
$history = $_SESSION['chatbot_history'] ?? [];

$result = ChatbotClient::sendMessage($history, $userMessage);

if (!$result['success']) {
    http_response_code($result['http_status'] > 0 ? 502 : 500);
    echo json_encode(['success' => false, 'message' => $result['message']]);
    exit;
}

$history[] = ['role' => 'user', 'content' => $userMessage];
$history[] = ['role' => 'assistant', 'content' => $result['reply']];
// Keep the last 5 turns only, so the session and the per-request payload
// sent to Claude both stay bounded no matter how long the chat runs.
$_SESSION['chatbot_history'] = array_slice($history, -10);

echo json_encode(['success' => true, 'reply' => $result['reply']]);
