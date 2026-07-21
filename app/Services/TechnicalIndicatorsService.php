<?php

namespace App\Services;

/**
 * Thin wrapper around the ta_lib PHP extension (github.com/TA-Lib/ext-ta-lib).
 * Computes real indicators from OHLC closes instead of the pipeline passing
 * raw price arrays to the AI brief provider and hoping the LLM does the math.
 */
class TechnicalIndicatorsService
{
    /**
     * @param  array<int, array{close: float}>  $ohlc  oldest first
     * @return array{rsi_14: float|null, macd: float|null, macd_signal: float|null, macd_histogram: float|null}
     */
    public function compute(array $ohlc): array
    {
        $closes = array_column($ohlc, 'close');

        return [
            'rsi_14' => $this->lastOrNull(count($closes) >= 15 ? ta_rsi($closes, 14) : null),
            ...$this->macd($closes),
        ];
    }

    /** @param  array<int, float>  $closes */
    private function macd(array $closes): array
    {
        if (count($closes) < 35) {
            return ['macd' => null, 'macd_signal' => null, 'macd_histogram' => null];
        }

        $result = ta_macd($closes, 12, 26, 9);

        return [
            'macd' => $this->lastOrNull($result['macd']),
            'macd_signal' => $this->lastOrNull($result['signal']),
            'macd_histogram' => $this->lastOrNull($result['hist']),
        ];
    }

    private function lastOrNull(?array $series): ?float
    {
        if (empty($series)) {
            return null;
        }

        $value = end($series);

        return is_numeric($value) && is_finite((float) $value) ? round((float) $value, 4) : null;
    }
}
