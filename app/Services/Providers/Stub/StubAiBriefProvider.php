<?php

namespace App\Services\Providers\Stub;

use App\Contracts\AiBriefProvider;
use App\Models\Instrument;

/**
 * Deterministic bilingual brief generator — no Anthropic call. Produces the
 * same structured shape (title/summary/key_levels/catalysts/risks/bias) the
 * real AnthropicBriefProvider returns, so the rest of the pipeline (storage,
 * queue, scheduler) can be built and verified before a live API key exists.
 */
class StubAiBriefProvider implements AiBriefProvider
{
    public function generateBrief(Instrument $instrument, array $context, string $modelTier): array
    {
        $quote = $context['quote'];
        $ohlc = $context['ohlc'];
        $indicators = $context['indicators'] ?? [];

        $changePercent = $quote['change_percent'];
        $bias = $this->biasFor($changePercent, $indicators['rsi_14'] ?? null);

        $closes = array_column($ohlc, 'close');
        $lows = array_column($ohlc, 'low');
        $highs = array_column($ohlc, 'high');
        $support = round(min($lows) * 1.002, 2);
        $support2 = round(min($lows) * 0.994, 2);
        $resistance = round(max($highs) * 0.998, 2);
        $resistance2 = round(max($highs) * 1.006, 2);

        $direction = $changePercent >= 0 ? 'up' : 'down';
        $nameAr = $instrument->name_localized ?? $instrument->name;

        $forecast = $context['forecast'] ?? [];
        $forecastLineEn = ! empty($forecast)
            ? ' Quantitative model ('.($forecast['sample_count'] ?? 0)."-path simulation) for the next {$forecast['horizon_days']}d: ".
                "range {$forecast['expected_low']}–{$forecast['expected_high']}, ".
                round($forecast['upside_probability'] * 100)."% of paths finish higher, ".
                round($forecast['volatility_amplification_probability'] * 100).'% show higher volatility than recent history.'
            : '';
        $forecastLineAr = ! empty($forecast)
            ? ' نموذج كمي (محاكاة '.($forecast['sample_count'] ?? 0)." مسار) لـ{$forecast['horizon_days']} يوم القادمة: ".
                "النطاق {$forecast['expected_low']}–{$forecast['expected_high']}، ".
                round($forecast['upside_probability'] * 100).'% من المسارات تنتهي أعلى، ونسبة '.
                round($forecast['volatility_amplification_probability'] * 100).'% منها تُظهر تقلباً أعلى من المعتاد.'
            : '';

        $rsi = $indicators['rsi_14'] ?? null;
        $indicatorsLineEn = $rsi !== null
            ? " RSI(14): {$rsi}".($indicators['macd'] !== null ? ", MACD: {$indicators['macd']} (signal {$indicators['macd_signal']})" : '').'.'
            : '';
        $indicatorsLineAr = $rsi !== null
            ? " مؤشر القوة النسبية RSI(14): {$rsi}".($indicators['macd'] !== null ? "، MACD: {$indicators['macd']} (الإشارة {$indicators['macd_signal']})" : '').'.'
            : '';

        return [
            'en' => [
                'title' => "{$instrument->short_name} Holds Near Key Levels After ".
                    ($direction === 'up' ? 'a Move Higher' : 'a Pullback'),
                'summary' => "{$instrument->name} is trading at {$quote['price']}, ".
                    ($direction === 'up' ? 'up ' : 'down ').abs($changePercent)."% over the last session. ".
                    "Price action is consolidating between {$support} and {$resistance}, with volume in line ".
                    'with recent averages. [Model tier: '.$modelTier.', stub provider — no live market read.]'.
                    $indicatorsLineEn.$forecastLineEn,
                'key_levels' => [
                    'support' => [$support, $support2],
                    'resistance' => [$resistance, $resistance2],
                ],
                'indicators' => $indicators,
                'forecast' => $forecast,
                'catalysts' => 'Stub data — no live news/catalyst feed connected yet in this environment.',
                'risks' => "A sustained break below {$support2} would expose the next support zone. ".
                    'Broader risk sentiment could move this instrument outside the current range.',
            ],
            'ar' => [
                'title' => "{$nameAr} يتماسك قرب مستويات رئيسية بعد ".
                    ($direction === 'up' ? 'ارتفاع' : 'تراجع'),
                'summary' => "يتداول {$nameAr} عند {$quote['price']}، ".
                    ($direction === 'up' ? 'بارتفاع ' : 'بتراجع ').abs($changePercent).'% خلال الجلسة الأخيرة. '.
                    "يتماسك السعر بين {$support} و{$resistance}، مع حجم تداول متماشٍ مع المتوسط الأخير. ".
                    '(بيانات تجريبية — لا يوجد اتصال ببيانات سوق حية بعد في هذه البيئة.)'.
                    $indicatorsLineAr.$forecastLineAr,
                'key_levels' => [
                    'support' => [$support, $support2],
                    'resistance' => [$resistance, $resistance2],
                ],
                'indicators' => $indicators,
                'forecast' => $forecast,
                'catalysts' => 'بيانات تجريبية — لا يوجد اتصال حالياً بمصدر أخبار أو محفزات حية.',
                'risks' => "أي اختراق مستمر تحت {$support2} قد يعرّض السعر لمنطقة الدعم التالية. ".
                    'أي تحول عام بمعنويات السوق قد يخرج السعر عن النطاق الحالي.',
            ],
            'bias' => $bias,
        ];
    }

    /**
     * Momentum sets the base read; RSI extremes cap it — a genuinely
     * overbought/oversold reading tempers a pure price-momentum bias rather
     * than letting the two disagree silently in the output.
     */
    private function biasFor(float $changePercent, ?float $rsi): string
    {
        $bias = match (true) {
            $changePercent >= 1.5 => 'bullish',
            $changePercent >= 0.3 => 'lean_bullish',
            $changePercent <= -1.5 => 'bearish',
            $changePercent <= -0.3 => 'lean_bearish',
            default => 'neutral',
        };

        if ($rsi !== null && $rsi >= 70 && in_array($bias, ['bullish', 'lean_bullish'], true)) {
            return 'lean_bullish';
        }

        if ($rsi !== null && $rsi <= 30 && in_array($bias, ['bearish', 'lean_bearish'], true)) {
            return 'lean_bearish';
        }

        return $bias;
    }
}
