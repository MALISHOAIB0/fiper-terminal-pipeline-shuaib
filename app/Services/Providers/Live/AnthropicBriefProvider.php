<?php

namespace App\Services\Providers\Live;

use App\Contracts\AiBriefProvider;
use App\Models\ApiRequestLog;
use App\Models\Instrument;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real Anthropic client. Not exercised until ANTHROPIC_API_KEY is set and
 * PIPELINE_PROVIDER_MODE=live — swap the binding in PipelineServiceProvider.
 *
 * Two calls per brief (EN then AR) rather than one bilingual call, so a
 * failure or quality issue in one language never blocks or corrupts the other.
 */
class AnthropicBriefProvider implements AiBriefProvider
{
    private const BASE_URL = 'https://api.anthropic.com/v1';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $modelTierOne,
        private readonly string $modelStandard,
    ) {}

    public function generateBrief(Instrument $instrument, array $context, string $modelTier): array
    {
        $model = $modelTier === 'tier_one' ? $this->modelTierOne : $this->modelStandard;

        $en = $this->requestBrief($instrument, $context, $model, 'English');
        $ar = $this->requestBrief($instrument, $context, $model, 'Modern Standard Arabic (Fusha) — never a dialect');

        return [
            'en' => $en,
            'ar' => $ar,
            'bias' => $en['bias'] ?? 'neutral',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestBrief(Instrument $instrument, array $context, string $model, string $language): array
    {
        $prompt = $this->buildPrompt($instrument, $context, $language);
        $start = microtime(true);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post(self::BASE_URL.'/messages', [
            'model' => $model,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        ApiRequestLog::create([
            'provider' => 'anthropic',
            'endpoint' => '/messages',
            'response_code' => $response->status(),
            'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
            'error_message' => $response->failed() ? $response->body() : null,
            'cache_hit' => false,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Anthropic request failed: {$response->status()}");
        }

        $text = $response->json('content.0.text', '');
        $parsed = json_decode($text, true);

        if (! is_array($parsed)) {
            throw new RuntimeException('Anthropic response was not valid JSON: '.$text);
        }

        return $parsed;
    }

    private function buildPrompt(Instrument $instrument, array $context, string $language): string
    {
        $ohlcSummary = collect($context['ohlc'])->take(-7)->toJson();
        $newsSummary = collect($context['news'])->pluck('title')->implode('; ');
        $quote = $context['quote'];
        $forecast = $context['forecast'] ?? [];
        $indicators = $context['indicators'] ?? [];

        $indicatorsLine = $indicators['rsi_14'] !== null
            ? "RSI(14): {$indicators['rsi_14']}, MACD: {$indicators['macd']} (signal {$indicators['macd_signal']}, histogram {$indicators['macd_histogram']}). ".
                'Computed via ta_lib, not estimated by you — treat these as ground truth, do not recompute or contradict them.'
            : 'Technical indicators not yet available (insufficient history).';

        $forecastLine = ! empty($forecast)
            ? "Quantitative model forecast ({$forecast['sample_count']}-path Monte Carlo simulation, {$forecast['horizon_days']}d horizon): ".
                "expected range {$forecast['expected_low']}–{$forecast['expected_high']}, median {$forecast['median_close']}, ".
                "{$forecast['upside_probability']} probability of finishing higher, ".
                "{$forecast['volatility_amplification_probability']} probability of higher-than-recent volatility. ".
                'Treat this as one input, not a directive — weigh it against the news and technical context below.'
            : 'No quantitative forecast available for this run.';

        return <<<PROMPT
You are a professional market analyst writing for a MENA trading audience.

Instrument: {$instrument->symbol} ({$instrument->name})
Asset class: {$instrument->asset_class}
Current price: {$quote['price']}
24h change: {$quote['change_percent']}%
Recent OHLC (7 days): {$ohlcSummary}
Technical indicators: {$indicatorsLine}
Recent news: {$newsSummary}
{$forecastLine}

Write the brief in: {$language}

Generate a structured brief covering:
- Current context (1 paragraph)
- Key technical levels (support, resistance)
- Sentiment signal
- Notable catalysts
- Risks

Respond with ONLY valid JSON, no prose outside the JSON, matching this shape:
{"title": string, "summary": string, "key_levels": {"support": [number, number], "resistance": [number, number]}, "catalysts": string, "risks": string, "bias": "bullish"|"bearish"|"neutral"|"lean_bullish"|"lean_bearish"}
PROMPT;
    }
}
