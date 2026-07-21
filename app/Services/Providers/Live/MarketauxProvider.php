<?php

namespace App\Services\Providers\Live;

use App\Contracts\NewsProvider;
use App\Models\ApiRequestLog;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real Marketaux client. Not exercised until MARKETAUX_API_KEY is set and
 * PIPELINE_PROVIDER_MODE=live — swap the binding in PipelineServiceProvider.
 */
class MarketauxProvider implements NewsProvider
{
    private const BASE_URL = 'https://api.marketaux.com/v1';

    public function __construct(private readonly string $apiKey) {}

    public function fetchNewsForSymbols(array $symbols, int $limit = 10): array
    {
        $start = microtime(true);

        $response = Http::baseUrl(self::BASE_URL)->get('/news/all', [
            'symbols' => implode(',', $symbols),
            'limit' => $limit,
            'api_token' => $this->apiKey,
        ]);

        ApiRequestLog::create([
            'provider' => 'marketaux',
            'endpoint' => '/news/all',
            'response_code' => $response->status(),
            'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
            'error_message' => $response->failed() ? $response->body() : null,
            'cache_hit' => false,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Marketaux request failed: {$response->status()}");
        }

        return collect($response->json('data', []))
            ->map(fn (array $item) => [
                'uuid' => $item['uuid'],
                'title' => $item['title'],
                'summary' => $item['description'] ?? $item['title'],
                'source' => $item['source'] ?? 'Marketaux',
                'url' => $item['url'],
                'published_at' => $item['published_at'],
                'related_symbols' => collect($item['entities'] ?? [])->pluck('symbol')->filter()->values()->all(),
            ])
            ->all();
    }
}
