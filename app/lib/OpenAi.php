<?php
declare(strict_types=1);

namespace App;

final class OpenAi
{
    public const CHAT_MODELS = [
        'gpt-4o-mini' => 'GPT-4o mini — fastest and cheapest, good default',
        'gpt-4o' => 'GPT-4o — strongest general model',
        'gpt-4.1-mini' => 'GPT-4.1 mini — cheap, long context',
        'gpt-4.1' => 'GPT-4.1 — high quality, long context',
    ];

    public const EMBEDDING_MODELS = [
        'text-embedding-3-small' => 'text-embedding-3-small — cheapest, recommended',
        'text-embedding-3-large' => 'text-embedding-3-large — highest quality',
    ];

    private string $apiKey;
    private string $baseUrl;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey ?? Settings::openAiKey();
        $this->baseUrl = rtrim($baseUrl ?? (string)Settings::get('openai_base_url', 'https://api.openai.com/v1'), '/');

        if ($this->apiKey === '') {
            throw new OpenAiException('No OpenAI API key is configured. Add it under Settings.');
        }
    }

    /**
     * @param array<int, array{role:string, content:string}> $messages
     * @return array{content:string, prompt_tokens:int, completion_tokens:int}
     */
    public function chat(array $messages, string $model, float $temperature = 0.3, int $maxTokens = 600): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        $data = $this->post('/chat/completions', $payload, 90);

        return [
            'content' => trim((string)($data['choices'][0]['message']['content'] ?? '')),
            'prompt_tokens' => (int)($data['usage']['prompt_tokens'] ?? 0),
            'completion_tokens' => (int)($data['usage']['completion_tokens'] ?? 0),
        ];
    }

    /**
     * @param string[] $inputs
     * @return array<int, float[]>
     */
    public function embed(array $inputs, string $model = 'text-embedding-3-small'): array
    {
        if ($inputs === []) {
            return [];
        }
        $data = $this->post('/embeddings', ['model' => $model, 'input' => array_values($inputs)], 120);

        $vectors = [];
        foreach ($data['data'] ?? [] as $item) {
            $vectors[(int)$item['index']] = array_map('floatval', $item['embedding'] ?? []);
        }
        ksort($vectors);

        if (count($vectors) !== count($inputs)) {
            throw new OpenAiException('OpenAI returned fewer embeddings than requested.');
        }
        return array_values($vectors);
    }

    /**
     * Speech to text for the widget's voice button.
     * Uses multipart/form-data, so it posts directly rather than through post().
     */
    public function transcribe(string $filePath, string $filename, string $model = 'whisper-1'): string
    {
        if (!function_exists('curl_init')) {
            throw new OpenAiException('Voice messages need the cURL extension, which this server does not have.');
        }

        $ch = curl_init($this->baseUrl . '/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey],
            CURLOPT_POSTFIELDS => [
                'model' => $model,
                'file' => new \CURLFile($filePath, 'application/octet-stream', $filename),
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new OpenAiException('Could not reach OpenAI: ' . $error);
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) {
            throw new OpenAiException('Unexpected response from OpenAI (HTTP ' . $status . ').');
        }
        if ($status >= 400) {
            throw new OpenAiException((string)($data['error']['message'] ?? 'Transcription failed.'));
        }
        return trim((string)($data['text'] ?? ''));
    }

    public function ping(): string
    {
        $result = $this->chat(
            [['role' => 'user', 'content' => 'Reply with the single word: ok']],
            (string)Settings::get('default_chat_model', 'gpt-4o-mini'),
            0.0,
            10
        );
        return $result['content'];
    }

    private function post(string $path, array $payload, int $timeout): array
    {
        $response = Http::request(
            'POST',
            $this->baseUrl . $path,
            [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $timeout
        );

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new OpenAiException('Unexpected response from OpenAI (HTTP ' . $response['status'] . ').');
        }
        if ($response['status'] >= 400) {
            $message = $data['error']['message'] ?? 'OpenAI request failed (HTTP ' . $response['status'] . ').';
            throw new OpenAiException((string)$message);
        }
        return $data;
    }
}
