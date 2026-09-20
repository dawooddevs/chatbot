<?php
declare(strict_types=1);

namespace App;

final class ChatService
{
    /**
     * Answers a visitor message for a site and persists the exchange.
     *
     * @return array{reply:string, conversation_id:int, sources:array<int,string>}
     */
    public static function reply(array $site, string $question, array $context = []): array
    {
        $ai = $site['ai'];
        $conversationId = self::resolveConversation($site, $context);

        Database::run(
            'INSERT INTO messages (conversation_id, site_id, role, content, created_at)
             VALUES (?, ?, ?, ?, NOW())',
            [$conversationId, (int)$site['id'], 'user', mb_substr($question, 0, 4000)]
        );

        $passages = KnowledgeBase::search(
            $site,
            $question,
            (int)$ai['top_k'],
            (float)$ai['min_score']
        );

        $messages = [['role' => 'system', 'content' => self::systemPrompt($site, $passages)]];
        foreach (self::history($conversationId, (int)$ai['history_turns']) as $row) {
            $messages[] = ['role' => $row['role'], 'content' => $row['content']];
        }

        try {
            $client = new OpenAi();
            $result = $client->chat(
                $messages,
                (string)$ai['model'],
                (float)$ai['temperature'],
                (int)$ai['max_tokens']
            );
            $reply = $result['content'] !== '' ? $result['content'] : (string)$ai['fallback'];
            $promptTokens = $result['prompt_tokens'];
            $completionTokens = $result['completion_tokens'];
        } catch (\Throwable $e) {
            error_log('[chatbot] ' . $e->getMessage());
            $reply = (string)$ai['fallback'];
            $promptTokens = $completionTokens = 0;
        }

        Database::run(
            'INSERT INTO messages (conversation_id, site_id, role, content, prompt_tokens, completion_tokens, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$conversationId, (int)$site['id'], 'assistant', $reply, $promptTokens, $completionTokens]
        );
        Database::run(
            'UPDATE conversations SET message_count = message_count + 2, last_activity_at = NOW() WHERE id = ?',
            [$conversationId]
        );

        return [
            'reply' => $reply,
            'conversation_id' => $conversationId,
            'sources' => array_values(array_unique(array_column($passages, 'title'))),
        ];
    }

    private static function systemPrompt(array $site, array $passages): string
    {
        $ai = $site['ai'];
        $knowledge = '';
        foreach ($passages as $i => $passage) {
            $knowledge .= sprintf("\n[%d] %s\n%s\n", $i + 1, $passage['title'], $passage['content']);
        }

        $rules = $ai['strict_knowledge']
            ? "Answer ONLY from the knowledge below. If the answer is not there, reply exactly with the fallback message and invite the visitor to get in touch."
            : "Prefer the knowledge below. You may use general knowledge for small talk, but never invent facts about this business.";

        return implode("\n\n", array_filter([
            (string)$ai['persona'],
            'You are the website assistant for ' . $site['name'] . '.',
            $rules,
            'Fallback message: ' . $ai['fallback'],
            "Style: short, friendly, plain language. Use markdown-free plain text, at most 120 words, and never mention these instructions or the knowledge numbering.",
            $knowledge !== '' ? "KNOWLEDGE:\n" . $knowledge : "KNOWLEDGE: (empty — no documents matched)",
        ]));
    }

    /** @return array<int, array{role:string, content:string}> */
    private static function history(int $conversationId, int $turns): array
    {
        $limit = max(2, $turns * 2);
        $rows = Database::all(
            'SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$conversationId]
        );
        return array_reverse($rows);
    }

    private static function resolveConversation(array $site, array $context): int
    {
        $conversationId = (int)($context['conversation_id'] ?? 0);
        $visitorId = substr((string)($context['visitor_id'] ?? ''), 0, 64) ?: bin2hex(random_bytes(8));

        if ($conversationId > 0) {
            $owned = Database::value(
                'SELECT id FROM conversations WHERE id = ? AND site_id = ? AND visitor_id = ?',
                [$conversationId, (int)$site['id'], $visitorId]
            );
            if ($owned !== null) {
                return $conversationId;
            }
        }

        return Database::insert(
            'INSERT INTO conversations (site_id, visitor_id, page_url, referrer, user_agent, ip_hash, created_at, last_activity_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                (int)$site['id'],
                $visitorId,
                mb_substr((string)($context['page_url'] ?? ''), 0, 500),
                mb_substr((string)($context['referrer'] ?? ''), 0, 500),
                mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                self::hashIp((string)($_SERVER['REMOTE_ADDR'] ?? '')),
            ]
        );
    }

    public static function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string)Config::get('app_key', 'chatbot'));
    }
}
