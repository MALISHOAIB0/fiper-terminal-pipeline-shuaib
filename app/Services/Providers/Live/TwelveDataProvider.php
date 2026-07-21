<?php

namespace App\Services\Providers\Live;

use App\Contracts\MarketDataProvider;
use App\Models\ApiRequestLog;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real TwelveData client. Not exercised until TWELVEDATA_API_KEY is set and
 * PIPELINE_PROVIDER_MODE=live — swap the binding in PipelineServiceProvider.
 */
class TwelveDataProvider implements MarketDataProvider
{
    private const BASE_URL = 'https://api.twelvedata.com';

    public function __construct(private readonly string $apiKey) {}

    public function fetchDailyOhlc(string $symbol, int $days): array
    {
        $response = $this->request('/time_series', [
            'symbol' => $symbol,
            'interval' => '1day',
            'outputsize' => $days,
            'apikey' => $this->apiKey,
        ]);

        $values = $response['values'] ?? [];

        return collect($values)
            ->reverse()
            ->map(fn (array $row) => [
                'date' => $row['datetime'],
                'open' => (float) $row['open'],
                'high' => (float) $row['high'],
                'low' => (float) $row['low'],
                'close' => (float) $row['close'],
                'volume' => isset($row['volume']) ? (int) $row['volume'] : null,
            ])
            ->values()
            ->all();
    }

    public function fetchQuote(string $symbol): array
    {
        $row = $this->request('/quote', [
            'symbol' => $symbol,
            'apikey' => $this->apiKey,
        ]);

        return [
            'price' => (float) $row['close'],
            'change' => (float) $row['change'],
            'change_percent' => (float) $row['percent_change'],
            'volume' => isset($row['volume']) ? (int) $row['volume'] : null,
            'quoted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $query): array
    {
        $start = microtime(true);

        $response = Http::baseUrl(self::BASE_URL)->get($endpoint, $query);

        ApiRequestLog::create([
            'provider' => 'twelvedata',
            'endpoint' => $endpoint,
            'response_code' => $response->status(),
            'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
            'error_message' => $response->failed() ? $response->body() : null,
            'cache_hit' => false,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("TwelveData request to {$endpoint} failed: {$response->status()}");
        }

        return $response->json();
    }
}
