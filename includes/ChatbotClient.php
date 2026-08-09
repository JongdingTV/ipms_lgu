<?php
/**
 * Outbound client: the IPMS chatbot widget (landing page + citizen dashboard)
 * calls Google's Gemini API directly via the Generative Language REST API
 * (raw cURL, same pattern as CimmClient.php — this app has no Composer/
 * vendor setup anywhere). Gemini was chosen over Claude specifically because
 * it has a genuinely free tier (no credit card) suitable for a school
 * capstone project — see GEMINI_API_KEY in .env.
 */
class ChatbotClient
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const MAX_OUTPUT_TOKENS = 1024;
    private const TIMEOUT_SECONDS = 25;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are the IPMS Assistant, a friendly guide for the Quezon City Infrastructure
Project Management System (IPMS) — the public portal for tracking Quezon
City government infrastructure projects (roads, drainage, public buildings,
parks, and more). You help both first-time visitors and logged-in citizens.

You help people:
- Understand what IPMS is and what they can do here.
- Navigate the site: the Transparency Dashboard (budgets and progress across
  all projects), Project Status (browse/search individual projects), Submit
  Feedback (report a concern or complaint, optionally with photos and a map
  pin), and Track Complaints (see the status of reports they already
  submitted).
- Understand general terms: project categories (Roads and Bridges, Drainage
  and Flood Control, Water Supply, Public Buildings and Facilities, Street
  Lighting, Parks and Recreation), project statuses (e.g. planning, bidding,
  active, delayed, completed), and why account/ID verification matters for
  citizen accounts.

Hard limits — do not break these:
- You do NOT have live access to the real project database, a citizen's
  account, or their submitted reports. Never invent a specific project name,
  status, budget figure, date, or complaint outcome. If asked about a
  SPECIFIC project or the status of a SPECIFIC complaint, say you can't look
  that up directly and point them to the right page (Project Status or Track
  Complaints) to check it themselves.
- You cannot submit feedback, edit an account, or take any action on the
  user's behalf — only explain how, then point to the relevant page/button.
- Keep answers short, warm, and in plain language a general resident would
  use. Reply in whichever language the user writes in (English or
  Filipino/Taglish).
- Do not use markdown formatting (no **bold**, no # headers, no - bullet
  lists) — replies display as plain text in a simple chat bubble.
PROMPT;

    public static function isEnabled(): bool
    {
        return defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '';
    }

    /**
     * @param list<array{role:string,content:string}> $history Prior turns, oldest first ('user'|'assistant').
     * @param ?string $systemPrompt Overrides the citizen-widget persona above — used by the
     *        Admin AI Project Assistant (api/ai-assistant.php) to swap in its own decision-support
     *        persona plus a freshly-queried data snapshot, while still sharing this same HTTP/error
     *        handling plumbing.
     * @return array{success:bool,reply:?string,message:string,http_status:int}
     */
    public static function sendMessage(array $history, string $userMessage, ?string $systemPrompt = null): array
    {
        // Gemini's "contents" shape differs from a generic {role, content}
        // history: role is 'user'|'model' (not 'assistant'), and each turn's
        // text sits under parts[].text rather than a plain string.
        $contents = [];
        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'model' : 'user';
            $contents[] = ['role' => $role, 'parts' => [['text' => (string) ($turn['content'] ?? '')]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        $body = [
            'system_instruction' => ['parts' => ['text' => $systemPrompt ?? self::SYSTEM_PROMPT]],
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => self::MAX_OUTPUT_TOKENS],
        ];

        return self::callGemini($body);
    }

    /**
     * Multimodal call: one image + one text prompt, no conversation history.
     * Used by includes/IdVerification.php to read a citizen's uploaded ID
     * photo during registration (name/address/document-type extraction).
     * Requests JSON-only output so the caller can json_decode() the reply
     * directly — the prompt itself must still ask for a specific shape,
     * responseMimeType only forces syntactically-valid JSON, not the schema.
     *
     * @param string $base64Image Raw image bytes, base64-encoded (no data: URI prefix).
     * @param string $mimeType e.g. 'image/jpeg', 'image/png'.
     * @return array{success:bool,reply:?string,message:string,http_status:int}
     */
    public static function analyzeImage(string $base64Image, string $mimeType, string $prompt): array
    {
        $body = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Image]],
                ],
            ]],
            'generationConfig' => [
                'maxOutputTokens' => self::MAX_OUTPUT_TOKENS,
                'responseMimeType' => 'application/json',
            ],
        ];

        return self::callGemini($body, 'ID verification');
    }

    /**
     * Shared HTTP call + response parsing for both sendMessage() and
     * analyzeImage() — the only thing that differs between them is the
     * request body (text-only conversation vs. one image+prompt turn).
     *
     * @return array{success:bool,reply:?string,message:string,http_status:int}
     */
    private static function callGemini(array $body, string $errorContext = 'Chatbot'): array
    {
        if (!self::isEnabled()) {
            return [
                'success' => false,
                'reply' => null,
                'message' => "$errorContext is not configured yet. Set GEMINI_API_KEY in .env to enable it (free key: aistudio.google.com/apikey).",
                'http_status' => 0,
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'reply' => null,
                'message' => 'PHP cURL extension is required for ' . lcfirst($errorContext) . '.',
                'http_status' => 0,
            ];
        }

        $model = defined('GEMINI_MODEL') && GEMINI_MODEL !== '' ? GEMINI_MODEL : 'gemini-flash-lite-latest';
        $url = self::API_BASE . rawurlencode($model) . ':generateContent?key=' . rawurlencode(GEMINI_API_KEY);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => ['content-type: application/json'],
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return [
                'success' => false,
                'reply' => null,
                'message' => "$errorContext connection failed: " . $error,
                'http_status' => $status,
            ];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'reply' => null,
                'message' => "$errorContext returned an unexpected response (HTTP $status).",
                'http_status' => $status,
            ];
        }

        if ($status === 429) {
            return [
                'success' => false,
                'reply' => null,
                'message' => 'Getting a lot of requests right now (free-tier limit). Please try again in a minute.',
                'http_status' => $status,
            ];
        }

        if ($status < 200 || $status >= 300) {
            return [
                'success' => false,
                'reply' => null,
                'message' => (string) ($decoded['error']['message'] ?? "$errorContext request failed (HTTP $status)."),
                'http_status' => $status,
            ];
        }

        if (!isset($decoded['candidates'][0])) {
            $blockReason = $decoded['promptFeedback']['blockReason'] ?? null;
            return [
                'success' => false,
                'reply' => null,
                'message' => $blockReason
                    ? "That could not be processed ($blockReason). Please try a different one."
                    : "$errorContext did not return a response.",
                'http_status' => $status,
            ];
        }

        $reply = '';
        foreach ($decoded['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (isset($part['text'])) {
                $reply .= $part['text'];
            }
        }

        if ($reply === '') {
            return [
                'success' => false,
                'reply' => null,
                'message' => "$errorContext did not return a text response.",
                'http_status' => $status,
            ];
        }

        return [
            'success' => true,
            'reply' => $reply,
            'message' => 'ok',
            'http_status' => $status,
        ];
    }
}
