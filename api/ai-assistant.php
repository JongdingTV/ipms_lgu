<?php
// ============================================================
// api/ai-assistant.php — AI Project Assistant (Admin)
//
// Decision-support chat backed by a live, freshly-queried data snapshot
// (includes/AiAssistantContext.php) injected into Gemini's system prompt on
// every turn, so the model only ever reasons over figures this file just
// queried a moment ago — it never invents project/contractor data. Read-only
// by design: this endpoint has no write actions, and the persona explicitly
// forbids the assistant from claiming to approve, reject, or modify anything.
// Conversation history lives in $_SESSION, same pattern as the citizen-facing
// api/chatbot.php, but namespaced separately so the two never share a counter.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/workflow.php';
require_once __DIR__ . '/../includes/ContractorScoring.php';
require_once __DIR__ . '/../includes/ProjectHealth.php';
require_once __DIR__ . '/../includes/AiAssistantContext.php';
require_once __DIR__ . '/../includes/ChatbotClient.php';

apiHeaders();
requireAnyRole(['super_admin', 'admin']);
requireCsrfProtection();

$db = getDB();
projectWorkflowEnsureRoleConnectionTables($db);

function aiAssistantHandleAsk(PDO $db): void
{
    $body = requestBody();
    $userMessage = trim((string) ($body['message'] ?? ''));

    if ($userMessage === '') {
        respond(['success' => false, 'message' => 'Please type a message.'], 422);
    }
    if (mb_strlen($userMessage) > 1000) {
        respond(['success' => false, 'message' => 'That message is too long (max 1000 characters).'], 422);
    }

    // Per-session rate limit, same shape as the citizen chatbot's own limit
    // but tracked under its own session keys.
    $rateLimit = 30;
    $rateWindowSeconds = 600;
    $now = time();
    if (empty($_SESSION['ai_assistant_rl_start']) || ($now - $_SESSION['ai_assistant_rl_start']) > $rateWindowSeconds) {
        $_SESSION['ai_assistant_rl_start'] = $now;
        $_SESSION['ai_assistant_rl_count'] = 0;
    }
    if ($_SESSION['ai_assistant_rl_count'] >= $rateLimit) {
        respond(['success' => false, 'message' => "You've sent a lot of messages recently — please wait a few minutes and try again."], 429);
    }
    $_SESSION['ai_assistant_rl_count']++;

    $history = $_SESSION['ai_assistant_history'] ?? [];
    $systemPrompt = aiAssistantSystemPrompt(aiAssistantBuildContext($db));

    $result = ChatbotClient::sendMessage($history, $userMessage, $systemPrompt);

    if (!$result['success']) {
        respond(['success' => false, 'message' => $result['message']], $result['http_status'] > 0 ? 502 : 500);
    }

    $history[] = ['role' => 'user', 'content' => $userMessage];
    $history[] = ['role' => 'assistant', 'content' => $result['reply']];
    // Keep the last 5 turns only, same bound as the citizen chatbot.
    $_SESSION['ai_assistant_history'] = array_slice($history, -10);

    respond(['success' => true, 'reply' => $result['reply']]);
}

function aiAssistantHandleHistory(): void
{
    respond(['success' => true, 'history' => $_SESSION['ai_assistant_history'] ?? []]);
}

function aiAssistantHandleReset(): void
{
    unset($_SESSION['ai_assistant_history']);
    respond(['success' => true]);
}

// ── Dispatch ────────────────────────────────────────────────────────────
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = (string) ($_GET['action'] ?? '');

    if ($method === 'POST' && $action === 'ask') {
        aiAssistantHandleAsk($db);
    } elseif ($method === 'GET' && $action === 'history') {
        aiAssistantHandleHistory();
    } elseif ($method === 'POST' && $action === 'reset') {
        aiAssistantHandleReset();
    } else {
        respond(['success' => false, 'message' => 'Unknown action.'], 404);
    }
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
}
