<?php

namespace App\Contracts;

interface PriceForecastProvider
{
    /**
     * Shape matches what Kronos actually produces (see the live demo:
     * https://shiyu-coder.github.io/Kronos-demo/) — Monte Carlo sampling over
     * N autoregressive paths, summarized as a price band plus two
     * probabilities. Deliberately NOT a single "confidence" score — that
     * isn't a real Kronos output, and inventing one would be dishonest to
     * whoever reads the brief downstream.
     *
     * @param  array<int, array{date: string, open: float, high: float, low: float, close: float}>  $ohlc
     *   Recent daily history, oldest first — the same window already fetched for the brief context.
     * @return array{
     *     horizon_days: int,
     *     sample_count: int,
     *     expected_low: float,
     *     expected_high: float,
     *     median_close: float,
     *     upside_probability: float,
     *     volatility_amplification_probability: float,
     * }
     *   upside_probability: share of sampled paths ending above the current close.
     *   volatility_amplification_probability: share of sampled paths with higher realized volatility than the recent window.
     */
    public function forecast(string $symbol, array $ohlc, int $horizonDays = 1): array;
}
