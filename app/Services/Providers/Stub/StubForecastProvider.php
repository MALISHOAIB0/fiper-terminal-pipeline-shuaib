<?php

namespace App\Services\Providers\Stub;

use App\Contracts\PriceForecastProvider;

/**
 * Placeholder for the Kronos forecasting model. This is NOT a predictive
 * model — it bootstraps N random-walk paths from the recent daily-return
 * distribution (mean/stdev), which structurally mirrors what Kronos's Monte
 * Carlo sampling produces (see https://shiyu-coder.github.io/Kronos-demo/)
 * without any learned signal. Good enough to exercise the pipeline and the
 * two real headline metrics (upside probability, volatility amplification
 * probability) end to end before the actual model is wired in.
 *
 * Swap the binding in PipelineServiceProvider for Live\KronosForecastProvider
 * once the Kronos microservice exists.
 */
class StubForecastProvider implements PriceForecastProvider
{
    private const SAMPLE_COUNT = 30;

    public function forecast(string $symbol, array $ohlc, int $horizonDays = 1): array
    {
        $closes = array_column($ohlc, 'close');
        $lastClose = end($closes) ?: 0.0;

        if (count($closes) < 5) {
            return [
                'horizon_days' => $horizonDays,
                'sample_count' => 0,
                'expected_low' => $lastClose,
                'expected_high' => $lastClose,
                'median_close' => $lastClose,
                'upside_probability' => 0.5,
                'volatility_amplification_probability' => 0.5,
            ];
        }

        $returns = [];
        for ($i = 1; $i < count($closes); $i++) {
            if ($closes[$i - 1] != 0) {
                $returns[] = ($closes[$i] - $closes[$i - 1]) / $closes[$i - 1];
            }
        }

        $historicalStdDev = $this->stdDev($returns);
        $seed = crc32($symbol.'|forecast') ?: 7;

        $finalPrices = [];
        $pathStdDevs = [];

        for ($path = 0; $path < self::SAMPLE_COUNT; $path++) {
            $price = $lastClose;
            $pathReturns = [];

            for ($step = 0; $step < $horizonDays; $step++) {
                $shock = ($this->nextRandom($seed) - 0.5) * 2 * $historicalStdDev * sqrt(3);
                $pathReturns[] = $shock;
                $price *= (1 + $shock);
            }

            $finalPrices[] = $price;
            $pathStdDevs[] = $this->stdDev($pathReturns);
        }

        sort($finalPrices);

        $upsideCount = count(array_filter($finalPrices, fn ($p) => $p > $lastClose));

        // A 1-step path has a single return, so its "stdev" is always 0 —
        // volatility amplification isn't a computable signal below a 2-day
        // horizon. Report neutral (0.5) rather than a spuriously confident 0%.
        $volatilityAmplificationProbability = $horizonDays < 2
            ? 0.5
            : count(array_filter($pathStdDevs, fn ($sd) => $sd > $historicalStdDev)) / self::SAMPLE_COUNT;

        return [
            'horizon_days' => $horizonDays,
            'sample_count' => self::SAMPLE_COUNT,
            'expected_low' => round($this->percentile($finalPrices, 10), 6),
            'expected_high' => round($this->percentile($finalPrices, 90), 6),
            'median_close' => round($this->percentile($finalPrices, 50), 6),
            'upside_probability' => round($upsideCount / self::SAMPLE_COUNT, 4),
            'volatility_amplification_probability' => round($volatilityAmplificationProbability, 4),
        ];
    }

    /** @param  array<int, float>  $values */
    private function stdDev(array $values): float
    {
        if (count($values) < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / count($values);

        return sqrt($variance);
    }

    /** @param  array<int, float>  $sorted */
    private function percentile(array $sorted, int $p): float
    {
        $index = (int) round(($p / 100) * (count($sorted) - 1));

        return $sorted[$index];
    }

    private function nextRandom(int &$seed): float
    {
        $seed = ($seed * 1103515245 + 12345) % 2147483648;

        return $seed / 2147483648;
    }
}
