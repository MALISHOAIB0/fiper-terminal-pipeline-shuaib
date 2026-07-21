<?php

namespace App\Jobs;

use App\Contracts\AiBriefProvider;
use App\Contracts\PriceForecastProvider;
use App\Models\Instrument;
use App\Services\TechnicalIndicatorsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One instrument, one job. This is what replaces the documented "no queue
 * worker exists" gap — analytics:refresh-briefs dispatches one of these per
 * instrument instead of looping and calling Anthropic synchronously in the
 * scheduler process.
 */
class GenerateInstrumentBrief implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $instrumentId) {}

    public function handle(
        AiBriefProvider $briefProvider,
        PriceForecastProvider $forecastProvider,
        TechnicalIndicatorsService $indicatorsService,
    ): void {
        $instrument = Instrument::find($this->instrumentId);

        if (! $instrument || ! $instrument->is_active) {
            return;
        }

        // 60 days of lookback: MACD(12,26,9) needs 35+ closes to produce a
        // signal value, more than the ~7-30 days the brief actually displays.
        $ohlc = $instrument->ohlcDaily()
            ->orderByDesc('date')
            ->limit(60)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($row) => [
                'date' => $row->date->toDateString(),
                'open' => (float) $row->open,
                'high' => (float) $row->high,
                'low' => (float) $row->low,
                'close' => (float) $row->close,
            ])
            ->all();

        $context = [
            'ohlc' => $ohlc,

            'quote' => $instrument->quoteSnapshots()
                ->latest('quoted_at')
                ->first()
                ?->only(['price', 'change', 'change_percent']),

            'news' => $instrument->newsArticles()
                ->latest('published_at')
                ->limit(5)
                ->get(['title', 'published_at'])
                ->toArray(),

            // Additive quantitative layer — see PriceForecastProvider. Empty
            // array (not an error) when there isn't enough OHLC history yet.
            'forecast' => empty($ohlc) ? [] : $forecastProvider->forecast($instrument->symbol, $ohlc, horizonDays: 1),

            // Real RSI/MACD via ta_lib, replacing raw OHLC dumped at the LLM
            // and hoping it computes indicators correctly itself.
            'indicators' => $indicatorsService->compute($ohlc),
        ];

        if (empty($context['ohlc']) || ! $context['quote']) {
            Log::warning('Skipping brief generation: no OHLC/quote data yet', ['symbol' => $instrument->symbol]);

            return;
        }

        $modelTier = $instrument->is_tier_one ? 'tier_one' : 'standard';
        $brief = $briefProvider->generateBrief($instrument, $context, $modelTier);

        $instrument->update([
            'ai_brief_en' => $brief['en'],
            'ai_brief_ar' => $brief['ar'],
            'ai_bias' => $brief['bias'],
            'analytics_refreshed_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateInstrumentBrief failed', [
            'instrument_id' => $this->instrumentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
