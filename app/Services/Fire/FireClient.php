<?php

namespace App\Services\Fire;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Minimal client for Fire's POST /v1/chat (https://fire.prosaga.net/v1/capabilities).
 *
 * Request:  {messages: [{role, content}], system_prompt?, species_name?, temperature?, max_tokens?}
 * Response: {content, model, call_id, usage: {input, output}, ...}
 */
class FireClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly ?string $model,
        private readonly int $timeoutSeconds = 60,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: rtrim((string) config('services.fire.base_url'), '/'),
            apiKey: config('services.fire.key'),
            model: config('services.fire.model'),
            timeoutSeconds: (int) config('services.fire.timeout', 60),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function model(): ?string
    {
        return $this->model;
    }

    /**
     * Run several chat requests concurrently.
     *
     * @param  array<string|int, array{system: string, user: string, max_tokens?: int}>  $requests
     * @return array<string|int, array{ok: bool, content?: string, model?: string, error?: string}>
     */
    public function chatMany(array $requests): array
    {
        if (! $this->isConfigured()) {
            return array_map(fn () => ['ok' => false, 'error' => 'FIRE_API_KEY is not set.'], $requests);
        }

        $responses = Http::pool(function (Pool $pool) use ($requests) {
            foreach ($requests as $key => $request) {
                $pool->as((string) $key)
                    ->withToken($this->apiKey)
                    ->acceptJson()
                    ->timeout($this->timeoutSeconds)
                    ->post($this->baseUrl.'/v1/chat', $this->payload($request));
            }
        });

        $results = [];
        foreach ($requests as $key => $request) {
            $results[$key] = $this->interpret($responses[(string) $key] ?? null);
        }

        return $results;
    }

    private function payload(array $request): array
    {
        return array_filter([
            'messages' => [['role' => 'user', 'content' => $request['user']]],
            'system_prompt' => $request['system'],
            'species_name' => $this->model,
            'temperature' => 0.2,
            'max_tokens' => $request['max_tokens'] ?? 700,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function interpret(mixed $response): array
    {
        if ($response instanceof Throwable) {
            return ['ok' => false, 'error' => 'Could not reach Fire: '.$response->getMessage()];
        }
        if (! $response instanceof Response) {
            return ['ok' => false, 'error' => 'No response from Fire.'];
        }
        if ($response->failed()) {
            $detail = $response->json('error.message') ?? $response->json('error') ?? $response->json('message') ?? mb_substr($response->body(), 0, 300);

            return ['ok' => false, 'error' => 'Fire returned HTTP '.$response->status().': '.(is_string($detail) ? $detail : json_encode($detail))];
        }

        $content = $response->json('content');
        if (! is_string($content) || trim($content) === '') {
            return ['ok' => false, 'error' => 'Fire returned an empty response.'];
        }

        return ['ok' => true, 'content' => $content, 'model' => $response->json('model')];
    }
}
