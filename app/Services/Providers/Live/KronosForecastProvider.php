<?php

namespace App\Services\Providers\Live;

use App\Contracts\PriceForecastProvider;
use App\Models\ApiRequestLog;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls a separate Python microservice (FastAPI) wrapping the Kronos
 * foundation model (https://github.com/shiyu-coder/Kronos). Not exercised
 * until that service exists and KRONOS_SERVICE_URL is set — swap the binding
 * in PipelineServiceProvider once it does.
 *
 * Expected service contract — POST {base}/forecast:
 *   body: {"symbol": string, "ohlc": [{date, open, high, low, close}, ...], "horizon_days": int}
 *   response: {"sample_count": int, "expected_low": number, "expected_high": number,
 *              "median_close": number, "upside_probability": number,
 *              "volatility_amplification_probability": number}
 *   (mirrors the live demo's Monte Carlo output: https://shiyu-coder.github.io/Kronos-demo/)
 */
class KronosForecastProvider implements PriceForecastProvider
{
    public function __construct(private readonly string $baseUrl) {}

    public function forecast(string $symbol, array $ohlc, int $horizonDays = 1): array
    {
        $start = microtime(true);

        $response = Http::baseUrl($this->baseUrl)->post('/forecast', [
            'symbol' => $symbol,
            'ohlc' => $ohlc,
            'horizon_days' => $horizonDays,
        ]);

        ApiRequestLog::create([
            'provider' => 'kronos',
            'endpoint' => '/forecast',
            'response_code' => $response->status(),
            'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
            'error_message' => $response->failed() ? $response->body() : null,
            'cache_hit' => false,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Kronos forecast request failed: {$response->status()}");
        }

        $data = $response->json();

        return [
            'horizon_days' => $horizonDays,
            'sample_count' => (int) $data['sample_count'],
            'expected_low' => (float) $data['expected_low'],
            'expected_high' => (float) $data['expected_high'],
            'median_close' => (float) $data['median_close'],
            'upside_probability' => (float) $data['upside_probability'],
            'volatility_amplification_probability' => (float) $data['volatility_amplification_probability'],
        ];
    }
}
